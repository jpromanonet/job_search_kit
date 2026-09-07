<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('portales');
}

try {
    $id = save_user_portal(
        (int) $user['id'],
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['url'] ?? ''),
        (string) ($_POST['notes'] ?? ''),
        (int) ($_POST['id'] ?? 0),
        (string) ($_POST['section'] ?? '')
    );
    flash('success', 'Portal guardado.');
    redirect_tab('portales', [], 'portal-' . $id);
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect_tab('portales', [], 'mis-portales');
}
