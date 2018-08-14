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
 * Opaque question definition class.
 *
 * @package   qtype_opaque
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Represents an Opaque question.
 *
 * @copyright 2009 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_question extends question_with_responses {
    /** @var integer the ID of the question engine that serves this question. */
    public $engineid;
    /** @var string the id by which the question engine knows this question. */
    public $remoteid;
    /** @var string the version number of this question to use. */
    public $remoteversion;

    public function make_behaviour(question_attempt $qa, $preferredbehaviour) {
        return question_engine::make_behaviour('opaque', $qa, $preferredbehaviour);
    }

    public function get_expected_data() {
        return question_attempt::USE_RAW_DATA;
    }

    public function get_correct_response() {
        // Not possible to say, so just return nothing.
        return array();
    }

    public function get_variants_selection_seed() {
        return "All opaque questions in a usage should get the same variant!";
    }

    public function get_num_variants() {
        // Let Moodle generate the random seed for us.
        return 1000000;
    }

    public function is_complete_response(array $response) {
        // Not acutally used by the behaviour.
        return null;
    }

    public function is_same_response(array $prevresponse, array $newresponse) {
        // Not acutally used by the behaviour.
        return null;
    }

    public function summarise_response(array $response) {
        ksort($response, SORT_NATURAL);

        $formatteddata = array();
        foreach ($response as $name => $value) {
            $formatteddata[] = $name . ' => ' . $value;
        }
        return implode(', ', $formatteddata);
    }
}
