<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('plan');
}

$target = (int) ($_POST['target_applications'] ?? 0);
$returnTab = (string) ($_POST['return_tab'] ?? 'plan');
if (!in_array($returnTab, ['plan', 'tracker', 'metricas'], true)) {
    $returnTab = 'plan';
}

if ($target < 1 || $target > 10000) {
    flash('error', 'La meta debe estar entre 1 y 10.000 postulaciones.');
    redirect_tab($returnTab);
}

try {
    $saved = save_campaign_target_applications($target);
    flash('success', 'Meta de campaña actualizada a ' . $saved . ' postulaciones.');
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
