<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
header('Content-Type: application/json; charset=utf-8');
$userId = (int)($_SESSION['user']['userid'] ?? 0);
if ($userId < 1) { http_response_code(401); echo json_encode(['error' => 'Ej inloggad']); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode($taskNotifications->listFor($userId, $auth->hasRight('orders_show_all')), JSON_UNESCAPED_UNICODE);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'Ogiltig session']); exit; }
$all = ($_POST['all'] ?? '') === '1';
$ids = $_POST['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$taskNotifications->markRead($userId, array_slice($ids, 0, 50), $all);
echo json_encode($taskNotifications->listFor($userId, $auth->hasRight('orders_show_all')), JSON_UNESCAPED_UNICODE);
