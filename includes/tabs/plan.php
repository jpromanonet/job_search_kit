<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config.php';
$days = load_plan_days();
$appTarget = (int) $config['app']['target_applications'];

$totals = [
    'planned' => 0,
    'logged' => 0,
    'done' => 0,
    'in_progress' => 0,
    'blocked' => 0,
    'missed' => 0,
    'content_complete' => 0,
    'content_due' => 0,
];
foreach ($days as $d) {
    $totals['planned'] += (int) $d['applications_target'];
    $totals['logged'] += (int) ($d['applications_logged'] ?? 0);
    $st = $d['status'] ?? 'not_started';
    if (isset($totals[$st])) {
        $totals[$st]++;
    }
    $isBlogDay = !empty($d['blog_publish']);
    if ($isBlogDay) {
        $totals['content_due']++;
        if ((int) ($d['instagram_done'] ?? 0) && (int) ($d['linkedin_posted'] ?? 0) && (int) ($d['x_posted'] ?? 0) && !empty($d['article_url'])) {
            $totals['content_complete']++;
        }
    }
}

$todayNum = focus_day_from_progress($days);
$focusDay = $todayNum;
$filter = $_GET['filter'] ?? 'all';
$remaining = max(0, $appTarget - $totals['logged']);
?>
<div class="page-head">
  <div>
    <h1>Plan de 100 días</h1>
    <p class="subtitle"><?= e((string) count($days)) ?> días · solo Argentina · máx. 5 apps/día · 1 artículo/semana</p>
  </div>
</div>

<?php if (!$days): ?>
  <div class="panel warn-panel">
    <h2>Falta data/days.php</h2>
    <p>Corré <code>python tools/embed_days.py</code>.</p>
  </div>
<?php else: ?>

<div class="stats">
  <div class="stat">
    <div class="stat-label">Día actual</div>
    <div class="stat-value">D<?= e((string) $todayNum) ?></div>
    <div class="stat-meta">próximo a trabajar</div>
  </div>
  <div class="stat">
    <div class="stat-label">Postulaciones</div>
    <div class="stat-value"><?= e((string) $totals['logged']) ?><span class="stat-slash">/<?= e((string) $appTarget) ?></span></div>
    <div class="stat-meta">Planificadas: <?= e((string) $totals['planned']) ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Restantes</div>
    <div class="stat-value"><?= e((string) $remaining) ?></div>
    <div class="stat-meta">hasta la meta</div>
  </div>
  <div class="stat">
    <div class="stat-label">Días hechos</div>
    <div class="stat-value"><?= e((string) $totals['done']) ?><span class="stat-slash">/100</span></div>
    <div class="stat-meta">En curso: <?= e((string) $totals['in_progress']) ?></div>
  </div>
  <div class="stat">
    <div class="stat-label">Blog semanal</div>
    <div class="stat-value"><?= e((string) $totals['content_complete']) ?><span class="stat-slash">/<?= e((string) $totals['content_due']) ?></span></div>
    <div class="stat-meta">días de publicación</div>
  </div>
</div>

<div class="toolbar plan-toolbar sticky-toolbar">
  <div class="chip-row">
    <?php
    $filters = [
        'all' => 'Todos',
        'today' => 'Hoy',
        'build' => 'Construcción 1–7',
        'high_volume' => 'Ejecución',
        'finish' => 'Cierre',
        'not_started' => 'Sin empezar',
        'in_progress' => 'En curso',
        'done' => 'Hechos',
    ];
    foreach ($filters as $key => $label):
    ?>
      <a class="chip <?= $filter === $key ? 'is-active' : '' ?>"
         href="<?= e(url('/index.php?tab=plan&filter=' . $key)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <div class="jump-box">
    <label class="muted" for="jumpDay">Ir al día</label>
    <input type="number" id="jumpDay" min="1" max="100" value="<?= e((string) $focusDay) ?>">
    <button type="button" class="btn btn-sm" id="jumpDayBtn">Ir</button>
    <a class="btn btn-sm btn-accent" href="<?= e(url('/index.php?tab=plan&filter=today')) ?>">Hoy</a>
  </div>
</div>

<?php if ($filter === 'today'): ?>
  <div class="flash flash-info">
    “Hoy” muestra el <strong>Día <?= e((string) $focusDay) ?></strong>: el próximo que todavía no marcaste como Hecho.
  </div>
<?php endif; ?>

<div class="day-stack" id="dayStack">
<?php
$shown = 0;
foreach ($days as $day):
    $num = (int) $day['day_number'];
    $show = true;
    if ($filter === 'today') {
        $show = $num === $focusDay;
    } elseif (in_array($filter, ['build', 'high_volume', 'finish'], true)) {
        $show = ($day['phase'] ?? '') === $filter;
    } elseif (in_array($filter, ['not_started', 'in_progress', 'done', 'blocked', 'missed'], true)) {
        $show = ($day['status'] ?? '') === $filter;
    }
    if (!$show) {
        continue;
    }
    $shown++;

    $candidate = json_list($day['candidate_tasks'] ?? []);
    $ai = json_list($day['ai_tasks'] ?? []);
    $execute = json_list($day['execute_today'] ?? []);
    $sources = json_list($day['source_allocations'] ?? []);
    $isToday = $num === $focusDay;
?>
  <article class="day-card <?= $isToday ? 'is-today' : '' ?> <?= e(phase_badge_class($day['phase'] ?? '')) ?>"
           id="day-<?= e((string) $num) ?>">
    <header class="day-card__head">
      <div>
        <span class="day-number">Día <?= e((string) $num) ?></span>
        <?php if ($isToday): ?>
          <span class="badge badge-warn">HOY</span>
        <?php endif; ?>
      </div>
      <div class="chip-row">
        <span class="badge badge-info"><?= e(phase_label_display($day['phase_label'] ?? '', $day['phase'] ?? '')) ?></span>
        <span class="badge badge-muted"><?= e(status_label($day['status'] ?? 'not_started')) ?></span>
        <span class="badge"><?= e($day['quota_label'] ?? '') ?></span>
      </div>
    </header>

    <div class="day-card__body">
      <?php if (!empty($day['outcome'])): ?>
        <p><strong>Resultado:</strong> <?= e($day['outcome']) ?></p>
      <?php endif; ?>
      <?php if (!empty($day['special_focus'])): ?>
        <p><strong>Enfoque especial:</strong> <?= e($day['special_focus']) ?></p>
      <?php endif; ?>

      <?php if ($sources): ?>
        <div class="box" style="overflow:auto">
          <table class="data-table">
            <thead>
              <tr><th>Mercado</th><th>Fuentes</th><th>Apps</th><th>Validación</th></tr>
            </thead>
            <tbody>
              <?php foreach ($sources as $row): ?>
                <tr>
                  <td><?= e($row['market'] ?? '') ?></td>
                  <td><?= e($row['allocation'] ?? '') ?></td>
                  <td><?= e((string) ($row['apps'] ?? '')) ?></td>
                  <td><?= e($row['validation'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

      <div class="grid-2" style="margin-top:1rem">
        <?php if ($candidate): ?>
          <div>
            <h3 class="day-section-title">Candidato — ejecutar</h3>
            <ul class="task-list"><?php foreach ($candidate as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <?php if ($ai): ?>
          <div>
            <h3 class="day-section-title">AI copilot</h3>
            <ul class="task-list"><?php foreach ($ai as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($execute): ?>
        <h3 class="day-section-title">Ejecutar hoy — en orden</h3>
        <ol class="task-list"><?php foreach ($execute as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ol>
      <?php endif; ?>

      <?php if (!empty($day['definition_of_done'])): ?>
        <div class="box"><strong>Definición de hecho</strong><p class="muted" style="margin:.4rem 0 0"><?= e($day['definition_of_done']) ?></p></div>
      <?php endif; ?>

      <?php
        $isBlogDay = !empty($day['blog_publish']);
      ?>
      <?php if ($isBlogDay): ?>
      <div class="grid-2" style="margin-top:1rem">
        <div class="box">
          <span class="content-label">Blog · publicar esta semana</span>
          <div style="font-family:var(--font-display);font-weight:600;margin-bottom:.35rem"><?= e($day['blog_title'] ?? '') ?></div>
          <?php if (!empty($day['blog_angle'])): ?><p class="muted" style="margin:0"><em>Ángulo:</em> <?= e($day['blog_angle']) ?></p><?php endif; ?>
          <?php if (!empty($day['blog_draft'])): ?><p class="muted" style="margin:.4rem 0 0"><?= e($day['blog_draft']) ?></p><?php endif; ?>
        </div>
        <div>
          <div class="box">
            <span class="content-label">X</span>
            <pre class="copy-block"><?= e($day['x_copy'] ?? '') ?></pre>
          </div>
          <div class="box">
            <span class="content-label">LinkedIn</span>
            <pre class="copy-block"><?= e($day['linkedin_copy'] ?? '') ?></pre>
          </div>
          <div class="box">
            <span class="content-label">Instagram</span>
            <p class="muted" style="margin:0"><?= e($day['instagram_task'] ?? '') ?></p>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($day['close_day_proof'])): ?>
        <div class="box"><strong>Prueba de cierre</strong><p class="muted" style="margin:.4rem 0 0"><?= e($day['close_day_proof']) ?></p></div>
      <?php endif; ?>

      <form class="form-grid" style="margin-top:1.1rem" method="post" action="<?= e(url('/actions/update_day.php')) ?>">
        <input type="hidden" name="day_number" value="<?= e((string) $num) ?>">
        <input type="hidden" name="return_filter" value="<?= e($filter) ?>">
        <div class="field span-3">
          <label>Estado</label>
          <select name="status">
            <?php foreach (['not_started','in_progress','done','blocked','missed'] as $st): ?>
              <option value="<?= e($st) ?>" <?= ($day['status'] ?? '') === $st ? 'selected' : '' ?>><?= e(status_label($st)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field span-3">
          <label>Apps registradas</label>
          <input type="number" min="0" max="50" name="applications_logged" value="<?= e((string) ($day['applications_logged'] ?? 0)) ?>">
        </div>
        <?php if ($isBlogDay): ?>
          <div class="field span-6">
            <label>URL del artículo</label>
            <input type="url" name="article_url" value="<?= e($day['article_url'] ?? '') ?>" placeholder="https://...">
          </div>
          <div class="field span-12">
            <div class="check-row">
              <label><input type="checkbox" name="linkedin_posted" value="1" <?= (int) ($day['linkedin_posted'] ?? 0) ? 'checked' : '' ?>> LinkedIn</label>
              <label><input type="checkbox" name="x_posted" value="1" <?= (int) ($day['x_posted'] ?? 0) ? 'checked' : '' ?>> X</label>
              <label><input type="checkbox" name="instagram_done" value="1" <?= (int) ($day['instagram_done'] ?? 0) ? 'checked' : '' ?>> Instagram Story</label>
            </div>
          </div>
        <?php else: ?>
          <input type="hidden" name="article_url" value="">
        <?php endif; ?>
        <div class="field span-6"><label>Notas</label><textarea name="notes" rows="2"><?= e($day['notes'] ?? '') ?></textarea></div>
        <div class="field span-6"><label>Evidencia</label><textarea name="evidence" rows="2"><?= e($day['evidence'] ?? '') ?></textarea></div>
        <div class="field span-6"><label>Bloqueos</label><textarea name="blockers" rows="2"><?= e($day['blockers'] ?? '') ?></textarea></div>
        <div class="field span-6"><label>Carry-forward</label><textarea name="carry_forward" rows="2"><?= e($day['carry_forward'] ?? '') ?></textarea></div>
        <div class="field span-12" style="flex-direction:row;gap:.5rem;flex-wrap:wrap">
          <button type="submit" class="btn btn-accent">Guardar día <?= e((string) $num) ?></button>
          <a class="btn" href="<?= e(url('/index.php?tab=tracker&new=1&day=' . $num)) ?>#app-form">Cargar postulación</a>
        </div>
      </form>
    </div>
  </article>
<?php endforeach; ?>
</div>

<?php if ($shown === 0): ?>
  <div class="panel warn-panel">
    <h2>Sin resultados</h2>
    <p>No hay días para este filtro. <a href="<?= e(url('/index.php?tab=plan&filter=all')) ?>">Ver todos</a></p>
  </div>
<?php endif; ?>

<?php endif; ?>
