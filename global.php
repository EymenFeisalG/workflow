<?php


use Mailer\Mailer;


session_start();
// page settings
require 'settings.php';
// PHP mailer init
require 'resources/PHPMailer/src/Exception.php';
require 'resources/PHPMailer/src/PHPMailer.php';
require 'resources/PHPMailer/src/SMTP.php';

// Workflow classes init
require 'php/classes/database.class.php';
require 'php/classes/mailer.class.php';
require 'php/classes/main.class.php';   
require 'php/classes/auth.class.php';


$db = new database($mysql_settings);
$main = new main($email_settings);
$auth = new auth($email_settings);

if($auth->Maintenance())
{
    $url = $_SERVER["REQUEST_URI"]; 
    $pos = strrpos($url, "Maintenance.php"); 
    
    if($pos == false) {
        header('location: Maintenance.php');
    }


}



/*

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/


