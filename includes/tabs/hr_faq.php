<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$items = load_json_data('hr_faq.json');
$mine = load_hr_answers_for_user($userId);
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
    $id = (int) ($row['id'] ?? 0);
    if (isset($mine[$id])) {
        if (!empty($mine[$id]['question'])) {
            $row['question'] = $mine[$id]['question'];
        }
        $row['answer'] = $mine[$id]['answer'];
        $row['is_mine'] = true;
    }
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
    <h1>Preguntas de HR</h1>
    <p class="subtitle"><?= e((string) count($items)) ?> clásicas. Las respuestas se guardan en tu usuario: editá y dejá la tuya.</p>
  </div>
</div>

<div class="toolbar faq-toolbar">
  <form class="faq-search" method="get" action="<?= e(url('/index.php')) ?>">
    <input type="hidden" name="tab" value="hr_faq">
    <?php if ($filter !== ''): ?>
      <input type="hidden" name="cat" value="<?= e($filter) ?>">
    <?php endif; ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar pregunta o respuesta…" aria-label="Buscar">
    <button type="submit" class="btn btn-sm">Buscar</button>
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
        $answer = (string) ($row['answer'] ?? '');
    ?>
      <details class="panel faq-item" id="faq-<?= e((string) $id) ?>">
        <summary>
          <span class="faq-num">#<?= e((string) $id) ?></span>
          <span class="faq-q"><?= e($row['question'] ?? '') ?></span>
          <span class="badge <?= !empty($row['is_mine']) ? 'badge-ok' : 'badge-muted' ?>"><?= !empty($row['is_mine']) ? 'Tuya' : e((string) ($row['category'] ?? '')) ?></span>
        </summary>
        <div class="faq-a">
          <?php if ($answer !== ''): ?>
            <p><?= nl2br(e($answer)) ?></p>
          <?php else: ?>
            <p class="muted">Todavía no escribiste tu respuesta.</p>
          <?php endif; ?>
          <div class="chip-row">
            <button type="button" class="btn btn-sm btn-accent" data-modal-open="faq-edit-<?= e((string) $id) ?>">Editar</button>
            <button type="button" class="btn btn-sm btn-copy" data-copy-text="<?= e($answer) ?>">Copiar</button>
          </div>
        </div>
      </details>
      <?php render_modal_start('faq-edit-' . $id, 'Editar respuesta'); ?>
        <form method="post" action="<?= e(url('/actions/save_hr_answer.php')) ?>" class="stack">
          <input type="hidden" name="faq_id" value="<?= e((string) $id) ?>">
          <div class="field">
            <label for="question-<?= e((string) $id) ?>">Pregunta</label>
            <input id="question-<?= e((string) $id) ?>" name="question" maxlength="512" value="<?= e((string) ($row['question'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="answer-<?= e((string) $id) ?>">Tu respuesta</label>
            <textarea id="answer-<?= e((string) $id) ?>" name="answer" rows="7"><?= e($answer) ?></textarea>
          </div>
          <button type="submit" class="btn btn-accent">Guardar respuesta</button>
        </form>
      <?php render_modal_end(); ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
