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
 * External API for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/externallib.php');

/**
 * External functions for course reassignment.
 */
class local_coursereassignment_external extends external_api {

    /**
     * Returns description of search_users parameters.
     *
     * @return external_function_parameters
     */
    public static function search_users_parameters() {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query'),
        ]);
    }

    /**
     * Search users by username or email.
     *
     * @param string $query Search query
     * @return array List of users
     */
    public static function search_users($query) {
        global $DB;

        $params = self::validate_parameters(self::search_users_parameters(), [
            'query' => $query,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursereassignment:managecourse', $context);

        $query = $params['query'];
        if (strlen($query) < 2) {
            return [];
        }

        $sql = "SELECT id, username, firstname, lastname, email
                  FROM {user}
                 WHERE deleted = 0
                   AND suspended = 0
                   AND (username LIKE :username
                        OR email LIKE :email
                        OR firstname LIKE :firstname
                        OR lastname LIKE :lastname
                        OR " . $DB->sql_fullname() . " LIKE :fullname)
              ORDER BY firstname, lastname
                 LIMIT 50";

        $searchparam = '%' . $DB->sql_like_escape($query) . '%';
        $users = $DB->get_records_sql($sql, [
            'username' => $searchparam,
            'email' => $searchparam,
            'firstname' => $searchparam,
            'lastname' => $searchparam,
            'fullname' => $searchparam,
        ]);

        $result = [];
        foreach ($users as $user) {
            $result[] = [
                'id' => $user->id,
                'username' => $user->username,
                'fullname' => fullname($user),
                'email' => $user->email,
            ];
        }

        return $result;
    }

    /**
     * Returns description of search_users return value.
     *
     * @return external_multiple_structure
     */
    public static function search_users_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User ID'),
                'username' => new external_value(PARAM_TEXT, 'Username'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'email' => new external_value(PARAM_TEXT, 'Email'),
            ])
        );
    }

    /**
     * Returns description of get_user_courses parameters.
     *
     * @return external_function_parameters
     */
    public static function get_user_courses_parameters() {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    /**
     * Get courses where user is enrolled.
     *
     * @param int $userid User ID
     * @return array List of courses
     */
    public static function get_user_courses($userid) {
        global $DB;

        $params = self::validate_parameters(self::get_user_courses_parameters(), [
            'userid' => $userid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursereassignment:managecourse', $context);

        $userid = $params['userid'];
        
        // Get all courses where user is enrolled.
        $courses = enrol_get_users_courses($userid, true, 'id,fullname,shortname');

        $result = [];
        foreach ($courses as $course) {
            // Skip site course.
            if ($course->id == SITEID) {
                continue;
            }
            
            $result[] = [
                'id' => $course->id,
                'fullname' => $course->fullname,
                'shortname' => $course->shortname,
            ];
        }

        return $result;
    }

    /**
     * Returns description of get_user_courses return value.
     *
     * @return external_multiple_structure
     */
    public static function get_user_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Course ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'shortname' => new external_value(PARAM_TEXT, 'Short name'),
            ])
        );
    }

    /**
     * Returns description of get_course_quizzes parameters.
     *
     * @return external_function_parameters
     */
    public static function get_course_quizzes_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
        ]);
    }

    /**
     * Get quizzes in a course.
     *
     * @param int $courseid Course ID
     * @param int $userid User ID
     * @return array List of quizzes
     */
    public static function get_course_quizzes($courseid, $userid) {
        $params = self::validate_parameters(self::get_course_quizzes_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);

        $context = context_system::instance();
        self::validate_context($context);
        require_capability('local/coursereassignment:managequiz', $context);

        $courseid = $params['courseid'];
        $userid = $params['userid'];

        // Verify user is enrolled in course.
        if (!local_coursereassignment_is_user_enrolled($userid, $courseid)) {
            return [];
        }

        $course = get_course($courseid);
        $modinfo = get_fast_modinfo($course, $userid);

        $result = [];
        if (empty($modinfo->instances['quiz'])) {
            return $result;
        }

        foreach ($modinfo->instances['quiz'] as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            $result[] = [
                'id' => (int)$cm->instance,
                'name' => format_string($cm->name),
                'coursemodule' => (int)$cm->id,
            ];
        }

        usort($result, function(array $a, array $b): int {
            return strcmp(core_text::strtolower($a['name']), core_text::strtolower($b['name']));
        });

        return $result;
    }

    /**
     * Returns description of get_course_quizzes return value.
     *
     * @return external_multiple_structure
     */
    public static function get_course_quizzes_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Quiz ID'),
                'name' => new external_value(PARAM_TEXT, 'Quiz name'),
                'coursemodule' => new external_value(PARAM_INT, 'Course module ID'),
            ])
        );
    }
}
