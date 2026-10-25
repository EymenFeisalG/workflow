<?php


use Mailer\Mailer;


require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/php/classes/centralenIdentity.class.php';
if (CentralenIdentity::enabled()) {
    if (str_starts_with(CentralenIdentity::url('WORKFLOW_URL'), 'https://') && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) {
        header('Location: '.CentralenIdentity::url('WORKFLOW_URL').'/',true,303);exit;
    }
    session_name('workflow_session');
    ini_set('session.use_strict_mode', '1');
}
session_set_cookie_params([
    'path' => CentralenIdentity::enabled() ? ((parse_url(CentralenIdentity::url('WORKFLOW_URL'), PHP_URL_PATH) ?: '') . '/') : '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
if (!isset($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
require 'php/classes/taskNotifications.class.php';


$db = new database($mysql_settings);
$main = new main($email_settings);
$auth = new auth($email_settings);
$taskNotifications = new TaskNotifications(database::$mysql);

$centralenIdentity = null;
if (CentralenIdentity::enabled()) {
    try {
        $centralenIdentity = CentralenIdentity::connect(database::$mysql);
        $sharedUser = $centralenIdentity->validateSession($_SESSION);
        if ($sharedUser) $_SESSION['user'] = $sharedUser;
        else unset($_SESSION['user'], $_SESSION['rights'], $_SESSION['centralen_identity']);
    } catch (Throwable $error) {
        unset($_SESSION['user'], $_SESSION['rights'], $_SESSION['centralen_identity']);
        error_log('Shared identity unavailable: ' . get_class($error));
        http_response_code(503);exit('Inloggningen kunde inte kontrolleras. Försök igen.');
    }
}
$auth->restoreRememberedLogin();
if (isset($_SESSION['user'])) $auth->initRights();

if (!in_array(basename($_SERVER['SCRIPT_NAME'] ?? ''), ['logout.php','centralen-login.php','centralen-shell-api.php'],true) && $auth->Maintenance())
{
    $url = $_SERVER["REQUEST_URI"]; 
    $pos = strrpos($url, "Maintenance.php"); 
    
    if($pos === false) {
        $prefix = file_exists('Maintenance.php') ? '' : '../';
        header('location: ' . $prefix . 'Maintenance.php');
        exit;
    }
}



/*

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
*/


