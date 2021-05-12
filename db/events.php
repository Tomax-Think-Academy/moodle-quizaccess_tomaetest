<?php

$observers = array(
    array(
        'eventname'   => 'mod_quiz\event\attempt_submitted',
        'callback'    => 'attempt_submitted',
        'priority'    => 200,
        'internal'    => false,
        'includefile' => '/mod/quiz/accessrule/tomaetest/rule.php'
    )
);
