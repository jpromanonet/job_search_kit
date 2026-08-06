<?php

declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tracker');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash('error', 'ID inválido.');
    redirect_tab('tracker');
}

if (delete_application_by_id($id)) {
    flash('success', "Postulación #$id eliminada.");
} else {
    flash('error', 'No se encontró la postulación.');
}
redirect_tab('tracker');
