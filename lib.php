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
 * Library functions for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot . '/grade/querylib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

/**
 * Validate if user is enrolled in a course.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return bool True if enrolled, false otherwise
 */
function local_coursereassignment_is_user_enrolled($userid, $courseid) {
    $context = context_course::instance($courseid);
    return is_enrolled($context, $userid, '', true);
}

/**
 * Validate if quiz belongs to a course.
 *
 * @param int $quizid Quiz ID
 * @param int $courseid Course ID
 * @return bool True if valid, false otherwise
 */
function local_coursereassignment_is_quiz_valid($quizid, $courseid) {
    global $DB;
    
    $quiz = $DB->get_record('quiz', ['id' => $quizid], 'course');
    if (!$quiz) {
        return false;
    }
    
    return $quiz->course == $courseid;
}

/**
 * Store historical data before course re-assignment.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @param int $reassignedby User ID who performed re-assignment
 * @return int|bool Insert ID or false on failure
 */
function local_coursereassignment_store_course_history($userid, $courseid, $reassignedby) {
    global $DB;
    
    try {
        $course = get_course($courseid);
        $completioninfo = new completion_info($course);
        $modinfo = get_fast_modinfo($course, $userid);

        // Gather course completion data.
        $completiondata = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $courseid
        ]);
        
        // Gather grade data.
        $gradedata = grade_get_course_grade($userid, $courseid);
        
        // Gather per-activity completion state (completed and not completed).
        $courseactivities = [];
        $quizdata = [];
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }

            $completionstate = null;
            $viewedstate = null;
            $iscompleted = null;
            if ($completioninfo->is_enabled($cm)) {
                $data = $completioninfo->get_data($cm, false, $userid);
                $completionstate = isset($data->completionstate) ? (int)$data->completionstate : null;
                $viewedstate = isset($data->viewed) ? (int)$data->viewed : null;
                $iscompleted = in_array($completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                    COMPLETION_COMPLETE_FAIL,
                    COMPLETION_COMPLETE_FAIL_HIDDEN,
                ], true);
            }

            $courseactivities[] = [
                'cmid' => $cm->id,
                'instanceid' => $cm->instance,
                'modname' => $cm->modname,
                'name' => format_string($cm->name),
                'completionenabled' => (bool)$completioninfo->is_enabled($cm),
                'completionstate' => $completionstate,
                'viewedstate' => $viewedstate,
                'iscompleted' => $iscompleted,
            ];

            if ($cm->modname !== 'quiz') {
                continue;
            }

            $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*');
            if (!$quiz) {
                continue;
            }
            $attempts = array_values(quiz_get_user_attempts($quiz->id, $userid, 'all', true));
            $bestgrade = quiz_get_best_grade($quiz, $userid);

            $gradebookgrade = null;
            $gradeinfo = grade_get_grades($courseid, 'mod', 'quiz', $quiz->id, $userid);
            if (!empty($gradeinfo->items[0]->grades[$userid])) {
                $gradebookgrade = $gradeinfo->items[0]->grades[$userid];
            }

            $quizdata[] = [
                'quizid' => $quiz->id,
                'name' => $quiz->name,
                'coursemoduleid' => $cm->id,
                'attempts' => $attempts,
                'bestgrade' => $bestgrade,
                'gradebookgrade' => $gradebookgrade,
                'completionstate' => $completionstate,
                'viewedstate' => $viewedstate,
            ];
        }
        
        $olddata = [
            'coursecompletion' => $completiondata,
            'coursegrade' => $gradedata,
            'activities' => array_values($courseactivities),
            'quizdata' => $quizdata,
            'timestamp' => time()
        ];
        
        $record = new stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->quizid = null;
        $record->reassignmenttype = 'course';
        $record->reassignedby = $reassignedby;
        $record->olddata = json_encode($olddata);
        $record->timecreated = time();
        
        $historyid = $DB->insert_record('local_coursereassignment_history', $record);

        return !empty($historyid) ? $historyid : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Store historical data before quiz re-assignment.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @param int $quizid Quiz ID
 * @param int $reassignedby User ID who performed re-assignment
 * @return int|bool Insert ID or false on failure
 */
function local_coursereassignment_store_quiz_history($userid, $courseid, $quizid, $reassignedby) {
    global $DB;
    
    try {
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

        // Gather quiz attempts.
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        
        // Gather quiz grades through core APIs.
        $bestgrade = quiz_get_best_grade($quiz, $userid);
        $quizgrades = [];
        if ($bestgrade !== false && $bestgrade !== null) {
            $quizgrades[] = (object) [
                'userid' => $userid,
                'quiz' => $quizid,
                'grade' => $bestgrade,
            ];
        }

        $gradegrades = [];
        $gradeinfo = grade_get_grades($courseid, 'mod', 'quiz', $quizid, $userid);
        if (!empty($gradeinfo->items[0]->grades[$userid])) {
            $gradegrades[] = $gradeinfo->items[0]->grades[$userid];
        }
        
        // Get quiz completion status.
        $cm = get_coursemodule_from_instance('quiz', $quizid, $courseid, false, MUST_EXIST);
        $completion = $DB->get_record('course_modules_completion', [
            'coursemoduleid' => $cm->id,
            'userid' => $userid
        ]);
        
        $olddata = [
            'attempts' => array_values($attempts),
            'quiz_grades' => array_values($quizgrades),
            'grade_grades' => array_values($gradegrades),
            'completion' => $completion,
            'timestamp' => time()
        ];
        
        $record = new stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->quizid = $quizid;
        $record->reassignmenttype = 'quiz';
        $record->reassignedby = $reassignedby;
        $record->olddata = json_encode($olddata);
        $record->timecreated = time();
        
        $historyid = $DB->insert_record('local_coursereassignment_history', $record);

        return !empty($historyid) ? $historyid : false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Reset course data for a user.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return bool True on success, false on failure
 */
function local_coursereassignment_reset_course($userid, $courseid) {
    global $DB;
    
    try {
        if (!local_coursereassignment_is_user_enrolled($userid, $courseid)) {
            return false;
        }

        $transaction = $DB->start_delegated_transaction();
        $course = get_course($courseid);
        $user = core_user::get_user($userid, '*', MUST_EXIST);

        // Reset all quiz attempts for quizzes in this course.
        $quizinstances = $DB->get_records('quiz', ['course' => $courseid], '', 'id');
        foreach ($quizinstances as $quizinstance) {
            if (!local_coursereassignment_reset_quiz($userid, $quizinstance->id, false)) {
                throw new moodle_exception('reassignfailed', 'local_coursereassignment');
            }
        }

        // Reset activity completion/viewed data for all modules in this course.
        $modinfo = get_fast_modinfo($course, $userid);
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->deletioninprogress) {
                continue;
            }
            \core_completion\privacy\provider::delete_completion($user, null, $cm->id);
        }

        // Reset course completion aggregates for this user.
        \core_completion\privacy\provider::delete_completion($user, $courseid);
        
        // Reset all gradebook data for the user in this course.
        grade_user_unenrol($courseid, $userid);

        // Purge once after a full course reset to avoid repeated purges per quiz.
        cache::make('core', 'completion')->purge();
        cache::make('core', 'coursecompletion')->purge();

        $transaction->allow_commit();
        return true;
    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Reset quiz data for a user.
 *
 * @param int $userid User ID
 * @param int $quizid Quiz ID
 * @param bool $purgecache Whether to purge completion caches after reset
 * @return bool True on success, false on failure
 */
function local_coursereassignment_reset_quiz($userid, $quizid, $purgecache = true) {
    global $DB;
    
    try {
        $transaction = $DB->start_delegated_transaction();

        // Get the quiz record.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        if (!local_coursereassignment_is_user_enrolled($userid, $quiz->course)) {
            throw new moodle_exception('usernotenrolled', 'local_coursereassignment');
        }
        $user = core_user::get_user($userid, '*', MUST_EXIST);
        
        // Get course module.
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $quiz->cmid = $cm->id;
        
        // Delete all attempts for this user for this quiz using the quiz core API.
        $quizsettings = \mod_quiz\quiz_settings::create($quizid);
        quiz_delete_user_attempts($quizsettings, $user);
        quiz_update_grades($quiz, $userid, true);
        
        // Delete user overrides through quiz core override manager.
        $manager = new \mod_quiz\local\override_manager(
            $quiz,
            context_module::instance($cm->id)
        );
        $alloverrides = $manager->get_all_overrides();
        $useroverrides = array_filter($alloverrides, function($override) use ($userid): bool {
            return !empty($override->userid) && (int)$override->userid === (int)$userid;
        });
        if (!empty($useroverrides)) {
            $manager->delete_overrides($useroverrides, false);
        }
        
        // Reset completion/viewed state for this quiz activity.
        \core_completion\privacy\provider::delete_completion($user, null, $cm->id);
        
        if ($purgecache) {
            // Keep this to avoid stale completion state in the same request after resets.
            cache::make('core', 'completion')->purge();
            cache::make('core', 'coursecompletion')->purge();
        }

        $transaction->allow_commit();
        return true;
    } catch (Exception $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Send core notification for course re-assignment.
 *
 * @param object $user User object
 * @param object $course Course object
 * @return bool True on success, false on failure
 */
function local_coursereassignment_send_course_notification($user, $course) {
    global $USER;

    $subject = get_config('local_coursereassignment', 'courseemailsubject');
    $message = get_config('local_coursereassignment', 'courseemailtemplate');

    if (empty($subject)) {
        $subject = get_string('defaultcoursesubject', 'local_coursereassignment');
    }
    if (empty($message)) {
        $message = get_string('defaultcoursemessage', 'local_coursereassignment');
    }

    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

    $placeholders = [
        '{firstname}' => $user->firstname,
        '{lastname}' => $user->lastname,
        '{username}' => $user->username,
        '{coursename}' => $course->fullname,
        '{courselink}' => $courseurl->out(false),
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);

    $receiver = core_user::get_user($user->id, '*', MUST_EXIST);
    $sender = core_user::get_user($USER->id, '*', MUST_EXIST);

    $notification = new \core\message\message();
    $notification->component = 'moodle';
    $notification->name = 'instantmessage';
    $notification->userfrom = $sender;
    $notification->userto = $receiver;
    $notification->subject = $subject;
    $notification->fullmessage = html_to_text($message);
    $notification->fullmessagehtml = $message;
    $notification->fullmessageformat = FORMAT_HTML;
    $notification->smallmessage = $subject;
    $notification->notification = 1;
    $notification->contexturl = $courseurl->out(false);
    $notification->contexturlname = format_string($course->fullname);

    return (bool)message_send($notification);
}

/**
 * Send core notification for quiz re-assignment.
 *
 * @param object $user User object
 * @param object $course Course object
 * @param object $quiz Quiz object
 * @return bool True on success, false on failure
 */
function local_coursereassignment_send_quiz_notification($user, $course, $quiz) {
    global $USER;

    $subject = get_config('local_coursereassignment', 'quizemailsubject');
    $message = get_config('local_coursereassignment', 'quizemailtemplate');

    if (empty($subject)) {
        $subject = get_string('defaultquizsubject', 'local_coursereassignment');
    }
    if (empty($message)) {
        $message = get_string('defaultquizmessage', 'local_coursereassignment');
    }

    $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $quiz->coursemodule]);

    $placeholders = [
        '{firstname}' => $user->firstname,
        '{lastname}' => $user->lastname,
        '{username}' => $user->username,
        '{coursename}' => $course->fullname,
        '{quizname}' => $quiz->name,
        '{quizlink}' => $quizurl->out(false),
    ];

    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);

    $receiver = core_user::get_user($user->id, '*', MUST_EXIST);
    $sender = core_user::get_user($USER->id, '*', MUST_EXIST);

    $notification = new \core\message\message();
    $notification->component = 'moodle';
    $notification->name = 'instantmessage';
    $notification->userfrom = $sender;
    $notification->userto = $receiver;
    $notification->subject = $subject;
    $notification->fullmessage = html_to_text($message);
    $notification->fullmessagehtml = $message;
    $notification->fullmessageformat = FORMAT_HTML;
    $notification->smallmessage = $subject;
    $notification->notification = 1;
    $notification->contexturl = $quizurl->out(false);
    $notification->contexturlname = format_string($quiz->name);

    return (bool)message_send($notification);
}
