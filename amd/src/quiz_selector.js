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
 * Quiz selector autocomplete transport.
 *
 * @module     local_coursereassignment/quiz_selector
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

export async function transport(selector, query, callback, failure) {
    const userid = document.querySelector('[name="userid"]').value;
    const courseid = document.querySelector('[name="courseid"]').value;

    if (!userid || !courseid) {
        callback([]);
        return;
    }

    const request = {
        methodname: 'local_coursereassignment_get_course_quizzes',
        args: {
            courseid: courseid,
            userid: userid
        }
    };

    try {
        const response = await Ajax.call([request])[0];
        callback(response);
    } catch (e) {
        failure(e);
    }
}

export function processResults(selector, results) {
    if (!Array.isArray(results)) {
        return [];
    }

    return results.map((quiz) => ({
        value: quiz.id,
        label: quiz.name
    }));
}
