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

    <script src="ui/js/changeorder.js?v=2.7"></script>



    <script src="resources/tinymce/tinymce.min.js"></script>

    <script>var path = "<?php echo $path ?>"; </script>

    <title>Workflow: Redigera order</title>


</head>

<body>

    

    <main style="margin-top: 90px;">


        <div id="orderArea">

            

        <div class="fields">

            <input type="text" value="<?php echo htmlspecialchars($orderData['Name'], ENT_QUOTES, 'UTF-8'); ?>" required placeholder="Uppgiftens namn" class="name">

            <input type="text" value="<?php echo htmlspecialchars($orderData['Hostname'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Webbadress (valfritt)" class="host">

        </div>

        

        <div class="fields">

            <input type="text" value="<?php echo htmlspecialchars($orderData['contact_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Kontaktperson (valfritt)" class="contact_name">

            <input type="text" value="<?php echo htmlspecialchars($orderData['contact_org'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Företag / org.nummer" class="contact_org">

            <input type="text" value="<?php echo htmlspecialchars($orderData['contact_details'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Kontaktuppgifter / e-post" class="contact_details">

            <input type="text" value="<?php echo htmlspecialchars(($orderData['admin'] ?? '') === 'tomt' ? '' : $orderData['admin'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Användarnamn (valfri tjänst)"  class="admin_name">

            <input type="password" value="<?php echo htmlspecialchars(($orderData['password'] ?? '') === 'tomt' ? '' : $orderData['password'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="Lösenord (valfri tjänst)"  class="admin_password">

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
