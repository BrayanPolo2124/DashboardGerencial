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