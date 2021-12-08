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
defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");

$identifierarraystudent = array(
    quizaccess_tomaetest_utils::IDENTIFIER_BY_EMAIL => "Email address",
    quizaccess_tomaetest_utils::IDENTIFIER_BY_ID => "ID number",
    quizaccess_tomaetest_utils::IDENTIFIER_BY_USERNAME => "User name",
    quizaccess_tomaetest_utils::IDENTIFIER_BY_ORBITID => "Orbit id",
);

$identifierarray_TEACHER = array(
    quizaccess_tomaetest_utils::IDENTIFIER_BY_EMAIL => "Email address",
    quizaccess_tomaetest_utils::IDENTIFIER_BY_ID => "ID",
    quizaccess_tomaetest_utils::IDENTIFIER_BY_HUJIID => "HUJI ID"
);

$apps = [
    ["name" => "Skype", "value" => "skype"],
    ["name" => "TeamViewer", "value" => "teamviewer"],
    ["name" => "Zoom", "value" => "zoom"],
    ["name" => "AnyDesk", "value" => "anydesk"],
    ["name" => "Remote Desktop Connection", "value" => "rdp"]
];


$settings->add(new admin_setting_heading(
    "quizaccess_system_config",
    "TomaETest System Configuration",
    "Define the TomaETest system configurations."
));
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/allow',
    "Allow TomaETest Quiz access",
    "",
    '1'
));

$settings->add(new admin_setting_configtext(
    'quizaccess_tomaetest/domain',
    "Domain",
    "",
    ''
));

$settings->add(new admin_setting_configpasswordunmask(
    'quizaccess_tomaetest/userid',
    "TomaETest UserID",
    "",
    ''
));

$settings->add(new admin_setting_configpasswordunmask(
    'quizaccess_tomaetest/apikey',
    "TomaETest APIKey",
    "",
    ''
));

$disclaimerHTML = new admin_setting_confightmleditor(
    'quizaccess_tomaetest/disclaimer',
    "Student Disclaimer",
    "",
    ''
);
$disclaimerHTML->set_updatedcallback('updateDisclaimer');
$settings->add($disclaimerHTML);

$settings->add(new
    admin_setting_configselect(
        'quizaccess_tomaetest/tomaetest_teacherID',
        'Set the Default Teacher identifier',
        '',
        '',
    $identifierarray_TEACHER
));

$settings->add(new
    admin_setting_configselect(
        'quizaccess_tomaetest/tomaetest_studentID',
        'Set the Default Student identifier',
        '',
        '',
    $identifierarraystudent
));

    $settings->add(new admin_setting_heading(
        "quizaccess_tomaetest/block_programs",
        "",
        "Define the Programs state when running the exam </br>
         <b>Ignore</b> = The program can be used. </br>
         <b>Alert</b> = The student will be alerted, and it will be saved on the integrity report.</br>
         <b>Deny access</b> = The student will have to close the program before entering."
    ));
foreach($apps as $app){
    $name = $app["name"];
    $value = $app["value"];
    $settings->add(new admin_setting_configselect(
            "quizaccess_tomaetest/tomaetest_appstate_$value",
            $name,
            '',
            '',
            quizaccess_tomaetest_utils::$APPLICATION_STATE
        ));
}

$settings->add(new admin_setting_configtext(
        'quizaccess_tomaetest/tomaetest_closeExamDelta',
    "Closing Exam delta (minutes)",
        "This will close the exam in TomaETest only after the the exam has been closed for X minutes.",
    30,
    PARAM_INT
));



$settings->add(new admin_setting_heading(
    "quizaccess_system_config_tg",
    "TomaGrade System Configuration",
    "Define the TomaGrade system configurations."
));

$settings->add(new admin_setting_configpasswordunmask(
    'quizaccess_tomaetest/tg_userid',
    "TomaGrade UserID",
    "",
    ''
));

$settings->add(new admin_setting_configpasswordunmask(
    'quizaccess_tomaetest/tg_apikey',
    "TomaGrade APIKey",
    "",
    ''
));

$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomagrade_sync_further',
    "Scanned exams should be ID Matched",
    "",
    '1'
));



$settings->add(new admin_setting_heading(
    "quizaccess_default_section",
    "TomaETest Defaults",
    "Define the default to be used when creating a new quiz"
));
// Defaults
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_allow',
    "Default TomaETest enable proctoring",
    "",
    '1'
));

$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_showParticipant',
    "Default Show Participant on screen",
    "",
    '1'
));

$settings->add(new
    admin_setting_configselect(
        'quizaccess_tomaetest/tomaetest_lockComputer',
        'Default Lock Computer',
        '',
        '',
        quizaccess_tomaetest_utils::$LockComputerEnums
    ));



$settings->add(new admin_setting_configselect(
    'quizaccess_tomaetest/tomaetest_verificationTiming',
    'Default Verification Timing',
    '',
    '',
    quizaccess_tomaetest_utils::$verificationTimings
));
$settings->add(new admin_setting_configselect(
    'quizaccess_tomaetest/tomaetest_verificationType',
    'Default Verification Types',
    '',
    '',
    quizaccess_tomaetest_utils::$verificationTypes
));


// Proctoring Types
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_proctoringType_computer',
    "Default Proctoring Type - Computer Camera",
    "",
    ''
));
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_proctoringType_monitor',
    "Default Proctoring Type - Monitor Recording",
    "",
    ''
));
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_blockThirdParty',
    "Default Block Third party software",
    "",
    ''
));
$settings->add(new admin_setting_configcheckbox(
    'quizaccess_tomaetest/tomaetest_requireReLogin',
    "Default Require re-login process",
    "",
    ''
));

$settings->add(new admin_setting_configtext(
    'quizaccess_tomaetest/tomaetest_scanningTime',
    "Default Scanning Time",
    "",
    10,
    PARAM_INT
));


$examCodeEnter = new moodle_url('/mod/quiz/accessrule/tomaetest/examCode.php');
$settings->add(new admin_setting_heading(
    "quizaccess_permissions_config",
    "TomaETest Dashboard Permission",
    "In order to define the roles which are allowed to access TomaETest Dashboard, please assign the capability 'mod/quizaccess_tomaetest:viewTomaETestMonitor' to the appropriate role.<br> OPTIONAL: This is the link to the ExamCode enter page: <a target='_blank' href='$examCodeEnter'>$examCodeEnter</a>"
));


$loginToIntegrityManagement = new moodle_url('/mod/quiz/report/tomaetest/ssoIntegrityManagement.php');
$settings->add(new admin_setting_heading(
    "quizaccess_permissions_config_2",
    "TomaETest Integrity Management Permission",
    "In order to define the roles which are allowed to access TomaETest Integrity Report, please assign the capability 'mod/quizaccess_tomaetest:viewTomaETestAIR' to the appropriate role.<br>OPTIONAL: The link to view the Integrity Management is: <a target='_blank' href='$loginToIntegrityManagement'>$loginToIntegrityManagement </a>"
));

}

