<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('documentos');
}

if ($_POST === [] && empty($_FILES)) {
    flash('error', 'El archivo no llegó. Probá un PDF de hasta 12 MB.');
    redirect_tab('documentos');
}

$groupId = (int) ($_POST['group_id'] ?? $_GET['group_id'] ?? 0);
if ($groupId < 1) {
    flash('error', 'Grupo inválido.');
    redirect_tab('documentos');
}

$groupRow = find_document_group_for_user($userId, $groupId);
if (!$groupRow) {
    flash('error', 'Grupo no encontrado.');
    redirect_tab('documentos');
}

$useDb = false;
try {
    if (db_available()) {
        $check = db()->prepare('SELECT id FROM document_groups WHERE id = ? LIMIT 1');
        $check->execute([$groupId]);
        $useDb = (bool) $check->fetch();
    }
} catch (Throwable $e) {
    $useDb = false;
}

$uploadErrors = [
    UPLOAD_ERR_INI_SIZE => 'El archivo supera el límite de PHP. Usá uno de hasta 12 MB.',
    UPLOAD_ERR_FORM_SIZE => 'El archivo es demasiado grande.',
    UPLOAD_ERR_PARTIAL => 'La subida se cortó. Intentá de nuevo.',
    UPLOAD_ERR_NO_FILE => 'No elegiste un archivo.',
    UPLOAD_ERR_NO_TMP_DIR => 'Falta la carpeta temporal del servidor.',
    UPLOAD_ERR_CANT_WRITE => 'El servidor no pudo escribir el archivo.',
    UPLOAD_ERR_EXTENSION => 'Una extensión de PHP bloqueó el archivo.',
];

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    flash('error', 'No se recibió archivo.');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$file = $_FILES['file'];
$err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
if ($err !== UPLOAD_ERR_OK) {
    flash('error', $uploadErrors[$err] ?? ('Error al subir el archivo (código ' . $err . ').'));
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$maxBytes = 12 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    flash('error', 'Archivo demasiado grande (máx. 12 MB).');
    redirect_tab('documentos', [], 'doc-' . $groupId);
}

$original = (string) ($file['name'] ?? 'file');
$mime = (string) ($file['type'] ?? 'application/octet-stream');
$format = detect_format_from_upload($original, $mime);
$category = (string) ($groupRow['category'] ?? '');
$pdfOnly = in_array($category, ['cv', 'cover_letter'], true);
if ($pdfOnly) {
    if ($format !== 'pdf') {
        flash('error', 'CVs y cartas van solo en PDF.');
        redirect_tab('documentos', [], 'doc-' . $groupId);
    }
} else {
    $postedFormat = (string) ($_POST['format'] ?? '');
    if ($format === null && in_array($postedFormat, ['docx', 'pdf', 'txt', 'md'], true)) {
        $format = $postedFormat;
    }
    if (!in_array($format, ['docx', 'pdf', 'txt', 'md'], true)) {
        flash('error', 'Formato no permitido. Usá PDF.');
        redirect_tab('documentos', [], 'doc-' . $groupId);
    }
}

$version = trim((string) ($_POST['version'] ?? '1.0')) ?: '1.0';
$approved = isset($_POST['approved']) ? 1 : 0;
$dir = uploads_dir();
$safeSlug = preg_replace('/[^a-z0-9\-]+/i', '-', (string) $groupRow['slug']) ?: 'doc';
$versionSafe = preg_replace('/[^0-9A-Za-z.\-]/', '', $version) ?: '1.0';
$stored = sprintf('%d_%s_%s_v%s.%s', $userId, $safeSlug, $format, $versionSafe, $format);
$dest = $dir . DIRECTORY_SEPARATOR . $stored;

try {
    if (!is_writable($dir)) {
        throw new RuntimeException('La carpeta de documentos no es escribible.');
    }
    store_uploaded_file((string) $file['tmp_name'], $dest);

    if ($useDb) {
        $pdo = db();
        $pdo->beginTransaction();
        $existing = $pdo->prepare(
            'SELECT * FROM document_files WHERE group_id = ? AND format = ? ORDER BY uploaded_at DESC LIMIT 1'
        );
        $existing->execute([$groupId, $format]);
        $old = $existing->fetch();

        if ($old) {
            $upd = $pdo->prepare(
                'UPDATE document_files
                 SET original_name = :original_name, stored_name = :stored_name, mime_type = :mime_type,
                     file_size = :file_size, version = :version, approved = :approved, uploaded_at = NOW()
                 WHERE id = :id'
            );
            $upd->execute([
                ':original_name' => $original,
                ':stored_name' => $stored,
                ':mime_type' => $mime,
                ':file_size' => (int) $file['size'],
                ':version' => $version,
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

    flash('success', strtoupper((string) $format) . ' subido en “' . $groupRow['name'] . '”.');
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
