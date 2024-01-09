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


if ($hassiteconfig) {

    $settings = new admin_settingpage('local_zoom_attendance_sync_settings', new lang_string('pluginname', 'local_zoom_attendance_sync'));
    /*
    $ADMIN->add('server', new admin_externalpage('admin_sync',
        "Zoom Attendance Sync",
        new moodle_url('/local/zoom_attendance_sync_setting/admin_sync.php')));
    */
    $ADMIN->add('localplugins', new admin_category("local_zoom_attendance_sync", get_string('pluginname', "local_zoom_attendance_sync")));

    $settings->add(new admin_setting_configcheckbox(
        'local_zoom_attendance_sync/enablesync',
        get_string('enablesync', 'local_zoom_attendance_sync'), 
        get_string('enablesync_desc', 
        'local_zoom_attendance_sync'), 
        0
    ));


    $settings->add(new admin_setting_configtext(
        'local_zoom_attendance_sync/baseuri',
        get_string('baseuri', 'local_zoom_attendance_sync'),
        get_string('baseuri_desc', 'local_zoom_attendance_sync'), 
        '', 
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_zoom_attendance_sync/apiuri',
        get_string('apiuri', 'local_zoom_attendance_sync'),
        get_string('apiuri_desc', 'local_zoom_attendance_sync'), 
        '', 
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_zoom_attendance_sync/accountid',
        get_string('accountid', 'local_zoom_attendance_sync'),
        get_string('accountid_desc', 'local_zoom_attendance_sync'), 
        '', 
        PARAM_ALPHANUMEXT
    ));
    
    $settings->add(new admin_setting_configtext(
        'local_zoom_attendance_sync/clientid',
        get_string('clientid', 'local_zoom_attendance_sync'),
        get_string('clientid_desc', 'local_zoom_attendance_sync'), 
        '', 
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_zoom_attendance_sync/clientsecret',
        get_string('clientsecret', 'local_zoom_attendance_sync'),
        get_string('clientsecret_desc', 'local_zoom_attendance_sync'), 
        ''    
    ));

    $settings->add(new admin_setting_configtext(
        'local_zoom_attendance_sync/passingpercentage',
        get_string('passingpercentage', 'local_zoom_attendance_sync'),
        get_string('passingpercentage_desc', 'local_zoom_attendance_sync'), 
        0, 
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_zoom_attendance_sync/emailnotification',
        get_string('emailnotification', 'local_zoom_attendance_sync'),
        get_string('emailnotification_desc', 'local_zoom_attendance_sync'), 
        0
    ));     

    $ADMIN->add('local_zoom_attendance_sync',$settings);
     
}
