<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/storage.php';
require __DIR__ . '/includes/repositories.php';
require __DIR__ . '/includes/auth.php';

auth_start();
$user = require_login();

$config = require __DIR__ . '/config.php';

$allowedTabs = [
    'dashboard', 'tracker', 'entrevistas', 'portales',
    'hr_faq', 'preguntas', 'tecnologias', 'documentos', 'ats',
    'comparador', 'metricas', 'perfil',
];
$activeTab = $_GET['tab'] ?? 'dashboard';
if ($activeTab === 'plan' || $activeTab === 'recomendaciones' || !in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'dashboard';
}

$tabTitles = [
    'dashboard' => 'Mapa',
    'tracker' => 'Diario',
    'entrevistas' => 'Charlas',
    'portales' => 'Portales',
    'hr_faq' => 'HR',
    'preguntas' => 'Preguntar',
    'tecnologias' => 'Stack',
    'documentos' => 'Grimorio',
    'ats' => 'Roles',
    'comparador' => 'Comparar',
    'metricas' => 'Métricas',
    'perfil' => 'Perfil',
];
$pageTitle = ($tabTitles[$activeTab] ?? 'JobKit') . ' · ' . $config['app']['name'];

require __DIR__ . '/includes/header.php';

$tabFile = __DIR__ . '/includes/tabs/' . $activeTab . '.php';
if (is_file($tabFile)) {
    require $tabFile;
} else {
    echo '<div class="panel warn-panel">Pestaña no encontrada.</div>';
}

require __DIR__ . '/includes/footer.php';
