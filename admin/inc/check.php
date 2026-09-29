<?php

if(!isset($_GET['pageName']))
    return false;

    $pages = ['users'];

$pageName = $_GET['pageName'];

if(in_array($pageName, $pages))
    echo '1';
else
    echo '0';
