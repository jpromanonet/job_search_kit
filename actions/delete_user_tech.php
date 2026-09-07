<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tecnologias');
}

$id = (int) ($_POST['id'] ?? 0);
if ($id > 0 && delete_user_technology((int) $user['id'], $id)) {
    flash('success', 'Tecnología eliminada de tu stack.');
} else {
    flash('error', 'No se pudo eliminar.');
}

redirect_tab('tecnologias');
