<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
header('Content-Type: application/json; charset=utf-8');
$userId = (int)($_SESSION['user']['userid'] ?? 0);
if ($userId < 1) { http_response_code(401); echo json_encode(['error' => 'Ej inloggad']); exit; }
$showAll = $auth->hasRight('orders_show_all');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $order = $taskNotifications->getFocus($userId, $showAll);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo json_encode(['error' => 'Ogiltig session']); exit; }
    $action = $_POST['action'] ?? '';
    if ($action === 'clear') {
        $taskNotifications->clearFocus($userId);
        $order = null;
    } elseif ($action === 'set') {
        $order = $taskNotifications->setFocus($userId, (int)($_POST['orderId'] ?? 0), $showAll);
        if (!$order) { http_response_code(403); echo json_encode(['error' => 'Uppgiften kan inte öppnas']); exit; }
    } else { http_response_code(400); echo json_encode(['error' => 'Ogiltig åtgärd']); exit; }
} else { http_response_code(405); exit; }

echo json_encode(['order' => $order ? ['id' => (int)$order['id'], 'title' => $order['Name'], 'status' => $order['status']] : null], JSON_UNESCAPED_UNICODE);
