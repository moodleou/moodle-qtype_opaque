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
 * Javascript change opaque question focus.
 *
 * @package   mod_quiz
 * @copyright 2017 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     3.2
 */
define(['jquery'], function($) {
    /**
     * @alias module:mod_quiz/attempt
     */
    var t = {
        init : function() {
            $( document ).ready(function() {
                var checkButton = $('input[id*="_omact_gen_"]');
                var opaqueTryAgainButton = $('input[id*="omact_ok"]');

                var lastindex = localStorage.getItem('last_question_index');
                localStorage.removeItem('last_question_index');
                /* Check if last question is set */
                if (lastindex !== null) {
                    // Because we got timeout inside inline script so we need to wait for that timeout.
                    setTimeout(function () {
                        // Scroll to last checked question.
                        $('html, body').animate({
                            scrollTop: $('#q' + lastindex).offset().top
                        }, 100);

                        // Focus on "Try again" button if exist otherwise blur all other button.
                        var focusTryAgainButton = $('input[id*=' + '":' + lastindex + '_omact_ok"' + ']');
                        if ($(focusTryAgainButton).length > 0) {
                            $(focusTryAgainButton).focus();
                        } else {
                            $('input[id*="_omact_ok"]').blur();
                        }
                    }, 350);
                }

                opaqueTryAgainButton.on('keydown', function (e) {
                    var keyCode = e.keyCode || e.which;
                    e.preventDefault();
                    // Tab on Try again button.
                    if (keyCode == 9 && !e.shiftKey) {
                        var slot = t.getQuestionIndex(e);
                        $('#q' + slot + ' input.questionflagimage').focus();
                        $(this).attr('tabindex', -1);
                    }
                });

                // Check button clicked.
                $(checkButton).click(function (e) {
                    localStorage.setItem('last_question_index', t.getQuestionIndex(e));
                });

                // Try again button clicked.
                $(opaqueTryAgainButton).click(function (e) {
                    localStorage.setItem('last_question_index', t.getQuestionIndex(e));
                });
            });
        },
        getQuestionIndex: function (e) {
            // Save last position to scroll back to that question when the page is re-rendered.
            var str = e.target.id;
            var questionindex = str.substr(str.indexOf(':') + 1);
            questionindex = questionindex.substr(0, questionindex.indexOf('_'));
            return questionindex;
        }
    };
    return t;
});
