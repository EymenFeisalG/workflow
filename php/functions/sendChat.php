<?php

require '../../global.php';


$main->sendChat($_POST['Message'], $_POST['Time']);

echo $_POST['Message'];
