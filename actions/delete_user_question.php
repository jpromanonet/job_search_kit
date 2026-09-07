<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('preguntas');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0 && delete_user_question((int) $user['id'], $id)) {
    flash('success', 'Pregunta eliminada.');
} else {
    flash('error', 'No se pudo eliminar.');
}

redirect_tab('preguntas', [], 'mis-preguntas');
