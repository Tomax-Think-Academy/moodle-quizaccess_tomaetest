<?php


$capabilities = array(
    'mod/quizaccess_tomaetest:viewTomaETestMonitor' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => array(
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'          => CAP_ALLOW
        )
    ),
    'mod/quizaccess_tomaetest:viewTomaETestAIR' => array(
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => array(
        )
    ),

)
?>