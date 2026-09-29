<?php
define('login_req', true);
require __DIR__ . '/../../global.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['userid'])) {
    echo json_encode(['success' => false, 'error' => 'Ej inloggad']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
$orders = $main->getMyCreatedOrders($limit);
$stats = $main->getMyCreatedOrdersStats();

echo json_encode([
    'success' => true,
    'stats'   => $stats,
    'orders'  => $orders
], JSON_UNESCAPED_UNICODE);
