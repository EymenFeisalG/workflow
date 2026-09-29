<?php
define('login_req', true);
require '../../global.php';
$auth->userLoginCheck();
$main->listOrders($_GET['dir'] ?? 'ongoing', (int)($_GET['orderId'] ?? 0));
