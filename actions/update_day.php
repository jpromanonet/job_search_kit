<?php

declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('plan');
}

$dayNumber = (int) ($_POST['day_number'] ?? 0);
if ($dayNumber < 1 || $dayNumber > 100) {
    flash('error', 'Día inválido.');
    redirect_tab('plan');
}

$status = $_POST['status'] ?? 'not_started';
$allowed = ['not_started', 'in_progress', 'done', 'blocked', 'missed'];
if (!in_array($status, $allowed, true)) {
    $status = 'not_started';
}

$apps = max(0, min(50, (int) ($_POST['applications_logged'] ?? 0)));
$articleUrl = trim((string) ($_POST['article_url'] ?? ''));
if ($articleUrl !== '' && !filter_var($articleUrl, FILTER_VALIDATE_URL)) {
    flash('error', "URL inválida en el día $dayNumber.");
    redirect_tab('plan', [], 'day-' . $dayNumber);
}

$filter = preg_replace('/[^a-z_]/', '', (string) ($_POST['return_filter'] ?? 'all')) ?: 'all';

try {
    save_day_progress($dayNumber, [
        'status' => $status,
        'applications_logged' => $apps,
        'article_url' => $articleUrl !== '' ? $articleUrl : null,
        'linkedin_posted' => isset($_POST['linkedin_posted']) ? 1 : 0,
        'x_posted' => isset($_POST['x_posted']) ? 1 : 0,
        'instagram_done' => isset($_POST['instagram_done']) ? 1 : 0,
        'notes' => null_if_blank($_POST['notes'] ?? null),
        'evidence' => null_if_blank($_POST['evidence'] ?? null),
        'blockers' => null_if_blank($_POST['blockers'] ?? null),
        'carry_forward' => null_if_blank($_POST['carry_forward'] ?? null),
        'completed_at' => $status === 'done' ? date('Y-m-d H:i:s') : null,
    ]);
    flash('success', "Día $dayNumber guardado.");
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar: ' . $e->getMessage());
}

redirect_tab('plan', ['filter' => $filter], 'day-' . $dayNumber);
