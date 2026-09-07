<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('perfil');
}

$name = trim((string) ($_POST['name'] ?? ''));
$headline = trim((string) ($_POST['headline'] ?? ''));
$location = trim((string) ($_POST['location'] ?? ''));
$bio = trim((string) ($_POST['bio'] ?? ''));
$phone = normalize_profile_link((string) ($_POST['phone'] ?? ''), 'phone');
$links = [
    'linkedin_url' => normalize_profile_link((string) ($_POST['linkedin_url'] ?? '')),
    'website_url' => normalize_profile_link((string) ($_POST['website_url'] ?? '')),
    'portfolio_url' => normalize_profile_link((string) ($_POST['portfolio_url'] ?? '')),
    'x_url' => normalize_profile_link((string) ($_POST['x_url'] ?? ''), 'x'),
    'instagram_url' => normalize_profile_link((string) ($_POST['instagram_url'] ?? ''), 'instagram'),
];

if ($name === '') {
    flash('error', 'El nombre es obligatorio.');
    redirect_tab('perfil');
}

foreach (['LinkedIn' => $links['linkedin_url'], 'Sitio web' => $links['website_url'], 'Portfolio' => $links['portfolio_url'], 'X' => $links['x_url'], 'Instagram' => $links['instagram_url']] as $label => $url) {
    if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
        flash('error', 'URL de ' . $label . ' inválida.');
        redirect_tab('perfil');
    }
}

$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');
$wantsPasswordChange = $currentPassword !== '' || $newPassword !== '' || $newPasswordConfirm !== '';

try {
    update_profile_for_user((int) $user['id'], array_merge($links, [
        'name' => $name,
        'headline' => $headline,
        'location' => $location,
        'bio' => $bio,
        'phone' => $phone,
    ]));

    if ($wantsPasswordChange) {
        if ($newPassword !== $newPasswordConfirm) {
            flash('error', 'La confirmación de contraseña no coincide.');
            redirect_tab('perfil');
        }
        change_password_for_user((int) $user['id'], $currentPassword, $newPassword);
        flash('success', 'Perfil y contraseña actualizados.');
    } else {
        flash('success', 'Perfil actualizado.');
    }
} catch (InvalidArgumentException $e) {
    flash('error', $e->getMessage());
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar el perfil: ' . $e->getMessage());
}

redirect_tab('perfil');
