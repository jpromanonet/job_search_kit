<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$categories = tech_categories();
$catMeta = tech_category_meta();
$groups = [];
$techsByGroup = [];
$summary = array_fill_keys(array_keys($categories), 0);
$ratings = [];
$mine = [];

try {
    if (db_available()) {
        $groups = db()->query('SELECT * FROM technology_groups ORDER BY sort_order ASC')->fetchAll();
        $rows = db()->query('SELECT * FROM technologies ORDER BY group_id ASC, sort_order ASC')->fetchAll();
        $ratings = load_tech_ratings_for_user($userId);
        $mine = load_user_technologies($userId);
        foreach ($rows as $row) {
            $tid = (int) $row['id'];
            if (isset($ratings[$tid])) {
                $row['category'] = $ratings[$tid];
            }
            $techsByGroup[(int) $row['group_id']][] = $row;
            $cat = (string) ($row['category'] ?? 'known');
            if (isset($summary[$cat])) {
                $summary[$cat]++;
            }
        }
        foreach ($mine as $row) {
            $cat = (string) ($row['category'] ?? 'known');
            if (isset($summary[$cat])) {
                $summary[$cat]++;
            }
        }
    }
} catch (Throwable $e) {
    $groups = [];
}

$canSave = $groups !== [];

$renderCatPicks = static function (string $name, string $current) use ($catMeta): void {
    $current = $current !== '' && isset($catMeta[$current]) ? $current : 'known';
    echo '<div class="cat-picks" role="radiogroup">';
    foreach ($catMeta as $key => $meta) {
        $id = preg_replace('/[^a-z0-9]+/i', '-', $name . '-' . $key);
        $checked = $current === $key ? ' checked' : '';
        echo '<label class="cat-pick cat-pick--' . e((string) $meta['tone']) . '"'
            . ' title="' . e((string) $meta['label']) . '">';
        echo '<input type="radio" name="' . e($name) . '" value="' . e($key) . '" id="' . e((string) $id) . '"' . $checked . '>';
        echo '<span>' . e((string) $meta['short']) . '</span>';
        echo '</label>';
    }
    echo '</div>';
};
?>
<div class="page-head">
  <div>
    <h1>Tecnologías</h1>
    <p class="subtitle">Marcá cada una: Sé, Antes, Nueva, Aprendo o Fuera. Guardá cuando termines.</p>
  </div>
  <button type="button" class="btn btn-accent" data-modal-open="tech-form">+ Tecnología</button>
</div>

<div class="stats stack-legend">
  <?php foreach ($catMeta as $key => $meta): ?>
    <div class="stat stat--<?= e((string) $meta['tone']) ?>">
      <div class="stat-label"><?= e((string) $meta['short']) ?></div>
      <div class="stat-value"><?= e((string) ($summary[$key] ?? 0)) ?></div>
      <div class="stat-hint"><?= e((string) $meta['label']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php render_modal_start('tech-form', 'Agregar a mi stack'); ?>
  <form method="post" action="<?= e(url('/actions/save_user_tech.php')) ?>" class="stack">
    <div class="field">
      <label for="tech_name">Tecnología</label>
      <input id="tech_name" name="name" required maxlength="128" placeholder=".NET 8, LangGraph…">
    </div>
    <div class="field">
      <label for="tech_group">Grupo</label>
      <select id="tech_group" name="group_id">
        <option value="">Mis tecnologías</option>
        <?php foreach ($groups as $group): ?>
          <option value="<?= e((string) (int) $group['id']) ?>"><?= e((string) $group['area']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <span class="field-label">Categoría</span>
      <?php $renderCatPicks('category', 'known'); ?>
    </div>
    <div>
      <button type="submit" class="btn btn-accent">Agregar</button>
    </div>
  </form>
<?php render_modal_end(); ?>

<?php if (!$canSave && !$mine): ?>
  <div class="panel warn-panel">Sin tecnologías. Corré install.php para cargar el inventario.</div>
<?php else: ?>
<form method="post" action="<?= e(url('/actions/update_tech.php')) ?>">
  <div class="toolbar stack-toolbar">
    <p class="muted" style="margin:0">Un toque cambia la categoría. Después Guardar.</p>
    <button type="submit" class="btn btn-accent">Guardar clasificaciones</button>
  </div>

  <?php if ($mine): ?>
    <section class="panel">
      <div class="panel-head">
        <h2>Mis tecnologías</h2>
        <span class="count"><?= e((string) count($mine)) ?></span>
      </div>
      <div class="stack-list">
        <?php foreach ($mine as $tech): ?>
          <div class="stack-row">
            <div class="stack-row__name">
              <strong><?= e((string) $tech['name']) ?></strong>
              <button type="submit" form="del-tech-<?= e((string) (int) $tech['id']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Quitar esta tecnología?');">Quitar</button>
            </div>
            <?php $renderCatPicks('user_category[' . (int) $tech['id'] . ']', (string) ($tech['category'] ?? 'known')); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>

  <?php foreach ($groups as $group):
      $gid = (int) $group['id'];
      $items = $techsByGroup[$gid] ?? [];
      if (!$items) {
          continue;
      }
  ?>
    <section class="panel">
      <div class="panel-head">
        <h2><?= e($group['area']) ?></h2>
        <span class="count"><?= e((string) count($items)) ?></span>
      </div>
      <div class="stack-list">
        <?php foreach ($items as $tech): ?>
          <div class="stack-row">
            <div class="stack-row__name">
              <strong><?= e((string) $tech['name']) ?></strong>
            </div>
            <?php $renderCatPicks('category[' . (int) $tech['id'] . ']', (string) ($tech['category'] ?? 'known')); ?>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-accent">Guardar clasificaciones</button>
</form>
  <?php foreach ($mine as $tech): ?>
    <form id="del-tech-<?= e((string) (int) $tech['id']) ?>" method="post" action="<?= e(url('/actions/delete_user_tech.php')) ?>">
      <input type="hidden" name="id" value="<?= e((string) (int) $tech['id']) ?>">
    </form>
  <?php endforeach; ?>
<?php endif; ?>
