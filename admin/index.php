<?php
    require '../global.php';

     // Get working time
   $timeWorked_attest = $main->MyWorkingTime($_SESSION['user']['userid']); 
   $timeWorked_paid = $main->MyWorkingTime($_SESSION['user']['userid'], true); 
?>

<!DOCTYPE html>

<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="../ui/js/jquery.js"></script>
    <script src="../ui/js/general.js"></script>
    <script src="../ui/js/hk.js"></script>
    <link href="../ui/style/css/general.css" rel="stylesheet">
    <link href="../ui/style/css/hk.css" rel="stylesheet">
    <title>Workflow: admin</title>
</head>
<body>
    <div class="container">
        
    <div class="LeftBar">
            
            <div class="userinfo">
                <div class="profilepic">
                    <img class="pic" src="../ui/style/images/profilepics/girl.jpg">
                </div>
                
                <a href="../home">
                <div class="pm">
                    <div class="pmIcon"></div>
                    <span>Hem</span>
                    <div class="clear"></div>
                </div>
                </a>
                
                <span class="username"><?php echo $_SESSION['user']['username']; ?></span>
               
            </div>

            <div data-pagename="time" class="menuButton">Arbetstider</div> 
            <div data-pagename="pay" class="menuButton">Betalningar</div> 
            <div data-pagename="users" class="menuButton">Användare</div>
           <!-- <div data-pagename="settings" class="menuButton">Inställningar</div> -->

            <div class="timeWorked">
        <span><h1><?php echo $timeWorked_paid['hours'] . 'h ' . $timeWorked_paid['minutes'] . 'm'; ?></h1><p>Betald tid</p></span>
         <span><h1 class="attestedTime"><?php echo $timeWorked_attest['hours'] . 'h ' . $timeWorked_attest['minutes'] . 'm'; ?></h1><p>Attesterad tid</p></span>
        </div>

            
      
    </div>
        
        <div class="content">
            <div class="adminContent">
                <div class="header"></div>
                <div class="jsHook"></div>
                <div class="clear"></div>
            </div>
        </div>
   </div>

 
</body>
</html>