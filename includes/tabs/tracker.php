<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);

$stages = stage_labels();
$liveStages = pipeline_stages();

$filterStage = preg_replace('/[^a-z_]/', '', (string) ($_GET['stage'] ?? '')) ?: '';
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$isNew = isset($_GET['new']);
$fromDate = preg_replace('/[^0-9\-]/', '', (string) ($_GET['from'] ?? '')) ?: '';
$toDate = preg_replace('/[^0-9\-]/', '', (string) ($_GET['to'] ?? '')) ?: '';
$view = ($_GET['view'] ?? '') === 'kanban' ? 'kanban' : 'days';

$trackerBase = array_filter([
    'tab' => 'tracker',
    'view' => $view === 'kanban' ? 'kanban' : null,
    'q' => $q !== '' ? $q : null,
    'stage' => $filterStage !== '' ? $filterStage : null,
    'from' => $fromDate !== '' ? $fromDate : null,
    'to' => $toDate !== '' ? $toDate : null,
], static fn ($v) => $v !== null && $v !== '');

$allApps = load_applications_for_user($userId);
$stats = application_stats_for_user($userId);

$apps = array_values(array_filter($allApps, static function ($app) use ($filterStage, $q, $fromDate, $toDate, $stages) {
    if ($filterStage !== '' && isset($stages[$filterStage]) && ($app['stage'] ?? '') !== $filterStage) {
        return false;
    }
    if ($q !== '') {
        $hay = strtolower(($app['company'] ?? '') . ' ' . ($app['role_title'] ?? '') . ' ' . ($app['platform'] ?? ''));
        if (!str_contains($hay, strtolower($q))) {
            return false;
        }
    }
    $d = (string) ($app['application_date'] ?? '');
    if ($d === '' && !empty($app['created_at'])) {
        $d = substr((string) $app['created_at'], 0, 10);
    }
    if ($fromDate !== '' && ($d === '' || $d < $fromDate)) {
        return false;
    }
    if ($toDate !== '' && ($d === '' || $d > $toDate)) {
        return false;
    }
    return true;
}));

usort($apps, static function ($a, $b) {
    $da = (string) ($a['application_date'] ?? substr((string) ($a['created_at'] ?? ''), 0, 10));
    $db = (string) ($b['application_date'] ?? substr((string) ($b['created_at'] ?? ''), 0, 10));
    if ($da !== $db) {
        return strcmp($db, $da);
    }
    return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
});

$byDay = [];
foreach ($apps as $app) {
    $d = (string) ($app['application_date'] ?? '');
    if ($d === '' && !empty($app['created_at'])) {
        $d = substr((string) $app['created_at'], 0, 10);
    }
    if ($d === '') {
        $d = 'sin-fecha';
    }
    $byDay[$d][] = $app;
}

$kanbanCols = $liveStages;
$byStage = array_fill_keys(array_keys($kanbanCols), []);
foreach ($apps as $app) {
    $st = normalize_pipeline_stage((string) ($app['stage'] ?? 'applied'));
    if (!isset($byStage[$st])) {
        $st = 'applied';
    }
    $byStage[$st][] = $app;
}

$weekdays = [
    'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo',
];

$editing = null;
if ($editId > 0) {
    $editing = find_application_for_user($userId, $editId);
}
if ($isNew) {
    $editing = [
        'id' => 0,
        'company' => '',
        'role_title' => '',
        'market' => 'ar',
        'platform' => 'LinkedIn',
        'canonical_url' => '',
        'application_date' => date('Y-m-d'),
        'stage' => 'applied',
        'notes' => '',
        'follow_up_date' => '',
        'contact_name' => '',
        'fit_score' => '',
        'location_eligible' => 1,
        'cv_version' => '',
        'cover_letter' => '',
        'currency' => 'ARS',
        'salary_max' => '',
        'salary_note' => '',
        'remote_policy' => '',
        'schedule_type' => '',
    ];
}
$cvOptions = load_document_groups_for_user($userId, 'cv');
$coverOptions = load_document_groups_for_user($userId, 'cover_letter');
$noSalary = $editing !== null && (string) ($editing['salary_note'] ?? '') === 'no indiqué';
$showExtra = $editing !== null && (
    (int) ($editing['id'] ?? 0) > 0
    || !empty($editing['follow_up_date'])
    || !empty($editing['notes'])
);
?>
<div class="page-head">
  <div>
    <h1>Diario de la aventura</h1>
    <p class="subtitle"><?= $view === 'kanban' ? 'Arrastrá las cards entre columnas para cambiar el estado.' : 'Cada día es un capítulo. Agrupá envíos, cambiá el estado y seguí la campaña.' ?></p>
  </div>
  <div class="chip-row">
    <a class="btn btn-sm <?= $view === 'days' ? 'is-on' : '' ?>" href="<?= e(url('/index.php?' . http_build_query(array_diff_key($trackerBase, ['view' => true])))) ?>">Diario</a>
    <a class="btn btn-sm <?= $view === 'kanban' ? 'is-on' : '' ?>" href="<?= e(url('/index.php?' . http_build_query(array_merge($trackerBase, ['view' => 'kanban'])))) ?>">Kanban</a>
    <a class="btn btn-accent" href="<?= e(url('/index.php?' . http_build_query(array_merge($trackerBase, ['new' => 1])))) ?>">+ Hoy</a>
  </div>
</div>

<?php
$submitted = (int) ($stats['submitted'] ?? 0);
$target = campaign_target_for_user($userId);
$goalReturn = 'tracker';
require __DIR__ . '/../goal_panel.php';
?>

<section class="day-summary">
  <div class="day-summary__item">
    <span class="day-summary__label">Total</span>
    <strong><?= e((string) (int) ($stats['submitted'] ?? 0)) ?></strong>
  </div>
  <div class="day-summary__item">
    <span class="day-summary__label">Hoy</span>
    <strong><?= e((string) (int) ($stats['today'] ?? 0)) ?></strong>
  </div>
  <div class="day-summary__item">
    <span class="day-summary__label">Días</span>
    <strong><?= e((string) (int) ($stats['days_active'] ?? 0)) ?></strong>
  </div>
  <div class="day-summary__item">
    <span class="day-summary__label">Follow-ups</span>
    <strong><?= e((string) (int) ($stats['overdue'] ?? 0)) ?></strong>
  </div>
</section>

<?php if ($editing !== null): ?>
<?php render_modal_start('app-form', (int) ($editing['id'] ?? 0) > 0 ? 'Editar postulación' : 'Nueva postulación', true, 'lg'); ?>
  <form method="post" action="<?= e(url('/actions/save_application.php')) ?>" class="slim-form">
    <input type="hidden" name="id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <input type="hidden" name="market" value="<?= e((string) ($editing['market'] ?? 'ar')) ?>">
    <div class="slim-form__row">
      <div class="field">
        <label for="company">Empresa</label>
        <input id="company" type="text" name="company" required autofocus value="<?= e((string) ($editing['company'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="role_title">Rol</label>
        <input id="role_title" type="text" name="role_title" required value="<?= e((string) ($editing['role_title'] ?? '')) ?>">
      </div>
    </div>
    <div class="slim-form__row slim-form__row--3">
      <div class="field">
        <label for="application_date">Día</label>
        <input id="application_date" type="date" name="application_date" required value="<?= e((string) ($editing['application_date'] ?? date('Y-m-d'))) ?>">
      </div>
      <div class="field">
        <label for="stage">Estado</label>
        <select id="stage" name="stage" required>
          <?php foreach ($liveStages as $key => $label): ?>
            <option value="<?= e($key) ?>" <?= ($editing['stage'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="platform">Portal</label>
        <input id="platform" type="text" name="platform" value="<?= e((string) ($editing['platform'] ?? '')) ?>" placeholder="Bumeran, Computrabajo…">
      </div>
    </div>
    <div class="field">
      <label for="canonical_url">Link</label>
      <input id="canonical_url" type="url" name="canonical_url" value="<?= e((string) ($editing['canonical_url'] ?? '')) ?>">
    </div>
    <div class="slim-form__row slim-form__row--3">
      <div class="field">
        <label for="cv_version">CV que usaste</label>
        <select id="cv_version" name="cv_version" required>
          <option value="">Elegí un CV…</option>
          <?php if (!$cvOptions): ?>
            <option value="CV maestro (ES)">CV maestro (ES)</option>
            <option value="Master CV (EN)">Master CV (EN)</option>
          <?php endif; ?>
          <?php foreach ($cvOptions as $cv): ?>
            <option value="<?= e((string) $cv['name']) ?>" <?= ($editing['cv_version'] ?? '') === $cv['name'] ? 'selected' : '' ?>><?= e((string) $cv['name']) ?></option>
          <?php endforeach; ?>
          <?php
            $cvCurrent = (string) ($editing['cv_version'] ?? '');
            $cvNames = array_map(static fn ($g) => (string) $g['name'], $cvOptions);
            if ($cvCurrent !== '' && !in_array($cvCurrent, $cvNames, true)):
          ?>
            <option value="<?= e($cvCurrent) ?>" selected><?= e($cvCurrent) ?></option>
          <?php endif; ?>
        </select>
      </div>
      <div class="field">
        <label for="cover_letter">¿Cover letter?</label>
        <select id="cover_letter" name="cover_letter" required>
          <option value="">Elegí…</option>
          <option value="no" <?= ($editing['cover_letter'] ?? '') === 'no' ? 'selected' : '' ?>>No</option>
          <?php foreach ($coverOptions as $cover): ?>
            <option value="<?= e((string) $cover['name']) ?>" <?= ($editing['cover_letter'] ?? '') === $cover['name'] ? 'selected' : '' ?>><?= e((string) $cover['name']) ?></option>
          <?php endforeach; ?>
          <?php
            $coverCurrent = (string) ($editing['cover_letter'] ?? '');
            $coverNames = array_map(static fn ($g) => (string) $g['name'], $coverOptions);
            if ($coverCurrent !== '' && $coverCurrent !== 'no' && !in_array($coverCurrent, $coverNames, true)):
          ?>
            <option value="<?= e($coverCurrent) ?>" selected><?= e($coverCurrent) ?></option>
          <?php endif; ?>
        </select>
      </div>
      <div class="field">
        <label for="salary_max">Sueldo que cargaste</label>
        <div class="salary-entry">
          <input id="salary_max" type="number" name="salary_max" min="0" step="1" value="<?= e((string) ($editing['salary_max'] ?? '')) ?>" <?= $noSalary ? '' : 'required' ?>>
          <select name="currency" aria-label="Moneda">
            <?php foreach (['ARS' => 'ARS', 'USD' => 'USD', 'EUR' => 'EUR'] as $cur => $lab): ?>
              <option value="<?= e($cur) ?>" <?= ($editing['currency'] ?? 'ARS') === $cur ? 'selected' : '' ?>><?= e($lab) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <label class="check-inline">
          <input type="checkbox" name="no_salary" value="1" <?= $noSalary ? 'checked' : '' ?>
                 onchange="document.getElementById('salary_max').required = !this.checked">
          No indiqué sueldo
        </label>
      </div>
    </div>
    <div class="slim-form__row slim-form__row--3">
      <div class="field">
        <label for="remote_policy">Modalidad</label>
        <select id="remote_policy" name="remote_policy">
          <option value="">Elegí…</option>
          <?php foreach (remote_policy_options() as $val => $lab):
              if ($val === 'unknown') {
                  continue;
              }
          ?>
            <option value="<?= e($val) ?>" <?= ($editing['remote_policy'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="schedule_type">Horario</label>
        <select id="schedule_type" name="schedule_type">
          <option value="">Elegí…</option>
          <?php foreach (schedule_type_options() as $val => $lab): ?>
            <option value="<?= e($val) ?>" <?= ($editing['schedule_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label class="check-inline" style="margin-top:1.45rem">
          <input type="checkbox" name="location_eligible" value="1" <?= (int) ($editing['location_eligible'] ?? 1) === 1 ? 'checked' : '' ?>>
          Desde Argentina
        </label>
      </div>
    </div>
    <details class="more-fields" <?= $showExtra ? 'open' : '' ?>>
      <summary>Más detalles</summary>
      <div class="slim-form__row slim-form__row--3">
        <div class="field">
          <label for="follow_up_date">Follow-up</label>
          <input id="follow_up_date" type="date" name="follow_up_date" value="<?= e((string) ($editing['follow_up_date'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="contact_name">Contacto</label>
          <input id="contact_name" type="text" name="contact_name" value="<?= e((string) ($editing['contact_name'] ?? '')) ?>">
        </div>
        <div class="field">
          <label for="fit_score">Fit %</label>
          <input id="fit_score" type="number" min="0" max="100" name="fit_score" value="<?= e((string) ($editing['fit_score'] ?? '')) ?>">
        </div>
      </div>
      <div class="field">
        <label for="notes">Notas</label>
        <textarea id="notes" name="notes" rows="3"><?= e((string) ($editing['notes'] ?? '')) ?></textarea>
      </div>
      <label class="check-inline"><input type="checkbox" name="force_duplicate" value="1"> Forzar si parece duplicado</label>
    </details>
    <div class="slim-form__actions">
      <button type="submit" class="btn btn-accent">Guardar</button>
    </div>
  </form>
<?php render_modal_end(); ?>
<?php endif; ?>

<section class="panel filters-compact">
  <form method="get" action="<?= e(url('/index.php')) ?>" class="filters-compact__form">
    <input type="hidden" name="tab" value="tracker">
    <?php if ($view === 'kanban'): ?><input type="hidden" name="view" value="kanban"><?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar empresa o rol…">
    <select name="stage">
      <option value="">Todos los estados</option>
      <?php foreach ($liveStages as $key => $label): ?>
        <option value="<?= e($key) ?>" <?= $filterStage === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($fromDate) ?>">
    <input type="date" name="to" value="<?= e($toDate) ?>">
    <button class="btn btn-sm" type="submit">Filtrar</button>
  </form>
</section>

<?php if ($view === 'kanban'): ?>
<section class="kanban-board" aria-label="Kanban de postulaciones">
  <?php foreach ($kanbanCols as $stageKey => $stageName): ?>
    <?php $colApps = $byStage[$stageKey] ?? []; ?>
    <section class="kanban-col" data-stage="<?= e((string) $stageKey) ?>">
      <div class="kanban-col__head">
        <span><?= e((string) $stageName) ?></span>
        <span class="badge kanban-count"><?= e((string) count($colApps)) ?></span>
      </div>
      <?php foreach ($colApps as $app): ?>
        <?php
          $appDate = (string) ($app['application_date'] ?? '');
          if ($appDate === '' && !empty($app['created_at'])) {
              $appDate = substr((string) $app['created_at'], 0, 10);
          }
        ?>
        <article class="kanban-card" draggable="true" data-id="<?= e((string) $app['id']) ?>" id="app-<?= e((string) $app['id']) ?>">
          <strong><?= e((string) $app['company']) ?></strong>
          <span class="kanban-card__role"><?= e((string) $app['role_title']) ?></span>
          <span class="kanban-card__meta"><?= e($appDate !== '' ? format_display_date($appDate) : '—') ?><?php
            if (!empty($app['cv_version'])) {
                echo ' · ' . e((string) $app['cv_version']);
            }
            if ($app['salary_max'] !== null && $app['salary_max'] !== '') {
                echo ' · ' . e(trim((string) ($app['currency'] ?? '') . ' ' . number_format((float) $app['salary_max'], 0, ',', '.')));
            }
          ?></span>
          <a class="kanban-card__edit" draggable="false" href="<?= e(url('/index.php?' . http_build_query(array_merge($trackerBase, ['edit' => $app['id']])))) ?>">Editar</a>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</section>
<?php elseif (!$byDay): ?>
  <section class="panel empty-day">
    <h2>El diario está en blanco</h2>
    <p class="muted">Cargá el primer envío de hoy. El mago anota el resto.</p>
    <a class="btn btn-accent" href="<?= e(url('/index.php?' . http_build_query(array_merge($trackerBase, ['new' => 1])))) ?>">Abrir capítulo de hoy</a>
  </section>
<?php else: ?>
  <div class="day-stack">
    <?php foreach ($byDay as $dayKey => $dayApps): ?>
      <?php
      $isToday = $dayKey === date('Y-m-d');
      $dayTitle = $dayKey === 'sin-fecha' ? 'Sin fecha' : format_display_date($dayKey);
      $weekday = '';
      if ($dayKey !== 'sin-fecha') {
          $weekday = $weekdays[(new DateTimeImmutable($dayKey))->format('l')] ?? '';
      }
      ?>
      <section class="day-group<?= $isToday ? ' is-today' : '' ?>" id="day-<?= e($dayKey) ?>">
        <header class="day-group__head">
          <div>
            <h2><?= e($dayTitle) ?><?= $isToday ? ' · HOY' : '' ?></h2>
            <?php if ($weekday !== ''): ?><p class="muted"><?= e($weekday) ?></p><?php endif; ?>
          </div>
          <span class="day-group__count"><?= count($dayApps) ?></span>
        </header>
        <ul class="day-group__list">
          <?php foreach ($dayApps as $app): ?>
            <li class="day-card" id="app-<?= e((string) $app['id']) ?>">
              <div>
                <strong><?= e((string) $app['company']) ?></strong>
                <span class="day-card__role"><?= e((string) $app['role_title']) ?></span>
                <div class="day-card__meta">
                  <?php if (!empty($app['platform'])): ?>
                    <span class="muted"><?= e((string) $app['platform']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($app['cv_version'])): ?>
                    <span class="muted">CV: <?= e((string) $app['cv_version']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($app['cover_letter']) && $app['cover_letter'] !== 'no'): ?>
                    <span class="muted">Cover: <?= e((string) $app['cover_letter']) ?></span>
                  <?php elseif (($app['cover_letter'] ?? '') === 'no'): ?>
                    <span class="muted">Sin cover</span>
                  <?php endif; ?>
                  <?php if ($app['salary_max'] !== null && $app['salary_max'] !== ''): ?>
                    <span class="muted"><?= e(trim((string) ($app['currency'] ?? '') . ' ' . number_format((float) $app['salary_max'], 0, ',', '.'))) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($app['remote_policy'])): ?>
                    <span class="muted"><?= e(remote_policy_options()[$app['remote_policy']] ?? (string) $app['remote_policy']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($app['schedule_type'])): ?>
                    <span class="muted"><?= e(schedule_type_options()[$app['schedule_type']] ?? (string) $app['schedule_type']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="day-card__actions">
                <?php if (!empty($app['canonical_url'])): ?>
                  <a class="btn btn-sm" href="<?= e((string) $app['canonical_url']) ?>" target="_blank" rel="noopener">Ver</a>
                <?php endif; ?>
                <a class="btn btn-sm" href="<?= e(url('/index.php?tab=tracker&edit=' . $app['id'])) ?>">Editar</a>
                <form method="post" action="<?= e(url('/actions/delete_application.php')) ?>" onsubmit="return confirm('¿Borrar esta postulación?');">
                  <input type="hidden" name="id" value="<?= e((string) $app['id']) ?>">
                  <input type="hidden" name="view" value="<?= e($view) ?>">
                  <button type="submit" class="btn btn-sm btn-danger">Borrar</button>
                </form>
                <form method="post" action="<?= e(url('/actions/update_application_stage.php')) ?>">
                  <input type="hidden" name="id" value="<?= e((string) $app['id']) ?>">
                  <input type="hidden" name="view" value="<?= e($view) ?>">
                  <select class="stage-select" name="stage" onchange="this.form.submit()" aria-label="Estado">
                    <?php foreach ($liveStages as $key => $label): ?>
                      <option value="<?= e($key) ?>" <?= ($app['stage'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </form>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
