<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('ats');
}

$lang = strtolower(trim((string) ($_POST['lang'] ?? '')));
if (!in_array($lang, ['es', 'en'], true)) {
    flash('error', 'Idioma inválido.');
    redirect_tab('ats');
}

$text = (string) ($_POST['text'] ?? '');

try {
    update_ats_lake($lang, $text);
    $count = count(parse_ats_keywords($text));
    flash('success', 'Lake ATS ' . strtoupper($lang) . ' guardado (' . $count . ' palabras).');
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar: ' . $e->getMessage());
}

redirect_tab('ats', [], 'ats-' . $lang);
