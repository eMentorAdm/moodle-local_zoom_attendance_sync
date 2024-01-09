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
use local_zoom_attendance_sync\handlers\zoom_meeting_handler;
use local_zoom_attendance_sync\handlers\attendance_handler;

global $CFG;
require_once($CFG->dirroot.'/local/zoom_attendance_sync/locallib.php');

/**
 * Class 	sync_attendance
 * @package local_zoom_attendance_sync\task
 */
class sync_attendance extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name()
    {
        return get_string('sync_zoom_attendance_task', 'local_zoom_attendance_sync');
    }

    /**
     * Execute the task.
     * 
     * @return void
     */
    public function execute()
    {
        global $DB;
        
        $config = get_config("local_zoom_attendance_sync");
        $task_enabled = $config->enablesync;

        if (!$task_enabled){
            return;
        }

        mtrace("Starting local_zoom_attendance_sync task");

        // Get all meetings not processed yet
        $sql = "SELECT * FROM {zoom}
                WHERE id not in (SELECT zoom_id FROM {zoom_attendance_processed} where activity=:activity)";
        
        $params=array();
        $params['activity']='report';

        $meetings = $DB->get_records_sql($sql,$params);
        $total_meetings = count($meetings);

        $i = 0;
        $meeting_handler = new zoom_meeting_handler();
        
        // Loop through meetings
        foreach($meetings as $meeting){
            
            $i++;   
            
            mtrace("(" . $i . "/" . $total_meetings . ") Processing meeting with id: " . $meeting->id);
           
            $attendance_handler = new attendance_handler($meeting->course);
   
            if (!$attendance_handler->has_attendance_module()){               
                mtrace("\tCourse ".$meeting->course." doesn't contain an attendance module");
                continue;
            }

            // Get the data from zoom api
            mtrace("\tGetting data from meeting");

            try {
                $result = $meeting_handler->get_meeting_data($meeting->meeting_id);
            } catch (Exception $e) {
                mtrace("\tERROR: " . $e->getMessage());
                continue;
            }
            
            // If the http code is not 200 or the content is empty, then we display an error
            if ($result->http_code != 200 || empty($result->content)) {
                mtrace("\tERROR: " . $result->http_code . " " . $result->http_reason);
                $attendance_handler->save_meeting_as_processed($meeting->id);
            } else {
                try {
                    mtrace("\Processing data");
                    $attendance_handler->process_data($meeting, $result->content);
                    mtrace("\Data processed");
                } catch (Exception $e) {
                    mtrace("\tERROR while saving data: " . $e->getMessage());
                }
            }
        }
    }
}