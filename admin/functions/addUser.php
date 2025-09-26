<?php

require '../../global.php';
require '../../php/classes/admin.class.php';

$admin = new admin($email_settings);

if(isset($_POST))
    $admin->addUser($_POST['username'], $_POST['email'], $_POST['role'], $_POST['salary']);