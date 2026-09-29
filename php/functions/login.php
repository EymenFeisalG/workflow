<?php


require '../../global.php';

$auth->login($_POST['username'] ?? '', $_POST['password'] ?? '', true, ($_POST['remember'] ?? '') === '1');
