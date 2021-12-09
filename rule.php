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
if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/connection.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/utils.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/tomagradeConnection.php");

class quizaccess_tomaetest extends quiz_access_rule_base
{

    protected $extradata;

    public function __construct($quizobj, $timenow) {
        parent::__construct($quizobj, $timenow);

        if ($this->quiz->tomaetest_extradata) {
            $this->extradata = json_decode($this->quiz->tomaetest_extradata, true);
        } else {
            $this->extradata = [];
        }
    }

    public static function make(quiz $quizobj, $timenow, $canignoretimelimits) {
        global $USER;
        $cmid = quizaccess_tomaetest_utils::get_cmid($quizobj->get_quiz()->id);
        $context = context_module::instance($cmid);
        $students = get_users_by_capability($context, "mod/quiz:attempt");
        // Check if all students who passed.
        $allow = false;
        foreach ($students as $student) {
            if ($student->id === $USER->id) {
                $allow = true;
                break;
            }
        }
        if (empty($quizobj->get_quiz()->tomaetest_innerid) || !quizaccess_tomaetest_utils::is_etest_plugin_enabled() || !$allow) {
            return null;
        }

        return new self($quizobj, $timenow);
    }

    public function prevent_access() {
        if (!quizaccess_tomaetest_utils::check_access($this->extradata["TETSebHeader"], $this->extradata)) {
            return self::get_blocked_message(quizaccess_tomaetest_utils::is_from_etest());
        } else {
            return false;
        }
    }

    public function validate_preflight_check($data, $files, $errors, $attemptid) {
        return ['not good'];
    }


    public static function add_settings_form_fields(
        mod_quiz_mod_form $quizform,
        MoodleQuickForm $mform
    ) {
        global $DB, $USER;

        if (!quizaccess_tomaetest_utils::is_etest_plugin_enabled())
            return;
        $config = tomaetest_connection::$config;
        $record = null;
        $quiz = $quizform->get_current();
        $isAllDisabled = false;
        $quizId = "";
        if ($quiz !== null) {
            $quizId = $quiz->id;
            $record = quizaccess_tomaetest_utils::get_etest_quiz($quizId);
            if ($record != false) {
                $isDuring = quizaccess_tomaetest_utils::is_on_going($record->extradata["TETID"]);
                $isClosed = (isset($record->extradata["isClosed"])) ? $record->extradata["isClosed"] : false;
                $isAllDisabled = $isDuring || $isClosed;
            }
        }

        $lockedAtts = [];
        if ($isAllDisabled) {
            $lockedAtts =  ["disabled"];
            $text = "The TomaETest exam is currently in progress, therefor it cannot be edited.";
            if ($isClosed){
                $text = "The TomaETest exam is closed, therefor it cannot be edited.";
            }
            $mform->addElement(
                'html',
                "<b>$text</b>"
            );
        } else {
            $mform->addElement(
                'html',
                "<b>TomaETest Proctoring</b>"
            );
        }
        $mform->addElement(
            'checkbox',
            'tomaetest_allow',
            "Allow TomaETest Proctoring module",
            ' ',
            $lockedAtts
        );

        $lockComputer = $mform->addElement('select', 'tomaetest_lockComputer', "Lock type", quizaccess_tomaetest_utils::$lockcomputerenums, $lockedAtts);

        $verificationtimings = $mform->addElement('select', 'tomaetest_verificationTiming', "Verification timing", quizaccess_tomaetest_utils::$verificationtimings, $lockedAtts);

        $verificationType = $mform->addElement('select', 'tomaetest_verificationType', "Verification type", quizaccess_tomaetest_utils::$verificationtypes, $lockedAtts);

        $mform->addElement('checkbox', 'tomaetest_proctoringType_computer', 'Proctoring Types', 'Computer Camera', $lockedAtts);
        $mform->addElement('checkbox', 'tomaetest_proctoringType_monitor', 'Monitor Recording', '', $lockedAtts);
        $mform->addElement('checkbox', 'tomaetest_proctoringType_second', 'Second Camera', '', ["disabled"]);
        $mform->addElement('checkbox', 'tomaetest_proctoringType_room', 'Room Verification', '', ["disabled"]);

        $mform->addElement('checkbox', 'tomaetest_showParticipant', 'Show Participant on screen', ' ', $lockedAtts);
        $mform->addElement('checkbox', 'tomaetest_blockThirdParty', 'Block Third Party', ' ', $lockedAtts);
        $mform->addElement('checkbox', 'tomaetest_requireReLogin', 'Require Re-Login Process', ' ', $lockedAtts);


        if ($config->tomagrade_sync_further === "1") {

            $mform->addElement(
                'checkbox',
                'tomaetest_scan_module',
                "Use TomaETest Scanning module",
                ' ',
                $lockedAtts
            );
            // teachers list
            $teachers = array();
            $teachersIDs = array();
            $teacherCodeToEmail = array();
            $idInMoodleToEmail = array();
            $teachersEmailsArray = array();
            $teachersIDsArray = array();

            // $isCurrentOwnerExistsInTeachersList = false;
            // $isLoggedUserExistsInTeachersList = false;

            // $loggedUserIdNumber = $USER->idnumber;

            $teachersArr = quizaccess_tomaetest_utils::get_moodle_teachers_by_course($quiz->course);
            $connection = new tet_plugin_tomagrade_connection();

            foreach ($teachersArr as $teacher) {

                $externalID = quizaccess_tomaetest_utils::get_external_id_for_teacher($teacher);
                $teachers[$teacher->id] = $teacher->firstname . " " . $teacher->lastname;
                $teachersIDs[$teacher->id] = $externalID; // email to id map
                $teacherCodeToID[$externalID] = $teacher->id; // id to email map
                $idInMoodleToEmail[$teacher->id] = $teacher->email;

                array_push($teachersEmailsArray, $teacher->email);
                array_push($teachersIDsArray, $externalID);
            }


            $identifyByEmail = false;
            $postdata = array();
            if ($identifyByEmail) {
                $postdata['emails'] = $teachersEmailsArray;
            } else {
                $postdata['teacherCodes'] = $teachersIDsArray;
            }


            $response = $connection->post_request("GetTeacherIdMoodle", json_encode($postdata));

            $arrayTeachersEmailsAndTeacherCode = $response['Message'];

            $emailTeacherCodeMap = array();
            $teacherCodeExists = array();
            $teachersThatExistsInTM = array();


            foreach ($arrayTeachersEmailsAndTeacherCode as $teacher) {
                $emailTeacherCodeMap[strtolower($teacher['Email'])] = $teacher['ExternalTeacherID'];
                $teacherCodeExists[$teacher['ExternalTeacherID']] = true;
            }

            $select = $mform->createElement('select', 'tomaetest_realted_user', 'Related TomaETest User (Required for scanning module)', '',$lockedAtts);

            $select->addOption("No Teacher Selected", -1);

            foreach ($teachers as $value => $label) {
                $teacherCode = $teachersIDs[$value];
                if (($identifyByEmail == true && isset($emailTeacherCodeMap[$value]) == false)
                    || ($identifyByEmail == false && isset($teacherCodeExists[$teacherCode]) == false)
                ) {
                    if ($value == strtolower($USER->email)) {
                        $isLoggedUserExistsInTM = false;
                    }
                    // if (false) {
                    //     $select->addOption($label . " - " . get_string('user_does_not_exists_in_tomagrade', 'plagiarism_tomagrade'), $value, array('disabled' => 'disabled'));
                    // } else {
                    $select->addOption($label, $value);
                    // }
                } else {
                    $teachersThatExistsInTM[$value] = $label;
                    $select->addOption($label, $value);
                }
            }
            $mform->addElement($select);

            $teachersEmailsArray = array();
            foreach ($teachersThatExistsInTM as $email => $name) {
                array_push($teachersEmailsArray, $email);
            }

            $postdata = array();

            if ($identifyByEmail) {
                $postdata['emails'] = $teachersEmailsArray;
            } else {
                $postdata['teacherCodes'] = $teachersIDsArray;
            }
            $response = $connection->post_request("MoodleGetExamsList", json_encode($postdata), true);

            $response = json_decode($response, true);
            $isChoosenExamInList = false;
            $examsByTeachersMap = array();

            $courses = array("0" =>  'Irrelevant - regular quiz (without scan)',);
            if (isset($response['Exams'])) {

                foreach ($response['Exams'] as $exam) {
                    $stringForExam = $exam['ExamID'];

                    // if (isset($data->idmatchontg)) {
                    // if ($exam['ExamID'] == $data->idmatchontg) {
                    // $isChoosenExamInList = true;
                    // }
                    // }

                    if (isset($existingExamsMap[$stringForExam]) == false) {
                        if (isset($exam['CourseID'])) {
                            $stringForExam = $stringForExam . " , ";
                            $stringForExam = $stringForExam . $exam['CourseID'];
                        }
                        if (isset($exam['ExamName'])) {
                            $stringForExam = $stringForExam . " , ";
                            $stringForExam = $stringForExam . $exam['ExamName'];
                        }
                        if (isset($exam['ExamDate'])) {
                            $stringForExam = $stringForExam . " , ";
                            try {
                                $date = date_create($exam['ExamDate']);
                                $stringForExam = $stringForExam . date_format($date, " d/m/Y ");
                            } catch (Exception $e) {
                                $stringForExam = $stringForExam . $exam['ExamDate'];
                            }
                        }
                        if (isset($exam['Year'])) {
                            $stringForExam = $stringForExam . " , ";
                            $stringForExam = $stringForExam . $exam['Year'];
                        }
                        if (isset($exam['SimesterID'])) {
                            $stringForExam = $stringForExam . " , simester:";
                            $stringForExam = $stringForExam . $exam['SimesterID'];
                        }
                        if (isset($exam['MoadID'])) {
                            $stringForExam = $stringForExam . " moed:";
                            $stringForExam = $stringForExam . $exam['MoadID'];
                        }
                        $courses[$exam['ExamID']] = $stringForExam;

                        $teacherIDInMoodle = isset($teacherCodeToID[$exam['TeacherCode']]) ? $teacherCodeToID[$exam['TeacherCode']] : "";

                        if ($teacherIDInMoodle != "") {
                            if (isset($examsByTeachersMap[$teacherIDInMoodle]) == false) {
                                $examsByTeachersMap[$teacherIDInMoodle] = array();
                            }
                            $examsByTeachersMap[$teacherIDInMoodle][$exam['ExamID']] = $stringForExam;
                        }
                    }
                }
                $mform->addElement('select', 'tomaetest_idmatchontg','ID Match On TomaETest',$courses, $lockedAtts);

                $mform->addElement('text', 'tomaetest_scanningTime','Student scanning time',$lockedAtts);
                $mform->setType('tomaetest_scanningTime',PARAM_INT);
                $mform->addRule('tomaetest_scanningTime', 'Numeric','numeric',null,'client');

                $buildJSTeachersMap = "var teachersmap = {}; ";
                foreach ($examsByTeachersMap as $teacher => $value) {
                    $buildJSTeachersMap = $buildJSTeachersMap . " var examArr = {}; ";
                    foreach ($value as $exam => $examString) {
                        $examString = str_replace("'", "", $examString);
                        $buildJSTeachersMap = $buildJSTeachersMap . "examArr['$exam'] = '$examString';";
                    }
                    $buildJSTeachersMap = $buildJSTeachersMap . " teachersmap['$teacher'] = examArr;";
                }
                $defaultOptionExam = "''";
                if ($record !== null && $record){
                    $extradata = $record->extradata;
                    if (isset($extradata["IDMatch"]) && $extradata["IDMatch"] === true && isset($extradata["TETExternalID"]) && !empty($extradata["TETExternalID"])){
                        $defaultOptionExam = "'".$extradata["TETExternalID"]."'";
                    }
                }
                echo ("<script>
                    var teachersHashMap = {};
                    var defaultOptionExam = $defaultOptionExam;


                     var x = 0;
                    var interval = setInterval( function() {
                        var currentTeacher = document.getElementById('id_tomaetest_realted_user');
                        if (currentTeacher == undefined || currentTeacher == null) {
                            x++;
                            if (x > 100) {
                                clearInterval(interval);
                            }
                            return;
                        }

                        var currentTeacherEmail = document.getElementById('id_tomaetest_realted_user').value;

                        clearInterval(interval);
                        initTeachersHashMap();
                        cleanSelectOptions();
                        setSelectByTeacher(currentTeacherEmail);

                        if (defaultOptionExam != '') {
                            setDefaultOptionToSelect(defaultOptionExam);
                        }

                        console.log('hello');

                        document.getElementById('id_tomaetest_realted_user').addEventListener('change', function() {
                            var email = this.value;
                            cleanSelectOptions();
                            setSelectByTeacher(email);

                     } )
                    var scanModule = document.getElementById('id_tomaetest_scan_module');

                    if (document.getElementById('id_tomaetest_idmatchontg').disabled !== true){
                       document.getElementById('id_tomaetest_idmatchontg').disabled = !(scanModule.checked);
                       document.getElementById('id_tomaetest_scanningTime').disabled = !(scanModule.checked);
                        //setSelectByTeacher(document.getElementById('id_tomaetest_realted_user').value)
                    }
                        scanModule.addEventListener('change', function(event) {
                            cleanSelectOptions();
                            document.getElementById('id_tomaetest_idmatchontg').disabled = !(scanModule.checked);
                            document.getElementById('id_tomaetest_scanningTime').disabled = !(scanModule.checked);
                            setSelectByTeacher(document.getElementById('id_tomaetest_realted_user').value)

                     } )
                    },250);

                    function setDefaultOptionToSelect(exam) {

                        var mySelect = document.getElementById('id_tomaetest_idmatchontg');

                        var length = mySelect.options.length;
                        console.log(mySelect)
                        for (i = length-1; i >= 1; i--) {
                            console.log(i);
                            if (mySelect.options[i].value == exam) {
                                mySelect.selectedIndex = i;
                                 break;
                            }
                        }

                    }


                    function cleanSelectOptions() {
                        var select = document.getElementById('id_tomaetest_idmatchontg');
                        var length = select.options.length;
                        for (i = length-1; i >= 1; i--) {
                            select.options[i] = null;
                        }
                    }

                    function initTeachersHashMap() {
                        $buildJSTeachersMap
                        teachersHashMap = teachersmap;
                    }

                    function setSelectByTeacher(email) {
                        console.log(email);
                        var exams = teachersHashMap[email];
                        var select = document.getElementById('id_tomaetest_idmatchontg');


                        if (exams == null || exams == undefined) { return; }

                        Object.keys(exams).forEach( function (exam) {
                            var opt = document.createElement('option');
                            opt.value = exam;
                            opt.innerHTML = exams[exam];
                            select.appendChild(opt);
                        });

                    }

                    </script>");
            }
        }
        //IF tomaetest_allow disabled..
        $mform->disabledIf("tomaetest_proctoringType_computer", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_monitor", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_second", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_room", "tomaetest_allow");
        $mform->disabledIf("tomaetest_showParticipant", "tomaetest_allow");
        $mform->disabledIf("tomaetest_lockComputer", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationType", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationTiming", "tomaetest_allow");
        $mform->disabledIf("tomaetest_scan_module", "tomaetest_allow");
        $mform->disabledIf("tomaetest_realted_user", "tomaetest_allow");
        $mform->disabledIf("tomaetest_idmatchontg", "tomaetest_allow");
        $mform->disabledIf("tomaetest_blockThirdParty", "tomaetest_allow");
        $mform->disabledIf("tomaetest_requireReLogin", "tomaetest_allow");
        $mform->disabledIf("tomaetest_scanningTime", "tomaetest_allow");
        //If no verification timing, no verification type.
        $mform->disabledIf("tomaetest_verificationType", "tomaetest_verificationTiming","eq", "noVerification");
        // Show Participant on  screen only if computer camera is enabled.
        $mform->disabledIf("tomaetest_showParticipant", "tomaetest_proctoringType_computer");


        if ($record !== null) {
            if ($record) {
                $mform->setDefault('tomaetest_allow', true);
                $extradata = $record->extradata;
                if (isset($extradata["LockComputer"])) {
                    $lockComputer->setSelected($extradata["LockComputer"]);
                }
                if (isset($extradata["VerificationType"])) {
                    $verificationType->setSelected($extradata["VerificationType"]);
                }
                if (isset($extradata["VerificationTiming"])) {
                    $verificationtimings->setSelected($extradata["VerificationTiming"]);
                }
                if (isset($extradata["ShowParticipant"])) {
                    $mform->setDefault('tomaetest_showParticipant', true);
                }
                if (isset($extradata["ScanningModule"]) && $extradata["ScanningModule"] == true) {
                    $mform->setDefault('tomaetest_scan_module', true);
                }
                if (isset($extradata["ReLogin"]) && $extradata["ReLogin"] == true) {
                    $mform->setDefault('tomaetest_requireReLogin', true);
                }
                if (isset($extradata["BlockThirdParty"]) && $extradata["BlockThirdParty"] == true) {
                    $mform->setDefault('tomaetest_blockThirdParty', true);
                }
                if (isset($extradata["IDMatch"]) && $extradata["IDMatch"]) {
                    $mform->setDefault('tomagrade_idmatchontg', $extradata["TETExternalID"]);
                }
                if (isset($extradata["ScanningTime"]) && $extradata["ScanningTime"]) {
                    $mform->setDefault('tomaetest_scanningTime', $extradata["ScanningTime"]);
                }

                if (isset($extradata["TeacherID"]) && !empty($extradata["TeacherID"])) {
                    $user = $DB->get_record('user', array('id' => $extradata["TeacherID"]));
                    $mform->setDefault('tomaetest_realted_user', $user->id);
                }

                if (isset($extradata["ProctoringType"])) {
                    $proctoringType = $extradata["ProctoringType"];
                    foreach ($proctoringType as $proctor) {
                        if ($proctor === "computer_cam_proctoring") {
                            $mform->setDefault('tomaetest_proctoringType_computer', true);
                        }
                        if ($proctor === "monitor_recording_proctoring") {
                            $mform->setDefault('tomaetest_proctoringType_monitor', true);
                        }
                        if ($proctor === "second_cam_proctoring") {
                            $mform->setDefault('tomaetest_proctoringType_second', true);
                        }
                    }
                }
            } else if ($quizId == "") {

                $mform->setDefault('tomaetest_allow', $config->tomaetest_allow);

                $lockComputer->setSelected($config->tomaetest_lockComputer);

                $verificationType->setSelected($config->tomaetest_verificationType);

                $verificationtimings->setSelected($config->tomaetest_verificationTiming);

                $mform->setDefault('tomaetest_showParticipant', $config->tomaetest_showParticipant);

                $mform->setDefault('tomaetest_showParticipant', $config->tomaetest_showParticipant);

                $mform->setDefault('tomaetest_proctoringType_computer', $config->tomaetest_proctoringType_computer);

                $mform->setDefault('tomaetest_proctoringType_monitor', $config->tomaetest_proctoringType_monitor);
                $mform->setDefault('tomaetest_blockThirdParty', $config->tomaetest_blockThirdParty);
                $mform->setDefault('tomaetest_requireReLogin', $config->tomaetest_requireReLogin);
                $mform->setDefault('tomaetest_scanningTime', $config->tomaetest_scanningTime);
            }
        }
    }

    public static function save_settings($quiz) {
        global $DB;
        $record = quizaccess_tomaetest_utils::get_etest_quiz($quiz->id);
        // var_dump($quiz);
        if ($record == false) {
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->id = $DB->insert_record('quizaccess_tomaetest_main', $record);
            $record->extradata = [];
            $externalID = "moodle-" . $quiz->id . "-" . time();
            $record->extradata["TETExternalID"] = $externalID;
        } else {
            $externalID = $record->extradata["TETExternalID"];
            $isClosed = (isset($record->extradata["isClosed"])) ? $record->extradata["isClosed"] : false;
            $isDuring = quizaccess_tomaetest_utils::is_on_going($record->extradata["TETID"]);
            if ($isClosed || $isDuring) {
                return;
            };
        }
        if (isset($quiz->tomaetest_allow) && ($quiz->tomaetest_allow == true)) {

            $lockComputer = isset($quiz->tomaetest_lockComputer) ? $quiz->tomaetest_lockComputer : "no";
            $verificationType = isset($quiz->tomaetest_verificationType) ? $quiz->tomaetest_verificationType : null;
            $verificationTiming = isset($quiz->tomaetest_verificationTiming) ? $quiz->tomaetest_verificationTiming : null;

            if (isset($quiz->tomaetest_showParticipant) && $quiz->tomaetest_showParticipant === "1") {
                $record->extradata["ShowParticipant"] = true;
            }
            $proctoringType = [];

            if (isset($quiz->tomaetest_proctoringType_computer) && $quiz->tomaetest_proctoringType_computer === "1") {
                array_push($proctoringType, "computer_cam_proctoring");
            }
            if (isset($quiz->tomaetest_proctoringType_monitor) && $quiz->tomaetest_proctoringType_monitor === "1") {
                array_push($proctoringType, "monitor_recording_proctoring");
            }

            if (isset($quiz->tomaetest_proctoringType_second) && $quiz->tomaetest_proctoringType_second  === "1") {
                array_push($proctoringType, "second_cam_proctoring");
            }
            $realtedUser = null;
            if (isset($quiz->tomaetest_realted_user) && !empty($quiz->tomaetest_realted_user)){
                $user = $DB->get_record('user', array('id' => $quiz->tomaetest_realted_user));
                if ($user !== false){
                    $record->extradata["TeacherID"] = $user->id;
                }

                $realtedUser = $user;

            }
            if (isset($quiz->tomaetest_scan_module) && !empty($quiz->tomaetest_scan_module)){
                $record->extradata["ScanningModule"] = true;
            }
            if (isset($quiz->tomaetest_scanningTime) && !empty($quiz->tomaetest_scanningTime)){
                $record->extradata["ScanningTime"] = $quiz->tomaetest_scanningTime;
            }
            if (isset($quiz->tomaetest_requireReLogin) && !empty($quiz->tomaetest_requireReLogin)){
                $record->extradata["ReLogin"] = true;
            }
            if (isset($quiz->tomaetest_blockThirdParty) && $quiz->tomaetest_blockThirdParty === "1"){
                $record->extradata["BlockThirdParty"] = true;
            }
            if (isset($quiz->tomaetest_idmatchontg) && !empty($quiz->tomaetest_idmatchontg)){
                $record->extradata["IDMatch"] = true;
                $record->extradata["TETExternalID"] = $quiz->tomaetest_idmatchontg;
            }
            $record->extradata["LockComputer"] = $lockComputer;
            $record->extradata["VerificationType"] = $verificationType;
            $record->extradata["VerificationTiming"] = $verificationTiming;
            $record->extradata["ProctoringType"] = $proctoringType;


            // if ($record->extradata["ScanningModule"]){
            //     $connection = new tet_plugin_tomagrade_connection();
            //     $postdata = [];
            //     $postdata["usersData"] = [[
            //         "Email" => $realtedUser->email,
            //         "FirstName"=>$user->firstName,
            //         "LastName"=>$user->lastName,
            //         "RoleID" => 0,
            //         "TeacherCode" => $user->idnumber,
            //         "IsOTP" => 0,
            //     ]];
            //     $result = $connection->post_request("SaveUsers", json_encode($postdata));
            //     var_dump($result);
            // }


            $result = tomaetest_connection::syncToTomaETestFromDatabase($quiz->id, $record);
            if (!$result["success"]) {
                echo "<script>alert(\"Didn't successfully update TomaETest.\")</script>";
                $DB->delete_records('quizaccess_tomaetest_main', array('quizid' => $quiz->id));
                return false;
            }
            $record->extradata["TETID"] = $result["data"]["ID"];
            $record->extradata["TETSebHeader"] = $result["data"]["Attributes"]["TETSebHeader"];
            $record->extradata["TETExamLink"] = $result["data"]["Attributes"]["TETExamLink"];
            quizaccess_tomaetest_utils::update_record($record);
        } else {
            $DB->delete_records('quizaccess_tomaetest_main', array('quizid' => $quiz->id));
        }
        return true;
    }


    public function get_blocked_message($fromETEST) {
        global $CFG, $USER;
        if ($fromETEST) {
            return '<b>Please make sure you choose the right quiz.</b>';
        } else {
            if (quizaccess_tomaetest_utils::is_on_going($this->extradata["TETID"])) {
                $vixURL = new moodle_url('/mod/quiz/accessrule/tomaetest/openVIX.php', array('quizID' => $this->quiz->id));
                return "<br>
                    <p> Make sure to install TomaETest first by <a target='_blank' href='https://setup.tomaetest.com/TomaETest/setup.html'>clicking here</a>.</p>
                    After installation, please <a target='_blank' href='$vixURL'>Click here </a>to launch TomaETest client";
            } else {
                return "Please come back in 30 minutes before the exam start date";
            }
        }
    }

    public static function get_settings_sql($quizid) {
        return array(
            'tomaetest.extradata AS tomaetest_extradata, tomaetest.id AS tomaetest_innerid',
            'LEFT JOIN {quizaccess_tomaetest_main} tomaetest ON tomaetest.quizid = quiz.id',
            array()
        );
    }
}

function etest_log($item) {
//    echo $item . "<br>----------------------------------------------------------------------------<br>";
}

function attempt_submitted($eventdata) {
    global $DB;
    $eventdata = $eventdata->get_data();
    $quizID = $eventdata["other"]["quizid"];
    $userID = $eventdata["userid"];

    $record = quizaccess_tomaetest_utils::get_etest_quiz($quizID);
    if ($record !== false) {
        $TETID = $record->extradata["TETID"];

        $user = $DB->get_record('user', array('id' => $userID));
        $externalID = quizaccess_tomaetest_utils::get_external_id_for_participant($user);
        $participant = tomaetest_connection::post_request("participant/getByUserName/view", ["UserName" => $externalID]);
        // var_dump($user->username);
        // var_dump($participant);
        if ($participant !== false) {
            $parid = $participant["data"];
            $result = tomaetest_connection::post_request("exam/participant/setSubmissionRequest/insert", ["parID" => $parid, "examID" => $TETID]);
        }
    }
}

function updateDisclaimer($arg) {
    $disclaimer = get_config('quizaccess_tomaetest')->disclaimer;
    $result = tomaetest_connection::post_request("/institution/edit", ['ID' => 1, "Attributes" => [
        'TETInstitutionProctoringDisclaimer' => $disclaimer
    ]]);
}
