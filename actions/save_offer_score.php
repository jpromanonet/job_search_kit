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
    redirect_tab('comparador');
}

$applicationId = (int) ($_POST['application_id'] ?? 0);
if ($applicationId < 1) {
    flash('error', 'Oferta inválida.');
    redirect_tab('comparador');
}

$blank = static function ($v): ?string {
    $v = trim((string) $v);
    return $v === '' ? null : $v;
};

try {
    save_offer_score_for_user($userId, $applicationId, [
        'compensation_score' => $_POST['compensation_score'] ?? 0,
        'role_fit_score' => $_POST['role_fit_score'] ?? 0,
        'growth_score' => $_POST['growth_score'] ?? 0,
        'culture_score' => $_POST['culture_score'] ?? 0,
        'schedule_score' => $_POST['schedule_score'] ?? 0,
        'risk_score' => $_POST['risk_score'] ?? 0,
        'total_comp_monthly' => $_POST['total_comp_monthly'] ?? '',
        'currency' => $_POST['currency'] ?? '',
        'employment_type' => $blank($_POST['employment_type'] ?? ''),
        'remote_policy' => $blank($_POST['remote_policy'] ?? ''),
        'schedule_type' => $blank($_POST['schedule_type'] ?? ''),
        'notes' => $blank($_POST['notes'] ?? ''),
        'ranking_notes' => $blank($_POST['ranking_notes'] ?? ''),
    ]);
    flash('success', 'Oferta guardada y recalculada.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect_tab('comparador', [], 'offer-' . $applicationId);
