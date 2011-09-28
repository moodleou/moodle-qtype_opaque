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
 * Defines functions that are used to apply historic hacks
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2006 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

function qtype_opaque_hacks_filter_xhtml($xhtml, $opaquestate) {
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
    if ($opaquestate->resultssequencenumber >= 0 || $opaquestate->questionended)
        $xhtml = qtype_opaque_hacks_strip_omact_buttons($xhtml);

    // TODO uncomment these lines when behaviour is updated
    //$browserclass = qtype_opaque_hacks_browser_type();
    //$xhtml = "<div class=\"$browserclass\">$xhtml</div>";

    return $xhtml;
}

/**
 * OpenMark relies on certain browser-specific class names to be present in the
 * HTML outside the question, in order to apply certian browser-specific layout
 * work-arounds. This function re-implements Om's browser sniffing rules. See
 * http://java.net/projects/openmark/sources/svn/content/trunk/src/util/misc/UserAgent.java
 * @return string class to add to the HTML.
 */
function qtype_opaque_hacks_browser_type() {
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

function qtype_opaque_hacks_filter_response(&$response) {
    // Process the resources.
    // TODO remove this. Evil hack. IE cannot cope with : and other odd characters
    // in the name argument to window.open. Until we can deploy a fix to the
    // OpenMark servers, apply the fix to the JS code here.
    if(isset($response->resources)) {
        foreach ($response->resources as $key => $resource) {
            if ($resource->filename == 'script.js') {
                $response->resources[$key]->content = preg_replace(
                        '/(?<=' . preg_quote('window.open("", idprefix') . '|' .
                                preg_quote('window.open("",idprefix') . ')\+(?=\"\w+\"\+id,)/',
                        '.replace(/\W/g,"_")+', $resource->content);
            }
        }
    }

    // Another nasty hack pending a permanent fix to OpenMark.
    if(!empty($response->progressInfo)) {
        $response->progressInfo = str_replace(
                array('attempts', 'attempt'),
                array('tries', 'try'),
                $response->progressInfo);
    }
}

/**
 * Wrapper round $step->get_submitted_data() to work around an incompatibility
 * between OpenMark and the Moodle question engine.
 * @param question_attempt_step $step a step.
 * @return array approximately $step->get_submitted_data().
 */
function qtype_opaque_hacks_get_submitted_data(question_attempt_step $step) {
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
 * Strip any buttons, followed by script tags, where the button has an id
 * containing _omact_, and is not disabled.
 */
function qtype_opaque_hacks_strip_omact_buttons($xhtml) {
    $xhtml = preg_replace(
            '|<input(?:(?!disabled=)[^>])*? id="[^"]*_omact_[^"]*"(?:(?!disabled=)[^>])*?>' .
            '<script type="text/javascript">[^<]*</script>|', '', $xhtml);

    return $xhtml;
}
?>
