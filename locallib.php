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
 * Plugin administration pages are defined here.
 *
 * @package     local_zoom_attendance_sync
 * @copyright   2023 e-Mentor srl <service@e-mentor.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot . '/mod/attendance/locallib.php');


/* in meeting status for zoom meetings report */

define("IN_MEETING","in_meeting");


/**
 * Returns all zoom meetings
 *
 * @return array
 *
 */
function get_meetings(){

    global $DB;

    $meetings = $DB->get_records("zoom");

    return $meetings;
}

/**
 * Returns zoom meeting extra data
 *
 * @param int $module_id
 * @return stdClass
 *
 */
function get_zoom_attendance_module_data($module_id){
    
    global $DB;

    $course_module = $DB->get_record("course_modules",array("id"=>$module_id));
    
    if(!empty($course_module)){
        $zoom_attendance_module_data = $DB->get_record("zoom_attendance_module_data",array("zoom_id"=>$course_module->instance));
        return $zoom_attendance_module_data;
    }

    return NULL;
}

/**
 * Returns list of status set
 *
 * @param int $course_id
 * @return array
 *
 */
function get_status_set_data($course_id){
    
    global $DB;

    $options = array();
    $attendance = $DB->get_record('attendance', array("course"=>$course_id));
    
    if($attendance){
        $maxstatusset = attendance_get_max_statusset($attendance->id);
        $options = array();

        for($i = 0; $i <= $maxstatusset; $i++) {
            $options[$i] = attendance_get_setname($attendance->id, $i);
        }
    }

    return $options;
    
}

/**
 * Returns the list of course students
 *
 * @param int $course_id
 * @return array
 *
 */
function course_get_students($course_id) {
    
    global $DB;

    $query='SELECT DISTINCT u.id AS userid, u.firstname, u.lastname, u.email, c.id AS courseid
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id
              JOIN {enrol} e ON e.id = ue.enrolid
              JOIN {role_assignments} ra ON ra.userid = u.id
              JOIN {context} ct ON ct.id = ra.contextid AND ct.contextlevel =50
              JOIN {course} c ON c.id = ct.instanceid AND e.courseid = c.id
              JOIN {role} r ON r.id = ra.roleid AND r.shortname = "student"
              WHERE e.status = 0 AND u.suspended = 0 AND u.deleted = 0
              AND (ue.timeend = 0 OR ue.timeend > NOW()) AND ue.status = 0 AND c.id=:courseid';

    $params = array('courseid' => $course_id);
    $results = $DB->get_records_sql($query,$params);
    $students=array();
    foreach ($results as $cmid => $obj) {
        $students[] = $obj;
    }
    return $students;
}

/**
 * Returns passing percentage
 *
 * @param int $meeting_id
 * @return int
 *
 */
function get_passing_percentage($meeting_id){
    
    global $DB;

    $zoom_attendance_module_data = $DB->get_record("zoom_attendance_module_data",array("zoom_id"=>$meeting_id));
    
    if(!empty($zoom_attendance_module_data)){
        return $zoom_attendance_module_data->get_passing_percentage;
    }

    return 0;
}

/**
 * Returns printable hour in hh:mm:ss format
 *
 * @param int $seconds
 * @return string
 *
 */
function seconds_to_pritable_hour($seconds){
    return sprintf('%02d:%02d:%02d', ($seconds/ 3600),($seconds/ 60 % 60), $seconds% 60);
}