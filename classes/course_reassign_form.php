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
 * Form for course re-assignment.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Course re-assignment form class.
 */
class local_coursereassignment_course_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform = $this->_form;
        
        // User selection with autocomplete.
        $mform->addElement('autocomplete', 'userid', 
            get_string('selectuser', 'local_coursereassignment'), 
            [], 
            [
                'ajax' => 'core_user/form_user_selector',
                'multiple' => false,
            ]
        );
        $mform->addRule('userid', get_string('usernotselected', 'local_coursereassignment'), 'required', null, 'client');
        $mform->addHelpButton('userid', 'selectuser', 'local_coursereassignment');

        // Course selection (will be populated via AJAX based on user selection).
        $mform->addElement('autocomplete', 'courseid', 
            get_string('selectcourse', 'local_coursereassignment'), 
            [],
            [
                'ajax' => 'local_coursereassignment/course_selector',
                'multiple' => false
            ]
        );
        $mform->addRule('courseid', get_string('coursenotselected', 'local_coursereassignment'), 'required', null, 'client');
        $mform->addHelpButton('courseid', 'selectcourse', 'local_coursereassignment');
        $mform->disabledIf('courseid', 'userid', 'eq', '');

        // Action buttons.
        $this->add_action_buttons(true, get_string('reassignbutton', 'local_coursereassignment'));
    }
}
