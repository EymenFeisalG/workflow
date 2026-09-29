<?php
define('login_req', true);



require '../../global.php';

$orderid = $_POST['orderid'] ?? 0;
$comment = $_POST['comment'] ?? '';
$action  = $_POST['action'] ?? '';

$main->addTimeWorker('0:0', $orderid, $comment, $action);
