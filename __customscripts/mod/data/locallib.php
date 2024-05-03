<?php


/**
 * Build the search array, coping with multidimensional fields.
 *
 * @param  stdClass $data      the database object
 * @param  bool $paging        if paging is being used
 * @param  array $searcharray  the current search array (saved by session)
 * @param  array $defaults     default values for the searchable fields
 * @param  str $fn             the first name to search (optional)
 * @param  str $ln             the last name to search (optional)
 * @return array               the search array and plain search build based on the different elements
 * @since  Moodle 3.9
 */
function data_build_search_array_multidim($data, $paging, $searcharray, $defaults = null, $fn = '', $ln = '') {
    global $DB;

    $search = '';
    $vals = array();
    $fields = $DB->get_records('data_fields', array('dataid' => $data->id));
    $mfields = []; // Array of multidimensional fields. 

    if (!empty($fields)) {
        foreach ($fields as $field) {

            // CHANGE+
            // Exclude multidimensional fields. aka : fieldname having _<a><n> suffix in name.
            // Those fields will be processed later.
            if (preg_match('/^([^_]*)(.*)_([a-z][0-9])$/', $field->name, $matches)) {
                $domain = $matches[1];
                $canonic = $matches[1].$matches[2];
                if (!array_key_exists($domain, $mfields)) {
                    $mfields[$domain] = [];
                }
                if (!array_key_exists($canonic, $mfields[$domain])) {
                    $mfield = new StdClass;
                    $mfield->canonic = $canonic;
                    $mfield->domain = $matches[1];
                    $mfield->type = $field->type; // Must be the same for all field instances, or will conclude in erratic behaviour.
                    $mfield->indexes[] = $matches[0]; // Collect valid indexes
                    $mfield->indexids[] = $field->id; // Collect valid indexes
                    $mfields[$domain][$canonic] = $mfield; // Will deduple
                } else {
                    $mfields[$domain][$canonic]->indexes[] = $matches[0];
                    $mfields[$domain][$canonic]->indexids[] = $field->id;
                }
                continue;
            }
            // CHANGE-

            $searchfield = data_get_field_from_id($field->id, $data);
            // Get field data to build search sql with.  If paging is false, get from user.
            // If paging is true, get data from $searcharray which is obtained from the $SESSION (see line 116).
            if (!$paging) {
                $val = $searchfield->parse_search_field($defaults);
            } else {
                // Set value from session if there is a value @ the required index.
                if (isset($searcharray[$field->id])) {
                    $val = $searcharray[$field->id]->data;
                } else { // If there is not an entry @ the required index, set value to blank.
                    $val = '';
                }
            }
            if (!empty($val)) {
                $searcharray[$field->id] = new stdClass();
                list($searcharray[$field->id]->sql, $searcharray[$field->id]->params) = $searchfield->generate_sql('c'.$field->id, $val);
                $searcharray[$field->id]->data = $val;
                $vals[] = $val;
            } else {
                // Clear it out.
                unset($searcharray[$field->id]);
            }
        }
    }

    if (function_exists('debug_trace')) {
        debug_trace("Multidimensional fields", TRACE_DEBUG_FINE);
        debug_trace($mfields, TRACE_DEBUG_FINE);
    }

    // Now process multidimensional fields
    foreach ($mfields as $domain => $domainfields) {

        // Receceive data from query string
        // Get field data to build search sql with.  If paging is false, get from user.
        // If paging is true, get data from $searcharray which is obtained from the $SESSION (see line 116).
        if (!$paging) {
            debug_trace("Receiving multidim values ");
            // $param = '_'.$mfield->canonic;
            $param = '_'.$domain;
            if (empty($defaults[$param])) {
                $defaults = array($param => '');
            }
            $val = optional_param($param, $defaults[$param], PARAM_NOTAGS);
            debug_trace("$param => $val ");
        } else {
            // Set value from session if there is a value @ the required index.
            if (isset($searcharray[$domain])) {
                $val = $searcharray[$domain]->data;
            } else { // If there is not an entry @ the required index, set value to blank.
                $val = '';
            }
        }

        if (!empty($val)) {
            $searcharray[$domain] = new stdClass();
            $domainparams = [];
            $domainsql = [];
            // Iterate over all domain  indexes, and assemble sql. 
            foreach ($domainfields as $canonic => $mfield) {
                foreach ($mfield->indexids as $mindexid) {

                    // Make a suitable type.
                    $searchfield = data_get_field_from_id($mindexid, $data);

                    switch($mfield->type) {
                        case "date" : {
                            // resolve later.
                            break;
                        }
                        case "multimenu" : {
                            list($sql, $params) = $searchfield->generate_sql('c'.$domain, [$val]);
                            $domainsql[] = '('.$sql.')';
                            break;
                        }
                        default:
                            list($sql, $params) = $searchfield->generate_sql('c'.$domain, $val);
                            $domainsql[] = '('.$sql.')';
                    }

                    $domainparams += $params;
                }
            }

            $vals[] = $val;
            $searcharray[$domain]->sql = '('.implode('OR', $domainsql).')';
            $searcharray[$domain]->data = $val;
            $searcharray[$domain]->params = $domainparams;
        } else {
            // Clear it out.
            unset($searcharray[$domain]);
        }
    }

    $rawtagnames = optional_param_array('tags', false, PARAM_TAGLIST);

    if ($rawtagnames) {
        $searcharray[DATA_TAGS] = new stdClass();
        $searcharray[DATA_TAGS]->params = [];
        $searcharray[DATA_TAGS]->rawtagnames = $rawtagnames;
        $searcharray[DATA_TAGS]->sql = '';
    } else {
        unset($searcharray[DATA_TAGS]);
    }

    if (!$paging) {
        // Name searching.
        $fn = optional_param('u_fn', $fn, PARAM_NOTAGS);
        $ln = optional_param('u_ln', $ln, PARAM_NOTAGS);
    } else {
        $fn = isset($searcharray[DATA_FIRSTNAME]) ? $searcharray[DATA_FIRSTNAME]->data : '';
        $ln = isset($searcharray[DATA_LASTNAME]) ? $searcharray[DATA_LASTNAME]->data : '';
    }
    if (!empty($fn)) {
        $searcharray[DATA_FIRSTNAME] = new stdClass();
        $searcharray[DATA_FIRSTNAME]->sql    = '';
        $searcharray[DATA_FIRSTNAME]->params = array();
        $searcharray[DATA_FIRSTNAME]->field  = 'u.firstname';
        $searcharray[DATA_FIRSTNAME]->data   = $fn;
        $vals[] = $fn;
    } else {
        unset($searcharray[DATA_FIRSTNAME]);
    }
    if (!empty($ln)) {
        $searcharray[DATA_LASTNAME] = new stdClass();
        $searcharray[DATA_LASTNAME]->sql     = '';
        $searcharray[DATA_LASTNAME]->params = array();
        $searcharray[DATA_LASTNAME]->field   = 'u.lastname';
        $searcharray[DATA_LASTNAME]->data    = $ln;
        $vals[] = $ln;
    } else {
        unset($searcharray[DATA_LASTNAME]);
    }

    // In case we want to switch to simple search later - there might be multiple values there ;-).
    if ($vals) {
        $val = reset($vals);
        if (is_string($val)) {
            $search = $val;
        }
    }
    return [$searcharray, $search];
}

/**
 * function that takes in the current data, number of items per page,
 * a search string and prints a preference box in view.php
 *
 * This preference box prints a searchable advanced search template if
 *     a) A template is defined
 *  b) The advanced search checkbox is checked.
 *
 * Manages multidimensional search items.
 *
 * @global object
 * @global object
 * @param object $data
 * @param int $perpage
 * @param string $search
 * @param string $sort
 * @param string $order
 * @param array $search_array
 * @param int $advanced
 * @param string $mode
 * @return void
 */
function data_print_preference_form_multidim($data, $perpage, $search, $sort='', $order='ASC', $search_array = '', $advanced = 0, $mode= ''){
    global $CFG, $DB, $PAGE, $OUTPUT;

    $cm = get_coursemodule_from_instance('data', $data->id);
    $context = context_module::instance($cm->id);
    echo '<br /><div class="datapreferences">';
    echo '<form id="options" action="view.php" method="get">';
    echo '<div>';
    echo '<input type="hidden" name="d" value="'.$data->id.'" />';
    if ($mode =='asearch') {
        $advanced = 1;
        echo '<input type="hidden" name="mode" value="list" />';
    }
    echo '<label for="pref_perpage">'.get_string('pagesize','data').'</label> ';
    $pagesizes = array(2=>2,3=>3,4=>4,5=>5,6=>6,7=>7,8=>8,9=>9,10=>10,15=>15,
                       20=>20,30=>30,40=>40,50=>50,100=>100,200=>200,300=>300,400=>400,500=>500,1000=>1000);
    echo html_writer::select($pagesizes, 'perpage', $perpage, false, array('id' => 'pref_perpage', 'class' => 'custom-select'));

    if ($advanced) {
        $regsearchclass = 'search_none';
        $advancedsearchclass = 'search_inline';
    } else {
        $regsearchclass = 'search_inline';
        $advancedsearchclass = 'search_none';
    }
    echo '<div id="reg_search" class="' . $regsearchclass . ' form-inline" >&nbsp;&nbsp;&nbsp;';
    echo '<label for="pref_search">' . get_string('search') . '</label> <input type="text" ' .
         'class="form-control" size="16" name="search" id= "pref_search" value="' . s($search) . '" /></div>';
    echo '&nbsp;&nbsp;&nbsp;<label for="pref_sortby">'.get_string('sortby').'</label> ';
    // foreach field, print the option
    echo '<select name="sort" id="pref_sortby" class="custom-select mr-1">';
    if ($fields = $DB->get_records('data_fields', array('dataid'=>$data->id), 'name')) {
        echo '<optgroup label="'.get_string('fields', 'data').'">';
        foreach ($fields as $field) {
            if ($field->id == $sort) {
                echo '<option value="'.$field->id.'" selected="selected">'.$field->name.'</option>';
            } else {
                echo '<option value="'.$field->id.'">'.$field->name.'</option>';
            }
        }
        echo '</optgroup>';
    }
    $options = array();
    $options[DATA_TIMEADDED]    = get_string('timeadded', 'data');
    $options[DATA_TIMEMODIFIED] = get_string('timemodified', 'data');
    $options[DATA_FIRSTNAME]    = get_string('authorfirstname', 'data');
    $options[DATA_LASTNAME]     = get_string('authorlastname', 'data');
    if ($data->approval and has_capability('mod/data:approve', $context)) {
        $options[DATA_APPROVED] = get_string('approved', 'data');
    }
    echo '<optgroup label="'.get_string('other', 'data').'">';
    foreach ($options as $key => $name) {
        if ($key == $sort) {
            echo '<option value="'.$key.'" selected="selected">'.$name.'</option>';
        } else {
            echo '<option value="'.$key.'">'.$name.'</option>';
        }
    }
    echo '</optgroup>';
    echo '</select>';
    echo '<label for="pref_order" class="accesshide">'.get_string('order').'</label>';
    echo '<select id="pref_order" name="order" class="custom-select mr-1">';
    if ($order == 'ASC') {
        echo '<option value="ASC" selected="selected">'.get_string('ascending','data').'</option>';
    } else {
        echo '<option value="ASC">'.get_string('ascending','data').'</option>';
    }
    if ($order == 'DESC') {
        echo '<option value="DESC" selected="selected">'.get_string('descending','data').'</option>';
    } else {
        echo '<option value="DESC">'.get_string('descending','data').'</option>';
    }
    echo '</select>';

    if ($advanced) {
        $checked = ' checked="checked" ';
    }
    else {
        $checked = '';
    }
    $PAGE->requires->js('/mod/data/data.js');
    echo '&nbsp;<input type="hidden" name="advanced" value="0" />';
    echo '&nbsp;<input type="hidden" name="filter" value="1" />';
    echo '&nbsp;<input type="checkbox" id="advancedcheckbox" name="advanced" value="1" ' . $checked . ' ' .
         'onchange="showHideAdvSearch(this.checked);" class="mx-1" />' .
         '<label for="advancedcheckbox">' . get_string('advancedsearch', 'data') . '</label>';
    echo '&nbsp;<input type="submit" class="btn btn-secondary" value="' . get_string('savesettings', 'data') . '" />';

    echo '<br />';
    echo '<div class="' . $advancedsearchclass . '" id="data_adv_form">';
    echo '<table class="boxaligncenter">';

    // print ASC or DESC
    echo '<tr><td colspan="2">&nbsp;</td></tr>';
    $i = 0;

    // Determine if we are printing all fields for advanced search, or the template for advanced search
    // If a template is not defined, use the deafault template and display all fields.
    if(empty($data->asearchtemplate)) {
        data_generate_default_template($data, 'asearchtemplate');
    }

    static $fields = array();
    static $dataid = null;

    if (empty($dataid)) {
        $dataid = $data->id;
    } else if ($dataid != $data->id) {
        $fields = array();
    }

    $mfielddomains = [];

    if (empty($fields)) {
        $fieldrecords = $DB->get_records('data_fields', array('dataid'=>$data->id));
        foreach ($fieldrecords as $fieldrecord) {
            // CHANGE+
            // Exclude multidimensional fields. aka : fieldname having _<a><n> suffix in name.
            // Those fields will be processed later.
            if (preg_match('/^([^_]*)(.*)_([a-z][0-9])$/', $fieldrecord->name, $matches)) {
                $domain = $matches[1];
                if (!in_array($domain, $mfielddomains)) {
                    // We just need domains here.
                    $mfielddomains[] = $domain;
                }
                continue;
            }
            // CHANGE-

            $fields[]= data_get_field($fieldrecord, $data);
        }
    }

    // Replacing tags
    $patterns = array();
    $replacement = array();

    // Then we generate strings to replace for normal tags
    foreach ($fields as $field) {
        $fieldname = $field->field->name;
        $fieldname = preg_quote($fieldname, '/');
        $patterns[] = "/\[\[$fieldname\]\]/i";
        $searchfield = data_get_field_from_id($field->field->id, $data);
        if (!empty($search_array[$field->field->id]->data)) {
            $replacement[] = $searchfield->display_search_field($search_array[$field->field->id]->data);
        } else {
            $replacement[] = $searchfield->display_search_field();
        }
    }

    // debug_trace("multifielddomains :");
    // debug_trace($mfielddomains);

    // Deal with multidimensional fieldsets
    foreach ($mfielddomains as $domain) {
        $patterns[] = "/\[\[{$domain}_data\]\]/i";
        if (!empty($search_array[$domain]->data)) {
            $replacement[] = $search_array[$domain]->data;
        } else {
            $replacement[] = ''; 
        }
    }

    $fn = !empty($search_array[DATA_FIRSTNAME]->data) ? $search_array[DATA_FIRSTNAME]->data : '';
    $ln = !empty($search_array[DATA_LASTNAME]->data) ? $search_array[DATA_LASTNAME]->data : '';
    $patterns[]    = '/##firstname##/';
    $replacement[] = '<label class="accesshide" for="u_fn">' . get_string('authorfirstname', 'data') . '</label>' .
                     '<input type="text" class="form-control" size="16" id="u_fn" name="u_fn" value="' . s($fn) . '" />';
    $patterns[]    = '/##lastname##/';
    $replacement[] = '<label class="accesshide" for="u_ln">' . get_string('authorlastname', 'data') . '</label>' .
                     '<input type="text" class="form-control" size="16" id="u_ln" name="u_ln" value="' . s($ln) . '" />';

    if (core_tag_tag::is_enabled('mod_data', 'data_records')) {
        $patterns[] = "/##tags##/";
        $selectedtags = isset($search_array[DATA_TAGS]->rawtagnames) ? $search_array[DATA_TAGS]->rawtagnames : [];
        $replacement[] = data_generate_tag_form(false, $selectedtags);
    }

    // Actual replacement of the tags

    $options = new stdClass();
    $options->para=false;
    $options->noclean=true;
    echo '<tr><td>';
    echo preg_replace($patterns, $replacement, format_text($data->asearchtemplate, FORMAT_HTML, $options));
    echo '</td></tr>';

    echo '<tr><td colspan="4"><br/>' .
         '<input type="submit" class="btn btn-primary mr-1" value="' . get_string('savesettings', 'data') . '" />' .
         '<input type="submit" class="btn btn-secondary" name="resetadv" value="' . get_string('resetsettings', 'data') . '" />' .
         '</td></tr>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}

