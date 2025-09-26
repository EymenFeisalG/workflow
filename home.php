<?php
   define('login_req', true);

   require 'global.php';

   $auth->userLoginCheck();

   if(!isset($_SESSION['focusOrder']))
        $_GET['dir'] = $dir = $auth->setDir();
    else
        $_GET['dir'] = $dir = $_SESSION['focusOrder']['dir'];

   // Get working time
   $timeWorked_attest = $main->MyWorkingTime($_SESSION['user']['userid']);
   $salary = $main->countSalary($timeWorked_attest['totalTime']); 
   $timeWorked_paid = $main->MyWorkingTime($_SESSION['user']['userid'], true); 

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/app.css" rel="stylesheet">
    <script>
        workflow = false;
    </script>
    <link href="ui/style/css/general.css" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script defer src="ui/js/general.js"></script>
    <script src="ui/js/app.js"></script>
    <script src="resources/tinymce/tinymce.min.js"></script>
    <script>
        var Direction = "<?php echo $_GET['dir']; ?>";
        var orderInFocus = "<?php if(isset($_SESSION['focusOrder'])) echo 'true'; else echo 'false'; ?>";
    </script>



    <title>WorkGUI</title>
    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">
</head>
<body>

<div class="container">

        <div class="LeftBar">
            
            <div class="userinfo">
                <div class="profilepic">
                    <img class="pic" src="ui/style/images/logo/dmlogo.png">
                </div>
                
                <div class="timerWorker">
                <div class="pm">
                    <div class="pmIcon paid"></div>
                    <span><?php echo  $salary; ?> kr</span>
                    <div class="clear"></div>
                </div>
                <div class="pm">
                    <div class="pmIcon attested"></div>
                    <span><?php echo  $timeWorked_attest['hours'].' h '.$timeWorked_attest['minutes'].' m'; ?></span>
                    <div class="clear"></div>
                </div>

                <div class="pm">
                    <div class="pmIcon"></div>
                    <span><?php echo  $timeWorked_paid['hours'].' h '.$timeWorked_paid['minutes'].' m'; ?></span>
                    <div class="clear"></div>
                </div>
                
                </div>
                
                <span class="username"><?php echo $_SESSION['user']['username']; ?></span>
               
            </div>


            <div class="filterOrders">
                <div class="searchField">
                <img class="searchicon" src="ui/style/images/icons/search-icon.png">
                <input class="searchOrder" placeholder="Sök uppgift" type="search">
                <span class="results"></span>
                </div>
         
                <div class="clear"></div>

            </div>
            
            <?php 

                if($auth->hasRight('add_new_order'))
                    echo '<div class="addOrder">Ny uppgift</div>';
            ?>
                   <a href="workflow"><div class="menuButton">WORKFLOW</div></a>
            
            <?php 
                $main->getOrderNav();
            ?>
            


              <?php
                if($auth->hasRight('admin'))
                    echo '<a href="admin/"><div class="menuButton">ADMIN</div></a>';

              ?>
             
             <a href="php/functions/logout.php"><div class="menuButton">LOGGA UT</div></a>
        </div>

        <div class="orders">
        
        </div>

   </div>



    <div hidden id="focusOrder"><?php if(isset($_SESSION['focusOrder'])) echo $_SESSION['focusOrder']['orderid']; ?></div>
        <div class="modalFocus" <?php if(isset($_SESSION['focusOrder'])) echo 'style="display: block;'; ?>></div>
        <div class="modal">
             <form class="timeForm" method="post">
                <h5>Arbetad tid</h5>
                <br>
                <div class="fields">
                <input type="number" min="0"  autocomplete="off" placeholder="h" class="hours" name="hours"> timmar
                <input type="number" min="0"  autocomplete="off" step="30" placeholder="m" class="minutes" name="minutes"> minuter
                <div class="clear"></div>
                </div>
                <input type="text" name="orderid" class="orderid" value="" hidden>
                <input type="text" name="action" class="action" value="" hidden>
                <h5>Skriv ett meddelande till Namo</h5>
                <textarea class="content" name="comment"></textarea>
                <input type="submit" value="Spara" class="saveTime">
                <input type="submit" class="closeTime close" value="Ångra">
            </form>
        </div>

</body>
</html>