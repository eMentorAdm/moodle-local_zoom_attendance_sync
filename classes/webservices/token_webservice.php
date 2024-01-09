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


namespace local_zoom_attendance_sync\webservices;

defined('MOODLE_INTERNAL') || die();

use Error;

/**
 * Class 	token_webservice
 * @package local_zoom_attendance_sync\webservices
 */
class token_webservice extends generic_rest_client {

    /**
     * Defines main config
     * @return void
     */
    protected function definition():void {
        $this->method = self::METHOD_POST;
        $this->async = false;

        $this->set_client_header('Accept', '*/*');
        $this->set_client_header('Host', 'zoom.us');
        $this->set_client_header('Accept-Encoding', 'gzip, deflate, br');
        $this->set_client_header('Connection', 'keep-alive');
        $this->set_client_header('Content-Type', 'application/json');
        $this->set_client_header('Content-Length', '0');
    }

    /**
     * Execute the call
     * @return \stdClass
     */
    public function call($base_uri, $resource, $query, $token):\stdClass {
        
        $this->set_client_header('Authorization', 'Basic ' . $token);
        
        $this->set_base_uri(trim($base_uri));
        $this->resource = $resource;
        $this->set_request_query($query);
        
        $response = $this->execute($this->resource, $this->method, $this->async);
        $result = json_decode($response->content);
       
        if (empty($result)) {
            return $this->empty_response($response->http_code, $response->http_reason);
        }

        return (object) [
            'http_code' => $response->http_code,
            'http_reason' => $response->http_reason,
            'content' => [
                'access_token' => $result->access_token,
                'token_type' => $result->token_type,
                'expires_in' => $result->expires_in,
            ]
        ];
    }

}