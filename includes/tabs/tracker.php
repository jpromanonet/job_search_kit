<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config.php';
require_once __DIR__ . '/../storage.php';

$stages = stage_labels();
$families = role_families();

$filterStage = preg_replace('/[^a-z_]/', '', (string) ($_GET['stage'] ?? '')) ?: '';
$filterMarket = preg_replace('/[^a-z]/', '', (string) ($_GET['market'] ?? '')) ?: '';
$q = trim((string) ($_GET['q'] ?? ''));
$editId = (int) ($_GET['edit'] ?? 0);
$isNew = isset($_GET['new']);
$view = ($_GET['view'] ?? 'table') === 'kanban' ? 'kanban' : 'table';

$apps = load_applications();

// filters
$apps = array_values(array_filter($apps, static function ($app) use ($filterStage, $filterMarket, $q, $stages) {
    if ($filterStage !== '' && isset($stages[$filterStage]) && ($app['stage'] ?? '') !== $filterStage) {
        return false;
    }
    if (in_array($filterMarket, ['ar', 'intl'], true) && ($app['market'] ?? '') !== $filterMarket) {
        return false;
    }
    if ($q !== '') {
        $hay = strtolower(($app['company'] ?? '') . ' ' . ($app['role_title'] ?? '') . ' ' . ($app['platform'] ?? '') . ' ' . ($app['notes'] ?? ''));
        if (!str_contains($hay, strtolower($q))) {
            return false;
        }
    }
    return true;
}));

$stageOrder = array_keys($stages);
usort($apps, static function ($a, $b) use ($stageOrder) {
    $ia = array_search($a['stage'] ?? 'discovered', $stageOrder, true);
    $ib = array_search($b['stage'] ?? 'discovered', $stageOrder, true);
    $ia = $ia === false ? 99 : $ia;
    $ib = $ib === false ? 99 : $ib;
    if ($ia !== $ib) {
        return $ia <=> $ib;
    }
    return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
});

$stats = application_stats(load_applications());
$counts = $stats['counts'];
$marketCounts = $stats['market'];
$totalSubmitted = $stats['submitted'];
$overdue = $stats['overdue'];

$editing = null;
if ($editId > 0) {
    $editing = find_application($editId);
}
if ($isNew) {
    $prefillDay = isset($_GET['day']) ? (int) $_GET['day'] : 0;
    if ($prefillDay < 1 || $prefillDay > 100) {
        $prefillDay = campaign_day_number($config['app']['campaign_start']) ?: null;
    }
    $editing = [
        'id' => 0,
        'day_number' => $prefillDay,
        'company' => '',
        'role_title' => '',
        'market' => 'ar',
        'platform' => '',
        'discovery_source' => '',
        'canonical_url' => '',
        'location_eligible' => 1,
        'role_family' => '',
        'cv_version' => '',
        'cover_letter' => '',
        'salary_note' => '',
        'currency' => '',
        'salary_min' => '',
        'salary_max' => '',
        'fit_score' => '',
        'application_date' => date('Y-m-d'),
        'contact_name' => '',
        'follow_up_date' => '',
        'stage' => 'applied',
        'result_notes' => '',
        'notes' => '',
    ];
}

$todayNum = campaign_day_number($config['app']['campaign_start']);
?>
<div class="page-head">
  <div>
    <h1>Tracker</h1>
    <p class="subtitle">Cargá, editá y seguí cada postulación. Se guarda en archivo local (sin depender de MySQL).</p>
  </div>
  <div class="chip-row">
    <a class="btn btn-sm" href="<?= e(url('/actions/export_applications_csv.php')) ?>">CSV</a>
    <a class="btn btn-sm <?= $view === 'table' ? 'btn-accent' : '' ?>" href="<?= e(url('/index.php?tab=tracker&view=table')) ?>">Tabla</a>
    <a class="btn btn-sm <?= $view === 'kanban' ? 'btn-accent' : '' ?>" href="<?= e(url('/index.php?tab=tracker&view=kanban')) ?>">Kanban</a>
    <a class="btn btn-accent btn-sm" href="<?= e(url('/index.php?tab=tracker&new=1')) ?>#app-form">+ Nueva postulación</a>
  </div>
</div>

<div class="stats">
  <div class="stat"><div class="stat-label">Enviadas</div><div class="stat-value"><?= e((string) $totalSubmitted) ?><span class="stat-slash">/1000</span></div></div>
  <div class="stat"><div class="stat-label">Argentina</div><div class="stat-value"><?= e((string) $marketCounts['ar']) ?><span class="stat-slash">/500</span></div></div>
  <div class="stat"><div class="stat-label">Internacional</div><div class="stat-value"><?= e((string) $marketCounts['intl']) ?><span class="stat-slash">/500</span></div></div>
  <div class="stat"><div class="stat-label">Follow-ups vencidos</div><div class="stat-value"><?= e((string) $overdue) ?></div></div>
  <div class="stat"><div class="stat-label">Ofertas</div><div class="stat-value"><?= e((string) ($counts['offer'] ?? 0)) ?></div></div>
  <div class="stat"><div class="stat-label">Día campaña</div><div class="stat-value"><?= $todayNum > 0 ? 'D' . e((string) $todayNum) : 'Pre' ?></div></div>
</div>

<section class="panel">
  <form class="form-grid" method="get" action="<?= e(url('/index.php')) ?>">
    <input type="hidden" name="tab" value="tracker">
    <input type="hidden" name="view" value="<?= e($view) ?>">
    <div class="field span-4">
      <label>Buscar</label>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Empresa, rol, portal…">
    </div>
    <div class="field span-3">
      <label>Stage</label>
      <select name="stage">
        <option value="">Todos</option>
        <?php foreach ($stages as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $filterStage === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field span-2">
      <label>Mercado</label>
      <select name="market">
        <option value="">Ambos</option>
        <option value="ar" <?= $filterMarket === 'ar' ? 'selected' : '' ?>>AR</option>
        <option value="intl" <?= $filterMarket === 'intl' ? 'selected' : '' ?>>INTL</option>
      </select>
    </div>
    <div class="field span-3" style="flex-direction:row;align-items:flex-end;gap:.4rem">
      <button class="btn btn-accent" type="submit">Filtrar</button>
      <a class="btn" href="<?= e(url('/index.php?tab=tracker&view=' . $view)) ?>">Limpiar</a>
    </div>
  </form>
</section>

<div class="chip-row" style="margin-bottom:1rem">
  <a href="<?= e(url('/index.php?tab=tracker&view=' . $view)) ?>" class="chip <?= $filterStage === '' ? 'is-active' : '' ?>">todos <?= count(load_applications()) ?></a>
  <?php foreach ($stages as $key => $label): ?>
    <a href="<?= e(url('/index.php?tab=tracker&view=' . $view . '&stage=' . $key)) ?>" class="chip <?= $filterStage === $key ? 'is-active' : '' ?>">
      <?= e($label) ?> <?= e((string) ($counts[$key] ?? 0)) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($editing !== null): ?>
<article class="panel" id="app-form">
  <div class="panel-head">
    <h2><?= (int) ($editing['id'] ?? 0) > 0 ? 'Editar #' . e((string) $editing['id']) : 'Nueva postulación' ?></h2>
    <a href="<?= e(url('/index.php?tab=tracker')) ?>" class="btn btn-sm">Cancelar</a>
  </div>
  <form method="post" action="<?= e(url('/actions/save_application.php')) ?>" class="form-grid">
    <input type="hidden" name="id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">

    <div class="field span-4">
      <label>Empresa *</label>
      <input type="text" name="company" required value="<?= e((string) ($editing['company'] ?? '')) ?>">
    </div>
    <div class="field span-4">
      <label>Rol *</label>
      <input type="text" name="role_title" required value="<?= e((string) ($editing['role_title'] ?? '')) ?>">
    </div>
    <div class="field span-2">
      <label>Mercado *</label>
      <select name="market" required>
        <option value="ar" <?= ($editing['market'] ?? '') === 'ar' ? 'selected' : '' ?>>AR</option>
        <option value="intl" <?= ($editing['market'] ?? '') === 'intl' ? 'selected' : '' ?>>INTL</option>
      </select>
    </div>
    <div class="field span-2">
      <label>Día plan</label>
      <input type="number" min="1" max="100" name="day_number" value="<?= e((string) ($editing['day_number'] ?? '')) ?>">
    </div>

    <div class="field span-3">
      <label>Portal / board</label>
      <input type="text" name="platform" value="<?= e((string) ($editing['platform'] ?? '')) ?>" placeholder="LinkedIn, Bumeran…">
    </div>
    <div class="field span-3">
      <label>Discovery source</label>
      <input type="text" name="discovery_source" value="<?= e((string) ($editing['discovery_source'] ?? '')) ?>">
    </div>
    <div class="field span-6">
      <label>URL canónica</label>
      <input type="url" name="canonical_url" value="<?= e((string) ($editing['canonical_url'] ?? '')) ?>">
    </div>

    <div class="field span-4">
      <label>Role family</label>
      <select name="role_family">
        <option value="">—</option>
        <?php foreach ($families as $fam): ?>
          <option value="<?= e($fam) ?>" <?= ($editing['role_family'] ?? '') === $fam ? 'selected' : '' ?>><?= e($fam) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field span-4">
      <label>CV version</label>
      <input type="text" name="cv_version" value="<?= e((string) ($editing['cv_version'] ?? '')) ?>">
    </div>
    <div class="field span-4">
      <label>Cover letter</label>
      <input type="text" name="cover_letter" value="<?= e((string) ($editing['cover_letter'] ?? '')) ?>">
    </div>

    <div class="field span-2">
      <label>Fit %</label>
      <input type="number" min="0" max="100" name="fit_score" value="<?= e((string) ($editing['fit_score'] ?? '')) ?>">
    </div>
    <div class="field span-2">
      <label>Currency</label>
      <select name="currency">
        <option value="">—</option>
        <?php foreach (['ARS','USD','EUR','other'] as $cur): ?>
          <option value="<?= e($cur) ?>" <?= ($editing['currency'] ?? '') === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field span-2">
      <label>Salary min</label>
      <input type="number" step="0.01" name="salary_min" value="<?= e((string) ($editing['salary_min'] ?? '')) ?>">
    </div>
    <div class="field span-2">
      <label>Salary max</label>
      <input type="number" step="0.01" name="salary_max" value="<?= e((string) ($editing['salary_max'] ?? '')) ?>">
    </div>
    <div class="field span-4">
      <label>Salary note</label>
      <input type="text" name="salary_note" value="<?= e((string) ($editing['salary_note'] ?? '')) ?>">
    </div>

    <div class="field span-3">
      <label>Fecha postulación</label>
      <input type="date" name="application_date" value="<?= e((string) ($editing['application_date'] ?? '')) ?>">
    </div>
    <div class="field span-3">
      <label>Follow-up</label>
      <input type="date" name="follow_up_date" value="<?= e((string) ($editing['follow_up_date'] ?? '')) ?>">
    </div>
    <div class="field span-3">
      <label>Contacto</label>
      <input type="text" name="contact_name" value="<?= e((string) ($editing['contact_name'] ?? '')) ?>">
    </div>
    <div class="field span-3">
      <label>Stage *</label>
      <select name="stage" required>
        <?php foreach ($stages as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= ($editing['stage'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field span-6">
      <label>Result notes</label>
      <textarea name="result_notes" rows="2"><?= e((string) ($editing['result_notes'] ?? '')) ?></textarea>
    </div>
    <div class="field span-6">
      <label>Notes</label>
      <textarea name="notes" rows="2"><?= e((string) ($editing['notes'] ?? '')) ?></textarea>
    </div>

    <div class="field span-12">
      <div class="check-row">
        <label><input type="checkbox" name="location_eligible" value="1" <?= !isset($editing['location_eligible']) || (int) $editing['location_eligible'] ? 'checked' : '' ?>> Location eligible (AR accepted)</label>
        <label><input type="checkbox" name="force_duplicate" value="1"> Forzar si hay duplicado</label>
      </div>
    </div>
    <div class="field span-12" style="flex-direction:row">
      <button type="submit" class="btn btn-accent">Guardar postulación</button>
    </div>
  </form>
</article>
<?php endif; ?>

<?php if ($view === 'kanban'): ?>
  <div class="kanban-board">
    <?php
    $kanbanStages = ['discovered','preparing','applied','follow_up','recruiter_screen','technical','final','offer'];
    $byStage = [];
    foreach ($apps as $app) {
        $byStage[$app['stage'] ?? 'discovered'][] = $app;
    }
    foreach ($kanbanStages as $st):
        $col = $byStage[$st] ?? [];
    ?>
      <div class="kanban-col">
        <div class="kanban-col__head"><?= e(stage_label($st)) ?> <span class="badge"><?= count($col) ?></span></div>
        <?php foreach ($col as $app): ?>
          <a class="kanban-card" id="app-<?= e((string) $app['id']) ?>" href="<?= e(url('/index.php?tab=tracker&edit=' . $app['id'])) ?>#app-form">
            <strong><?= e($app['company']) ?></strong>
            <div class="muted"><?= e($app['role_title']) ?></div>
            <div class="muted"><?= e(strtoupper((string) $app['market'])) ?> · <?= e($app['platform'] ?? '—') ?></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="panel">
    <div style="overflow:auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th><th>Empresa</th><th>Rol</th><th>Mkt</th><th>Fuente</th>
            <th>Fit</th><th>Stage</th><th>Fecha</th><th>Follow-up</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$apps): ?>
            <tr><td colspan="10" class="muted">Todavía no hay postulaciones. Tocá <strong>+ Nueva postulación</strong>.</td></tr>
          <?php endif; ?>
          <?php foreach ($apps as $app): ?>
            <tr id="app-<?= e((string) $app['id']) ?>">
              <td>#<?= e((string) $app['id']) ?></td>
              <td>
                <strong><?= e($app['company']) ?></strong>
                <?php if (!empty($app['canonical_url'])): ?>
                  <a href="<?= e($app['canonical_url']) ?>" target="_blank" rel="noopener">↗</a>
                <?php endif; ?>
              </td>
              <td><?= e($app['role_title']) ?></td>
              <td><?= e(strtoupper((string) $app['market'])) ?></td>
              <td><?= e($app['platform'] ?? '') ?></td>
              <td><?= $app['fit_score'] !== null && $app['fit_score'] !== '' ? e((string) $app['fit_score']) . '%' : '—' ?></td>
              <td><span class="badge"><?= e(stage_label($app['stage'] ?? '')) ?></span></td>
              <td><?= e($app['application_date'] ?? '') ?></td>
              <td><?= e($app['follow_up_date'] ?? '') ?></td>
              <td>
                <div class="actions">
                  <a class="link-edit" href="<?= e(url('/index.php?tab=tracker&edit=' . $app['id'])) ?>#app-form">Editar</a>
                  <?php if (in_array($app['stage'] ?? '', ['offer','accepted'], true)): ?>
                    <a class="link-edit" href="<?= e(url('/index.php?tab=comparador')) ?>#offer-<?= e((string) $app['id']) ?>">Comparar</a>
                  <?php endif; ?>
                  <form method="post" action="<?= e(url('/actions/delete_application.php')) ?>" onsubmit="return confirm('¿Eliminar #<?= e((string) $app['id']) ?>?');">
                    <input type="hidden" name="id" value="<?= e((string) $app['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
