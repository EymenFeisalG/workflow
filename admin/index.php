<?php
    define('login_req', true);
    require '../global.php';

    // Säkerställ att admin alltid har avslutande snedstreck så att relativa länkar inte hoppar ur mappen
    $requestUriPath = explode('?', $_SERVER['REQUEST_URI'])[0];
    if (!str_ends_with($requestUriPath, '/') && !str_ends_with($requestUriPath, '.php')) {
        header('Location: ' . $requestUriPath . '/' . (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
        exit;
    }

    if (!isset($_SESSION['user'])) {
        header('location: ../index.php');
        exit;
    }

    if (!$auth->hasRight('admin')) {
        header('location: ../home.php');
        exit;
    }

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
                
                <a href="../home.php">
                <div class="pm">
                    <div class="pmIcon"></div>
                    <span>Hem</span>
                    <div class="clear"></div>
                </div>
                </a>
                
                <span class="username"><?php echo $_SESSION['user']['username']; ?></span>
               
            </div>

            <div data-pagename="users" class="menuButton active">Användare</div>

            
      
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