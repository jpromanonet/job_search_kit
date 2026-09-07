<?php

declare(strict_types=1);

/** @var array $user */

$userId = (int) ($user['id'] ?? 0);
$stats = application_stats_for_user($userId);
$campaign = campaign_settings_for_user($userId);
$target = (int) $campaign['target_applications'];
$moment = dashboard_moment($stats);
$apps = load_applications_for_user($userId);

$submitted = (int) ($stats['submitted'] ?? 0);
$interviews = (int) ($stats['interviews'] ?? 0);
$offers = (int) ($stats['offers'] ?? 0);
$rejected = (int) ($stats['rejected'] ?? 0);
$overdue = (int) ($stats['overdue'] ?? 0);
$todayCount = (int) ($stats['today'] ?? 0);
$counts = $stats['counts'] ?? [];
$progressPct = $target > 0 ? round(($submitted / $target) * 100, 1) : 0;

$today = date('Y-m-d');
$runStart = (string) $campaign['run_started_on'];
$runDays = run_day_count($runStart, $today);
$todayApps = array_values(array_filter($apps, static function ($app) use ($today) {
    return application_date_of($app) === $today;
}));

$runApps = 0;
$runDates = [];
foreach ($apps as $app) {
    $d = application_date_of($app);
    if ($d !== '' && $d >= $runStart) {
        $runApps++;
        $runDates[$d] = true;
    }
}

$span = $runDays;
$window = $span <= 21 ? max(0, $span) : 20;
$spark = [];
for ($i = $window; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($today . " -{$i} days"));
    if ($d >= $runStart) {
        $spark[$d] = 0;
    }
}
if (!$spark) {
    $spark[$today] = 0;
}
foreach ($apps as $app) {
    $d = application_date_of($app);
    if (isset($spark[$d])) {
        $spark[$d]++;
    }
}
$sparkMax = max(1, ...array_values($spark));
$sparkSum = array_sum($spark);

$firstName = trim((string) explode(' ', (string) ($user['name'] ?? 'mago'))[0]);
$todayLabel = date('d/m/Y');
?>
<section class="quest-hero">
  <?php require __DIR__ . '/../wizard.php'; ?>
  <div>
    <p class="eyebrow">Mapa · <?= e($todayLabel) ?></p>
    <h1><?= e((string) ($moment['title'] ?? 'Listo para la campaña')) ?></h1>
    <p><?= e($firstName) ?>, <?= e((string) ($moment['blurb'] ?? 'El mago te espera en el diario.')) ?></p>
  </div>
  <div class="run-counter" aria-label="Días postulando">
    <span class="run-counter__label">Días postulando</span>
    <strong class="run-counter__value"><?= e((string) $runDays) ?></strong>
    <span class="run-counter__hint">desde <?= e(format_display_date($runStart)) ?></span>
  </div>
  <div class="quest-hero__actions">
    <a class="btn btn-accent" href="<?= e(url('/index.php?tab=tracker&new=1')) ?>#app-form">+ Envío de hoy</a>
    <a class="btn" href="<?= e(url('/index.php?tab=tracker')) ?>">Abrir diario</a>
    <a class="btn" href="<?= e(url('/index.php?tab=comparador')) ?>">Comparar tesoros</a>
  </div>
</section>

<?php
$goalReturn = 'dashboard';
require __DIR__ . '/../goal_panel.php';
?>

<section class="metrics-grid">
  <article class="metric-card metric-card--gold">
    <span class="metric-card__label">Hoy</span>
    <strong class="metric-card__value"><?= e((string) $todayCount) ?></strong>
    <span class="metric-card__hint">envíos de hoy</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Historial</span>
    <strong class="metric-card__value"><?= e((string) $submitted) ?></strong>
    <span class="metric-card__hint"><?= e((string) $progressPct) ?>% del objetivo</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Esta campaña</span>
    <strong class="metric-card__value"><?= e((string) $runApps) ?></strong>
    <span class="metric-card__hint"><?= e((string) count($runDates)) ?> días con envío</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Entrevistas</span>
    <strong class="metric-card__value"><?= e((string) $interviews) ?></strong>
    <span class="metric-card__hint">boss fights abiertas</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Ofertas</span>
    <strong class="metric-card__value"><?= e((string) $offers) ?></strong>
    <span class="metric-card__hint">cofres a comparar</span>
  </article>
  <article class="metric-card <?= $overdue > 0 ? 'metric-card--warn' : '' ?>">
    <span class="metric-card__label">Follow-ups</span>
    <strong class="metric-card__value"><?= e((string) $overdue) ?></strong>
    <span class="metric-card__hint">pendientes vencidos</span>
  </article>
</section>

<section class="dash-split">
  <article class="panel">
    <div class="panel-head">
      <h2>Actividad de la campaña</h2>
      <span class="count"><?= e((string) $sparkSum) ?> XP</span>
    </div>
    <div class="spark" aria-hidden="true">
      <?php foreach ($spark as $d => $n): ?>
        <div class="spark__col" title="<?= e(format_display_date($d) . ': ' . $n) ?>">
          <div class="spark__bar<?= $n > 0 ? ' is-on' : '' ?>" style="height: <?= e((string) max(10, round(($n / $sparkMax) * 100))) ?>%"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="spark__labels">
      <span><?= e(format_display_date((string) array_key_first($spark))) ?></span>
      <span>hoy</span>
    </div>
    <form method="post" action="<?= e(url('/actions/reset_run.php')) ?>" class="run-reset" onsubmit="return confirm('¿Empezar una campaña nueva desde hoy? El historial no se borra.');">
      <input type="hidden" name="return" value="dashboard">
      <button type="submit" class="btn btn-sm">Reiniciar campaña</button>
    </form>
  </article>

  <article class="panel">
    <div class="panel-head">
      <h2>Mazmorra de estados</h2>
      <a href="<?= e(url('/index.php?tab=tracker')) ?>">Diario</a>
    </div>
    <ul class="pipe-list">
      <?php
      $pipe = [
          ['Postulado', (int) ($counts['applied'] ?? 0)],
          ['Follow-up', (int) ($counts['follow_up'] ?? 0)],
          ['Entrevistas', $interviews],
          ['Ofertas', $offers],
          ['Rechazadas', $rejected],
      ];
      $pipeMax = max(1, ...array_column($pipe, 1));
      foreach ($pipe as [$label, $value]):
      ?>
        <li>
          <span><?= e($label) ?></span>
          <div class="pipe-list__bar"><i style="width:<?= e((string) round(($value / $pipeMax) * 100)) ?>%"></i></div>
          <strong><?= e((string) $value) ?></strong>
        </li>
      <?php endforeach; ?>
    </ul>
  </article>
</section>

<section class="panel">
  <div class="panel-head">
    <h2>Quests de hoy</h2>
    <a class="btn btn-sm btn-accent" href="<?= e(url('/index.php?tab=tracker&new=1')) ?>#app-form">+ Sumar</a>
  </div>
  <?php if (!$todayApps): ?>
    <p class="muted" style="margin:0">Todavía no hay envíos hoy. El mago está listo cuando vos lo estés.</p>
  <?php else: ?>
    <ul class="today-list">
      <?php foreach ($todayApps as $app): ?>
        <li>
          <div>
            <strong><?= e((string) $app['company']) ?></strong>
            <span class="muted"><?= e((string) $app['role_title']) ?></span>
          </div>
          <span class="badge <?= e(stage_badge_class((string) ($app['stage'] ?? ''))) ?>"><?= e(stage_label($app['stage'] ?? '')) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
