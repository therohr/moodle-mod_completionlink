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
 * AJAX datasource for the "Target course" autocomplete field on the Course completion link
 * activity settings form. Replaces the old approach of preloading every course on
 * the site into the <select> (which silently truncated at 500 courses); this module
 * searches on the server as the teacher types instead.
 *
 * @module     mod_completionlink/form_completionlink_selector
 * @copyright  2026 David Rohr (tidewatercreative.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';

/**
 * Read the host course id off the originating <select>'s data-courseid attribute.
 *
 * mod_form.php renders this via the autocomplete element's attributes array so the
 * server-side search (classes/external/search_courses.php) can exclude the host
 * course and check capabilities in the right context.
 *
 * @param {String} selector CSS selector for the autocomplete's originating select element.
 * @returns {Number} The host course id, or 0 if it could not be determined.
 */
const getCourseId = (selector) => {
    const element = document.querySelector(selector);
    const courseid = element ? parseInt(element.dataset.courseid, 10) : 0;

    return Number.isNaN(courseid) ? 0 : courseid;
};

/**
 * Fetch matching courses from the server for the text the user has typed so far.
 *
 * @param {String} selector CSS selector for the autocomplete's originating select element.
 * @param {String} query Text typed by the user so far.
 * @param {Function} success Callback to invoke with the raw results on success.
 * @param {Function} failure Callback to invoke on error.
 */
export const transport = (selector, query, success, failure) => {
    Ajax.call([{
        methodname: 'mod_completionlink_search_courses',
        args: {
            query,
            courseid: getCourseId(selector),
        },
    }])[0]
        .then(success)
        .catch(failure || Notification.exception);
};

/**
 * Convert the raw results from {@link transport} into the {value, label} shape
 * the autocomplete element expects.
 *
 * @param {String} selector CSS selector for the autocomplete's originating select element.
 * @param {Array} results Raw results returned by the external function.
 * @returns {Array} Processed results ready for the autocomplete dropdown.
 */
export const processResults = (selector, results) => results.map((course) => ({
    value: course.id,
    label: course.label,
}));

export default {
    transport,
    processResults,
};
