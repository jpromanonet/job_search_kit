<?php
/**
 * Job Search Kit — local config (no auth).
 * Copy to config.local.php to override without editing this file.
 */

declare(strict_types=1);

$config = [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'job_search_kit',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'JobKit',
        // Vacío = se detecta solo desde la URL.
        'url' => '',
        'timezone' => 'America/Argentina/Buenos_Aires',
        'target_applications' => 100,
        'target_ar' => 100,
        'target_intl' => 0,
        'apps_per_day' => 5,
        'blog_per_week' => 1,
    ],
    'paths' => [
        'root' => __DIR__,
        'data' => __DIR__ . '/data',
        'uploads' => __DIR__ . '/uploads',
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $override = require $local;
    if (is_array($override)) {
        $config = array_replace_recursive($config, $override);
    }
}

date_default_timezone_set($config['app']['timezone']);

return $config;
