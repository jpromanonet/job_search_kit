<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('hr_faq');
}

$id = (int) ($_POST['faq_id'] ?? 0);
$answer = (string) ($_POST['answer'] ?? '');
$question = (string) ($_POST['question'] ?? '');

try {
    save_hr_answer_for_user((int) $user['id'], $id, $answer, $question);
    flash('success', 'Pregunta y respuesta guardadas.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect_tab('hr_faq', [], 'faq-' . $id);
