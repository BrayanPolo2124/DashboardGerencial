<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/DashboardController.php';

if (isset($_GET['logout'])) {
    $route = 'logout';
} else {
    if (isset($_POST['route'])) {
        $route = $_POST['route'];
        if ($route !== 'generar_auditoria' && $route !== 'guardar_feedback_ia' && $route !== 'cambiar_tema') {
            $_SESSION['current_route'] = $route;
        }
    } else {
        $route = $_SESSION['current_route'] ?? 'login';
    }
}

$auth = new AuthController();
$dash = new DashboardController();

if (!isset($_SESSION['user']) && $route !== 'login' && $route !== 'logout') {
    $route = 'login';
}

switch ($route) {
    case 'login': $auth->login(); break;
    case 'logout': $auth->logout(); break;
    case 'home': $dash->index(); break;
    case 'admisiones': $dash->admisiones(); break;
    case 'facturacion': $dash->facturacion(); break;
    case 'auditoria': $dash->auditoria(); break;
    case 'cambiar_tema': $dash->cambiar_tema(); break;
    case 'generar_auditoria': $dash->generar_auditoria(); break;
    case 'guardar_feedback_ia': $dash->guardar_feedback_ia(); break;
    default: $dash->index(); break;
}