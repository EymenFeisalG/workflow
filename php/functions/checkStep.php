<?php
define('login_req', true);

require '../../global.php';
    
$main->checkStep($_POST['stepid'], $_POST['value']);

