<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    flash('error', 'Archivo inválido.');
    redirect_tab('documentos');
}

$file = null;
try {
    if (db_available()) {
        $stmt = db()->prepare(
            'SELECT f.*, g.name AS group_name
             FROM document_files f
             JOIN document_groups g ON g.id = f.group_id
             WHERE f.id = ?'
        );
        $stmt->execute([$id]);
        $file = $stmt->fetch() ?: null;
    }
} catch (Throwable $e) {
    $file = null;
}
if (!$file) {
    $file = find_document_file($id);
    if ($file) {
        $g = find_document_group((int) $file['group_id']);
        $file['group_name'] = $g['name'] ?? '';
    }
}
if (!$file) {
    flash('error', 'Archivo no encontrado.');
    redirect_tab('documentos');
}

$path = uploads_dir() . DIRECTORY_SEPARATOR . $file['stored_name'];
if (!is_file($path)) {
    flash('error', 'El archivo no está en disco. Volvé a subirlo.');
    redirect_tab('documentos', [], 'doc-' . $file['group_id']);
}

$mime = $file['mime_type'] ?: 'application/octet-stream';
$downloadName = $file['original_name'] ?: $file['stored_name'];

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
