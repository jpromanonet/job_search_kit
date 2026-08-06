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

$body = (string) ($_POST['body_text'] ?? '');
$description = null_if_blank($_POST['description'] ?? null);

try {
    if (db_available()) {
        $stmt = db()->prepare(
            'UPDATE document_groups SET body_text = :body_text, description = :description WHERE id = :id'
        );
        $stmt->execute([
            ':body_text' => $body === '' ? null : $body,
            ':description' => $description,
            ':id' => $groupId,
        ]);
    } else {
        if (!update_document_group_text($groupId, $body, $description)) {
            flash('error', 'Grupo no encontrado.');
            redirect_tab('documentos');
        }
    }
} catch (Throwable $e) {
    if (!update_document_group_text($groupId, $body, $description)) {
        flash('error', 'No se pudo guardar el texto.');
        redirect_tab('documentos');
    }
}

flash('success', 'Texto del documento guardado.');
redirect_tab('documentos', [], 'doc-' . $groupId);
