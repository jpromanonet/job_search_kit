<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tracker');
}

$id = (int) ($_POST['id'] ?? 0);
$company = trim((string) ($_POST['company'] ?? ''));
$role = trim((string) ($_POST['role_title'] ?? ''));
$market = $_POST['market'] ?? 'ar';
$stage = $_POST['stage'] ?? 'discovered';

if ($company === '' || $role === '') {
    flash('error', 'Empresa y rol son obligatorios.');
    redirect_tab('tracker', $id > 0 ? ['edit' => $id] : ['new' => 1]);
}

if (!in_array($market, ['ar', 'intl'], true)) {
    $market = 'ar';
}
if (!array_key_exists($stage, stage_labels())) {
    $stage = 'discovered';
}

$dayNumber = $_POST['day_number'] ?? '';
$dayNumber = $dayNumber === '' ? null : (int) $dayNumber;
if ($dayNumber !== null && ($dayNumber < 1 || $dayNumber > 100)) {
    $dayNumber = null;
}

$url = null_if_blank($_POST['canonical_url'] ?? null);
if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
    flash('error', 'URL canónica inválida.');
    redirect_tab('tracker', $id > 0 ? ['edit' => $id] : ['new' => 1]);
}

$fit = $_POST['fit_score'] ?? '';
$fitScore = $fit === '' ? null : max(0, min(100, (int) $fit));
$salaryMin = $_POST['salary_min'] ?? '';
$salaryMax = $_POST['salary_max'] ?? '';
$currency = $_POST['currency'] ?? '';
if (!in_array($currency, ['ARS', 'USD', 'EUR', 'other', ''], true)) {
    $currency = '';
}

// Duplicate check
$force = isset($_POST['force_duplicate']);
foreach (load_applications() as $existing) {
    if ($id > 0 && (int) $existing['id'] === $id) {
        continue;
    }
    $sameCompany = strcasecmp((string) $existing['company'], $company) === 0;
    $sameRole = strcasecmp((string) $existing['role_title'], $role) === 0;
    $existingUrl = $existing['canonical_url'] ?? null;
    $sameUrl = ($url === null && ($existingUrl === null || $existingUrl === ''))
        || ($url !== null && $existingUrl === $url);
    if ($sameCompany && $sameRole && $sameUrl && !$force) {
        flash('error', 'Posible duplicado (#' . $existing['id'] . '). Marcá “forzar” si es otra vacante.');
        redirect_tab('tracker', $id > 0 ? ['edit' => $id] : ['new' => 1]);
    }
}

$app = [
    'id' => $id,
    'day_number' => $dayNumber,
    'company' => $company,
    'role_title' => $role,
    'market' => $market,
    'platform' => null_if_blank($_POST['platform'] ?? null),
    'discovery_source' => null_if_blank($_POST['discovery_source'] ?? null),
    'canonical_url' => $url,
    'location_eligible' => isset($_POST['location_eligible']) ? 1 : 0,
    'role_family' => null_if_blank($_POST['role_family'] ?? null),
    'cv_version' => null_if_blank($_POST['cv_version'] ?? null),
    'cover_letter' => null_if_blank($_POST['cover_letter'] ?? null),
    'salary_note' => null_if_blank($_POST['salary_note'] ?? null),
    'currency' => $currency !== '' ? $currency : null,
    'salary_min' => $salaryMin === '' ? null : (float) $salaryMin,
    'salary_max' => $salaryMax === '' ? null : (float) $salaryMax,
    'fit_score' => $fitScore,
    'application_date' => null_if_blank($_POST['application_date'] ?? null),
    'contact_name' => null_if_blank($_POST['contact_name'] ?? null),
    'follow_up_date' => null_if_blank($_POST['follow_up_date'] ?? null),
    'stage' => $stage,
    'result_notes' => null_if_blank($_POST['result_notes'] ?? null),
    'notes' => null_if_blank($_POST['notes'] ?? null),
    // offer scores (kept on same record for simplicity)
    'offer' => $id > 0 ? (find_application($id)['offer'] ?? null) : null,
];

try {
    $saved = upsert_application($app);
    flash('success', $id > 0 ? ('Postulación #' . $saved['id'] . ' actualizada.') : ('Postulación #' . $saved['id'] . ' creada.'));
    redirect_tab('tracker', [], 'app-' . $saved['id']);
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar: ' . $e->getMessage());
    redirect_tab('tracker', $id > 0 ? ['edit' => $id] : ['new' => 1]);
}
