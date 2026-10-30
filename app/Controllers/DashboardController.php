<?php
require_once __DIR__ . '/../Models/HomeModel.php';
require_once __DIR__ . '/../Models/AdmisionesModel.php';
require_once __DIR__ . '/../Models/FacturacionModel.php';
require_once __DIR__ . '/../Models/AiModel.php';

class DashboardController {
    public function index() { $this->loadView('home'); }
    public function admisiones() { $this->loadView('admisiones'); }
    public function facturacion() { $this->loadView('facturacion'); }
    public function auditoria() { $this->loadView('auditoria'); }

    public function cambiar_tema() {
        if (!isset($_SESSION['user'])) { echo json_encode(['error' => 'No autorizado']); exit; }
        $nuevoTema = $_POST['theme'] ?? 'oscuro';
        $validos = ['oscuro', 'claro', 'azul', 'esmeralda'];
        if (in_array($nuevoTema, $validos)) {
            $_SESSION['user']['theme_pref'] = $nuevoTema;
            try {
                $db = Database::getMariaDB();
                $config = include __DIR__ . '/../../.vynet_config.php';
                $table = $config['mariadb']['table'] ?? 'users';
                $stmt = $db->prepare("UPDATE `$table` SET theme_pref = ? WHERE id = ?");
                $stmt->execute([$nuevoTema, $_SESSION['user']['id']]);
            } catch (Exception $e) {}
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    public function generar_auditoria() {
        if (!isset($_SESSION['user'])) { echo json_encode(['error' => 'No autorizado']); exit; }
        
        // TIEMPO LÍMITE PHP AMPLIADO A 20 MINUTOS
        set_time_limit(1200); 

        date_default_timezone_set('America/Bogota');
        $startDate = $_POST['start_date'] ?? date('Y-m-d', strtotime('-11 days'));
        $endDate = $_POST['end_date'] ?? date('Y-m-d');

        $admModel = new AdmisionesModel();
        $facModel = new FacturacionModel();
        
        $datosAuditoria = [
            'asistencial_y_citas' => $admModel->getData($startDate, $endDate),
            'financiero_y_facturacion' => $facModel->getData($startDate, $endDate)
        ];

        $aiModel = new AiModel();
        $reporteMarkdown = $aiModel->generarInforme($startDate, $endDate, $datosAuditoria);

        header('Content-Type: application/json');
        echo json_encode(['markdown' => $reporteMarkdown]);
        exit;
    }

    public function guardar_feedback_ia() {
        if (!isset($_SESSION['user'])) { echo json_encode(['error' => 'No autorizado']); exit; }
        $informe = $_POST['informe'] ?? '';
        $calificacion = (int)($_POST['calificacion'] ?? 1);
        
        if (!empty($informe)) {
            $aiModel = new AiModel();
            $success = $aiModel->guardarFeedback($informe, $calificacion);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false]);
        }
        exit;
    }

    private function validDate($value, $fallback) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return $fallback;
        return $value;
    }

    private function loadView($tab) {
        if (!isset($_SESSION['user'])) { header('Location: ?'); exit; }

        date_default_timezone_set('America/Bogota');
        $currentDateColombia = date('Y-m-d');
        
        // Carga de fechas priorizando el POST actual, luego sesión, luego defecto.
        if (isset($_POST['start_date'])) {
            $_SESSION['start_date'] = $_POST['start_date'];
        }
        if (isset($_POST['end_date'])) {
            $_SESSION['end_date'] = $_POST['end_date'];
        }

        // Si es la vista de auditoria, el defecto es los ultimos 11 dias, para el resto es HOY
        if ($tab === 'auditoria') {
             $defaultStart = date('Y-m-d', strtotime('-11 days'));
             $startDate = $this->validDate($_SESSION['start_date'] ?? '', $defaultStart);
        } else {
             $startDate = $this->validDate($_SESSION['start_date'] ?? '', $currentDateColombia);
        }
        
        $endDate = $this->validDate($_SESSION['end_date'] ?? '', $currentDateColombia);

        $config = include __DIR__ . '/../../.vynet_config.php';
        $data = [];

        if ($tab === 'home') {
            $endDate = $startDate; 
            $m = new HomeModel();
            $data = $m->getData($startDate);
        } elseif ($tab === 'admisiones') {
            $m = new AdmisionesModel();
            $data = $m->getData($startDate, $endDate);
        } elseif ($tab === 'facturacion') {
            $m = new FacturacionModel();
            $data = $m->getData($startDate, $endDate);
        }

        require_once __DIR__ . '/../Views/dashboard/layout.php';
    }
}