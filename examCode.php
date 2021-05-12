<?php

require_once(dirname(dirname(__FILE__)) . '../../../../config.php');
require_login();
require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once($CFG->dirroot . "/mod/quiz/accessrule/tomaetest/rule.php");
    $code = $_GET["code"];
    $result = quizaccess_tomaetest_utils::get_quiz_by_examCode($code);
    if ($result === false){
        echo json_encode(["result"=>false]);
    }else{
        $location = new moodle_url('/mod/quiz/report/tomaetest/sso.php', array('id' => $result->quizid));
        echo json_encode(["result" => true,"location"=> "".$location]);
    }
} else {

    $PAGE->set_context(context_system::instance());
    $PAGE->set_pagelayout('admin');
    $PAGE->set_title("Exam Code");
    $PAGE->set_heading("Exam Code");
    $url = new moodle_url('/mod/quiz/accessrule/tomaetest/examCode.php');
    $PAGE->set_url($url);

    echo $OUTPUT->header();
    echo "<p>Please enter Exam Code <input id='examCode' type='text'/></p>";
    echo "<button onclick='clickedExamCode()'>Enter Code</button>";
    echo "
<script>
function clickedExamCode(){
    var code = document.getElementById('examCode').value
    var xmlHttp = new XMLHttpRequest();
    var link = '$url?code=' + code;
    xmlHttp.open( \"POST\", link, false ); // false for synchronous request
    xmlHttp.send( null );
    console.log(xmlHttp.responseText);
    var result;
    try{
        result = JSON.parse(xmlHttp.responseText)
    }catch(e){
        console.log(e);
        alert('There was something wrong');
        return;
    }
    console.log(result.result);
    if (result.result == false){
        alert('The Exam Code is not correct');
        return;
    }
    location.href = result.location;
}
</script>";
    echo $OUTPUT->footer();
}
