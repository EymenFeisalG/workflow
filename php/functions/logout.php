<?php
require '../../global.php';

$auth->forgetRememberedLogin();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $options = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $options['path'],
        'domain' => $options['domain'],
        'secure' => $options['secure'],
        'httponly' => $options['httponly'],
        'samesite' => $options['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('location: ../../index.php');
exit;
