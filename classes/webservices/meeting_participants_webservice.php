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
 * Zoom Meeting Webservice Class
 *
 * @package     local_zoom_attendance_sync
 * @copyright   2023 e-Mentor srl <service@e-mentor.it>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_zoom_attendance_sync\webservices;

defined('MOODLE_INTERNAL') || die();

use Error;

/**
 * Class 	zoom_webservice
 * @package local_zoom_attendance_sync\webservices
 */
class meeting_participants_webservice extends generic_rest_client {

    /**
     * Defines main config
     * @return void
     */
    protected function definition():void {

        $config = get_config('local_zoom_attendance_sync');
        $this->set_base_uri(trim($config->apiuri));
       
        $this->method = self::METHOD_GET;
        $this->async = false;

        $this->set_client_header('Accept', '*/*');
        $this->set_client_header('Host', 'zoom.us');
        $this->set_client_header('Accept-Encoding', 'gzip, deflate, br');
        $this->set_client_header('Connection', 'keep-alive');
    }

    /**
     * Execute the call
     * @return \stdClass
     */
    public function call($token, $meeting_id):\stdClass {
        
        $this->set_client_header('Authorization', 'Bearer ' . $token);

        $this->resource = 'report/meetings/'.$meeting_id.'/participants';

        $query = [];
        
        $this->set_request_query($query);

        $response = $this->execute($this->resource, $this->method, $this->async);

        $result = json_decode($response->content);

        if (empty($result)) {
            return $this->empty_response($response->http_code, $response->http_reason);
        }

        return (object) [
            'http_code' => $response->http_code,
            'http_reason' => $response->http_reason,
            'content' => $result
        ];
    }
}