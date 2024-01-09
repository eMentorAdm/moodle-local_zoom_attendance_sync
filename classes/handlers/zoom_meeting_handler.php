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
 * Zoom meeting handler class
 *
 * @package     local_zoom_attendance_sync
 * @copyright   2023 e-Mentor srl <service@e-mentor.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace local_zoom_attendance_sync\handlers;

defined('MOODLE_INTERNAL') || die();

use local_zoom_attendance_sync\handlers\token_handler;
use local_zoom_attendance_sync\webservices\meeting_participants_webservice;

/**
 * Class 	zoom_meeting_handler
 * @package local_zoom_attendance_sync\handlers
 */
class zoom_meeting_handler
{
    /**
     * Returns meeting data
     * @return StdClass
     * @throws Exception
     */
    public function get_meeting_data($meeting_id)
    {

        $token_handler = new token_handler();
        $token = $token_handler->get_token();

        $meeting_participants_webservice = new meeting_participants_webservice();
        return $meeting_participants_webservice->call($token, $meeting_id);

    }
}