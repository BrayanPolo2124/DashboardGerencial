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