<?php


class quizaccess_tomaetest_utils
{
    

    const IDENTIFIER_BY_EMAIL = 0;
    const IDENTIFIER_BY_ID = 1;
    const IDENTIFIER_BY_USERNAME = 2;
    const IDENTIFIER_BY_ORBITID = 3;
    const IDENTIFIER_BY_HUJIID = 4;



    static $LockComputerEnums = [
        "no" => "Without",
        "semi" => "Soft Lock",
        "full" => "Hard Lock"
    ];

    static $APPLICATION_STATE = [
        0 => "Ignore",
        1 => "Alert",
        2 => "Deny access"
    ];

    static $verificationTypes = [
        "camera" => "Camera",
        "manual" => "Manual",
        "password" => "Password",
        "no" => "Without"
    ];

    static $verificationTimings = [
        "noVerification" => "Without",
        "beforeExam" => "Before Exam",
        "atExam" => "During Exam"
    ];

    public static function check_access($TETSebHeader, $extraData)
    {
        if (
            ((array_key_exists('HTTP_USER_AGENT', $_SERVER)
                && substr($_SERVER["HTTP_USER_AGENT"], 0, strlen($TETSebHeader)) === $TETSebHeader))
            ||
            ((array_key_exists('HTTP_USER_AGENT', $_SERVER) &&
                $extraData["LockComputer"] === "no" && strpos($_SERVER["HTTP_USER_AGENT"], "-tomaetest") > -1))
        ) {
            return true;
        }
        return false;
    }

    public static function getExternalIDForTeacher($user){

        global $DB;
        $output = null;
        if (tomaetest_connection::$config->tomaetest_teacherID == self::IDENTIFIER_BY_EMAIL) {
            $output = $user->email;
        } else if (tomaetest_connection::$config->tomaetest_teacherID== self::IDENTIFIER_BY_ID) {
            $output = $user->idnumber;

            // if (isset($config->tomagrade_zeroCompleteTeacher)) {
            //     if (is_numeric($config->tomagrade_zeroCompleteTeacher)) {
            //         $zeros = intval($config->tomagrade_zeroCompleteTeacher);
            //         if ($zeros > 0) {
            //             $newObject->data = plagiarism_plugin_tomagrade::completeZeroes($user->idnumber . "", $zeros);
            //         }
            //     }
            // }

            // return $newObject;
        } else if (tomaetest_connection::$config->tomaetest_teacherID == self::IDENTIFIER_BY_HUJIID) {
            $output = $user->idnumber;

            $hudjidData = $DB->get_field_sql("SELECT hujiid FROM huji.userdata WHERE tz=?", array('tz' => $user->idnumber));

            if ($hudjidData !== false ) {
                $output = $hudjidData;
            }
        }
        return $output;

    }
    public static function getExternalIDForParticipant($user){

        global $DB;
        if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_EMAIL) {
            $output = $user->email;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_ID) {
            $output = $user->idnumber;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_USERNAME) {
            $output = $user->username;
        } else if (tomaetest_connection::$config->tomaetest_studentID == self::IDENTIFIER_BY_ORBITID) {
            $output = $user->idnumber;

            $orbitidData = $DB->get_records_sql("select o.orbitid from {import_interface_user} o JOIN {user} m ON o.username=m.username where m.id = ?", array($user->id));

            if (count($orbitidData) > 0) {

                $output = reset($orbitidData)->orbitid;
            }
        }
        return $output;
    }

    public static function get_quiz($quizid)
    {
        global $DB;
        $record = $DB->get_record('quiz', array('id' => $quizid));
        return $record;
    }

    public static function get_coursemodule($cmid)
    {
        global $DB;
        $record = $DB->get_record('course_modules', array('id' => $cmid));
        return $record;
    }

    public static function get_course($cmid)
    {
        global $DB;
        $record = $DB->get_record_sql(
            "select * from {course_modules} 
            join {course} on {course_modules}.course = {course}.id
            where {course_modules}.id = ?",
            [$cmid]
        );
        return $record;
    }

    public static function get_quiz_by_examCode($code)
    {
        global $DB;
        $record = $DB->get_record_sql(
            "select * from {quizaccess_tomaetest_main} where extradata like ?",
            ["%\"TETExamLink\":\"$code\"%"]
        );
        return $record;
    }

    public static function is_from_etest()
    {

        if (
            array_key_exists('HTTP_USER_AGENT', $_SERVER)
            && strpos($_SERVER["HTTP_USER_AGENT"], '-tomaetest')
        ) {
            return true;
        }
        return false;
    }

    public static function getTeacherID($userID)
    {
        global $DB;
        $user = $DB->get_record('user', array('id' => $userID));
        return quizaccess_tomaetest_utils::getExternalIDForTeacher($user);
    }

    public static function createGuideLineValue($name, $type, $value)
    {
        return [
            "guidelineParameter" => [
                "type" => $type,
                "variable" => $name,
            ],
            "value" => $value
        ];
    }

    public static function update_record($record)
    {
        global $DB;
        $record->extradata = json_encode($record->extradata);
        return $DB->update_record('quizaccess_tomaetest_main', $record);
    }

    public static function get_etest_quiz($quizID)
    {
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

    static function isOnGoing($tetID)
    {
        $information = tomaetest_connection::getInformation($tetID);
        return (strtotime($information["data"]["dynamicAttributes"]["ExamPublishTime"]) < time());
    }

    public static function get_course_information($courseid)
    {
        global $DB;
        return $DB->get_record('course', array('id' => $courseid));
    }

    public static function getCMID($quizID)
    {
        global $DB;
        $record = $DB->get_record_sql("SELECT {course_modules}.ID from {course_modules} 
        join {modules} on module = {modules}.id
        where {modules}.name = 'quiz' and {course_modules}.instance = ?", [$quizID]);

        return ($record != false) ? $record->id : null;
    }

    public static function getQuizStudents($quizID)
    {
        global $DB;

        $CMID = static::getCMID($quizID);
        $context = context_module::instance($CMID);

        $students = get_users_by_capability($context, "mod/quiz:attempt");
        $students = static::MoodleParticipantsToTETParticipants($students);

        return $students;
    }

    public static function MoodleParticipantsToTETParticipants($moodleArray)
    {
        return
            array_map(function ($student) {
                $newStudent = new stdClass();
                $newStudent->TETParticipantFirstName = $student->firstname;
                $newStudent->TETParticipantLastName = $student->lastname;
                $newStudent->TETParticipantPhone = $student->phone1;
                $newStudent->TETParticipantIdentity = quizaccess_tomaetest_utils::getExternalIDForParticipant($student);
                return $newStudent;
            }, $moodleArray);
    }

    public static function isETestPluginEnabled()
    {
        return (tomaetest_connection::$config->allow === "1");
    }


    public static function getQuizTeachers($quizID)
    {
        global $DB;

        $users = static::getMoodleTeachers($quizID);
        $users = static::MoodleUsersToTETUsers($users);
        return $users;
    }

    public static function MoodleUsersToTETUsers($moodleArray)
    {
        return array_map(function ($user) {
            $newUser = new stdClass();

            $newUser->Role = "ROLE_MOODLE";
            $newUser->TETExternalID = static::getExternalIDForTeacher($user);
            $newUser->UserName = static::getExternalIDForTeacher($user);
            $newUser->TETUserLastName = $user->lastname;
            $newUser->TETUserEmail = $user->email;
            $newUser->TETUserFirstName = $user->firstname;
            $newUser->TETUserPhone = $user->phone1;

            return $newUser;
        }, $moodleArray);
    }

    public static function getMoodleTeachers($quizID = null, $userID = null)
    {
        global $DB;
        if ($quizID === null) {
            $systemcontext = context_system::instance();
            $teachers = [];
            if (has_capability("mod/quizaccess_tomaetest:viewTomaETestMonitor", $systemcontext, $userID)) {
                array_push($teachers, $DB->get_record('user', array("id" => $userID)));
            }
            return $teachers;
        }
        $CMID = static::getCMID($quizID);
        $context = context_module::instance($CMID);
        $teachers = get_users_by_capability($context, "mod/quizaccess_tomaetest:viewTomaETestMonitor");
        if ($userID !== null) {
            $teachers = array_filter($teachers, function ($user) use ($userID) {
                return $user->id === $userID;
            });
        }

        return $teachers;
    }

    public static function getMoodleTeachersByCourse($courseid){
        $context = context_course::instance($courseid);
       return get_users_by_capability($context, "mod/quizaccess_tomaetest:viewTomaETestMonitor");
    }

    public static function getMoodleAllowedIntegrityManagement($userID = null)
    {
        global $DB;
        $systemcontext = context_system::instance();
        $teachers = [];
        if (has_capability("mod/quizaccess_tomaetest:viewTomaETestAIR", $systemcontext, $userID)) {
            array_push($teachers, $DB->get_record('user', array("id" => $userID)));
        }
        return $teachers;
    }

    public static function createSystemUser($id)
    {
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
