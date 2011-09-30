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
 * Defines the qtype_opaque_cache_manager class.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2011 Antti Andreimann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * Manages opaque question sessions and associated results cache
 *
 * @copyright  2011 Antti Andreimann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class qtype_opaque_cache_manager {
    /** @var array reference to where the data is acutally stored in the session. */
    protected $cache;

    /** @var qtype_opaque_cache_manager singleton instance. */
    protected static $manager;

    /**
     * Constructor.
     */
    protected function __construct() {
        global $SESSION;

        if (!isset($SESSION->opaque_state_cache) ||
                !is_array($SESSION->opaque_state_cache)) {
            $SESSION->opaque_state_cache = array();
        }

        $this->cache = &$SESSION->opaque_state_cache;
    }

    /**
     * Get the cache manager instance associated with the current
     * user session or create a new one if it does not exist.
     * 
     * @return qtype_cache_manager the cache manager instance
     */
    public static function get() {
        if (empty($class->manager)) {
            $class->manager = new self();
        }

        return $class->manager;
    }

    /**
     * Load the cached state from the store.
     *
     * @param string $key a unique key for this cached entry
     * @return object|null On success, a cached opaque state,
     *      null if there was no usable cached state to return.
     */
    public function load($key) {
        if (!isset($this->cache[$key])) {
            return null;
        }

        return $this->cache[$key];
    }

    /**
     * Save or update the cached state
     *
     * @param string $key a unique key for this cached entry
     * @param object $state the opaque state to save
     */
    public function save($key, $state) {
        $this->cache[$key] = $state;
    }

    /**
     * Delete the cached state
     *
     * @param string $key a unique key of the cached entry to delete
     */
    public function delete($key) {
        unset($this->cache[$key]);
    }
}
