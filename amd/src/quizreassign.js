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
 * JavaScript for quiz re-assignment page.
 *
 * @module     local_coursereassignment/quizreassign
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {

    let selectedUserId = null;

    /**
     * Initialize the quiz reassignment page.
     */
    const init = function() {
        // Watch for changes to the user select.
        $('#id_userid').on('change', function() {
            const userId = $(this).val();
            selectedUserId = userId;
            if (userId) {
                loadUserCourses(userId);
            } else {
                resetCourseSelect();
                resetQuizSelect();
            }
        });

        // Watch for changes to the course select.
        $('#id_courseid').on('change', function() {
            const courseId = $(this).val();
            if (courseId && selectedUserId) {
                loadCourseQuizzes(courseId, selectedUserId);
            } else {
                resetQuizSelect();
            }
        });
    };

    /**
     * Load courses for selected user.
     *
     * @param {int} userId User ID
     */
    const loadUserCourses = function(userId) {
        const courseSelect = $('#id_courseid');
        
        // Disable and show loading state.
        courseSelect.prop('disabled', true);
        resetQuizSelect();
        
        Ajax.call([{
            methodname: 'local_coursereassignment_get_user_courses',
            args: {userid: parseInt(userId)},
        }])[0].done(function(courses) {
            // Clear existing options.
            courseSelect.empty();
            
            // Add default option.
            courseSelect.append($('<option>', {
                value: '',
                text: M.util.get_string('searchcourse', 'local_coursereassignment')
            }));
            
            // Add course options.
            courses.forEach(function(course) {
                courseSelect.append($('<option>', {
                    value: course.id,
                    text: course.fullname + ' (' + course.shortname + ')'
                }));
            });
            
            // Re-enable the select.
            courseSelect.prop('disabled', false);
            
        }).fail(function(error) {
            Notification.exception(error);
            courseSelect.prop('disabled', false);
        });
    };

    /**
     * Load quizzes for selected course.
     *
     * @param {int} courseId Course ID
     * @param {int} userId User ID
     */
    const loadCourseQuizzes = function(courseId, userId) {
        const quizSelect = $('#id_quizid');
        
        // Disable and show loading state.
        quizSelect.prop('disabled', true);
        
        Ajax.call([{
            methodname: 'local_coursereassignment_get_course_quizzes',
            args: {
                courseid: parseInt(courseId),
                userid: parseInt(userId)
            },
        }])[0].done(function(quizzes) {
            // Clear existing options.
            quizSelect.empty();
            
            // Add default option.
            quizSelect.append($('<option>', {
                value: '',
                text: M.util.get_string('searchquiz', 'local_coursereassignment')
            }));
            
            // Add quiz options.
            quizzes.forEach(function(quiz) {
                quizSelect.append($('<option>', {
                    value: quiz.id,
                    text: quiz.name
                }));
            });
            
            // Re-enable the select.
            quizSelect.prop('disabled', false);
            
        }).fail(function(error) {
            Notification.exception(error);
            quizSelect.prop('disabled', false);
        });
    };

    /**
     * Reset course select.
     */
    const resetCourseSelect = function() {
        const courseSelect = $('#id_courseid');
        courseSelect.empty();
        courseSelect.append($('<option>', {
            value: '',
            text: M.util.get_string('searchcourse', 'local_coursereassignment')
        }));
        courseSelect.prop('disabled', true);
    };

    /**
     * Reset quiz select.
     */
    const resetQuizSelect = function() {
        const quizSelect = $('#id_quizid');
        quizSelect.empty();
        quizSelect.append($('<option>', {
            value: '',
            text: M.util.get_string('searchquiz', 'local_coursereassignment')
        }));
        quizSelect.prop('disabled', true);
    };

    return {
        init: init
    };
});
