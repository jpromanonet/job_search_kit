<?php

declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('plan');
}

$confirm = trim((string) ($_POST['confirm'] ?? ''));
if ($confirm !== 'REINICIAR') {
    flash('error', 'Reset cancelado: confirmación inválida.');
    redirect_tab('plan');
}

$config = require __DIR__ . '/../config.php';
$dataDir = $config['paths']['data'];

try {
    write_data_file($dataDir . '/day_progress.json', "[]\n");
    write_data_file($dataDir . '/applications.json', "[]\n");

    if (function_exists('db_available') && db_available()) {
        try {
            $pdo = db();
            $pdo->exec('DELETE FROM offer_scores');
            $pdo->exec('DELETE FROM applications');
        } catch (Throwable $e) {
            // File store is primary; DB wipe is best-effort.
        }
    }

    flash('success', 'Campaña reiniciada. Progreso y postulaciones quedaron en cero. El playbook se mantiene.');
} catch (Throwable $e) {
    flash('error', 'No se pudo reiniciar: ' . $e->getMessage());
}

redirect_tab('plan');
