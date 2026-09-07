<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';
require __DIR__ . '/../includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('documentos');
}

$id = (int) ($_POST['id'] ?? 0);
$groupId = (int) ($_POST['group_id'] ?? 0);
if ($id < 1) {
    flash('error', 'Archivo inválido.');
    redirect_tab('documentos');
}

$file = null;
$useDb = false;
try {
    if (db_available()) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT * FROM document_files WHERE id = ?');
        $stmt->execute([$id]);
        $file = $stmt->fetch() ?: null;
        $useDb = (bool) $file;
        if ($file) {
            $path = uploads_dir() . DIRECTORY_SEPARATOR . $file['stored_name'];
            $pdo->prepare('DELETE FROM document_files WHERE id = ?')->execute([$id]);
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }
} catch (Throwable $e) {
    $useDb = false;
    $file = null;
}

if (!$useDb) {
    $removed = delete_document_file_by_id($id);
    if (!$removed) {
        flash('error', 'Archivo no encontrado.');
        redirect_tab('documentos');
    }
    $path = uploads_dir() . DIRECTORY_SEPARATOR . $removed['stored_name'];
    if (is_file($path)) {
        @unlink($path);
    }
    $groupId = $groupId ?: (int) $removed['group_id'];
} else {
    $groupId = $groupId ?: (int) $file['group_id'];
}

flash('success', 'Archivo eliminado.');
redirect_tab('documentos', [], 'doc-' . $groupId);
