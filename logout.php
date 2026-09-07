<?php

declare(strict_types=1);

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/auth.php';

auth_start();
logout_user();
header('Location: ' . url('/login.php'));
exit;
