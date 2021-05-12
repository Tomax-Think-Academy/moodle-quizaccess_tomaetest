<?php

require_once(dirname(dirname(__FILE__)) . '../../../../config.php');

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}



$value = $_GET["moodleSession"];
$courseModule = $_GET["courseModule"];
$sessionName = 'MoodleSession' . $CFG->sessioncookie;
$sessionPath = $CFG->sessioncookiepath;
$sessionDomain = $CFG->sessioncookiedomain;
$sessionSecure = $CFG->cookiesecure;
$sessionHTTPOnly = $CFG->cookiehttponly;

if (
    !isset($_SERVER['HTTPS'])
) {
    $sessionSecure = "0";
}

setcookie($sessionName,$value,0,$sessionPath,$sessionDomain,$sessionSecure,$sessionHTTPOnly);

$cmURL = new moodle_url('/mod/quiz/view.php', array('id' => $courseModule));
header("location: $cmURL");