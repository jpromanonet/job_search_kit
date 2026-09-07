<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('comparador');
}

$ars = (float) str_replace(',', '.', (string) ($_POST['ideal_comp_ars'] ?? 0));
$usd = (float) str_replace(',', '.', (string) ($_POST['ideal_comp_usd'] ?? 0));
$eur = (float) str_replace(',', '.', (string) ($_POST['ideal_comp_eur'] ?? 0));

if ($ars < 1 || $usd < 1 || $eur < 1) {
    flash('error', 'El techo de cada moneda tiene que ser mayor a 0.');
    redirect_tab('comparador', [], 'techo-form');
}

save_offer_targets_for_user((int) $user['id'], [
    'ideal_comp_ars' => $ars,
    'ideal_comp_usd' => $usd,
    'ideal_comp_eur' => $eur,
    'ideal_remote' => (string) ($_POST['ideal_remote'] ?? 'full_remote'),
    'ideal_schedule' => (string) ($_POST['ideal_schedule'] ?? 'flexible'),
    'ideal_require_ar' => isset($_POST['ideal_require_ar']) ? 1 : 0,
    'weight_comp' => $_POST['weight_comp'] ?? 35,
    'weight_remote' => $_POST['weight_remote'] ?? 25,
    'weight_schedule' => $_POST['weight_schedule'] ?? 15,
    'weight_quality' => $_POST['weight_quality'] ?? 15,
    'weight_risk' => $_POST['weight_risk'] ?? 15,
]);
flash('success', 'Techo actualizado. El ranking se recalcula con tus reglas.');
redirect_tab('comparador');
