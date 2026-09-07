<?php

declare(strict_types=1);

/**
 * MySQL repositories — source of truth (no JSON runtime for user data).
 */

function campaign_settings_for_user(int $userId): array
{
    $today = date('Y-m-d');
    $stmt = db()->prepare('SELECT * FROM campaign_settings WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        db()->prepare(
            'INSERT INTO campaign_settings
                (user_id, target_applications, apps_per_day, run_started_on, ideal_comp_ars, ideal_comp_usd, ideal_comp_eur)
             VALUES (?, 465, 5, ?, 7000000, 10000, 9000)'
        )->execute([$userId, $today]);
        $row = [
            'target_applications' => 465,
            'apps_per_day' => 5,
            'run_started_on' => $today,
            'ideal_comp_ars' => 7000000,
            'ideal_comp_usd' => 10000,
            'ideal_comp_eur' => 9000,
        ];
    }

    $start = (string) ($row['run_started_on'] ?? '');
    if ($start === '') {
        $start = earliest_application_date_for_user($userId) ?? $today;
    }

    return array_merge(offer_ceiling_defaults(), [
        'target_applications' => max(1, (int) ($row['target_applications'] ?? 465)),
        'apps_per_day' => max(1, (int) ($row['apps_per_day'] ?? 5)),
        'run_started_on' => $start,
        'ideal_comp_ars' => max(1.0, (float) ($row['ideal_comp_ars'] ?? 7000000)),
        'ideal_comp_usd' => max(1.0, (float) ($row['ideal_comp_usd'] ?? 10000)),
        'ideal_comp_eur' => max(1.0, (float) ($row['ideal_comp_eur'] ?? 9000)),
        'ideal_remote' => offer_ceiling_remote((string) ($row['ideal_remote'] ?? 'full_remote')),
        'ideal_schedule' => offer_ceiling_schedule((string) ($row['ideal_schedule'] ?? 'flexible')),
        'ideal_require_ar' => (int) ($row['ideal_require_ar'] ?? 1) === 1 ? 1 : 0,
        'weight_comp' => offer_ceiling_weight($row['weight_comp'] ?? 35, 35),
        'weight_remote' => offer_ceiling_weight($row['weight_remote'] ?? 25, 25),
        'weight_schedule' => offer_ceiling_weight($row['weight_schedule'] ?? 15, 15),
        'weight_quality' => offer_ceiling_weight($row['weight_quality'] ?? 15, 15),
        'weight_risk' => offer_ceiling_weight($row['weight_risk'] ?? 15, 15),
    ]);
}

function campaign_target_for_user(int $userId): int
{
    return (int) campaign_settings_for_user($userId)['target_applications'];
}

function save_campaign_target_for_user(int $userId, int $target): int
{
    $target = max(1, min(50000, $target));
    $stmt = db()->prepare(
        'INSERT INTO campaign_settings (user_id, target_applications)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE target_applications = VALUES(target_applications)'
    );
    $stmt->execute([$userId, $target]);
    return $target;
}

function reset_run_for_user(int $userId): string
{
    $today = date('Y-m-d');
    db()->prepare(
        'INSERT INTO campaign_settings (user_id, target_applications, apps_per_day, run_started_on)
         VALUES (?, 465, 5, ?)
         ON DUPLICATE KEY UPDATE run_started_on = VALUES(run_started_on)'
    )->execute([$userId, $today]);
    return $today;
}

function save_offer_targets_for_user(int $userId, array $fields): array
{
    $ars = max(1, (float) ($fields['ideal_comp_ars'] ?? 0));
    $usd = max(1, (float) ($fields['ideal_comp_usd'] ?? 0));
    $eur = max(1, (float) ($fields['ideal_comp_eur'] ?? 0));
    $remote = offer_ceiling_remote((string) ($fields['ideal_remote'] ?? 'full_remote'));
    $schedule = offer_ceiling_schedule((string) ($fields['ideal_schedule'] ?? 'flexible'));
    $requireAr = !empty($fields['ideal_require_ar']) ? 1 : 0;
    $wComp = offer_ceiling_weight($fields['weight_comp'] ?? 35, 35);
    $wRemote = offer_ceiling_weight($fields['weight_remote'] ?? 25, 25);
    $wSchedule = offer_ceiling_weight($fields['weight_schedule'] ?? 15, 15);
    $wQuality = offer_ceiling_weight($fields['weight_quality'] ?? 15, 15);
    $wRisk = offer_ceiling_weight($fields['weight_risk'] ?? 15, 15);

    db()->prepare(
        'INSERT INTO campaign_settings
            (user_id, target_applications, apps_per_day,
             ideal_comp_ars, ideal_comp_usd, ideal_comp_eur,
             ideal_remote, ideal_schedule, ideal_require_ar,
             weight_comp, weight_remote, weight_schedule, weight_quality, weight_risk)
         VALUES (?, 465, 5, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ideal_comp_ars = VALUES(ideal_comp_ars),
            ideal_comp_usd = VALUES(ideal_comp_usd),
            ideal_comp_eur = VALUES(ideal_comp_eur),
            ideal_remote = VALUES(ideal_remote),
            ideal_schedule = VALUES(ideal_schedule),
            ideal_require_ar = VALUES(ideal_require_ar),
            weight_comp = VALUES(weight_comp),
            weight_remote = VALUES(weight_remote),
            weight_schedule = VALUES(weight_schedule),
            weight_quality = VALUES(weight_quality),
            weight_risk = VALUES(weight_risk)'
    )->execute([
        $userId, $ars, $usd, $eur, $remote, $schedule, $requireAr,
        $wComp, $wRemote, $wSchedule, $wQuality, $wRisk,
    ]);
    return campaign_settings_for_user($userId);
}

function earliest_application_date_for_user(int $userId): ?string
{
    $stmt = db()->prepare(
        'SELECT MIN(COALESCE(application_date, DATE(created_at))) FROM applications WHERE user_id = ?'
    );
    $stmt->execute([$userId]);
    $value = $stmt->fetchColumn();
    return $value ? (string) $value : null;
}

function application_date_of(array $app): string
{
    $date = (string) ($app['application_date'] ?? '');
    if ($date === '' && !empty($app['created_at'])) {
        $date = substr((string) $app['created_at'], 0, 10);
    }
    return $date;
}

function run_day_count(string $start, string $today): int
{
    $from = strtotime($start . ' 00:00:00');
    $to = strtotime($today . ' 00:00:00');
    if ($from === false || $to === false) {
        return 1;
    }
    if ($to < $from) {
        return 1;
    }
    return (int) floor(($to - $from) / 86400) + 1;
}

function load_document_groups_for_user(int $userId, ?string $category = null): array
{
    if ($category !== null && $category !== '') {
        $stmt = db()->prepare(
            'SELECT * FROM document_groups WHERE user_id = ? AND category = ? ORDER BY sort_order ASC, name ASC'
        );
        $stmt->execute([$userId, $category]);
        return $stmt->fetchAll();
    }
    $stmt = db()->prepare(
        'SELECT * FROM document_groups WHERE user_id = ? ORDER BY sort_order ASC, name ASC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function load_plan_days_for_user(int $userId): array
{
    $days = db()->query(
        'SELECT * FROM plan_days ORDER BY day_number ASC'
    )->fetchAll();
    if (!$days) {
        return [];
    }
    $prog = db()->prepare('SELECT * FROM day_progress WHERE user_id = ?');
    $prog->execute([$userId]);
    $byDay = [];
    foreach ($prog->fetchAll() as $p) {
        $byDay[(int) $p['day_number']] = $p;
    }
    $out = [];
    foreach ($days as $d) {
        $n = (int) $d['day_number'];
        $p = $byDay[$n] ?? [];
        $row = $d;
        $row['status'] = $p['status'] ?? 'not_started';
        $row['article_url'] = $p['article_url'] ?? null;
        $row['instagram_done'] = (int) ($p['instagram_done'] ?? 0);
        $row['linkedin_posted'] = (int) ($p['linkedin_posted'] ?? 0);
        $row['x_posted'] = (int) ($p['x_posted'] ?? 0);
        $row['notes'] = $p['notes'] ?? null;
        $row['evidence'] = $p['evidence'] ?? null;
        $row['blockers'] = $p['blockers'] ?? null;
        $row['carry_forward'] = $p['carry_forward'] ?? null;
        $row['applications_logged'] = (int) ($p['applications_logged'] ?? 0);
        $row['completed_at'] = $p['completed_at'] ?? null;
        foreach (['candidate_tasks', 'ai_tasks', 'execute_today', 'source_allocations'] as $jsonKey) {
            if (isset($row[$jsonKey]) && is_string($row[$jsonKey])) {
                $decoded = json_decode($row[$jsonKey], true);
                $row[$jsonKey] = is_array($decoded) ? $decoded : [];
            }
        }
        $out[] = $row;
    }
    return $out;
}

function upsert_day_progress(int $userId, int $dayNumber, array $fields): void
{
    $allowed = [
        'status', 'article_url', 'instagram_done', 'linkedin_posted', 'x_posted',
        'notes', 'evidence', 'blockers', 'carry_forward', 'applications_logged', 'completed_at',
    ];
    $data = ['user_id' => $userId, 'day_number' => $dayNumber];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $fields)) {
            $data[$k] = $fields[$k];
        }
    }
    $cols = array_keys($data);
    $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
    $updates = [];
    foreach ($cols as $c) {
        if ($c === 'user_id' || $c === 'day_number') {
            continue;
        }
        $updates[] = "$c = VALUES($c)";
    }
    $sql = sprintf(
        'INSERT INTO day_progress (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
        implode(',', $cols),
        implode(',', $placeholders),
        implode(', ', $updates)
    );
    $stmt = db()->prepare($sql);
    foreach ($data as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->execute();
}

function load_applications_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT a.*, o.remote_policy, o.schedule_type
         FROM applications a
         LEFT JOIN offer_scores o ON o.application_id = a.id
         WHERE a.user_id = ?
         ORDER BY a.updated_at DESC, a.id DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function find_application_for_user(int $userId, int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT a.*, o.remote_policy, o.schedule_type
         FROM applications a
         LEFT JOIN offer_scores o ON o.application_id = a.id
         WHERE a.user_id = ? AND a.id = ?
         LIMIT 1'
    );
    $stmt->execute([$userId, $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function upsert_offer_compare_fields_for_user(int $userId, int $applicationId, array $fields): void
{
    if ($applicationId < 1 || !find_application_for_user($userId, $applicationId)) {
        return;
    }

    $remote = (string) ($fields['remote_policy'] ?? '');
    if ($remote !== '' && !array_key_exists($remote, remote_policy_options())) {
        $remote = '';
    }
    $schedule = (string) ($fields['schedule_type'] ?? '');
    if ($schedule !== '' && !array_key_exists($schedule, schedule_type_options())) {
        $schedule = '';
    }
    $currency = (string) ($fields['currency'] ?? '');
    if (!in_array($currency, ['ARS', 'USD', 'EUR', 'other'], true)) {
        $currency = null;
    }
    $comp = $fields['total_comp_monthly'] ?? null;
    if ($comp === '' || $comp === null) {
        $comp = null;
    } else {
        $comp = (float) $comp;
    }

    db()->prepare(
        'INSERT INTO offer_scores (application_id, remote_policy, schedule_type, total_comp_monthly, currency)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            remote_policy = VALUES(remote_policy),
            schedule_type = VALUES(schedule_type),
            total_comp_monthly = VALUES(total_comp_monthly),
            currency = VALUES(currency)'
    )->execute([
        $applicationId,
        $remote !== '' ? $remote : null,
        $schedule !== '' ? $schedule : null,
        $comp,
        $currency,
    ]);
}

function save_application_for_user(int $userId, array $app): array
{
    $id = (int) ($app['id'] ?? 0);
    $fields = [
        'day_number' => $app['day_number'] ?? null,
        'company' => $app['company'] ?? '',
        'role_title' => $app['role_title'] ?? '',
        'market' => $app['market'] ?? 'ar',
        'platform' => $app['platform'] ?? null,
        'discovery_source' => $app['discovery_source'] ?? null,
        'canonical_url' => $app['canonical_url'] ?? null,
        'location_eligible' => (int) ($app['location_eligible'] ?? 1),
        'role_family' => $app['role_family'] ?? null,
        'cv_version' => $app['cv_version'] ?? null,
        'cover_letter' => $app['cover_letter'] ?? null,
        'salary_note' => $app['salary_note'] ?? null,
        'currency' => $app['currency'] ?? null,
        'salary_min' => $app['salary_min'] ?? null,
        'salary_max' => $app['salary_max'] ?? null,
        'fit_score' => $app['fit_score'] ?? null,
        'application_date' => $app['application_date'] ?? null,
        'contact_name' => $app['contact_name'] ?? null,
        'follow_up_date' => $app['follow_up_date'] ?? null,
        'stage' => $app['stage'] ?? 'applied',
        'result_notes' => $app['result_notes'] ?? null,
        'notes' => $app['notes'] ?? null,
    ];

    if ($id > 0) {
        $existing = find_application_for_user($userId, $id);
        if (!$existing) {
            throw new RuntimeException('Postulación no encontrada.');
        }
        $sets = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $sets[] = "$k = ?";
            $params[] = $v;
        }
        $params[] = $userId;
        $params[] = $id;
        db()->prepare(
            'UPDATE applications SET ' . implode(', ', $sets) . ' WHERE user_id = ? AND id = ?'
        )->execute($params);
        if (($existing['stage'] ?? '') !== ($fields['stage'] ?? '')) {
            log_application_stage_change($userId, $id, (string) ($existing['stage'] ?? ''), (string) $fields['stage']);
        }
        return find_application_for_user($userId, $id) ?? $existing;
    }

    $cols = array_merge(['user_id'], array_keys($fields));
    $vals = array_merge([$userId], array_values($fields));
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare(
        'INSERT INTO applications (' . implode(',', $cols) . ') VALUES (' . $ph . ')'
    )->execute($vals);
    $newId = (int) db()->lastInsertId();
    log_application_stage_change($userId, $newId, null, (string) $fields['stage']);
    return find_application_for_user($userId, $newId) ?? [];
}

function delete_application_for_user(int $userId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM applications WHERE user_id = ? AND id = ?');
    $stmt->execute([$userId, $id]);
    return $stmt->rowCount() > 0;
}

function update_application_stage_for_user(int $userId, int $id, string $stage): ?array
{
    $app = find_application_for_user($userId, $id);
    if (!$app) {
        return null;
    }
    $from = (string) ($app['stage'] ?? '');
    db()->prepare('UPDATE applications SET stage = ? WHERE user_id = ? AND id = ?')->execute([$stage, $userId, $id]);
    log_application_stage_change($userId, $id, $from, $stage);
    return find_application_for_user($userId, $id);
}

function log_application_stage_change(int $userId, int $appId, ?string $from, string $to): void
{
    db()->prepare(
        'INSERT INTO application_events (application_id, user_id, from_stage, to_stage) VALUES (?, ?, ?, ?)'
    )->execute([$appId, $userId, $from, $to]);
}

function application_stats_for_user(int $userId): array
{
    $apps = load_applications_for_user($userId);
    $counts = [];
    $market = ['ar' => 0, 'intl' => 0];
    $overdue = 0;
    $today = date('Y-m-d');
    $submitted = 0;
    $todayCount = 0;
    $dates = [];

    foreach ($apps as $app) {
        $stage = (string) ($app['stage'] ?? 'discovered');
        $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        $appDate = (string) ($app['application_date'] ?? '');
        if ($appDate === '' && !empty($app['created_at'])) {
            $appDate = substr((string) $app['created_at'], 0, 10);
        }
        if ($appDate !== '') {
            $dates[$appDate] = true;
            if ($appDate === $today) {
                $todayCount++;
            }
        }
        if (is_submitted_stage($stage)) {
            $submitted++;
            $m = $app['market'] ?? 'ar';
            if (isset($market[$m])) {
                $market[$m]++;
            }
        }
        $fu = $app['follow_up_date'] ?? null;
        if ($fu && $fu < $today && !in_array($stage, ['accepted', 'rejected', 'closed'], true)) {
            $overdue++;
        }
    }

    return [
        'counts' => $counts,
        'market' => $market,
        'submitted' => $submitted,
        'overdue' => $overdue,
        'total' => count($apps),
        'today' => $todayCount,
        'days_active' => count($dates),
        'rejected' => ($counts['rejected'] ?? 0) + ($counts['closed'] ?? 0),
        'offers' => ($counts['offer'] ?? 0) + ($counts['accepted'] ?? 0),
        'interviews' => ($counts['recruiter_screen'] ?? 0)
            + ($counts['technical'] ?? 0)
            + ($counts['leadership'] ?? 0)
            + ($counts['final'] ?? 0),
    ];
}

function load_interview_notes_for_user(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT n.*, a.company, a.role_title
         FROM interview_notes n
         LEFT JOIN applications a ON a.id = n.application_id
         WHERE n.user_id = ?
         ORDER BY COALESCE(n.interview_date, DATE(n.created_at)) DESC, n.id DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function find_interview_note_for_user(int $userId, int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM interview_notes WHERE user_id = ? AND id = ? LIMIT 1');
    $stmt->execute([$userId, $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_interview_note_for_user(int $userId, array $note): array
{
    $id = (int) ($note['id'] ?? 0);
    $fields = [
        'application_id' => ($note['application_id'] ?? '') !== '' ? (int) $note['application_id'] : null,
        'interview_type' => $note['interview_type'] ?? 'other',
        'interview_date' => $note['interview_date'] ?: null,
        'interviewer_name' => $note['interviewer_name'] ?: null,
        'title' => $note['title'] ?? '',
        'prep_notes' => $note['prep_notes'] ?: null,
        'live_notes' => $note['live_notes'] ?: null,
        'debrief_went_well' => $note['debrief_went_well'] ?: null,
        'debrief_gaps' => $note['debrief_gaps'] ?: null,
        'debrief_follow_up' => $note['debrief_follow_up'] ?: null,
        'mood_score' => ($note['mood_score'] ?? '') !== '' ? (int) $note['mood_score'] : null,
        'outcome' => $note['outcome'] ?? 'pending',
        'tags' => $note['tags'] ?: null,
    ];

    if ($id > 0) {
        $sets = [];
        $params = [];
        foreach ($fields as $k => $v) {
            $sets[] = "$k = ?";
            $params[] = $v;
        }
        $params[] = $userId;
        $params[] = $id;
        db()->prepare(
            'UPDATE interview_notes SET ' . implode(', ', $sets) . ' WHERE user_id = ? AND id = ?'
        )->execute($params);
        return find_interview_note_for_user($userId, $id) ?? [];
    }

    $cols = array_merge(['user_id'], array_keys($fields));
    $vals = array_merge([$userId], array_values($fields));
    $ph = implode(',', array_fill(0, count($cols), '?'));
    db()->prepare(
        'INSERT INTO interview_notes (' . implode(',', $cols) . ') VALUES (' . $ph . ')'
    )->execute($vals);
    return find_interview_note_for_user($userId, (int) db()->lastInsertId()) ?? [];
}

function delete_interview_note_for_user(int $userId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM interview_notes WHERE user_id = ? AND id = ?');
    $stmt->execute([$userId, $id]);
    return $stmt->rowCount() > 0;
}

function default_ats_role_text(string $lang = 'es'): string
{
    $es = [
        'Líder técnico',
        'Technical Lead',
        'Tech Lead',
        'Engineering Manager',
        'Gerente de Ingeniería',
        'Head of Engineering',
        'Director de Ingeniería',
        'Staff Engineer',
        'Principal Engineer',
        'Arquitecto de Software',
        'Software Architect',
        'Software Delivery Lead',
        'Team Lead',
        'Ingeniero Full Stack Senior',
        'Senior Full Stack Engineer',
        'Ingeniero de Plataforma',
        'Platform Engineer',
        'SRE',
        'Site Reliability Engineer',
        'DevOps Lead',
        'Ingeniero de Observabilidad',
        'IT Manager',
        'Infrastructure Lead',
        'Solutions Engineer',
        'Technical Account Manager',
    ];
    $en = [
        'Technical Lead',
        'Tech Lead',
        'Engineering Manager',
        'Head of Engineering',
        'Director of Engineering',
        'Staff Software Engineer',
        'Principal Engineer',
        'Software Architect',
        'Software Delivery Lead',
        'Engineering Team Lead',
        'Senior Full Stack Engineer',
        'Platform Engineer',
        'Site Reliability Engineer',
        'DevOps Lead',
        'Observability Engineer',
        'IT Manager',
        'Infrastructure Lead',
        'Solutions Engineer',
        'Technical Account Manager',
        'Implementation Engineer',
    ];
    return implode("\n", $lang === 'en' ? $en : $es);
}

function ats_text_is_tech_inventory(string $text): bool
{
    $text = trim($text);
    if ($text === '') {
        return false;
    }
    $techs = [];
    foreach (load_json_data('technologies.json') as $group) {
        foreach ($group['technologies'] ?? [] as $name) {
            $techs[strtolower(trim((string) $name))] = true;
        }
    }
    $hits = 0;
    $lines = 0;
    foreach (preg_split('/\R+/', $text) ?: [] as $line) {
        $line = strtolower(trim($line));
        if ($line === '') {
            continue;
        }
        $lines++;
        if (isset($techs[$line])) {
            $hits++;
        }
    }
    return $lines > 0 && $hits >= 8 && ($hits / $lines) >= 0.4;
}

function ensure_ats_lakes_for_user(int $userId): void
{
    foreach (['es', 'en'] as $lang) {
        $stmt = db()->prepare('SELECT body_text FROM ats_lakes WHERE user_id = ? AND language = ? LIMIT 1');
        $stmt->execute([$userId, $lang]);
        $row = $stmt->fetch();
        $text = $row ? trim((string) $row['body_text']) : '';
        if ($text === '' || ats_text_is_tech_inventory($text)) {
            save_ats_lake_for_user($userId, $lang, default_ats_role_text($lang));
        }
    }
}

function load_ats_lake_for_user(int $userId, string $lang): string
{
    ensure_ats_lakes_for_user($userId);
    $stmt = db()->prepare('SELECT body_text FROM ats_lakes WHERE user_id = ? AND language = ? LIMIT 1');
    $stmt->execute([$userId, $lang]);
    $row = $stmt->fetch();
    return $row ? (string) $row['body_text'] : '';
}

function save_ats_lake_for_user(int $userId, string $lang, string $text): void
{
    db()->prepare(
        'INSERT INTO ats_lakes (user_id, language, body_text)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE body_text = VALUES(body_text)'
    )->execute([$userId, $lang, $text]);
}

function seed_user_document_groups(PDO $pdo, int $userId): void
{
    $rows = [
        ['CV maestro (ES)', 'cv-master-es', 'cv', 'es', 1, null],
        ['Master CV (EN)', 'cv-master-en', 'cv', 'en', 2, null],
        ['Carta de presentación (ES)', 'cover-es', 'cover_letter', 'es', 200, 'Estimado equipo,\n\nMe postulo a {ROL} en {EMPRESA}.'],
        ['Cover letter (EN)', 'cover-en', 'cover_letter', 'en', 210, 'Dear Hiring Team,\n\nI am applying for {ROLE} at {COMPANY}.'],
        ['Mensaje reclutador (ES)', 'msg-recruiter-es', 'message', 'es', 400, 'Hola {NOMBRE},\n\nVi la búsqueda de {ROL} en {EMPRESA}.'],
        ['Recruiter message (EN)', 'msg-recruiter-en', 'message', 'en', 410, 'Hi {NAME},\n\nI saw the {ROLE} opening at {COMPANY}.'],
        ['Mensaje CTO/CEO (ES)', 'msg-cto-es', 'message', 'es', 420, "Hola {NOMBRE},\n\nVi {NECESIDAD} en {EMPRESA} y puedo aportar {EVIDENCIA}.\n¿Tenés 15 minutos para validar si tiene sentido?\n\nGracias"],
        ['CTO/CEO message (EN)', 'msg-cto-en', 'message', 'en', 430, "Hi {NAME},\n\nI noticed {NEED} at {COMPANY} and can bring {EVIDENCE}.\nWould a 15-minute chat be useful?\n\nThanks"],
        ['Follow-up 1 (ES)', 'msg-followup-1-es', 'message', 'es', 480, "Hola {NOMBRE},\n\nTe escribo por mi postulación a {ROL}.\n\nSaludos"],
        ['Follow-up 1 (EN)', 'msg-followup-1-en', 'message', 'en', 490, "Hi {NAME},\n\nFollowing up on my application for {ROLE}.\n\nBest"],
        ['Agradecimiento (ES)', 'msg-thankyou-es', 'message', 'es', 520, "Hola {NOMBRE},\n\nGracias por la conversación sobre {ROL}."],
        ['Thank-you note (EN)', 'msg-thankyou-en', 'message', 'en', 530, "Hi {NAME},\n\nThank you for the conversation about {ROLE}."],
        ['Script salarial (ES)', 'msg-salary-es', 'message', 'es', 540, 'Gracias por la pregunta. ¿Qué rango está aprobado para este rol?'],
        ['Salary script (EN)', 'msg-salary-en', 'message', 'en', 550, 'Thanks for asking. What compensation range is approved for this role?'],
        ['¿Crees que soy un fit? (ES)', 'msg-fit-check-es', 'message', 'es', 560, '¿Crees que soy un fit para el rol? ¿Sí o no? ¿Y por qué?'],
    ];
    $ins = $pdo->prepare(
        'INSERT IGNORE INTO document_groups (user_id, name, slug, category, language, sort_order, body_text)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $r) {
        $ins->execute([$userId, $r[0], $r[1], $r[2], $r[3], $r[4], $r[5]]);
    }
}

function update_profile_for_user(int $userId, array $fields): void
{
    $name = trim((string) ($fields['name'] ?? ''));
    if ($name === '') {
        throw new InvalidArgumentException('El nombre es obligatorio.');
    }

    $blank = static function (?string $v): ?string {
        $v = $v === null ? '' : trim($v);
        return $v === '' ? null : $v;
    };

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $userId]);
        $pdo->prepare(
            'INSERT INTO profiles (
                user_id, headline, linkedin_url, website_url, portfolio_url, x_url, instagram_url, phone, location, bio
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               headline = VALUES(headline),
               linkedin_url = VALUES(linkedin_url),
               website_url = VALUES(website_url),
               portfolio_url = VALUES(portfolio_url),
               x_url = VALUES(x_url),
               instagram_url = VALUES(instagram_url),
               phone = VALUES(phone),
               location = VALUES(location),
               bio = VALUES(bio)'
        )->execute([
            $userId,
            $blank(isset($fields['headline']) ? (string) $fields['headline'] : null),
            $blank(isset($fields['linkedin_url']) ? (string) $fields['linkedin_url'] : null),
            $blank(isset($fields['website_url']) ? (string) $fields['website_url'] : null),
            $blank(isset($fields['portfolio_url']) ? (string) $fields['portfolio_url'] : null),
            $blank(isset($fields['x_url']) ? (string) $fields['x_url'] : null),
            $blank(isset($fields['instagram_url']) ? (string) $fields['instagram_url'] : null),
            $blank(isset($fields['phone']) ? (string) $fields['phone'] : null),
            $blank(isset($fields['location']) ? (string) $fields['location'] : null),
            $blank(isset($fields['bio']) ? (string) $fields['bio'] : null),
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function change_password_for_user(int $userId, string $currentPassword, string $newPassword): void
{
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('La nueva contraseña debe tener al menos 8 caracteres.');
    }
    $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($currentPassword, (string) $row['password_hash'])) {
        throw new InvalidArgumentException('La contraseña actual no es correcta.');
    }
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
}

function dashboard_moment(array $stats, int $focusDay = 0): array
{
    if (($stats['offers'] ?? 0) > 0) {
        return [
            'key' => 'offer',
            'title' => '¡Apareció un cofre de oferta!',
            'blurb' => 'Abrí Comparar, anotá la negociación y no dejes que el tesoro se enfríe.',
        ];
    }
    if (($stats['interviews'] ?? 0) > 0) {
        return [
            'key' => 'interview',
            'title' => 'Hay un boss fight de entrevista',
            'blurb' => 'Repasá Charlas, llevá 3 historias y un hechizo de cierre.',
        ];
    }
    if (($stats['overdue'] ?? 0) > 0) {
        return [
            'key' => 'follow',
            'title' => 'Follow-ups abandonados en el mapa',
            'blurb' => 'Un mensaje corto hoy vale más que diez envíos nuevos.',
        ];
    }
    if (($stats['today'] ?? 0) > 0) {
        return [
            'key' => 'today',
            'title' => 'La campaña de hoy ya empezó',
            'blurb' => 'Buen ritmo. Seguí sumando al diario, día por día.',
        ];
    }
    if (($stats['submitted'] ?? 0) === 0) {
        return [
            'key' => 'start',
            'title' => 'El grimorio está vacío',
            'blurb' => 'Cargá la primera postulación en el diario. Yo te acompaño.',
        ];
    }
    return [
        'key' => 'apply',
        'title' => 'Listo para la campaña de hoy',
        'blurb' => 'El progreso es tu historial. Cada envío es un paso en la aventura.',
    ];
}

function load_offers_for_user(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT a.*,
                o.compensation_score,
                o.role_fit_score,
                o.growth_score,
                o.culture_score,
                o.schedule_score,
                o.risk_score,
                o.total_comp_monthly,
                o.currency AS offer_currency,
                o.employment_type,
                o.remote_policy,
                o.schedule_type,
                o.notes AS offer_notes,
                o.ranking_notes
         FROM applications a
         LEFT JOIN offer_scores o ON o.application_id = a.id
         WHERE a.user_id = ? AND a.stage IN ('offer', 'accepted')
         ORDER BY a.updated_at DESC, a.id DESC"
    );
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $comp = $row['total_comp_monthly'] ?? $row['salary_max'] ?? $row['salary_min'] ?? null;
        $row['offer'] = [
            'compensation_score' => (int) ($row['compensation_score'] ?? 0),
            'role_fit_score' => (int) ($row['role_fit_score'] ?? 0),
            'growth_score' => (int) ($row['growth_score'] ?? 0),
            'culture_score' => (int) ($row['culture_score'] ?? 0),
            'schedule_score' => (int) ($row['schedule_score'] ?? 0),
            'risk_score' => (int) ($row['risk_score'] ?? 0),
            'total_comp_monthly' => $comp,
            'currency' => $row['offer_currency'] ?? $row['currency'] ?? null,
            'employment_type' => $row['employment_type'] ?? null,
            'remote_policy' => $row['remote_policy'] ?? null,
            'schedule_type' => $row['schedule_type'] ?? null,
            'notes' => $row['offer_notes'] ?? null,
            'ranking_notes' => $row['ranking_notes'] ?? null,
        ];
    }
    unset($row);
    return $rows;
}

function save_offer_score_for_user(int $userId, int $applicationId, array $fields): void
{
    $app = find_application_for_user($userId, $applicationId);
    if (!$app || !in_array((string) ($app['stage'] ?? ''), ['offer', 'accepted'], true)) {
        throw new InvalidArgumentException('La postulación no está en Oferta.');
    }

    $clamp = static fn ($v): int => max(0, min(10, (int) $v));
    $currency = (string) ($fields['currency'] ?? '');
    if (!in_array($currency, ['ARS', 'USD', 'EUR', 'other', ''], true)) {
        $currency = '';
    }
    $comp = $fields['total_comp_monthly'] ?? '';

    db()->prepare(
        'INSERT INTO offer_scores (
            application_id, compensation_score, role_fit_score, growth_score, culture_score,
            schedule_score, risk_score, total_comp_monthly, currency, employment_type,
            remote_policy, schedule_type, notes, ranking_notes
         ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            compensation_score = VALUES(compensation_score),
            role_fit_score = VALUES(role_fit_score),
            growth_score = VALUES(growth_score),
            culture_score = VALUES(culture_score),
            schedule_score = VALUES(schedule_score),
            risk_score = VALUES(risk_score),
            total_comp_monthly = VALUES(total_comp_monthly),
            currency = VALUES(currency),
            employment_type = VALUES(employment_type),
            remote_policy = VALUES(remote_policy),
            schedule_type = VALUES(schedule_type),
            notes = VALUES(notes),
            ranking_notes = VALUES(ranking_notes)'
    )->execute([
        $applicationId,
        $clamp($fields['compensation_score'] ?? 0),
        $clamp($fields['role_fit_score'] ?? 0),
        $clamp($fields['growth_score'] ?? 0),
        $clamp($fields['culture_score'] ?? 0),
        $clamp($fields['schedule_score'] ?? 0),
        $clamp($fields['risk_score'] ?? 0),
        $comp === '' || $comp === null ? null : (float) $comp,
        $currency !== '' ? $currency : null,
        $fields['employment_type'] ?? null,
        $fields['remote_policy'] ?? null,
        $fields['schedule_type'] ?? null,
        $fields['notes'] ?? null,
        $fields['ranking_notes'] ?? null,
    ]);
}

function load_user_portals(int $userId): array
{
    ensure_user_portals_for_user($userId);
    $stmt = db()->prepare('SELECT * FROM user_portals WHERE user_id = ? ORDER BY section ASC, name ASC, id ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function ensure_user_portals_for_user(int $userId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM user_portals WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    $titles = [
        'Argentina — job boards and search' => 'Argentina · Bolsa de trabajo',
        'Argentina — recruiters and communities' => 'Argentina · Recruiters y comunidades',
        'International — remote boards' => 'Internacional · Remote boards',
        'International — talent networks and firms' => 'Internacional · Talent networks',
        'Freelance and contract channels' => 'Freelance / contrato',
    ];
    foreach (load_json_data('portals.json') as $section) {
        $sectionName = $titles[$section['section'] ?? ''] ?? (string) ($section['section'] ?? 'Otros');
        foreach ($section['portals'] ?? [] as $portal) {
            $name = trim((string) ($portal['name'] ?? ''));
            $url = trim((string) ($portal['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            try {
                save_user_portal($userId, $name, $url, (string) ($portal['use'] ?? ''), 0, $sectionName);
            } catch (Throwable $e) {
                // skip invalid seed rows
            }
        }
    }
}

function save_user_portal(int $userId, string $name, string $url, string $notes = '', int $id = 0, ?string $section = null): int
{
    $name = trim($name);
    $url = trim($url);
    $notes = trim($notes);
    $section = $section !== null ? trim($section) : null;
    if ($section === '') {
        $section = null;
    }
    if ($name === '' || $url === '') {
        throw new InvalidArgumentException('Nombre y URL son obligatorios.');
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('La URL no es válida.');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE user_portals SET name = ?, url = ?, notes = ?, section = ? WHERE user_id = ? AND id = ?'
        )->execute([$name, $url, $notes !== '' ? $notes : null, $section, $userId, $id]);
        return $id;
    }
    db()->prepare(
        'INSERT INTO user_portals (user_id, name, url, notes, section) VALUES (?, ?, ?, ?, ?)'
    )->execute([$userId, $name, $url, $notes !== '' ? $notes : null, $section]);
    return (int) db()->lastInsertId();
}

function delete_user_portal(int $userId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM user_portals WHERE user_id = ? AND id = ?');
    $stmt->execute([$userId, $id]);
    return $stmt->rowCount() > 0;
}

function load_tech_ratings_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT technology_id, category FROM user_tech_ratings WHERE user_id = ?');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['technology_id']] = (string) $row['category'];
    }
    return $out;
}

function save_tech_ratings_for_user(int $userId, array $posted): int
{
    $allowed = array_keys(tech_categories());
    $stmt = db()->prepare(
        'INSERT INTO user_tech_ratings (user_id, technology_id, category)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE category = VALUES(category)'
    );
    $updated = 0;
    foreach ($posted as $id => $category) {
        $id = (int) $id;
        $category = (string) $category;
        if ($id < 1 || !in_array($category, $allowed, true)) {
            continue;
        }
        $stmt->execute([$userId, $id, $category]);
        $updated++;
    }
    return $updated;
}

function load_user_technologies(int $userId): array
{
    $stmt = db()->prepare('SELECT * FROM user_technologies WHERE user_id = ? ORDER BY name ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function save_user_technology(int $userId, string $name, string $category, ?int $groupId = null): int
{
    $name = trim($name);
    $allowed = array_keys(tech_categories());
    if ($name === '') {
        throw new InvalidArgumentException('El nombre de la tecnología es obligatorio.');
    }
    if (!in_array($category, $allowed, true)) {
        $category = 'known';
    }
    db()->prepare(
        'INSERT INTO user_technologies (user_id, group_id, name, category) VALUES (?, ?, ?, ?)'
    )->execute([$userId, $groupId, $name, $category]);
    return (int) db()->lastInsertId();
}

function save_user_technology_categories(int $userId, array $posted): int
{
    $allowed = array_keys(tech_categories());
    $stmt = db()->prepare(
        'UPDATE user_technologies SET category = ? WHERE user_id = ? AND id = ?'
    );
    $updated = 0;
    foreach ($posted as $id => $category) {
        $id = (int) $id;
        $category = (string) $category;
        if ($id < 1 || !in_array($category, $allowed, true)) {
            continue;
        }
        $stmt->execute([$category, $userId, $id]);
        $updated += $stmt->rowCount() > 0 ? 1 : 0;
    }
    return $updated;
}

function delete_user_technology(int $userId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM user_technologies WHERE user_id = ? AND id = ?');
    $stmt->execute([$userId, $id]);
    return $stmt->rowCount() > 0;
}

function load_hr_answers_for_user(int $userId): array
{
    $stmt = db()->prepare('SELECT faq_id, question, answer FROM user_hr_answers WHERE user_id = ?');
    $stmt->execute([$userId]);
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['faq_id']] = [
            'question' => $row['question'] !== null && $row['question'] !== '' ? (string) $row['question'] : null,
            'answer' => (string) $row['answer'],
        ];
    }
    return $out;
}

function save_hr_answer_for_user(int $userId, int $faqId, string $answer, ?string $question = null): void
{
    if ($faqId < 1) {
        throw new InvalidArgumentException('Pregunta inválida.');
    }
    $question = $question !== null ? trim($question) : null;
    if ($question === '') {
        $question = null;
    }
    db()->prepare(
        'INSERT INTO user_hr_answers (user_id, faq_id, question, answer)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            question = VALUES(question),
            answer = VALUES(answer)'
    )->execute([$userId, $faqId, $question, $answer]);
}

function load_user_questions(int $userId): array
{
    ensure_user_questions_for_user($userId);
    $stmt = db()->prepare('SELECT * FROM user_questions WHERE user_id = ? ORDER BY id ASC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function ensure_user_questions_for_user(int $userId): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM user_questions WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }
    foreach (load_json_data('company_questions.json') as $row) {
        $q = trim((string) ($row['question'] ?? ''));
        if ($q === '') {
            continue;
        }
        save_user_question(
            $userId,
            $q,
            (string) ($row['why'] ?? ''),
            (string) ($row['tip'] ?? '')
        );
    }
}

function save_user_question(int $userId, string $question, string $why = '', string $tip = '', int $id = 0): int
{
    $question = trim($question);
    $why = trim($why);
    $tip = trim($tip);
    if ($question === '') {
        throw new InvalidArgumentException('La pregunta es obligatoria.');
    }
    if ($id > 0) {
        db()->prepare(
            'UPDATE user_questions SET question = ?, why = ?, tip = ? WHERE user_id = ? AND id = ?'
        )->execute([$question, $why !== '' ? $why : null, $tip !== '' ? $tip : null, $userId, $id]);
        return $id;
    }
    db()->prepare(
        'INSERT INTO user_questions (user_id, question, why, tip) VALUES (?, ?, ?, ?)'
    )->execute([$userId, $question, $why !== '' ? $why : null, $tip !== '' ? $tip : null]);
    return (int) db()->lastInsertId();
}

function delete_user_question(int $userId, int $id): bool
{
    $stmt = db()->prepare('DELETE FROM user_questions WHERE user_id = ? AND id = ?');
    $stmt->execute([$userId, $id]);
    return $stmt->rowCount() > 0;
}
