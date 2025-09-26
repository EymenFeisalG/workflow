<?php

session_start();



if(isset($_SESSION['addOrder'])) 

    unset($_SESSION['addOrder']);


if(isset($_SESSION['changeOrder']))

    unset($_SESSION['changeOrder']);


header('location: ../../home.php');