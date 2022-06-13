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

namespace qtype_opaque;

use local_systemcheck\remote_check_result;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/opaque/enginemanager.php');

/**
 * Remote check for the Opaque question type: verify we can connect to the share we use.
 *
 * @package qtype_opaque
 * @copyright 2021 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_systemcheck_remote extends \local_systemcheck\remote_check {
    /** @var int the engine this check is for. */
    protected $engineid;

    /** @var string the engine this check is for. */
    protected $enginename;

    public function __construct(int $engineid, string $enginename) {
        $this->engineid = $engineid;
        $this->enginename = $enginename;
    }

    public function get_id(): string {
        return 'qtype_opaque_engine_' . $this->engineid;
    }

    public function get_name(): string {
        return 'Opaque engine ' . $this->enginename;
    }

    public static function get_checks(): array {
        $checks = [];
        $enginemanager = \qtype_opaque_engine_manager::get();

        foreach ($enginemanager->choices() as $engineid => $enginename) {
            $checks[] = new self($engineid, $enginename);
        }
        return $checks;
    }

    public function execute(): remote_check_result {
        $enginemanager = \qtype_opaque_engine_manager::get();
        $engine = $enginemanager->load($this->engineid);
        // Remote check needs to fail quickly if it is going to fail, so reduce the timeout.
        $engine->timeout = min($engine->timeout, 2);

        foreach ($engine->questionengines as $engineurl) {
            $engine->urlused = $engineurl;
            $info = $enginemanager->get_engine_info($engine);
            if (!is_array($info) || !isset($info['engineinfo']['#'])) {
                return new remote_check_result(false, 'Failed to get engine info from ' . $engineurl);
            }
        }

        return new remote_check_result(true, 'Connected to: ' . implode(', ', $engine->questionengines));
    }
}
