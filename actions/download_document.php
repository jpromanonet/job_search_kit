<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/auth.php';

require_login();

$fileId = (int) ($_GET['id'] ?? 0);
$groupId = (int) ($_GET['group_id'] ?? 0);
if ($fileId < 1 && $groupId < 1) {
    flash('error', 'Archivo inválido.');
    redirect_tab('documentos');
}

$file = find_downloadable_document($fileId, $groupId);
if (!$file) {
    flash('error', 'Archivo no encontrado.');
    redirect_tab('documentos');
}

$path = resolve_document_path($file);
if ($path === null) {
    flash('error', 'El PDF no está en disco. Volvé a subirlo con Subir.');
    redirect_tab('documentos', [], 'doc-' . (int) ($file['group_id'] ?? $groupId));
}

$downloadName = document_download_name($file);
$ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'documento.pdf';
$mime = (string) ($file['mime_type'] ?? '');
if ($mime === '' || $mime === 'application/octet-stream') {
    $mime = 'application/pdf';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
