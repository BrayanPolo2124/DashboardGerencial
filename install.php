<?php
/**
 * Instalador y Generador MVC
 * Edición Vynet360 Enterprise: Gráficos Dinámicos de Tiempo & Seguridad Oculta
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// === DESTRUCCIÓN ABSOLUTA DE SESIONES PREVIAS ===
session_start();
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

$message = '';
$success = false;

$hospitalName = $_POST['hospital_name'] ?? 'HOSPITAL GONZALO CONTRERAS E.S.E.';
$hospitalLocation = $_POST['hospital_location'] ?? 'Sistema de Información Gerencial';
$themeColor = $_POST['theme_color'] ?? 'oscuro';

$perc_host = $_POST['perc_host'] ?? 'union.sihos.com.co';
$perc_db   = $_POST['perc_db'] ?? 'sihos';
$perc_user = $_POST['perc_user'] ?? 'consulta';
$perc_pass = $_POST['perc_pass'] ?? '*Union2026*';

$maria_host  = $_POST['maria_host'] ?? '127.0.0.1';
$maria_db    = $_POST['maria_db'] ?? 'vynet360_dashboardunion';
$maria_user  = $_POST['maria_user'] ?? 'vynet360_dashboardunion';
$maria_pass  = $_POST['maria_pass'] ?? 'Desarrollo.2026';
$maria_table = $_POST['maria_table'] ?? 'users';

$admin_user  = $_POST['admin_user'] ?? 'admin';
$admin_pass  = $_POST['admin_pass'] ?? 'admin123';

$ollama_url = $_POST['ollama_url'] ?? 'http://127.0.0.1:11434';
// Preconfigurado al modelo rápido y eficiente por defecto
$ollama_model = $_POST['ollama_model'] ?? 'qwen2.5:14b'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hospital_name'])) {
    try {
        $dsnPerc = "mysql:host=$perc_host;dbname=$perc_db;charset=utf8mb4";
        $pdoPerc = new PDO($dsnPerc, $perc_user, $perc_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $pdoPerc->query("SELECT 1");

        $dsnServer = "mysql:host=$maria_host;charset=utf8mb4";
        $pdoServer = new PDO($dsnServer, $maria_user, $maria_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $pdoServer->exec("CREATE DATABASE IF NOT EXISTS `$maria_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

        $dsnMaria = "mysql:host=$maria_host;dbname=$maria_db;charset=utf8mb4";
        $pdoMaria = new PDO($dsnMaria, $maria_user, $maria_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]);

        $pdoMaria->exec("DROP TABLE IF EXISTS `$maria_table`;");
        $pdoMaria->exec("CREATE TABLE `$maria_table` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            theme_pref VARCHAR(20) DEFAULT '$themeColor',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $pdoMaria->exec("CREATE TABLE IF NOT EXISTS `ia_feedback` (
            id INT AUTO_INCREMENT PRIMARY KEY,
            informe_texto TEXT NOT NULL,
            calificacion INT DEFAULT 1,
            fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $hashedPassword = password_hash($admin_pass, PASSWORD_DEFAULT);
        $stmtInsert = $pdoMaria->prepare("INSERT INTO `$maria_table` (username, password, theme_pref) VALUES (?, ?, ?)");
        $stmtInsert->execute([$admin_user, $hashedPassword, $themeColor]);

        function makeDir($path) { if (!is_dir($path)) mkdir($path, 0755, true); }
        function makeFile($path, $content) { file_put_contents($path, $content); }

        makeDir(__DIR__ . '/app/Config'); makeDir(__DIR__ . '/app/Models'); makeDir(__DIR__ . '/app/Controllers'); makeDir(__DIR__ . '/app/Views/auth'); makeDir(__DIR__ . '/app/Views/dashboard');

        // --- CONFIGURACIÓN OCULTA Y SEGURA (.vynet_config.php) ---
        $configContent = "<?php\n" .
            "if (basename(\$_SERVER['PHP_SELF']) == basename(__FILE__)) { die('Acceso Restringido'); }\n" .
            "return [\n" .
            "    'hospital_name' => " . var_export($hospitalName, true) . ",\n" .
            "    'hospital_location' => " . var_export($hospitalLocation, true) . ",\n" .
            "    'theme_color' => " . var_export($themeColor, true) . ",\n" .
            "    'ollama_url' => " . var_export($ollama_url, true) . ",\n" .
            "    'ollama_model' => " . var_export($ollama_model, true) . ",\n" .
            "    'percona' => [\n" .
            "        'host' => " . var_export($perc_host, true) . ",\n" .
            "        'db' => " . var_export($perc_db, true) . ",\n" .
            "        'user' => " . var_export($perc_user, true) . ",\n" .
            "        'pass' => " . var_export($perc_pass, true) . "\n" .
            "    ],\n" .
            "    'mariadb' => [\n" .
            "        'host' => " . var_export($maria_host, true) . ",\n" .
            "        'db' => " . var_export($maria_db, true) . ",\n" .
            "        'user' => " . var_export($maria_user, true) . ",\n" .
            "        'pass' => " . var_export($maria_pass, true) . ",\n" .
            "        'table' => " . var_export($maria_table, true) . "\n" .
            "    ]\n" .
            "];\n";
        makeFile(__DIR__ . '/.vynet_config.php', $configContent);

        // --- DATABASE CLASS ---
        $dbContent = <<<'PHP'
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
PHP;
        makeFile(__DIR__ . '/app/Config/Database.php', $dbContent);

        // --- AUTH MODEL ---
        $authModelContent = <<<'PHP'
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
PHP;
        makeFile(__DIR__ . '/app/Models/AuthModel.php', $authModelContent);

        // --- HOME MODEL ---
        $homeModelContent = <<<'PHP'
<?php
require_once __DIR__ . '/../Config/Database.php';

class HomeModel {
    private function calculateVariation($today, $yesterday) {
        if ($yesterday == 0) return $today > 0 ? '+100,0% vs ant' : '0,0% vs ant';
        $diff = (($today - $yesterday) / $yesterday) * 100;
        $sign = $diff >= 0 ? '+' : '';
        return $sign . number_format($diff, 1, ',', '.') . '% vs ant';
    }

    public function getData($targetDay) {
        $prevDay = date('Y-m-d', strtotime($targetDay . ' -1 day'));
        $startToday = $targetDay . ' 00:00:00';
        $endToday = $targetDay . ' 23:59:59';
        $startPrev = $prevDay . ' 00:00:00';
        $endPrev = $prevDay . ' 23:59:59';

        $admHoy = $this->getAdmisionesByRange($startToday, $endToday);
        $admAyer = $this->getAdmisionesByRange($startPrev, $endPrev);

        $urgHoy = $this->getPacientesUrgenciasByRange($startToday, $endToday);
        $urgAyer = $this->getPacientesUrgenciasByRange($startPrev, $endPrev);
        
        $urgActivasHoy = $this->getUrgenciasActivasByRange($startToday, $endToday);
        $urgActivasAyer = $this->getUrgenciasActivasByRange($startPrev, $endPrev);

        $pendClasificarHoy = $this->getPendientesClasificarByRange($startToday, $endToday);
        $pendClasificarAyer = $this->getPendientesClasificarByRange($startPrev, $endPrev);

        $factHoy = $this->getFacturacionValorByRange($startToday, $endToday);
        $factAyer = $this->getFacturacionValorByRange($startPrev, $endPrev);
        
        $epsHoy = $this->getFacturacionEPSByRange($startToday, $endToday);
        $epsAyer = $this->getFacturacionEPSByRange($startPrev, $endPrev);

        $copagosHoy = $this->getFacturacionCopagosByRange($startToday, $endToday);
        $copagosAyer = $this->getFacturacionCopagosByRange($startPrev, $endPrev);

        $citasHoy = $this->getCitasByRange($startToday, $endToday);
        $citasAyer = $this->getCitasByRange($startPrev, $endPrev);

        return [
            'facturacion_mes'       => $factHoy,
            'facturacion_mes_sub'   => $this->calculateVariation($factHoy, $factAyer),
            'valor_eps'             => $epsHoy,
            'valor_eps_sub'         => $this->calculateVariation($epsHoy, $epsAyer),
            'valor_copagos'         => $copagosHoy,
            'valor_copagos_sub'     => $this->calculateVariation($copagosHoy, $copagosAyer),
            'camas_disp'            => 0,
            'camas_disp_sub'        => '0% libre',
            'pacientes_hoy'         => $admHoy,
            'pacientes_hoy_sub'     => $this->calculateVariation($admHoy, $admAyer),
            'urgencias'             => $urgHoy,
            'urgencias_sub'         => $this->calculateVariation($urgHoy, $urgAyer),
            'urgencias_hoy'         => $urgActivasHoy,
            'urgencias_hoy_sub'     => $this->calculateVariation($urgActivasHoy, $urgActivasAyer),
            'pendientes_clasificar' => $pendClasificarHoy,
            'pendientes_clas_sub'   => $this->calculateVariation($pendClasificarHoy, $pendClasificarAyer),
            'citas_hoy'             => $citasHoy,
            'citas_hoy_sub'         => $this->calculateVariation($citasHoy, $citasAyer),
            'cirugias_hoy'          => 0,
            'cirugias_hoy_sub'      => 'Realizadas',
            'hospitalizados'        => 0,
            'hospitalizados_sub'    => '0% ocupación',
            'ocupacion_camas'       => 0.0,
            'ocupacion_camas_sub'   => 'Normal',
            'pacientes_uci'         => 0,
            'pacientes_uci_sub'     => 'Cuidado intensivo',
            'ocupacion_uci'         => 0.0,
            'ocupacion_uci_sub'     => 'Normal'
        ];
    }

    public function getAdmisionesByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $stmt = $db->prepare("SELECT COUNT(*) FROM Admision WHERE FechDigi BETWEEN ? AND ?");
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public function getPendientesClasificarByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(A.ConsAdmi) AS Total_Pacientes_Pendientes_Clasificar FROM Admision A LEFT JOIN Triage B ON A.ConsAdmi = B.ConsAdmi INNER JOIN CodiServ D ON A.CodiServ = D.CodiServ LEFT JOIN CodiServ E ON A.ServEgre = E.CodiServ WHERE A.ViaIngre = 1 AND A.Anulado <> 1 AND D.NombServ IN ('URGENCIAS','URGENCIAS AMBULATORIO','URGENCIAS CONSULTORIO Y TRASLADO') AND ((E.NombServ NOT LIKE '%HOSPITALIZACION%' AND E.NombServ NOT LIKE '%UCI%' AND E.NombServ NOT LIKE '%PARTO%') OR E.NombServ IS NULL) AND (B.ConsAdmi IS NULL OR B.FechTria IS NULL) AND A.FechDigi BETWEEN ? AND ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public function getPacientesUrgenciasByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(DISTINCT CASE WHEN a.ConsAdmi IS NOT NULL THEN tr.ConsAdmi END) AS total FROM Admision a INNER JOIN Triage tr ON a.ConsAdmi = tr.ConsAdmi AND a.CodiInst = tr.CodiInst WHERE a.FechIngr BETWEEN ? AND ? AND tr.ClasTria <= 5 AND a.Anulado <> 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
    
    public function getUrgenciasActivasByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(*) FROM Admision a WHERE a.FechIngr BETWEEN ? AND ? AND a.ViaIngre=1 AND a.Anulado <> 1 AND a.ServEgre IN (007,021) AND a.Cerrado =2";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public function getFacturacionValorByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $stmt = $db->prepare("SELECT SUM(A.ValoTota) FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi=B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont=C.NumeCont AND A.CodiAdmi=C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado=1 AND A.Anulado<>1 AND A.FechDocu BETWEEN ? AND ?");
            $stmt->execute([$start, $end]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) { return 0.0; }
    }
    
    public function getFacturacionEPSByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT SUM(A.ValoEAPB) FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi=B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont=C.NumeCont AND A.CodiAdmi=C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado=1 AND A.Anulado<>1 AND A.FechDocu BETWEEN ? AND ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) { return 0.0; }
    }
    
    public function getFacturacionCopagosByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT SUM(CASE WHEN A.ValoEAPB > 0 THEN 0 ELSE A.ValoCopa END) FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi = B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont = C.NumeCont AND A.CodiAdmi = C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado = 1 AND A.Anulado <> 1 AND A.FechDocu BETWEEN ? AND ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (float)$stmt->fetchColumn();
        } catch (Exception $e) { return 0.0; }
    }

    public function getCitasByRange($start, $end) {
        try {
            $db = Database::getPercona();
            $stmt = $db->prepare("SELECT COUNT(*) FROM DetaCita WHERE FechCita BETWEEN ? AND ?");
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
}
PHP;
        makeFile(__DIR__ . '/app/Models/HomeModel.php', $homeModelContent);

        // --- ADMISIONES MODEL (AGRUPACIÓN DINÁMICA DE TIEMPO) ---
        $admisionesModelContent = <<<'PHP'
<?php
require_once __DIR__ . '/../Config/Database.php';

class AdmisionesModel {

    private function getGroupFormat($start, $end) {
        $days = (strtotime($end) - strtotime($start)) / 86400;
        if ($days > 1095) return '%Y'; // > 3 años: agrupar por año
        if ($days > 90) return '%Y-%m'; // > 90 días: agrupar por mes
        return '%Y-%m-%d'; // Por defecto: día
    }

    private function fetchSeries(PDO $db, $sql, $params, $valueKey) {
        $result = ['total' => 0, 'labels' => [], 'values' => []];
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($row['fecha'])) continue;
                $value = (int)($row[$valueKey] ?? 0);
                $result['labels'][] = (string)$row['fecha'];
                $result['values'][] = $value;
                $result['total'] += $value;
            }
        } catch (Throwable $e) {}
        return $result;
    }

    public function getData($startDate, $endDate) {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';
        return [
            'pacientes_por_dia'              => $this->getPacientesPorDiaSeries($start, $end),
            'citas_asignadas_por_dia'        => $this->getCitasAsignadasPorDiaSeries($start, $end),
            'total_citas_asignadas'          => $this->getCitasAsignadasTotal($start, $end),
            'pacientes_por_urgencias'        => $this->getPacientesUrgenciasAdmisiones($start, $end),
            'urgencias_actuales_activas'     => $this->getUrgenciasActualmente($start, $end),
            'indicador_cumplimiento_citas'   => $this->getCumplimientoCitas($start, $end),
            'estado_rda'                     => $this->getEstadoRda($startDate, $endDate),
            'promedio_tiempo_atencion_medico'=> $this->getPromedioAtencionMedico($startDate, $endDate),
            'promedio_tiempo_clasificacion'  => $this->getPromedioClasificacionTriage($startDate, $endDate),
            'promedio_global_espera_cita'    => $this->getPromedioEsperaCitaGlobal($startDate, $endDate),
            'oportunidad_espera_por_tipo'    => $this->getOportunidadEsperaPorTipo($startDate, $endDate)
        ];
    }

    public function getPacientesPorDiaSeries($start, $end) {
        $db = Database::getPercona();
        $fmt = $this->getGroupFormat($start, $end);
        $sql = "SELECT DATE_FORMAT(A.FechIngr, '$fmt') AS fecha, COUNT(*) AS total FROM Admision A WHERE A.FechIngr BETWEEN ? AND ? GROUP BY fecha ORDER BY fecha ASC";
        return $this->fetchSeries($db, $sql, [$start, $end], 'total');
    }

    public function getCitasAsignadasTotal($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(*) FROM DetaCita A WHERE A.FechCita BETWEEN ? AND ? AND A.EstaCita <> 1 AND A.NumeUsua IS NOT NULL AND A.NumeUsua <> '' AND A.TipoDocu IS NOT NULL AND A.TipoDocu <> ''";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    public function getCitasAsignadasPorDiaSeries($start, $end) {
        $db = Database::getPercona();
        $fmt = $this->getGroupFormat($start, $end);
        $sql = "SELECT DATE_FORMAT(A.FechCita, '$fmt') AS fecha, COUNT(*) AS total FROM DetaCita A WHERE A.FechCita BETWEEN ? AND ? AND A.EstaCita <> 1 AND A.NumeUsua IS NOT NULL AND A.NumeUsua <> '' AND A.TipoDocu IS NOT NULL AND A.TipoDocu <> '' GROUP BY fecha ORDER BY fecha ASC";
        return $this->fetchSeries($db, $sql, [$start, $end], 'total');
    }

    public function getPacientesUrgenciasAdmisiones($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(DISTINCT CASE WHEN a.ConsAdmi IS NOT NULL THEN tr.ConsAdmi END) AS total FROM Admision a INNER JOIN Triage tr ON a.ConsAdmi = tr.ConsAdmi AND a.CodiInst = tr.CodiInst WHERE a.FechIngr BETWEEN ? AND ? AND tr.ClasTria <= 5 AND a.Anulado <> 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    public function getUrgenciasActualmente($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(*) FROM Admision a WHERE a.FechIngr BETWEEN ? AND ? AND a.ViaIngre=1 AND a.Anulado <> 1 AND a.ServEgre IN (007,021) AND a.Cerrado =2";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0; }
    }

    public function getCumplimientoCitas($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT 
                COUNT(*) AS total_citas_programadas,
                SUM(CASE WHEN A.EstaCita = 3 THEN 1 ELSE 0 END) AS total_cumplidas,
                SUM(CASE WHEN A.EstaCita = 2 THEN 1 ELSE 0 END) AS citas_pendientes,
                SUM(CASE WHEN A.EstaCita = 6 THEN 1 ELSE 0 END) AS total_canceladas,
                SUM(CASE WHEN A.EstaCita = 4 THEN 1 ELSE 0 END) AS ausentismo_paciente,
                SUM(CASE WHEN A.EstaCita = 5 THEN 1 ELSE 0 END) AS ausentismo_medico,
                SUM(CASE WHEN A.EstaCita = 7 THEN 1 ELSE 0 END) AS fallo_sistema
            FROM DetaCita A
            WHERE A.FechCita between ? and ? 
              AND A.EstaCita <> 1 
              AND A.NumeUsua IS NOT NULL 
              AND A.NumeUsua <> '' 
              AND A.TipoDocu IS NOT NULL";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    public function getEstadoRda($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT 
                CASE 
                    WHEN A.tipo_rda_id = 60 THEN 'PACIENTE'
                    WHEN A.tipo_rda_id = 61 THEN 'CONSULTA EXTERNA'
                    WHEN A.tipo_rda_id = 62 THEN 'URGENCIAS'
                    WHEN A.tipo_rda_id = 63 THEN 'HOSPITALIZACION'
                END AS TIPO_RDA,
                COUNT(*) AS TOTAL,
                SUM(CASE WHEN A.estado_id = 56 THEN 1 ELSE 0 END) AS ENVIADOS,
                SUM(CASE WHEN A.estado_id = 57 THEN 1 ELSE 0 END) AS PENDIENTES,
                SUM(CASE WHEN A.estado_id = 58 THEN 1 ELSE 0 END) AS RECHAZADOS
            FROM EnviRda A
            WHERE A.tipo_rda_id IN (60,61,62,63)
              AND A.created_at >= ?
            GROUP BY A.tipo_rda_id";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate . ' 01:00:00']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

    public function getPromedioAtencionMedico($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT Nombre_Nivel_Triage, Total_Pacientes,
                CASE WHEN Promedio_Segundos IS NULL THEN 'N/A'
                ELSE CONCAT(
                    CASE WHEN (Promedio_Segundos DIV 86400) > 0 THEN CONCAT(FLOOR(Promedio_Segundos DIV 86400), ' D ') ELSE '' END,
                    CASE WHEN ((MOD(Promedio_Segundos, 86400) DIV 3600) > 0 OR (Promedio_Segundos DIV 86400) > 0) THEN CONCAT(FLOOR((MOD(Promedio_Segundos, 86400) DIV 3600)), ' H ') ELSE '' END,
                    CASE WHEN ((MOD(Promedio_Segundos, 3600) DIV 60) > 0 OR (Promedio_Segundos DIV 86400) > 0 OR (MOD(Promedio_Segundos, 86400) DIV 3600) > 0) THEN CONCAT(FLOOR(MOD(Promedio_Segundos, 3600) DIV 60), ' M ') ELSE '' END
                ) END AS Promedio_Tiempo_Atencion_Medico,
                Total_Derivados
            FROM (
                SELECT Nombre_Nivel_Triage, COUNT(*) AS Total_Pacientes, AVG(Segundos_Espera_Atencion) AS Promedio_Segundos, SUM(CASE WHEN Estado_Oportunidad IN ('Derivado a Prioritaria', 'Derivado a Externa') THEN 1 ELSE 0 END) AS Total_Derivados
                FROM (
                    SELECT 
                        CASE WHEN B.ClasTria = 1 THEN 'Triage I' WHEN B.ClasTria = 2 THEN 'Triage II' WHEN B.ClasTria = 3 THEN 'Triage III' WHEN B.ClasTria = 4 THEN 'Triage IV' WHEN B.ClasTria = 5 THEN 'Triage V' ELSE 'Sin Clasificar' END AS Nombre_Nivel_Triage,
                        CASE WHEN B.ClasTria IN (4, 5) THEN NULL WHEN B.FechTria IS NULL OR C.FechCons IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND, CONCAT(B.FechTria, ' ', B.HoraTria), CONCAT(C.FechCons, ' ', C.HoraCons)) END AS Segundos_Espera_Atencion,
                        CASE WHEN B.FechTria IS NULL THEN 'Pendiente Triage' WHEN B.ClasTria = 4 THEN 'Derivado a Prioritaria' WHEN B.ClasTria = 5 THEN 'Derivado a Externa' WHEN C.FechCons IS NULL THEN 'Sin Atención' ELSE 'Atendido' END AS Estado_Oportunidad
                    FROM Admision A
                    LEFT JOIN Triage B ON A.ConsAdmi = B.ConsAdmi
                    LEFT JOIN RipsCons C ON A.ConsAdmi = C.ConsAdmi
                    INNER JOIN CodiServ D ON A.CodiServ = D.CodiServ
                    LEFT JOIN CodiServ E ON A.ServEgre = E.CodiServ   
                    WHERE A.ViaIngre = 1 AND A.Anulado <> 1 AND D.NombServ IN ('URGENCIAS','URGENCIAS AMBULATORIO','URGENCIAS CONSULTORIO Y TRASLADO') AND ((E.NombServ NOT LIKE '%HOSPITALIZACION%' AND E.NombServ NOT LIKE '%UCI%' AND E.NombServ NOT LIKE '%PARTO%') OR E.NombServ IS NULL OR B.ConsAdmi IS NOT NULL) AND A.FechDigi BETWEEN ? AND ?
                ) AS Detalle_Atencion_Interno GROUP BY Nombre_Nivel_Triage
            ) AS Sub_Agrupada_Atencion";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

    public function getPromedioClasificacionTriage($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT Nombre_Nivel_Triage, Total_Pacientes,
                CASE WHEN Promedio_Segundos IS NULL THEN 'N/A'
                ELSE CONCAT(
                    CASE WHEN (Promedio_Segundos DIV 86400) > 0 THEN CONCAT(FLOOR(Promedio_Segundos DIV 86400), ' D ') ELSE '' END,
                    CASE WHEN ((MOD(Promedio_Segundos, 86400) DIV 3600) > 0 OR (Promedio_Segundos DIV 86400) > 0) THEN CONCAT(FLOOR((MOD(Promedio_Segundos, 86400) DIV 3600)), ' H ') ELSE '' END,
                    CASE WHEN ((MOD(Promedio_Segundos, 3600) DIV 60) > 0 OR (Promedio_Segundos DIV 86400) > 0 OR (MOD(Promedio_Segundos, 86400) DIV 3600) > 0) THEN CONCAT(FLOOR(MOD(Promedio_Segundos, 3600) DIV 60), ' M ') ELSE '' END
                ) END AS Promedio_Tiempo_Clasificacion
            FROM (
                SELECT Nombre_Nivel_Triage, COUNT(*) AS Total_Pacientes, AVG(Segundos_Espera_Triage) AS Promedio_Segundos
                FROM (
                    SELECT 
                        CASE WHEN B.ClasTria = 1 THEN 'Triage I' WHEN B.ClasTria = 2 THEN 'Triage II' WHEN B.ClasTria = 3 THEN 'Triage III' WHEN B.ClasTria = 4 THEN 'Triage IV' WHEN B.ClasTria = 5 THEN 'Triage V' ELSE 'Sin Clasificar' END AS Nombre_Nivel_Triage,
                        CASE WHEN B.FechTria IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND, CONCAT(A.FechIngr, ' ', A.HoraIngr), CONCAT(B.FechTria, ' ', B.HoraTria)) END AS Segundos_Espera_Triage
                    FROM Admision A
                    LEFT JOIN Triage B ON A.ConsAdmi = B.ConsAdmi
                    INNER JOIN CodiServ D ON A.CodiServ = D.CodiServ
                    LEFT JOIN CodiServ E ON A.ServEgre = E.CodiServ   
                    WHERE A.ViaIngre = 1 AND A.Anulado <> 1 AND D.NombServ IN ('URGENCIAS','URGENCIAS AMBULATORIO','URGENCIAS CONSULTORIO Y TRASLADO') AND ((E.NombServ NOT LIKE '%HOSPITALIZACION%' AND E.NombServ NOT LIKE '%UCI%') OR E.NombServ IS NULL OR B.ConsAdmi IS NOT NULL) AND A.FechDigi BETWEEN ? AND ?
                ) AS Detalle_Interno GROUP BY Nombre_Nivel_Triage
            ) AS Sub_Agrupada";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }

    public function getPromedioEsperaCitaGlobal($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT CONCAT(
                CASE WHEN (AVG(Segundos_Espera_Cita) DIV 86400) > 0 THEN CONCAT(FLOOR(AVG(Segundos_Espera_Cita) DIV 86400), ' Dia(s) ') ELSE '' END,
                CASE WHEN ((MOD(AVG(Segundos_Espera_Cita), 86400) DIV 3600) > 0 OR (AVG(Segundos_Espera_Cita) DIV 86400) > 0) THEN CONCAT(FLOOR((MOD(AVG(Segundos_Espera_Cita), 86400) DIV 3600)), ' Hr(s) ') ELSE '' END,
                FLOOR(MOD(AVG(Segundos_Espera_Cita), 3600) DIV 60), ' Min(s)'
            ) AS Promedio_Global_Espera
            FROM (
                SELECT CASE WHEN A.FechAsig = '0000-00-00' OR A.FechCita = '0000-00-00' OR CONCAT(A.FechCita, ' ', A.HoraCita) < CONCAT(A.FechAsig, ' ', A.HoraAsig) THEN NULL ELSE TIMESTAMPDIFF(SECOND, CONCAT(A.FechAsig, ' ', A.HoraAsig), CONCAT(A.FechCita, ' ', A.HoraCita)) END AS Segundos_Espera_Cita
                FROM DetaCita A
                WHERE A.FechCita BETWEEN ? AND ? AND A.EstaCita <> 1 AND A.NumeUsua IS NOT NULL AND A.NumeUsua <> '' AND A.TipoDocu IS NOT NULL AND A.TipoDocu <> ''
            ) AS subconsulta_tramos";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchColumn() ?: 'N/A';
        } catch (Throwable $e) { return 'N/A'; }
    }

    public function getOportunidadEsperaPorTipo($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT Nombre_Tipo, COUNT(*) AS Total_Citas, CONCAT(
                CASE WHEN (AVG(Segundos_Espera_Cita) DIV 86400) > 0 THEN CONCAT(FLOOR(AVG(Segundos_Espera_Cita) DIV 86400), ' D ') ELSE '' END,
                CASE WHEN ((MOD(AVG(Segundos_Espera_Cita), 86400) DIV 3600) > 0 OR (AVG(Segundos_Espera_Cita) DIV 86400) > 0) THEN CONCAT(FLOOR((MOD(AVG(Segundos_Espera_Cita), 86400) DIV 3600)), ' H ') ELSE '' END
            ) AS Promedio_Espera_Hasta_Cita
            FROM (
                SELECT Tipo.NombTipo AS Nombre_Tipo, CASE WHEN A.FechAsig = '0000-00-00' OR A.FechCita = '0000-00-00' THEN NULL ELSE TIMESTAMPDIFF(SECOND, CONCAT(A.FechAsig, ' ', A.HoraAsig), CONCAT(A.FechCita, ' ', A.HoraCita)) END AS Segundos_Espera_Cita
                FROM DetaCita A
                INNER JOIN TipoServ Tipo ON A.TipoServ = Tipo.CodiTipo
                WHERE A.FechCita BETWEEN ? AND ? AND A.EstaCita <> 1 AND A.NumeUsua IS NOT NULL AND A.NumeUsua <> '' AND A.TipoDocu IS NOT NULL AND A.TipoDocu <> ''
            ) AS subconsulta_tramos GROUP BY Nombre_Tipo ORDER BY Total_Citas DESC LIMIT 8";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) { return []; }
    }
}
PHP;
        makeFile(__DIR__ . '/app/Models/AdmisionesModel.php', $admisionesModelContent);

        // --- FACTURACION MODEL (AGRUPACIÓN DINÁMICA DE TIEMPO) ---
        $facturacionModelContent = <<<'PHP'
<?php
require_once __DIR__ . '/../Config/Database.php';

class FacturacionModel {
    private function getGroupFormat($start, $end) {
        $days = (strtotime($end) - strtotime($start)) / 86400;
        if ($days > 1095) return '%Y'; // Agrupar por año
        if ($days > 90) return '%Y-%m'; // Agrupar por mes
        return '%Y-%m-%d';
    }

    private function fetchSeries(PDO $db, $sql, $params, $valueKey) {
        $result = ['total' => 0.0, 'labels' => [], 'values' => []];
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($row['fecha'])) continue;
                $value = (float)($row[$valueKey] ?? 0);
                $result['labels'][] = (string)$row['fecha'];
                $result['values'][] = round($value, 2);
                $result['total'] += $value;
            }
        } catch (Throwable $e) {}
        return $result;
    }

    public function getData($startDate, $endDate) {
        $start = $startDate . ' 00:00:00';
        $end = $endDate . ' 23:59:59';
        return [
            'facturacion_diaria_valores' => $this->getFacturacionDiariaValues($start, $end),
            'facturacion_diaria_conteo'  => $this->getFacturacionDiariaConteo($start, $end),
            'valor_facturado_eps'        => $this->getFacturacionEPS($start, $end),
            'valor_copagos'              => $this->getFacturacionCopagos($start, $end),
            'notas_credito_sispro'       => $this->getNotasCreditoSispro($startDate, $endDate),
            'total_rips_json'            => $this->getTotalRipsJson($startDate, $endDate)
        ];
    }

    public function getFacturacionDiariaValues($start, $end) {
        $db = Database::getPercona();
        $fmt = $this->getGroupFormat($start, $end);
        $sql = "SELECT DATE_FORMAT(A.FechDocu, '$fmt') AS fecha, SUM(A.ValoTota) AS total FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi=B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont=C.NumeCont AND A.CodiAdmi=C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado=1 AND A.Anulado<>1 AND A.FechDocu BETWEEN ? AND ? GROUP BY fecha ORDER BY fecha ASC";
        return $this->fetchSeries($db, $sql, [$start, $end], 'total');
    }

    public function getFacturacionDiariaConteo($start, $end) {
        $db = Database::getPercona();
        $fmt = $this->getGroupFormat($start, $end);
        $sql = "SELECT DATE_FORMAT(A.FechDocu, '$fmt') AS fecha, COUNT(*) AS total FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi=B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont=C.NumeCont AND A.CodiAdmi=C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado=1 AND A.Anulado<>1 AND A.FechDocu BETWEEN ? AND ? GROUP BY fecha ORDER BY fecha ASC";
        return $this->fetchSeries($db, $sql, [$start, $end], 'total');
    }

    public function getFacturacionEPS($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT SUM(A.ValoEAPB) FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi=B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont=C.NumeCont AND A.CodiAdmi=C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado=1 AND A.Anulado<>1 AND A.FechDocu BETWEEN ? AND ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0.0; }
    }

    public function getFacturacionCopagos($start, $end) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT SUM(CASE WHEN A.ValoEAPB > 0 THEN 0 ELSE A.ValoCopa END) FROM EncaCont A INNER JOIN CodiAdmi B ON A.CodiAdmi = B.CodiAdmi INNER JOIN Contrato C ON A.NumeCont = C.NumeCont AND A.CodiAdmi = C.CodiAdmi WHERE A.CodiDocu IN ('FE','FVE','FEV','SA') AND A.Causado = 1 AND A.Anulado <> 1 AND A.FechDocu BETWEEN ? AND ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$start, $end]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) { return 0.0; }
    }

    public function getNotasCreditoSispro($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT COUNT(*) AS Total_Notas, SUM(CASE WHEN BaseNotas.EstaRips = 'E' THEN 1 ELSE 0 END) AS Enviadas_Exito, SUM(CASE WHEN BaseNotas.EstaRips IN ('P', 'R') AND BaseNotas.FacturaRef LIKE '%(E)%' THEN 1 ELSE 0 END) AS Listas_Para_Gestionar, SUM(CASE WHEN BaseNotas.EstaRips IN ('P', 'R') AND BaseNotas.FacturaRef NOT LIKE '%(E)%' THEN 1 ELSE 0 END) AS Bloqueadas_Por_Factura_No_Enviada FROM ( SELECT CONCAT(fe.CodiDocu,'-',fe.NumeDocu) AS Factura, COALESCE( (SELECT CONCAT(d.TiDoRefe,'-',d.NudoRefe,' (',((case when (SELECT e.EstaRips FROM EnviRips e WHERE e.CodiInst=er.CodiInst AND  e.CodiDocu=d.TiDoRefe AND e.NumeDocu=d.NuDoRefe LIMIT 1) IS NULL then 'P' ELSE (SELECT e.EstaRips FROM EnviRips e WHERE e.CodiInst=er.CodiInst AND  e.CodiDocu=d.TiDoRefe AND e.NumeDocu=d.NuDoRefe LIMIT 1) END ) ),')') FROM DetaNoFa d WHERE d.CodiDocu=fe.CoDoRefe AND d.NumeDocu=fe.NuDoRefe LIMIT 1), (SELECT CONCAT(de.TiDoRefe,'-',de.NuDoRefe,' (',((case when (SELECT e.EstaRips FROM EnviRips e WHERE e.CodiInst=er.CodiInst AND  e.CodiDocu=de.TiDoRefe AND e.NumeDocu=de.NuDoRefe LIMIT 1) IS NULL then 'P' ELSE (SELECT e.EstaRips FROM EnviRips e WHERE e.CodiInst=er.CodiInst AND  e.CodiDocu=de.TiDoRefe AND e.NumeDocu=de.NuDoRefe LIMIT 1) END ) ),')') FROM DetaCont d INNER JOIN DetaCont de ON de.CodiDocu=d.TiDoRefe AND de.NumeDocu=d.NumeDocu WHERE d.CodiDocu=fe.CoDoRefe AND d.NumeDocu=fe.NuDoRefe LIMIT 1) ) AS FacturaRef, (case when en.TiDoTerc!='NI' then 'PARTICULAR' else (case when er.EstaRips IS NULL then 'P' ELSE er.EstaRips END) end) AS EstaRips FROM FactElec AS fe INNER JOIN EncaCont AS en ON ( fe.CodiInst = en.CodiInst AND fe.CoDoRefe = en.CodiDocu AND fe.NuDoRefe = en.NumeDocu) INNER JOIN MaesDocu m ON m.CodiInst=fe.CodiInst AND m.CodiDocu=fe.CodiDocu INNER JOIN EnviRips er ON er.CodiInst=fe.CodiInst AND er.NumeDocu=fe.NumeDocu AND er.CodiDocu=fe.CodiDocu AND er.CodiAno=year(en.FechDocu) LEFT JOIN DetaLiqu dl ON dl.CodiDocu=en.CodiDocu AND dl.NumeDocu=en.NumeDocu WHERE en.FechDocu BETWEEN ? AND ? AND m.DocuApli=127 ) AS BaseNotas WHERE BaseNotas.EstaRips != 'PARTICULAR'";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }

    public function getTotalRipsJson($startDate, $endDate) {
        try {
            $db = Database::getPercona();
            $sql = "SELECT SUM(CASE WHEN Notas.TieneNota IS NULL AND B.EstaRips = 'P' THEN 1 ELSE 0 END) AS Pendientes, SUM(CASE WHEN Notas.TieneNota IS NULL AND B.EstaRips = 'E' THEN 1 ELSE 0 END) AS Enviados, SUM(CASE WHEN Notas.TieneNota IS NULL AND B.EstaRips = 'R' THEN 1 ELSE 0 END) AS Rechazados, SUM(CASE WHEN Notas.TieneNota = 1 THEN 1 ELSE 0 END) AS Anulados_Con_Nota, COUNT(*) AS Total_General FROM EncaCont A INNER JOIN EnviRips B ON A.CodiDocu = B.CodiDocu AND A.NumeDocu = B.NumeDocu INNER JOIN CodiAdmi C ON A.CodiAdmi = C.CodiAdmi LEFT JOIN ( SELECT DISTINCT Nota_B.NudoRefe, 1 AS TieneNota FROM EncaCont Nota_A INNER JOIN DetaCont Nota_B ON Nota_A.CodiInst = Nota_B.CodiInst AND Nota_A.CodiDocu = Nota_B.CodiDocu AND Nota_A.NumeDocu = Nota_B.NumeDocu WHERE Nota_A.CodiDocu IN ('NCC','NCF') AND Nota_B.TiDoRefe = 'FE' ) Notas ON Notas.NudoRefe = A.NumeDocu WHERE A.CodiDocu = 'FE' AND A.TipoCont IN (1,3,4) AND C.NombAdmi NOT LIKE 'PARTI%' AND A.FechDocu BETWEEN ? AND ? AND A.TiDoTerc = 'NI'";
            $stmt = $db->prepare($sql);
            $stmt->execute([$startDate, $endDate]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) { return []; }
    }
}
PHP;
        makeFile(__DIR__ . '/app/Models/FacturacionModel.php', $facturacionModelContent);

        // --- 9. AI MODEL (MÁXIMA POTENCIA PARA SERVIDOR DEDICADO 12 HILOS) ---
        $aiModelContent = <<<'PHP'
<?php
require_once __DIR__ . '/../Config/Database.php';

class AiModel {
    public function generarInforme($startDate, $endDate, $dataArray) {
        $config = include __DIR__ . '/../../.vynet_config.php';
        $ollamaUrl = $config['ollama_url'] ?? 'http://127.0.0.1:11434';
        $ollamaModel = $config['ollama_model'] ?? 'qwen2.5:14b'; 
        
        $totalPacientes = $dataArray['asistencial_y_citas']['pacientes_por_dia']['total'] ?? 0;
        $totalFacturado = $dataArray['financiero_y_facturacion']['facturacion_diaria_valores']['total'] ?? 0.0;

        if ($totalPacientes == 0 && $totalFacturado == 0.0) {
            return "### Aviso de Auditoría\nNo se encontraron registros activos para el periodo seleccionado.";
        }

        $resumenDatos = [
            'periodo' => "$startDate al $endDate", 'pacientes' => $totalPacientes,
            'citas' => $dataArray['asistencial_y_citas']['total_citas_asignadas'] ?? 0,
            'urgencias' => $dataArray['asistencial_y_citas']['pacientes_por_urgencias'] ?? 0,
            'facturacion' => $totalFacturado, 'eps' => $dataArray['financiero_y_facturacion']['valor_facturado_eps'] ?? 0,
            'copagos' => $dataArray['financiero_y_facturacion']['valor_copagos'] ?? 0
        ];

        $prompt = "Actúa como Auditor Médico. Redacta un informe corporativo muy breve en Markdown basado en: " . json_encode($resumenDatos) . ". Estructura obligatoria: ### 1. Resumen Ejecutivo ### 2. Gestión Operativa ### 3. Rendimiento Financiero ### 4. Recomendaciones";

        $url = rtrim($ollamaUrl, '/') . '/api/generate';
        
        // CONFIGURACIÓN DE RENDIMIENTO: 12 Hilos Activos + Contexto Reducido para velocidad de rayo
        $payload = json_encode([
            'model' => $ollamaModel,
            'prompt' => $prompt,
            'stream' => false,
            'options' => [
                'temperature' => 0.1, 
                'num_predict' => 600,  
                'num_thread' => 12,    
                'num_ctx' => 2048      
            ]
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1200); // 20 Minutos Timeout extremo
        
        $response = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        
        if ($curlErrno) {
            return "## Informe No Disponible\nEl motor de análisis local no respondió a tiempo ($curlError).";
        }

        $result = json_decode($response, true);
        
        if (isset($result['error'])) {
            return "## Error del Motor IA\nEl servidor rechazó la solicitud: **" . htmlspecialchars($result['error']) . "**";
        }

        if (isset($result['response'])) {
            return trim(preg_replace('/<think>.*?<\/think>/s', '', $result['response']));
        }
        
        return "## Error\nEl motor local devolvió una respuesta vacía o irreconocible.";
    }

    public function guardarFeedback($informe, $calificacion) {
        try {
            $db = Database::getMariaDB();
            $stmt = $db->prepare("INSERT INTO ia_feedback (informe_texto, calificacion) VALUES (?, ?)");
            $stmt->execute([$informe, $calificacion]);
            return true;
        } catch (Exception $e) { return false; }
    }
}
PHP;
        makeFile(__DIR__ . '/app/Models/AiModel.php', $aiModelContent);

        // --- 10. AUTH & DASHBOARD CONTROLLERS ---
        $authControllerContent = <<<'PHP'
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
PHP;
        makeFile(__DIR__ . '/app/Controllers/AuthController.php', $authControllerContent);

        $dashControllerContent = <<<'PHP'
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
PHP;
        makeFile(__DIR__ . '/app/Controllers/DashboardController.php', $dashControllerContent);

        // --- 11. LOGIN VIEW ---
        $loginViewContent = <<<'PHP'
<?php
$config = file_exists(__DIR__ . '/../../../.vynet_config.php') ? include(__DIR__ . '/../../../.vynet_config.php') : ['hospital_name' => 'Plataforma Gerencial', 'theme_color' => 'oscuro'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso - <?= htmlspecialchars($config['hospital_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-corp {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
    </style>
</head>
<body class="bg-slate-950 flex items-center justify-center min-h-screen">
    <div class="glass-corp p-10 rounded-3xl w-full max-w-md relative overflow-hidden border-t-2 border-indigo-500">
        <div class="text-center mb-8">
            <div class="bg-indigo-600 text-white w-16 h-16 mx-auto flex items-center justify-center rounded-2xl shadow-lg mb-4">
                <i class="fa-solid fa-hospital-user text-3xl"></i>
            </div>
            <h2 class="text-xl font-bold text-white"><?= htmlspecialchars($config['hospital_name']) ?></h2>
            <p class="text-xs text-indigo-400 mt-1 uppercase tracking-widest font-extrabold">Panel Gerencial Analítico</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-950/80 text-red-300 p-3 rounded-xl text-sm mb-4 text-center border border-red-500/30 font-semibold">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="space-y-4">
            <input type="hidden" name="route" value="login">
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-1">Usuario</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" required class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-1">Contraseña</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-500"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" name="password" required class="w-full bg-slate-900 border border-slate-700 rounded-xl pl-10 pr-4 py-3 text-white text-sm focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>
            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 rounded-xl transition shadow-lg text-sm mt-2 uppercase tracking-wider">
                Iniciar Sesión
            </button>
        </form>
    </div>
</body>
</html>
PHP;
        makeFile(__DIR__ . '/app/Views/auth/login.php', $loginViewContent);

        // --- 12. LAYOUT DASHBOARD ---
        $layoutViewContent = <<<'PHP'
<?php
$userTheme = $_SESSION['user']['theme_pref'] ?? ($config['theme_color'] ?? 'oscuro');

$themesMap = [
    'oscuro' => [
        'sidebar' => 'bg-[#0b0f19]', 
        'bg' => '#070a13', 
        'card' => 'bg-gradient-to-br from-slate-900/90 via-slate-900/70 to-slate-950', 
        'border' => 'border-slate-800', 
        'header' => 'bg-slate-900/80',
        'text' => 'text-white'
    ],
    'claro' => [
        'sidebar' => 'bg-slate-50', 
        'bg' => '#f1f5f9', 
        'card' => 'bg-white shadow-[0_10px_30px_rgba(0,0,0,0.05)]', 
        'border' => 'border-slate-200', 
        'header' => 'bg-white/80',
        'text' => 'text-slate-800'
    ],
    'azul' => [
        'sidebar' => 'bg-[#0a192f]', 
        'bg' => '#020c1b', 
        'card' => 'bg-gradient-to-br from-[#112240] via-[#112240] to-[#0a192f]', 
        'border' => 'border-[#233554]', 
        'header' => 'bg-[#0a192f]/80',
        'text' => 'text-slate-200'
    ],
    'esmeralda' => [
        'sidebar' => 'bg-[#022c22]', 
        'bg' => '#064e3b', 
        'card' => 'bg-emerald-950/80', 
        'border' => 'border-emerald-800/50', 
        'header' => 'bg-emerald-900/80',
        'text' => 'text-emerald-50'
    ]
];
$activeTheme = $themesMap[$userTheme] ?? $themesMap['oscuro'];

$currentTab = $_SESSION['current_route'] ?? 'home';
$e = static function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$js = static function ($value) { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK); };
$currency = static function ($value) { return '$ ' . number_format((float)$value, 0, ',', '.'); };
$number = static function ($value) { return number_format((int)$value, 0, ',', '.'); };

$navItem = function($route, $icon, $label, $isActive, $isNew = false) use ($userTheme) {
    $activeClass = $isActive ? 'bg-indigo-600 text-white font-extrabold shadow-lg border-l-4 border-indigo-400' : 'text-slate-400 hover:bg-black/10 hover:text-indigo-400 font-medium transition-all';
    $badge = $isNew ? '<span class="ml-auto bg-indigo-500 text-white text-[9px] font-black px-2 py-0.5 rounded shadow">IA</span>' : '';
    return "<a href='#' onclick=\"event.preventDefault(); navigateTo('{$route}');\" class='flex items-center px-4 py-3 mx-2 rounded-xl {$activeClass}'><i class='fa-solid {$icon} w-6 text-center'></i><span class='ml-3 text-xs tracking-wide'>{$label}</span>{$badge}</a>";
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($config['hospital_name']) ?> - Gerencia Analítica</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<script defer src="https://cdn.jsdelivr.net/npm/chart.js@4.5.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
    body { background-color: <?= $activeTheme['bg'] ?>; color: <?= $userTheme === 'claro' ? '#1e293b' : '#f8fafc' ?>; font-family: 'Inter', system-ui, sans-serif; }
    .card-4d { 
        background: <?= $userTheme === 'claro' ? '#ffffff' : ($userTheme === 'azul' ? 'linear-gradient(135deg, #112240, #0a192f)' : 'rgba(15, 23, 42, 0.8)') ?>;
        border: 1px solid <?= $userTheme === 'claro' ? '#e2e8f0' : 'rgba(255, 255, 255, 0.05)' ?>;
        box-shadow: <?= $userTheme === 'claro' ? '0 10px 25px -5px rgba(0, 0, 0, 0.05)' : '0 20px 40px -10px rgba(0,0,0,0.5), inset 0 1px 1px rgba(255,255,255,0.05)' ?>;
        border-radius: 1.25rem; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }
    .card-4d:hover { transform: translateY(-4px) scale(1.01); box-shadow: <?= $userTheme === 'claro' ? '0 20px 30px -10px rgba(0,0,0,0.1)' : '0 30px 60px -15px rgba(0,0,0,0.6), inset 0 1px 2px rgba(255,255,255,0.1)' ?>; }
    .card-4d::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, #4f46e5, #10b981); opacity: <?= $userTheme === 'claro' ? '0.2' : '0.8' ?>; }
    .nav-header { font-size: 0.6rem; font-weight: 900; letter-spacing: 0.15em; color: <?= $userTheme === 'claro' ? '#64748b' : '#94a3b8' ?>; text-transform: uppercase; padding: 1.25rem 1.25rem 0.5rem; }
    .prose h1, .prose h2, .prose h3 { color: <?= $userTheme === 'claro' ? '#0f172a' : '#f8fafc' ?>; }
    select option { background-color: #1e293b; color: #f8fafc; }
</style>
</head>
<body class="flex h-screen overflow-hidden antialiased">
<form id="mainNavForm" method="POST" action="" style="display:none;"><input type="hidden" name="route" id="mainNavRoute" value=""></form>
<script>
function navigateTo(route) { document.getElementById('mainNavRoute').value = route; document.getElementById('mainNavForm').submit(); }
function cambiarTemaVisual(tema) { const fd = new URLSearchParams(); fd.append('route', 'cambiar_tema'); fd.append('theme', tema); fetch('index.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: fd.toString() }).then(r => r.json()).then(res => { if(res.success) location.reload(); }); }
</script>

<aside class="<?= $activeTheme['sidebar'] ?> w-64 flex-shrink-0 flex flex-col h-full shadow-2xl relative z-20 border-r <?= $activeTheme['border'] ?>">
    <div class="h-20 flex items-center px-5 border-b <?= $activeTheme['border'] ?> shrink-0">
        <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow mr-3"><i class="fa-solid fa-hospital-user text-lg"></i></div>
        <div class="min-w-0"><h1 class="<?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> font-bold text-xs tracking-wide leading-tight truncate"><?= $e($config['hospital_name']) ?></h1><p class="text-[10px] text-indigo-500 font-bold mt-0.5">Plataforma Gerencial</p></div>
    </div>
    <div class="flex-1 overflow-y-auto py-3 flex flex-col gap-1">
        <div class="nav-header">Mando Central</div>
        <?= $navItem('home', 'fa-chart-pie', 'Dashboard Ejecutivo', $currentTab === 'home') ?>
        <div class="nav-header mt-1">Inteligencia Artificial</div>
        <?= $navItem('auditoria', 'fa-brain', 'Auditoría Gerencial', $currentTab === 'auditoria', true) ?>
        <div class="nav-header mt-1">Reportes Operativos</div>
        <?= $navItem('admisiones', 'fa-user-injured', 'Asistencial & Citas', $currentTab === 'admisiones') ?>
        <?= $navItem('facturacion', 'fa-file-invoice-dollar', 'Facturación & RIPS', $currentTab === 'facturacion') ?>
    </div>
    <div class="p-4 border-t <?= $activeTheme['border'] ?> shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-indigo-600/20 text-indigo-500 grid place-items-center"><i class="fa-solid fa-user-shield text-xs"></i></div>
            <div class="flex-1 min-w-0"><p class="text-xs font-bold <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> truncate"><?= $e($_SESSION['user']['username'] ?? 'Usuario') ?></p><p class="text-[9px] text-emerald-500 font-bold"><i class="fa-solid fa-circle text-[7px] mr-1"></i>Sesión Segura</p></div>
            <a href="?logout=true" title="Cerrar Sesión" class="text-slate-400 hover:text-red-500 transition-colors p-1.5"><i class="fa-solid fa-power-off text-sm"></i></a>
        </div>
    </div>
</aside>

<main class="flex-1 flex flex-col h-full overflow-hidden relative">
    <header class="h-16 <?= $activeTheme['header'] ?> border-b <?= $activeTheme['border'] ?> flex items-center justify-between px-6 shrink-0 z-10 backdrop-blur-md">
        <div class="flex items-center text-xs font-semibold text-slate-400">
            <span class="text-indigo-500 font-bold uppercase tracking-wider">Enterprise Analytics</span> <span class="text-slate-500 mx-2">/</span> 
            <span class="<?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> font-bold"><?= $currentTab === 'auditoria' ? 'Auditoría Estratégica' : 'Panel de Control' ?></span>
        </div>
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 <?= $userTheme === 'claro' ? 'bg-white border-slate-200' : 'bg-black/20 border-white/10' ?> px-2.5 py-1 rounded-xl border">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Tema:</span>
                <select onchange="cambiarTemaVisual(this.value)" class="bg-transparent <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> text-xs font-semibold outline-none cursor-pointer">
                    <option value="oscuro" <?= $userTheme === 'oscuro' ? 'selected' : '' ?>>Oscuro Corporativo</option>
                    <option value="claro" <?= $userTheme === 'claro' ? 'selected' : '' ?>>Claro Institucional</option>
                    <option value="azul" <?= $userTheme === 'azul' ? 'selected' : '' ?>>Azul Médico</option>
                    <option value="esmeralda" <?= $userTheme === 'esmeralda' ? 'selected' : '' ?>>Esmeralda Clínico</option>
                </select>
            </div>
            <form id="filterForm" method="POST" action="" class="flex items-center gap-2 <?= $userTheme === 'claro' ? 'bg-white border-slate-200' : 'bg-black/20 border-white/10' ?> p-1 rounded-xl border">
                <input type="hidden" name="route" id="filterRoute" value="<?= $e($currentTab) ?>">
                <?php if ($currentTab === 'home'): ?>
                    <span class="text-xs font-bold text-slate-500 ml-2">Día:</span><input type="date" name="start_date" id="start_date" value="<?= $e($startDate) ?>" class="text-xs border-0 bg-transparent <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> font-medium focus:outline-none cursor-pointer">
                <?php else: ?>
                    <span class="text-xs font-bold text-slate-500 ml-2">Desde:</span><input type="date" name="start_date" id="start_date" value="<?= $e($startDate) ?>" class="text-xs border-0 bg-transparent <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> font-medium focus:outline-none cursor-pointer">
                    <span class="text-xs font-bold text-slate-500">Hasta:</span><input type="date" name="end_date" id="end_date" value="<?= $e($endDate) ?>" class="text-xs border-0 bg-transparent <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> font-medium focus:outline-none cursor-pointer">
                <?php endif; ?>
                <?php if ($currentTab !== 'auditoria'): ?>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1 rounded-lg text-xs font-bold transition shadow"><i class="fa-solid fa-filter mr-1"></i> Filtrar</button>
                <?php endif; ?>
            </form>
        </div>
    </header>

    <div class="flex-1 overflow-y-auto p-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-2xl font-black <?= $userTheme === 'claro' ? 'text-slate-900' : 'text-white' ?> tracking-tight">
                    <?php if ($currentTab === 'home'): ?>Dashboard Ejecutivo<?php elseif ($currentTab === 'admisiones'): ?>Reporte Asistencial y Citas<?php elseif ($currentTab === 'facturacion'): ?>Reporte Financiero y RIPS<?php else: ?>Inteligencia Estratégica<?php endif; ?>
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-1"><?= $e($config['hospital_name']) ?> · Periodo: <?= $e($startDate) ?> al <?= $e($endDate) ?></p>
            </div>
            <?php if ($currentTab === 'auditoria'): ?>
                <div class="flex items-center gap-3">
                    <button onclick="descargarPDF()" id="btnExportarPDF" class="hidden bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-4 rounded-xl transition items-center gap-2 text-xs"><i class="fa-solid fa-file-pdf text-red-400"></i> Exportar PDF</button>
                    <button onclick="generarAuditoria()" id="btnGenerarAuditoria" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 px-5 rounded-xl transition flex items-center gap-2 text-xs shadow-lg"><i class="fa-solid fa-brain"></i> Procesar Informe</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($currentTab === 'home'): ?>
            <div class="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-widest flex items-center"><i class="fa-solid fa-chart-pie mr-2 text-indigo-500"></i> Desempeño Financiero</div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card-4d p-6 flex flex-col justify-between"><div class="flex justify-between items-start"><p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Total Facturado</p><i class="fa-solid fa-wallet text-indigo-500"></i></div><div><p class="text-2xl font-black <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> mt-2"><?= $currency($data['facturacion_mes']) ?></p></div><div class="h-16 mt-2"><canvas id="miniChartFact"></canvas></div></div>
                <div class="card-4d p-6 flex flex-col justify-between"><div class="flex justify-between items-start"><p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Facturado EPS</p><i class="fa-solid fa-building-columns text-emerald-500"></i></div><div><p class="text-2xl font-black <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> mt-2"><?= $currency($data['valor_eps']) ?></p></div><div class="h-16 mt-2"><canvas id="miniChartEps"></canvas></div></div>
                <div class="card-4d p-6 flex flex-col justify-between"><div class="flex justify-between items-start"><p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Valor Copagos</p><i class="fa-solid fa-hand-holding-dollar text-amber-500"></i></div><div><p class="text-2xl font-black <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> mt-2"><?= $currency($data['valor_copagos']) ?></p></div><div class="h-16 mt-2"><canvas id="miniChartCop"></canvas></div></div>
            </div>

            <div class="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-widest flex items-center"><i class="fa-solid fa-stethoscope mr-2 text-indigo-500"></i> Tiempos y Operatividad</div>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-5 mb-8">
                <div class="card-4d p-5 text-center"><p class="text-[10px] font-bold text-slate-500 uppercase">Pacientes Hoy</p><p class="text-3xl font-black <?= $userTheme === 'claro' ? 'text-indigo-600' : 'text-white' ?> mt-2"><?= $number($data['pacientes_hoy']) ?></p></div>
                <div class="card-4d p-5 text-center"><p class="text-[10px] font-bold text-slate-500 uppercase">Triage Hoy</p><p class="text-3xl font-black <?= $userTheme === 'claro' ? 'text-indigo-600' : 'text-white' ?> mt-2"><?= $number($data['urgencias']) ?></p></div>
                <div class="card-4d p-5 text-center border-b-4 border-amber-500"><p class="text-[10px] font-bold text-slate-500 uppercase">Urgencias Activas</p><p class="text-3xl font-black <?= $userTheme === 'claro' ? 'text-amber-600' : 'text-white' ?> mt-2"><?= $number($data['urgencias_hoy']) ?></p></div>
                <div class="card-4d p-5 text-center border-b-4 border-rose-500"><p class="text-[10px] font-bold text-slate-500 uppercase">Pend. Clasificar</p><p class="text-3xl font-black <?= $userTheme === 'claro' ? 'text-rose-600' : 'text-white' ?> mt-2"><?= $number($data['pendientes_clasificar']) ?></p></div>
                <div class="card-4d p-5 text-center"><p class="text-[10px] font-bold text-slate-500 uppercase">Citas Hoy</p><p class="text-3xl font-black <?= $userTheme === 'claro' ? 'text-indigo-600' : 'text-white' ?> mt-2"><?= $number($data['citas_hoy']) ?></p></div>
            </div>

        <?php elseif ($currentTab === 'auditoria'): ?>
            <div id="pdf-wrapper" class="card-4d p-10 w-full min-h-[500px] relative flex flex-col">
                <div id="ia-intro" class="text-center py-16">
                    <div class="w-20 h-20 bg-indigo-600/10 text-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-5 border border-indigo-500/20 shadow-inner"><i class="fa-solid fa-brain text-4xl"></i></div>
                    <h3 class="text-xl font-bold <?= $userTheme === 'claro' ? 'text-slate-800' : 'text-white' ?> mb-2">Auditoría Ejecutiva</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto leading-relaxed">Reporte analítico generado en milisegundos para el periodo <strong><?= $e($startDate) ?> al <?= $e($endDate) ?></strong>.</p>
                </div>
                <div id="ia-loading" class="hidden flex-col items-center justify-center py-24 flex-1">
                    <i class="fa-solid fa-circle-notch fa-spin text-5xl text-indigo-500 mb-4"></i><p class="text-sm font-bold <?= $userTheme === 'claro' ? 'text-slate-700' : 'text-white' ?>">Consolidando métricas operativas y financieras...</p>
                </div>
                <div id="ia-result" class="hidden flex-1 overflow-auto p-4">
                    <div id="pdf-header" class="hidden border-b-2 border-slate-200 pb-4 mb-6"><h2 class="text-3xl font-black text-slate-900 mb-1"><?= $e($config['hospital_name']) ?></h2><p class="text-sm font-bold text-indigo-700">INFORME DE AUDITORÍA EJECUTIVA</p></div>
                    <div id="ia-markdown-content" class="prose <?= $userTheme === 'claro' ? '' : 'prose-invert' ?> max-w-none text-sm"></div>
                    <div id="ia-feedback-panel" class="mt-10 <?= $userTheme === 'claro' ? 'bg-slate-50 border-slate-200' : 'bg-black/30 border-white/10' ?> border rounded-2xl p-5 text-center">
                        <h4 class="text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Evaluación de Calidad del Informe</h4>
                        <div class="flex justify-center gap-4 mt-3"><button onclick="enviarFeedback(5)" class="bg-emerald-600/20 hover:bg-emerald-600/40 text-emerald-600 border border-emerald-500/30 font-bold py-1.5 px-4 rounded-xl text-xs transition"><i class="fa-solid fa-thumbs-up mr-1"></i> Excelente</button><button onclick="enviarFeedback(1)" class="bg-rose-600/20 hover:bg-rose-600/40 text-rose-600 border border-rose-500/30 font-bold py-1.5 px-4 rounded-xl text-xs transition"><i class="fa-solid fa-thumbs-down mr-1"></i> Necesita Ajustes</button></div><p id="feedback-status" class="text-xs font-bold mt-2 text-indigo-500 hidden"></p>
                    </div>
                </div>
            </div>

        <?php elseif ($currentTab === 'admisiones'): ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Ingresos y Triage</h3><div class="h-64"><canvas id="pacientesChart"></canvas></div></div>
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Citas Asignadas</h3><div class="h-64"><canvas id="citasChart"></canvas></div></div>
            </div>
            
            <div class="mb-3 text-[11px] font-bold text-slate-500 uppercase tracking-widest flex items-center"><i class="fa-solid fa-stopwatch mr-2 text-indigo-500"></i> Desempeño y Tiempos de Oportunidad</div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Promedio de Clasificación Triage</h3><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="<?= $userTheme === 'claro' ? 'bg-slate-50' : 'bg-black/20' ?> text-slate-500 uppercase font-bold"><tr><th class="p-2.5">Nivel Triage</th><th class="p-2.5 text-center">Pacientes</th><th class="p-2.5 text-right">Tiempo Promedio</th></tr></thead><tbody class="divide-y <?= $userTheme === 'claro' ? 'divide-slate-100' : 'divide-white/5' ?> font-medium"><?php if (!empty($data['promedio_tiempo_clasificacion'])): foreach($data['promedio_tiempo_clasificacion'] as $ptc): ?><tr><td class="p-2.5 font-bold"><?= $e($ptc['Nombre_Nivel_Triage']) ?></td><td class="p-2.5 text-center"><?= $number($ptc['Total_Pacientes']) ?></td><td class="p-2.5 text-right text-indigo-500"><?= $e($ptc['Promedio_Tiempo_Clasificacion']) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Sin registros.</td></tr><?php endif; ?></tbody></table></div></div>
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Tiempo hasta Atención Médica</h3><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="<?= $userTheme === 'claro' ? 'bg-slate-50' : 'bg-black/20' ?> text-slate-500 uppercase font-bold"><tr><th class="p-2.5">Nivel Triage</th><th class="p-2.5 text-center">Espera Médica</th><th class="p-2.5 text-right">Derivados</th></tr></thead><tbody class="divide-y <?= $userTheme === 'claro' ? 'divide-slate-100' : 'divide-white/5' ?> font-medium"><?php if (!empty($data['promedio_tiempo_atencion_medico'])): foreach($data['promedio_tiempo_atencion_medico'] as $pam): ?><tr><td class="p-2.5 font-bold"><?= $e($pam['Nombre_Nivel_Triage']) ?></td><td class="p-2.5 text-center text-amber-500"><?= $e($pam['Promedio_Tiempo_Atencion_Medico']) ?></td><td class="p-2.5 text-right"><?= $number($pam['Total_Derivados']) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Sin registros.</td></tr><?php endif; ?></tbody></table></div></div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Estado de RDA</h3><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="<?= $userTheme === 'claro' ? 'bg-slate-50' : 'bg-black/20' ?> text-slate-500 uppercase font-bold"><tr><th class="p-2.5">Tipo RDA</th><th class="p-2.5 text-center">Total</th><th class="p-2.5 text-center text-emerald-500">Enviados</th><th class="p-2.5 text-center text-amber-500">Pendientes</th><th class="p-2.5 text-center text-rose-500">Rechazados</th></tr></thead><tbody class="divide-y <?= $userTheme === 'claro' ? 'divide-slate-100' : 'divide-white/5' ?> font-medium"><?php if (!empty($data['estado_rda'])): foreach($data['estado_rda'] as $r): ?><tr><td class="p-2.5 font-bold"><?= $e($r['TIPO_RDA']) ?></td><td class="p-2.5 text-center"><?= $number($r['TOTAL']) ?></td><td class="p-2.5 text-center text-emerald-500"><?= $number($r['ENVIADOS']) ?></td><td class="p-2.5 text-center text-amber-500"><?= $number($r['PENDIENTES']) ?></td><td class="p-2.5 text-center text-rose-500"><?= $number($r['RECHAZADOS']) ?></td></tr><?php endforeach; else: ?><tr><td colspan="5" class="p-4 text-center text-slate-500">Sin registros.</td></tr><?php endif; ?></tbody></table></div></div>
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Desglose de Citas</h3><div class="h-48 relative"><canvas id="citasDonutChart"></canvas><script>document.addEventListener("DOMContentLoaded",function(){const ctx=document.getElementById("citasDonutChart").getContext("2d");new Chart(ctx,{type:"doughnut",data:{labels:["Cumplidas","Pendientes","Canceladas","Ausencias"],datasets:[{data:[<?= ($data['indicador_cumplimiento_citas']['total_cumplidas']??0) ?>,<?= ($data['indicador_cumplimiento_citas']['citas_pendientes']??0) ?>,<?= ($data['indicador_cumplimiento_citas']['total_canceladas']??0) ?>,<?= (($data['indicador_cumplimiento_citas']['ausentismo_paciente']??0)+($data['indicador_cumplimiento_citas']['ausentismo_medico']??0)) ?>],backgroundColor:["#10b981","#f59e0b","#64748b","#f43f5e"],borderWidth:0}]},options:{responsive:true,maintainAspectRatio:false,cutout:"75%",plugins:{legend:{position:"right",labels:{color:"#94a3b8"}}}}});});</script></div></div>
            </div>
            
            <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Oportunidad de Citas por Especialidad</h3><div class="overflow-x-auto"><table class="w-full text-left text-xs"><thead class="<?= $userTheme === 'claro' ? 'bg-slate-50' : 'bg-black/20' ?> text-slate-500 uppercase font-bold"><tr><th class="p-2.5">Especialidad / Servicio</th><th class="p-2.5 text-center">Total Citas Asignadas</th><th class="p-2.5 text-right">Promedio Espera Global</th></tr></thead><tbody class="divide-y <?= $userTheme === 'claro' ? 'divide-slate-100' : 'divide-white/5' ?> font-medium"><?php if (!empty($data['oportunidad_espera_por_tipo'])): foreach($data['oportunidad_espera_por_tipo'] as $o): ?><tr><td class="p-2.5 font-bold"><?= $e($o['Nombre_Tipo']) ?></td><td class="p-2.5 text-center"><?= $number($o['Total_Citas']) ?></td><td class="p-2.5 text-right text-indigo-500"><?= $e($o['Promedio_Espera_Hasta_Cita']) ?></td></tr><?php endforeach; else: ?><tr><td colspan="3" class="p-4 text-center text-slate-500">Sin datos de oportunidad.</td></tr><?php endif; ?></tbody></table></div></div>

        <?php elseif ($currentTab === 'facturacion'): ?>
            <div class="card-4d p-6 mb-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Evolución de Facturación Diaria</h3><div class="h-72"><canvas id="facturacionChart"></canvas></div></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Notas Crédito Sispro</h3><div class="grid grid-cols-2 gap-4"><div class="<?= $userTheme === 'claro' ? 'bg-slate-50 border-slate-200' : 'bg-black/20 border-white/10' ?> p-4 rounded-xl border text-center"><p class="text-[10px] font-bold text-slate-500 uppercase">Total Notas</p><p class="text-xl font-black mt-1"><?= $number($data['notas_credito_sispro']['Total_Notas'] ?? 0) ?></p></div><div class="<?= $userTheme === 'claro' ? 'bg-emerald-50 border-emerald-200' : 'bg-emerald-950/30 border-emerald-900/30' ?> p-4 rounded-xl border text-center"><p class="text-[10px] font-bold text-emerald-500 uppercase">Enviadas</p><p class="text-xl font-black text-emerald-500 mt-1"><?= $number($data['notas_credito_sispro']['Enviadas_Exito'] ?? 0) ?></p></div><div class="<?= $userTheme === 'claro' ? 'bg-amber-50 border-amber-200' : 'bg-amber-950/30 border-amber-900/30' ?> p-4 rounded-xl border text-center"><p class="text-[10px] font-bold text-amber-500 uppercase">Listas Gestión</p><p class="text-xl font-black text-amber-500 mt-1"><?= $number($data['notas_credito_sispro']['Listas_Para_Gestionar'] ?? 0) ?></p></div><div class="<?= $userTheme === 'claro' ? 'bg-rose-50 border-rose-200' : 'bg-rose-950/30 border-rose-900/30' ?> p-4 rounded-xl border text-center"><p class="text-[10px] font-bold text-rose-500 uppercase">Bloqueadas</p><p class="text-xl font-black text-rose-500 mt-1"><?= $number($data['notas_credito_sispro']['Bloqueadas_Por_Factura_No_Enviada'] ?? 0) ?></p></div></div></div>
                <div class="card-4d p-6"><h3 class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-4">Consolidado RIPS JSON</h3><div class="grid grid-cols-2 md:grid-cols-3 gap-4"><div class="<?= $userTheme === 'claro' ? 'bg-slate-50 border-slate-200' : 'bg-black/20 border-white/10' ?> p-3 rounded-xl border text-center"><p class="text-[9px] font-bold text-slate-500 uppercase">Total General</p><p class="text-lg font-black mt-1"><?= $number($data['total_rips_json']['Total_General'] ?? 0) ?></p></div><div class="<?= $userTheme === 'claro' ? 'bg-emerald-50 border-emerald-200' : 'bg-emerald-950/30 border-emerald-900/30' ?> p-3 rounded-xl border text-center"><p class="text-[9px] font-bold text-emerald-500 uppercase">Enviados</p><p class="text-lg font-black text-emerald-500 mt-1"><?= $number($data['total_rips_json']['Enviados'] ?? 0) ?></p></div><div class="<?= $userTheme === 'claro' ? 'bg-amber-50 border-amber-200' : 'bg-amber-950/30 border-amber-900/30' ?> p-3 rounded-xl border text-center"><p class="text-[9px] font-bold text-amber-500 uppercase">Pendientes</p><p class="text-lg font-black text-amber-500 mt-1"><?= $number($data['total_rips_json']['Pendientes'] ?? 0) ?></p></div></div></div>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function(){
    if(typeof Chart !== 'undefined'){
        Chart.defaults.color = '<?= $userTheme === 'claro' ? '#64748b' : '#94a3b8' ?>';
        Chart.defaults.font.family = "'Inter', 'system-ui', 'sans-serif'";
        
        const createChart = (id, type, labels, data, color, isMoney = false, isArea = false) => {
            const el = document.getElementById(id);
            if(!el) return;
            const ctx = el.getContext('2d');
            let bg = color;
            if(isArea) {
                bg = ctx.createLinearGradient(0, 0, 0, 300);
                bg.addColorStop(0, color + '55');
                bg.addColorStop(1, color + '00');
            }
            new Chart(ctx, {
                type: type,
                data: { labels: labels, datasets: [{ data: data, backgroundColor: bg, borderColor: color, borderWidth: type==='line'? 3 : 0, fill: isArea, tension: 0.4, borderRadius: type === 'bar' ? 6 : 0 }] },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false }, ticks: { maxTicksLimit: 12, maxRotation: 45, minRotation: 0 } }, y: { grid: { color: '<?= $userTheme === 'claro' ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.05)' ?>' }, ticks: { callback: function(v) { return isMoney ? '$' + (v/1000000).toFixed(1) + 'M' : v; } } } } }
            });
        };

        <?php if ($currentTab === 'home'): ?>
            createChart('miniChartFact', 'line', ['1','2','3','4','5'], [10,20,15,30,40], '#6366f1', false, true);
            createChart('miniChartEps', 'line', ['1','2','3','4','5'], [5,10,8,20,35], '#10b981', false, true);
            createChart('miniChartCop', 'line', ['1','2','3','4','5'], [2,4,3,8,12], '#f59e0b', false, true);
        <?php elseif ($currentTab === 'admisiones'): ?>
            createChart('pacientesChart', 'bar', <?= $js($data['pacientes_por_dia']['labels']) ?>, <?= $js($data['pacientes_por_dia']['values']) ?>, '#6366f1');
            createChart('citasChart', 'line', <?= $js($data['citas_asignadas_por_dia']['labels']) ?>, <?= $js($data['citas_asignadas_por_dia']['values']) ?>, '#f59e0b', false, true);
        <?php elseif ($currentTab === 'facturacion'): ?>
            createChart('facturacionChart', 'line', <?= $js($data['facturacion_diaria_valores']['labels']) ?>, <?= $js($data['facturacion_diaria_valores']['values']) ?>, '#10b981', true, true);
        <?php endif; ?>
    }
});

let currentReporteGenerado = "";
function generarAuditoria() {
    const btn = document.getElementById('btnGenerarAuditoria');
    const intro = document.getElementById('ia-intro');
    const loader = document.getElementById('ia-loading');
    const resultDiv = document.getElementById('ia-result');
    const markdownContent = document.getElementById('ia-markdown-content');
    const start = document.getElementById('start_date').value;
    const end = document.getElementById('end_date').value;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Procesando...';
    intro.classList.add('hidden');
    resultDiv.classList.add('hidden');
    loader.classList.remove('hidden');
    loader.classList.add('flex');

    const formData = new URLSearchParams();
    formData.append('route', 'generar_auditoria');
    formData.append('start_date', start);
    formData.append('end_date', end);

    fetch('index.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: formData.toString() })
    .then(res => res.json())
    .then(data => {
        loader.classList.add('hidden');
        loader.classList.remove('flex');
        resultDiv.classList.remove('hidden');
        if (data.markdown) {
            currentReporteGenerado = data.markdown;
            markdownContent.innerHTML = marked.parse(data.markdown);
            document.getElementById('btnExportarPDF').classList.remove('hidden');
        } else {
            markdownContent.innerHTML = '<p class="text-red-500 font-bold">Error obteniendo respuesta del agente.</p>';
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-brain"></i> Procesar Informe';
    })
    .catch(err => {
        loader.classList.add('hidden');
        resultDiv.classList.remove('hidden');
        markdownContent.innerHTML = '<p class="text-red-500 font-bold">Tiempo de espera agotado.</p>';
        btn.disabled = false;
        btn.innerHTML = 'Reintentar';
    });
}

function enviarFeedback(cal) {
    if(!currentReporteGenerado) return;
    const st = document.getElementById('feedback-status');
    st.classList.remove('hidden');
    st.innerText = "Guardando...";
    const fd = new URLSearchParams();
    fd.append('route', 'guardar_feedback_ia');
    fd.append('informe', currentReporteGenerado);
    fd.append('calificacion', cal);
    fetch('index.php', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: fd.toString() })
    .then(r => r.json()).then(res => { if(res.success) st.innerText = "¡Calificación guardada!"; });
}

function descargarPDF() {
    const el = document.getElementById('ia-result');
    const btn = document.getElementById('btnExportarPDF');
    const fbk = document.getElementById('ia-feedback-panel');
    btn.innerHTML = 'Exportando...';
    fbk.classList.add('hidden');
    el.classList.add('pdf-export-mode');
    html2pdf().set({ margin: [20,20,20,20], filename: 'Auditoria_Gerencial.pdf', html2canvas: {scale:2} }).from(el).save().then(() => {
        btn.innerHTML = '<i class="fa-solid fa-file-pdf text-red-400"></i> Exportar PDF';
        fbk.classList.remove('hidden');
        el.classList.remove('pdf-export-mode');
    });
}
</script>
</body>
</html>
PHP;
        makeFile(__DIR__ . '/app/Views/dashboard/layout.php', $layoutViewContent);

        // --- 13. INDEX ENRUTADOR ---
        $indexContent = <<<'PHP'
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
PHP;
        makeFile(__DIR__ . '/index.php', $indexContent);

        $success = true;
        $message = "¡Plataforma instalada! Se ha optimizado el rendimiento y habilitado el selector de temas corporativo.";

    } catch (Exception $e) {
        $success = false;
        $message = "Error en la instalación: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalador Gerencial</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-corp { background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7); }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between selection:bg-indigo-500 selection:text-white">
    <header class="bg-slate-900 border-b border-slate-800 py-5 px-8 shadow">
        <div class="max-w-7xl mx-auto flex items-center space-x-4">
            <div class="bg-indigo-600 p-3.5 rounded-2xl shadow-md"><i class="fa-solid fa-hospital text-white text-xl"></i></div>
            <div>
                <h1 class="text-lg font-black tracking-tight text-white">Instalador Corporativo</h1>
                <p class="text-xs text-slate-400">Motor IA Dedicado y Sincronización Horaria Colombia</p>
            </div>
        </div>
    </header>
    <main class="max-w-5xl mx-auto px-6 py-10 w-full space-y-6">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="p-5 rounded-2xl border text-sm font-bold shadow-2xl <?= $success ? 'bg-emerald-950/85 border-emerald-500/30 text-emerald-300' : 'bg-red-950/85 border-red-500/30 text-red-300' ?>">
                <i class="fa-solid <?= $success ? 'fa-circle-check text-emerald-400' : 'fa-triangle-exclamation text-red-400' ?> text-lg mr-2"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="glass-corp rounded-3xl p-10 border-t-2 border-indigo-500">
            <form method="POST" class="space-y-6">
                <div>
                    <h3 class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-3 border-b border-slate-800 pb-2"><i class="fa-solid fa-hospital mr-2"></i> 1. Información de la Institución y Tema</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Nombre Entidad</label>
                            <input type="text" name="hospital_name" value="<?= htmlspecialchars($hospitalName) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Módulo</label>
                            <input type="text" name="hospital_location" value="<?= htmlspecialchars($hospitalLocation) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Paleta Inicial</label>
                            <select name="theme_color" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-bold outline-none cursor-pointer">
                                <option value="oscuro" <?= $themeColor === 'oscuro' ? 'selected' : '' ?>>Oscuro Corporativo</option>
                                <option value="claro" <?= $themeColor === 'claro' ? 'selected' : '' ?>>Claro Institucional</option>
                                <option value="azul" <?= $themeColor === 'azul' ? 'selected' : '' ?>>Azul Médico</option>
                                <option value="esmeralda" <?= $themeColor === 'esmeralda' ? 'selected' : '' ?>>Esmeralda Clínico</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-black text-amber-400 uppercase tracking-widest mb-3 border-b border-slate-800 pb-2"><i class="fa-solid fa-microchip mr-2"></i> 2. Servidor de Inferencia IA (Ollama Local)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <input type="text" name="ollama_url" value="<?= htmlspecialchars($ollama_url) ?>" required class="w-full bg-slate-900 border border-amber-700/50 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Ollama URL">
                        <input type="text" name="ollama_model" value="<?= htmlspecialchars($ollama_model) ?>" required class="w-full bg-slate-900 border border-amber-700/50 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Modelo (Ej. qwen2.5:14b)">
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-3 border-b border-slate-800 pb-2"><i class="fa-solid fa-database mr-2"></i> 3. Base de Datos Principal (Datos Clínicos/Financieros)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
                        <input type="text" name="perc_host" value="<?= htmlspecialchars($perc_host) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Host DB">
                        <input type="text" name="perc_db" value="<?= htmlspecialchars($perc_db) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Nombre BD">
                        <input type="text" name="perc_user" value="<?= htmlspecialchars($perc_user) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Usuario DB">
                        <input type="password" name="perc_pass" value="<?= htmlspecialchars($perc_pass) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Contraseña DB">
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-black text-emerald-400 uppercase tracking-widest mb-3 border-b border-slate-800 pb-2"><i class="fa-solid fa-key mr-2"></i> 4. Base de Datos Secundaria (Login y Memoria IA)</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
                        <input type="text" name="maria_host" value="<?= htmlspecialchars($maria_host) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Host Login">
                        <input type="text" name="maria_db" value="<?= htmlspecialchars($maria_db) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="BD Login">
                        <input type="text" name="maria_user" value="<?= htmlspecialchars($maria_user) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Usuario">
                        <input type="password" name="maria_pass" value="<?= htmlspecialchars($maria_pass) ?>" class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Contraseña">
                        <input type="text" name="maria_table" value="<?= htmlspecialchars($maria_table) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm font-mono" placeholder="Tabla (users)">
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-black text-indigo-400 uppercase tracking-widest mb-3 border-b border-slate-800 pb-2"><i class="fa-solid fa-user-shield mr-2"></i> 5. Usuario Administrador Inicial</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <input type="text" name="admin_user" value="<?= htmlspecialchars($admin_user) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm">
                        <input type="text" name="admin_pass" value="<?= htmlspecialchars($admin_pass) ?>" required class="w-full bg-slate-900 border border-slate-700 rounded-xl px-4 py-3 text-white text-sm">
                    </div>
                </div>

                <div class="pt-6 flex flex-col sm:flex-row justify-between items-center gap-4">
                    <button type="submit" class="w-full sm:w-auto bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 px-8 rounded-xl transition shadow-lg text-sm uppercase tracking-wider">
                        <i class="fa-solid fa-microchip mr-2"></i> Compilar e Instalar
                    </button>
                    <?php if ($success): ?>
                        <a href="index.php" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 px-8 rounded-xl transition shadow-lg text-sm text-center uppercase tracking-wider">
                            Ir a la Plataforma <i class="fa-solid fa-arrow-right ml-2"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </main>
</body>
</html>