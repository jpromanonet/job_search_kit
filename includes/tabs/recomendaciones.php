<?php

declare(strict_types=1);

$sections = [];
try {
    if (db_available()) {
        $sections = db()->query('SELECT * FROM recommendation_sections ORDER BY sort_order ASC')->fetchAll();
    }
} catch (Throwable $e) {
    $sections = [];
}
if (!$sections) {
    $sections = load_json_data('recommendations.json');
}

$byTitle = [];
foreach ($sections as $s) {
    $byTitle[$s['title'] ?? ''] = $s;
}

$groups = recommendation_groups();
$toc = [];
foreach ($groups as $groupTitle => $titles) {
    foreach ($titles as $t) {
        if (isset($byTitle[$t])) {
            $toc[$groupTitle][] = $t;
        }
    }
}
// Orphan sections not in groups
$known = [];
foreach ($groups as $titles) {
    foreach ($titles as $t) {
        $known[$t] = true;
    }
}
$orphans = [];
$hidden = array_fill_keys(recommendation_hidden_titles(), true);
foreach ($byTitle as $t => $s) {
    if ($t !== '' && !isset($known[$t]) && !isset($hidden[$t])) {
        $orphans[] = $t;
    }
}
?>
<div class="page-head">
  <div>
    <h1>Recomendaciones</h1>
    <p class="subtitle">Reglas y tablas del playbook, ordenadas. Los portales también están en la pestaña Portales.</p>
  </div>
</div>

<?php if (!$sections): ?>
  <div class="panel warn-panel">No hay recomendaciones cargadas.</div>
<?php else: ?>

<nav class="rec-toc panel">
  <div class="panel-head"><h2>Índice</h2></div>
  <div class="rec-toc-grid">
    <?php foreach ($toc as $groupTitle => $titles): ?>
      <div class="rec-toc-col">
        <h3><?= e($groupTitle) ?></h3>
        <ul>
          <?php foreach ($titles as $t): ?>
            <li><a href="#rec-<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($t))) ?>"><?= e(recommendation_title_es($t)) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</nav>

<?php foreach ($toc as $groupTitle => $titles): ?>
  <section class="rec-group">
    <h2 class="rec-group-title"><?= e($groupTitle) ?></h2>
    <?php foreach ($titles as $t):
        $section = $byTitle[$t];
        $anchor = preg_replace('/[^a-z0-9]+/i', '-', strtolower($t));
    ?>
      <article class="panel rec-panel" id="rec-<?= e($anchor) ?>">
        <div class="panel-head">
          <h3><?= e(recommendation_title_es($t)) ?></h3>
        </div>
        <?= render_recommendation_body($t, (string) ($section['body'] ?? '')) ?>
      </article>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<?php if ($orphans): ?>
  <section class="rec-group">
    <h2 class="rec-group-title">Otros</h2>
    <?php foreach ($orphans as $t):
        $section = $byTitle[$t];
        $anchor = preg_replace('/[^a-z0-9]+/i', '-', strtolower($t));
    ?>
      <article class="panel rec-panel" id="rec-<?= e($anchor) ?>">
        <div class="panel-head"><h3><?= e(recommendation_title_es($t)) ?></h3></div>
        <?= render_recommendation_body($t, (string) ($section['body'] ?? '')) ?>
      </article>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php endif; ?>
