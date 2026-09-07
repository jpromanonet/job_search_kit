<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('entrevistas');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id < 1) {
    flash('error', 'ID inválido.');
    redirect_tab('entrevistas');
}

if (delete_interview_note_for_user((int) $user['id'], $id)) {
    flash('success', 'Nota de entrevista eliminada.');
} else {
    flash('error', 'No se encontró la nota.');
}

redirect_tab('entrevistas');
