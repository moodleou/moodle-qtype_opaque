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
 * The questiontype class for the Opaque question type.
 *
 * @package   qtype_opaque
 * @copyright 2006 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/opaque/enginemanager.php');


/**
 * The Opaque question type.
 *
 * @copyright 2006 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque extends question_type {
    /** @var qtype_opaque_engine_manager */
    protected $enginemanager;

    /** @var javascript_ready */
    protected $jsready;

    public function __construct() {
        parent::__construct();
        $this->enginemanager = qtype_opaque_engine_manager::get();
        $this->jsready = true;
    }

    /**
     * Set the engine manager to used. You should not need to call this except
     * when testing.
     * @param qtype_opaque_engine_manager $manager
     */
    public function set_engine_manager(qtype_opaque_engine_manager $manager) {
        $this->enginemanager = $manager;
    }

    public function can_analyse_responses() {
        return false;
    }

    public function extra_question_fields() {
        return array('qtype_opaque_options', 'engineid', 'remoteid', 'remoteversion');
    }

    public function save_question($question, $form) {
        $form->questiontext = '';
        $form->questiontextformat = FORMAT_MOODLE;
        $form->unlimited = 0;
        $form->penalty = 0;
        return parent::save_question($question, $form);
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        global $PAGE;
        parent::initialise_question_instance($question, $questiondata);
        $question->engineid = $questiondata->options->engineid;
        $question->remoteid = $questiondata->options->remoteid;
        $question->remoteversion = $questiondata->options->remoteversion;
        if ($this->jsready) {
            $this->jsready = false;
            $PAGE->requires->js_call_amd('qtype_opaque/changefocus', 'init');
        }
    }

    public function get_random_guess_score($questiondata) {
        return null;
    }

    public function export_to_xml($question, qformat_xml $format, $extra = null) {
        $expout = '';
        $expout .= '    <remoteid>' . $question->options->remoteid . "</remoteid>\n";
        $expout .= '    <remoteversion>' . $question->options->remoteversion . "</remoteversion>\n";
        $expout .= "    <engine>\n";
        $engine = $this->enginemanager->load($question->options->engineid);
        $expout .= "      <name>\n" . $format->writetext($engine->name, 4) . "      </name>\n";
        $expout .= "      <passkey>\n" . $format->writetext($engine->passkey, 4) .
                "      </passkey>\n";
        $expout .= "      <timeout>" . $engine->timeout . "</timeout>\n";
        foreach ($engine->questionengines as $qe) {
            $expout .= "      <qe>\n" . $format->writetext($qe, 4) . "      </qe>\n";
        }
        foreach ($engine->questionbanks as $qb) {
            $expout .= "      <qb>\n" . $format->writetext($qb, 4) . "      </qb>\n";
        }
        $expout .= "    </engine>\n";
        return $expout;
    }

    public function import_from_xml($data, $question, qformat_xml $format, $extra = null) {
        if (!isset($data['@']['type']) || $data['@']['type'] != 'opaque') {
            return false;
        }

        $question = $format->import_headers($data);
        $question->qtype = 'opaque';
        $question->remoteid = $format->getpath($data, array('#', 'remoteid', 0, '#'),
                '', false, get_string('missingremoteidinimport', 'qtype_opaque'));
        $question->remoteversion = $format->getpath($data, array('#', 'remoteversion', 0, '#'),
                '', false, get_string('missingremoteversioninimport', 'qtype_opaque'));

        // Engine bit.
        $strerror = get_string('missingenginedetailsinimport', 'qtype_opaque');
        if (!isset($data['#']['engine'][0])) {
             $format->error($strerror);
        }
        $enginedata = $data['#']['engine'][0];
        $engine = new stdClass();
        $engine->name = $format->import_text($enginedata['#']['name'][0]['#']['text']);
        $engine->passkey = $format->import_text($enginedata['#']['passkey'][0]['#']['text']);
        $engine->timeout = $format->getpath($enginedata, array('#', 'timeout', 0, '#'), null);
        if (empty($engine->timeout)) {
            unset($engine->timeout); // So that we use the default defined in the DB.
        }
        $engine->questionengines = array();
        $engine->questionbanks = array();
        if (isset($enginedata['#']['qe'])) {
            foreach ($enginedata['#']['qe'] as $qedata) {
                $engine->questionengines[] = $format->import_text($qedata['#']['text']);
            }
        }
        if (isset($enginedata['#']['qb'])) {
            foreach ($enginedata['#']['qb'] as $qbdata) {
                $engine->questionbanks[] = $format->import_text($qbdata['#']['text']);
            }
        }
        $question->engineid = $this->enginemanager->find_or_create($engine);
        return $question;
    }
}
