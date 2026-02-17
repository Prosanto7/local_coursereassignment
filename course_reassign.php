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
 * Course re-assignment page.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/course_reassign_form.php');

//admin_externalpage_setup('local_coursereassignment_course');
require_login();

$context = context_system::instance();
require_capability('local/coursereassignment:managecourse', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/coursereassignment/course_reassign.php'));
$PAGE->set_title(get_string('coursereassign', 'local_coursereassignment'));
$PAGE->set_heading(get_string('coursereassign', 'local_coursereassignment'));
$PAGE->set_pagelayout('admin');

// Initialize form.
$mform = new local_coursereassignment_course_form();

// Handle form cancellation.
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/coursereassignment/course_reassign.php'));
} else if ($data = $mform->get_data()) {
    $userid = $data->userid;
    $courseid = $data->courseid;

    var_dump($data); // Debugging: dump form data.
    die();
        
    // Store historical data.
    $historyid = local_coursereassignment_store_course_history($userid, $courseid, $USER->id);
        
    if ($historyid) {
        // Reset course data.
        $success = local_coursereassignment_reset_course($userid, $courseid);
                
        if ($success) {
            // Send email notification.
            $user = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
            $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
            
            $emailsent = local_coursereassignment_send_course_email($user, $course);
            
            $message = get_string('reassignsuccess', 'local_coursereassignment');
            if ($emailsent) {
                $message .= ' ' . get_string('emailsentsuccess', 'local_coursereassignment');
            }
            
            redirect(
                new moodle_url('/local/coursereassignment/course_reassign.php'),
                $message,
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else {
            redirect(
                new moodle_url('/local/coursereassignment/course_reassign.php'),
                get_string('reassignfailed', 'local_coursereassignment'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
        }
    } else {
        redirect(
            new moodle_url('/local/coursereassignment/course_reassign.php'),
            get_string('reassignfailed', 'local_coursereassignment'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

// Include JavaScript for dynamic course loading.
$PAGE->requires->js_call_amd('local_coursereassignment/coursereassign', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursereassign', 'local_coursereassignment'));

// Display the form.
$mform->display();

echo $OUTPUT->footer();
