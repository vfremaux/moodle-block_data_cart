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
 * Form for editing Data cart block instances.
 *
 * @package   block_data_cart
 * @copyright 1999 onwards Martin Dougiamas (http://dougiamas.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class block_data_cart extends block_base {

    function init() {
        $this->title = get_string('pluginname', 'block_data_cart');
    }

    function has_config() {
        return false;
    }

    function applicable_formats() {
        return array('all' => false, 'my' => false, 'course' => true);
    }

    function specialization() {
        if (isset($this->config->title)) {
            $this->title = $this->title = format_string($this->config->title, true, ['context' => $this->context]);
        } else {
            $this->title = get_string('newblock', 'block_data_cart');
        }
    }

    function instance_allow_multiple() {
        return true;
    }

    function get_content() {
        global $OUTPUT, $USER, $DB, $COURSE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new StdClass;

        $cart = $DB->get_records('block_data_cart', ['blockid' => $this->instance->id, 'userid' => $USER->id]);

        $template = new StdClass;
        $template->blockid = $this->instance->id;
        $params = ['blockid' => $this->instance->id, 'id' => $COURSE->id, 'attachments' => 1];
        $template->exporturl = new moodle_url('/blocks/data_cart/export.php', $params);

        if ($this->config->anonymize) {
            $template->anonymize = true;
            $params = ['blockid' => $this->instance->id, 'id' => $COURSE->id, 'anon' => 1];
            $template->anonexporturl = new moodle_url('/blocks/data_cart/export.php', $params);
        }

        if (!empty($cart)) {
            foreach($cart as $c) {
                $rectpl = new StdClass;
                $rectpl->recordid = $c->datarecordid;
                $datarec = $DB->get_record('data_records', ['id' => $c->datarecordid]);
                $rectpl->id = $c->datarecordid;
                $u = $DB->get_record('user', ['id' => $datarec->userid]);
                $rectpl->datarecordname = $u->firstname.' '.$u->lastname;
                if (!empty($this->config->listfields)) {
                    $rectpl->datarecordname = $this->get_record_name($datarec, $this->config->listfields);
                }
                $rectpl->recordurl = new moodle_url('/mod/data/view.php', ['rid' => $c->datarecordid]);
                $template->datarecords[] = $rectpl; 
                $template->hasdatarecords = true;
            }
        }

        $this->content->text = $OUTPUT->render_from_template('block_data_cart/body', $template);

        return $this->content;
    }

    function content_is_trusted() {
        return true;
    }

    /**
     * The block should only be dockable when the title of the block is not empty
     * and when parent allows docking.
     *
     * @return bool
     */
    public function instance_can_be_docked() {
        return (!empty($this->config->title) && parent::instance_can_be_docked());
    }

    public function get_required_javascript() {
        global $PAGE;

        $PAGE->requires->js_call_amd('block_data_cart/datacart', 'init', [[$this->instance->id]]);
    }

    /**
     * Packs the record data with all its values.
     * First version gives a flat record.
     * Processes the fieldsets
     * @param int recordid the data record id
     * @param bool $assemblfieldsets future use, if there is a value to assemble virtual data subsets.
     */
    public function pack_data($recordid, $assemblefieldsets = false, $anon = false) {
        global $DB;
        static $fieldcache = [];

        if (!$datarecord = $DB->get_record('data_records', ['id' => $recordid])) {
            throw new coding_exception("Data record should exist when asking to packing");
        }

        if (!array_key_exists($datarecord->dataid, $fieldcache)) {
            // Load fieldset once per data instance.
            $fieldcache[$datarecord->dataid] = $DB->get_records('data_fields', array('dataid' => $datarecord->dataid), 'id');
        }

        $fieldcontents = $DB->get_records('data_content', array('recordid' => $datarecord->id), 'id');

        foreach ($fieldcontents as $fc) {
            $valuekey = $fieldcache[$datarecord->dataid][$fc->fieldid]->name;
            switch ($fieldcache[$datarecord->dataid][$fc->fieldid]->type) {
                case 'url':
                case 'radiobutton':
                case 'menu':
                case 'multimenu':
                case 'checkbox':
                case 'text': {
                    $value = $fc->content;
                    if (empty($value)) {
                        $value = null;
                    }
                    if ($anon) {
                        if ($fieldcache[$datarecord->dataid][$fc->fieldid]->type != 'url') {
                            $value = $this->anonymize($fieldcache[$datarecord->dataid][$fc->fieldid], $value);
                        } else {
                            // Mangle only after domain.
                            if (preg_match('#(https?:\/\/[^\/]+)\/(.*)#', $value, $matches)) {
                                $domain = $matches[1];
                                $end = $matches[2];
                                $mangled = $this->anonymize($fieldcache[$datarecord->dataid][$fc->fieldid], $end);
                                $value = $domain.'/'.$mangled;
                            }
                        }
                    }
                    if ($fieldcache[$datarecord->dataid][$fc->fieldid]->type == 'multimenu') {
                        $value = str_replace('##', ', ', $value);
                    }
                    break;
                }
                case 'textarea': {
                    $value = format_text($fc->content, $fc->content1);
                    break;
                }
                case 'date' : {
                    if (!empty($fc->content)) {
                        $value = date('c', $fc->content);
                        $valuekeyyear = $valuekey.'_year';
                        $datarecord->$valuekeyyear = date('Y', $fc->content);
                        $valuekeymonth = $valuekey.'_month';
                        $datarecord->$valuekeymonth = date('M Y', $fc->content);
                    } else {
                        $value = null;
                    }
                    break;
                }
                case 'file' :
                case 'picture' : {
                    $value = $fc->content; // This is the filename.
                    $valueidkey = $valuekey.'_id';
                    $datarecord->$valueidkey = $fc->id;
                    break;
                }
            }

            // Trim initial spaces.
            $value = trim($value);
            $datarecord->$valuekey = $value;

            // fieldsets processing.
            // debug_trace("Post processing $valuekey ");
            if (preg_match('/_[a-z][1-9]$/', $valuekey)) {
                // This is a fieldset element
                $parts = explode('_', $valuekey);
                $setname = array_shift($parts);
                $setindex = array_pop($parts);

                // Check it has data and mark template to trigger sets visibility.
                if ($fieldcache[$datarecord->dataid][$fc->fieldid]->type == 'text') {
                    // Only react on text fields. (Usually the first would be enough...);
                    $setkey = $setname.'_hasdata';
                    $setinstancekey = $setname.'_'.$setindex.'_hasdata';
                    // Initialise at empty = true;
                    if (!isset($datarecord->$setkey)) {
                        // debug_trace("Setting false in $setkey ");
                        $datarecord->$setkey = false;
                    }
                    $datarecord->$setinstancekey = false;
                    // debug_trace("checking real content in fltered value ".strip_tags($value));
                    if (preg_match('/[a-zA-Z0-9_]/', strip_tags($value))) {
                        $datarecord->$setkey = true;
                        $datarecord->$setinstancekey = true;
                    }
                }
            }
        }

        $datarecord->user = $DB->get_record('user', ['id' => $datarecord->userid]);

        return $datarecord;
    }

    /*
     * Mangle sensible values if required.
     */
    protected function anonymize($field, $value) {
        if (!in_array($field->type, ['text', 'url'])) {
            debug_trace("anonymize : Not a text field... ");
            return $value;
        }

        $sensiblefields = preg_split('/[\s,]+/', $this->config->sensiblefields);

        if (in_array($field->name, $sensiblefields)) {
            $len = mb_strlen($value);
            $base = 'TUVEWXYZ';
            $mangled = '';
            // Search the first char starting pattern.
            for ($i = 0; $i < $len; $i++) {
                $ix = rand(0, strlen($base));
                $mangled .= $base[$ix];
            } 
            return $mangled;
        }

        return $value;
    }

    /**
     * Fetch appropriate data to forge the record's name in cart list.
     * @param object $datarec the data record
     * @param string $listfields list of fields from block instance config.
     */
    protected function get_record_name($datarec, $listfields) {
        global $DB;

        if (empty($listfields)) {
            return 'data '.$datarec->id;
        }

        $fields = preg_split("/[\s,]+/", trim($listfields));

        list($insql, $inparams) = $DB->get_in_or_equal($fields);

        $sql = "
            SELECT
                f.name, f.id, f.type, fc.content
            FROM
                {data_fields} f,
                {data_content} fc
            WHERE
                fc.fieldid = f.id AND
                fc.recordid = ? AND
                f.name $insql
        ";
        $params = array_merge([$datarec->id], $inparams);
        $data = $DB->get_records_sql($sql, $params);

        $values = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                switch ($data[$f]->type) {
                    case 'date' :
                        $values[] = userdate($data[$f]->content);
                    default:
                        $values[] = $data[$f]->content;
                }
            }
        }
        return implode(' ', $values);
    }
}
