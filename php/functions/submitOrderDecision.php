<?php
define('login_req', true);
require __DIR__ . '/../../global.php';

header('Content-Type: application/json; charset=utf-8');
$actorId = (int)($_SESSION['user']['userid'] ?? 0);
$id = filter_var($_POST['orderid'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($_POST['action'] ?? '');
if ($actorId < 1 || !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf'] ?? '')) || !$id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ogiltig session.']);
    exit;
}
$order = $taskNotifications->order((int)$id);
$canDecide = $order && (
    ($action === 'deny' && $order['status'] === 'pending' && ((int)$order['creator'] === $actorId || $auth->hasRight('orders_show_all'))) ||
    ($action !== 'deny' && in_array($order['status'], ['ongoing', 'rework'], true) && (int)$order['worker_name_id'] === $actorId)
);
if (!$canDecide) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Du får inte ändra uppgiftens status.']);
    exit;
}
$main->submitOrderDecision((int)$id, (string)($_POST['comment'] ?? ''), $action);
echo json_encode(['success' => true]);
