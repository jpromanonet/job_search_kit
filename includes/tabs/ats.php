<?php

declare(strict_types=1);

$lakes = load_ats_lakes();
$panels = [
    'es' => [
        'title' => 'Lake ATS · Español',
        'hint' => 'Pegá keywords en español, una por línea. Guardá y copiá cuando las necesites.',
        'placeholder' => "leadership\ngestión de equipos\n.NET\nobservabilidad\n...",
    ],
    'en' => [
        'title' => 'ATS lake · English',
        'hint' => 'Paste English keywords, one per line. Save and copy when you need them.',
        'placeholder' => "leadership\nteam management\n.NET\nobservability\n...",
    ],
];
?>
<div class="page-head">
  <div>
    <h1>Palabras ATS</h1>
    <p class="subtitle">Dos lakes editables: español e inglés. Pegá el texto, guardá y copiá keywords al CV.</p>
  </div>
</div>

<div class="ats-grid">
  <?php foreach ($panels as $lang => $meta):
      $row = $lakes[$lang];
      $text = (string) ($row['text'] ?? '');
      $keywords = parse_ats_keywords($text);
      $bodyId = 'ats-body-' . $lang;
  ?>
    <section class="panel ats-panel" id="ats-<?= e($lang) ?>">
      <div class="panel-head">
        <div>
          <h2><?= e($meta['title']) ?></h2>
          <div class="muted" style="margin-top:.25rem"><?= e($meta['hint']) ?></div>
        </div>
        <span class="count"><?= e((string) count($keywords)) ?> palabra(s)</span>
      </div>

      <?php if (!empty($row['updated_at'])): ?>
        <p class="muted ats-meta">Actualizado: <?= e((string) $row['updated_at']) ?></p>
      <?php endif; ?>

      <form method="post" action="<?= e(url('/actions/save_ats_lake.php')) ?>">
        <input type="hidden" name="lang" value="<?= e($lang) ?>">
        <div class="field">
          <label for="<?= e($bodyId) ?>">Listado (una palabra o frase por línea)</label>
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
        <div class="ats-chips" aria-label="Vista rápida de keywords">
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
