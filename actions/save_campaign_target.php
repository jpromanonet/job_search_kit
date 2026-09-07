<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('dashboard');
}

$target = (int) ($_POST['target_applications'] ?? 0);
$returnTab = (string) ($_POST['return_tab'] ?? 'dashboard');
if (!in_array($returnTab, ['tracker', 'metricas', 'dashboard'], true)) {
    $returnTab = 'dashboard';
}

if ($target < 1 || $target > 50000) {
    flash('error', 'El objetivo tiene que estar entre 1 y 50.000 postulaciones.');
    redirect_tab($returnTab);
}

try {
    $saved = save_campaign_target_for_user($userId, $target);
    flash('success', 'Objetivo actualizado a ' . $saved . ' postulaciones.');
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar la meta: ' . $e->getMessage());
}

$query = [];
if ($returnTab === 'tracker' && isset($_POST['view'])) {
    $view = (string) $_POST['view'];
    if (in_array($view, ['table', 'kanban'], true)) {
        $query['view'] = $view;
    }
}

redirect_tab($returnTab, $query);
