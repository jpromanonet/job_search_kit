<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$mine = load_user_portals($userId);
$editId = (int) ($_GET['edit'] ?? 0);

$grouped = [];
foreach ($mine as $p) {
    $section = trim((string) ($p['section'] ?? ''));
    if ($section === '') {
        $section = 'Mis portales';
    }
    $grouped[$section][] = $p;
}
?>
<?php
$editing = null;
if ($editId > 0) {
    foreach ($mine as $p) {
        if ((int) $p['id'] === $editId) {
            $editing = $p;
            break;
        }
    }
}
?>
<div class="page-head">
  <div>
    <h1>Portales</h1>
    <p class="subtitle">Toda la lista es tuya: editá, agregá o quitá. El catálogo se copió a tu usuario para que no dependas del original.</p>
  </div>
  <?php if ($editing): ?>
    <a class="btn btn-accent" href="<?= e(url('/index.php?tab=portales')) ?>">+ Portal</a>
  <?php else: ?>
    <button type="button" class="btn btn-accent" data-modal-open="portal-form">+ Portal</button>
  <?php endif; ?>
</div>

<?php render_modal_start('portal-form', $editing ? 'Editar portal' : 'Agregar portal', $editing !== null); ?>
  <form method="post" action="<?= e(url('/actions/save_user_portal.php')) ?>" class="portal-form">
    <input type="hidden" name="id" value="<?= e((string) (int) ($editing['id'] ?? 0)) ?>">
    <div class="field">
      <label for="portal_name">Nombre</label>
      <input id="portal_name" name="name" required maxlength="255" value="<?= e((string) ($editing['name'] ?? '')) ?>" placeholder="Bumeran">
    </div>
    <div class="field">
      <label for="portal_url">URL</label>
      <input id="portal_url" type="url" name="url" required maxlength="768" value="<?= e((string) ($editing['url'] ?? '')) ?>" placeholder="https://…">
    </div>
    <div class="field">
      <label for="portal_notes">Nota</label>
      <input id="portal_notes" name="notes" maxlength="512" value="<?= e((string) ($editing['notes'] ?? '')) ?>" placeholder="Cómo lo usás">
    </div>
    <div class="field">
      <label for="portal_section">Grupo</label>
      <input id="portal_section" name="section" maxlength="128" value="<?= e((string) ($editing['section'] ?? '')) ?>" placeholder="Argentina · Bolsa de trabajo">
    </div>
    <div class="field portal-form__go">
      <button type="submit" class="btn btn-accent"><?= $editing ? 'Guardar cambios' : 'Agregar portal' ?></button>
    </div>
  </form>
<?php render_modal_end(); ?>

<?php foreach ($grouped as $section => $items): ?>
  <section class="panel">
    <div class="panel-head">
      <h2><?= e($section) ?></h2>
      <span class="count"><?= e((string) count($items)) ?></span>
    </div>
    <div class="portal-grid">
      <?php foreach ($items as $p): ?>
        <article class="portal-card portal-card--mine" id="portal-<?= e((string) (int) $p['id']) ?>">
          <a href="<?= e((string) $p['url']) ?>" target="_blank" rel="noopener">
            <strong><?= e((string) $p['name']) ?></strong>
            <span class="url"><?= e(preg_replace('#^https?://#', '', (string) $p['url'])) ?></span>
            <?php if (!empty($p['notes'])): ?>
              <span class="use"><?= e((string) $p['notes']) ?></span>
            <?php endif; ?>
          </a>
          <div class="chip-row">
            <a class="btn btn-sm" href="<?= e(url('/index.php?tab=portales&edit=' . (int) $p['id'])) ?>">Editar</a>
            <form method="post" action="<?= e(url('/actions/delete_user_portal.php')) ?>" onsubmit="return confirm('¿Sacar este portal de tu lista?');">
              <input type="hidden" name="id" value="<?= e((string) (int) $p['id']) ?>">
              <button type="submit" class="btn btn-sm btn-danger">Quitar</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
