<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
header('Content-Type: application/json; charset=utf-8');
if (!$auth->hasRight('admin')) { http_response_code(403); echo json_encode(['error' => 'FORBIDDEN']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'INVALID_REQUEST']); exit; }
require '../../php/classes/admin.class.php';
$rights = $_POST['rights'] ?? [];
if (!is_array($rights) || !(new admin($email_settings))->saveRights((int)($_POST['user_id'] ?? 0), $rights)) {
    http_response_code(400);
    echo json_encode(['error' => 'INVALID_RIGHTS']);
    exit;
}
echo json_encode(['status' => 'OK']);
