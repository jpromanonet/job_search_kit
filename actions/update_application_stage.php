<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/data.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('tracker');
}

$id = (int) ($_POST['id'] ?? 0);
$stage = (string) ($_POST['stage'] ?? '');
$wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
    || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';

function stage_update_respond(bool $ok, string $message, array $extra = [], bool $json = false): never
{
    if ($json) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
        exit;
    }
    flash($ok ? 'success' : 'error', $message);
    $view = (string) ($_POST['view'] ?? '');
    redirect_tab('tracker', $view === 'kanban' ? ['view' => 'kanban'] : []);
}

if ($id < 1 || !array_key_exists($stage, stage_labels())) {
    stage_update_respond(false, 'Postulación o estado inválido.', [], $wantsJson);
}

$saved = update_application_stage_for_user($userId, $id, $stage);
if (!$saved) {
    stage_update_respond(false, 'Postulación no encontrada.', [], $wantsJson);
}

stage_update_respond(
    true,
    'Estado actualizado.',
    [
        'id' => (int) $saved['id'],
        'stage' => $saved['stage'],
        'label' => stage_label((string) $saved['stage']),
    ],
    $wantsJson
);
