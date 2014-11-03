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
 * The question engine edited event.
 *
 * @package   qtype_opaque
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_opaque\event;
defined('MOODLE_INTERNAL') || die();

/**
 * The question engine edited event class.
 *
 * @since     Moodle 2.7
 * @copyright 2014 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 **/
class engine_edited extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'qtype_opaque_engines';
    }

    public static function get_name() {
        return get_string('eventengine_edited', 'qtype_opaque');
    }

    public function get_description() {
        return "The user with id {$this->userid} edited the Opaque question engine with id {$this->objectid}.";
    }

    public function get_url() {
        return new \moodle_url('/question/type/opaque/testengine.php?', array('engineid' => '1'));
    }

    public function get_legacy_logdata() {
        return array($this->courseid, 'qtype_opaque', 'question/type/opaque/engines.php',
                $this->objectid, $this->contextinstanceid);
    }
}
