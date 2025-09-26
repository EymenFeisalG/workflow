<?php

require '../../global.php';

if(isset($_POST['dir']))
    $dir = $_POST['dir'];
else
    $dir = '';

$main->focusOrder($_POST['orderId'], $dir);