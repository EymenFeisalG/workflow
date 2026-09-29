<?php
// Kompatibilitet för äldre klienter. Nya klienter öppnar korrigeringen direkt.
define('login_req', true);
require __DIR__ . '/../../global.php';
header('Content-Type: application/json; charset=utf-8');
$id = filter_var($_POST['orderId'] ?? null, FILTER_VALIDATE_INT);
if (!$id || !isset($_SESSION['user']['userid'])) {
    http_response_code(400);
    echo json_encode(['success' => false]);
    exit;
}
require __DIR__ . '/../classes/taskCorrection.class.php';
$editor = new TaskCorrection(database::$mysql);
if (!$editor->read((int)$id, (int)$_SESSION['user']['userid'], $auth->hasRight('changeOrder'))) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}
$_SESSION['changeOrder'] = (int)$id;
echo json_encode(['success' => true]);
