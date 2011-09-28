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
 * Defines the qtype_opaque_state class.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2006 The Open University, 2011 Antti Andreimann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Stores active OPAQUE question session and caches associated results
 *
 * @copyright  2011 Antti Andreimann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_opaque_state {

    protected $resourcecahce;
    protected $connection;
    protected $replaces;

    /**
     * Get a cached state associated with the question attempt
     * if cached state is not available, a new one is created
     * and associated with the question attempt
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) a pending initial step
     * @return qtype_opaque_state an opaque state object
     */
    public static function get($qa, $pendingstep = null) {
        return new self($qa, $pendingstep);
    }

    /**
     * Calculate a hash code that should not change during the lifetime
     * of a question attempt and allows us to find the matching
     * cache entries without relying on the database ID.
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) a pending initial step
     * @return string a unique hash code for the question attempt
     *
     */
    protected static function calculateCacheKey($qa, $pendingstep = null) {
        $firststep = qtype_opaque_state::find_step(0, $qa, $pendingstep);

        if(is_null($firststep))
            throw new coding_exception('Unable to find the fist step to extract initial parameters from');

        // We expect the question that is attempted to be stored in the database.
        // Even if it is not the case, prepending an empty id to the data
        // will not cause any harm.
        $initdata = $qa->get_question()->id;

        /* TODO find an answer to these philosophical questions:
         *   1. Shouldn't initParameters be stored in question var-s instead
         *      of behaviour vars and created in question->start_attempt ?
         *   2. Why don't we use $question->get_variants_selection_seed
         *      for the random seed?
         */

        // Extract initialization data from the first step

        $randomseed = $firststep->get_behaviour_var('_randomseed');
        if(is_null($randomseed))
            throw new coding_exception('No random seed in first step');
        $initdata .= $randomseed;

        $initdata .= $firststep->get_behaviour_var('_userid');
        $initdata .= $firststep->get_behaviour_var('_language');
        $initdata .= $firststep->get_behaviour_var('_preferredbehaviour');

        return md5($initdata);
    }

    /**
     * Get a step from $qa, as if $pendingstep had already been added at the end
     * of the list, if it is not null.
     *
     * @param int $seq
     * @param question_attempt $qa
     * @param question_attempt_step|null $pendingstep
     * @return question_attempt_step
     */
    protected static function find_step($seq, question_attempt $qa, $pendingstep) {
        if ($seq < $qa->get_num_steps()) {
            return $qa->get_step($seq);
        }
        if ($seq == $qa->get_num_steps() && !is_null($pendingstep)) {
            return $pendingstep;
        }
        throw new coding_exception('Sequence number ' . $seq . ' out of range.');
    }

    /**
     * Create a new cached state and link it to a question attempt.
     * 
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) a pending initial step
     */
    protected function __construct($qa, $pendingstep) {
        $this->load_state($qa, $pendingstep);

        if(!$this->validateState($qa))
            $this->invalidate();

        if(empty($this->state)) {
            $this->new_state($qa, $pendingstep);
        }

        // TODO compare to old prefix
        $this->state->nameprefix = $qa->get_field_prefix();
    }

    /**
     * Find a state associated with this question attempt from the cache.
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) a pending initial step
     */
    protected function load_state($qa, $pendingstep) {
        $key = qtype_opaque_state::calculateCacheKey($qa, $pendingstep);
        $this->state = qtype_opaque_cache_manager::get()->load($key);
    }

    /**
     * Check if the cached state is valid for a question attempt.
     * 
     * @param question_attempt $qa the question attempt to use
     * @return true if cached state is valid, false othervise
     */
    public function validateState($qa) {
        if(empty($this->state))
            return false;

        if($this->state->hash != qtype_opaque_state::calculateHashCode($qa))
            return false;

        return true;
    }

    /**
     * Invalidate the cached state.
     * Close a question session if it's still active.
     */
    public function invalidate() {
        if(empty($this->state))
            return; // Already invalidated

        // If there is some question session active, try to stop it ...
        if(!empty($this->state->questionsessionid)) {
            try {
                $this->get_connection()->stop($this->state->questionsessionid);
            } catch (SoapFault $e) {
                // ... but ignore any errors when doing so.
            }
        }

        qtype_opaque_cache_manager::get()->delete($this->state->cacheKey);
        $this->state = null;
    }

    /**
     * Start a question session and cache the results.
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) a pending initial step
     */
    public function do_start($qa, $pendingstep, $options = null) {
        $this->state->nameprefix = $qa->get_field_prefix();
        $step = qtype_opaque_state::find_step(0, $qa, $pendingstep);
        $resourcecache = $this->get_resource_cache();

        $startreturn = $this->get_connection()->start($this->state->remoteid, $this->state->remoteversion,
                $step->get_all_data(), $resourcecache->list_cached_resources(),
                $options);

        if(isset($startreturn->protocolVersion))
            $this->state->remoteProtocolVersion = $startreturn->protocolVersion;
        else
            $this->state->remoteProtocolVersion = 0;

        $this->extract_stuff_from_response($startreturn, $resourcecache);
        $this->state->sequencenumber++;
    }

    /**
     * Take first unprocessed step and send it to the engine.
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) if we are in
     *      the process of adding a new step to the end of the question_attempt,
     *      this is it.
     */
    public function do_process($qa, $pendingstep = null) {
        $this->state->nameprefix = $qa->get_field_prefix();
        $step = qtype_opaque_state::find_step($this->state->sequencenumber + 1, $qa, $pendingstep);
        $resourcecache = $this->get_resource_cache();

        $data = $step->get_submitted_data();

        // Apply OpenMark hacks
        if($this->state->remoteProtocolVersion < 1)
            $data = qtype_opaque_hacks_get_submitted_data($step);

        try {
            $processreturn = $this->get_connection()->process($this->state->questionsessionid, $data);
        } catch (SoapFault $e) {
            $this->invalidate();
            throw $e;
        }

        if (!empty($processreturn->results)) {
            $this->state->results = $processreturn->results;

            // FIXME is the following line a programming bug or a clever hack? 
            $this->state->resultssequencenumber = $this->state->sequencenumber + 1;
        }

        if ($processreturn->questionEnd) {
            $this->state->questionended = true;
            // TODO maybe we should call stop as well?
            unset($this->state->questionsessionid);

            // FIXME what to do with this hack?
            return;
        }

        $this->extract_stuff_from_response($processreturn, $resourcecache);
        $this->state->sequencenumber++;
    }

    /**
     * Update opaque state to match the question attempt by sending
     * user data in the pending step to the engine for processing.
     * If engine session has not been started yet, start is called,
     * if the step can not be processed by the current session (eg. it's
     * out of sequence), the session will be restarted and the entire
     * history played back.
     *
     * @param question_attempt $qa the question attempt to use
     * @param question_attempt_step $pendingstep (optional) if we are in
     *      the process of adding a new step to the end of the question_attempt,
     *      this is it.
     * @param question_display_options $options (optional) display options to
     *      pass on to the question engine
     */
    public function update($qa, $pendingstep = null, $options = null) {
        $targetseq = $qa->get_num_steps() - 1;
        if (!is_null($pendingstep)) {
            $targetseq += 1;
        }

        // If the engine is ahead of us we must close the session and start over
        if($this->state->sequencenumber > $targetseq) {
            $this->invalidate();
            $this->new_state($qa, $pendingstep);
        }

        // If we have never started a session for this state, do it now!
        if ($this->state->sequencenumber < 0) {
            $this->do_start($qa, $pendingstep, $options);
        }

        // Now play back the user input
        // TODO for slower engines and longer sequences there is a risk to
        //      hit the PHP script runtime limit or a browser connection
        //      timeout. Investigate options to play back some steps and
        //      then force web browser to poll for more with a redirect.
        while ($this->state->sequencenumber < $targetseq) {
            $this->do_process($qa, $pendingstep);
            if($this->state->questionended) {
                $this->state->sequencenumber = $targetseq;
                break;
            }
        }
    }

    /**
     * Get a properly filtered question XHTML 
     */
    public function get_xhtml() {
        $replaces = $this->get_replaces();

        // Process the XHTML, replacing the strings that need to be replaced.
        $xhtml = str_replace(array_keys($replaces), $replaces, $this->state->xhtml);

        // Apply OpenMark hacks
        if($this->state->remoteProtocolVersion < 1)
            $xhtml = qtype_opaque_hacks_filter_xhtml($xhtml, $this->state);

        return $xhtml;
    }

    /**
     * Get a piece of html that should be included in <head>
     */
    public function get_head_xhtml() {
        $replaces = $this->get_replaces();
        return str_replace(array_keys($replaces), $replaces, $this->state->head);
    }

    public function get_results() {
        return $this->state->results;
    }

    public function get_cssfilename() {
        return $this->state->cssfilename;
    }

    public function get_progressinfo() {
        return $this->state->progressinfo;
    }

    // TODO remove this hackish thing ...
    public function get_resultssequencenumber() {
        return $this->state->resultssequencenumber;
    }

    /**
     * Create a new state and store it in cache.
     *
     * @param question_attempt $qa the question attempt to use
     * @return mixed a stdClass with attributes for holding the cached state
     */
    protected function new_state($qa, $pendingstep) {
        $question = $qa->get_question();
        $rv = new stdClass();

        $rv->engineid              = $question->engineid;
        $rv->remoteid              = $question->remoteid;
        $rv->remoteversion         = $question->remoteversion;
        $rv->nameprefix            = $qa->get_field_prefix();
        $rv->hash                  = qtype_opaque_state::calculateHashCode($qa);
        $rv->cacheKey              = qtype_opaque_state::calculateCacheKey($qa, $pendingstep);
        $rv->engine                = qtype_opaque_engine_manager::get()->load($question->engineid);
        $rv->questionended         = false;
        $rv->sequencenumber        = -1;
        $rv->resultssequencenumber = -1;
        $rv->remoteProtocolVersion = 0;
        $rv->xhtml                 = null;
        $rv->questionsessionid     = null;
        $rv->headXHTML             = null;
        $rv->results               = null;
        $rv->cssfilename           = null;
        $rv->progressinfo          = null;

        $this->state = $rv;
        qtype_opaque_cache_manager::get()->save($rv->cacheKey, $this->state);
    }

    /**
     * Calculate a hash code over the variables that should not change
     * during the lifetime of an OPAQUE session. If the code changes,
     * it indicates that something important is changed and the cached
     * state is no longer valid.
     *
     * @param question_attempt $qa the question attempt to use
     * @return string a unique hash code
     *
     */
    protected static function calculateHashCode($qa) {
        $question = $qa->get_question();

        $engineid      = $question->engineid;
        $remoteid      = $question->remoteid;
        $remoteversion = $question->remoteversion;

        // validate that required data is present
        if(empty($engineid))
            throw new coding_exception('engineid is missing from question');
        if(empty($remoteid))
            throw new coding_exception('remoteid is missing from question');
        if(empty($remoteversion))
            throw new coding_exception('remoteversion is missing from question');

        $data = $engineid . $remoteid . $remoteversion;

        // TODO: Add random seed once it becomes available

        return md5($data);
    }

    /**
     * Get resource cache associated with the current opaque state
     *
     * @return qtype_opaque_resource_cache
     */
    protected function get_resource_cache() {
        if(empty($this->resourcecache))
            $this->resourcecache = new qtype_opaque_resource_cache(
                    $this->state->engineid, $this->state->remoteid,
                    $this->state->remoteversion);

        return $this->resourcecache;
    }

    /**
     * Get connection to the correct question engine
     *
     * @return mixed an opaque connection
     */
    protected function get_connection() {
        if(empty($this->connection))
            $this->connection = qtype_opaque_engine_manager::get()->get_connection($this->state->engine);

        return $this->connection;
    }

    /**
     * Create a map of replacements that must be applied to
     * question xhtml, CSS and header.
     *
     * @return array a map of replacements
     */
    protected function get_replaces() {
        if(!empty($this->replaces))
            return $this->replaces;

        $this->replaces = array(
            '%%RESOURCES%%' => $this->get_resource_cache()->file_url(''),
            '%%IDPREFIX%%' => $this->state->nameprefix,
            '%%%%' => '%%'
        );

        $strings = array('lTRYAGAIN', 'lGIVEUP', 'lNEXTQUESTION', 'lENTERANSWER', 'lCLEAR');
        foreach ($strings as $string) {
            $this->replaces["%%$string%%"] = get_string($string, 'qtype_opaque');
        }

        return $this->replaces;
    }

    /**
     * Pulls out the fields common to StartResponse and ProcessResponse.
     *
     * @param object $response a StartResponse or ProcessResponse.
     * @param object $resourcecache the resource cache for this question.
     */
    protected function extract_stuff_from_response($response,
            qtype_opaque_resource_cache $resourcecache) {

        // Apply OpenMark hacks
        if($this->state->remoteProtocolVersion < 1)
            qtype_opaque_hacks_filter_response($response);

        $this->state->xhtml = $response->XHTML;

        // Record the session id.
        if (!empty($response->questionSession))
            $this->state->questionsessionid = $response->questionSession;

        // Process the CSS
        if (!empty($response->CSS)) {
            $this->state->cssfilename = $resourcecache->stylesheet_filename(
                    $this->state->questionsessionid);

            // TODO %%IDPREFIX%% is not stable so we should re-filter CSS when it changes
            $replaces = $this->get_replaces();
            $resourcecache->cache_file($this->state->cssfilename, 'text/css;charset=UTF-8',
                    str_replace(array_keys($replaces), $replaces, $response->CSS));
        }

        // Process the resources.
        if(isset($response->resources))
            $resourcecache->cache_resources($response->resources);
     
        // Save the progress info. 
        if(isset($response->progressInfo))
            $this->state->progressinfo = $response->progressInfo;

        // Store any head HTML.
        if (!empty($response->head))
            $this->state->headXHTML = $response->head;

        return true;
    }

    // TODO option string is currently not used for anything
    protected function make_optionstring($options) {
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

        return $optionstring;
    }
}
