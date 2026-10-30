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