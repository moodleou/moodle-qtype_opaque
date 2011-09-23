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
/** Prefix used for CSS files. */
define('OPAQUE_CSS_FILENAME_PREFIX', '__styles_');

define('OPAQUE_SOAP_TIMEOUT', 10);

/**
 * @return an array id -> enginename, that can be used to build a dropdown
 * menu of installed question types.
 */
function qtype_opaque_installed_engine_choices() {
    global $DB;
    return $DB->get_records_menu('question_opaque_engines', array(), 'name ASC', 'id, name');
}

/**
 * Load the definition of an engine from the database.
 * @param int $engineid the id of the engine to load.
 * @return mixed On success, and object with fields id, name, questionengines and questionbanks.
 * The last two fields are arrays of URLs. On an error, returns a string to look up in the
 * qtype_opaque language file as an error message.
 */
function qtype_opaque_load_engine_def($engineid) {
    $manager = new qtype_opaque_engine_manager();
    return $manager->load_engine_def($engineid);
}

/**
 * Save or update an engine definition in the database, and returm the engine id. The definition
 * will be created if $engine->id is not set, and updated if it is.
 *
 * @param object $engine the definition to save.
 * @return int the id of the saved definition.
 */
function qtype_opaque_save_engine_def($engine) {
    $manager = new qtype_opaque_engine_manager();
    return $manager->save_engine_def($engine);
}

/**
 * Delete the definition of an engine from the database.
 * @param int $engineid the id of the engine to delete.
 * @return bool whether the delete succeeded.
 */
function qtype_opaque_delete_engine_def($engineid) {
    $manager = new qtype_opaque_engine_manager();
    return $manager->delete_engine_def($engineid);
}

/**
 * If an engine definition like this one (same passkey and server lists) already exists
 * in the database, then return its id, otherwise save this one to the database and
 * return the new engine id.
 *
 * @param object $engine the engine to ensure is in the databse.
 * @return int its id.
 */
function qtype_opaque_find_or_create_engineid($engine) {
    $manager = new qtype_opaque_engine_manager();
    return $manager->find_or_create_engineid($engine);
}

/**
 * @param mixed $engine either an $engine object, or the URL of a particular
 *      question engine server.
 * @return a soap connection, either to the specific URL give, or to to one of
 *      the question engine servers of this $engine object picked at random.
 *      returns a string to look up in the qtype_opaque language file as an error
 *      message if a problem arises.
 */
function qtype_opaque_connect($engine) {
    if (is_string($engine)) {
        $url = $engine;
    } else if (!empty($engine->urlused)) {
        $url = $engine->urlused;
    } else {
        $url = $engine->questionengines[array_rand($engine->questionengines)];
    }
    ini_set('default_socket_timeout', OPAQUE_SOAP_TIMEOUT);
    $connection = new SoapClient($url . '?wsdl', array(
        'soap_version'       => SOAP_1_1,
        'exceptions'         => true,
        'connection_timeout' => OPAQUE_SOAP_TIMEOUT,
        'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
    ));
    if (!is_string($engine)) {
        $engine->urlused = $url;
    }
    return $connection;
}

/**
 * @param mixed $engine either an $engine object, or the URL of a particular
 *      question engine server.
 * @return some XML, as parsed by xmlize, on success, or a string to look up in
 *      the qtype_opaque language file as an error message.
 */
function qtype_opaque_get_engine_info($engine) {
    $connection = qtype_opaque_connect($engine);
    $getengineinforesult = $connection->getEngineInfo();
    return xmlize($getengineinforesult);
}

/**
 * @param mixed $engine either an $engine object, or the URL of a particular
 *      question engine server.
 * @return The question metadata, as an xmlised array, so, for example,
 *      $metadata[questionmetadata][@][#][scoring][0][#][marks][0][#] is the
 *      maximum possible score for this question.
 */
function qtype_opaque_get_question_metadata($engine, $remoteid, $remoteversion) {
    $connection = qtype_opaque_connect($engine);
    if (!empty($engine->questionbanks)) {
        $questionbaseurl = $engine->questionbanks[array_rand($engine->questionbanks)];
    } else {
        $questionbaseurl = '';
    }
    $getmetadataresult = $connection->getQuestionMetadata(
            $remoteid, $remoteversion, $questionbaseurl);
    return xmlize($getmetadataresult);
}

/**
 * @param object $engine the engine to connect to.
 * @param string $remoteid
 * @param string $remoteversion
 * @param int $randomseed
 * @param question_display_options|null $options
 * @return mixed the result of the soap call on success, or a string error message on failure.
 */
function qtype_opaque_start_question_session($engine, $remoteid, $remoteversion,
        $data, $cached_resources, question_display_options $options = null) {
    $connection = qtype_opaque_connect($engine);

    $questionbaseurl = '';
    if (!empty($engine->questionbanks)) {
        $questionbaseurl = $engine->questionbanks[array_rand($engine->questionbanks)];
    }

    $initialparams = array(
        'randomseed' => $data['-_randomseed'],
        'userid' => $data['-_userid'],
        'language' => $data['-_language'],
        'passKey' => qtype_opaque_generate_passkey($engine->passkey, $data['-_userid']),
        'preferredbehaviour' => $data['-_preferredbehaviour'],
    );

    if (!is_null($options)) {
        $initialparams['display_readonly'] = (int) $options->readonly;
        $initialparams['display_marks'] = (int) $options->marks;
        $initialparams['display_markdp'] = (int) $options->markdp;
        $initialparams['display_correctness'] = (int) $options->correctness;
        $initialparams['display_feedback'] = (int) $options->feedback;
        $initialparams['display_generalfeedback'] = (int) $options->generalfeedback;
    }

    return $connection->start($remoteid, $remoteversion, $questionbaseurl,
            array_keys($initialparams), array_values($initialparams), $cached_resources);
}

function qtype_opaque_process($engine, $questionsessionid, $response) {
    $connection = qtype_opaque_connect($engine);
    return $connection->process($questionsessionid, array_keys($response),
            array_values($response));
}

/**
 * @param string $questionsessionid the question session to stop.
 * @return true on success, or a string error message on failure.
 */
function qtype_opaque_stop_question_session($engine, $questionsessionid) {
    $connection = qtype_opaque_connect($engine);
    $connection->stop($questionsessionid);
    return true;
}

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
                qtype_opaque_stop_question_session($SESSION->cached_opaque_state->engine,
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

        $engine = qtype_opaque_load_engine_def($question->engineid);
        if (is_string($engine)) {
            unset($SESSION->cached_opaque_state);
            return $engine;
        }
        $opaquestate->engine = $engine;

        $step = qtype_opaque_get_step(0, $qa, $pendingstep);
        try {
            $startreturn = qtype_opaque_start_question_session($engine, $question->remoteid,
                $question->remoteversion, $step->get_all_data(),
                $resourcecache->list_cached_resources(), $options);

        } catch (SoapFault $e) {
            unset($SESSION->cached_opaque_state);
            throw $e;
        }

        qtype_opaque_extract_stuff_from_response($opaquestate, $startreturn, $resourcecache);
        $opaquestate->sequencenumber++;
        $cachestatus = 'catchup';
    } else {
        $opaquestate = $SESSION->cached_opaque_state;
    }

    if ($cachestatus == 'catchup') {
        if ($opaquestate->sequencenumber >= $targetseq) {
            $error = qtype_opaque_stop_question_session($opaquestate->engine,
                    $opaquestate->questionsessionid);
        }
        while ($opaquestate->sequencenumber < $targetseq) {
            $step = qtype_opaque_get_step($opaquestate->sequencenumber + 1, $qa, $pendingstep);

            try {
                $processreturn = qtype_opaque_process($opaquestate->engine,
                    $opaquestate->questionsessionid, qtype_opaque_get_submitted_data($step));
            } catch (SoapFault $e) {
                unset($SESSION->cached_opaque_state);
                throw $e;
            }

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
 * File name used to store the CSS of the question, question session id is appended.
 */
function qtype_opaque_stylesheet_filename($questionsessionid) {
    return OPAQUE_CSS_FILENAME_PREFIX . $questionsessionid . '.css';
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
    // FIXME The OPAQUE protocol spec does specify such limitation
    if (!empty($response->CSS) && !empty($response->questionSession)) {
        $css = str_replace(array_keys($replaces), $replaces, $response->CSS);
        $opaquestate->cssfilename = qtype_opaque_stylesheet_filename($response->questionSession);
        $resourcecache->cache_file($opaquestate->cssfilename,
                'text/css;charset=UTF-8', $css);
    }

    // Process the resources.
    // TODO remove this. Evil hack. IE cannot cope with : and other odd characters
    // in the name argument to window.open. Until we can deploy a fix to the
    // OpenMark servers, apply the fix to the JS code here.
    if(!empty($response->resources)) {
        foreach ($response->resources as $key => $resource) {
            if ($resource->filename == 'script.js') {
                $response->resources[$key]->content = preg_replace(
                        '/(?<=' . preg_quote('window.open("", idprefix') . '|' .
                                preg_quote('window.open("",idprefix') . ')\+(?=\"\w+\"\+id,)/',
                        '.replace(/\W/g,"_")+', $resource->content);
            }
        }
        $resourcecache->cache_resources($response->resources);
    }

    // Process the other bits.
    if(!empty($response->progressinfo))
        $opaquestate->progressinfo = $response->progressInfo;
    else
        $opaquestate->progressinfo = null;

    if (!empty($response->questionSession)) {
        $opaquestate->questionsessionid = $response->questionSession;
    }

    if (!empty($response->head)) {
        $head = str_replace(array_keys($replaces), $replaces, $response->head);
        $opaquestate->headXHTML = $head;
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
 * @param string $secret the secret string for this question engine.
 * @param int $userid the id of the user attempting this question.
 * @return string the passkey that needs to be sent to the quetion engine to
 *      show that we are allowed to start a question session for this user.
 */
function qtype_opaque_generate_passkey($secret, $userid) {
    return md5($secret . $userid);
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
