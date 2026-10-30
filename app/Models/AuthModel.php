<?php
require_once __DIR__ . '/../Config/Database.php';

class AuthModel {
    public function authenticate($username, $password) {
        try {
            $db = Database::getMariaDB();
            $config = include __DIR__ . '/../../.vynet_config.php';
            $table = $config['mariadb']['table'] ?? 'users';
            
            $stmt = $db->prepare("SELECT * FROM `$table` WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                return $user;
            }
        } catch (Exception $e) {}
        return false;
    }
}