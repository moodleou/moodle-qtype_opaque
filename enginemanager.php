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
 * Defines the qtype_opaque_engine_manager class.
 *
 * @package   qtype_opaque
 * @copyright 2006 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/opaque/connection.php');


/**
 * Manages loading and saving question engine definitions to and from the database.
 *
 * @copyright 2010 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_engine_manager {

    protected static $instance = null;

    /** @return qtype_opaque_engine_manager get the engine manager. */
    public static function get() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @return array engine id => name of installed engines that can be used in the UI.
     */
    public function choices() {
        global $DB;
        return $DB->get_records_menu('qtype_opaque_engines', array(), 'name ASC', 'id, name');
    }

    /**
     * Load the definition of an engine from the database.
     * @param int $engineid the id of the engine to load.
     * @return mixed On success, and object with fields id, name, questionengines
     *      and questionbanks. The last two fields are arrays of URLs. On an error,
     *      returns a string to look up in the qtype_opaque language file as an
     *      error message.
     */
    public function load($engineid) {
        global $DB;
        $engine = $DB->get_record('qtype_opaque_engines',
                array('id' => $engineid), '*', MUST_EXIST);

        $engine->questionengines = array();
        $engine->questionbanks = array();
        $servers = $DB->get_records('qtype_opaque_servers',
                array('engineid' => $engineid), 'id ASC');

        if (!$servers) {
            throw new moodle_exception('couldnotloadengineservers', 'qtype_opaque', '', $engineid);
        }

        foreach ($servers as $server) {
            if ($server->type == 'qe') {
                $engine->questionengines[] = $server->url;
            } else if ($server->type == 'qb') {
                $engine->questionbanks[] = $server->url;
            } else {
                throw new moodle_exception('unrecognisedservertype', 'qtype_opaque', '', $engineid);
            }
        }

        return $engine;
    }

    /**
     * Save or update an engine definition in the database, and returm the engine id. The definition
     * will be created if $engine->id is not set, and updated if it is.
     *
     * @param object $engine the definition to save.
     * @return int the id of the saved definition.
     */
    public function save($engine) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();

        if (!empty($engine->id)) {
            $DB->update_record('qtype_opaque_engines', $engine);
        } else {
            $engine->id = $DB->insert_record('qtype_opaque_engines', $engine);
        }
        $DB->delete_records('qtype_opaque_servers', array('engineid' => $engine->id));
        $this->store_opaque_servers($engine->questionengines, 'qe', $engine->id);
        $this->store_opaque_servers($engine->questionbanks, 'qb', $engine->id);

        $transaction->allow_commit();
        return $engine->id;
    }

    /**
     * Save a list of servers of a given type in the qtype_opaque_servers table.
     *
     * @param array $urls an array of URLs.
     * @param string $type 'qe' or 'qb'.
     * @param int $engineid
     */
    protected function store_opaque_servers($urls, $type, $engineid) {
        global $DB;
        foreach ($urls as $url) {
            $server = new stdClass();
            $server->engineid = $engineid;
            $server->type = $type;
            $server->url = $url;
            $DB->insert_record('qtype_opaque_servers', $server, false);
        }
    }

    /**
     * Delete the definition of an engine from the database.
     * @param int $engineid the id of the engine to delete.
     * @return bool whether the delete succeeded.
     */
    public function delete($engineid) {
        global $DB;
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('qtype_opaque_servers', array('engineid' => $engineid));
        $DB->delete_records('qtype_opaque_engines', array('id' => $engineid));
        $transaction->allow_commit();
    }

    protected function get_possibly_matching_engines($engine) {
        global $DB;

        // First we try to get a reasonably accurate guess with SQL - we load
        // the id of all engines with the same passkey and which use the first
        // questionengine and questionbank (if any).
        $tables = array('FROM {qtype_opaque_engines} e');
        $conditions = array('e.passkey = :passkey');
        $params = array('passkey' => $engine->passkey);
        if (!empty($engine->questionengines)) {
            $qeurl = reset($engine->questionengines);
            $tables[] = "JOIN {qtype_opaque_servers} qe ON
                    qe.engineid = e.id AND qe.type = 'qe'";
            $conditions[] = 'qe.url = :qeurl';
            $params['qeurl'] = $qeurl;
        }
        if (!empty($engine->questionbanks)) {
            $qburl = reset($engine->questionbanks);
            $tables[] = "JOIN {qtype_opaque_servers} qb ON
                    qb.engineid = e.id AND qb.type = 'qb'";
            $conditions[] = 'qb.url = :qburl';
            $params['qburl'] = $qburl;
        }
        return $DB->get_records_sql_menu('
                SELECT e.id,1 ' . implode(' ', $tables) . ' WHERE ' .
        implode(' AND ', $conditions), $params);
    }

    /**
     * If an engine definition like this one (same passkey and server lists) already exists
     * in the database, then return its id, otherwise save this one to the database and
     * return the new engine id.
     *
     * @param object $engine the engine to ensure is in the databse.
     * @return int its id.
     */
    public function find_or_create($engine) {
        $possibleengineids = $this->get_possibly_matching_engines($engine);

        // Then we loop through the possibilities loading the full definition and comparing it.
        if ($possibleengineids) {
            foreach ($possibleengineids as $engineid => $ignored) {
                $testengine = $this->load($engineid);
                $testengine->passkey = $testengine->passkey;
                if ($this->is_same($engine, $testengine)) {
                    return $engineid;
                }
            }
        }

        return $this->save($engine);
    }

    /**
     * Are these two engine definitions essentially the same (same passkey and server lists)?
     *
     * @param object $engine1 one engine definition.
     * @param object $engine2 another engine definition.
     * @return bool whether they are the same.
     */
    public function is_same($engine1, $engine2) {
        // Same passkey.
        $ans = $engine1->passkey == $engine2->passkey &&
        // Same question engines.
        !array_diff($engine1->questionengines, $engine2->questionengines) &&
        !array_diff($engine2->questionengines, $engine1->questionengines) &&
        // Same question banks.
        !array_diff($engine1->questionbanks, $engine2->questionbanks) &&
        !array_diff($engine2->questionbanks, $engine1->questionbanks);
        return $ans;
    }

    /**
     * Connect to a particular question engine.
     * @param object $engine the engine definition.
     * @return qtype_opaque_connection the opaque connection that can be used
     *      to make SOAP calls.
     */
    public function get_connection($engine) {
        return new qtype_opaque_connection($engine);
    }

    /**
     * Get the remote info from a question engine.
     * @param object $engine the engine definition.
     * @return some XML, as parsed by xmlize giving the status of the engine.
     */
    public function get_engine_info($engine) {
        return $this->get_connection($engine)->get_engine_info();
    }
}
