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