<?php



require '../../global.php';







$orderId = $main->sendOrder(

       
    $_POST['company_name'],
    
    $_SESSION['user']['userid'],

    $_POST['org'],

    $_POST['contact'],

    $_POST['company_domain'],

    $_POST['order_desc'],

    $_POST['worker'],

    $_POST['company_admin_username'],

    $_POST['company_admin_password'],

    $_POST['asap'],

    $_POST['devMessage'],

    $_POST['path'],

    $_POST['saveCustomer']
    
);





echo $orderId;



