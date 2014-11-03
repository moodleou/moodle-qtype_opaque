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
 * Test helper code for the Opaque question type.
 *
 * @package   qtype_opaque
 * @copyright 2010 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/test_engine_configuration.php');


/**
 * Test helper class for the Opaque question type.
 *
 * @copyright 2010 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_test_helper {
    public function get_test_questions() {
        return array('mu120_m5_q01');
    }

    /**
     * Makes an Opaque question that refers to one of the sample questions
     * supplied by OpenMark.
     * @return qtype_opaque_question
     */
    public function make_opaque_question_mu120_m5_q01() {
        global $DB;

        $engineid = $this->get_engine_id();

        question_bank::load_question_definition_classes('opaque');
        $q = new qtype_opaque_question();
        test_question_maker::initialise_a_question($q);

        $q->name = 'samples.mu120.module5.question01';
        $q->qtype = question_bank::get_qtype('opaque');
        $q->defaultmark = 3;

        $q->engineid = $engineid;
        $q->remoteid = 'samples.mu120.module5.question01';
        $q->remoteversion = '1.6';

        return $q;
    }

    protected function get_engine_id() {
        return qtype_opaque_test_config::setup_test_opaque_engine();
    }
}
