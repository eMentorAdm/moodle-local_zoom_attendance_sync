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

namespace local_zoom_attendance_sync\handlers;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/local/zoom_attendance_sync/locallib.php');

/**
 * Class 	mail_handler
 * @package local_zoom_attendance_sync\handlers
 */
class mail_handler
{

    private $meeting;

    function __construct($meeting) 
    {
        $this->meeting = $meeting;
    }

    /**
     * Sends the email of the meeting to a single student
     *
     * @param stdClass $student
     * @return boolean result of mail 
     * 
     */
    function send_notification($student)
    {
        
        $subject = get_string("notification_mail_subject",["meeting_name"=>$this->meeting->name]);
        $message = get_string("notification_mail_text",["firstname"=>$student->firstname,"lastname"=>$student->lastname, "meeting_name"=>$this->meeting->name, "date"=>date("d/m/Y",$this->meeting->start_time)]);
        $email_user = new \stdClass();
        $email_user->email=$student->email;
        $email_user->firstname=$student->firstname;
        $email_user->lastname=$student->lastname;
        $email_user->maildisplay = true;
        $email_user->mailformat = 1;
        $email_user->id=$student->id;
        $email_user->firstnamephonetic="";
        $email_user->lastnamephonetic="";
        $email_user->middlename="";
        $email_user->alternatename="";
        $messagehtml = text_to_html($message, false, false, true);

        return email_to_user($emailuser1,$CFG->noreplyaddress,$subject,$message,$messagehtml, ", ", true);
        
    }

    /**
     * Save meeting as processed
     *
     * @return void
     * 
     */
    function save_meeting_as_processed()
    {
        global $DB;
	
        $zoom_processed_meeting = new \StdClass();
        $zoom_processed_meeting->zoom_id = $this->meeting->id;
        $zoom_processed_meeting->activity = 'notification';
        $zoom_processed_meeting->time = time();
        
        $zoom_processed_meeting->id = $DB->insert_record("zoom_attendance_processed",$zoom_processed_meeting);
    }

}