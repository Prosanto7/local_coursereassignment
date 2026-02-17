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
 * Web service function definitions for Course Re-assignment plugin.
 *
 * @package    local_coursereassignment
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_coursereassignment_search_users' => [
        'classname'   => 'local_coursereassignment_external',
        'methodname'  => 'search_users',
        'classpath'   => 'local/coursereassignment/externallib.php',
        'description' => 'Search users by username or email',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'local/coursereassignment:managecourse,local/coursereassignment:managequiz',
    ],
    'local_coursereassignment_get_user_courses' => [
        'classname'   => 'local_coursereassignment_external',
        'methodname'  => 'get_user_courses',
        'classpath'   => 'local/coursereassignment/externallib.php',
        'description' => 'Get courses where user is enrolled',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'local/coursereassignment:managecourse,local/coursereassignment:managequiz',
    ],
    'local_coursereassignment_get_course_quizzes' => [
        'classname'   => 'local_coursereassignment_external',
        'methodname'  => 'get_course_quizzes',
        'classpath'   => 'local/coursereassignment/externallib.php',
        'description' => 'Get quizzes in a course',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> 'local/coursereassignment:managequiz',
    ],
];
