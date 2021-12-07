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
class tet_plugin_tomagrade_connection
{

    const STATUS_NOT_STARTED = 0;
    const STATUS_WAITING = 1;
    const STATUS_ONGOING = 2;
    const STATUS_FINISHED = 3;
    const STATUS_QUEUED = 4;
    const STATUS_FAILED = 1000;
    const STATUS_FAILED_FILETYPE = 1001;
    const STATUS_FAILED_UNKNOWN = 1002;
    const STATUS_FAILED_OPTOUT = 1003;
    const STATUS_FAILED_CONNECTION = 1004;

    const REPORT_STATS = 0;
    const REPORT_LINKS = 1;
    const REPORT_SOURCES = 2;
    const REPORT_DOCX = 3;
    const REPORT_HTML = 4;
    const REPORT_MATCHES = 5;
    const REPORT_PS = 6;
    //const REPORT_RESERVED = 7;
    const REPORT_PDFHTML = 8;
    const REPORT_PDFREPORT = 9;
    const REPORT_HIGHLIGHT = 25;
    const REPORT_GETSOURCE = 26;

    const SUBMIT_OK = 0;
    const SUBMIT_UNSUPPORTED = 1;
    const SUBMIT_OPTOUT = 2;

    protected $config;
    protected $username = -1;
    protected $nondisclosure = false;

    function __construct()
    {
        $this->config = get_config('quizaccess_tomaetest');
    }

    function post_request($method, $postdata, $dontDecode = false, $parameters = "")
    {
        global $CFG;
        $params = null;
        $config = $this->config;
        // tomagrade_log("================== post $method to $config->domain ====================");
        // if (isset($CFG->TomaToken) && isset($CFG->TomaUser)) {
        if ($method !== "DoLogin") {
            $params = "TOKEN/" . $config->tg_userid;
        }
        // }
        //$params = (isset($params)) ? implode('/',$params) : "";
        $url = "https://$config->domain.tomagrade.com/TomaGrade/Server/php/WS.php/$method/" . $params . $parameters;
        //$url = "https://tomagradedev.tomagrade.com/TomaGrade/Server/php/DoLogout.php/9";
        // tomagrade_log("url : " . $url);
        // tomagrade_log("postdata : " . json_encode($postdata));

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER => array("cache-control: no-cache", "x-apikey: $config->tg_apikey", "x-userid: $config->tg_userid")
            //CURLOPT_CAPATH => "/etc/apache2/ssl",
            //CURLOPT_CAINFO => "/etc/apache2/ssl/certificate.ca"

        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);



        //echo("response : ".json_encode($response));
        // tomagrade_log("response : " . json_encode($response));
        // tomagrade_log("================== end post $method to $config->tomagrade_server ====================");

        if ($dontDecode) {
            return $response;
        }

        return json_decode($response, true);
    }

    public function teacherLogin($id)
    {
        global $USER;
        $config = $this->config;
        $type = "TeacherID";
        if (tomaetest_connection::$config->tomaetest_teacherID == quizaccess_tomaetest_utils::IDENTIFIER_BY_EMAIL) {
            $type ="Email";
        }
        $information = quizaccess_tomaetest_utils::getExternalIDForTeacher($USER);
        $postdata = "{\"$type\":\"$information\"}";

        // exit;//echo("POSRTDATA:".$postdata."<br>");
        $response_post = $this->post_request("DoLogin", $postdata);
        return $response_post;
    }

}
