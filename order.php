<?php

    define('login_req', true);

    require 'global.php';

    $path = "";

    if(!isset($_SESSION['addOrder']) && !isset($_SESSION['updateOrder']))
    {
		$dir = date("yh_s");
        $_SESSION['addOrder'] = $dir;
        $path = 'media/' . $_SESSION['addOrder'];
    }

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
    <script defer src="ui/js/fileUploader.js"></script>
    <script src="ui/js/addOrder.js"></script>
    <script src="resources/tinymce/tinymce.min.js"></script>
    <script>var path = "<?php echo $path ?>"; </script>
    <title>WorkGUI: Lägg till order</title>
    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">
</head>
<body>

    <main>
    <div class="header">
        <button id="showUploader">Lägg till bilder</button>
    </div>

    <div class="imagesContainer">

       <form enctype="multipart/form-data">

       <div class="images">

           <div class="uploadbg">

               <h1>Ladda upp bilder, dra hit eller klicka</h1>

           </div>

           <input type="file" class="image" name="image[]" multiple>

        </form>


       </div>



       <div class="showCase"></div>
        
    </div>
    

        <div id="orderArea">

        <div class="getCustomers">

            <?php $main->getSavedCustomers(); ?>

        </div>
            

        <div class="fields">

            <input type="text" required placeholder="Kundens namn" class="name">

            <input type="text" required placeholder="Webbadress" class="host">

        </div>

        

        <div class="fields">

            <input type="text" placeholder="Admin namn"  class="admin_name">

            <input type="text" placeholder="Admin lösenord"  class="admin_password">

        </div>

        <div class="fields">

            <input type="text" placeholder="Orgs.nr"  class="org">

            <input type="text" placeholder="Kontaktperson"  class="contact">

        </div>


        <div  class="messageToDev"></div>
        <button class="addToList">+</button>
        <button class="resetList">Radera listan</button>
        <button class="reCreateList">Återställ listan</button>
        </div>


        <div class="text">
                <textarea name="text" class="content"></textarea>
            </div>
   
        </div>





    <div class="clear"></div>



    <div id="header">

        

        <section>


        <div class="markContainer">

                 <span class="mark"><input type="checkbox" class="markAsap" value="asap"><p class="label">Markera som akut</p></span>

        </div>


            <button class="closeOrder">Stäng</button>
            <button class="saveOrder">Skicka order</button>
            <select class="devName">
                <?php $main->getWorkers(); ?>
            </select>
            <div class="clear"></div>
        </section>

    
    </div>
    </main>
    <div class="notis"></div>
</body>

</html>