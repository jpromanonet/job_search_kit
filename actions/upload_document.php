<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('documentos');
}

$groupId = (int) ($_POST['group_id'] ?? 0);
if ($groupId < 1) {
    flash('error', 'Grupo inválido.');
    redirect_tab('documentos');
}

$useDb = false;
$groupRow = null;
try {
    if (db_available()) {
        $group = db()->prepare('SELECT * FROM document_groups WHERE id = ?');
        $group->execute([$groupId]);
        $groupRow = $group->fetch() ?: null;
        $useDb = (bool) $groupRow;
    }
} catch (Throwable $e) {
    $useDb = false;
}
if (!$groupRow) {
    $groupRow = find_document_group($groupId);
}
if (!$groupRow) {
    flash('error', 'Grupo no encontrado.');
    redirect_tab('documentos');
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    flash('error', 'No se recibió archivo.');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash('error', 'Error al subir el archivo (código ' . (int) $file['error'] . ').');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$maxBytes = 12 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    flash('error', 'Archivo demasiado grande (máx. 12 MB).');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$original = (string) ($file['name'] ?? 'file');
$mime = (string) ($file['type'] ?? 'application/octet-stream');
$format = $_POST['format'] ?? detect_format_from_upload($original, $mime);
if (!in_array($format, ['docx', 'pdf', 'txt', 'md'], true)) {
    flash('error', 'Formato no permitido. Usá DOCX, PDF o TXT.');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$version = trim((string) ($_POST['version'] ?? '1.0')) ?: '1.0';
$approved = isset($_POST['approved']) ? 1 : 0;
$dir = uploads_dir();
$safeSlug = preg_replace('/[^a-z0-9\-]+/i', '-', (string) $groupRow['slug']) ?: 'doc';
$versionSafe = preg_replace('/[^0-9A-Za-z.\-]/', '', $version) ?: '1.0';
$stored = sprintf('%s_%s_v%s.%s', $safeSlug, $format, $versionSafe, $format);
$dest = $dir . DIRECTORY_SEPARATOR . $stored;

try {
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        throw new RuntimeException('No se pudo guardar el archivo en disco.');
    }

    if ($useDb) {
        $pdo = db();
        $pdo->beginTransaction();
        $existing = $pdo->prepare(
            'SELECT * FROM document_files WHERE group_id = ? AND format = ? AND version = ? LIMIT 1'
        );
        $existing->execute([$groupId, $format, $version]);
        $old = $existing->fetch();

        if ($old) {
            $upd = $pdo->prepare(
                'UPDATE document_files
                 SET original_name = :original_name, stored_name = :stored_name, mime_type = :mime_type,
                     file_size = :file_size, approved = :approved, uploaded_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                ':original_name' => $original,
                ':stored_name' => $stored,
                ':mime_type' => $mime,
                ':file_size' => (int) $file['size'],
                ':approved' => $approved,
                ':id' => $old['id'],
            ]);
            if ($old['stored_name'] !== $stored) {
                $oldPath = $dir . DIRECTORY_SEPARATOR . $old['stored_name'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO document_files
                 (group_id, format, original_name, stored_name, mime_type, file_size, version, approved)
                 VALUES (:group_id, :format, :original_name, :stored_name, :mime_type, :file_size, :version, :approved)'
            );
            $ins->execute([
                ':group_id' => $groupId,
                ':format' => $format,
                ':original_name' => $original,
                ':stored_name' => $stored,
                ':mime_type' => $mime,
                ':file_size' => (int) $file['size'],
                ':version' => $version,
                ':approved' => $approved,
            ]);
        }
        $pdo->commit();
    } else {
        $oldFiles = load_document_files();
        foreach ($oldFiles as $old) {
            if (
                (int) ($old['group_id'] ?? 0) === $groupId
                && ($old['format'] ?? '') === $format
                && ($old['version'] ?? '') === $version
                && ($old['stored_name'] ?? '') !== $stored
            ) {
                $oldPath = $dir . DIRECTORY_SEPARATOR . $old['stored_name'];
                if (is_file($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
        upsert_document_file([
            'group_id' => $groupId,
            'format' => $format,
            'original_name' => $original,
            'stored_name' => $stored,
            'mime_type' => $mime,
            'file_size' => (int) $file['size'],
            'version' => $version,
            'approved' => $approved,
        ]);
    }

    flash('success', strtoupper($format) . ' subido en “' . $groupRow['name'] . '”.');
} catch (Throwable $e) {
    if ($useDb) {
        try {
            $pdo = db();
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $ignored) {
        }
    }
    if (is_file($dest)) {
        @unlink($dest);
    }
    flash('error', 'Upload falló: ' . $e->getMessage());
}

redirect_tab('documentos', [], 'doc-' . $groupId);
