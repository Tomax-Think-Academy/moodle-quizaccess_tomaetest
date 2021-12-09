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
require_login();
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); // It must be included from a Moodle page.
}


$quizid = isset($_GET["quizID"]) ? $_GET["quizID"] : false;

if ($quizid === false) {
    echo 'window.close()';
}

$quiz = quizaccess_tomaetest_utils::get_etest_quiz($quizid);
$code = $quiz->extradata["TETExamLink"];

$domain = tomaetest_connection::$config->domain;

$cmid = quizaccess_tomaetest_utils::getCMID($quizid);
$context = context_module::instance($cmid);
if (has_capability("mod/quiz:attempt", $context)) {


    if ($domain == "tomaxdev") {
        $code = 'dev-' . $code;
    } else if ($domain == "tomaxtst") {
        $code = 'tst-' . $code;
    }

    $coursemodule = $cmid;
    $moodlesession = $_COOKIE['MoodleSession' . $CFG->sessioncookie];
    $logintopanel = new moodle_url('/mod/quiz/accessrule/tomaetest/studentSSO.php',
     array('moodleSession' => $moodlesession, "courseModule" => $coursemodule));
    $logintopanel = urlencode($logintopanel);
    $result = tomaetest_connection::syncToTomaETestFromDatabase($quizid);
    $externalid = quizaccess_tomaetest_utils::get_external_id_for_participant($USER);
    $participant = tomaetest_connection::post_request("participant/getByUserName/view", ["UserName" => $externalid]);
    if ($participant["success"]) {
        $tokenrequest = tomaetest_connection::post_request("exam/thirdPartySSOMoodle/view",
         ["examID" => $quiz->extradata["TETID"], "parID" => $participant["data"]]);
        if ($tokenrequest["success"]) {
            $token = $tokenrequest["data"]["token"];
            $parid = $tokenrequest["data"]["parID"];
            $url = "vix://?examCode=$code&token=$token&parID=$parid&thirdPartyStartupURL=$logintopanel";
            header("location: $url");
            exit;
        }
    }
}
echo "<script>alert('You are not a student of this exam.');window.close();</script>";
