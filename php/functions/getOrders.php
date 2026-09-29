<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
if (($_GET['live'] ?? '') === '1') {
    $dir = $_GET['dir'] ?? 'ongoing';
    ob_start();
    $main->listOrders($dir);
    $orders = ob_get_clean();
    ob_start();
    $main->getOrderNav();
    $nav = ob_get_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['orders' => $orders, 'nav' => $nav], JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
$main->listOrders($_GET['dir'] ?? 'ongoing', (int)($_GET['orderId'] ?? 0));
