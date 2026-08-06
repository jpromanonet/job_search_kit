<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../config.php';
$appName = $config['app']['name'];
$activeTab = $activeTab ?? 'plan';
$flash = take_flash();

$tabs = [
    'plan' => 'Plan 100 días',
    'portales' => 'Portales',
    'recomendaciones' => 'Recomendaciones',
    'hr_faq' => 'HR FAQ',
    'tecnologias' => 'Tecnologías',
    'documentos' => 'Documentos',
    'tracker' => 'Tracker',
    'comparador' => 'Comparador',
    'metricas' => 'Métricas',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? $appName) ?></title>
  <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
  <script>window.JOBKIT_BASE = <?= json_encode(base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <a href="<?= e(url('/index.php?tab=plan')) ?>"><?= e($appName) ?></a>
    </div>
    <nav class="nav">
      <?php foreach ($tabs as $key => $label): ?>
        <a class="<?= $activeTab === $key ? 'is-active' : '' ?>"
           href="<?= e(url('/index.php?tab=' . $key)) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
  </header>

  <main class="shell">
    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'ok' : 'info')) ?>">
        <?= e($flash['message']) ?>
      </div>
    <?php endif; ?>
