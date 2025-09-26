<?php

/*Host & database settings*/

$host = 'localhost'; // hostname or adress
$mysql_user = 'root'; // mysql user
$mysql_database = 'workflow'; // database name
$mysql_password = ''; // mysql password

$mysql_settings = [
    'host' => $host,
    'mysql_user' => $mysql_user,
    'mysql_database' => $mysql_database,
    'mysql_password' => $mysql_password
];


// Email settings

$emailServerHost = 'mailcluster.loopia.se'; // email server host
$emailServerPort = 587; // server port
$website_mail = 'noreply@workflow.digitalmaklarna.se'; // email sender
$website_mail_password = 'Teleflow2023!!'; // email sender

$email_settings = [
    'emailServerHost' => $emailServerHost,
    'emailServerPort' => $emailServerPort,
    'website_mail' => $website_mail,
    'website_mail_password' => $website_mail_password
];