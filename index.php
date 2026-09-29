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
    <title>Workflow: Logga in</title>
</head>
<body>

    <main class="loginHolder">
        <section class="loginForm" aria-labelledby="login-title">
            <p class="eyebrow">Workflow</p>
            <form method="post">
                <div class="fieldGroup">
                    <label for="username">Användarnamn eller e-post</label>
                    <input id="username" type="text" name="username" autocomplete="username" required>
                </div>
                <div class="fieldGroup">
                    <label for="password">Lösenord</label>
                    <input id="password" type="password" name="password" autocomplete="current-password" required>
                </div>
                <label class="rememberOption" for="remember">
                    <input id="remember" type="checkbox" name="remember" value="1">
                    <span>Kom ihåg mig på den här enheten</span>
                </label>
                <div class="buttons">
                    <input type="submit" class="login" value="Logga in" name="login">
                </div>
                <div class="forgotPassword"><a href="register.php">Skapa ett konto</a></div>
            </form>
        </section>
    </main>

</body>
</html>
