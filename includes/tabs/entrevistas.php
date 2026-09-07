<?php

declare(strict_types=1);

/** @var array $user */

$userId = (int) ($user['id'] ?? 0);
$notes = load_interview_notes_for_user($userId);
$apps = load_applications_for_user($userId);

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId > 0 ? find_interview_note_for_user($userId, $editId) : null;
if (!$editing) {
    $editing = [
        'id' => 0,
        'application_id' => '',
        'interview_type' => 'other',
        'interview_date' => '',
        'interviewer_name' => '',
        'title' => '',
        'prep_notes' => '',
        'live_notes' => '',
        'debrief_went_well' => '',
        'debrief_gaps' => '',
        'debrief_follow_up' => '',
        'mood_score' => '',
        'outcome' => 'pending',
        'tags' => '',
    ];
}

$typeLabels = [
    'recruiter_screen' => 'Screen reclutador',
    'technical' => 'Técnica',
    'leadership' => 'Liderazgo',
    'final' => 'Final',
    'offer' => 'Oferta',
    'other' => 'Otra',
];

$outcomeLabels = [
    'pending' => 'Pendiente',
    'passed' => 'Avanzó',
    'rejected' => 'Rechazada',
    'ghosted' => 'Sin respuesta',
    'offer' => 'Oferta',
    'other' => 'Otro',
];
?>
<div class="page-head">
  <div>
    <h1>Notas de entrevista</h1>
    <p class="subtitle">Un pergamino de izquierda a derecha: quién, prep, en vivo y debrief.</p>
  </div>
  <?php if ($editId > 0): ?>
    <a class="btn btn-accent" href="<?= e(url('/index.php?tab=entrevistas&new=1')) ?>">Nueva nota</a>
  <?php else: ?>
    <button type="button" class="btn btn-accent" data-modal-open="nota-form">Nueva nota</button>
  <?php endif; ?>
</div>

<?php render_modal_start('nota-form', $editId > 0 ? 'Editar nota' : 'Nueva nota', $editId > 0 || isset($_GET['new']), 'wide'); ?>
  <form method="post" action="<?= e(url('/actions/save_interview_note.php')) ?>">
    <input type="hidden" name="id" value="<?= e((string) (int) ($editing['id'] ?? 0)) ?>">

    <div class="talk-meta">
      <div class="field">
        <label for="title">Título</label>
        <input id="title" name="title" required maxlength="255" value="<?= e((string) ($editing['title'] ?? '')) ?>" placeholder="Técnica ronda 1 · Acme">
      </div>
      <div class="field">
        <label for="application_id">Postulación</label>
        <select id="application_id" name="application_id">
          <option value="">— Sin vincular —</option>
          <?php foreach ($apps as $app): ?>
            <?php
              $aid = (int) $app['id'];
              $sel = (int) ($editing['application_id'] ?? 0) === $aid ? ' selected' : '';
              $label = trim(($app['company'] ?? '') . ' · ' . ($app['role_title'] ?? '')) . ' (#' . $aid . ')';
            ?>
            <option value="<?= e((string) $aid) ?>"<?= $sel ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="interview_type">Tipo</label>
        <select id="interview_type" name="interview_type">
          <?php foreach ($typeLabels as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= (($editing['interview_type'] ?? '') === $key) ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="interview_date">Fecha</label>
        <input id="interview_date" type="date" name="interview_date" value="<?= e((string) ($editing['interview_date'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="interviewer_name">Entrevistador/a</label>
        <input id="interviewer_name" name="interviewer_name" maxlength="255" value="<?= e((string) ($editing['interviewer_name'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="outcome">Resultado</label>
        <select id="outcome" name="outcome">
          <?php foreach ($outcomeLabels as $key => $label): ?>
            <option value="<?= e($key) ?>"<?= (($editing['outcome'] ?? '') === $key) ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="mood_score">Ánimo</label>
        <input id="mood_score" type="number" name="mood_score" min="1" max="5" value="<?= e((string) ($editing['mood_score'] ?? '')) ?>" placeholder="1–5">
      </div>
      <div class="field">
        <label for="tags">Tags</label>
        <input id="tags" name="tags" maxlength="255" value="<?= e((string) ($editing['tags'] ?? '')) ?>" placeholder="system design…">
      </div>
    </div>

    <div class="talk-wizard">
      <section class="talk-step">
        <h3>1 · Prep</h3>
        <p class="muted">Historias, preguntas y hechizos para entrar.</p>
        <textarea id="prep_notes" name="prep_notes" rows="8"><?= e((string) ($editing['prep_notes'] ?? '')) ?></textarea>
      </section>
      <section class="talk-step talk-step--live">
        <h3>2 · En vivo</h3>
        <p class="muted">Anotá lo que pasa en la sala.</p>
        <textarea id="live_notes" name="live_notes" rows="8"><?= e((string) ($editing['live_notes'] ?? '')) ?></textarea>
      </section>
      <section class="talk-step talk-step--debrief">
        <h3>3 · Debrief</h3>
        <p class="muted">Cierre del mago: bien, gaps, follow-up.</p>
        <label for="debrief_went_well">Qué salió bien</label>
        <textarea id="debrief_went_well" name="debrief_went_well" rows="3"><?= e((string) ($editing['debrief_went_well'] ?? '')) ?></textarea>
        <label for="debrief_gaps">Gaps</label>
        <textarea id="debrief_gaps" name="debrief_gaps" rows="3"><?= e((string) ($editing['debrief_gaps'] ?? '')) ?></textarea>
        <label for="debrief_follow_up">Follow-up</label>
        <textarea id="debrief_follow_up" name="debrief_follow_up" rows="3"><?= e((string) ($editing['debrief_follow_up'] ?? '')) ?></textarea>
      </section>
    </div>

    <div class="talk-actions">
      <button type="submit" class="btn btn-accent"><?= $editId > 0 ? 'Guardar cambios' : 'Crear nota' ?></button>
    </div>
  </form>
<?php render_modal_end(); ?>

<?php if (!$notes): ?>
  <div class="panel">
    <p class="muted" style="margin:0">Todavía no hay notas. Después de cada entrevista, llená las tres columnas.</p>
  </div>
<?php else: ?>
  <div class="talk-list">
    <?php foreach ($notes as $note): ?>
      <?php
        $nid = (int) $note['id'];
        $type = (string) ($note['interview_type'] ?? 'other');
        $outcome = (string) ($note['outcome'] ?? 'pending');
        $company = trim((string) (($note['company'] ?? '') . (isset($note['role_title']) && $note['role_title'] !== '' ? ' · ' . $note['role_title'] : '')));
      ?>
      <article class="panel talk-card" id="nota-<?= e((string) $nid) ?>">
        <div class="panel-head">
          <div>
            <h2><?= e((string) ($note['title'] ?: 'Sin título')) ?></h2>
            <p class="muted" style="margin:0.35rem 0 0">
              <?= e($typeLabels[$type] ?? $type) ?>
              · <?= e($outcomeLabels[$outcome] ?? $outcome) ?>
              <?php if (!empty($note['interview_date'])): ?>
                · <?= e((string) $note['interview_date']) ?>
              <?php endif; ?>
              <?php if ($company !== ''): ?>
                · <?= e($company) ?>
              <?php endif; ?>
            </p>
          </div>
          <div class="chip-row">
            <?php if (!empty($note['interviewer_name'])): ?>
              <span class="badge"><?= e((string) $note['interviewer_name']) ?></span>
            <?php endif; ?>
            <?php if ($note['mood_score'] !== null && $note['mood_score'] !== ''): ?>
              <span class="badge">Ánimo <?= e((string) $note['mood_score']) ?>/5</span>
            <?php endif; ?>
            <a class="btn btn-sm" href="<?= e(url('/index.php?tab=entrevistas&edit=' . $nid)) ?>">Editar</a>
            <form method="post" action="<?= e(url('/actions/delete_interview_note.php')) ?>" onsubmit="return confirm('¿Eliminar esta nota?');">
              <input type="hidden" name="id" value="<?= e((string) $nid) ?>">
              <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
            </form>
          </div>
        </div>

        <div class="talk-wizard talk-wizard--read">
          <section class="talk-step">
            <h3>Prep</h3>
            <p><?= !empty($note['prep_notes']) ? nl2br(e((string) $note['prep_notes'])) : '<span class="muted">—</span>' ?></p>
          </section>
          <section class="talk-step talk-step--live">
            <h3>En vivo</h3>
            <p><?= !empty($note['live_notes']) ? nl2br(e((string) $note['live_notes'])) : '<span class="muted">—</span>' ?></p>
          </section>
          <section class="talk-step talk-step--debrief">
            <h3>Debrief</h3>
            <?php if (!empty($note['debrief_went_well'])): ?>
              <p><strong>Bien:</strong> <?= nl2br(e((string) $note['debrief_went_well'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($note['debrief_gaps'])): ?>
              <p><strong>Gaps:</strong> <?= nl2br(e((string) $note['debrief_gaps'])) ?></p>
            <?php endif; ?>
            <?php if (!empty($note['debrief_follow_up'])): ?>
              <p><strong>Follow-up:</strong> <?= nl2br(e((string) $note['debrief_follow_up'])) ?></p>
            <?php endif; ?>
            <?php if (empty($note['debrief_went_well']) && empty($note['debrief_gaps']) && empty($note['debrief_follow_up'])): ?>
              <p class="muted">—</p>
            <?php endif; ?>
          </section>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
