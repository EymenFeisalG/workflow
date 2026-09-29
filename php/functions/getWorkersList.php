<?php
define('login_req', true);
require __DIR__ . '/../../global.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['userid'])) {
    echo json_encode(['success' => false, 'error' => 'Ej inloggad']);
    exit;
}

$workers = $main->getWorkersList();

echo json_encode([
    'success' => true,
    'my_id'   => (int)$_SESSION['user']['userid'],
    'workers' => $workers
], JSON_UNESCAPED_UNICODE);
