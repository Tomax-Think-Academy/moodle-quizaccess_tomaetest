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

class quizaccess_tomaetest_utils
{


    const IDENTIFIER_BY_EMAIL = 0;
    const IDENTIFIER_BY_ID = 1;
    const IDENTIFIER_BY_USERNAME = 2;
    const IDENTIFIER_BY_ORBITID = 3;
    const IDENTIFIER_BY_HUJIID = 4;



    public static $lockcomputerenums = [
        "no" => "Without",
        "semi" => "Soft Lock",
        "full" => "Hard Lock"
    ];

    public static $applicationstate = [
        0 => "Ignore",
        1 => "Alert",
        2 => "Deny access"
    ];

    public static $verificationtypes = [
        "camera" => "Camera",
        "manual" => "Manual",
        "password" => "Password",
        "no" => "Without"
    ];

    public static $verificationtimings = [
        "noVerification" => "Without",
        "beforeExam" => "Before Exam",
        "atExam" => "During Exam"
    ];

    public static function check_access($tetsebheader, $extradata) {
        if (
            ((array_key_exists('HTTP_USER_AGENT', $_SERVER)
                && substr($_SERVER["HTTP_USER_AGENT"], 0, strlen($tetsebheader)) === $tetsebheader))
            ||
            ((array_key_exists('HTTP_USER_AGENT', $_SERVER) &&
                $extradata["LockComputer"] === "no" && strpos($_SERVER["HTTP_USER_AGENT"], "-tomaetest") > -1))
        ) {
            return true;
        }
        return false;
    }

    public static function get_external_id_for_teacher($user) {

        global $DB;
        $output = null;
        if (tomaetest_connection::$config->tomaetest_teacherID == self::IDENTIFIER_BY_EMAIL) {
            $output = $user->email;
        } else if (tomaetest_connection::$config->tomaetest_teacherID == self::IDENTIFIER_BY_ID) {
            $output = $user->idnumber;

        } else if (tomaetest_connection::$config->tomaetest_teacherID == self::IDENTIFIER_BY_HUJIID) {
            $output = $user->idnumber;

            $hudjiddata = $DB->get_field_sql("SELECT hujiid FROM huji.userdata WHERE tz=?", array('tz' => $user->idnumber));

            if ($hudjiddata !== false ) {
                $output = $hudjiddata;
            }
        }
        return $output;

    }
    public static function get_external_id_for_participant($user) {

        global $DB;
        if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_EMAIL) {
            $output = $user->email;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_ID) {
            $output = $user->idnumber;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_USERNAME) {
            $output = $user->username;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_ORBITID) {
            $output = $user->idnumber;

            $orbitiddata = $DB->get_records_sql("select o.orbitid from {import_interface_user} o JOIN {user} m ON o.username=m.username where m.id = ?", array($user->id));

            if (count($orbitiddata) > 0) {

                $output = reset($orbitiddata)->orbitid;
            }
        }
        return $output;
    }

    public static function get_quiz($quizid) {
        global $DB;
        $record = $DB->get_record('quiz', array('id' => $quizid));
        return $record;
    }

    public static function get_coursemodule($cmid) {
        global $DB;
        $record = $DB->get_record('course_modules', array('id' => $cmid));
        return $record;
    }

    public static function get_course($cmid) {
        global $DB;
        $record = $DB->get_record_sql(
            "select * from {course_modules}
            join {course} on {course_modules}.course = {course}.id
            where {course_modules}.id = ?",
            [$cmid]
        );
        return $record;
    }

    public static function get_quiz_by_exam_code($code) {
        global $DB;
        $record = $DB->get_record_sql(
            "select * from {quizaccess_tomaetest_main} where extradata like ?",
            ["%\"TETExamLink\":\"$code\"%"]
        );
        return $record;
    }

    public static function is_from_etest() {

        if (
            array_key_exists('HTTP_USER_AGENT', $_SERVER)
            && strpos($_SERVER["HTTP_USER_AGENT"], '-tomaetest')
        ) {
            return true;
        }
        return false;
    }

    public static function get_teacher_id($userid) {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userid));
        return self::get_external_id_for_teacher($user);
    }

    public static function create_guide_line_value($name, $type, $value) {
        return [
            "guidelineParameter" => [
                "type" => $type,
                "variable" => $name,
            ],
            "value" => $value
        ];
    }

    public static function update_record($record) {
        global $DB;
        $record->extradata = json_encode($record->extradata);
        return $DB->update_record('quizaccess_tomaetest_main', $record);
    }

    public static function get_etest_quiz($quizID) {
        global $DB;
        try {
            $record = $DB->get_record('quizaccess_tomaetest_main', array('quizid' => $quizID));
        } catch (Exception $e) {
            return false;
        }
        if ($record != false) {
            if (isset($record->extradata)) {
                $record->extradata = json_decode($record->extradata, true);
            } else {
                $record->extradata = [];
            }
        }
        return $record;
    }

    static function isOnGoing($tetID) {
        $information = tomaetest_connection::getInformation($tetID);
        return (strtotime($information["data"]["dynamicAttributes"]["ExamPublishTime"]) < time());
    }

    public static function get_course_information($courseid) {
        global $DB;
        return $DB->get_record('course', array('id' => $courseid));
    }

    public static function getCMID($quizID) {
        global $DB;
        $record = $DB->get_record_sql("SELECT {course_modules}.ID from {course_modules}
        join {modules} on module = {modules}.id
        where {modules}.name = 'quiz' and {course_modules}.instance = ?", [$quizID]);

        return ($record != false) ? $record->id : null;
    }

    public static function getQuizStudents($quizID) {
        global $DB;

        $CMID = static::getCMID($quizID);
        $context = context_module::instance($CMID);

        $students = get_users_by_capability($context, "mod/quiz:attempt");
        $students = static::MoodleParticipantsToTETParticipants($students);

        return $students;
    }

    public static function MoodleParticipantsToTETParticipants($moodleArray) {
        return
            array_map(function ($student) {
                $newStudent = new stdClass();
                $newStudent->TETParticipantFirstName = $student->firstname;
                $newStudent->TETParticipantLastName = $student->lastname;
                $newStudent->TETParticipantPhone = $student->phone1;
                $newStudent->TETParticipantIdentity = quizaccess_tomaetest_utils::get_external_id_for_participant($student);
                return $newStudent;
            }, $moodleArray);
    }

    public static function isETestPluginEnabled() {
        return (tomaetest_connection::$config->allow === "1");
    }


    public static function getQuizTeachers($quizID) {
        global $DB;

        $users = static::getMoodleTeachers($quizID);
        $users = static::MoodleUsersToTETUsers($users);
        return $users;
    }

    public static function MoodleUsersToTETUsers($moodleArray) {
        return array_map(function ($user) {
            $newUser = new stdClass();

            $newUser->Role = "ROLE_MOODLE";
            $newUser->TETExternalID = static::get_external_id_for_teacher($user);
            $newUser->UserName = static::get_external_id_for_teacher($user);
            $newUser->TETUserLastName = $user->lastname;
            $newUser->TETUserEmail = $user->email;
            $newUser->TETUserFirstName = $user->firstname;
            $newUser->TETUserPhone = $user->phone1;

            return $newUser;
        }, $moodleArray);
    }

    public static function getMoodleTeachers($quizID = null, $userid = null) {
        global $DB;
        if ($quizID === null) {
            $systemcontext = context_system::instance();
            $teachers = [];
            if (has_capability("mod/quizaccess_tomaetest:viewTomaETestMonitor", $systemcontext, $userid)) {
                array_push($teachers, $DB->get_record('user', array("id" => $userid)));
            }
            return $teachers;
        }
        $CMID = static::getCMID($quizID);
        $context = context_module::instance($CMID);
        $teachers = get_users_by_capability($context, "mod/quizaccess_tomaetest:viewTomaETestMonitor");
        if ($userid !== null) {
            $teachers = array_filter($teachers, function ($user) use ($userid) {
                return $user->id === $userid;
            });
        }

        return $teachers;
    }

    public static function getMoodleTeachersByCourse($courseid) {
        $context = context_course::instance($courseid);
       return get_users_by_capability($context, "mod/quizaccess_tomaetest:viewTomaETestMonitor");
    }

    public static function getMoodleAllowedIntegrityManagement($userid = null) {
        global $DB;
        $systemcontext = context_system::instance();
        $teachers = [];
        if (has_capability("mod/quizaccess_tomaetest:viewTomaETestAIR", $systemcontext, $userid)) {
            array_push($teachers, $DB->get_record('user', array("id" => $userid)));
        }
        return $teachers;
    }

    public static function createSystemUser($id) {
        global $DB;

        $user = $DB->get_record("user", array("id" => $id));
        $user = static::MoodleUsersToTETUsers([$user])[0];

        $TETUserResponse = tomaetest_connection::post_request("user/getByExternalID/view", ["ExternalID" => $user->TETExternalID]);

        if (!$TETUserResponse["success"]) {
            $sendingObject = [
                "UserName" => $user->UserName,
                "Attributes" => $user
            ];
            unset($sendingObject["Attributes"]->UserName);
            unset($sendingObject["Attributes"]->Role);
            $TETUserResponse = tomaetest_connection::post_request("user/insert", $sendingObject);
            if (!$TETUserResponse['success']) {
                return "Duplicate ExternalID/UserName - " . $sendingObject["UserName"] . " Please check for duplicate data.";
            }
            $TETUserID = $TETUserResponse["data"];
        } else {
            $TETUserID = $TETUserResponse["data"]["Entity"];
        }
        $TETRoleResponse = tomaetest_connection::post_request("role/getByName/view", ["Name" => "ROLE_MOODLE"]);

        //Need to sync at least one exam to create ROLE_MOODLE...
        if (!$TETRoleResponse["success"]) {
            return "Please try and sync one quiz with a user attached to it first.";
        }
        $RoleID = $TETRoleResponse["data"]["Entity"]["ID"];
        $responseConnect = tomaetest_connection::post_request("user/edit?ID=" . $TETUserID, [
            "ID" => $TETUserID,
            "Attributes" => new stdClass(),
            "Roles" => ["Delete" => [], "Insert" => [$RoleID]]
        ]);
        return true;
    }
}
