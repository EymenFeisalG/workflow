<?php

define('login_req', true);
require '../../global.php';

header('Content-Type: application/json; charset=utf-8');

$queryTerm = isset($_REQUEST['q']) ? trim($_REQUEST['q']) : '';
$customers = $main->searchCustomers($queryTerm);

echo json_encode($customers);
