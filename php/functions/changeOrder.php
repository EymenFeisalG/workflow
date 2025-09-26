<?php
   
   define('login_req', true);

   require '../../global.php';

   if(!isset($_POST['orderId'])) return false;
   if(isset($_POST['addOrder'])) return false;

   $orderId = $_POST['orderId'];

   $_SESSION['changeOrder'] = $orderId;

   return true;

?>