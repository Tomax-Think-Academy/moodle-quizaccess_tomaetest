<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * checkConnection.php - Checks the connection using ApiKeys
 *
 * @package    quizaccess_tomaetest
 * @subpackage quiz
 * @copyright  2021 Tomax ltd <roy@tomax.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(dirname(__FILE__)) . '../../../../config.php');
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

global $CFG;

require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/connection.php");
require_login();

$connection = new tomaetest_connection;
$res = $connection->get_exams();
if (isset($res)) {
    if ($res["success"] == true) {
        write(get_string('well_connected' , 'quizaccess_tomaetest'));
    } else {
        write(get_string('connection_auth_error' , 'quizaccess_tomaetest'));
    }
} else {
    write(get_string('no_open_connection' , 'quizaccess_tomaetest'));
}
echo ("<script>history.back();</script>");

function write($message) {
    echo ("<script>alert('$message');</script>");
}
