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
 * Library routines used by the Opaque question type.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2006 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/xmlize.php');
require_once($CFG->dirroot . '/question/type/opaque/enginemanager.php');
require_once($CFG->dirroot . '/question/type/opaque/resourcecache.php');
require_once($CFG->dirroot . '/question/type/opaque/connection.php');
require_once($CFG->dirroot . '/question/type/opaque/cachemanager.php');
require_once($CFG->dirroot . '/question/type/opaque/hacks.php');
require_once($CFG->dirroot . '/question/type/opaque/opaquestate.php');


/** User passed on question. Should match the definition in Om.question.Results. */
define('OPAQUE_ATTEMPTS_PASS', 0);
/**
 * User got question wrong after all attempts. Should match the definition in
 * om.question.Results.
 */
define('OPAQUE_ATTEMPTS_WRONG', -1);
/**
 * User got question partially correct after all attempts. Should match the
 * definition in om.question.Results.
 */
define('OPAQUE_ATTEMPTS_PARTIALLYCORRECT', -2);
/** If developer hasn't set the value. Should match the definition in om.question.Results. */
define('OPAQUE_ATTEMPTS_UNSET', -99);

// TODO remove these "compatibility" functions once the opaque behaviour is updated

function qtype_opaque_update_state(question_attempt $qa,
        question_attempt_step $pendingstep = null, question_display_options $options = null) {

    $opaquestate = qtype_opaque_state::get($qa, $pendingstep);
    $opaquestate->update($qa, $pendingstep, $options);

    $opaquestate->xhtml = $opaquestate->get_xhtml();
    $opaquestate->results = $opaquestate->get_results();
    $opaquestate->cssfilename = $opaquestate->get_cssfilename();
    $opaquestate->progressinfo = $opaquestate->get_progressinfo();
    $opaquestate->resultssequencenumber = $opaquestate->get_resultssequencenumber();

    return $opaquestate;
}

function qtype_opaque_get_submitted_data(question_attempt_step $step) {
    return qtype_opaque_hacks_get_submitted_data($step);
}

function qtype_opaque_browser_type() {
    return qtype_opaque_hacks_browser_type();
}
