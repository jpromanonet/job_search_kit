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
$company = trim((string) ($_POST['company'] ?? ''));
$role = trim((string) ($_POST['role_title'] ?? ''));
$market = $_POST['market'] ?? 'ar';
$stage = $_POST['stage'] ?? 'applied';
$viewQ = (($_POST['view'] ?? '') === 'kanban') ? ['view' => 'kanban'] : [];

if ($company === '' || $role === '') {
    flash('error', 'Empresa y rol son obligatorios.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}

if (!in_array($market, ['ar', 'intl'], true)) {
    $market = 'ar';
}
if (!array_key_exists($stage, stage_labels())) {
    $stage = 'applied';
}

$url = null_if_blank($_POST['canonical_url'] ?? null);
if ($url !== null && !filter_var($url, FILTER_VALIDATE_URL)) {
    flash('error', 'URL canónica inválida.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}

$fit = $_POST['fit_score'] ?? '';
$fitScore = $fit === '' ? null : max(0, min(100, (int) $fit));

$force = isset($_POST['force_duplicate']);
foreach (load_applications_for_user($userId) as $existing) {
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
        redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
    }
}

$cvVersion = trim((string) ($_POST['cv_version'] ?? ''));
$coverLetter = trim((string) ($_POST['cover_letter'] ?? ''));
$currency = (string) ($_POST['currency'] ?? '');
$salaryRaw = trim((string) ($_POST['salary_max'] ?? ''));
$noSalary = isset($_POST['no_salary']);

if ($cvVersion === '') {
    flash('error', 'Elegí qué CV usaste.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}
if ($coverLetter === '') {
    flash('error', 'Decí si usaste cover letter.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}
if (!$noSalary && $salaryRaw === '') {
    flash('error', 'Cargá el sueldo que pusiste, o marcá que no indicaste.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}
if (!in_array($currency, ['ARS', 'USD', 'EUR', 'other'], true)) {
    $currency = 'ARS';
}
$salaryVal = $noSalary || $salaryRaw === '' ? null : max(0, (float) $salaryRaw);
$fromAr = isset($_POST['location_eligible']);
$market = $fromAr ? 'ar' : 'intl';

$base = [
    'id' => $id,
    'day_number' => null,
    'company' => $company,
    'role_title' => $role,
    'market' => $market,
    'platform' => null_if_blank($_POST['platform'] ?? null),
    'canonical_url' => $url,
    'location_eligible' => $fromAr ? 1 : 0,
    'fit_score' => $fitScore,
    'application_date' => null_if_blank($_POST['application_date'] ?? null),
    'contact_name' => null_if_blank($_POST['contact_name'] ?? null),
    'follow_up_date' => null_if_blank($_POST['follow_up_date'] ?? null),
    'stage' => $stage,
    'notes' => null_if_blank($_POST['notes'] ?? null),
    'cv_version' => $cvVersion,
    'cover_letter' => $coverLetter,
    'salary_note' => $noSalary ? 'no indiqué' : null_if_blank($_POST['salary_note'] ?? null),
    'currency' => $noSalary ? null : $currency,
    'salary_min' => $salaryVal,
    'salary_max' => $salaryVal,
    'discovery_source' => null,
    'role_family' => null,
    'result_notes' => null,
];

if ($id > 0) {
    $prev = find_application_for_user($userId, $id) ?? [];
    foreach (['day_number', 'discovery_source', 'role_family', 'result_notes'] as $key) {
        if (array_key_exists($key, $prev)) {
            $base[$key] = $prev[$key];
        }
    }
}

if (in_array($stage, ['offer', 'accepted'], true)
    && (trim((string) ($_POST['remote_policy'] ?? '')) === '' || trim((string) ($_POST['schedule_type'] ?? '')) === '')) {
    flash('error', 'Si está en Oferta, cargá modalidad y horario: el comparador los usa contra tu techo.');
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}

try {
    $saved = save_application_for_user($userId, $base);
    upsert_offer_compare_fields_for_user($userId, (int) $saved['id'], [
        'remote_policy' => (string) ($_POST['remote_policy'] ?? ''),
        'schedule_type' => (string) ($_POST['schedule_type'] ?? ''),
        'total_comp_monthly' => $salaryVal,
        'currency' => $noSalary ? '' : $currency,
    ]);
    flash('success', $id > 0 ? ('Postulación #' . $saved['id'] . ' actualizada.') : ('Postulación #' . $saved['id'] . ' creada.'));
    $hash = !empty($viewQ)
        ? 'app-' . (int) $saved['id']
        : 'day-' . (($saved['application_date'] ?? '') !== '' ? $saved['application_date'] : 'sin-fecha');
    redirect_tab('tracker', $viewQ, $hash);
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar: ' . $e->getMessage());
    redirect_tab('tracker', array_merge($viewQ, $id > 0 ? ['edit' => $id] : ['new' => 1]));
}
