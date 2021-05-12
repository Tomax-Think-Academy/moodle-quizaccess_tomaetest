<?php

namespace quizaccess_tomaetest\task;

class close_etest_quiz extends \core\task\scheduled_task {
    public function get_name() {
        return 'closeETestQuiz';
    }

    public function execute() {
      global $CFG;
        require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/cron.php");
    }
}
