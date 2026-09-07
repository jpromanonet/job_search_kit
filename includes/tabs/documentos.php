<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);

$formats = ['pdf'];
$groups = [];
$filesByGroup = [];

try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM document_groups WHERE user_id = ? ORDER BY sort_order ASC, name ASC');
    $stmt->execute([$userId]);
    $groups = $stmt->fetchAll();
    if (!$groups) {
        try {
            $pdo->prepare(
                'UPDATE document_groups SET user_id = ? WHERE user_id IS NULL OR user_id = 0'
            )->execute([$userId]);
        } catch (Throwable $e) {
            // user_id column may not exist yet
        }
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll();
    }
    if (!$groups) {
        seed_user_document_groups($pdo, $userId);
        $stmt->execute([$userId]);
        $groups = $stmt->fetchAll();
    }
    if ($groups) {
        $ids = array_map(static fn ($g) => (int) $g['id'], $groups);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $filesStmt = $pdo->prepare(
            "SELECT * FROM document_files WHERE group_id IN ($in) ORDER BY group_id, format, uploaded_at DESC"
        );
        $filesStmt->execute($ids);
        foreach ($filesStmt->fetchAll() as $file) {
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

$fileSections = [
    [
        'title' => 'CVs maestros',
        'subtitle' => 'Español e inglés · solo PDF',
        'match' => static fn (array $g): bool => str_starts_with((string) ($g['slug'] ?? ''), 'cv-master')
            || ($g['category'] ?? '') === 'cv',
    ],
    [
        'title' => 'Cartas de presentación',
        'subtitle' => 'Español e inglés · solo PDF',
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
    <p class="subtitle">CVs y cartas: un PDF cada uno. Los mensajes siguen siendo texto para copiar y pegar.</p>
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

    <div class="doc-file-list">
      <?php foreach ($rows as $group):
          $gid = (int) $group['id'];
          $fileMap = $filesByGroup[$gid] ?? [];
          $pdf = $fileMap['pdf'] ?? null;
          $pdfOnDisk = false;
          $fileId = 0;
          if ($pdf) {
              $fileId = (int) ($pdf['id'] ?? 0);
              $probe = $pdf;
              $probe['slug'] = (string) ($group['slug'] ?? '');
              $probe['group_name'] = (string) ($group['name'] ?? '');
              $pdfOnDisk = resolve_document_path($probe) !== null;
          }
      ?>
        <article class="doc-file-row" id="doc-<?= e((string) $gid) ?>">
          <div class="doc-file-row__info">
            <?php if ($pdfOnDisk && $fileId > 0): ?>
              <a class="doc-file-name" href="<?= e(url('/actions/download_document.php?id=' . $fileId . '&group_id=' . $gid)) ?>"><?= e((string) $group['name']) ?></a>
            <?php else: ?>
              <strong class="doc-file-name"><?= e((string) $group['name']) ?></strong>
            <?php endif; ?>
            <span class="badge badge-muted"><?= e($langLabel($group)) ?></span>
          </div>
          <div class="doc-file-row__actions">
            <form method="post" action="<?= e(url('/actions/upload_document.php?group_id=' . $gid)) ?>" enctype="multipart/form-data" class="doc-upload">
              <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
              <input type="hidden" name="format" value="pdf">
              <input type="hidden" name="version" value="1.0">
              <input type="hidden" name="approved" value="1">
              <label class="btn btn-sm doc-fmt">
                Subir
                <input type="file" name="file" accept=".pdf,application/pdf" required class="sr-only" onchange="this.form.submit()">
              </label>
            </form>
            <?php if ($pdf): ?>
              <form method="post" action="<?= e(url('/actions/delete_document.php')) ?>" class="doc-upload"
                    onsubmit="return confirm('¿Borrar este PDF?');">
                <input type="hidden" name="id" value="<?= e((string) $pdf['id']) ?>">
                <input type="hidden" name="group_id" value="<?= e((string) $gid) ?>">
                <button type="submit" class="btn btn-sm btn-danger doc-fmt">Borrar</button>
              </form>
            <?php else: ?>
              <span class="btn btn-sm doc-fmt format-missing" aria-disabled="true">Borrar</span>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
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
