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
 * Form for editing HTML block instances.
 *
 * @package   block_data_cart
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Form for editing Data Cart block instances.
 *
 * @copyright 2009 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_data_cart_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $CFG, $DB, $COURSE;

        // Fields for editing Data Cart block title and contents.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        $mform->addElement('text', 'config_title', get_string('configtitle', 'block_data_cart'));
        $mform->setType('config_title', PARAM_TEXT);

        if (is_dir($CFG->dirroot.'/mod/pdcertificate')) {
            $module = $DB->get_record('modules', ['name' => 'pdcertificate']);
            $sql = "
                SELECT 
                    cm.id as id,
                    pdc.name
                FROM
                    {course_modules} cm,
                    {pdcertificate} pdc
                WHERE
                    cm.course = ? AND
                    cm.module = ? AND
                    cm.deletioninprogress = 0 AND
                    cm.instance = pdc.id
            ";
            $producers = $DB->get_records_sql_menu($sql, [$COURSE->id, $module->id]);
            $mform->addElement('select', 'config_documentproducerbinding', get_string('configdocumentproducerbinding', 'block_data_cart'), $producers);
            $mform->addHelpButton('config_documentproducerbinding', 'configdocumentproducerbinding', 'block_data_cart');
            $mform->setType('config_documentproducerbinding', PARAM_INT);

            $mform->addElement('checkbox', 'config_anonymize', get_string('configanonymize', 'block_data_cart'));
            $mform->addHelpButton('config_anonymize', 'configanonymize', 'block_data_cart');

            $mform->addElement('text', 'config_listfields', get_string('configlistfields', 'block_data_cart'));
            $mform->addHelpButton('config_listfields', 'configlistfields', 'block_data_cart');
            $mform->setType('config_listfields', PARAM_TEXT);

            $mform->addElement('text', 'config_sensiblefields', get_string('configsensiblefields', 'block_data_cart'));
            $mform->addHelpButton('config_sensiblefields', 'configsensiblefields', 'block_data_cart');
            $mform->setType('config_sensiblefields', PARAM_TEXT);
        }
    }

    function set_data($defaults) {
        if (!empty($this->block->config) && !empty($this->block->config->text)) {
            $text = $this->block->config->text;
            $draftid_editor = file_get_submitted_draft_itemid('config_text');
            if (empty($text)) {
                $currenttext = '';
            } else {
                $currenttext = $text;
            }
            $defaults->config_text['text'] = file_prepare_draft_area($draftid_editor, $this->block->context->id, 'block_data_cart', 'content', 0, array('subdirs' => true), $currenttext);
            $defaults->config_text['itemid'] = $draftid_editor;
            $defaults->config_text['format'] = $this->block->config->format ?? FORMAT_MOODLE;
        } else {
            $text = '';
        }

        if (!$this->block->user_can_edit() && !empty($this->block->config->title)) {
            // If a title has been set but the user cannot edit it format it nicely
            $title = $this->block->config->title;
            $defaults->config_title = format_string($title, true, $this->page->context);
            // Remove the title from the config so that parent::set_data doesn't set it.
            unset($this->block->config->title);
        }

        // have to delete text here, otherwise parent::set_data will empty content
        // of editor
        unset($this->block->config->text);
        parent::set_data($defaults);
        // restore $text
        if (!isset($this->block->config)) {
            $this->block->config = new stdClass();
        }
        $this->block->config->text = $text;
        if (isset($title)) {
            // Reset the preserved title
            $this->block->config->title = $title;
        }
    }
}
