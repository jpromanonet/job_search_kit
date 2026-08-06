<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tecnologias');
}

$categories = array_keys(tech_categories());
$posted = $_POST['category'] ?? [];
if (!is_array($posted)) {
    flash('error', 'Payload inválido.');
    redirect_tab('tecnologias');
}

$pdo = db();
$stmt = $pdo->prepare('UPDATE technologies SET category = :category WHERE id = :id');
$updated = 0;

foreach ($posted as $id => $category) {
    $id = (int) $id;
    if ($id < 1 || !in_array($category, $categories, true)) {
        continue;
    }
    $stmt->execute([':category' => $category, ':id' => $id]);
    $updated += $stmt->rowCount() > 0 ? 1 : 0;
}

flash('success', "Tecnologías actualizadas ($updated cambios).");
redirect_tab('tecnologias');
