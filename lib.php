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

/**
 * Validate if user is enrolled in a course.
 *
 * @param int $userid User ID
 * @param int $courseid Course ID
 * @return bool True if enrolled, false otherwise
 */
function local_coursereassignment_is_user_enrolled($userid, $courseid) {
    global $DB;
    
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
    global $CFG, $DB;
    
    try {
        require_once($CFG->libdir . '/gradelib.php');

        // Gather course completion data.
        $completiondata = $DB->get_record('course_completions', [
            'userid' => $userid,
            'course' => $courseid
        ]);
        
        // Gather grade data.
        $gradedata = grade_get_course_grade($userid, $courseid);
        
        // Gather activity completion data using JOIN to filter by course.
        $sql = "SELECT cmc.*
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid AND cmc.userid = :userid";
        $courseactivities = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        
        $olddata = [
            'completion' => $completiondata,
            'grade' => $gradedata,
            'activities' => array_values($courseactivities),
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
        
        if (!$historyid) {
            debugging('Failed to insert history record for user ' . $userid . ' in course ' . $courseid, DEBUG_DEVELOPER);
            return false;
        }
        
        return $historyid;
    } catch (Exception $e) {
        debugging('Error storing course history: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
        // Gather quiz attempts.
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        
        // Gather quiz grades.
        $quizgrades = $DB->get_records('quiz_grades', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        
        // Gather grade item data.
        $gradeitem = $DB->get_record('grade_items', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid
        ]);
        
        $gradegrades = [];
        if ($gradeitem) {
            $gradegrades = $DB->get_records('grade_grades', [
                'itemid' => $gradeitem->id,
                'userid' => $userid
            ]);
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
        
        if (!$historyid) {
            debugging('Failed to insert quiz history record for user ' . $userid . ' quiz ' . $quizid, DEBUG_DEVELOPER);
            return false;
        }
        
        return $historyid;
    } catch (Exception $e) {
        debugging('Error storing quiz history: ' . $e->getMessage(), DEBUG_DEVELOPER);
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
    global $CFG, $DB;
    require_once($CFG->dirroot . '/course/lib.php');
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/completionlib.php');
    
    try {
        $course = get_course($courseid);
        
        // Reset activity completions for this specific user in this course.
        $sql = "SELECT cmc.id
                  FROM {course_modules_completion} cmc
                  JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                 WHERE cm.course = :courseid AND cmc.userid = :userid";
        $completions = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid]);
        
        $deletedcount = 0;
        if ($completions) {
            $completionids = array_keys($completions);
            list($insql, $params) = $DB->get_in_or_equal($completionids);
            $DB->delete_records_select('course_modules_completion', "id $insql", $params);
            $deletedcount = count($completionids);
            debugging('Deleted ' . $deletedcount . ' activity completion records for user ' . $userid . ' in course ' . $courseid, DEBUG_DEVELOPER);
        } else {
            debugging('No activity completions found for user ' . $userid . ' in course ' . $courseid, DEBUG_DEVELOPER);
        }
        
        // Also delete course_modules_viewed records for this user in this course.
        $sql = "DELETE FROM {course_modules_viewed}
                WHERE coursemoduleid IN (
                    SELECT id FROM {course_modules} WHERE course = :courseid
                ) AND userid = :userid";
        $DB->execute($sql, ['courseid' => $courseid, 'userid' => $userid]);
        
        // Reset course completion for this user.
        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            // Delete course completion records.
            $deleted = $DB->delete_records('course_completions', [
                'userid' => $userid,
                'course' => $courseid
            ]);
            debugging('Deleted course_completions: ' . ($deleted ? 'yes' : 'no'), DEBUG_DEVELOPER);
            
            $deleted = $DB->delete_records('course_completion_crit_compl', [
                'userid' => $userid,
                'course' => $courseid
            ]);
            debugging('Deleted course_completion_crit_compl: ' . ($deleted ? 'yes' : 'no'), DEBUG_DEVELOPER);
        }
        
        // Reset grades using core grade functions.
        // Get all grade items for this course.
        $gradeitems = grade_item::fetch_all(['courseid' => $courseid]);
        if ($gradeitems) {
            foreach ($gradeitems as $gradeitem) {
                // Use delete_grade() which properly handles all related data.
                $result = $gradeitem->delete_grade($userid);
                debugging('Deleted grade for item ' . $gradeitem->id . ': ' . ($result ? 'success' : 'failed'), DEBUG_DEVELOPER);
            }
        }
        
        // Clear the completion cache for this user.
        cache::make('core', 'completion')->purge();
        cache::make('core', 'coursecompletion')->purge();
        
        debugging('Successfully reset course ' . $courseid . ' for user ' . $userid, DEBUG_DEVELOPER);
        return true;
    } catch (Exception $e) {
        debugging('Error resetting course: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Reset quiz data for a user.
 *
 * @param int $userid User ID
 * @param int $quizid Quiz ID
 * @return bool True on success, false on failure
 */
function local_coursereassignment_reset_quiz($userid, $quizid) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    require_once($CFG->libdir . '/gradelib.php');
    require_once($CFG->libdir . '/completionlib.php');
    
    try {
        // Get the quiz record.
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        
        // Get course module.
        $cm = get_coursemodule_from_instance('quiz', $quizid, $quiz->course, false, MUST_EXIST);
        $quiz->cmid = $cm->id;
        
        // Get all quiz attempts for this user.
        $attempts = $DB->get_records('quiz_attempts', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        
        debugging('Found ' . count($attempts) . ' attempts for user ' . $userid . ' in quiz ' . $quizid, DEBUG_DEVELOPER);
        
        // Delete each attempt using core function.
        // quiz_delete_attempt handles:
        // - Deleting question usage
        // - Deleting the attempt record
        // - Deleting/recalculating quiz_grades
        // - Updating gradebook via quiz_update_grades
        foreach ($attempts as $attempt) {
            quiz_delete_attempt($attempt, $quiz);
            debugging('Deleted attempt ' . $attempt->id, DEBUG_DEVELOPER);
        }
        
        // Delete quiz overrides for this user.
        $deleted = $DB->delete_records('quiz_overrides', [
            'quiz' => $quizid,
            'userid' => $userid
        ]);
        debugging('Deleted quiz overrides: ' . ($deleted ? 'yes' : 'no'), DEBUG_DEVELOPER);
        
        // Reset activity completion for this quiz.
        $deleted = $DB->delete_records('course_modules_completion', [
            'coursemoduleid' => $cm->id,
            'userid' => $userid
        ]);
        debugging('Deleted quiz completion: ' . ($deleted ? 'yes' : 'no'), DEBUG_DEVELOPER);
        
        // Clear the completion cache.
        cache::make('core', 'completion')->purge();
        
        debugging('Successfully reset quiz ' . $quizid . ' for user ' . $userid, DEBUG_DEVELOPER);
        return true;
    } catch (Exception $e) {
        debugging('Error resetting quiz: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return false;
    }
}

/**
 * Send email notification for course re-assignment.
 *
 * @param object $user User object
 * @param object $course Course object
 * @return bool True on success, false on failure
 */
function local_coursereassignment_send_course_email($user, $course) {
    global $CFG;
    
    $subject = get_config('local_coursereassignment', 'courseemailsubject');
    $message = get_config('local_coursereassignment', 'courseemailtemplate');
    
    if (empty($subject)) {
        $subject = get_string('defaultcoursesubject', 'local_coursereassignment');
    }
    if (empty($message)) {
        $message = get_string('defaultcoursemessage', 'local_coursereassignment');
    }
    
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    $courselink = html_writer::link($courseurl, $course->fullname);
    
    $placeholders = [
        '{firstname}' => $user->firstname,
        '{lastname}' => $user->lastname,
        '{username}' => $user->username,
        '{coursename}' => $course->fullname,
        '{courselink}' => $courseurl->out(false)
    ];
    
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);
    
    $from = core_user::get_support_user();
    
    return email_to_user($user, $from, $subject, html_to_text($message), $message);
}

/**
 * Send email notification for quiz re-assignment.
 *
 * @param object $user User object
 * @param object $course Course object
 * @param object $quiz Quiz object
 * @return bool True on success, false on failure
 */
function local_coursereassignment_send_quiz_email($user, $course, $quiz) {
    global $CFG;
    
    $subject = get_config('local_coursereassignment', 'quizemailsubject');
    $message = get_config('local_coursereassignment', 'quizemailtemplate');
    
    if (empty($subject)) {
        $subject = get_string('defaultquizsubject', 'local_coursereassignment');
    }
    if (empty($message)) {
        $message = get_string('defaultquizmessage', 'local_coursereassignment');
    }
    
    $quizurl = new moodle_url('/mod/quiz/view.php', ['id' => $quiz->coursemodule]);
    $quizlink = html_writer::link($quizurl, $quiz->name);
    
    $placeholders = [
        '{firstname}' => $user->firstname,
        '{lastname}' => $user->lastname,
        '{username}' => $user->username,
        '{coursename}' => $course->fullname,
        '{quizname}' => $quiz->name,
        '{quizlink}' => $quizurl->out(false)
    ];
    
    $subject = str_replace(array_keys($placeholders), array_values($placeholders), $subject);
    $message = str_replace(array_keys($placeholders), array_values($placeholders), $message);
    
    $from = core_user::get_support_user();
    
    return email_to_user($user, $from, $subject, html_to_text($message), $message);
}
