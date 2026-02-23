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
 * Re-assignment history details page.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_coursereassignment_history');

$historyid = required_param('id', PARAM_INT);

$context = context_system::instance();
require_capability('local/coursereassignment:viewhistory', $context);

$PAGE->set_url(new moodle_url('/local/coursereassignment/history_details.php', ['id' => $historyid]));
$PAGE->set_title(get_string('historydetailstitle', 'local_coursereassignment'));
$PAGE->set_heading(get_string('historydetailstitle', 'local_coursereassignment'));
$PAGE->set_pagelayout('admin');

$sql = "SELECT h.*,
               u.firstname,
               u.lastname,
               u.username,
               c.fullname AS coursename,
               q.name AS quizname,
               ru.firstname AS reassignedfirstname,
               ru.lastname AS reassignedlastname
          FROM {local_coursereassignment_history} h
          JOIN {user} u ON u.id = h.userid
          JOIN {course} c ON c.id = h.courseid
     LEFT JOIN {quiz} q ON q.id = h.quizid
          JOIN {user} ru ON ru.id = h.reassignedby
         WHERE h.id = :id";
$record = $DB->get_record_sql($sql, ['id' => $historyid], MUST_EXIST);

$olddata = json_decode((string)$record->olddata, true);
if (!is_array($olddata)) {
    $olddata = [];
}

$renderattempttable = function(array $attempts): string {
    if (empty($attempts)) {
        return html_writer::div(get_string('noquizattemptdata', 'local_coursereassignment'), 'alert alert-info');
    }

    $table = new html_table();
    $table->head = [
        get_string('attempt', 'local_coursereassignment'),
        get_string('state', 'local_coursereassignment'),
        get_string('startedon', 'quiz'),
        get_string('timecompleted', 'quiz'),
        get_string('duration', 'local_coursereassignment'),
        get_string('grade', 'local_coursereassignment'),
    ];
    foreach ($attempts as $attempt) {
        $state = !empty($attempt['state']) ? s((string)$attempt['state']) : '-';
        $started = !empty($attempt['timestart']) ? userdate((int)$attempt['timestart']) : '-';
        $finished = !empty($attempt['timefinish']) ? userdate((int)$attempt['timefinish']) : '-';
        $duration = '-';
        if (!empty($attempt['timestart']) && !empty($attempt['timefinish'])) {
            $duration = format_time((int)$attempt['timefinish'] - (int)$attempt['timestart']);
        }
        $grade = array_key_exists('sumgrades', $attempt) && $attempt['sumgrades'] !== null ? s((string)$attempt['sumgrades']) : '-';
        $table->data[] = [
            !empty($attempt['attempt']) ? (int)$attempt['attempt'] : '-',
            $state,
            $started,
            $finished,
            $duration,
            $grade,
        ];
    }
    return html_writer::table($table);
};

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('historydetailstitle', 'local_coursereassignment'));

$infotable = new html_table();
$infotable->data = [
    [get_string('reassignmenttype', 'local_coursereassignment'), $record->reassignmenttype === 'course' ? get_string('type_course', 'local_coursereassignment') : get_string('type_quiz', 'local_coursereassignment')],
    [get_string('user', 'local_coursereassignment'), fullname($record) . ' (' . s($record->username) . ')'],
    [get_string('course', 'local_coursereassignment'), format_string($record->coursename)],
    [get_string('reassignedby', 'local_coursereassignment'), fullname((object)['firstname' => $record->reassignedfirstname, 'lastname' => $record->reassignedlastname])],
    [get_string('reassigndate', 'local_coursereassignment'), userdate((int)$record->timecreated)],
];
echo html_writer::table($infotable);

if ($record->reassignmenttype === 'course') {
    $activities = $olddata['activities'] ?? [];
    $quizdata = $olddata['quizdata'] ?? [];

    $activityrows = [];
    foreach ($activities as $activity) {
        if (empty($activity['completionenabled'])) {
            continue;
        }

        $activityname = s(($activity['name'] ?? get_string('unknown', 'core')) . ' (' . ($activity['modname'] ?? '-') . ')');
        $iscompleted = $activity['iscompleted'] ?? null;
        if ($iscompleted === null && array_key_exists('completionstate', $activity)) {
            $iscompleted = in_array((int)$activity['completionstate'], [
                COMPLETION_COMPLETE,
                COMPLETION_COMPLETE_PASS,
                COMPLETION_COMPLETE_FAIL,
                COMPLETION_COMPLETE_FAIL_HIDDEN,
            ], true);
        }

        $statuslabel = '-';
        if ($iscompleted === true) {
            $statuslabel = get_string('completed', 'completion');
        } else if ($iscompleted === false) {
            $statuslabel = get_string('incomplete', 'local_coursereassignment');
        }

        $activityrows[] = ['activity' => $activityname, 'status' => s($statuslabel)];
    }

    echo $OUTPUT->heading(get_string('activitycompletionstatus', 'local_coursereassignment'), 3, 'mt-5');
    if (empty($activityrows)) {
        echo $OUTPUT->notification(get_string('notrackedactivities', 'local_coursereassignment'), 'info');
    } else {
        echo html_writer::start_div('border rounded');
        echo html_writer::start_div('d-flex justify-content-between px-3 py-2 border-bottom bg-light');
        echo html_writer::tag('strong', get_string('activity', 'local_coursereassignment'));
        echo html_writer::tag('strong', get_string('completionstatus', 'local_coursereassignment'));
        echo html_writer::end_div();

        foreach ($activityrows as $row) {
            echo html_writer::start_div('d-flex justify-content-between px-3 py-2 border-bottom');
            echo html_writer::span($row['activity']);
            echo html_writer::span($row['status']);
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }

    echo $OUTPUT->heading(get_string('quizhistorysnapshot', 'local_coursereassignment'), 3, 'mt-5');
    if (empty($quizdata)) {
        echo $OUTPUT->notification(get_string('noquizattemptdata', 'local_coursereassignment'), 'info');
    } else {
        foreach ($quizdata as $quizsnapshot) {
            $quizname = format_string($quizsnapshot['name'] ?? get_string('quiz', 'local_coursereassignment'));
            echo $OUTPUT->heading(get_string('quiztitle', 'local_coursereassignment', $quizname), 4);
            $attempts = $quizsnapshot['attempts'] ?? [];
            $bestgrade = $quizsnapshot['bestgrade'] ?? null;
            echo html_writer::tag('p', get_string('attempts', 'quiz') . ': ' . count($attempts));
            echo html_writer::tag('p', get_string('bestgrade', 'local_coursereassignment') . ': ' . (($bestgrade !== null && $bestgrade !== false) ? s((string)$bestgrade) : '-'));
            echo $renderattempttable($attempts);
            echo html_writer::empty_tag('hr', ['class' => 'my-4']);
        }
    }
} else {
    echo $OUTPUT->heading(get_string('quizhistorysnapshot', 'local_coursereassignment'), 3, 'mt-5');
    $attempts = $olddata['attempts'] ?? [];
    $bestgrade = null;
    if (!empty($olddata['quiz_grades'][0]['grade'])) {
        $bestgrade = $olddata['quiz_grades'][0]['grade'];
    }
    echo html_writer::tag('p', get_string('attempts', 'quiz') . ': ' . count($attempts));
    echo html_writer::tag('p', get_string('grade', 'local_coursereassignment') . ': ' . (($bestgrade !== null) ? s((string)$bestgrade) : '-'));
    echo $renderattempttable($attempts);
}

echo $OUTPUT->footer();
