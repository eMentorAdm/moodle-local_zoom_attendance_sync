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

defined('MOODLE_INTERNAL') || die();

require_once ($CFG->dirroot . '/local/zoom_attendance_sync/locallib.php');

/**
 * Adds custom fields into form
 *
 * @param stdClass $formwrapper
 * @param stdClass $mform
 * @return void
 *
 */
function local_zoom_attendance_sync_coursemodule_standard_elements($formwrapper, $mform) {
    
    $modulename = $formwrapper->get_current()->modulename;
	
    if($modulename === 'zoom'){
		
        global $DB, $COURSE;

        $passing_percentage_default_value = get_config("local_zoom_attendance_sync");

        $update  = optional_param('update', -1, PARAM_INT);

        if($update!=-1){
            $zoom_attendance_module_data = get_zoom_attendance_module_data($update); 
        }

        $mform->addElement('header', 'zoom_mondadori', get_string("additional_info_form","local_zoom_attendance_sync"));
        $mform->addElement('text', 'passing_percentage', get_string("passingpercentage","local_zoom_attendance_sync"));
        $mform->setDefault('passing_percentage',$passing_percentage_default_value->passingpercentage);

        if(!empty($zoom_attendance_module_data)){
            $mform->setDefault('passing_percentage', $zoom_attendance_module_data->passing_percentage);
        }

        $options = array();
		
        $options = get_status_set_data($COURSE->id);
		
        $mform->addElement('select', 'status_set', get_string("statusset","local_zoom_attendance_sync"), $options);

        if(!empty($zoom_attendance_module_data))
            $mform->setDefault('status_set', $zoom_attendance_module_data->status_set);


    }
    
}

/**
 * Process data from submitted form
 *
 * @param stdClass $data
 * @param stdClass $course
 * @return void
 * See plugin_extend_coursemodule_edit_post_actions in
 * https://github.com/moodle/moodle/blob/master/course/modlib.php
 */
function local_zoom_attendance_sync_coursemodule_edit_post_actions($data, $course) {

    $values=array();
    if($data->modulename === 'zoom'){

        global $COURSE;

        $keys = ['passing_percentage','instance','coursemodule','status_set'];
        
        foreach($keys as $i){
            $value = $data->$i;
            $values[$i] = $value;
        }

        $session_id = get_session_id($data->instance);

        $zoom_info = new StdClass();
        $zoom_info->id = $data->instance;
        $zoom_info->name = $data->name;
        $zoom_info->start_time = $data->start_time;
        $zoom_info->duration = $data->duration;

        if(!$session_id){
            $session_id = save_session($zoom_info,$COURSE->id);
        }
        else {
            update_session($session_id,$zoom_info,$COURSE->id);
        }

        $values['session_id'] = $session_id;
        save_params($values);

    }
    return $data;
   
}

/**
 * Save extra parameters
 *
 * @param array $params
 * @return void
 * 
 */
function save_params($params){
    
	global $DB;
    	
	$exist = $DB->get_record('zoom_attendance_module_data',array('zoom_id' => $params['instance']));
	
    $record = '';
    $record = (object) $record;
    
    $record->zoom_id = $params['instance'];
    $record->module_id = $params['coursemodule'];
    $record->session_id = $params['session_id'];
    $record->status_set = ($params['status_set'])? $params['status_set'] : 0;
    $record->passing_percentage = $params['passing_percentage'];

    if($exist){
        $record->id = $exist->id;
        $DB->update_record('zoom_attendance_module_data', $record);
    } else {
        
        $DB->insert_record('zoom_attendance_module_data', $record, true, false);

        $attendance_session = $DB->get_record('attendance_sessions', array("id" => $params['session_id']));
        if($attendance_session){
            $attendance_session->statusset = $record->status_set;
            $DB->update_record('attendance_sessions', $attendance_session);
        }
        
    }

}

/**
 * Returns attendance session id
 *
 * @param int $zoom_id
 * @return int $session_id
 * 
 */
function get_session_id($zoom_id){
    global $DB;

    $zoom_attendance = $DB->get_record('zoom_attendance_module_data', array("zoom_id"=>$zoom_id));

    if(!$zoom_attendance) return NULL;

    return $zoom_attendance->session_id;

}

/**
 * Save session information from Zoom Meeting information
 *
 * @param stdClass $zoom
 * @param int $course_id
 * @return int|null return session_id
 * 
 */
function save_session($zoom, $course_id){

    global $DB;

    $attendance = $DB->get_record('attendance', array("course"=>$course_id));

    if($attendance){

        $attendance_session = new stdClass;
        $attendance_session->attendanceid = $attendance->id;
        $attendance_session->statusset = 0;
        $attendance_session->groupid = 0;
        $attendance_session->sessdate = $zoom->start_time;
        $attendance_session->duration = $zoom->duration;
        $attendance_session->timemodified = time();
        $attendance_session->description=$zoom->name;
        $attendance_session->id = $DB->insert_record('attendance_sessions', $attendance_session);

        return $attendance_session->id;
    }

    return NULL;
        
}

/**
 * Update session informations
 *
 * @param int $session_id
 * @param stdClass $zoom
 * @param int $course_id
 * @return void
 * 
 */
function update_session($session_id, $zoom, $course_id){

    global $DB;

    $attendance_session = $DB->get_record('attendance_sessions', array("id"=>$session_id));

    if($attendance_session){
        $attendance_session->sessdate = $zoom->start_time;
        $attendance_session->duration = $zoom->duration;
        $attendance_session->timemodified = time();
        $attendance_session->description=$zoom->name;
        $DB->update_record('attendance_sessions', $attendance_session);
    }
    
}


