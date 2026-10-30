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