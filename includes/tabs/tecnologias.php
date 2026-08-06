<?php

declare(strict_types=1);

$categories = tech_categories();
$groups = [];
$techsByGroup = [];
$summary = array_fill_keys(array_keys($categories), 0);

try {
    if (db_available()) {
        $groups = db()->query('SELECT * FROM technology_groups ORDER BY sort_order ASC')->fetchAll();
        $rows = db()->query('SELECT * FROM technologies ORDER BY group_id ASC, sort_order ASC')->fetchAll();
        foreach ($rows as $row) {
            $techsByGroup[(int) $row['group_id']][] = $row;
            if (isset($summary[$row['category']])) {
                $summary[$row['category']]++;
            }
        }
    }
} catch (Throwable $e) {
    $groups = [];
}

if (!$groups) {
    $json = load_json_data('technologies.json');
    foreach ($json as $gi => $g) {
        $gid = $gi + 1;
        $groups[] = ['id' => $gid, 'area' => $g['area']];
        foreach ($g['technologies'] as $ti => $name) {
            $techsByGroup[$gid][] = [
                'id' => ($gid * 1000) + $ti,
                'name' => $name,
                'category' => 'known',
            ];
            $summary['known']++;
        }
    }
}

$canSave = db_available() && !empty($groups) && isset($groups[0]['id']) && db_available();
// Detect JSON-only: ids are synthetic if not in DB
try {
    $canSave = db_available() && (int) db()->query('SELECT COUNT(*) FROM technologies')->fetchColumn() > 0;
} catch (Throwable $e) {
    $canSave = false;
}
?>
<div class="page-head">
  <div>
    <h1>Tecnologías</h1>
    <p class="subtitle">Inventario del documento. Clasificá: sé / vieja / nueva / learning / exclude.</p>
  </div>
</div>

<div class="stats">
  <?php foreach ($categories as $key => $label): ?>
    <div class="stat">
      <div class="stat-label"><?= e($label) ?></div>
      <div class="stat-value"><?= e((string) ($summary[$key] ?? 0)) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$groups): ?>
  <div class="panel warn-panel">Sin tecnologías.</div>
<?php elseif ($canSave): ?>
<form method="post" action="<?= e(url('/actions/update_tech.php')) ?>">
  <div class="toolbar">
    <button type="submit" class="btn btn-accent">Guardar clasificaciones</button>
  </div>
  <?php foreach ($groups as $group):
      $gid = (int) $group['id'];
      $items = $techsByGroup[$gid] ?? [];
  ?>
    <section class="panel">
      <div class="panel-head">
        <h2><?= e($group['area']) ?></h2>
        <span class="count"><?= e((string) count($items)) ?></span>
      </div>
      <table class="data-table">
        <thead><tr><th>Tecnología</th><th>Categoría</th></tr></thead>
        <tbody>
          <?php foreach ($items as $tech): ?>
            <tr>
              <td><?= e($tech['name']) ?></td>
              <td style="width:16rem">
                <select name="category[<?= e((string) $tech['id']) ?>]">
                  <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($tech['category'] ?? '') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-accent">Guardar clasificaciones</button>
</form>
<?php else: ?>
  <?php foreach ($groups as $group):
      $gid = (int) $group['id'];
      $items = $techsByGroup[$gid] ?? [];
  ?>
    <section class="panel">
      <div class="panel-head">
        <h2><?= e($group['area']) ?></h2>
        <span class="count"><?= e((string) count($items)) ?></span>
      </div>
      <div class="tech-chips">
        <?php foreach ($items as $tech): ?>
          <span class="tech-chip"><?= e($tech['name']) ?></span>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endforeach; ?>
  <div class="flash flash-info">Modo lectura (JSON). Para clasificar y guardar, corré <a href="<?= e(url('/install.php')) ?>">install.php</a>.</div>
<?php endif; ?>
