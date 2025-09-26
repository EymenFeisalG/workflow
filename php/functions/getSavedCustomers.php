<?php

define('login_req', true);

require '../../global.php';

$main->getSavedCustomersData($_POST['customerID']);