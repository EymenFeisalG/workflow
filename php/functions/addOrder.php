<?php

define('login_req', true);
require '../../global.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user']['userid'])) {
    echo json_encode(['success' => false, 'error' => 'Du måste vara inloggad för att skapa en order.']);
    exit;
}

$images = isset($_FILES['images']) ? $_FILES['images'] : (isset($_FILES['image']) ? $_FILES['image'] : null);

$result = $main->createOrderUnified($_POST, $images);

echo json_encode($result);
