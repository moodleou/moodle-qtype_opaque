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
 * Helper class for setting up the Opaque configuration for automated tests.
 *
 * @package   qtype_opaque
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Helper class for setting up the Opaque configuration for automated tests.
 *
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_opaque_test_config {
    public static function is_test_config_available() {
        return defined('QTYPE_OPAQUE_TEST_ENGINE_QE');
    }

    /**
     * Helper that sets up the Opaque configuration. This allows Opaque to be used
     * from test classes that cannot subclass this one, for whatever reason.
     * @return int the engine id.
     */
    public static function setup_test_opaque_engine() {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/opaque/enginemanager.php');

        if (!self::is_test_config_available()) {
            throw new coding_exception('The calling code should call setup_test_maxima_connection ' .
                    'and skip the test in an appropriate way if it returns false.');
        }

        if (!defined('QTYPE_OPAQUE_TEST_ENGINE_QE')) {
            return null;
        }
        $engine = new stdClass();
        $engine->name = 'Opaque engine for tests';
        $engine->passkey = defined('QTYPE_OPAQUE_TEST_ENGINE_PASSKEY') ? QTYPE_OPAQUE_TEST_ENGINE_PASSKEY : '';
        $engine->timeout = defined('QTYPE_OPAQUE_TEST_ENGINE_TIMEOUT') ? QTYPE_OPAQUE_TEST_ENGINE_TIMEOUT : 10;
        $engine->questionengines = array(QTYPE_OPAQUE_TEST_ENGINE_QE);
        $engine->questionbanks = array();
        if (defined('QTYPE_OPAQUE_TEST_ENGINE_TN')) {
            $engine->questionbanks[] = QTYPE_OPAQUE_TEST_ENGINE_TN;
        }
        return qtype_opaque_engine_manager::get()->save($engine);
    }
}
