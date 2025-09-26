<?php
define('login_req', true);



require '../../global.php';

if(isset($_SESSION['focusOrder']))
    unset($_SESSION['focusOrder']);


if($_POST['hours'] == "") $_POST['hours'] = 0;
if($_POST['minutes'] == "") $_POST['minutes'] = 0;

$_POST['minutes'] = $_POST['minutes'] ?? '0';

$time = $_POST['hours'] . ':' . $_POST['minutes'];



$main->addTimeWorker($time, $_POST['orderid'], $_POST['comment'], $_POST['action']);
