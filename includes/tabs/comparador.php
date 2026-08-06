<?php

declare(strict_types=1);

require_once __DIR__ . '/../storage.php';

$apps = load_applications();
$offers = [];
foreach ($apps as $app) {
    if (!in_array($app['stage'] ?? '', ['offer', 'accepted'], true)) {
        continue;
    }
    $o = is_array($app['offer'] ?? null) ? $app['offer'] : [];
    $rank = compute_offer_ranking($app);
    $offers[] = array_merge($app, [
        'compensation_score' => (int) ($o['compensation_score'] ?? 0),
        'role_fit_score' => (int) ($o['role_fit_score'] ?? 0),
        'growth_score' => (int) ($o['growth_score'] ?? 0),
        'culture_score' => (int) ($o['culture_score'] ?? 0),
        'schedule_score' => (int) ($o['schedule_score'] ?? 0),
        'risk_score' => (int) ($o['risk_score'] ?? 0),
        'total_comp_monthly' => $o['total_comp_monthly'] ?? null,
        'offer_currency' => $o['currency'] ?? null,
        'employment_type' => $o['employment_type'] ?? null,
        'remote_policy' => $o['remote_policy'] ?? null,
        'schedule_type' => $o['schedule_type'] ?? null,
        'offer_notes' => $o['notes'] ?? null,
        'ranking_notes' => $o['ranking_notes'] ?? null,
        'rank_score' => $rank['score'],
        'rank_label' => $rank['label'],
        'rank_breakdown' => $rank['breakdown'],
    ]);
}
usort($offers, static fn ($a, $b) => ($b['rank_score'] <=> $a['rank_score']));

$dims = [
    'compensation_score' => 'Comp (manual 0–10)',
    'role_fit_score' => 'Role fit',
    'growth_score' => 'Growth',
    'culture_score' => 'Cultura',
    'schedule_score' => 'Flex horario (0–10)',
    'risk_score' => 'Riesgo (resta)',
];
?>
<div class="page-head">
  <div>
    <h1>Comparador</h1>
    <p class="subtitle">
      Ranking 0–100. Ideal = <strong>full remoto desde Argentina</strong> + sueldo excelente
      (ARS 7M / USD 10k) + <strong>horario flexible</strong>. Todo lo demás baja desde ahí.
    </p>
  </div>
</div>

<div class="panel rank-legend">
  <div class="rank-legend__ideal">
    <span class="badge badge-ok">Techo 100</span>
    Full remoto AR · compensación excelente · horario flexible · bajo riesgo
  </div>
  <div class="rank-weights">
    <span>Comp 35</span>
    <span>Remoto AR 25</span>
    <span>Horario 15</span>
    <span>Fit/Growth/Cultura 15</span>
    <span>Riesgo −15</span>
  </div>
</div>

<?php if (!$offers): ?>
  <div class="panel">
    <p class="muted" style="margin:0">Todavía no hay ofertas. Pasá una postulación a <strong>Oferta</strong> en el Tracker.</p>
  </div>
<?php else: ?>
<section class="panel">
  <div style="overflow:auto">
    <table class="data-table">
      <thead>
        <tr>
          <th>Rank</th>
          <th>Empresa / Rol</th>
          <th>Score</th>
          <th>Remoto</th>
          <th>Horario</th>
          <th>Comp mensual</th>
          <th>Desglose</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($offers as $i => $o): ?>
          <tr>
            <td><strong>#<?= e((string) ($i + 1)) ?></strong></td>
            <td>
              <div class="name"><?= e($o['company']) ?></div>
              <div class="muted"><?= e($o['role_title']) ?></div>
            </td>
            <td>
              <strong><?= e(number_format((float) $o['rank_score'], 1)) ?></strong>
              <div class="muted" style="font-size:.8rem"><?= e($o['rank_label']) ?></div>
            </td>
            <td><?= e(remote_policy_options()[$o['remote_policy'] ?? ''] ?? ($o['remote_policy'] ?: '—')) ?></td>
            <td><?= e(schedule_type_options()[$o['schedule_type'] ?? ''] ?? ($o['schedule_type'] ?: '—')) ?></td>
            <td>
              <?php if ($o['total_comp_monthly'] !== null): ?>
                <?= e(($o['offer_currency'] ?? '') . ' ' . number_format((float) $o['total_comp_monthly'], 0, ',', '.')) ?>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td class="muted" style="font-size:.82rem;white-space:nowrap">
              C<?= e((string) $o['rank_breakdown']['compensacion']) ?>
              · R<?= e((string) $o['rank_breakdown']['remoto_ar']) ?>
              · H<?= e((string) $o['rank_breakdown']['horario']) ?>
              · Q<?= e((string) $o['rank_breakdown']['calidad']) ?>
              · K<?= e((string) $o['rank_breakdown']['riesgo']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($offers as $i => $o): ?>
  <article class="panel" id="offer-<?= e((string) $o['id']) ?>">
    <div class="panel-head">
      <h2>#<?= e((string) ($i + 1)) ?> · <?= e($o['company']) ?></h2>
      <span class="count"><?= e(number_format((float) $o['rank_score'], 1)) ?> / 100 · <?= e($o['rank_label']) ?></span>
    </div>

    <div class="rank-bars">
      <?php
      $bars = [
          'compensacion' => ['Compensación', 35],
          'remoto_ar' => ['Remoto AR', 25],
          'horario' => ['Horario', 15],
          'calidad' => ['Calidad', 15],
      ];
      foreach ($bars as $key => [$label, $max]):
          $val = max(0, (float) ($o['rank_breakdown'][$key] ?? 0));
          $pct = $max > 0 ? min(100, ($val / $max) * 100) : 0;
      ?>
        <div class="rank-bar">
          <div class="rank-bar__label"><span><?= e($label) ?></span><span><?= e((string) $val) ?>/<?= e((string) $max) ?></span></div>
          <div class="rank-bar__track"><div class="rank-bar__fill" style="width:<?= e((string) round($pct)) ?>%"></div></div>
        </div>
      <?php endforeach; ?>
      <?php $riskAbs = abs((float) ($o['rank_breakdown']['riesgo'] ?? 0)); ?>
      <div class="rank-bar rank-bar--risk">
        <div class="rank-bar__label"><span>Riesgo (resta)</span><span>−<?= e(number_format($riskAbs, 1)) ?></span></div>
        <div class="rank-bar__track"><div class="rank-bar__fill" style="width:<?= e((string) round(($riskAbs / 15) * 100)) ?>%"></div></div>
      </div>
    </div>

    <form method="post" action="<?= e(url('/actions/save_offer_score.php')) ?>" class="form-grid" style="margin-top:1rem">
      <input type="hidden" name="application_id" value="<?= e((string) $o['id']) ?>">
      <?php foreach ($dims as $field => $label): ?>
        <div class="field span-2">
          <label><?= e($label) ?></label>
          <input type="number" min="0" max="10" name="<?= e($field) ?>" value="<?= e((string) $o[$field]) ?>">
        </div>
      <?php endforeach; ?>
      <div class="field span-3">
        <label>Comp mensual</label>
        <input type="number" step="0.01" name="total_comp_monthly" value="<?= e((string) ($o['total_comp_monthly'] ?? '')) ?>">
      </div>
      <div class="field span-2">
        <label>Moneda</label>
        <select name="currency">
          <option value="">—</option>
          <?php foreach (['ARS','USD','EUR','other'] as $cur): ?>
            <option value="<?= e($cur) ?>" <?= ($o['offer_currency'] ?? '') === $cur ? 'selected' : '' ?>><?= e($cur) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field span-3">
        <label>Modalidad remota</label>
        <select name="remote_policy">
          <option value="">—</option>
          <?php foreach (remote_policy_options() as $val => $lab): ?>
            <option value="<?= e($val) ?>" <?= ($o['remote_policy'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field span-4">
        <label>Tipo de horario</label>
        <select name="schedule_type">
          <option value="">— (usar score 0–10)</option>
          <?php foreach (schedule_type_options() as $val => $lab): ?>
            <option value="<?= e($val) ?>" <?= ($o['schedule_type'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field span-3">
        <label>Employment type</label>
        <input type="text" name="employment_type" value="<?= e((string) ($o['employment_type'] ?? '')) ?>" placeholder="full-time / contract…">
      </div>
      <div class="field span-6">
        <label>Notas</label>
        <textarea name="notes" rows="2"><?= e((string) ($o['offer_notes'] ?? '')) ?></textarea>
      </div>
      <div class="field span-6">
        <label>Notas de ranking</label>
        <textarea name="ranking_notes" rows="2"><?= e((string) ($o['ranking_notes'] ?? '')) ?></textarea>
      </div>
      <div class="field span-12" style="flex-direction:row;gap:.5rem">
        <button type="submit" class="btn btn-accent">Guardar y recalcular</button>
        <a class="btn" href="<?= e(url('/index.php?tab=tracker&edit=' . $o['id'])) ?>#app-form">Editar en Tracker</a>
      </div>
    </form>
  </article>
<?php endforeach; ?>
<?php endif; ?>
