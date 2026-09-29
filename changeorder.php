<?php
define('login_req', true);
require __DIR__ . '/global.php';
$id = (int)($_SESSION['changeOrder'] ?? $_GET['orderId'] ?? 0);
unset($_SESSION['changeOrder']);
header('Location: home.php' . ($id > 0 ? '?edit=' . $id : ''));
exit;
