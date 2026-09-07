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
    redirect_tab('tracker');
}

$id = (int) ($_POST['id'] ?? 0);
$viewQ = (($_POST['view'] ?? '') === 'kanban') ? ['view' => 'kanban'] : [];
if ($id < 1) {
    flash('error', 'ID inválido.');
    redirect_tab('tracker', $viewQ);
}

if (delete_application_for_user($userId, $id)) {
    flash('success', "Postulación #$id eliminada.");
} else {
    flash('error', 'No se encontró la postulación.');
}
redirect_tab('tracker', $viewQ);
