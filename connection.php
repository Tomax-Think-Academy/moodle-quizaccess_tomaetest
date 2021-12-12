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
class tomaetest_connection
{
    public static $config;


    public static function sso($quizid, $userid, $parid = null) {
        $record = quizaccess_tomaetest_utils::get_etest_quiz($quizid);
        $id = $record->extradata["TETID"];
        $externalid = quizaccess_tomaetest_utils::get_teacher_id($userid);
        $examid = $record->extradata["TETExternalID"];
        $data = ["userExternalID" => $externalid, "examExternalID" => $examid];
        if ($parid !== null) {
            $data["externalLocation"] = "exams/$id/proctoring/$parid";
        }
        $result = static::post_request("auth/login/SafeGenerateToken", $data);
        if ($result["success"] == true) {
            return $result["data"]["url"];
        }
        return false;
    }

    public static function sso_integrity_management($userid) {
        $externalid = quizaccess_tomaetest_utils::get_teacher_id($userid);
        $data = ["userExternalID" => $externalid, "externalLocation" => "air"];
        $result = static::post_request("auth/login/SafeGenerateToken", $data);
        if ($result["success"] == true) {
            return $result["data"]["url"];
        }
        return false;
    }

    public static function get_information($id) {
        return static::get_participants_list($id, 1);
    }

    public static function get_exam_specific_information($id) {
        return $result = static::post_request(
            "exam/view?ID=$id",
            []
        );
        return $result;
    }

    static function get_participants_list($id, $amount = false) {

        $result = static::post_request(
            "exam/participant/view",
            [
                "ID" => $id,
                "filter" => [
                    "firstRow" => 0,
                    "amount" => ($amount === false) ? 200 : 1,
                    "filterItem" => "allPars",
                    "filterString" => ""
                ]
            ]
        );
        return $result;
    }

    public static function sync_to_toma_etest_from_database($quizid, $tetquiz = null) {
        global $DB;
        if ($tetquiz === null) {
            $tetquiz = quizaccess_tomaetest_utils::get_etest_quiz($quizid);
        }
        $quiz = quizaccess_tomaetest_utils::get_quiz($quizid);
        $cmid = quizaccess_tomaetest_utils::get_cmid($quizid);
        $cm = quizaccess_tomaetest_utils::get_coursemodule($cmid);
        $course = quizaccess_tomaetest_utils::get_course_information($quiz->course);

        $externalid = $tetquiz->extradata["TETExternalID"];
        $lockcomputer = $tetquiz->extradata["LockComputer"];
        $verificationyype = $tetquiz->extradata["VerificationType"];
        $verificationTiming = $tetquiz->extradata["VerificationTiming"];
        $proctoringtype = $tetquiz->extradata["ProctoringType"];
        $scanningModule = (isset($tetquiz->extradata["ScanningModule"])) ? $tetquiz->extradata["ScanningModule"] : false;
        $blockThirdParty = (isset($tetquiz->extradata["BlockThirdParty"])) ? $tetquiz->extradata["BlockThirdParty"] : false;
        $showParticipantOnScreen = (isset($tetquiz->extradata["ShowParticipant"])) ? $tetquiz->extradata["ShowParticipant"] : false;
        $reLogin = (isset($tetquiz->extradata["ReLogin"])) ? $tetquiz->extradata["ReLogin"] : false;
        $scanningTime = (isset($tetquiz->extradata["ScanningTime"])) ? $tetquiz->extradata["ScanningTime"] : 0;

        date_default_timezone_set('UTC');
        if (isset($quiz->timeopen) && $quiz->timeopen != 0) {
            $date = date("d/m/Y", $quiz->timeopen);
            $time = date("H:i", $quiz->timeopen);
        } else {
            $date = date("d/m/Y", strtotime("+1 month")); // default = in a month.
            $time = "00:00";
        }

        $quizName = $quiz->name;
        $courseName = $course->fullname;
        // $teacherID = "204433";
        $teacherID = null;
        if (isset($tetquiz->extradata["TeacherID"])) {
            $user = $DB->get_record("user", array("id" => $tetquiz->extradata["TeacherID"]));
            $teacherID = quizaccess_tomaetest_utils::get_external_id_for_teacher($user);
        }

        $cmURL = new moodle_url('/mod/quiz/view.php', array('id' => $cmid));
        $thirdParty = [
            "QuizURL" => $cmURL->__toString(),
            "PassToTG" => $scanningModule
        ];

        $result = tomaetest_connection::syncToTomaETest($quiz->id, $quizName, $date, $courseName, $externalid, $teacherID, $time, $lockcomputer, $verificationyype, $verificationTiming, $proctoringtype, $showParticipantOnScreen, $thirdParty, $scanningModule, $blockThirdParty, $reLogin, $scanningTime);
        return $result;
    }


    static function syncToTomaETest($quizid, $name, $date, $course, $externalid, $TeacherExternalID, $startTime, $lockcomputer, $verificationyype, $verificationTiming, $proctoringtype, $showParticipantOnScreen, $exam3rdPartyConfig, $scanningModule, $blockThirdParty, $reLogin, $scanningTime) {
        $duration = 1000000;
        $data = [
            "bankExamDTO" => null,
            "examParameter" => [
                "TETExamDate" => $date,
                "TETExamName" => $name,
                "TETExamCourse" => $course,
                "TETExamExternalID" => $externalid,
                "TETExamType" => "open",
                "TETTeacherExternalID" => $TeacherExternalID,
                "Year" => -1,
                "Moed" => -1,
                "Semester" => -1,
                "TETExamQuestionNumberingType" => "1",
                "TETExamQuestionAnswerNumberingType" => "a",
                "TETExam3rdPartyConfig" => $exam3rdPartyConfig,
                "TETExamRecordingParticipantView" => $showParticipantOnScreen,
                "TETExamUseUnlockParticipantPassword" => $reLogin,
                "TETExamDuration" => $duration,
                "TETOverallExamOverTime" => 0,
                "TETExamLockComputer" => ["key" => $lockcomputer],
                "TETExamStartTime" => $startTime,
                "TETExamAuthorizationType" => ["key" => "saml"],
                "TETExamPasswordTrustNeeded" => ['key' => $verificationTiming],
                "TETExamEndDelay" => $scanningTime,
                "TETExamProctoringType" => array_map(function($proctoringtype){
                    return ["key" => $proctoringtype];
                }, $proctoringtype)

            ]
        ];
        // $proctoringtype = str_replace("\"", "'", json_encode($proctoringtype));
        // $guidelineValues = [
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamDuration", "number", $duration),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETOverallExamOverTime", "number", 0),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamLockComputer", "list", $lockcomputer),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamStartTime", "time", $startTime),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamAuthorizationType", "list", 'saml'),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamPasswordTrustNeeded", "list", $verificationTiming),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamEndDelay", "number", $scanningTime)
        // ];

        // $data["guidelineValue"] = $guidelineValues;
        // $data["extraFieldValue"] = [
        //     [
        //         "objectExtraFieldDefinition" => [
        //             "name" => "TETExamProctoringType",
        //             "fieldType" => "multipleSelect",
        //         ],
        //         "value" => $proctoringtype
        //     ]
        // ];
        if (isset($verificationyype) && $verificationyype != null) {
            // array_push($data["extraFieldValue"],
            //     [
            //         "objectExtraFieldDefinition" => [
            //             "name" => "TETExamVerificationType",
            //             "fieldType" => "multipleSelect",
            //         ],
            //         "value" => str_replace("\"", "'", json_encode([$verificationyype]))
            //     ]);
            $data['examParameter']["TETExamVerificationType"] = [["key" => $verificationyype]];
        }
        if ($blockThirdParty) {
            $alertedAPPS = [];
            $deniedAPPS = [];

            $keys = array_filter(array_keys((array)tomaetest_connection::$config), function ($item) {
                if (strpos($item, "tomaetest_appstate_") > -1) {
                    return true;
                }
                return false;
            });

            foreach ($keys as $key) {
                $name = str_replace("tomaetest_appstate_", "", $key);
                //alert
                if (tomaetest_connection::$config->{$key} === "1") {
                    array_push($alertedAPPS, $name);
                    //deny
                } else if (tomaetest_connection::$config->{$key} === "2") {
                    array_push($deniedAPPS, $name);
                }
            }

            // $tETExamAlertedApps = str_replace("\"", "'", json_encode($alertedAPPS));
            // $tETExamDeniedApps = str_replace("\"", "'", json_encode($deniedAPPS));
            $data['examParameter']["TETExamAlertedApps"] = $alertedAPPS;
            $data['examParameter']["TETExamDeniedApps"] = $deniedAPPS;
            // array_push($data["extraFieldValue"], [
            //     "objectExtraFieldDefinition" => [
            //         "name" => "TETExamAlertedApps",
            //         "fieldType" => "multipleSelect",
            //     ],
            //     "value" => $tETExamAlertedApps
            // ], [
            //     "objectExtraFieldDefinition" => [
            //         "name" => "TETExamDeniedApps",
            //         "fieldType" => "multipleSelect",
            //     ],
            //     "value" => $tETExamDeniedApps
            // ]);
        } else {
            // array_push($data["extraFieldValue"], [
            //     "objectExtraFieldDefinition" => [
            //         "name" => "TETExamAlertedApps",
            //         "fieldType" => "multipleSelect",
            //     ],
            //     "value" => []
            // ], [
            //     "objectExtraFieldDefinition" => [
            //         "name" => "TETExamDeniedApps",
            //         "fieldType" => "multipleSelect",
            //     ],
            //     "value" => []
            // ]);
        }
        $data["examParticipants"] = [];
        $students = quizaccess_tomaetest_utils::get_quiz_students($quizid);
        foreach ($students as $student) {
            array_push(
                $data["examParticipants"],
                [
                    "Active" => true,
                    "UserName" => $student->TETParticipantIdentity,
                    "Attributes" => $student
                ]
            );
        }
        $users = quizaccess_tomaetest_utils::get_quiz_teachers($quizid);
        $data["Users"] = $users;
        if ($scanningModule === true) {
            $data["bankExamDTO"] = array(
                array(
                    "elements" => [[
                        "TETQuestionType" => "open",
                        "TETElementID" => 1,
                        "TETQuestionScore" => 100,
                        "TETQuestionTitle" => "Scanning question",
                        "TETElementPath" => "1",
                        "TETElementType" => "question",
                        "TETElementPin" => 1,
                        "TETExamQuestionResponseType" => str_replace("\"", "'", json_encode(["scan"])),
                        "TETElementOriginalOrder" => 1,
                        "TETContinuousNumbering" => 0,
                    ]]
                )
            );
        }

        return static::post_request("exam/tsimport/insert", $data);
    }

    static function post_request($method, $postdata, $parameters = "") {
        $config = static::$config;
        etest_log("================== post $method to :$config->domain ====================");
        $url = "https://$config->domain.tomaetest.com/TomaETest/api/dashboard/WS/$method$parameters";

        etest_log("url : " . $url);
        etest_log("postdata : " . json_encode($postdata));

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($postdata),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => [
                "cache-control: no-cache",
                "x-apikey: " . $config->apikey,
                "x-userid: " . $config->userid
            ]
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        etest_log("response : " . $response);

        etest_log("================== end post $method to $config->domain ====================");

        // if ($dontDecode) {
        //     return $response;
        // }

        return json_decode($response, true);
    }
}
tomaetest_connection::$config = get_config('quizaccess_tomaetest');
