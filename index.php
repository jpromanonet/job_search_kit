<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/data.php';
require __DIR__ . '/includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/config.php';

$allowedTabs = ['plan', 'portales', 'recomendaciones', 'hr_faq', 'tecnologias', 'documentos', 'tracker', 'comparador', 'metricas'];
$activeTab = $_GET['tab'] ?? 'plan';
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'plan';
}

$tabTitles = [
    'plan' => 'Plan 100 días',
    'portales' => 'Portales',
    'recomendaciones' => 'Recomendaciones',
    'hr_faq' => 'HR FAQ',
    'tecnologias' => 'Tecnologías',
    'documentos' => 'Documentos',
    'tracker' => 'Tracker',
    'comparador' => 'Comparador',
    'metricas' => 'Métricas',
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
