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
class tomaetest_connection
{
    static $config;


    static function sso($quizID, $userID, $parID = null)
    {
        $record = quizaccess_tomaetest_utils::get_etest_quiz($quizID);
        $id = $record->extradata["TETID"];
        $externalID = quizaccess_tomaetest_utils::get_teacher_id($userID);
        $examid = $record->extradata["TETExternalID"];
        $data = ["userExternalID" => $externalID, "examExternalID" => $examid];
        if ($parID !== null) {
            $data["externalLocation"] = "exams/$id/proctoring/$parID";
        }
        $result = static::post_request("auth/login/SafeGenerateToken", $data);
        if ($result["success"] == true) {
            return $result["data"]["url"];
        }
        return false;
    }

    static function ssoIntegrityManagement($userID)
    {
        $externalID = quizaccess_tomaetest_utils::get_teacher_id($userID);
        $data = ["userExternalID" => $externalID, "externalLocation" => "air"];
        $result = static::post_request("auth/login/SafeGenerateToken", $data);
        if ($result["success"] == true) {
            return $result["data"]["url"];
        }
        return false;
    }

    static function getInformation($id)
    {
        return static::getParticipantsList($id, 1);
    }

    static function getExamSpecificInformation($id)
    {
        return $result = static::post_request(
            "exam/view?ID=$id",
            []
        );
        return $result;
    }

    static function getParticipantsList($id, $amount = false)
    {

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

    static function syncToTomaETestFromDatabase($quizID, $TETQuiz = null)
    {
        global $DB;
        if ($TETQuiz === null) {
            $TETQuiz = quizaccess_tomaetest_utils::get_etest_quiz($quizID);
        }
        $quiz = quizaccess_tomaetest_utils::get_quiz($quizID);
        $CMID = quizaccess_tomaetest_utils::get_cmid($quizID);
        $cm = quizaccess_tomaetest_utils::get_coursemodule($CMID);
        $course = quizaccess_tomaetest_utils::get_course_information($quiz->course);

        $externalID = $TETQuiz->extradata["TETExternalID"];
        $lockComputer = $TETQuiz->extradata["LockComputer"];
        $verificationType = $TETQuiz->extradata["VerificationType"];
        $verificationTiming = $TETQuiz->extradata["VerificationTiming"];
        $proctoringType = $TETQuiz->extradata["ProctoringType"];
        $scanningModule = (isset($TETQuiz->extradata["ScanningModule"])) ? $TETQuiz->extradata["ScanningModule"] : false;
        $blockThirdParty = (isset($TETQuiz->extradata["BlockThirdParty"])) ? $TETQuiz->extradata["BlockThirdParty"] : false;
        $showParticipantOnScreen = (isset($TETQuiz->extradata["ShowParticipant"])) ? $TETQuiz->extradata["ShowParticipant"] : false;
        $reLogin = (isset($TETQuiz->extradata["ReLogin"])) ? $TETQuiz->extradata["ReLogin"] : false;
        $scanningTime = (isset($TETQuiz->extradata["ScanningTime"])) ? $TETQuiz->extradata["ScanningTime"] : 0;

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
        if (isset($TETQuiz->extradata["TeacherID"])) {
            $user = $DB->get_record("user", array("id" => $TETQuiz->extradata["TeacherID"]));
            $teacherID = quizaccess_tomaetest_utils::get_external_id_for_teacher($user);
        }

        $cmURL = new moodle_url('/mod/quiz/view.php', array('id' => $CMID));
        $thirdParty = [
            "QuizURL" => $cmURL->__toString(),
            "PassToTG" => $scanningModule
        ];

        $result = tomaetest_connection::syncToTomaETest($quiz->id, $quizName, $date, $courseName, $externalID, $teacherID, $time, $lockComputer, $verificationType, $verificationTiming, $proctoringType, $showParticipantOnScreen, $thirdParty, $scanningModule, $blockThirdParty, $reLogin, $scanningTime);
        return $result;
    }


    static function syncToTomaETest($quizid, $name, $date, $course, $externalID, $TeacherExternalID, $startTime, $lockComputer, $verificationType, $verificationTiming, $proctoringType, $showParticipantOnScreen, $exam3rdPartyConfig, $scanningModule, $blockThirdParty, $reLogin, $scanningTime)
    {
        $duration = 1000000;
        $data = [
            "bankExamDTO" => null,
            "examParameter" => [
                "TETExamDate" => $date,
                "TETExamName" => $name,
                "TETExamCourse" => $course,
                "TETExamExternalID" => $externalID,
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
                "TETExamLockComputer" => ["key" => $lockComputer],
                "TETExamStartTime" => $startTime,
                "TETExamAuthorizationType" => ["key" => "saml"],
                "TETExamPasswordTrustNeeded" => ['key' => $verificationTiming],
                "TETExamEndDelay" => $scanningTime,
                "TETExamProctoringType" => array_map(function($proctoringType){
                    return ["key" => $proctoringType];
                }, $proctoringType)

            ]
        ];
        // $proctoringType = str_replace("\"", "'", json_encode($proctoringType));
        // $guidelineValues = [
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamDuration", "number", $duration),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETOverallExamOverTime", "number", 0),
        //     quizaccess_tomaetest_utils::create_guide_line_value("TETExamLockComputer", "list", $lockComputer),
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
        //         "value" => $proctoringType
        //     ]
        // ];
        if (isset($verificationType) && $verificationType != null) {
            // array_push($data["extraFieldValue"],
            //     [
            //         "objectExtraFieldDefinition" => [
            //             "name" => "TETExamVerificationType",
            //             "fieldType" => "multipleSelect",
            //         ],
            //         "value" => str_replace("\"", "'", json_encode([$verificationType]))
            //     ]);
            $data['examParameter']["TETExamVerificationType"] = [["key" => $verificationType]];
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
        $students = quizaccess_tomaetest_utils::getQuizStudents($quizid);
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
        $users = quizaccess_tomaetest_utils::getQuizTeachers($quizid);
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

    static function post_request($method, $postdata, $parameters = "")
    {
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
