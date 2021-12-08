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
 * version.php - version information.
 *
 * @package    quizaccess_tomaetest
 * @subpackage quiz
 * @copyright  2021 Tomax ltd <roy@tomax.co.il>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(__FILE__)) . '../../../../config.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}



$value = $_GET["moodleSession"];
$coursemodule = $_GET["courseModule"];
$sessionname = 'MoodleSession' . $CFG->sessioncookie;
$sessionpath = $CFG->sessioncookiepath;
$sessiondomain = $CFG->sessioncookiedomain;
$sessionsecure = $CFG->cookiesecure;
$sessionhttponly = $CFG->cookiehttponly;

if (
    !isset($_SERVER['HTTPS'])
) {
    $sessionsecure = "0";
}

setcookie($sessionname, $value, 0, $sessionpath, $sessiondomain, $sessionsecure, $sessionhttponly);

$cmurl = new moodle_url('/mod/quiz/view.php', array('id' => $coursemodule));
header("location: $cmurl");
