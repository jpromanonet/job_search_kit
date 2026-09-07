<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$campaign = campaign_settings_for_user($userId);
$targets = array_merge(offer_ceiling_defaults(), $campaign);

$apps = load_offers_for_user($userId);
$offers = [];
foreach ($apps as $app) {
    $o = is_array($app['offer'] ?? null) ? $app['offer'] : [];
    $rank = compute_offer_ranking($app, $targets);
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

$fmtMoney = static function (float $n): string {
    return number_format($n, 0, ',', '.');
};
?>
<div class="page-head">
  <div>
    <h1>Comparar tesoros</h1>
    <p class="subtitle">
      Ranking contra <strong>tu techo</strong>:
      <?= e(remote_policy_options()[$targets['ideal_remote']] ?? $targets['ideal_remote']) ?>
      <?= !empty($targets['ideal_require_ar']) ? 'desde Argentina' : '' ?>
      · <?= e(schedule_type_options()[$targets['ideal_schedule']] ?? $targets['ideal_schedule']) ?>
      · ARS <?= e($fmtMoney((float) $targets['ideal_comp_ars'])) ?>
      · USD <?= e($fmtMoney((float) $targets['ideal_comp_usd'])) ?>
      · EUR <?= e($fmtMoney((float) $targets['ideal_comp_eur'])) ?>.
    </p>
  </div>
  <button type="button" class="btn btn-accent" data-modal-open="techo-form">Editar techo</button>
</div>

<?php render_modal_start('techo-form', 'Tu techo', false, 'wide'); ?>
  <p class="muted" style="margin-top:0">Definí el trabajo ideal. El ranking mide cada oferta contra esto, no solo el sueldo.</p>
  <form method="post" action="<?= e(url('/actions/save_offer_targets.php')) ?>" class="ceiling-form">
    <div class="field">
      <label for="ideal_comp_ars">ARS / mes</label>
      <input id="ideal_comp_ars" type="number" name="ideal_comp_ars" min="1" step="1" required value="<?= e((string) (int) $targets['ideal_comp_ars']) ?>">
    </div>
    <div class="field">
      <label for="ideal_comp_usd">USD / mes</label>
      <input id="ideal_comp_usd" type="number" name="ideal_comp_usd" min="1" step="1" required value="<?= e((string) (int) $targets['ideal_comp_usd']) ?>">
    </div>
    <div class="field">
      <label for="ideal_comp_eur">EUR / mes</label>
      <input id="ideal_comp_eur" type="number" name="ideal_comp_eur" min="1" step="1" required value="<?= e((string) (int) $targets['ideal_comp_eur']) ?>">
    </div>
    <div class="field">
      <label for="ideal_remote">Modalidad</label>
      <select id="ideal_remote" name="ideal_remote">
        <?php foreach (remote_policy_options() as $val => $lab):
            if ($val === 'unknown') {
                continue;
            }
        ?>
          <option value="<?= e($val) ?>" <?= ($targets['ideal_remote'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="ideal_schedule">Horario</label>
      <select id="ideal_schedule" name="ideal_schedule">
        <?php foreach (schedule_type_options() as $val => $lab): ?>
          <option value="<?= e($val) ?>" <?= ($targets['ideal_schedule'] ?? '') === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label class="check-inline" style="margin-top:1.4rem">
        <input type="checkbox" name="ideal_require_ar" value="1" <?= !empty($targets['ideal_require_ar']) ? 'checked' : '' ?>>
        Tiene que ser desde Argentina
      </label>
    </div>
    <div class="ceiling-weights">
      <div class="field">
        <label for="weight_comp">Peso sueldo</label>
        <input id="weight_comp" type="number" name="weight_comp" min="0" max="80" value="<?= e((string) (int) $targets['weight_comp']) ?>">
      </div>
      <div class="field">
        <label for="weight_remote">Peso remoto</label>
        <input id="weight_remote" type="number" name="weight_remote" min="0" max="80" value="<?= e((string) (int) $targets['weight_remote']) ?>">
      </div>
      <div class="field">
        <label for="weight_schedule">Peso horario</label>
        <input id="weight_schedule" type="number" name="weight_schedule" min="0" max="80" value="<?= e((string) (int) $targets['weight_schedule']) ?>">
      </div>
      <div class="field">
        <label for="weight_quality">Peso calidad</label>
        <input id="weight_quality" type="number" name="weight_quality" min="0" max="80" value="<?= e((string) (int) $targets['weight_quality']) ?>">
      </div>
      <div class="field">
        <label for="weight_risk">Peso riesgo (−)</label>
        <input id="weight_risk" type="number" name="weight_risk" min="0" max="80" value="<?= e((string) (int) $targets['weight_risk']) ?>">
      </div>
    </div>
    <div class="field ceiling-form__go">
      <button type="submit" class="btn btn-accent">Guardar techo</button>
    </div>
  </form>
<?php render_modal_end(); ?>

<?php if (!$offers): ?>
  <div class="panel">
    <p class="muted" style="margin:0">Todavía no hay ofertas. Pasá una postulación a <strong>Oferta</strong> en el Diario y cargá acá sueldo, remoto y scores.</p>
  </div>
<?php else: ?>
<section class="panel">
  <div class="table-wrap">
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
              <?php if ($o['total_comp_monthly'] !== null && $o['total_comp_monthly'] !== ''): ?>
                <?= e(($o['offer_currency'] ?? '') . ' ' . $fmtMoney((float) $o['total_comp_monthly'])) ?>
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

<div class="offer-grid">
<?php foreach ($offers as $i => $o): ?>
  <article class="panel" id="offer-<?= e((string) $o['id']) ?>">
    <div class="panel-head">
      <h2>#<?= e((string) ($i + 1)) ?> · <?= e($o['company']) ?></h2>
      <span class="count"><?= e(number_format((float) $o['rank_score'], 1)) ?> / 100 · <?= e($o['rank_label']) ?></span>
    </div>

    <div class="rank-bars">
      <?php
      $bars = [
          'compensacion' => ['Compensación', (int) $targets['weight_comp']],
          'remoto_ar' => ['Remoto', (int) $targets['weight_remote']],
          'horario' => ['Horario', (int) $targets['weight_schedule']],
          'calidad' => ['Calidad', (int) $targets['weight_quality']],
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
        <div class="rank-bar__track"><div class="rank-bar__fill" style="width:<?= e((string) round(($riskAbs / max(1, (int) $targets['weight_risk'])) * 100)) ?>%"></div></div>
      </div>
    </div>

    <button type="button" class="btn btn-sm btn-accent" data-modal-open="offer-edit-<?= e((string) $o['id']) ?>">Editar oferta</button>
    <?php render_modal_start('offer-edit-' . (string) $o['id'], 'Editar oferta · ' . (string) $o['company'], false, 'wide'); ?>
    <form method="post" action="<?= e(url('/actions/save_offer_score.php')) ?>" class="form-grid">
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
        <a class="btn" href="<?= e(url('/index.php?tab=tracker&edit=' . $o['id'])) ?>">Editar en Diario</a>
      </div>
    </form>
    <?php render_modal_end(); ?>
  </article>
<?php endforeach; ?>
</div>
<?php endif; ?>
