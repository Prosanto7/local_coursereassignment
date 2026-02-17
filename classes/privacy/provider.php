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
 * Privacy provider for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursereassignment\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider class.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Get the language string identifier with the component's language
     * file to explain why this plugin stores no data.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_coursereassignment_history',
            [
                'userid' => 'privacy:metadata:local_coursereassignment_history:userid',
                'reassignedby' => 'privacy:metadata:local_coursereassignment_history:reassignedby',
                'courseid' => 'privacy:metadata:local_coursereassignment_history:courseid',
                'quizid' => 'privacy:metadata:local_coursereassignment_history:quizid',
                'timecreated' => 'privacy:metadata:local_coursereassignment_history:timecreated',
                'olddata' => 'privacy:metadata:local_coursereassignment_history:olddata',
            ],
            'privacy:metadata:local_coursereassignment_history'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // The data is stored at system context level.
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_coursereassignment_history} h ON h.userid = :userid OR h.reassignedby = :userid2
                 WHERE ctx.contextlevel = :contextlevel";

        $params = [
            'userid' => $userid,
            'userid2' => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid
                  FROM {local_coursereassignment_history}";

        $userlist->add_from_sql('userid', $sql, []);

        $sql = "SELECT reassignedby
                  FROM {local_coursereassignment_history}";

        $userlist->add_from_sql('reassignedby', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            // Export data where user was reassigned.
            $sql = "SELECT h.*, c.fullname as coursename, q.name as quizname
                      FROM {local_coursereassignment_history} h
                      JOIN {course} c ON c.id = h.courseid
                 LEFT JOIN {quiz} q ON q.id = h.quizid
                     WHERE h.userid = :userid
                  ORDER BY h.timecreated DESC";

            $records = $DB->get_records_sql($sql, ['userid' => $user->id]);

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = (object) [
                        'type' => $record->reassignmenttype,
                        'course' => $record->coursename,
                        'quiz' => $record->quizname,
                        'reassignedby' => $record->reassignedby,
                        'date' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_coursereassignment'), 'reassignments'],
                    (object) $data
                );
            }

            // Export data where user performed reassignment.
            $sql = "SELECT h.*, c.fullname as coursename, q.name as quizname, u.firstname, u.lastname
                      FROM {local_coursereassignment_history} h
                      JOIN {course} c ON c.id = h.courseid
                 LEFT JOIN {quiz} q ON q.id = h.quizid
                      JOIN {user} u ON u.id = h.userid
                     WHERE h.reassignedby = :userid
                  ORDER BY h.timecreated DESC";

            $records = $DB->get_records_sql($sql, ['userid' => $user->id]);

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = (object) [
                        'type' => $record->reassignmenttype,
                        'course' => $record->coursename,
                        'quiz' => $record->quizname,
                        'user' => fullname($record),
                        'date' => \core_privacy\local\request\transform::datetime($record->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_coursereassignment'), 'performed_reassignments'],
                    (object) $data
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('local_coursereassignment_history');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            $DB->delete_records('local_coursereassignment_history', ['userid' => $user->id]);
            $DB->delete_records('local_coursereassignment_history', ['reassignedby' => $user->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        foreach ($userids as $userid) {
            $DB->delete_records('local_coursereassignment_history', ['userid' => $userid]);
            $DB->delete_records('local_coursereassignment_history', ['reassignedby' => $userid]);
        }
    }
}
