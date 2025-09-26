<?php
session_start();



if(isset($_SESSION['user']))
{
    session_destroy();
}

if(isset($_COOKIE['user']))
{
    $expirationTime = time() - 3600;
    setcookie('user', '', $expirationTime, '/');
}

header('location: ../../index.php');