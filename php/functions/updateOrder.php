<?php
   
   define('login_req', true);

   require '../../global.php';

   $orderId = $main->updateOrder(
      
      $_POST['orderid'],
      $_POST['company_name'],
      $_POST['company_domain'],
      $_POST['order_desc'],
      $_POST['worker'],
      $_POST['company_admin_username'],
      $_POST['company_admin_password'],
      $_POST['asap']
  );
  
  
  echo $orderId;
  
  