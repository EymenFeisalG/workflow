<?php
define('login_req', true);

require __DIR__ . '/../../global.php';
$auth->userLoginCheck();
    
$main->getSteps($_GET['orderId']);

