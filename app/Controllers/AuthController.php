<?php
require_once __DIR__ . '/../Config/Database.php';
require_once __DIR__ . '/../Models/AuthModel.php';

class AuthController {
    public function login() {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
            $user = trim($_POST['username'] ?? '');
            $pass = trim($_POST['password'] ?? '');
            $auth = new AuthModel();
            $userData = $auth->authenticate($user, $pass);
            
            if ($userData) {
                $_SESSION['user'] = $userData;
                // Al inciar sesión purgar cualquier rastro de fecha de sesiones anteriores
                unset($_SESSION['start_date']);
                unset($_SESSION['end_date']);
                $_SESSION['current_route'] = 'home';
                header('Location: ?');
                exit;
            } else {
                $error = 'Credenciales inválidas.';
            }
        }
        require_once __DIR__ . '/../Views/auth/login.php';
    }

    public function logout() {
        session_destroy();
        header('Location: ?');
        exit;
    }
}