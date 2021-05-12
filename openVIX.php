<?php

require_once(dirname(dirname(__FILE__)) . '../../../../config.php');
require_login();
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.'); /// It must be included from a Moodle page
}


$quizID = isset($_GET["quizID"]) ? $_GET["quizID"] : false;

if ($quizID === false) {
    echo 'window.close()';
}

$quiz = quizaccess_tomaetest_utils::get_etest_quiz($quizID);
$code = $quiz->extradata["TETExamLink"];

$domain = tomaetest_connection::$config->domain;

$cmid = quizaccess_tomaetest_utils::getCMID($quizID);
$context = context_module::instance($cmid);
if (has_capability("mod/quiz:attempt", $context)) {


    if ($domain == "tomaxdev") {
        $code = 'dev-' . $code;
    } else if ($domain == "tomaxtst") {
        $code = 'tst-' . $code;
    }

    $courseModule = $cmid;
    $moodleSession = $_COOKIE['MoodleSession' . $CFG->sessioncookie];
    $loginToPanel = new moodle_url('/mod/quiz/accessrule/tomaetest/studentSSO.php', array('moodleSession' => $moodleSession, "courseModule" => $courseModule));
    $loginToPanel = urlencode($loginToPanel);
    $result = tomaetest_connection::syncToTomaETestFromDatabase($quizID);
    $externalID = quizaccess_tomaetest_utils::getExternalIDForParticipant($USER);
    $participant = tomaetest_connection::post_request("participant/getByUserName/view", ["UserName" => $externalID]);
    if ($participant["success"]) {
        $tokenRequest = tomaetest_connection::post_request("exam/thirdPartySSOMoodle/view", ["examID" => $quiz->extradata["TETID"], "parID" => $participant["data"]]);
        if ($tokenRequest["success"]) {
            $token = $tokenRequest["data"]["token"];
            $parID = $tokenRequest["data"]["parID"];
            $url = "vix://?examCode=$code&token=$token&parID=$parID&thirdPartyStartupURL=$loginToPanel";
            // echo $url;
            header("location: $url");
            exit;
        }
    }
}
echo "<script>alert('You are not a student of this exam.');window.close();</script>";
