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
defined('MOODLE_INTERNAL') || die();
global $DB, $CFG;

require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/connection.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/utils.php");
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");

function log_and_print($msg, &$log = null) {
    echo $msg;
    echo "\n";

    $log .= "\n" . $msg;
}

if (quizaccess_tomaetest_utils::isETestPluginEnabled()) {
    log_and_print("ETest plugin enabled!");
    log_and_print("Starting - checkAllExamsIfClosed");
    checkAllExamsIfClosed();
    log_and_print("Done - checkAllExamsIfClosed");
    log_and_print("Start - close_all_exams");
    close_all_exams();
    log_and_print("Done - close_all_exams");


}

function close_all_exams() {
    global $DB;

    $delta = 30;
    if (isset(tomaetest_connection::$config->tomaetest_closeExamDelta)) {
        $delta = intval(tomaetest_connection::$config->tomaetest_closeExamDelta);
    }
    $dbtype = $DB->get_dbfamily();
    if ($dbtype === "postgres") {
        $endingquizes = $DB->get_records_sql("SELECT quizid,
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
            having extract(epoch FROM NOW() - INTERVAL '$delta' MINUTE) > max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0))
             and not max(COALESCE(v.userclose, v.groupclose, v.timeclose, 0)) = 0", ["%\"isClosed\":true%"]);
    } else {
        $endingquizes = $DB->get_records_sql("SELECT
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

    foreach ($endingquizes as $tempquiz) {
        if ($tempquiz->quizid === null) {
            continue;
        }
        $quiz = quizaccess_tomaetest_utils::get_etest_quiz($tempquiz->quizid);
        $id = $quiz->extradata["TETID"];
        if ($quiz->extradata["isClosed"] === true) {
            return;
        }
        log_and_print("Trying to close TomaETest exam $id (quizid " . $tempquiz->quizid . ")");
        $result = tomaetest_connection::post_request("exam/CloseExam/edit", [], "?ID=$id&force=true");
        if ($result["success"] === true) {
            log_and_print("Closed TomaETest exam $id");
            $quiz->extradata["isClosed"] = true;
            quizaccess_tomaetest_utils::update_record($quiz);
        } else {
            log_and_print("Failed to close TomaETest exam $id, response:");
            log_and_print(json_encode($result));
        }
    }
}

function checkAllExamsIfClosed() {
    global $DB;
    $checkquizes = $DB->get_records_sql("SELECT  * FROM {quizaccess_tomaetest_main} where extradata not like ?",
     ["%\"isClosed\":true%"]);
    $amount = count($checkquizes);
    log_and_print("checking $amount quizes");
    foreach ($checkquizes as $etestquiz) {
        if (isset($etestquiz->extradata)) {
            $etestquiz->extradata = json_decode($etestquiz->extradata, true);
        } else {
            $etestquiz->extradata = [];
        }
        $tetid = $etestquiz->extradata["TETID"];
        if ($etestquiz->extradata["isClosed"] === true) {
            return;
        }

        $etest = tomaetest_connection::getExamSpecificInformation($tetid);
        if (isset($etest["data"]["Entity"]["Attributes"]["TETExamWFStatus"]["key"]) &&
         $etest["data"]["Entity"]["Attributes"]["TETExamWFStatus"]["key"] !== "imported") {
            log_and_print("CLOSING.. $tetid");
            $etestquiz->extradata["isClosed"] = true;
            quizaccess_tomaetest_utils::update_record($etestquiz);
        }
    }
}
