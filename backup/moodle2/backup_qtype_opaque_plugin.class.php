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
 * @package   qtype_opaque
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Provides the information to backup ddwtos questions.
 *
 * @copyright 2011 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_qtype_opaque_plugin extends backup_qtype_plugin {

    /**
     * Returns the qtype information to attach to question element.
     */
    protected function define_question_plugin_structure() {

        // Define the virtual plugin element with the condition to fulfill.
        $plugin = $this->get_plugin_element(null, '../../qtype', 'opaque');

        // Create one standard named plugin element (the visible container).
        $pluginwrapper = new backup_nested_element($this->get_recommended_name());

        // Connect the visible container ASAP.
        $plugin->add_child($pluginwrapper);

        // Now create the qtype own structures.
        $opaque = new backup_nested_element('opaque', array('id'), array(
            'remoteid', 'remoteversion'));
        $engine = new backup_nested_element('engine', array('id'), array('name', 'passkey', 'timeout'));
        $server = new backup_nested_element('server', array('type'), array('url'));

        // Now the own qtype tree.
        $pluginwrapper->add_child($opaque);
        $opaque->add_child($engine);
        $engine->add_child($server);

        // Set source to populate the data.
        $opaque->set_source_table('qtype_opaque_options',
                array('questionid' => backup::VAR_PARENTID));
        $engine->set_source_sql('
                SELECT e.*
                FROM {qtype_opaque_engines} e
                JOIN {qtype_opaque_options} qo ON qo.engineid = e.id
                WHERE qo.id = ?',
                array(backup::VAR_PARENTID));
        $server->set_source_table('qtype_opaque_servers',
                array('engineid' => backup::VAR_PARENTID));

        return $plugin;
    }

    /**
     * Returns one array with filearea => mappingname elements for the qtype.
     *
     * Used by {@link get_components_and_fileareas} to know about all the qtype.
     * files to be processed both in backup and restore.
     */
    public static function get_qtype_fileareas() {
        return array(
            'correctfeedback' => 'question_created',
            'partiallycorrectfeedback' => 'question_created',
            'incorrectfeedback' => 'question_created');
    }
}
