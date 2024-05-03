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
 * @package     block_data_cart
 * @category    blocks
 * @author      Valery Fremaux
 *
 * Ajax services for data_ cart bloc.
 */

include('../../../config.php');

if (!defined('AJAX_SCRIPT')) {
    define('AJAX_SCRIPT', true);
}

$action = required_param('what', PARAM_ALPHANUM);
$blockid = required_param('blockid', PARAM_INT);
$instance = $DB->get_record('block_instances', array('id' => $blockid));
if (!$instance) {
    throw new moodle_exception("Bad block instance");
}

require_login();

$PAGE->set_context(context_block::instance($blockid));

$url = new moodle_url($CFG->wwwroot.'/blocks/data_cart/ajax/service.php');

if ($action == 'addrecord') {
    $recordid = required_param('recordid', PARAM_INT);
    $record = new StdClass();
    $record->userid = $USER->id;
    $record->blockid = $blockid;
    $record->datarecordid = $recordid;

    $params = [
        'blockid' => $blockid,
        'userid' => $USER->id,
        'datarecordid' => $recordid,
    ];
    if (!$DB->record_exists('block_data_cart', $params)) {
        $DB->insert_record('block_data_cart', $record);
    }
}
else if ($action == 'removerecord') {
    $recordid = required_param('recordid', PARAM_INT);
    $params = [
        'blockid' => $blockid,
        'userid' => $USER->id,
        'datarecordid' => $recordid,
    ];
    if ($DB->record_exists('block_data_cart', $params)) {
        $DB->delete_records('block_data_cart', $params);
    }
}
else if ($action == 'reset') {
    // Delete all records.
    $params = [
        'blockid' => $blockid,
        'userid' => $USER->id,
    ];
    $DB->delete_records('block_data_cart', $params);
} else if ($action == 'reload') {
    // Reloads updated record list.
    $parentcontext = context::instance_by_id($instance->parentcontextid); // Assumes block resides in a course.
    $theblock = block_instance('data_cart', $instance);
    $cart = $DB->get_records('block_data_cart', ['blockid' => $blockid, 'userid' => $USER->id]);

    $template = new StdClass;
    if (!empty($cart)) {
        foreach ($cart as $c) {
            $rectpl = new StdClass;
            $rectpl->recordid = $c->datarecordid;
            $datarec = $DB->get_record('data_records', ['id' => $c->datarecordid]);
            $rectpl->id = $c->datarecordid;
            $u = $DB->get_record('user', ['id' => $datarec->userid]);
            $rectpl->firstname = $u->firstname;
            $rectpl->lastname = $u->lastname;
            $rectpl->recordurl = new moodle_url('/mod/data/view.php', ['rid' => $c->datarecordid]);
            $template->datarecords[] = $rectpl; 
            $template->hasdatarecords = true;
        }

        if ($theblock->config->anonymize) {
            $template->anonymize = true;
            $params = ['blockid' => $theblock->instance->id, 'id' => $COURSE->id, 'anon' => 1];
            $template->anonexporturl = new moodle_url('/blocks/data_cart/export.php', $params);
        }

        $params = ['blockid' => $blockid, 'id' => $parentcontext->instanceid, 'attachments' => 1];
        $template->exporturl = new moodle_url('/blocks/data_cart/export.php', $params);

    }
    echo $OUTPUT->render_from_template('block_data_cart/list', $template);
}
