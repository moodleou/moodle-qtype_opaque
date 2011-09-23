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

/**
 * Get a step from $qa, as if $pendingstep had already been added at the end
 * of the list, if it is not null.
 * @param int $seq
 * @param question_attempt $qa
 * @param question_attempt_step|null $pendingstep
 * @return question_attempt_step
 */
function qtype_opaque_get_step($seq, question_attempt $qa, $pendingstep) {
    if ($seq < $qa->get_num_steps()) {
        return $qa->get_step($seq);
    }
    if ($seq == $qa->get_num_steps() && !is_null($pendingstep)) {
        return $pendingstep;
    }
    throw new coding_exception('Sequence number ' . $seq . ' out of range.');
}

/**
 * Wrapper round $step->get_submitted_data() to work around an incompatibility
 * between OpenMark and the Moodle question engine.
 * @param question_attempt_step $step a step.
 * @return array approximately $step->get_submitted_data().
 */
function qtype_opaque_get_submitted_data(question_attempt_step $step) {
    // By default, OpenMark radio buttons get the name '_rg', whcih breaks
    // one of the assumptions of the qutesion engine, so we have to manually
    // include it when doing get_submitted_data.
    $response = $step->get_submitted_data();
    if ($step->has_qt_var('_rg')) {
        $response['_rg'] = $step->get_qt_var('_rg');
    }
    return $response;
}

/**
 * Update the $SESSION->cached_opaque_state to show the current status of $question for state
 * $state.
 * @param question_attempt $qa the question attempt
 * @param question_attempt_step $pendingstep (opitional) if we are in the process of
 *      adding a new step to the end of the question_attempt, this is it.
 * @param question_display_options $options (optional) display options to pass on
 *      to the question engine
 * @return mixed $SESSION->cached_opaque_state on success, a string error message on failure.
 */
function qtype_opaque_update_state(question_attempt $qa,
        question_attempt_step $pendingstep = null, question_display_options $options = null) {
    global $SESSION;

    $question = $qa->get_question();
    $targetseq = $qa->get_num_steps() - 1;
    if (!is_null($pendingstep)) {
        $targetseq += 1;
    }

    if (!is_null($options)) {
        $optionstring = implode('|', array(
            (int) $options->readonly,
            (int) $options->marks,
            (int) $options->markdp,
            (int) $options->correctness,
            (int) $options->feedback,
            (int) $options->generalfeedback,
        ));
    } else {
        $optionstring = '';
    }

    if (empty($SESSION->cached_opaque_state) ||
            empty($SESSION->cached_opaque_state->qaid) ||
            empty($SESSION->cached_opaque_state->sequencenumber)) {
        $cachestatus = 'empty';
    } else if ($SESSION->cached_opaque_state->qaid != $qa->get_database_id() ||
            $SESSION->cached_opaque_state->sequencenumber > $targetseq ||
            $SESSION->cached_opaque_state->optionstring != $optionstring) {
        // If there is some question session active, try to stop it ...
        if (!empty($SESSION->cached_opaque_state->questionsessionid)) {
            try {
                qtype_opaque_connection::connect($SESSION->cached_opaque_state->engine)->stop(
                        $SESSION->cached_opaque_state->questionsessionid);
            } catch (SoapFault $e) {
                unset($SESSION->cached_opaque_state);
                // ... but ignore any errors when doing so.
            }
        }
        unset($SESSION->cached_opaque_state);
        $cachestatus = 'empty';
    } else if ($SESSION->cached_opaque_state->sequencenumber < $targetseq) {
        $cachestatus = 'catchup';
    } else {
        $cachestatus = 'good';
    }

    $resourcecache = new qtype_opaque_resource_cache($question->engineid,
            $question->remoteid, $question->remoteversion);

    if ($cachestatus == 'empty') {
        $SESSION->cached_opaque_state = new stdClass();
        $opaquestate = $SESSION->cached_opaque_state;
        $opaquestate->qaid = $qa->get_database_id();
        $opaquestate->remoteid = $question->remoteid;
        $opaquestate->remoteversion = $question->remoteversion;
        $opaquestate->engineid = $question->engineid;
        $opaquestate->optionstring = $optionstring;
        $opaquestate->nameprefix = $qa->get_field_prefix();
        $opaquestate->questionended = false;
        $opaquestate->sequencenumber = -1;
        $opaquestate->resultssequencenumber = -1;

        $opaquestate->engine = qtype_opaque_engine_manager::get()->load($question->engineid);
        $connection = qtype_opaque_connection::connect($opaquestate->engine);

        $step = qtype_opaque_get_step(0, $qa, $pendingstep);
        $startreturn = $connection->start($question->remoteid, $question->remoteversion,
                $step->get_all_data(), $resourcecache->list_cached_resources(), $options);

        qtype_opaque_extract_stuff_from_response($opaquestate, $startreturn, $resourcecache);
        $opaquestate->sequencenumber++;
        $cachestatus = 'catchup';

    } else {
        $opaquestate = $SESSION->cached_opaque_state;
        $connection = qtype_opaque_connection::connect($opaquestate->engine);
    }

    if ($cachestatus == 'catchup') {
        if ($opaquestate->sequencenumber >= $targetseq) {
            $connection->stop($opaquestate->questionsessionid);
        }

        while ($opaquestate->sequencenumber < $targetseq) {
            $step = qtype_opaque_get_step($opaquestate->sequencenumber + 1, $qa, $pendingstep);

            $processreturn = $connection->process($opaquestate->questionsessionid,
                    qtype_opaque_get_submitted_data($step));

            if (!empty($processreturn->results)) {
                $opaquestate->resultssequencenumber = $opaquestate->sequencenumber + 1;
                $opaquestate->results = $processreturn->results;
            }

            if ($processreturn->questionEnd) {
                $opaquestate->questionended = true;
                $opaquestate->sequencenumber = $targetseq;
                $opaquestate->xhtml = qtype_opaque_strip_omact_buttons($opaquestate->xhtml);
                unset($opaquestate->questionsessionid);
                break;
            }

            qtype_opaque_extract_stuff_from_response($opaquestate, $processreturn, $resourcecache);
            $opaquestate->sequencenumber++;
        }

        $cachestatus = 'good';
    }

    return $opaquestate;
}

/**
 * Pulls out the fields common to StartResponse and ProcessResponse.
 * @param object $opaquestate should be $SESSION->cached_opaque_state, or equivalent.
 * @param object $response a StartResponse or ProcessResponse.
 * @param object $resourcecache the resource cache for this question.
 * @return true on success, or a string error message on failure.
 */
function qtype_opaque_extract_stuff_from_response($opaquestate, $response, $resourcecache) {
    global $CFG;
    static $replaces;

    if (empty($replaces)) {
        $replaces = array(
            '%%RESOURCES%%' => '', // Filled in below.
            '%%IDPREFIX%%' => '', // Filled in below.
            '%%%%' => '%%'
        );

        $strings = array('lTRYAGAIN', 'lGIVEUP', 'lNEXTQUESTION', 'lENTERANSWER', 'lCLEAR');
        foreach ($strings as $string) {
            $replaces["%%$string%%"] = get_string($string, 'qtype_opaque');
        }
    }

    // Process the XHTML, replacing the strings that need to be replaced.
    $xhtml = $response->XHTML;

    $replaces['%%RESOURCES%%'] = $resourcecache->file_url('');
    $replaces['%%IDPREFIX%%'] = $opaquestate->nameprefix;
    $xhtml = str_replace(array_keys($replaces), $replaces, $xhtml);

    // TODO this is a nasty hack. Flash uses & as a separator in the FlashVars string,
    // so we have to replce the &amp;s with %26s in this one place only. So for now
    // do it with a regexp. Longer term, it might be better to changes the file.php urls
    // so they don't contain &s.
    $xhtml = preg_replace_callback(
            '/name="FlashVars" value="TheSound=[^"]+"/',
            create_function('$matches', 'return str_replace("&amp;", "%26", $matches[0]);'),
            $xhtml);

    // Another hack to take out the next button that most OM questions include,
    // but which does not work in Moodle. Actually, we remove any non-disabled
    // buttons, and the following script tag.
    // TODO think of a better way to do this.
    if ($opaquestate->resultssequencenumber >= 0) {
        $xhtml = qtype_opaque_strip_omact_buttons($xhtml);
    }

    $opaquestate->xhtml = $xhtml;

    // Process the CSS (only when we have a StartResponse).
    if (!empty($response->CSS)) {
        $opaquestate->cssfilename = $resourcecache->stylesheet_filename($response->questionSession);
        $resourcecache->cache_file($opaquestate->cssfilename,
                'text/css;charset=UTF-8',
                str_replace(array_keys($replaces), $replaces, $response->CSS));
    }

    // Process the resources.
    // TODO remove this. Evil hack. IE cannot cope with : and other odd characters
    // in the name argument to window.open. Until we can deploy a fix to the
    // OpenMark servers, apply the fix to the JS code here.
    foreach ($response->resources as $key => $resource) {
        if ($resource->filename == 'script.js') {
            $response->resources[$key]->content = preg_replace(
                    '/(?<=' . preg_quote('window.open("", idprefix') . '|' .
                            preg_quote('window.open("",idprefix') . ')\+(?=\"\w+\"\+id,)/',
                    '.replace(/\W/g,"_")+', $resource->content);
        }
    }
    $resourcecache->cache_resources($response->resources);

    // Save the progress info. Note, another nasty hack pending a permanent fix
    // to OpenMark.
    $opaquestate->progressinfo = str_replace(
            array('attempts', 'attempt'),
            array('tries', 'try'),
            $response->progressInfo);

    // Record the session id.
    if (!empty($response->questionSession)) {
        $opaquestate->questionsessionid = $response->questionSession;
    }

    // Store any head HTML.
    if (!empty($response->head)) {
        $opaquestate->headXHTML = str_replace(array_keys($replaces), $replaces, $response->head);
    }

    return true;
}

/**
 * Strip any buttons, followed by script tags, where the button has an id
 * containing _omact_, and is not disabled.
 */
function qtype_opaque_strip_omact_buttons($xhtml) {
    return preg_replace(
            '|<input(?:(?!disabled=)[^>])*? id="[^"]*_omact_[^"]*"(?:(?!disabled=)[^>])*?>' .
            '<script type="text/javascript">[^<]*</script>|', '', $xhtml);
}

/**
 * OpenMark relies on certain browser-specific class names to be present in the
 * HTML outside the question, in order to apply certian browser-specific layout
 * work-arounds. This function re-implements Om's browser sniffing rules. See
 * http://java.net/projects/openmark/sources/svn/content/trunk/src/util/misc/UserAgent.java
 * @return string class to add to the HTML.
 */
function qtype_opaque_browser_type() {
    $useragent = $_SERVER['HTTP_USER_AGENT'];

    // Filter troublemakers
    if (strpos($useragent, 'KHTML') !== false) {
        return "khtml";
    }
    if (strpos($useragent, 'Opera') !== false) {
        return "opera";
    }

    // Check version of our two supported browsers
    $matches = array();
    if (preg_match('/"^.*rv:(\d+)\\.(\d+)\D.*$"/', $useragent, $matches)) {
        return 'gecko-' . $matches[1] . '-' . $matches[2];
    }
    if (preg_match('/^.*MSIE (\d+)\\.(\d+)\D.*Windows.*$/', $useragent, $matches)) {
        return 'winie-' . $matches[1]; // Major verison only
    }

    return '';
}
