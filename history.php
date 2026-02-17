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
 * Re-assignment history page.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('local_coursereassignment_history');

$PAGE->set_url(new moodle_url('/local/coursereassignment/history.php'));
$PAGE->set_title(get_string('historypagetitle', 'local_coursereassignment'));
$PAGE->set_heading(get_string('historypagetitle', 'local_coursereassignment'));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('historypagetitle', 'local_coursereassignment'));

// Define table.
$table = new flexible_table('local-coursereassignment-history');

$table->define_columns([
    'reassignmenttype',
    'user',
    'course',
    'quiz',
    'reassignedby',
    'timecreated'
]);

$table->define_headers([
    get_string('reassignmenttype', 'local_coursereassignment'),
    get_string('user', 'local_coursereassignment'),
    get_string('course', 'local_coursereassignment'),
    get_string('quiz', 'local_coursereassignment'),
    get_string('reassignedby', 'local_coursereassignment'),
    get_string('reassigndate', 'local_coursereassignment')
]);

$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('quiz');
$table->setup();

// Get history records.
$sql = "SELECT h.*,
               u.firstname as userfirstname, u.lastname as userlastname, u.username,
               c.fullname as coursename,
               q.name as quizname,
               ru.firstname as reassignedfirstname, ru.lastname as reassignedlastname
          FROM {local_coursereassignment_history} h
          JOIN {user} u ON u.id = h.userid
          JOIN {course} c ON c.id = h.courseid
     LEFT JOIN {quiz} q ON q.id = h.quizid
          JOIN {user} ru ON ru.id = h.reassignedby
      ORDER BY h.timecreated DESC";

$records = $DB->get_records_sql($sql);

if (empty($records)) {
    echo html_writer::tag('p', get_string('nohistory', 'local_coursereassignment'), ['class' => 'alert alert-info']);
} else {
    foreach ($records as $record) {
        $type = $record->reassignmenttype == 'course' 
            ? get_string('type_course', 'local_coursereassignment')
            : get_string('type_quiz', 'local_coursereassignment');
        
        $username = fullname($record) . ' (' . $record->username . ')';
        
        $courselink = html_writer::link(
            new moodle_url('/course/view.php', ['id' => $record->courseid]),
            $record->coursename
        );
        
        $quizname = '';
        if ($record->quizid) {
            $cm = get_coursemodule_from_instance('quiz', $record->quizid, $record->courseid);
            if ($cm) {
                $quizname = html_writer::link(
                    new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]),
                    $record->quizname
                );
            } else {
                $quizname = $record->quizname;
            }
        }
        
        $reassignedby = $record->reassignedfirstname . ' ' . $record->reassignedlastname;
        
        $date = userdate($record->timecreated, get_string('strftimedatetime', 'langconfig'));
        
        $table->add_data([
            $type,
            $username,
            $courselink,
            $quizname,
            $reassignedby,
            $date
        ]);
    }
    
    $table->finish_output();
}

echo $OUTPUT->footer();
