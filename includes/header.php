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
    <div class="topbar-inner">
      <div class="brand">
        <a href="<?= e(url('/index.php?tab=plan')) ?>"><?= e($appName) ?></a>
      </div>
      <nav class="nav" id="siteNav">
        <div class="nav-primary">
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
        </div>
        <form class="nav-reset-form"
              method="post"
              action="<?= e(url('/actions/reset_campaign.php')) ?>"
              onsubmit="return window.confirm('REINICIAR TODO borra el progreso de los 100 días y todas las postulaciones del Tracker.\n\nNo toca el playbook, documentos ni portales.\n\n¿Seguro que querés arrancar una campaña nueva?') && window.confirm('Última confirmación: ¿reiniciar la campaña ahora?');">
          <input type="hidden" name="confirm" value="REINICIAR">
          <button type="submit" class="nav-reset-btn">REINICIAR TODO</button>
        </form>
      </nav>
      <button type="button"
              class="nav-toggle"
              id="navToggle"
              aria-controls="siteNav"
              aria-expanded="false"
              aria-label="Abrir menú">
        <span class="nav-toggle-bars" aria-hidden="true"></span>
      </button>
    </div>
  </header>
  <div class="nav-backdrop" id="navBackdrop" hidden></div>

  <main class="shell">
    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['type'] === 'error' ? 'danger' : ($flash['type'] === 'success' ? 'ok' : 'info')) ?>">
        <?= e($flash['message']) ?>
      </div>
    <?php endif; ?>
