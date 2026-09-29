<?php
define('login_req', true);

require 'global.php';

if (isset($_SESSION['addOrder'])) {
    unset($_SESSION['addOrder']);
}

$auth->userLoginCheck();

// Redirect to home dashboard with new order modal trigger
header('Location: home.php?newOrder=1');
exit;