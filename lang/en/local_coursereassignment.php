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
 * Language strings for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Course Re-assignment';
$string['coursereassignment'] = 'Course Re-assignment';

// Capabilities.
$string['coursereassignment:managecourse'] = 'Manage course re-assignment';
$string['coursereassignment:managequiz'] = 'Manage quiz re-assignment';
$string['coursereassignment:viewhistory'] = 'View re-assignment history';

// Navigation.
$string['coursereassign'] = 'Re-Assign Course';
$string['quizreassign'] = 'Re-Assign Quiz';
$string['viewhistory'] = 'Re-assignment History';

// Form labels.
$string['selectuser'] = 'Select User';
$string['selectuser_help'] = 'Search and select the user for re-assignment. Start typing the username, email, or name to find the user.';
$string['selectcourse'] = 'Select Course';
$string['selectcourse_help'] = 'Select the course to re-assign. Only courses where the selected user is enrolled will be shown.';
$string['selectquiz'] = 'Select Quiz';
$string['selectquiz_help'] = 'Select the quiz to re-assign. Only quizzes from the selected course will be shown.';
$string['searchuser'] = 'Search by username or email';
$string['searchcourse'] = 'Search enrolled courses';
$string['searchquiz'] = 'Search available quizzes';
$string['reassignbutton'] = 'Re-assign';

// Settings.
$string['settings'] = 'Course Re-assignment Settings';
$string['historysettings'] = 'History Settings';
$string['historyperpage'] = 'History records per page';
$string['historyperpage_desc'] = 'Number of re-assignment history records displayed per page on the history screen.';
$string['emailsettings'] = 'Email Settings';
$string['courseemailtemplate'] = 'Course Re-assignment Email Template';
$string['courseemailtemplate_desc'] = 'Email template for course re-assignment. Available placeholders: {firstname}, {lastname}, {username}, {coursename}, {courselink}';
$string['quizemailtemplate'] = 'Quiz Re-assignment Email Template';
$string['quizemailtemplate_desc'] = 'Email template for quiz re-assignment. Available placeholders: {firstname}, {lastname}, {username}, {coursename}, {quizname}, {quizlink}';
$string['courseemailsubject'] = 'Course Re-assignment Email Subject';
$string['courseemailsubject_desc'] = 'Subject line for course re-assignment email';
$string['quizemailsubject'] = 'Quiz Re-assignment Email Subject';
$string['quizemailsubject_desc'] = 'Subject line for quiz re-assignment email';

// Messages.
$string['usernotselected'] = 'Please select a user';
$string['coursenotselected'] = 'Please select a course';
$string['quiznotselected'] = 'Please select a quiz';
$string['usernotenrolled'] = 'User is not enrolled in the selected course';
$string['quiznotincourse'] = 'Quiz does not belong to the selected course';
$string['reassignsuccess'] = 'Re-assignment completed successfully';
$string['reassignfailed'] = 'Re-assignment failed. Please try again.';
$string['emailsentsuccess'] = 'Email notification sent successfully';
$string['emailsentfailed'] = 'Failed to send email notification';
$string['notificationsentsuccess'] = 'Moodle notification sent successfully';

// History page.
$string['historypagetitle'] = 'Re-assignment History';
$string['reassignmenttype'] = 'Type';
$string['course'] = 'Course';
$string['quiz'] = 'Quiz';
$string['user'] = 'User';
$string['grade'] = 'Grade';
$string['bestgrade'] = 'Best grade';
$string['state'] = 'State';
$string['duration'] = 'Duration';
$string['attempt'] = 'Attempt No.';
$string['reassignedby'] = 'Re-assigned By';
$string['reassigndate'] = 'Re-assignment Date';
$string['nohistory'] = 'No re-assignment history found';
$string['viewdetails'] = 'View details';
$string['historydetailstitle'] = 'Re-assignment History Details';
$string['activitycompletionstatus'] = 'Activity completion status';
$string['activity'] = 'Activity';
$string['completionstatus'] = 'Completion status';
$string['completedactivities'] = 'Completed activities';
$string['incomplete'] = 'Not completed';
$string['notcompletedactivities'] = 'Not completed activities';
$string['nottrackedactivities'] = 'Not tracked activities';
$string['quizhistorysnapshot'] = 'Quiz history snapshot';
$string['quiztitle'] = 'Quiz: {$a}';
$string['noquizattemptdata'] = 'No quiz attempt data stored for this record';

// Types.
$string['type_course'] = 'Course';
$string['type_quiz'] = 'Quiz';

// Default email templates.
$string['defaultcoursesubject'] = 'Course Re-assigned: {coursename}';
$string['defaultcoursemessage'] = 'Dear {firstname} {lastname},

Your course "{coursename}" has been re-assigned. You can now start fresh with this course.

Access the course here: {courselink}

Good luck!';

$string['defaultquizsubject'] = 'Quiz Re-assigned: {quizname}';
$string['defaultquizmessage'] = 'Dear {firstname} {lastname},

Your quiz "{quizname}" in course "{coursename}" has been re-assigned. You can now attempt this quiz again.

Access the quiz here: {quizlink}

Good luck!';

// Privacy.
$string['privacy:metadata:local_coursereassignment_history'] = 'Stores historical data of course and quiz re-assignments';
$string['privacy:metadata:local_coursereassignment_history:userid'] = 'The ID of the user who was re-assigned';
$string['privacy:metadata:local_coursereassignment_history:reassignedby'] = 'The ID of the user who performed the re-assignment';
$string['privacy:metadata:local_coursereassignment_history:courseid'] = 'The ID of the course that was re-assigned';
$string['privacy:metadata:local_coursereassignment_history:quizid'] = 'The ID of the quiz that was re-assigned';
$string['privacy:metadata:local_coursereassignment_history:timecreated'] = 'The time when the re-assignment was performed';
$string['privacy:metadata:local_coursereassignment_history:olddata'] = 'Historical data before re-assignment';
$string['messageprovider:reassignmentnotice'] = 'Re-assignment notifications';
