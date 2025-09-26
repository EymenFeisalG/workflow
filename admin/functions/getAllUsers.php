<?php

require '../../global.php';
require '../../php/classes/admin.class.php';

$admin = new admin($email_settings);

$admin->getAllUsers();