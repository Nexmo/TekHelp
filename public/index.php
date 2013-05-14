<?php 
date_default_timezone_set('America/Chicago');
$schedule = require_once '../data/schedule.php';
require_once '../library/help.php';

//check if this is a valid request
if(empty($_REQUEST['msisdn'])){
    //no from, just ignore
    return;
}

//Nexmo credentials may be optionally defined elsewhere
defined('NEXMO_KEY') || getenv('NEXMO_KEY') AND define('NEXMO_KEY', getenv('NEXMO_KEY'));
defined('NEXMO_SECRET') || getenv('NEXMO_SECRET') AND define('NEXMO_SECRET', getenv('NEXMO_SECRET'));
defined('NEXMO_FROM') || getenv('NEXMO_FROM') AND define('NEXMO_FROM', getenv('NEXMO_FROM'));

//hijack the session for our devious purposes, make the incoming number the session
session_id(md5($_REQUEST['msisdn']));
session_start();

$help = new Help(NEXMO_KEY, NEXMO_SECRET, NEXMO_FROM, $schedule, $_SESSION);
try{
    $help->process($_REQUEST['msisdn'], $_REQUEST['text']);
} catch (Exception $e) {
    error_log($e->getMessage());
}

//update session
$_SESSION = $help->getSession();
session_write_close();