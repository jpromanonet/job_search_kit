<?php

declare(strict_types=1);

require __DIR__ . '/../includes/db.php';
require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/repositories.php';
require __DIR__ . '/../includes/auth.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_tab('entrevistas');
}

$id = (int) ($_POST['id'] ?? 0);
$title = trim((string) ($_POST['title'] ?? ''));
if ($title === '') {
    flash('error', 'El título es obligatorio.');
    redirect_tab('entrevistas', $id > 0 ? ['edit' => $id] : [], 'nota-form');
}

$types = ['recruiter_screen', 'technical', 'leadership', 'final', 'offer', 'other'];
$outcomes = ['pending', 'passed', 'rejected', 'ghosted', 'offer', 'other'];

$interviewType = (string) ($_POST['interview_type'] ?? 'other');
if (!in_array($interviewType, $types, true)) {
    $interviewType = 'other';
}

$outcome = (string) ($_POST['outcome'] ?? 'pending');
if (!in_array($outcome, $outcomes, true)) {
    $outcome = 'pending';
}

$mood = $_POST['mood_score'] ?? '';
$moodScore = $mood === '' ? '' : max(1, min(5, (int) $mood));

$appId = trim((string) ($_POST['application_id'] ?? ''));
if ($appId !== '') {
    $app = find_application_for_user((int) $user['id'], (int) $appId);
    if (!$app) {
        flash('error', 'La postulación vinculada no existe.');
        redirect_tab('entrevistas', $id > 0 ? ['edit' => $id] : [], 'nota-form');
    }
}

$note = [
    'id' => $id,
    'application_id' => $appId,
    'interview_type' => $interviewType,
    'interview_date' => null_if_blank($_POST['interview_date'] ?? null) ?? '',
    'interviewer_name' => null_if_blank($_POST['interviewer_name'] ?? null) ?? '',
    'title' => $title,
    'prep_notes' => null_if_blank($_POST['prep_notes'] ?? null) ?? '',
    'live_notes' => null_if_blank($_POST['live_notes'] ?? null) ?? '',
    'debrief_went_well' => null_if_blank($_POST['debrief_went_well'] ?? null) ?? '',
    'debrief_gaps' => null_if_blank($_POST['debrief_gaps'] ?? null) ?? '',
    'debrief_follow_up' => null_if_blank($_POST['debrief_follow_up'] ?? null) ?? '',
    'mood_score' => $moodScore,
    'outcome' => $outcome,
    'tags' => null_if_blank($_POST['tags'] ?? null) ?? '',
];

try {
    $saved = save_interview_note_for_user((int) $user['id'], $note);
    flash('success', $id > 0 ? 'Nota de entrevista actualizada.' : 'Nota de entrevista creada.');
    redirect_tab('entrevistas', [], 'nota-' . (int) ($saved['id'] ?? 0));
} catch (Throwable $e) {
    flash('error', 'No se pudo guardar la nota: ' . $e->getMessage());
    redirect_tab('entrevistas', $id > 0 ? ['edit' => $id] : [], 'nota-form');
}
