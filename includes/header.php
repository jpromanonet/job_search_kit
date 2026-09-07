<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$config = require __DIR__ . '/../config.php';
$appName = $config['app']['name'];
$activeTab = $activeTab ?? 'dashboard';
$flash = take_flash();
$navUser = $user ?? current_user();

$navGroups = [
    'write' => [
        'label' => 'Cargar',
        'tabs' => [
            'tracker' => 'Diario',
            'entrevistas' => 'Charlas',
            'ats' => 'Roles',
            'portales' => 'Portales',
            'preguntas' => 'Preguntar',
            'tecnologias' => 'Stack',
        ],
    ],
    'read' => [
        'label' => 'Mirar',
        'tabs' => [
            'dashboard' => 'Mapa',
            'comparador' => 'Comparar',
            'hr_faq' => 'HR',
            'documentos' => 'Grimorio',
            'metricas' => 'Métricas',
        ],
    ],
];

$navRunDays = null;
if ($navUser && function_exists('campaign_settings_for_user')) {
    $navCampaign = campaign_settings_for_user((int) $navUser['id']);
    $navRunDays = run_day_count((string) ($navCampaign['run_started_on'] ?? ''), date('Y-m-d'));
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? $appName) ?></title>
  <link href="<?= e(url('/assets/css/quest.css')) ?>?v=<?= e((string) filemtime(__DIR__ . '/../assets/css/quest.css')) ?>" rel="stylesheet">
  <script>window.JOBKIT_BASE = <?= json_encode(base_path(), JSON_UNESCAPED_SLASHES) ?>;</script>
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <div class="brand">
        <a href="<?= e(url('/index.php?tab=dashboard')) ?>">
          <?php require __DIR__ . '/wizard.php'; ?>
          <?= e($appName) ?>
        </a>
      </div>
      <nav class="nav" id="siteNav">
        <div class="nav-primary">
          <?php foreach ($navGroups as $groupKey => $group): ?>
            <div class="nav-group nav-group--<?= e($groupKey) ?>">
              <span class="nav-group__label"><?= e((string) $group['label']) ?></span>
              <div class="nav-group__links">
                <?php foreach ($group['tabs'] as $key => $label): ?>
                  <a class="<?= $activeTab === $key ? 'is-active' : '' ?>"
                     href="<?= e(url('/index.php?tab=' . $key)) ?>"><?= e($label) ?></a>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="nav-user">
          <?php if ($navUser): ?>
            <?php if ($navRunDays !== null): ?>
              <a class="run-chip" href="<?= e(url('/index.php?tab=dashboard')) ?>" title="Días postulando">
                <strong><?= e((string) $navRunDays) ?></strong>
                <span><?= $navRunDays === 1 ? 'día' : 'días' ?></span>
              </a>
            <?php endif; ?>
            <a class="profile-chip <?= $activeTab === 'perfil' ? 'is-active' : '' ?>" href="<?= e(url('/index.php?tab=perfil')) ?>">
              <?= e((string) ($navUser['name'] ?? 'Perfil')) ?>
            </a>
            <a class="btn btn-sm" href="<?= e(url('/logout.php')) ?>">Salir</a>
          <?php endif; ?>
        </div>
      </nav>
      <button type="button" class="nav-toggle" id="navToggle" aria-controls="siteNav" aria-expanded="false" aria-label="Abrir menú">
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
