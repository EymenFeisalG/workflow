<?php
class database
{
    static  $mysql;
    static  $mysqlStmt;

    function __construct($mysql_settings = [])
    {
        if(self::$mysql == null)
        {
            self::$mysql = new mysqli($mysql_settings['host'], $mysql_settings['mysql_user'], $mysql_settings['mysql_password'], $mysql_settings['mysql_database'], (int)($mysql_settings['port'] ?? 3306));

            if(self::$mysql->connect_errno)
            {
                die('<h1>Felaktiga mysql uppgifter</h1>');
            }
            self::$mysql->set_charset('utf8mb4');
        }
    }

    static function query($string)
    {
        self::$mysqlStmt = self::$mysql->query($string);

        return new self;
    }

    static function escape($string, $encrypt = false)
    {
        if(!$encrypt)
            return self::$mysql->real_escape_string($string);
        else
            return self::$mysql->real_escape_string(md5($string));
    }

    static function numrows()
    {
        return (self::$mysqlStmt) ? self::$mysqlStmt->num_rows : null;
    }

    static function assoc()
    {
        return (self::$mysqlStmt) ? self::$mysqlStmt->fetch_assoc() : null;
    }	
}