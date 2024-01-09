<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Attendance handler class
 *
 * @package     local_zoom_attendance_sync
 * @copyright   2023 e-Mentor srl <service@e-mentor.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zoom_attendance_sync\handlers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/zoom_attendance_sync/locallib.php');

/**
 * Class 	attendance_handler
 * @package local_zoom_attendance_sync\handlers
 */
class attendance_handler
{

    private $course_id;

    function __construct($course_id) 
    {
        $this->course_id = $course_id;
    }

    /**
     * Checks if there is an attendance module inside the course
     *
     * @return boolean $session_id
     * 
     */
    function has_attendance_module()
    {
        global $DB;

        $attendance = $DB->get_record("attendance",array("course"=>$this->course_id));

        if(!$attendance) return false;

        return true;
    }

    /**
     * Returns attendance of the course
     *
     * @return stdClass $attendance
     * 
     */
    function get_attendance_module()
    {
        global $DB;

        $attendance = $DB->get_record("attendance",array("course"=>$this->course_id));

        if(!$attendance) return NULL;

        return $attendance;
    }

    /**
     * Process data from zoom api
     *
     * @param stdClass $meeting
     * @param stdClass $meeting_data
     * @return void
     * 
     */
    function process_data($meeting, $meeting_data)
    {
        $passing_percentage = get_passing_percentage($meeting->id);

        if(!empty($meeting_data->code) && $meeting_data->code==3001) 
            return;
        
        $attendance_info = $this->get_attendance_info($meeting->id);
        $course_students = course_get_students($meeting->course);
        
        $duration = $meeting->duration;
        $passing_duration = ($meeting->duration*$passing_percentage)/100;

        $participants_timing = $this->get_participants_timing($meeting_data->participants,$course_students);

        foreach($participants_timing as $user_id=>$participant_timing){
            
            if($participant_timing>=$passing_duration){
                $this->save_student_as_present($user_id,$meeting->id,$participant_timing,$attendance_info);
            }
            else {
                $this->save_student_as_absent($user_id,$meeting->id,$participant_timing,$attendance_info);
            }

        }

        $this->save_meeting_as_processed($meeting->id);

    }

    /**
     * Saves meeting as processed
     *
     * @param int $zoom_id
     * @return void
     * 
     */
    function save_meeting_as_processed($zoom_id)
    {
        global $DB;
	
        $zoom_processed_meeting = new \StdClass();
        $zoom_processed_meeting->zoom_id = $zoom_id;
        $zoom_processed_meeting->activity = 'report';
        $zoom_processed_meeting->time = time();
        
        $zoom_processed_meeting->id = $DB->insert_record("zoom_attendance_processed",$zoom_processed_meeting);
    }

    /**
     * Returns attendance session id
     *
     * @param stdClass $participants_data
     * @param array $students
     * @return array timing for each student
     * 
     */
    function get_participants_timing($participants_data,$students)
    {
        $pariticipants_timing = array();

        foreach($participants_data as $participant_data){

            // checks user id by email, name
            $user_id = $this->get_user_id($participant_data, $students);

            if($user_id && $participant_data->status==IN_MEETING){
                if(!isset($pariticipants_timing[$user_id])) {
                    $pariticipants_timing[$user_id] = 0;
                }

                $pariticipants_timing[$user_id] += $participant_data->duration;
            }

        }

        return $pariticipants_timing;
    }

    /**
     * Returns user id searched by name and email
     *
     * @param stdClass $participant_data
     * @param array $students
     * @return int $user_id
     * 
     */
    function get_user_id($participant_data, $students)
    {
        global $DB;

        $name = strtolower(trim($participant_data->name));
        $email = strtolower(trim($participant_data->email));
        
        foreach($students as $student){
        
            $firstname_stud = strtolower(trim($student->firstname));
            $lastname_stud = strtolower(trim($student->lastname));
            $email_student = strtolower(trim($student->email));

            if($email_student == $email)
                return $student->userid;
            
            if($name == ($firstname_stud." ".$lastname_stud) || $name == ($lastname_stud." ".$firstname_stud))
                return $student->userid; 

        }

        return NULL;

    }

    /**
     * Assign presence to user
     *
     * @param int $user_id
     * @param int $meeting_id
     * @param int $duration
     * @param stdClass $attendance_info
     * @return int attendance_log id
     * 
     */
    function save_student_as_present($user_id, $meeting_id, $duration, $attendance_info)
    {
        global $DB;

        $attendance_log = new \StdClass();
        $attendance_log->takenby = 2;
        $attendance_log->statusset = "'" . $attendance_info->present_status->id . "," . $attendance_info->absent_status->id . "'";
        $attendance_log->statusid = $attendance_info->present_status->id;
        $attendance_log->sessionid = $attendance_info->session->id;
        $attendance_log->studentid = $user_id;
        $attendance_log->timetaken = time();
        $attendance_log->remarks = seconds_to_pritable_hour($duration);

        $attendance_log->id = $DB->insert_record("attendance_log",$attendance_log);

        // refresh to see new data
        $sess = $DB->get_record("attendance_sessions",array("id"=>$attendance_info->session->id));
        $sess->lasttakenby = 2;
        $sess->lasttaken = time();
        $DB->update_record("attendance_sessions",$sess);

        return $attendance_log->id;
    }

    /**
     * Assign absence to user
     *
     * @param int $user_id
     * @param int $meeting_id
     * @param int $duration
     * @param stdClass $attendance_info
     * @return int attendance_log id
     * 
     */
    function save_student_as_absent($user_id, $meeting_id, $duration, $attendance_info)
    {
        global $DB;

        $attendance_log = new \StdClass();
        $attendance_log->takenby = 2;
        $attendance_log->statusset = "'" . $attendance_info->present_status->id . "," . $attendance_info->absent_status->id . "'";
        $attendance_log->statusid = $attendance_info->absent_status->id;
        $attendance_log->sessionid = $attendance_info->session->id;
        $attendance_log->studentid = $user_id;
        $attendance_log->timetaken = time();
        $attendance_log->remarks = seconds_to_pritable_hour($duration);

        $attendance_log->id = $DB->insert_record("attendance_log",$attendance_log);

        // refresh to see new data
        $sess = $DB->get_record("attendance_sessions",array("id"=>$attendance_info->session->id));
        $sess->lasttakenby=2;
        $sess->lasttaken=time();
        $DB->update_record("attendance_sessions",$sess);

        return $attendance_log->id;
    }

    /**
     * Returns main attendance information
     *
     * @param int $meeting_id
     * @return stdClass main attendance information
     * 
     */
    function get_attendance_info($meeting_id){
        
        global $DB;

        $attendance_info = array();
        $attendance_module_data = $DB->get_record("zoom_attendance_module_data",array("zoom_id"=>$meeting_id));

        if($attendance_module_data){
            $session = $DB->get_record("attendance_sessions",array("id"=>$attendance_module_data->session_id));
            $present_status = $DB->get_record("attendance_statuses",array("acronym"=>"P","attendanceid"=>$session->attendanceid,"setnumber"=>$session->statusset));
            $absent_status = $DB->get_record("attendance_statuses",array("acronym"=>"A","attendanceid"=>$session->attendanceid,"setnumber"=>$session->statusset));
        
            $attendance_info["session"] = $session;
            $attendance_info["present_status"] = $present_status;
            $attendance_info["absent_status"] = $absent_status;
        }   

        return (object) $attendance_info;           
                    
    }

}