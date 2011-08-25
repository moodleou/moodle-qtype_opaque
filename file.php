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
 * Serves files from the Opaque resource cache.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2007 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');

$path = get_file_argument();
list($engineid, $remoteid, $remoteversion, $filename) = explode('/', trim($path, '/'), 4);

// The Open University found it necessary to comment out the whole of the following if statement
// to make things work reliably. However, I think that was only problems with synchronising
// the session between our load-balanced servers, and I think it is better to leave
// this code in. (OU bug 7991.)

// Actually, this seems to be unrelated to load-balancers, and it is happening
// again. There is some horrible race-condition here that I can't work out, so
// I think the only option is to do without this bit of security.

/*
global $SESSION;
if ($SESSION->cached_opaque_state->engineid != $engineid ||
        $SESSION->cached_opaque_state->remoteid != $remoteid ||
        $SESSION->cached_opaque_state->remoteversion != $remoteversion) {
    print_error('cannotaccessfile');
}
*/

$resourcecache = new qtype_opaque_resource_cache($engineid, $remoteid, $remoteversion);
$resourcecache->serve_file($filename);
