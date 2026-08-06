<?php

declare(strict_types=1);

$items = load_json_data('hr_faq.json');
$categories = [];
foreach ($items as $row) {
    $cat = (string) ($row['category'] ?? 'General');
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
}
ksort($categories);

$filter = trim((string) ($_GET['cat'] ?? ''));
$q = trim((string) ($_GET['q'] ?? ''));

$filtered = [];
foreach ($items as $row) {
    if ($filter !== '' && ($row['category'] ?? '') !== $filter) {
        continue;
    }
    if ($q !== '') {
        $hay = mb_strtolower(($row['question'] ?? '') . ' ' . ($row['answer'] ?? '') . ' ' . ($row['category'] ?? ''), 'UTF-8');
        if (!str_contains($hay, mb_strtolower($q, 'UTF-8'))) {
            continue;
        }
    }
    $filtered[] = $row;
}
?>
<div class="page-head">
  <div>
    <h1>HR FAQ</h1>
    <p class="subtitle"><?= e((string) count($items)) ?> preguntas clásicas con respuestas genéricas en español. Personalizá con tus hechos.</p>
  </div>
</div>

<div class="toolbar sticky-toolbar faq-toolbar">
  <form class="faq-search" method="get" action="<?= e(url('/index.php')) ?>">
    <input type="hidden" name="tab" value="hr_faq">
    <?php if ($filter !== ''): ?>
      <input type="hidden" name="cat" value="<?= e($filter) ?>">
    <?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar pregunta o respuesta…" aria-label="Buscar">
    <button type="submit" class="btn btn-sm btn-accent">Buscar</button>
    <?php if ($q !== '' || $filter !== ''): ?>
      <a class="btn btn-sm" href="<?= e(url('/index.php?tab=hr_faq')) ?>">Limpiar</a>
    <?php endif; ?>
  </form>
  <div class="chip-row">
    <a class="chip <?= $filter === '' ? 'is-active' : '' ?>" href="<?= e(url('/index.php?tab=hr_faq' . ($q !== '' ? '&q=' . rawurlencode($q) : ''))) ?>">Todas</a>
    <?php foreach ($categories as $cat => $count): ?>
      <a class="chip <?= $filter === $cat ? 'is-active' : '' ?>"
         href="<?= e(url('/index.php?tab=hr_faq&cat=' . rawurlencode($cat) . ($q !== '' ? '&q=' . rawurlencode($q) : ''))) ?>">
        <?= e($cat) ?> <span class="muted">(<?= e((string) $count) ?>)</span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<p class="muted" style="margin:.25rem 0 1rem"><?= e((string) count($filtered)) ?> resultado(s)</p>

<?php if (!$filtered): ?>
  <div class="panel warn-panel">No hay preguntas para ese filtro.</div>
<?php else: ?>
  <div class="faq-list">
    <?php foreach ($filtered as $row):
        $id = (int) ($row['id'] ?? 0);
    ?>
      <details class="panel faq-item" id="faq-<?= e((string) $id) ?>">
        <summary>
          <span class="faq-num">#<?= e((string) $id) ?></span>
          <span class="faq-q"><?= e($row['question'] ?? '') ?></span>
          <span class="badge badge-muted"><?= e($row['category'] ?? '') ?></span>
        </summary>
        <div class="faq-a">
          <p><?= nl2br(e($row['answer'] ?? '')) ?></p>
          <button type="button" class="btn btn-sm btn-copy" data-copy-text="<?= e($row['answer'] ?? '') ?>">Copiar respuesta</button>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
