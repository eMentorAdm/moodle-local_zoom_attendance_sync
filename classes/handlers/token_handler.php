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

use Exception;
use local_zoom_attendance_sync\webservices\token_webservice;

class token_handler
{

    /**
     * check if there is a token in the cache and if it is still valid, if not, get a new one
     * @return string
     * @throws Exception
     */
    public function get_token(): string
    {
        // Check if there is a token in the cache
        $cache = \cache::make('local_zoom_attendance_sync', 'bearer_token');
        $token = $cache->get('zoom_token');

        if ($token['token']) {
            // Check if it is still valid
            $time_expires = $token['expires'];
            if ($time_expires - 20 > time()) {
                // Token is still valid
                return $token['token'];
            } else {
                // Token is not valid anymore, get a new one
                return $this->generate_token();
            }
        } else {
            // No token in the cache, get a new one
            return $this->generate_token();
        }
    }

    /**
     * Generate a new token
     * @return string
     * @throws Exception
     */
    public function generate_token(): string
    {

        $config = get_config("local_zoom_attendance_sync");

        $token = base64_encode($config->clientid.":".$config->clientsecret);
       
        $base_uri = $config->baseuri;
        $resource = 'oauth/token';

        $query = [];
        $query['grant_type'] = 'account_credentials';
        $query['account_id'] = $config->accountid;
       
        $token_webservice = new token_webservice();
        $result = $token_webservice->call($base_uri, $resource, $query, $token);
        
        if ($result->http_code != 200 or !isset($result->content['access_token'])) {
            throw new Exception('Error getting token: ' . $result->http_code . ' - ' . $result->http_reason);
        }

        $token = $result->content['access_token'];
        $time_expires = time() + $result->content['expires_in'];
        $cache = \cache::make('local_zoom_attendance_sync', 'bearer_token');
        $cache->set('zoom_token', ['token' => $token, 'expires' => $time_expires]);
       
        return $token;
    }

}