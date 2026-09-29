<?php
define('login_req', true);
require __DIR__ . '/../../global.php';
require __DIR__ . '/../classes/taskCorrection.class.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$actorId = (int)($_SESSION['user']['userid'] ?? 0);
if ($actorId < 1 || !hash_equals((string)$_SESSION['csrf_token'], (string)($_POST['csrf'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Ogiltig session.']);
    exit;
}
$id = filter_var($_POST['orderId'] ?? null, FILTER_VALIDATE_INT);
$correction = new TaskCorrection(database::$mysql);
$order = $correction->read($id ? (int)$id : 0, $actorId, $auth->hasRight('changeOrder'));
if (!$order) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Uppgiften kan inte korrigeras.']);
    exit;
}
echo json_encode(['success' => true, 'order' => $order], JSON_UNESCAPED_UNICODE);
