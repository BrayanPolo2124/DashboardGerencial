<?php
class Database {
    private static $perconaPdo = null;
    private static $mariaPdo = null;

    public static function getPercona() {
        if (self::$perconaPdo === null) {
            $config = include __DIR__ . '/../../.vynet_config.php';
            $p = $config['percona'];
            $dsn = "mysql:host={$p['host']};dbname={$p['db']};charset=utf8mb4";
            self::$perconaPdo = new PDO($dsn, $p['user'], $p['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
        }
        return self::$perconaPdo;
    }

    public static function getMariaDB() {
        if (self::$mariaPdo === null) {
            $config = include __DIR__ . '/../../.vynet_config.php';
            $m = $config['mariadb'];
            $dsn = "mysql:host={$m['host']};dbname={$m['db']};charset=utf8mb4";
            self::$mariaPdo = new PDO($dsn, $m['user'], $m['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5
            ]);
        }
        return self::$mariaPdo;
    }
}