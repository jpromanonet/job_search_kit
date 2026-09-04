<?php

declare(strict_types=1);

$items = load_json_data('company_questions.json');
?>
<div class="page-head">
  <div>
    <h1>Mis preguntas</h1>
    <p class="subtitle">Ocho preguntas que funcionan en casi cualquier entrevista. Muestran interés real por el trabajo, no por el proceso.</p>
  </div>
</div>

<div class="panel" style="margin-bottom:1rem">
  <p class="muted" style="margin:0">
    Usalas hacia el final de la conversación. Elegí 3–4 según el tiempo; no leas la lista entera.
    Personalizá con un detalle del producto o del equipo cuando puedas.
  </p>
</div>

<?php if (!$items): ?>
  <div class="panel warn-panel">No hay preguntas cargadas.</div>
<?php else: ?>
  <div class="faq-list">
    <?php foreach ($items as $row):
        $id = (int) ($row['id'] ?? 0);
        $q = (string) ($row['question'] ?? '');
        $why = (string) ($row['why'] ?? '');
        $tip = (string) ($row['tip'] ?? '');
        $copy = $q;
    ?>
      <details class="panel faq-item" id="cq-<?= e((string) $id) ?>"<?= $id === 1 ? ' open' : '' ?>>
        <summary>
          <span class="faq-num">#<?= e((string) $id) ?></span>
          <span class="faq-q"><?= e($q) ?></span>
        </summary>
        <div class="faq-a">
          <?php if ($why !== ''): ?>
            <p><strong>Por qué funciona:</strong> <?= e($why) ?></p>
          <?php endif; ?>
          <?php if ($tip !== ''): ?>
            <p class="muted"><strong>Tip:</strong> <?= e($tip) ?></p>
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-copy" data-copy-text="<?= e($copy) ?>">Copiar pregunta</button>
        </div>
      </details>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
