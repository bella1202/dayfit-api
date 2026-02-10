<?php
class Db {
    public static $pdo;

    public static function init() {
        self::$pdo = new PDO(
            "mysql:host=".Env::get('DB_HOST').";dbname=".Env::get('DB_NAME'),
            Env::get('DB_USER'),
            Env::get('DB_PASS'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
}
