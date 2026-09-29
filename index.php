<?php


   define('login_req', false);



   require 'global.php';

   $auth->userLoginCheck();





?>



<!DOCTYPE html>

<html lang="sv">
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

    <main class="loginHolder">
        <section class="loginForm" aria-labelledby="login-title">
            <div class="brandMark" aria-hidden="true">W</div>
            <p class="eyebrow">WorkGUI</p>
            <h1 id="login-title">Välkommen tillbaka</h1>
            <p class="loginIntro">Logga in för att fortsätta till ditt arbetsflöde.</p>

            <form method="post">
                <div class="fieldGroup">
                    <label for="username">Användarnamn eller e-post</label>
                    <input id="username" type="text" name="username" autocomplete="username" required>
                </div>
                <div class="fieldGroup">
                    <label for="password">Lösenord</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <div class="buttons">
                    <input type="submit" class="login" value="Logga in" name="login">
                </div>
                <div class="forgotPassword"><a href="register.php">Skapa ett konto</a></div>
            </form>
        </section>
    </main>

</body>
</html>