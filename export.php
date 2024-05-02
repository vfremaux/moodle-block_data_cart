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
 * @package    block_data_cart
 * @category   blocks
 * @author     Valery Fremaux (valery.fremaux@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/lib/filestorage/tgz_packer.php');

$courseid = required_param('id', PARAM_INT);
$blockid = required_param('blockid', PARAM_INT);
$anon = optional_param('anon', 0, PARAM_BOOL);
$attachments = optional_param('attachments', 0, PARAM_BOOL);

$firstnamefield = 'cv_prenom';
$lastnamefield = 'cv_nom';

if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourseid');
}

if (!$instance = $DB->get_record('block_instances', array('id' => $blockid))) {
    print_error('invalidblockid');
}

// Security.

require_login($course);
$theblock = block_instance('data_cart', $instance);

$cart = $DB->get_records('block_data_cart', ['userid' => $USER->id, 'blockid' => $theblock->instance->id]);

// Export cart by zipping an archive with all produced data documents, if a data document does not yet exist, 
// Asl the associated PDCertificate to produce it.

if (empty($cart)) {
    throw new coding_exception("This should not happen unless reloading an export url with an emptied cart");
}

$ziparchiver = new tgz_packer();
// $ziparchiver = new zip_packer();

$exportid = uniqid();
$tempdir = make_temp_directory('data_cart/exports');

// Get Document Producer instance.
$producercm = $DB->get_record('course_modules', ['id' => $theblock->config->documentproducerbinding]);
$producermodule = $DB->get_record('modules', ['id' => $producercm->module]);
if ($producermodule->name == 'pdcertificate') {
    include_once($CFG->dirroot.'/mod/pdcertificate/xlib.php');
    $producerfunc = 'pdcertificate_make';
    $producergetfunc = 'pdcertificate_get_document';
}

$files = [];

$fs = get_file_storage();
$anonmap = [];

foreach ($cart as $c) {
    // Feed files with pdcertificate document producer results.
    if (!empty($theblock->config->documentproducerbinding)) {
        // Proto : we force making the document to reflect any changes in data record.
        // TODO : Rationalize by comparing record's update dates and document production dates.
        $recorddata = $theblock->pack_data($c->datarecordid, false, $anon);
        $producerfunc($c->userid, $producercm->id, $recorddata, false);

        if ($anon) {
            $anonmap[$recorddata->$firstnamefield.' '.$recorddata->$lastnamefield] = $recorddata->user->firstname.' '.$recorddata->user->lastname;
        }

        // Now we should have the document.
        if (!$document = $producergetfunc($c->userid, $producercm->id)) {
            throw new coding_exception("There has been an issue while producing output document from producer.");
        }

        // else document is a stored file, register in archivable files by its string content.
        $content = $document->get_content();
        $files['data_'.$recorddata->$firstnamefield.'_'.$recorddata->$lastnamefield.'.pdf'] = [$content];

        $cm = get_coursemodule_from_instance('data', $recorddata->dataid);
        $context = context_module::instance($cm->id);

        // Examine file records in datarecord.
        if ($attachments) {
            for ($i = 1; $i <= 8; $i++) {
                $fieldkey = 'file_f'.$i;
                $fieldidkey = 'file_f'.$i.'_id';
                if (!empty($recorddata->$fieldkey)) {
                    // We have a file to add to export.
                    $document = $fs->get_file($context->id, 'mod_data', 'content', $recorddata->$fieldidkey, '/', $recorddata->$fieldkey);
                    $content = $document->get_content();
                    $files[$recorddata->$firstnamefield.'_'.$recorddata->$lastnamefield.'/attachements/'.$recorddata->$fieldkey] = [$content];
                }
            }
        }
    }
}

if (!empty($anonmap)) {
    $content = '';
    foreach ($anonmap as $faked => $real) {
        $content .= $faked.' : '.$real."\n";
    }
    $files['anonymize_mapping.txt'] = [$content];
}

if (function_exists('debug_trace')) {
    debug_trace(array_keys($files));
}

$progress = null;
$anonstr = '';
if ($anon) {
    $anonstr = '_anon';
}
$archivefile = $tempdir.'/cart_export_'.$exportid.$anonstr.'.zip';
$ziparchiver->archive_to_pathname($files, $archivefile, true, $progress);
$datestamp = date('c', time());
$visiblearchivename = 'cart_export_'.$datestamp.$anonstr.'.zip'; 

send_temp_file($archivefile, $visiblearchivename);