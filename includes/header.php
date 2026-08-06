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
    'tecnologias' => 'Tecnologías',
    'documentos' => 'Documentos',
    'tracker' => 'Tracker',
    'comparador' => 'Comparador',
    'metricas' => 'Métricas',
];

$faqTabs = [
    'hr_faq' => 'HR FAQ',
    'preguntas' => 'Mis preguntas',
];
$faqActive = array_key_exists($activeTab, $faqTabs);
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
        <?php if ($key === 'tecnologias'): ?>
          <div class="nav-dropdown<?= $faqActive ? ' is-active' : '' ?>">
            <button type="button" class="nav-dropdown-toggle<?= $faqActive ? ' is-active' : '' ?>" aria-expanded="false" aria-haspopup="true">
              FAQ
              <span class="nav-caret" aria-hidden="true"></span>
            </button>
            <div class="nav-dropdown-menu" role="menu">
              <?php foreach ($faqTabs as $faqKey => $faqLabel): ?>
                <a role="menuitem"
                   class="<?= $activeTab === $faqKey ? 'is-active' : '' ?>"
                   href="<?= e(url('/index.php?tab=' . $faqKey)) ?>"><?= e($faqLabel) ?></a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
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
