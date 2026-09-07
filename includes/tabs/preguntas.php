<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$mine = load_user_questions($userId);
$editId = (int) ($_GET['edit'] ?? 0);
$editing = null;
foreach ($mine as $row) {
    if ((int) $row['id'] === $editId) {
        $editing = $row;
        break;
    }
}
?>
<div class="page-head">
  <div>
    <h1>Preguntas a la empresa</h1>
    <p class="subtitle">Estas preguntas son tuyas. Editá las de base o sumá otras. Elegí 3–4 al final de la charla.</p>
  </div>
  <?php if ($editing): ?>
    <a class="btn btn-accent" href="<?= e(url('/index.php?tab=preguntas')) ?>">+ Pregunta</a>
  <?php else: ?>
    <button type="button" class="btn btn-accent" data-modal-open="pregunta-form">+ Pregunta</button>
  <?php endif; ?>
</div>

<?php render_modal_start('pregunta-form', $editing ? 'Editar pregunta' : 'Nueva pregunta', $editing !== null); ?>
  <form method="post" action="<?= e(url('/actions/save_user_question.php')) ?>" class="stack">
    <input type="hidden" name="id" value="<?= e((string) (int) ($editing['id'] ?? 0)) ?>">
    <div class="field">
      <label for="uq_question">Pregunta</label>
      <input id="uq_question" name="question" required maxlength="512" value="<?= e((string) ($editing['question'] ?? '')) ?>" placeholder="¿Cómo miden el éxito de este rol?">
    </div>
    <div class="field">
      <label for="uq_why">Por qué la hago</label>
      <textarea id="uq_why" name="why" rows="2"><?= e((string) ($editing['why'] ?? '')) ?></textarea>
    </div>
    <div class="field">
      <label for="uq_tip">Tip</label>
      <textarea id="uq_tip" name="tip" rows="2"><?= e((string) ($editing['tip'] ?? '')) ?></textarea>
    </div>
    <div>
      <button type="submit" class="btn btn-accent"><?= $editing ? 'Guardar cambios' : 'Agregar pregunta' ?></button>
    </div>
  </form>
<?php render_modal_end(); ?>

<div class="faq-list">
  <?php foreach ($mine as $row): ?>
    <article class="panel faq-item" id="cq-<?= e((string) (int) $row['id']) ?>" style="padding:0.95rem 1.1rem">
      <div class="panel-head" style="margin-bottom:0.45rem;padding-bottom:0.45rem">
        <strong><?= e((string) $row['question']) ?></strong>
        <div class="chip-row">
          <a class="btn btn-sm" href="<?= e(url('/index.php?tab=preguntas&edit=' . (int) $row['id'])) ?>">Editar</a>
          <form method="post" action="<?= e(url('/actions/delete_user_question.php')) ?>" onsubmit="return confirm('¿Quitar esta pregunta?');">
            <input type="hidden" name="id" value="<?= e((string) (int) $row['id']) ?>">
            <button type="submit" class="btn btn-sm btn-danger">Quitar</button>
          </form>
        </div>
      </div>
      <?php if (!empty($row['why'])): ?>
        <p style="margin:0 0 0.35rem"><strong>Por qué:</strong> <?= e((string) $row['why']) ?></p>
      <?php endif; ?>
      <?php if (!empty($row['tip'])): ?>
        <p class="muted" style="margin:0 0 0.55rem"><strong>Tip:</strong> <?= e((string) $row['tip']) ?></p>
      <?php endif; ?>
      <button type="button" class="btn btn-sm btn-copy" data-copy-text="<?= e((string) $row['question']) ?>">Copiar</button>
    </article>
  <?php endforeach; ?>
</div>
