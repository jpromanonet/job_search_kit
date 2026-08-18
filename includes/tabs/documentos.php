<?php

declare(strict_types=1);

$formats = ['docx', 'pdf', 'txt'];
$groups = [];
$filesByGroup = [];

ensure_cv_master_document_groups();

try {
    if (db_available()) {
        $groups = db()->query('SELECT * FROM document_groups ORDER BY sort_order ASC, name ASC')->fetchAll();
        $files = db()->query('SELECT * FROM document_files ORDER BY group_id, format, uploaded_at DESC')->fetchAll();
        foreach ($files as $file) {
            $gid = (int) $file['group_id'];
            $fmt = $file['format'];
            if (!isset($filesByGroup[$gid][$fmt])) {
                $filesByGroup[$gid][$fmt] = $file;
            }
        }
    }
} catch (Throwable $e) {
    $groups = [];
}

if (!$groups) {
    $groups = load_document_groups();
    foreach (load_document_files() as $file) {
        $gid = (int) $file['group_id'];
        $fmt = $file['format'];
        if (!isset($filesByGroup[$gid][$fmt])) {
            $filesByGroup[$gid][$fmt] = $file;
        }
    }
}

$fileSections = [
    [
        'title' => 'CVs maestros',
        'subtitle' => 'Español e inglés · DOCX / PDF / TXT',
        'match' => static fn (array $g): bool => str_starts_with((string) ($g['slug'] ?? ''), 'cv-master')
            || ($g['category'] ?? '') === 'cv',
    ],
    [
        'title' => 'Cartas de presentación',
        'subtitle' => 'Español e inglés · archivos + texto editable',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'cover_letter',
    ],
];

$textSections = [
    [
        'title' => 'Mensajes a reclutadores',
        'subtitle' => 'Texto editable · copiar y pegar',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'message' && str_contains((string) $g['slug'], 'recruiter'),
    ],
    [
        'title' => 'Mensajes a CTO / CEO',
        'subtitle' => 'Texto editable · copiar y pegar',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'message' && str_contains((string) $g['slug'], 'cto'),
    ],
    [
        'title' => 'Hiring manager / referidos',
        'subtitle' => 'Texto editable · copiar y pegar',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'message' && (str_contains((string) $g['slug'], 'hm') || str_contains((string) $g['slug'], 'referral')),
    ],
    [
        'title' => 'Follow-ups',
        'subtitle' => 'Texto editable · copiar y pegar',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'message' && str_contains((string) $g['slug'], 'followup'),
    ],
    [
        'title' => 'Agradecimientos y script salarial',
        'subtitle' => 'Texto editable · copiar y pegar',
        'match' => static fn (array $g): bool => ($g['category'] ?? '') === 'message' && (str_contains((string) $g['slug'], 'thank') || str_contains((string) $g['slug'], 'salary')),
    ],
];

$langLabel = static function (array $group): string {
    return match ($group['language'] ?? '') {
        'es' => 'ES',
        'en' => 'EN',
        'both' => 'ES/EN',
        default => '—',
    };
};
?>
<div class="page-head">
  <div>
    <h1>Documentos</h1>
    <p class="subtitle">CVs y cartas con archivos. El resto: texto editable para copiar y pegar.</p>
  </div>
</div>

<?php if (!$groups): ?>
  <div class="panel warn-panel">
    Todavía no hay grupos. Corré <code>python tools/seed_document_groups.py</code> o <a href="<?= e(url('/install.php')) ?>">install.php</a>.
  </div>
<?php endif; ?>

<?php foreach ($fileSections as $section):
    $rows = array_values(array_filter($groups, $section['match']));
    if (!$rows) {
        continue;
    }
?>
  <section class="panel" id="<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($section['title']))) ?>">
    <div class="panel-head">
      <div>
        <h2><?= e($section['title']) ?></h2>
        <div class="muted" style="margin-top:.25rem"><?= e($section['subtitle']) ?></div>
      </div>
      <span class="count"><?= e((string) count($rows)) ?> documento(s)</span>
    </div>

    <div style="overflow:auto">
      <table class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Idioma</th>
            <th>DOCX</th>
            <th>PDF</th>
            <th>TXT</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $group):
              $gid = (int) $group['id'];
              $fileMap = $filesByGroup[$gid] ?? [];
              $isCover = ($group['category'] ?? '') === 'cover_letter';
          ?>
            <tr id="doc-<?= e((string) $gid) ?>">
              <td class="name"><?= e($group['name']) ?></td>
              <td><span class="badge badge-muted"><?= e($langLabel($group)) ?></span></td>
              <?php foreach ($formats as $fmt):
                  $has = isset($fileMap[$fmt]);
              ?>
                <td>
                  <?php if ($has): ?>
                    <a class="btn btn-sm" href="<?= e(url('/actions/download_document.php?id=' . $fileMap[$fmt]['id'])) ?>">
                      <?= strtoupper($fmt) ?>
                    </a>
                  <?php else: ?>
                    <span class="btn btn-sm format-missing" disabled><?= strtoupper($fmt) ?></span>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td>
                <div class="actions">
                  <details>
                    <summary class="link-edit">Subir / editar</summary>
                    <div class="box" style="margin-top:.6rem;min-width:280px">
                      <form method="post" action="<?= e(url('/actions/upload_document.php')) ?>" enctype="multipart/form-data" class="form-grid">
                        <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
                        <div class="field span-4">
                          <label>Formato</label>
                          <select name="format">
                            <?php foreach ($formats as $fmt): ?>
                              <option value="<?= e($fmt) ?>"><?= strtoupper($fmt) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="field span-4">
                          <label>Versión</label>
                          <input type="text" name="version" value="1.0">
                        </div>
                        <div class="field span-12">
                          <label>Archivo</label>
                          <input type="file" name="file" accept=".docx,.pdf,.txt" required>
                        </div>
                        <div class="field span-12" style="flex-direction:row;gap:.5rem">
                          <label class="muted"><input type="checkbox" name="approved" value="1" checked> Aprobado</label>
                          <button type="submit" class="btn btn-sm btn-accent">Subir</button>
                        </div>
                      </form>

                      <?php if ($isCover): ?>
                        <form method="post" action="<?= e(url('/actions/save_document_text.php')) ?>" style="margin-top:.75rem">
                          <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
                          <div class="field">
                            <label>Texto / template</label>
                            <textarea name="body_text" id="body-<?= e((string) $gid) ?>" rows="5"><?= e($group['body_text'] ?? '') ?></textarea>
                          </div>
                          <div style="display:flex;gap:.4rem;margin-top:.5rem;flex-wrap:wrap">
                            <button type="submit" class="btn btn-sm btn-accent">Guardar texto</button>
                            <button type="button" class="btn btn-sm btn-copy" data-copy-target="body-<?= e((string) $gid) ?>">Copiar</button>
                          </div>
                        </form>
                      <?php endif; ?>

                      <?php foreach ($formats as $fmt):
                          if (!isset($fileMap[$fmt])) {
                              continue;
                          }
                      ?>
                        <form method="post" action="<?= e(url('/actions/delete_document.php')) ?>" style="margin-top:.4rem"
                              onsubmit="return confirm('¿Borrar <?= strtoupper($fmt) ?>?');">
                          <input type="hidden" name="id" value="<?= e((string) $fileMap[$fmt]['id']) ?>">
                          <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Eliminar <?= strtoupper($fmt) ?></button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  </details>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endforeach; ?>

<?php foreach ($textSections as $section):
    $rows = array_values(array_filter($groups, $section['match']));
    if (!$rows) {
        continue;
    }
?>
  <section class="panel" id="<?= e(preg_replace('/[^a-z0-9]+/i', '-', strtolower($section['title']))) ?>">
    <div class="panel-head">
      <div>
        <h2><?= e($section['title']) ?></h2>
        <div class="muted" style="margin-top:.25rem"><?= e($section['subtitle']) ?></div>
      </div>
      <span class="count"><?= e((string) count($rows)) ?> template(s)</span>
    </div>

    <div class="doc-text-grid">
      <?php foreach ($rows as $group):
          $gid = (int) $group['id'];
          $bodyId = 'body-' . $gid;
      ?>
        <article class="doc-text-card" id="doc-<?= e((string) $gid) ?>">
          <div class="doc-text-card__head">
            <strong><?= e($group['name']) ?></strong>
            <span class="badge badge-muted"><?= e($langLabel($group)) ?></span>
          </div>
          <form method="post" action="<?= e(url('/actions/save_document_text.php')) ?>">
            <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
            <label class="sr-only" for="<?= e($bodyId) ?>">Texto</label>
            <textarea
              class="doc-text-card__body"
              name="body_text"
              id="<?= e($bodyId) ?>"
              rows="8"
              placeholder="Escribí o pegá el mensaje acá…"><?= e($group['body_text'] ?? '') ?></textarea>
            <div class="doc-text-card__actions">
              <button type="submit" class="btn btn-sm btn-accent">Guardar</button>
              <button type="button" class="btn btn-sm btn-copy" data-copy-target="<?= e($bodyId) ?>">Copiar</button>
            </div>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
