<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tecnologias');
}

$groupId = (int) ($_POST['group_id'] ?? 0);

try {
    save_user_technology(
        (int) $user['id'],
        (string) ($_POST['name'] ?? ''),
        (string) ($_POST['category'] ?? 'known'),
        $groupId > 0 ? $groupId : null
    );
    flash('success', 'Tecnología agregada a tu stack.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect_tab('tecnologias');
