<?php

require '../../global.php';
require '../../php/classes/admin.class.php';

$admin = new admin($email_settings);

if(isset($_POST))
    $admin->pay($_POST['orders'], $_POST['paymentOrder']);