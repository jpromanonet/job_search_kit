<?php

declare(strict_types=1);

/** @var int $submitted */
/** @var int $target */

$goalReturn = $goalReturn ?? 'dashboard';
$submitted = (int) $submitted;
$target = max(1, (int) $target);
$remaining = max(0, $target - $submitted);
$overflow = max(0, $submitted - $target);
$pct = round(($submitted / $target) * 100, 1);
$barPct = min(100, $pct);
$done = $remaining === 0;
?>
<section class="goal-quest<?= $done ? ' is-done' : '' ?>" id="objetivo">
  <div class="goal-quest__copy">
    <p class="eyebrow">Objetivo general</p>
    <h2><?= e((string) $submitted) ?> / <?= e((string) $target) ?></h2>
    <?php if ($overflow > 0): ?>
      <p class="muted">Te pasaste por <?= e((string) $overflow) ?>. Subí el número a mano y seguimos midiendo.</p>
    <?php elseif ($remaining > 0): ?>
      <p class="muted">Faltan <strong><?= e((string) $remaining) ?></strong> para el objetivo · <?= e((string) $pct) ?>% hecho · <?= e((string) round(100 - $pct, 1)) ?>% restante</p>
    <?php else: ?>
      <p class="muted">Objetivo cumplido. Si querés seguir, cambiá el número.</p>
    <?php endif; ?>
    <div class="goal-rail" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= e((string) (int) $barPct) ?>">
      <i style="width: <?= e((string) $barPct) ?>%"></i>
    </div>
    <div class="goal-rail__meta">
      <span><?= e((string) $pct) ?>%</span>
      <span><?= $overflow > 0 ? '+' . $overflow : $remaining . ' restan' ?></span>
    </div>
  </div>
  <form method="post" action="<?= e(url('/actions/save_campaign_target.php')) ?>" class="goal-quest__form">
    <input type="hidden" name="return_tab" value="<?= e($goalReturn) ?>">
    <label for="target_applications">Cambiar objetivo</label>
    <div class="goal-quest__row">
      <input id="target_applications" type="number" name="target_applications" min="1" max="50000" required value="<?= e((string) $target) ?>">
      <button type="submit" class="btn btn-accent">Guardar</button>
    </div>
  </form>
</section>
