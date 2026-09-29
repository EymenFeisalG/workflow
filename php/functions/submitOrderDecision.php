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
$canDecide = $order && in_array($action, ['attest', 'deny'], true) && (
    ($action === 'deny' && $order['status'] === 'pending' && ((int)$order['creator'] === $actorId || $auth->hasRight('orders_show_all'))) ||
    ($action === 'attest' && in_array($order['status'], ['ongoing', 'rework'], true) && (int)$order['worker_name_id'] === $actorId)
);
if (!$canDecide) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Du får inte ändra uppgiftens status.']);
    exit;
}
try {
    if (!is_string($_POST['comment'] ?? '')) throw new InvalidArgumentException('Ogiltig kommentar.');
    $main->submitOrderDecision((int)$id, $_POST['comment'], $action);
    echo json_encode(['success' => true]);
} catch (InvalidArgumentException $error) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (DomainException $error) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('Order decision failed: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Beslutet kunde inte sparas. Försök igen.'], JSON_UNESCAPED_UNICODE);
}
