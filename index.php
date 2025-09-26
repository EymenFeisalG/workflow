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
    <script src="ui/js/login.js"></script>
    <title>WorkGUI: Logga in</title>
    <link rel="icon" type="image/x-icon" href="ui/style/images/icons/W.ico">
</head>
<body>

    <div class="loginHolder">
        <div class="loginForm">
        <img style="display: none; width: 100%; height: 600px;" class="buklao" src="ui/style/images/buklao.png">
            
        <form method="post">
                <h5>Användarnamn / Email</h5>
                <input type="text" name="username">
                <h5>Lösenord</h5>
                <input type="password" name="password">
                <br>
                <div class="forgotPassword"><a href="register.php">Registrera konto</a></div>
                <div class="buttons">
                    <input type="submit" class="login" value="Logga in" name="login">
                </div>
            </form>
        </div>
    </div>

</body>
</html>