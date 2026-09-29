<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
header('Content-Type: application/json; charset=utf-8');
if (!$auth->hasRight('admin')) { http_response_code(403); echo json_encode(['error' => 'FORBIDDEN']); exit; }
require '../../php/classes/admin.class.php';
$admin = new admin($email_settings);
echo json_encode($admin->getAllUsers(), JSON_UNESCAPED_UNICODE);
