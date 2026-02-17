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
 * Form for quiz re-assignment.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Quiz re-assignment form class.
 */
class local_coursereassignment_quiz_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        global $DB;
        
        $mform = $this->_form;

        // Get all active users for autocomplete.
        // Select all user fields to avoid missing field warnings when calling fullname().
        $sql = "SELECT u.*
                FROM {user} u
                WHERE u.deleted = 0
                AND u.suspended = 0
                AND u.id NOT IN (1, 2)
                ORDER BY u.lastname, u.firstname";
        
        $users = $DB->get_records_sql($sql);
        
        // Build options array for user autocomplete.
        $useroptions = [];
        foreach ($users as $user) {
            $useroptions[$user->id] = fullname($user) . ' (' . $user->username . ') - ' . $user->email;
        }
        
        // User selection with autocomplete.
        $mform->addElement('autocomplete', 'userid', 
            get_string('selectuser', 'local_coursereassignment'), 
            $useroptions,
            ['noselectionstring' => get_string('searchuser', 'local_coursereassignment')]
        );
        $mform->addRule('userid', get_string('usernotselected', 'local_coursereassignment'), 'required', null, 'client');
        $mform->addHelpButton('userid', 'selectuser', 'local_coursereassignment');

        // Course selection (will be populated via AJAX based on user selection).
        $mform->addElement('autocomplete', 'courseid', 
            get_string('selectcourse', 'local_coursereassignment'), 
            [],
            ['noselectionstring' => get_string('searchcourse', 'local_coursereassignment')]
        );
        $mform->addRule('courseid', get_string('coursenotselected', 'local_coursereassignment'), 'required', null, 'client');
        $mform->addHelpButton('courseid', 'selectcourse', 'local_coursereassignment');
        $mform->disabledIf('courseid', 'userid', 'eq', '');

        // Quiz selection (will be populated via AJAX based on course selection).
        $mform->addElement('autocomplete', 'quizid', 
            get_string('selectquiz', 'local_coursereassignment'), 
            [],
            ['noselectionstring' => get_string('searchquiz', 'local_coursereassignment')]
        );
        $mform->addRule('quizid', get_string('quiznotselected', 'local_coursereassignment'), 'required', null, 'client');
        $mform->addHelpButton('quizid', 'selectquiz', 'local_coursereassignment');
        $mform->disabledIf('quizid', 'courseid', 'eq', '');

        // Action buttons.
        $this->add_action_buttons(true, get_string('reassignbutton', 'local_coursereassignment'));
    }

    /**
     * Validation.
     *
     * @param array $data Data to validate
     * @param array $files Files
     * @return array Errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty($data['userid'])) {
            $errors['userid'] = get_string('usernotselected', 'local_coursereassignment');
        }

        if (empty($data['courseid'])) {
            $errors['courseid'] = get_string('coursenotselected', 'local_coursereassignment');
        }

        if (empty($data['quizid'])) {
            $errors['quizid'] = get_string('quiznotselected', 'local_coursereassignment');
        }

        // Validate user enrollment.
        if (!empty($data['userid']) && !empty($data['courseid'])) {
            if (!local_coursereassignment_is_user_enrolled($data['userid'], $data['courseid'])) {
                $errors['courseid'] = get_string('usernotenrolled', 'local_coursereassignment');
            }
        }

        // Validate quiz belongs to course.
        if (!empty($data['quizid']) && !empty($data['courseid'])) {
            if (!local_coursereassignment_is_quiz_valid($data['quizid'], $data['courseid'])) {
                $errors['quizid'] = get_string('quiznotincourse', 'local_coursereassignment');
            }
        }

        return $errors;
    }
}
