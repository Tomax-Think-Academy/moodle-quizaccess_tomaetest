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

require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/connection.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/utils.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/tomagradeConnection.php");

class quizaccess_tomaetest extends mod_quiz\local\access_rule_base
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

    public static function make(mod_quiz\quiz_settings $quizobj, $timenow, $canignoretimelimits) {
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
        global $DB, $USER, $PAGE;

        if (!quizaccess_tomaetest_utils::is_etest_plugin_enabled()) {
            return;
        }
        $config = tomaetest_connection::$config;
        $record = null;
        $quiz = $quizform->get_current();
        $isalldisabled = false;
        $quizid = "";
        if ($quiz !== null) {
            $quizid = $quiz->id;
            $record = quizaccess_tomaetest_utils::get_etest_quiz($quizid);
            if ($record != false) {
                $isduring = quizaccess_tomaetest_utils::is_on_going($record->extradata["TETID"]);
                $isclosed = (isset($record->extradata["isClosed"])) ? $record->extradata["isClosed"] : false;
                $isalldisabled = $isduring || $isclosed;
            }
        }

        $lockedatts = [];
        if ($isalldisabled) {
            $lockedatts = ["disabled"];
            $text = "The TomaETest exam is currently in progress, therefor it cannot be edited.";
            if ($isclosed) {
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
            $lockedatts
        );

        $lockcomputer = $mform->addElement('select',
         'tomaetest_lockComputer', "Lock type",
          quizaccess_tomaetest_utils::$lockcomputerenums, $lockedatts);

        $verificationtimings = $mform->addElement('select',
         'tomaetest_verificationTiming', "Verification timing",
          quizaccess_tomaetest_utils::$verificationtimings, $lockedatts);

        $mform->addElement('checkbox', 'tomaetest_verificationType_camera', 'Verification Types', 'Identity', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_verificationType_manual', 'Manual', '', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_verificationType_room', 'Room', '', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_verificationType_password', 'Password', '', $lockedatts);

        $mform->addElement('checkbox', 'tomaetest_proctoringType_computer', 'Proctoring Types', 'Computer Camera', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_proctoringType_monitor', 'Monitor Recording', '', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_proctoringType_second', 'Second Camera', '', $lockedatts);

        $mform->addElement('checkbox', 'tomaetest_showParticipant', 'Show Participant on screen', ' ', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_blockThirdParty', 'Block Third Party', ' ', $lockedatts);
        $mform->addElement('checkbox', 'tomaetest_requireReLogin', 'Require Re-Login Process', ' ', $lockedatts);

        $mform->addElement('editor', 'tomaetest_proctoringGuidelines', 'Proctoring Guidelines');
        $mform->setType('tomaetest_proctoringGuidelines', PARAM_RAW);

        // JavaScript to enforce constraint
        $PAGE->requires->js_init_code("
            document.addEventListener('DOMContentLoaded', function () {
                let verificationTiming = document.getElementById('id_tomaetest_verificationTiming');
                let cameraCheckbox = document.getElementById('id_tomaetest_verificationType_camera');
                let manualCheckbox = document.getElementById('id_tomaetest_verificationType_manual');
                let roomCheckbox = document.getElementById('id_tomaetest_verificationType_room');
                let passwordCheckbox = document.getElementById('id_tomaetest_verificationType_password');

                function clearTypes(event) {
                    if (event.target.value === 'noVerification') {
                        cameraCheckbox.checked = false;
                        manualCheckbox.checked = false;
                        roomCheckbox.checked = false;
                        passwordCheckbox.checked = false;
                    }
                }
                function updateConstraint() {
                    manualCheckbox.disabled = cameraCheckbox.checked;
                    cameraCheckbox.disabled = manualCheckbox.checked;
                }

                cameraCheckbox.addEventListener('change', updateConstraint);
                manualCheckbox.addEventListener('change', updateConstraint);
                updateConstraint(); // Initialize on page load

                verificationTiming.addEventListener('change', clearTypes);
                clearTypes(); // Initialize on page load
            }, {once: true});
        ");

        if ($config->tomagrade_sync_further === "1") {

            $mform->addElement(
                'checkbox',
                'tomaetest_scan_module',
                "Use TomaETest Scanning module",
                ' ',
                $lockedatts
            );
            // Teachers list.
            $teachers = array();
            $teachersids = array();
            $idinmoodletoemail = array();
            $teachersemailsarray = array();
            $teachersidsarray = array();

            $teachersarr = quizaccess_tomaetest_utils::get_moodle_teachers_by_course($quiz->course);
            $connection = new tet_plugin_tomagrade_connection();

            foreach ($teachersarr as $teacher) {

                $externalid = quizaccess_tomaetest_utils::get_external_id_for_teacher($teacher);
                $teachers[$teacher->id] = $teacher->firstname . " " . $teacher->lastname;
                $teachersids[$teacher->id] = $externalid;
                $teachercodetoid[$externalid] = $teacher->id;
                $idinmoodletoemail[$teacher->id] = $teacher->email;

                array_push($teachersemailsarray, $teacher->email);
                array_push($teachersidsarray, $externalid);
            }

            $identifybyemail = false;
            $postdata = array();
            if ($identifybyemail) {
                $postdata['emails'] = $teachersemailsarray;
            } else {
                $postdata['teacherCodes'] = $teachersidsarray;
            }

            $response = $connection->post_request("GetTeacherIdMoodle", json_encode($postdata));

            $arrayteachersemailsandteachercode = $response['Message'];

            $emailteachercodemap = array();
            $teachercodeexists = array();
            $teachersthatexistsintm = array();

            foreach ($arrayteachersemailsandteachercode as $teacher) {
                $emailteachercodemap[strtolower($teacher['Email'])] = $teacher['ExternalTeacherID'];
                $teachercodeexists[$teacher['ExternalTeacherID']] = true;
            }

            $select = $mform->createElement('select', 'tomaetest_realted_user',
             'Related TomaETest User (Required for scanning module)', '', $lockedatts);

            $select->addOption("No Teacher Selected", -1);

            foreach ($teachers as $value => $label) {
                $teachercode = $teachersids[$value];
                if (($identifybyemail == true && isset($emailteachercodemap[$value]) == false)
                    || ($identifybyemail == false && isset($teachercodeexists[$teachercode]) == false)
                ) {
                    if ($value == strtolower($USER->email)) {
                        $isloggeduserexistsintm = false;
                    }
                    $select->addOption($label, $value);
                } else {
                    $teachersthatexistsintm[$value] = $label;
                    $select->addOption($label, $value);
                }
            }
            $mform->addElement($select);

            $teachersemailsarray = array();
            foreach ($teachersthatexistsintm as $email => $name) {
                array_push($teachersemailsarray, $email);
            }

            $postdata = array();

            if ($identifybyemail) {
                $postdata['emails'] = $teachersemailsarray;
            } else {
                $postdata['teacherCodes'] = $teachersidsarray;
            }
            $response = $connection->post_request("MoodleGetExamsList", json_encode($postdata), true);

            $response = json_decode($response, true);
            $examsbyteachersmap = array();

            $courses = array("0" => 'Irrelevant - regular quiz (without scan)', );
            if (isset($response['Exams'])) {

                foreach ($response['Exams'] as $exam) {
                    $stringforexam = $exam['ExamID'];

                    if (isset($existingexamsmap[$stringforexam]) == false) {
                        if (isset($exam['CourseID'])) {
                            $stringforexam = $stringforexam . " , ";
                            $stringforexam = $stringforexam . $exam['CourseID'];
                        }
                        if (isset($exam['ExamName'])) {
                            $stringforexam = $stringforexam . " , ";
                            $stringforexam = $stringforexam . $exam['ExamName'];
                        }
                        if (isset($exam['ExamDate'])) {
                            $stringforexam = $stringforexam . " , ";
                            try {
                                $date = date_create($exam['ExamDate']);
                                $stringforexam = $stringforexam . date_format($date, " d/m/Y ");
                            } catch (Exception $e) {
                                $stringforexam = $stringforexam . $exam['ExamDate'];
                            }
                        }
                        if (isset($exam['Year'])) {
                            $stringforexam = $stringforexam . " , ";
                            $stringforexam = $stringforexam . $exam['Year'];
                        }
                        if (isset($exam['SimesterID'])) {
                            $stringforexam = $stringforexam . " , simester:";
                            $stringforexam = $stringforexam . $exam['SimesterID'];
                        }
                        if (isset($exam['MoadID'])) {
                            $stringforexam = $stringforexam . " moed:";
                            $stringforexam = $stringforexam . $exam['MoadID'];
                        }
                        $courses[$exam['ExamID']] = $stringforexam;

                        $teacheridimoodle = isset($teachercodetoid[$exam['TeacherCode']]) ? $teachercodetoid[$exam['TeacherCode']] : "";

                        if ($teacheridimoodle != "") {
                            if (isset($examsbyteachersmap[$teacheridimoodle]) == false) {
                                $examsbyteachersmap[$teacheridimoodle] = array();
                            }
                            $examsbyteachersmap[$teacheridimoodle][$exam['ExamID']] = $stringforexam;
                        }
                    }
                }
                $mform->addElement('select', 'tomaetest_idmatchontg', 'ID Match On TomaETest', $courses, $lockedatts);

                $mform->addElement('text', 'tomaetest_scanningTime', 'Student scanning time', $lockedatts);
                $mform->setType('tomaetest_scanningTime', PARAM_INT);
                $mform->addRule('tomaetest_scanningTime', 'Numeric', 'numeric', null, 'client');

                $buildjsteachersmap = "var teachersmap = {}; ";
                foreach ($examsbyteachersmap as $teacher => $value) {
                    $buildjsteachersmap = $buildjsteachersmap . " var examArr = {}; ";
                    foreach ($value as $exam => $examstring) {
                        $examstring = str_replace("'", "", $examstring);
                        $buildjsteachersmap = $buildjsteachersmap . "examArr['$exam'] = '$examstring';";
                    }
                    $buildjsteachersmap = $buildjsteachersmap . " teachersmap['$teacher'] = examArr;";
                }
                $defaultoptionexam = "''";
                if ($record !== null && $record) {
                    $extradata = $record->extradata;
                    if (isset($extradata["IDMatch"]) &&
                     $extradata["IDMatch"] === true &&
                      isset($extradata["TETExternalID"]) && !empty($extradata["TETExternalID"])) {
                        $defaultoptionexam = "'".$extradata["TETExternalID"]."'";
                    }
                }
                echo ("<script>
                    var teachersHashMap = {};
                    var defaultOptionExam = $defaultoptionexam;


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
                        $buildjsteachersmap
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
        // IF tomaetest_allow disabled..
        $mform->disabledIf("tomaetest_verificationType_camera", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationType_manual", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationType_room", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationType_password", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_computer", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_monitor", "tomaetest_allow");
        $mform->disabledIf("tomaetest_proctoringType_second", "tomaetest_allow");
        $mform->disabledIf("tomaetest_showParticipant", "tomaetest_allow");
        $mform->disabledIf("tomaetest_lockComputer", "tomaetest_allow");
        $mform->disabledIf("tomaetest_verificationTiming", "tomaetest_allow");
        $mform->disabledIf("tomaetest_scan_module", "tomaetest_allow");
        $mform->disabledIf("tomaetest_realted_user", "tomaetest_allow");
        $mform->disabledIf("tomaetest_idmatchontg", "tomaetest_allow");
        $mform->disabledIf("tomaetest_blockThirdParty", "tomaetest_allow");
        $mform->disabledIf("tomaetest_requireReLogin", "tomaetest_allow");
        $mform->disabledIf("tomaetest_scanningTime", "tomaetest_allow");
        // If no verification timing, no verification type.
        $mform->hideIf("tomaetest_verificationType_camera", "tomaetest_verificationTiming", "eq", "noVerification");
        $mform->hideIf("tomaetest_verificationType_manual", "tomaetest_verificationTiming", "eq", "noVerification");
        $mform->hideIf("tomaetest_verificationType_room", "tomaetest_verificationTiming", "eq", "noVerification");
        $mform->hideIf("tomaetest_verificationType_password", "tomaetest_verificationTiming", "eq", "noVerification");
        // Identity and Manual can't be chosen together
        $mform->disabledIf("tomaetest_verificationType_camera", "tomaetest_verificationType_manual", 'checked');
        $mform->disabledIf("tomaetest_verificationType_manual", "tomaetest_verificationType_camera", 'checked');
        // Show Participant on  screen only if computer camera is enabled.
        $mform->disabledIf("tomaetest_showParticipant", "tomaetest_proctoringType_computer");

        if ($record !== null) {
            if ($record) {
                $mform->setDefault('tomaetest_allow', true);
                $extradata = $record->extradata;
                if (isset($extradata["LockComputer"])) {
                    $lockcomputer->setSelected($extradata["LockComputer"]);
                }
                if (isset($extradata["VerificationType"])) {
                    $verificationtype = $extradata["VerificationType"];
                    foreach ($verificationtype as $verification) {
                        if ($verification === "camera") {
                            $mform->setDefault('tomaetest_verificationType_camera', true);
                        }
                        if ($verification === "manual") {
                            $mform->setDefault('tomaetest_verificationType_manual', true);
                        }
                        if ($verification === "room") {
                            $mform->setDefault('tomaetest_verificationType_room', true);
                        }
                        if ($verification === "password") {
                            $mform->setDefault('tomaetest_verificationType_password', true);
                        }
                    }
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

                if (isset($extradata["proctoringGuidelines"]) && $extradata["proctoringGuidelines"]) {
                    $mform->setDefault('tomaetest_proctoringGuidelines', $extradata["proctoringGuidelines"]);
                }

                if (isset($extradata["TeacherID"]) && !empty($extradata["TeacherID"])) {
                    $user = $DB->get_record('user', array('id' => $extradata["TeacherID"]));
                    $mform->setDefault('tomaetest_realted_user', $user->id);
                }

                if (isset($extradata["ProctoringType"])) {
                    $proctoringtype = $extradata["ProctoringType"];
                    foreach ($proctoringtype as $proctor) {
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
            }
            else if ($quizid == "") {

                $mform->setDefault('tomaetest_allow', $config->tomaetest_allow);

                $lockcomputer->setSelected($config->tomaetest_lockComputer);

                $mform->setDefault('tomaetest_verificationType_camera', $config->tomaetest_verificationType_camera);
                $mform->setDefault('tomaetest_verificationType_manual', $config->tomaetest_verificationType_manual);
                $mform->setDefault('tomaetest_verificationType_room', $config->tomaetest_verificationType_room);
                $mform->setDefault('tomaetest_verificationType_password', $config->tomaetest_verificationType_password);

                $verificationtimings->setSelected($config->tomaetest_verificationTiming);

                $mform->setDefault('tomaetest_showParticipant', $config->tomaetest_showParticipant);

                $mform->setDefault('tomaetest_showParticipant', $config->tomaetest_showParticipant);

                $mform->setDefault('tomaetest_proctoringType_computer', $config->tomaetest_proctoringType_computer);
                $mform->setDefault('tomaetest_proctoringType_monitor', $config->tomaetest_proctoringType_monitor);
                $mform->setDefault('tomaetest_proctoringType_second', $config->tomaetest_proctoringType_second);

                $mform->setDefault('tomaetest_blockThirdParty', $config->tomaetest_blockThirdParty);
                $mform->setDefault('tomaetest_requireReLogin', $config->tomaetest_requireReLogin);
                $mform->setDefault('tomaetest_scanningTime', $config->tomaetest_scanningTime);
            }
        }
    }

    public static function save_settings($quiz) {
        global $DB;
        $record = quizaccess_tomaetest_utils::get_etest_quiz($quiz->id);
        if ($record == false) {
            $record = new stdClass();
            $record->quizid = $quiz->id;
            $record->id = $DB->insert_record('quizaccess_tomaetest_main', $record);
            $record->extradata = [];
            $externalid = "moodle-" . $quiz->id . "-" . time();
            $record->extradata["TETExternalID"] = $externalid;
        } else {
            $externalid = $record->extradata["TETExternalID"];
            $isclosed = (isset($record->extradata["isClosed"])) ? $record->extradata["isClosed"] : false;
            $isduring = quizaccess_tomaetest_utils::is_on_going($record->extradata["TETID"]);
            if ($isclosed || $isduring) {
                return;
            };
        }
        if (isset($quiz->tomaetest_allow) && ($quiz->tomaetest_allow == true)) {

            $lockcomputer = isset($quiz->tomaetest_lockComputer) ? $quiz->tomaetest_lockComputer : "no";
            $verificationtype = [];
            if (isset($quiz->tomaetest_verificationType_camera) && $quiz->tomaetest_verificationType_camera === "1") {
                array_push($verificationtype, "camera");
            }
            if (isset($quiz->tomaetest_verificationType_manual) && $quiz->tomaetest_verificationType_manual === "1") {
                array_push($verificationtype, "manual");
            }
            if (isset($quiz->tomaetest_verificationType_room) && $quiz->tomaetest_verificationType_room === "1") {
                array_push($verificationtype, "room");
            }
            if (isset($quiz->tomaetest_verificationType_password) && $quiz->tomaetest_verificationType_password === "1") {
                array_push($verificationtype, "password");
            }

            $verificationtiming = isset($quiz->tomaetest_verificationTiming) ? $quiz->tomaetest_verificationTiming : null;
            if (empty($verificationtype) || $verificationtiming === "noVerification") {
                $verificationtype = ["no"];
            }

            if (isset($quiz->tomaetest_showParticipant) && $quiz->tomaetest_showParticipant === "1") {
                $record->extradata["ShowParticipant"] = true;
            }
            $proctoringtype = [];

            if (isset($quiz->tomaetest_proctoringType_computer) && $quiz->tomaetest_proctoringType_computer === "1") {
                array_push($proctoringtype, "computer_cam_proctoring");
            }
            if (isset($quiz->tomaetest_proctoringType_monitor) && $quiz->tomaetest_proctoringType_monitor === "1") {
                array_push($proctoringtype, "monitor_recording_proctoring");
            }

            if (isset($quiz->tomaetest_proctoringType_second) && $quiz->tomaetest_proctoringType_second === "1") {
                array_push($proctoringtype, "second_cam_proctoring");
            }
            if (empty($proctoringtype)) {
                $proctoringtype = ["no"];
            }
            $relateduser = null;
            if (isset($quiz->tomaetest_realted_user) && !empty($quiz->tomaetest_realted_user)) {
                $user = $DB->get_record('user', array('id' => $quiz->tomaetest_realted_user));
                if ($user !== false) {
                    $record->extradata["TeacherID"] = $user->id;
                }

                $relateduser = $user;

            }
            if (isset($quiz->tomaetest_scan_module) && !empty($quiz->tomaetest_scan_module)) {
                $record->extradata["ScanningModule"] = true;
            }
            if (isset($quiz->tomaetest_scanningTime) && !empty($quiz->tomaetest_scanningTime)) {
                $record->extradata["ScanningTime"] = $quiz->tomaetest_scanningTime;
            }
            if (isset($quiz->tomaetest_requireReLogin) && !empty($quiz->tomaetest_requireReLogin)) {
                $record->extradata["ReLogin"] = true;
            }
            if (isset($quiz->tomaetest_blockThirdParty) && $quiz->tomaetest_blockThirdParty === "1") {
                $record->extradata["BlockThirdParty"] = true;
            }
            if (isset($quiz->tomaetest_idmatchontg) && !empty($quiz->tomaetest_idmatchontg)) {
                $record->extradata["IDMatch"] = true;
                $record->extradata["TETExternalID"] = $quiz->tomaetest_idmatchontg;
            }
            $record->extradata["LockComputer"] = $lockcomputer;
            $record->extradata["VerificationType"] = $verificationtype;
            $record->extradata["VerificationTiming"] = $verificationtiming;
            $record->extradata["ProctoringType"] = $proctoringtype;

            if (isset($quiz->tomaetest_proctoringGuidelines) && !empty($quiz->tomaetest_proctoringGuidelines)) {
                $record->extradata["proctoringGuidelines"] = $quiz->tomaetest_proctoringGuidelines;
            }

            $syncresult = tomaetest_connection::sync_to_toma_etest_from_database($quiz->id, $record);
            if (!$syncresult["success"]) {
                $errmsg = "Didn't successfully update TomaETest.";
                if (!empty($syncresult["missingparams"])) {
                    $errmsg = $errmsg . "\\nMandatory Settings are missing: " . implode(", ", $syncresult["missingparams"]);
                }
                echo "<script>alert(\"$errmsg\")</script>";
                $DB->delete_records('quizaccess_tomaetest_main', array('quizid' => $quiz->id));
                throw new moodle_exception('syncerror', 'quizaccess_tomaetest', '', '', json_encode($syncresult));
            }
            $tetid = $syncresult["data"]["ID"];
           // $proctoringguidelines = $quiz->tomaetest_proctoringGuidelines['text'];
            $guidelinesresult = tomaetest_connection::set_proctoring_guidelines($tetid, $proctoringguidelines);
            if (!$guidelinesresult["success"]) {
                $errmsg = "Didn't successfully update TomaETest.";
                echo "<script>alert(\"$errmsg\")</script>";
                $DB->delete_records('quizaccess_tomaetest_main', array('quizid' => $quiz->id));
                throw new moodle_exception('syncerror', 'quizaccess_tomaetest', '', '', json_encode($guidelinesresult));
            }
            $record->extradata["TETID"] = $tetid;
            $record->extradata["TETSebHeader"] = $syncresult["data"]["Attributes"]["TETSebHeader"];
            $record->extradata["TETExamLink"] = $syncresult["data"]["Attributes"]["TETExamLink"];
            quizaccess_tomaetest_utils::update_record($record);
        } else {
            $DB->delete_records('quizaccess_tomaetest_main', array('quizid' => $quiz->id));
        }
        return true;
    }


    public function get_blocked_message($frometest) {
        global $CFG, $USER;
        if ($frometest) {
            return '<b>Please make sure you choose the right quiz.</b>';
        } else {
            if (quizaccess_tomaetest_utils::is_on_going($this->extradata["TETID"])) {
                $vixurl = new moodle_url('/mod/quiz/accessrule/tomaetest/openVIX.php', array('quizID' => $this->quiz->id));
                return "<br>
                    <p> Make sure to install TomaETest first by <a target='_blank' href='https://setup.tomaetest.com/TomaETest/setup.html'>clicking here</a>.</p>
                    After installation, please <a target='_blank' href='$vixurl'>Click here </a>to launch TomaETest client";
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
}

function attempt_submitted($eventdata) {
    global $DB;
    $eventdata = $eventdata->get_data();
    $quizid = $eventdata["other"]["quizid"];
    $userid = $eventdata["userid"];

    $record = quizaccess_tomaetest_utils::get_etest_quiz($quizid);
    if ($record !== false) {
        $tetid = $record->extradata["TETID"];

        $user = $DB->get_record('user', array('id' => $userid));
        $externalid = quizaccess_tomaetest_utils::get_external_id_for_participant($user);
        $participant = tomaetest_connection::post_request("participant/getByUserName/view", ["UserName" => $externalid]);
        if ($participant !== false) {
            $parid = $participant["data"];
            $result = tomaetest_connection::post_request("exam/participant/setSubmissionRequest/insert",
             ["parID" => $parid, "examID" => $tetid]);
        }
    }
}

function update_disclaimer($arg) {
    $disclaimer = get_config('quizaccess_tomaetest')->disclaimer;
    $result = tomaetest_connection::post_request("/institution/edit", ['ID' => 1, "Attributes" => [
        'TETInstitutionProctoringDisclaimer' => $disclaimer
    ]]);
}
