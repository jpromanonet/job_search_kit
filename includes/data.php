<?php

declare(strict_types=1);

function load_json_data(string $filename): array
{
    $config = require __DIR__ . '/../config.php';
    $path = $config['paths']['data'] . '/' . $filename;
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Prefer MySQL plan_days (seeded by install.php).
 * Per-user progress: use load_plan_days_for_user() in repositories.php when logged in.
 * Fallback: data/days.php or days.json + day_progress.json.
 */
function load_plan_days(): array
{
    try {
        if (function_exists('db')) {
            $days = db()->query('SELECT * FROM plan_days ORDER BY day_number ASC')->fetchAll();
            if ($days) {
                $out = [];
                foreach ($days as $d) {
                    $row = $d;
                    $row['blog_publish'] = !empty($d['blog_publish']);
                    $row['status'] = 'not_started';
                    $row['article_url'] = null;
                    $row['instagram_done'] = 0;
                    $row['linkedin_posted'] = 0;
                    $row['x_posted'] = 0;
                    $row['notes'] = null;
                    $row['evidence'] = null;
                    $row['blockers'] = null;
                    $row['carry_forward'] = null;
                    $row['applications_logged'] = 0;
                    $row['completed_at'] = null;
                    $out[] = $row;
                }
                return $out;
            }
        }
    } catch (Throwable $e) {
        // fall through to file fallback
    }

    $path = (require __DIR__ . '/../config.php')['paths']['data'] . '/days.php';
    if (!is_file($path)) {
        $jsonDays = load_json_data('days.json');
    } else {
        $jsonDays = require $path;
        if (!is_array($jsonDays)) {
            $jsonDays = [];
        }
    }

    $progress = load_json_data('day_progress.json');
    $byDay = [];
    foreach ($progress as $row) {
        if (isset($row['day_number'])) {
            $byDay[(int) $row['day_number']] = $row;
        }
    }

    $out = [];
    foreach ($jsonDays as $day) {
        $row = normalize_day_from_json($day);
        $n = (int) $row['day_number'];
        if (isset($byDay[$n])) {
            $p = $byDay[$n];
            foreach (['status', 'article_url', 'notes', 'evidence', 'blockers', 'carry_forward', 'applications_logged', 'instagram_done', 'linkedin_posted', 'x_posted', 'completed_at'] as $k) {
                if (array_key_exists($k, $p)) {
                    $row[$k] = $p[$k];
                }
            }
        }
        $out[] = $row;
    }
    return $out;
}

function normalize_day_from_json(array $day): array
{
    $n = (int) ($day['day'] ?? 0);
    // Sin fechas de calendario en el playbook: solo número de día.
    $planDate = null;

    return [
        'day_number' => $n,
        'plan_date' => $planDate,
        'date_label' => '',
        'phase' => $day['phase'] ?? 'build',
        'phase_label' => $day['phase_label'] ?? '',
        'quota_label' => $day['quota_label'] ?? '',
        'applications_target' => (int) ($day['applications_target'] ?? 0),
        'market_split' => $day['market_split'] ?? '',
        'outcome' => $day['outcome'] ?? null,
        'special_focus' => $day['special_focus'] ?? null,
        'candidate_tasks' => json_encode($day['candidate_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
        'ai_tasks' => json_encode($day['ai_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
        'execute_today' => json_encode($day['execute_today'] ?? [], JSON_UNESCAPED_UNICODE),
        'definition_of_done' => $day['definition_of_done'] ?? null,
        'source_allocations' => json_encode($day['source_allocations'] ?? [], JSON_UNESCAPED_UNICODE),
        'blog_publish' => !empty($day['blog_publish']),
        'blog_title' => $day['blog_title'] ?? '',
        'blog_angle' => $day['blog_angle'] ?? null,
        'blog_draft' => $day['blog_draft'] ?? null,
        'x_copy' => $day['x_copy'] ?? null,
        'linkedin_copy' => $day['linkedin_copy'] ?? null,
        'instagram_task' => $day['instagram_task'] ?? null,
        'close_day_proof' => $day['close_day_proof'] ?? null,
        'status' => 'not_started',
        'article_url' => null,
        'instagram_done' => 0,
        'linkedin_posted' => 0,
        'x_posted' => 0,
        'notes' => null,
        'evidence' => null,
        'blockers' => null,
        'carry_forward' => null,
        'applications_logged' => 0,
        'completed_at' => null,
    ];
}

function day_progress_path(): string
{
    $config = require __DIR__ . '/../config.php';
    return $config['paths']['data'] . '/day_progress.json';
}

function save_day_progress(int $dayNumber, array $fields): void
{
    $path = day_progress_path();
    $all = load_json_data('day_progress.json');
    $indexed = [];
    foreach ($all as $row) {
        $indexed[(int) $row['day_number']] = $row;
    }
    $current = $indexed[$dayNumber] ?? ['day_number' => $dayNumber];
    foreach ($fields as $k => $v) {
        $current[$k] = $v;
    }
    $current['day_number'] = $dayNumber;
    $current['updated_at'] = date('Y-m-d H:i:s');
    $indexed[$dayNumber] = $current;
    ksort($indexed);
    $json = json_encode(array_values($indexed), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar day_progress.json');
    }
    write_data_file($path, $json . "\n");
}

function db_available(): bool
{
    try {
        db()->query('SELECT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}
