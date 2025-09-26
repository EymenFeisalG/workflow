<?php

   

   define('login_req', false);



   require 'global.php';

   $auth->userLoginCheck();


?>



<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="ui/style/css/login.css" rel="stylesheet">
    <link href="ui/style/css/general.css" rel="stylesheet">
    <script src="ui/js/jquery.js"></script>
    <script src="ui/js/general.js"></script>
    <title>WorkGUI: Logga in</title>
    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">
</head>
<body>
    <div class="loginHolder">
        <div class="loginForm">
        <img style="display: none; width: 100%; height: 600px;" class="buklao" src="ui/style/images/buklao.png">
            
        <form method="post">
                <?php 
                if(isset($_POST['login']))
                {
                    $_SESSION['temp_seckey'] = $_POST['Seckey'];
                    $auth->register($_POST['Seckey'], $_POST['password'], $_POST['cPassword']); 
                }
                ?>
                <h5>Aktiveringskod</h5>
                <input type="text" value="<?php if(isset($_SESSION['temp_seckey'])) echo $_SESSION['temp_seckey']; ?>" name="Seckey">
                <h5>Lösenord</h5>
                <input type="password" name="password">
                <h5>Bekräfta lösenord</h5>
                <input type="password" name="cPassword">
                <br>
                <div class="forgotPassword"><a href="index.php">Logga in</a></div>
                <div class="buttons">
                    <input type="submit" class="register" value="Aktivera konto" name="login">
                </div>
            </form>
        </div>
    </div>

</body>
</html>