<?php
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { die('Acceso Restringido'); }
return [
    'hospital_name' => 'HOSPITAL GONZALO CONTRERAS E.S.E.',
    'hospital_location' => 'Sistema de Información Gerencial',
    'theme_color' => 'oscuro',
    'ollama_url' => 'http://127.0.0.1:11434',
    'ollama_model' => 'qwen2.5:14b',
    'percona' => [
        'host' => 'rovira.sihos.com.co',
        'db' => 'sihosCapacitacion',
        'user' => 'root',
        'pass' => 'aoa248202501'
    ],
    'mariadb' => [
        'host' => '127.0.0.1',
        'db' => 'ynet360_dashboardunion',
        'user' => 'root',
        'pass' => '',
        'table' => 'users'
    ]
];
