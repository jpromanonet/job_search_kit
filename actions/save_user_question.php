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

try {
    save_user_question(
        (int) $user['id'],
        (string) ($_POST['question'] ?? ''),
        (string) ($_POST['why'] ?? ''),
        (string) ($_POST['tip'] ?? ''),
        (int) ($_POST['id'] ?? 0)
    );
    flash('success', 'Pregunta guardada.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect_tab('preguntas', [], 'mis-preguntas');
