<?php
// This file is part of Stack - http://stack.bham.ac.uk/
//
// Stack is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stack is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stack.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat steps definitions for the Opaque question type.
 *
 * @package   qtype_opaque
 * @category  test
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

use Moodle\BehatExtension\Exception\SkippedException;

/**
 * Steps definitions related to the Opaque question type.
 *
 * @package   qtype_opaque
 * @category  test
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_qtype_opaque extends behat_base {

    /**
     * This step looks to see if there is information about a Questoin engine
     * configuration for testing in the config.php file. If there is, it creates
     * an engine configuration with name "Opaque engine for tests",
     * otherwise it skips this scenario.
     *
     * @When /^I set up Opaque using the test configuration$/
     */
    public function iSetUpOpaqueUsingTheTestConfiguration() {
        // The require_once is here, this file may be required by behat before including /config.php.
        require_once(__DIR__ . '/../test_engine_configuration.php');

        if (!qtype_opaque_test_config::is_test_config_available()) {
            throw new SkippedException('To run this Opaque test, ' .
                    ' you must define a test engine configuration in config.php.');
        }

        qtype_opaque_test_config::setup_test_opaque_engine();
    }
}
