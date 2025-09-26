<?php

    define('login_req', true);

    require 'global.php';

    $orderId = $_SESSION['changeOrder'];

    

    $orderData = $auth->changeOrderCheck($_SESSION['changeOrder']); 



    $path = $orderData['id'];



?>



<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <link href="ui/style/css/general.css" rel="stylesheet">

    <link href="ui/style/css/addOrder.css" rel="stylesheet">



    <script src="ui/js/jquery.js"></script>

    <script defer src="ui/js/general.js"></script>

    <script src="ui/js/changeorder.js"></script>



    <script src="resources/tinymce/tinymce.min.js"></script>

    <script>var path = "<?php echo $path ?>"; </script>

    <title>Workflow: Redigera order</title>

    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">

</head>

<body>

    

    <main style="margin-top: 90px;">


        <div id="orderArea">

            

        <div class="fields">

            <input type="text" value="<?php echo $orderData['Name']; ?>" required placeholder="Kundens namn" class="name">

            <input type="text" value="<?php echo $orderData['Hostname']; ?>" required placeholder="Webbadress" class="host">

        </div>

        

        <div class="fields">

            <input type="text" value="<?php echo $orderData['admin']; ?>" placeholder="Admin namn"  class="admin_name">

            <input type="text" value="<?php echo $orderData['password']; ?>" placeholder="Admin lösenord"  class="admin_password">

        </div>

        <div  class="messageToDev"></div>
        <button class="addToList">+</button>
        <button class="resetList">Radera listan</button>
        <button class="reCreateList">Återställ listan</button>
        </div>

        </div>



        <div class="text">

                <textarea name="text" class="content"><?php echo $orderData['Info']; ?></textarea>

            </div>

            







    <div class="clear"></div>



    <div id="header">

        

        <section>



        <div class="markContainer">

                 <span class="mark"><input type="checkbox" class="markAsap" value="asap"><p class="label">Markera som akut</p></span>

        </div>





            <button class="closeOrder">Stäng</button>

            <button class="saveOrder">Spara order</button>

            <select class="devName">

                <?php $main->getWorkers($orderData['worker_name_id']); ?>

            </select>

            <div class="clear"></div>

        </section>

        

    </div>

    </main>

    <div class="notis"></div>

</body>

</html>