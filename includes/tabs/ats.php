<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);

$panels = [
    'es' => [
        'title' => 'Roles ATS · Español',
        'hint' => 'Títulos de rol para pegar en filtros ATS, uno por línea. Guardá y copiá cuando los necesites.',
        'placeholder' => "Engineering Manager\nLíder técnico\nHead of Engineering\nStaff Engineer\n...",
    ],
    'en' => [
        'title' => 'ATS roles · English',
        'hint' => 'Job titles for ATS filters, one per line. Save and copy when you need them.',
        'placeholder' => "Engineering Manager\nTechnical Lead\nHead of Engineering\nStaff Software Engineer\n...",
    ],
];
?>
<div class="page-head">
  <div>
    <h1>Roles ATS</h1>
    <p class="subtitle">Títulos de puesto (ES/EN) para buscar en LinkedIn y ATS. El stack técnico vive en Stack.</p>
  </div>
</div>

<div class="ats-grid">
  <?php foreach ($panels as $lang => $meta):
      $text = load_ats_lake_for_user($userId, $lang);
      $keywords = parse_ats_keywords($text);
      $bodyId = 'ats-body-' . $lang;
  ?>
    <section class="panel ats-panel" id="ats-<?= e($lang) ?>">
      <div class="panel-head">
        <div>
          <h2><?= e($meta['title']) ?></h2>
          <div class="muted" style="margin-top:.25rem"><?= e($meta['hint']) ?></div>
        </div>
        <span class="count"><?= e((string) count($keywords)) ?> rol(es)</span>
      </div>

      <form method="post" action="<?= e(url('/actions/save_ats_lake.php')) ?>">
        <input type="hidden" name="lang" value="<?= e($lang) ?>">
        <div class="field">
          <label for="<?= e($bodyId) ?>">Listado (un rol por línea)</label>
          <textarea
            class="ats-textarea"
            name="text"
            id="<?= e($bodyId) ?>"
            rows="18"
            placeholder="<?= e($meta['placeholder']) ?>"
          ><?= e($text) ?></textarea>
        </div>
        <div class="ats-actions">
          <button type="submit" class="btn btn-sm btn-accent">Guardar</button>
          <button type="button" class="btn btn-sm btn-copy" data-copy-target="<?= e($bodyId) ?>">Copiar todo</button>
        </div>
      </form>

      <?php if ($keywords): ?>
        <div class="ats-chips" aria-label="Vista rápida de roles">
          <?php foreach (array_slice($keywords, 0, 80) as $kw): ?>
            <button type="button" class="ats-chip btn-copy" data-copy-text="<?= e($kw) ?>" title="Copiar"><?= e($kw) ?></button>
          <?php endforeach; ?>
          <?php if (count($keywords) > 80): ?>
            <span class="muted">+<?= e((string) (count($keywords) - 80)) ?> más en el listado</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
