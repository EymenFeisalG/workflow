<?php
require '../../global.php';

(isset($_POST['imagesExists'])) ? $imagesExists = false : $imagesExists = true;


$main->uploadImages($_FILES, $imagesExists);