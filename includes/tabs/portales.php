<?php

declare(strict_types=1);

$portals = load_json_data('portals.json');

$sectionTitles = [
    'Argentina — job boards and search' => 'Argentina · Bolsa de trabajo',
    'Argentina — recruiters and communities' => 'Argentina · Recruiters y comunidades',
    'International — remote boards' => 'Internacional · Remote boards',
    'International — talent networks and firms' => 'Internacional · Talent networks',
    'Freelance and contract channels' => 'Freelance / contrato',
];
?>
<div class="page-head">
  <div>
    <h1>Portales</h1>
    <p class="subtitle">Todos los canales del playbook para buscar y postularte (con link directo).</p>
  </div>
</div>

<?php if (!$portals): ?>
  <div class="panel warn-panel">No hay portales en <code>data/portals.json</code>.</div>
<?php endif; ?>

<?php foreach ($portals as $section):
    $title = $sectionTitles[$section['section']] ?? $section['section'];
    $items = $section['portals'] ?? [];
?>
  <section class="panel">
    <div class="panel-head">
      <h2><?= e($title) ?></h2>
      <span class="count"><?= e((string) count($items)) ?> portal(es)</span>
    </div>
    <div class="portal-grid">
      <?php foreach ($items as $p): ?>
        <a class="portal-card" href="<?= e($p['url']) ?>" target="_blank" rel="noopener">
          <strong><?= e($p['name']) ?></strong>
          <span class="url"><?= e(preg_replace('#^https?://#', '', $p['url'])) ?></span>
          <?php if (!empty($p['use'])): ?>
            <span class="use"><?= e($p['use']) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
