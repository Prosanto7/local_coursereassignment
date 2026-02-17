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
 * Settings for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Create the category under courses.
    $ADMIN->add('courses', new admin_category('local_coursereassignment',
        get_string('pluginname', 'local_coursereassignment')));

    // Settings page.
    $settings = new admin_settingpage('local_coursereassignment_settings',
        get_string('settings', 'local_coursereassignment'));

    if ($ADMIN->fulltree) {
        // Email settings heading.
        $settings->add(new admin_setting_heading('local_coursereassignment/emailsettings',
            get_string('emailsettings', 'local_coursereassignment'), ''));

        // Course re-assignment email subject.
        $settings->add(new admin_setting_configtext('local_coursereassignment/courseemailsubject',
            get_string('courseemailsubject', 'local_coursereassignment'),
            get_string('courseemailsubject_desc', 'local_coursereassignment'),
            get_string('defaultcoursesubject', 'local_coursereassignment'),
            PARAM_TEXT));

        // Course re-assignment email template.
        $settings->add(new admin_setting_confightmleditor('local_coursereassignment/courseemailtemplate',
            get_string('courseemailtemplate', 'local_coursereassignment'),
            get_string('courseemailtemplate_desc', 'local_coursereassignment'),
            get_string('defaultcoursemessage', 'local_coursereassignment')));

        // Quiz re-assignment email subject.
        $settings->add(new admin_setting_configtext('local_coursereassignment/quizemailsubject',
            get_string('quizemailsubject', 'local_coursereassignment'),
            get_string('quizemailsubject_desc', 'local_coursereassignment'),
            get_string('defaultquizsubject', 'local_coursereassignment'),
            PARAM_TEXT));

        // Quiz re-assignment email template.
        $settings->add(new admin_setting_confightmleditor('local_coursereassignment/quizemailtemplate',
            get_string('quizemailtemplate', 'local_coursereassignment'),
            get_string('quizemailtemplate_desc', 'local_coursereassignment'),
            get_string('defaultquizmessage', 'local_coursereassignment')));
    }

    $ADMIN->add('local_coursereassignment', $settings);

    // Add external pages for course and quiz re-assignment.
    $ADMIN->add('local_coursereassignment',
        new admin_externalpage('local_coursereassignment_course',
            get_string('coursereassign', 'local_coursereassignment'),
            new moodle_url('/local/coursereassignment/course_reassign.php'),
            'local/coursereassignment:managecourse'));

    $ADMIN->add('local_coursereassignment',
        new admin_externalpage('local_coursereassignment_quiz',
            get_string('quizreassign', 'local_coursereassignment'),
            new moodle_url('/local/coursereassignment/quiz_reassign.php'),
            'local/coursereassignment:managequiz'));

    $ADMIN->add('local_coursereassignment',
        new admin_externalpage('local_coursereassignment_history',
            get_string('viewhistory', 'local_coursereassignment'),
            new moodle_url('/local/coursereassignment/history.php'),
            'local/coursereassignment:viewhistory'));
}
