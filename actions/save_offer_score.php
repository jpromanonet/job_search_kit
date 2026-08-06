<?php

declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('comparador');
}

$applicationId = (int) ($_POST['application_id'] ?? 0);
$app = find_application($applicationId);
if (!$app || !in_array($app['stage'] ?? '', ['offer', 'accepted'], true)) {
    flash('error', 'La postulación no está en Offer/Accepted.');
    redirect_tab('comparador');
}

$clamp = static fn ($v): int => max(0, min(10, (int) $v));
$currency = $_POST['currency'] ?? '';
if (!in_array($currency, ['ARS', 'USD', 'EUR', 'other', ''], true)) {
    $currency = '';
}
$comp = $_POST['total_comp_monthly'] ?? '';

$app['offer'] = [
    'compensation_score' => $clamp($_POST['compensation_score'] ?? 0),
    'role_fit_score' => $clamp($_POST['role_fit_score'] ?? 0),
    'growth_score' => $clamp($_POST['growth_score'] ?? 0),
    'culture_score' => $clamp($_POST['culture_score'] ?? 0),
    'schedule_score' => $clamp($_POST['schedule_score'] ?? 0),
    'risk_score' => $clamp($_POST['risk_score'] ?? 0),
    'total_comp_monthly' => $comp === '' ? null : (float) $comp,
    'currency' => $currency !== '' ? $currency : null,
    'employment_type' => null_if_blank($_POST['employment_type'] ?? null),
    'remote_policy' => null_if_blank($_POST['remote_policy'] ?? null),
    'schedule_type' => null_if_blank($_POST['schedule_type'] ?? null),
    'notes' => null_if_blank($_POST['notes'] ?? null),
    'ranking_notes' => null_if_blank($_POST['ranking_notes'] ?? null),
];

upsert_application($app);
flash('success', "Scores de oferta #$applicationId guardados.");
redirect_tab('comparador', [], 'offer-' . $applicationId);
