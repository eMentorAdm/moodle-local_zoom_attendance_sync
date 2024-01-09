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


namespace local_zoom_attendance_sync\task;

defined('MOODLE_INTERNAL') || die();

use Exception;
use local_zoom_attendance_sync\handlers\mail_handler;

class send_notification extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('send_notification_task', 'local_zoom_attendance_sync');
    }

    /**
     * Execute the task.
     * @return void
     */
    public function execute()
    {
        mtrace("Starting send_notification task");
        global $DB;

        $config = get_config("local_zoom_attendance_sync");
        $task_enabled = $config->emailnotification;

        if (!$task_enabled){
            return;
        }

        // Get all meetings not processed yet
        $sql = "SELECT * FROM {zoom}
                WHERE id not in (SELECT zoom_id FROM {zoom_attendance_processed} where activity=:activity)";
        
        $params=array();
        $params['activity']='notification';

        $meetings = $DB->get_records_sql($sql,$params);
        $total_meetings = count($meetings);


        // Loop through users
        foreach ($meetings as $meeting){
            $i++;
            mtrace("(" . $i . "/" . $total_meetings . ") Processing meeting with id: " . $meeting->id);
           
            $mail_handler = new mail_handler($meeting);

            $course_students = course_get_students($meeting->course);

            
            mtrace("\Starting sending notifications");

            foreach($course_students as $course_student){
                try {
                    $result = $mail_handler->send_notification($course_student);
                    mtrace("\Mail to ".$course_student->email." sent successfully.");
                } catch (Exception $e) {
                    mtrace("\tERROR: " . $e->getMessage());
                    continue;
                }
            }
            
            $mail_handler->save_meeting_as_processed();

        }
    }
}