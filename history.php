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
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_coursereassignment_history');

$userid = optional_param('userid', 0, PARAM_INT);
$search = trim(optional_param('search', '', PARAM_RAW_TRIMMED));
$defaultperpage = (int)get_config('local_coursereassignment', 'historyperpage');
if ($defaultperpage <= 0) {
    $defaultperpage = 10;
}
$perpage = optional_param('perpage', $defaultperpage, PARAM_INT);
$perpage = max(1, min($perpage, TABLE_SHOW_ALL_PAGE_SIZE));

$PAGE->set_url(new moodle_url('/local/coursereassignment/history.php'));
$PAGE->set_title(get_string('historypagetitle', 'local_coursereassignment'));
$PAGE->set_heading(get_string('historypagetitle', 'local_coursereassignment'));
$PAGE->set_pagelayout('admin');

$baseparams = [];
if (!empty($userid)) {
    $baseparams['userid'] = $userid;
}
if ($search !== '') {
    $baseparams['search'] = $search;
}
if (!empty($perpage)) {
    $baseparams['perpage'] = $perpage;
}
$baseurl = new moodle_url('/local/coursereassignment/history.php', $baseparams);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('historypagetitle', 'local_coursereassignment'));

// Search box.
$searchdata = [
    'action' => (new moodle_url('/local/coursereassignment/history.php'))->out(false),
    'inputname' => 'search',
    'searchstring' => get_string('search'),
    'query' => $search,
    'hiddenfields' => [],
];
if (!empty($userid)) {
    $searchdata['hiddenfields'][] = [
        'name' => 'userid',
        'value' => $userid,
    ];
}
if (!empty($perpage)) {
    $searchdata['hiddenfields'][] = [
        'name' => 'perpage',
        'value' => $perpage,
    ];
}

echo html_writer::start_div('d-flex justify-content-between align-items-center mb-3');
echo html_writer::start_div('search-box ml-1');
echo $OUTPUT->render_from_template('core/search_input', $searchdata);
echo html_writer::end_div();
echo html_writer::end_div();

// Define table.
$table = new flexible_table('local-coursereassignment-history');

$table->define_columns([
    'userfullname',
    'useremail',
    'reassignmenttype',
    'coursename',
    'quizname',
    'reassignedbyname',
    'timecreated',
    'actions',
]);

$table->define_headers([
    get_string('user', 'local_coursereassignment'),
    get_string('email'),
    get_string('reassignmenttype', 'local_coursereassignment'),
    get_string('course', 'local_coursereassignment'),
    get_string('quiz', 'local_coursereassignment'),
    get_string('reassignedby', 'local_coursereassignment'),
    get_string('reassigndate', 'local_coursereassignment'),
    get_string('actions'),
]);

$table->define_baseurl($baseurl);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->no_sorting('actions');
$table->setup();

// Build search filters.
$where = [];
$params = [];

if (!empty($userid)) {
    $where[] = 'h.userid = :userid';
    $params['userid'] = $userid;
}

if ($search !== '') {
    $searchparam = '%' . $DB->sql_like_escape($search) . '%';
    $searchwhere = [];
    $searchwhere[] = $DB->sql_like('u.firstname', ':searchfirstname', false);
    $searchwhere[] = $DB->sql_like('u.lastname', ':searchlastname', false);
    $searchwhere[] = $DB->sql_like('u.username', ':searchusername', false);
    $searchwhere[] = $DB->sql_like('c.fullname', ':searchcourse', false);
    $searchwhere[] = $DB->sql_like('q.name', ':searchquiz', false);
    $searchwhere[] = $DB->sql_like('ru.firstname', ':searchreassignfirstname', false);
    $searchwhere[] = $DB->sql_like('ru.lastname', ':searchreassignlastname', false);
    $where[] = '(' . implode(' OR ', $searchwhere) . ')';

    $params['searchfirstname'] = $searchparam;
    $params['searchlastname'] = $searchparam;
    $params['searchusername'] = $searchparam;
    $params['searchcourse'] = $searchparam;
    $params['searchquiz'] = $searchparam;
    $params['searchreassignfirstname'] = $searchparam;
    $params['searchreassignlastname'] = $searchparam;
}

// Support flexible table sorting with explicit SQL mappings.
$sortmap = [
    'userfullname' => $DB->sql_fullname('u.firstname', 'u.lastname'),
    'useremail' => 'u.email',
    'reassignmenttype' => 'h.reassignmenttype',
    'coursename' => 'c.fullname',
    'quizname' => 'q.name',
    'reassignedbyname' => $DB->sql_fullname('ru.firstname', 'ru.lastname'),
    'timecreated' => 'h.timecreated',
];
$sortbits = [];
foreach ($table->get_sort_columns() as $column => $direction) {
    if (!array_key_exists($column, $sortmap)) {
        continue;
    }
    $sortbits[] = $DB->sql_order_by_null($sortmap[$column], $direction);
}
$sortsql = $sortbits ? implode(', ', $sortbits) : 'h.timecreated DESC';

$wheresql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$fromsql = "FROM {local_coursereassignment_history} h
              JOIN {user} u ON u.id = h.userid
              JOIN {course} c ON c.id = h.courseid
         LEFT JOIN {quiz} q ON q.id = h.quizid
              JOIN {user} ru ON ru.id = h.reassignedby";

$totalsql = "SELECT COUNT(1) $fromsql $wheresql";
$totalrecords = (int)$DB->count_records_sql($totalsql, $params);
$table->pagesize($perpage, $totalrecords);

$datasql = "SELECT h.*,
                   u.firstname,
                   u.lastname,
                   u.email,
                   u.username,
                   c.fullname AS coursename,
                   q.name AS quizname,
                   ru.firstname AS reassignedfirstname,
                   ru.lastname AS reassignedlastname
              $fromsql
              $wheresql
          ORDER BY $sortsql";

$records = $DB->get_records_sql($datasql, $params, $table->get_page_start(), $table->get_page_size());

if (!$records) {
    echo $OUTPUT->notification(get_string('nohistory', 'local_coursereassignment'), 'info');
}

foreach ($records as $record) {
    $type = $record->reassignmenttype === 'course'
        ? get_string('type_course', 'local_coursereassignment')
        : get_string('type_quiz', 'local_coursereassignment');

    $userlabel = fullname($record);
    $userlink = html_writer::link(
        new moodle_url('/user/profile.php', ['id' => $record->userid]),
        $userlabel
    );

    $courselink = html_writer::link(
        new moodle_url('/course/view.php', ['id' => $record->courseid]),
        format_string($record->coursename)
    );

    $quizdisplay = '';
    if (!empty($record->quizid)) {
        $cm = get_coursemodule_from_instance('quiz', $record->quizid, $record->courseid, false, IGNORE_MISSING);
        if ($cm) {
            $quizdisplay = html_writer::link(
                new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]),
                format_string($record->quizname)
            );
        } else {
            $quizdisplay = format_string((string)$record->quizname);
        }
    }

    $reassignedby = fullname((object)[
        'firstname' => $record->reassignedfirstname,
        'lastname' => $record->reassignedlastname,
    ]);

    $date = userdate($record->timecreated, get_string('strftimedatetime', 'langconfig'));
    $detailsurl = new moodle_url('/local/coursereassignment/history_details.php', ['id' => $record->id]);
    $detailsicon = $OUTPUT->pix_icon('i/preview', get_string('viewdetails', 'local_coursereassignment'));
    $detailslink = html_writer::link(
        $detailsurl,
        $detailsicon . ' ' . get_string('viewdetails', 'local_coursereassignment'),
        ['class' => 'btn btn-sm btn-outline-primary']
    );

    $table->add_data_keyed([
        'userfullname' => $userlink,
        'useremail' => s((string)$record->email),
        'reassignmenttype' => $type,
        'coursename' => $courselink,
        'quizname' => $quizdisplay,
        'reassignedbyname' => $reassignedby,
        'timecreated' => $date,
        'actions' => $detailslink,
    ]);
}

$table->finish_output();

echo $OUTPUT->footer();
