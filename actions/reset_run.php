<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('dashboard');
}

$start = reset_run_for_user((int) $user['id']);
flash('success', 'Nueva campaña desde ' . date('d/m/Y', strtotime($start)) . '. El historial no se borra.');
redirect_tab((string) ($_POST['return'] ?? 'dashboard'));
