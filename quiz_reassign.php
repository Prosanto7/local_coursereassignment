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
 * Quiz re-assignment page.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/quiz_reassign_form.php');

require_login();

$context = context_system::instance();
require_capability('local/coursereassignment:managequiz', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursereassignment/quiz_reassign.php'));
$PAGE->set_title(get_string('quizreassign', 'local_coursereassignment'));
$PAGE->set_heading(get_string('quizreassign', 'local_coursereassignment'));
$PAGE->set_pagelayout('admin');

// Initialize form.
$mform = new local_coursereassignment_quiz_form();

// Handle form cancellation.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/coursereassignment/quiz_reassign.php'));
} else if ($data = $mform->get_data()) {
    $userid = $data->userid;
    $courseid = $data->courseid;
    $quizid = $data->quizid;

    // Store historical data.
    $historyid = local_coursereassignment_store_quiz_history($userid, $courseid, $quizid, $USER->id);

    if ($historyid) {
        // Reset quiz data.
        $success = local_coursereassignment_reset_quiz($userid, $quizid);

        if ($success) {
            // Send notifications.
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
            $cm = get_coursemodule_from_instance('quiz', $quizid, $courseid, false, MUST_EXIST);
            $quiz->coursemodule = $cm->id;
            
            $emailsent = local_coursereassignment_send_quiz_email($user, $course, $quiz);
            $notificationsent = local_coursereassignment_send_quiz_notification($user, $course, $quiz);
            
            $message = get_string('reassignsuccess', 'local_coursereassignment');
            
            redirect(
                new moodle_url('/local/coursereassignment/quiz_reassign.php'),
                $message,
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            $DB->delete_records('local_coursereassignment_history', ['id' => $historyid]);
            redirect(
                new moodle_url('/local/coursereassignment/quiz_reassign.php'),
                get_string('reassignfailed', 'local_coursereassignment'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    } else {
        redirect(
            new moodle_url('/local/coursereassignment/quiz_reassign.php'),
            get_string('reassignfailed', 'local_coursereassignment'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('quizreassign', 'local_coursereassignment'));

// Display the form.
$mform->display();

echo $OUTPUT->footer();
