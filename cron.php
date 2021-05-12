<?php

defined('MOODLE_INTERNAL') || die();
global $DB, $CFG;

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/connection.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/utils.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");

function logAndPrint($msg, &$log = null)
{
    echo $msg;
    echo "\n";

    $log .=  "\n" . $msg;
}

if (quizaccess_tomaetest_utils::isETestPluginEnabled()) {
    logAndPrint("ETest plugin enabled!");
    logAndPrint("Starting - checkAllExamsIfClosed");
    checkAllExamsIfClosed();
    logAndPrint("Done - checkAllExamsIfClosed");
    logAndPrint("Start - closeAllExams");
    closeAllExams();
    logAndPrint("Done - closeAllExams");


}

function closeAllExams()
{
    global $DB;

    $delta = 30;
    if (isset(tomaetest_connection::$config->tomaetest_closeExamDelta)){
        $delta = intval(tomaetest_connection::$config->tomaetest_closeExamDelta);
    }
    $dbType = $DB->get_dbfamily();
    if ($dbType === "postgres") {
        $endingQuizes = $DB->get_records_sql("SELECT quizid,
            max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0)) AS finalDate 
            from (
            SELECT quiz.id as quizid,
                        MAX(quo.timeclose) AS userclose,
                        MAX(qgo.timeclose) AS groupclose,
                        MAX(quiz.timeclose) as timeclose
                FROM {quizaccess_tomaetest_main} as tomaetest_main
                left join {quiz} quiz on tomaetest_main.quizid = quiz.id
            LEFT JOIN {quiz_overrides} quo on quiz.id = quo.quiz
            LEFT JOIN {groups_members} gm ON gm.userid = quo.userid
            LEFT JOIN {quiz_overrides} qgo on quiz.id = qgo.quiz AND qgo.groupid = gm.groupid
                WHERE tomaetest_main.extradata not like ?
                group by quiz.id
            ) as v
            group by v.quizid
            having extract(epoch FROM NOW() - INTERVAL '$delta' MINUTE) > max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0)) and not max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0)) = 0", ["%\"isClosed\":true%"]);
    } else {
        $endingQuizes = $DB->get_records_sql("SELECT
    quizid,
     max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0)) AS finalDate
  FROM (
      SELECT quiz.id as quizid,
            MAX(quo.timeclose) AS userclose,
            MAX(qgo.timeclose) AS groupclose,
            MAX(quiz.timeclose) as timeclose
       FROM {quizaccess_tomaetest_main} as tomaetest_main
       left join {quiz} quiz on tomaetest_main.quizid = quiz.id
  LEFT JOIN {quiz_overrides} quo on quiz.id = quo.quiz
  LEFT JOIN {groups_members} gm ON gm.userid = quo.userid
  LEFT JOIN {quiz_overrides} qgo on quiz.id = qgo.quiz AND qgo.groupid = gm.groupid
      WHERE tomaetest_main.extradata not like ?
   GROUP BY quiz.id) as v
       group by v.quizid
       having UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL $delta minute)) > finalDate and not finalDate = 0", ["%\"isClosed\":true%"]);
    }

    foreach ($endingQuizes as $tempQuiz) {
        if ($tempQuiz->quizid === null)
            continue;
        $quiz = quizaccess_tomaetest_utils::get_etest_quiz($tempQuiz->quizid);
        $id = $quiz->extradata["TETID"];
        if ($quiz->extradata["isClosed"] === true){
            return;
        }
        logAndPrint("Trying to close TomaETest exam $id (quizid " . $tempQuiz->quizid . ")");
        $result = tomaetest_connection::post_request("exam/CloseExam/edit", [], "?ID=$id&force=true");
        if ($result["success"] === true) {
            logAndPrint("Closed TomaETest exam $id");
            $quiz->extradata["isClosed"] = true;
            quizaccess_tomaetest_utils::update_record($quiz);
        } else {
            logAndPrint("Failed to close TomaETest exam $id, response:");
            logAndPrint(json_encode($result));
        }
    }
}

function checkAllExamsIfClosed()
{
    global $DB;
    $checkQuizes = $DB->get_records_sql("SELECT  * FROM {quizaccess_tomaetest_main} where extradata not like ?", ["%\"isClosed\":true%"]);
    $amount = count($checkQuizes);
    logAndPrint("checking $amount quizes");
    foreach ($checkQuizes as $etestQuiz) {
        if (isset($etestQuiz->extradata)) {
            $etestQuiz->extradata = json_decode($etestQuiz->extradata, true);
        } else {
            $etestQuiz->extradata = [];
        }
        $TETID = $etestQuiz->extradata["TETID"];
        if ($etestQuiz->extradata["isClosed"] === true) {
            return;
        }

        $etest = tomaetest_connection::getExamSpecificInformation($TETID);
        if (isset($etest["data"]["Entity"]["Attributes"]["TETExamWFStatus"]["key"]) && $etest["data"]["Entity"]["Attributes"]["TETExamWFStatus"]["key"] !== "imported") {
            logAndPrint("CLOSING.. $TETID");
            $etestQuiz->extradata["isClosed"] = true;
            quizaccess_tomaetest_utils::update_record($etestQuiz);
        }
    }
}
