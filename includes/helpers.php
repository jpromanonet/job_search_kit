<?php

declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_list($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if ($value === null || $value === '') {
        return [];
    }
    $decoded = json_decode((string) $value, true);
    return is_array($decoded) ? $decoded : [];
}

function status_label(string $status): string
{
    return match ($status) {
        'not_started' => 'Sin empezar',
        'in_progress' => 'En curso',
        'done' => 'Hecho',
        'blocked' => 'Bloqueado',
        'missed' => 'Perdido',
        default => $status,
    };
}

function status_badge_class(string $status): string
{
    return match ($status) {
        'not_started' => 'text-bg-secondary',
        'in_progress' => 'text-bg-primary',
        'done' => 'text-bg-success',
        'blocked' => 'text-bg-warning',
        'missed' => 'text-bg-danger',
        default => 'text-bg-light',
    };
}

function phase_label(string $phase): string
{
    return match ($phase) {
        'build' => 'Construcción',
        'high_volume' => 'Ejecución',
        'finish' => 'Cierre',
        default => $phase,
    };
}

function phase_label_display(?string $raw, ?string $phase = null): string
{
    $raw = trim((string) $raw);
    $map = [
        'BUILD' => 'Construcción',
        'LAUNCH' => 'Lanzamiento',
        'HIGH-VOLUME' => 'Ejecución',
        'OPTIMIZE' => 'Optimización',
        'FINISH' => 'Cierre',
        'CLOSE' => 'Cierre',
        'DEPTH' => 'Profundidad',
        'INTERVIEW' => 'Entrevistas',
        'PIPELINE' => 'Pipeline',
        'CONSTRUCCIÓN' => 'Construcción',
        'LANZAMIENTO' => 'Lanzamiento',
        'ALTO VOLUMEN' => 'Ejecución',
        'EJECUCIÓN' => 'Ejecución',
        'OPTIMIZACIÓN' => 'Optimización',
        'CIERRE' => 'Cierre',
        'PROFUNDIDAD' => 'Profundidad',
        'ENTREVISTAS' => 'Entrevistas',
    ];
    if ($raw !== '' && isset($map[mb_strtoupper($raw, 'UTF-8')])) {
        return $map[mb_strtoupper($raw, 'UTF-8')];
    }
    if ($raw !== '') {
        return $raw;
    }
    return phase_label((string) $phase);
}

function phase_badge_class(string $phase): string
{
    return match ($phase) {
        'build' => 'phase-build',
        'high_volume' => 'phase-volume',
        'finish' => 'phase-finish',
        default => '',
    };
}

/**
 * Día actual del plan = primer día aún no marcado como "Hecho".
 * Sin fechas de calendario: arrancás cuando querés desde el Día 1.
 */
function focus_day_from_progress(array $days): int
{
    foreach ($days as $d) {
        $status = (string) ($d['status'] ?? 'not_started');
        if ($status !== 'done') {
            $n = (int) ($d['day_number'] ?? $d['day'] ?? 1);
            return max(1, min(100, $n));
        }
    }
    return 100;
}

/** @deprecated Usar focus_day_from_progress(); se mantiene por compatibilidad. */
function campaign_day_number(string $startDate = ''): int
{
    if (function_exists('load_plan_days')) {
        return focus_day_from_progress(load_plan_days());
    }
    return 1;
}

/** @deprecated Usar focus_day_from_progress(); se mantiene por compatibilidad. */
function focus_day_number(string $startDate = ''): int
{
    return campaign_day_number($startDate);
}

function parse_plan_date(string $label): ?string
{
    $dt = DateTimeImmutable::createFromFormat('l, j F Y', $label);
    if ($dt instanceof DateTimeImmutable) {
        return $dt->format('Y-m-d');
    }
    return null;
}

function nl2p(string $text): string
{
    $parts = preg_split("/\n{2,}/", trim($text)) ?: [];
    $html = '';
    foreach ($parts as $part) {
        $html .= '<p>' . nl2br(e($part)) . '</p>';
    }
    return $html;
}

/**
 * Render recommendation body: detect repeating column tables vs prose/lists.
 */
function render_recommendation_body(string $title, string $body): string
{
    $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];
    $lines = array_values(array_map('trim', $lines));
    $lines = array_values(array_filter($lines, static fn ($l) => $l !== ''));

    $known = recommendation_table_schema($title);
    if ($known !== null) {
        return render_known_recommendation_table($lines, $known);
    }

    // Generic: if first N lines look like headers and body length divisible
    $guess = guess_table_from_lines($lines);
    if ($guess !== null) {
        return render_html_table($guess['headers'], $guess['rows'], $guess['intro']);
    }

    // Bullet / numbered list
    $bulletish = true;
    foreach ($lines as $l) {
        if (!preg_match('/^(?:[-•☐]|\d+[\.)])\s+/u', $l) && count($lines) > 1) {
            // allow mixed: if majority are short sentences as list items
            $bulletish = false;
            break;
        }
    }
    if ($bulletish && count($lines) >= 2 && preg_match('/^(?:[-•☐]|\d+[\.)])\s+/u', $lines[0])) {
        $html = '<ul class="rec-list">';
        foreach ($lines as $l) {
            $html .= '<li>' . e(preg_replace('/^(?:[-•☐]|\d+[\.)])\s+/u', '', $l) ?? $l) . '</li>';
        }
        return $html . '</ul>';
    }

    // Prose paragraphs (group by blank already stripped — one line = one block if long)
    $html = '';
    foreach ($lines as $l) {
        $html .= '<p>' . e($l) . '</p>';
    }
    return $html !== '' ? '<div class="rec-prose">' . $html . '</div>' : '';
}

function recommendation_table_schema(string $title): ?array
{
    $map = [
        'THE TWO PHASES' => [
            'cols' => 4,
            'headers' => ['Fase', 'Días', 'Apps', 'Resultado principal'],
            'skip_header_lines' => [
                'Phase', 'Days', 'Applications', 'Primary outcome',
                'Fase', 'Días', 'Apps', 'Resultado principal',
                'Aplicaciones', 'Resultado primario',
            ],
        ],
        'TARGETS' => [
            'cols' => 5,
            'headers' => ['Mercado', 'Base / walk-in', 'Buen outcome', 'Excelente', 'Cómo plantearlo'],
            'skip_header_lines' => [
                'Market', 'Base / walk-in target', 'Good outcome', 'Excellent outcome', 'How to state it',
                'Mercado', 'Base / walk-in', 'Buen outcome', 'Excelente', 'Cómo plantearlo',
                'Objetivo base/sin cita previa', 'Buen resultado', 'Excelente resultado', 'como decirlo', 'Cómo decirlo',
            ],
            'intro_until' => ['Market', 'Mercado'],
        ],
        'Six CV role families' => [
            'cols' => 3,
            'headers' => ['#', 'Familia', 'Evidencia principal'],
            'skip_header_lines' => ['#', 'Family', 'Primary evidence', 'Familia', 'Evidencia principal'],
        ],
        'Candidate and AI copilot responsibilities' => [
            'cols' => 2,
            'headers' => ['Candidato es dueño de', 'AI copilot apoya'],
            'skip_header_lines' => [
                'Candidate owns', 'AI copilot supports',
                'Candidato es dueño de', 'AI copilot apoya',
                'El candidato es dueño de', 'El copiloto de IA apoya',
                'El candidato posee', 'Soportes de copiloto AI', 'Soportes de copiloto de IA',
            ],
        ],
        'Direct outreach channels' => [
            'cols' => 4,
            'headers' => ['Audiencia', 'Volumen/día', 'Dónde identificarlos', 'Propósito'],
            'skip_header_lines' => ['Audience', 'Daily volume', 'Where to identify them', 'Purpose', 'Audiencia', 'Volumen/día', 'Dónde identificarlos', 'Propósito'],
        ],
        'Core MySQL tables' => [
            'cols' => 3,
            'headers' => ['Tabla', 'Campos mínimos', 'Relación'],
            'skip_header_lines' => ['Table', 'Minimum fields', 'Key relationship', 'Tabla', 'Campos mínimos', 'Relación'],
        ],
        'Calculations and controls' => [
            'cols' => 2,
            'headers' => ['Métrica / regla', 'Definición'],
            'skip_header_lines' => ['Metric / rule', 'Definition', 'Métrica / regla', 'Definición'],
        ],
        'Recommended implementation' => [
            'cols' => 3,
            'headers' => ['Capa', 'Recomendación', 'Motivo'],
            'skip_header_lines' => ['Layer', 'Recommendation', 'Reason', 'Capa', 'Recomendación', 'Motivo'],
        ],
        'Argentina — job boards and search' => [
            'cols' => 3,
            'headers' => ['Canal', 'URL', 'Uso'],
            'skip_header_lines' => ['Channel', 'Home', 'Use', 'Canal', 'URL', 'Uso'],
        ],
        'Argentina — recruiters and communities' => [
            'cols' => 3,
            'headers' => ['Canal', 'URL', 'Uso'],
            'skip_header_lines' => ['Channel', 'Home', 'Use', 'Canal', 'URL', 'Uso'],
        ],
    ];
    return $map[$title] ?? null;
}

function render_known_recommendation_table(array $lines, array $schema): string
{
    $cols = (int) $schema['cols'];
    $headers = $schema['headers'];
    $skip = $schema['skip_header_lines'] ?? [];
    $introUntil = $schema['intro_until'] ?? null;

    $intro = [];
    $i = 0;
    if ($introUntil) {
        while ($i < count($lines) && !in_array($lines[$i], $introUntil, true) && !in_array($lines[$i], $skip, true)) {
            $intro[] = $lines[$i];
            $i++;
        }
    }

    while ($i < count($lines) && in_array($lines[$i], $skip, true)) {
        $i++;
    }

    $data = array_slice($lines, $i);

    // Multi-sección (Canal/URL/Uso con subtítulos "Internacional — …")
    if ($cols === 3 && in_array('Canal', $headers, true)) {
        return render_multi_channel_tables($data, $headers, $intro, $skip);
    }

    $rows = [];
    $chunk = [];
    $notes = [];
    foreach ($data as $line) {
        if ($rows && preg_match('/^.+\s+—\s+.+/u', $line)) {
            $notes[] = $line;
            continue;
        }
        if ($notes) {
            $notes[] = $line;
            continue;
        }
        $chunk[] = $line;
        if (count($chunk) === $cols) {
            $rows[] = $chunk;
            $chunk = [];
        }
    }
    if ($chunk) {
        $notes = array_merge($chunk, $notes);
    }

    $html = render_html_table($headers, $rows, $intro, []);
    if ($notes) {
        $html .= render_recommendation_notes($notes, $headers, $skip);
    }
    return $html;
}

/**
 * Parsea bloques Canal/URL/Uso separados por títulos "… — …".
 */
function render_multi_channel_tables(array $lines, array $headers, array $intro, array $skip): string
{
    $html = '';
    if ($intro) {
        $html .= '<div class="rec-prose">';
        foreach ($intro as $p) {
            $html .= '<p>' . e($p) . '</p>';
        }
        $html .= '</div>';
    }

    $sectionTitle = null;
    $rows = [];
    $chunk = [];

    $emit = static function (string &$html, ?string &$sectionTitle, array &$rows, array &$chunk, array $headers): void {
        $extra = $chunk;
        $chunk = [];
        if ($sectionTitle !== null && $sectionTitle !== '') {
            $html .= '<h4 class="rec-subhead">' . e($sectionTitle) . '</h4>';
            $sectionTitle = null;
        }
        if ($rows) {
            $html .= render_html_table($headers, $rows, [], []);
            $rows = [];
        }
        if ($extra) {
            $html .= '<div class="rec-notes">';
            foreach ($extra as $n) {
                $html .= '<p>' . e($n) . '</p>';
            }
            $html .= '</div>';
        }
    };

    foreach ($lines as $idx => $line) {
        $trim = trim((string) $line);
        if ($trim === '') {
            continue;
        }
        $isSectionDash = (bool) preg_match('/^.+\s+—\s+.+/u', $trim)
            && !preg_match('#^(https?://|www\.)#i', $trim);

        // Título de bloque sin "—": línea seguida de Canal/URL/Uso
        $isSectionBeforeHeaders = false;
        if (!$isSectionDash && !in_array($trim, $skip, true) && !in_array($trim, $headers, true)) {
            $lookAhead = [];
            for ($j = $idx + 1; $j < count($lines) && count($lookAhead) < 3; $j++) {
                $n = trim((string) $lines[$j]);
                if ($n === '') {
                    continue;
                }
                $lookAhead[] = $n;
            }
            if (
                count($lookAhead) >= 3
                && in_array($lookAhead[0], $skip, true)
                && in_array($lookAhead[1], $skip, true)
                && in_array($lookAhead[2], $skip, true)
            ) {
                $isSectionBeforeHeaders = true;
            }
        }

        if ($isSectionDash || $isSectionBeforeHeaders) {
            $emit($html, $sectionTitle, $rows, $chunk, $headers);
            $sectionTitle = $trim;
            continue;
        }
        if (in_array($trim, $skip, true) || in_array($trim, $headers, true)) {
            continue;
        }
        $chunk[] = $trim;
        if (count($chunk) === 3) {
            $rows[] = $chunk;
            $chunk = [];
        }
    }
    $emit($html, $sectionTitle, $rows, $chunk, $headers);

    return $html;
}

/**
 * Notas residuales: si parecen tablas Canal/URL/Uso, renderizarlas; si no, prosa.
 */
function render_recommendation_notes(array $notes, array $headers = ['Canal', 'URL', 'Uso'], array $skip = []): string
{
    $skip = array_values(array_unique(array_merge($skip, $headers, ['Channel', 'Home', 'Use', 'Canal', 'URL', 'Uso'])));
    $looksLikeChannels = false;
    foreach ($notes as $n) {
        if (in_array($n, ['Canal', 'Channel', 'URL', 'Home', 'Uso', 'Use'], true) || preg_match('/^.+\s+—\s+.+/u', $n)) {
            $looksLikeChannels = true;
            break;
        }
        if (preg_match('#^(https?://|www\.)#i', $n) || (str_contains($n, '.') && !str_contains($n, ' ') && strlen($n) < 80)) {
            $looksLikeChannels = true;
            break;
        }
    }
    if ($looksLikeChannels) {
        return render_multi_channel_tables($notes, $headers, [], $skip);
    }

    $html = '<div class="rec-notes">';
    foreach ($notes as $n) {
        $content = e($n);
        if (preg_match('#^(https?://|www\.)#i', $n)) {
            $href = str_starts_with(strtolower($n), 'http') ? $n : ('https://' . $n);
            $content = '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($n) . '</a>';
        }
        $html .= '<p>' . $content . '</p>';
    }
    return $html . '</div>';
}

function guess_table_from_lines(array $lines): ?array
{
    if (count($lines) < 6) {
        return null;
    }
    foreach ([5, 4, 3, 2] as $cols) {
        // Try: optional intro, then cols header words, then rows
        for ($start = 0; $start <= min(4, count($lines) - $cols * 2); $start++) {
            $rest = array_slice($lines, $start);
            if (count($rest) < $cols * 2) {
                continue;
            }
            $headers = array_slice($rest, 0, $cols);
            $body = array_slice($rest, $cols);
            if (count($body) % $cols !== 0) {
                continue;
            }
            // Heuristic: headers are short
            $shortHeaders = true;
            foreach ($headers as $h) {
                if (mb_strlen($h) > 48) {
                    $shortHeaders = false;
                    break;
                }
            }
            if (!$shortHeaders) {
                continue;
            }
            $rows = array_chunk($body, $cols);
            if (count($rows) < 2) {
                continue;
            }
            return [
                'headers' => $headers,
                'rows' => $rows,
                'intro' => array_slice($lines, 0, $start),
            ];
        }
    }
    return null;
}

function render_html_table(array $headers, array $rows, array $intro = [], array $notes = []): string
{
    $html = '';
    if ($intro) {
        $html .= '<div class="rec-prose">';
        foreach ($intro as $p) {
            $html .= '<p>' . e($p) . '</p>';
        }
        $html .= '</div>';
    }
    if ($rows) {
        $html .= '<div class="table-wrap"><table class="data-table rec-table"><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . e($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $i => $cell) {
                $tag = $i === 0 ? 'th' : 'td';
                $cls = $i === 0 ? ' class="row-head"' : '';
                // Autolink URLs
                $content = e($cell);
                if (preg_match('#^(https?://|www\.)#i', $cell)) {
                    $href = str_starts_with(strtolower($cell), 'http') ? $cell : ('https://' . $cell);
                    $content = '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($cell) . '</a>';
                }
                $html .= "<{$tag}{$cls}>{$content}</{$tag}>";
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
    }
    if ($notes) {
        $html .= '<div class="rec-notes">';
        foreach ($notes as $n) {
            $content = e($n);
            if (preg_match('#^(https?://|www\.)#i', $n)) {
                $href = str_starts_with(strtolower($n), 'http') ? $n : ('https://' . $n);
                $content = '<a href="' . e($href) . '" target="_blank" rel="noopener">' . e($n) . '</a>';
            }
            $html .= '<p>' . $content . '</p>';
        }
        $html .= '</div>';
    }
    return $html;
}

function recommendation_groups(): array
{
    return [
        'Campaña' => ['Campaña', 'CAMPAIGN PROMISE', 'READ THIS FIRST'],
        'Cómo opera' => ['THE TWO PHASES', 'SUSTAINABLE PACE', 'What counts as an application', 'Operating assumptions and safeguards', 'Candidate and AI copilot responsibilities', 'Definition of a completed day', 'DAILY EXECUTION', 'DAILY ORDER'],
        'Targets y CVs' => ['TARGETS', 'NEGOTIATION RULE', 'Six CV role families', 'KEYWORD DISCIPLINE'],
        'Canales' => ['CHANNEL MAP', 'PORTAL AVAILABILITY', 'Argentina — job boards and search', 'Argentina — recruiters and communities', 'Direct outreach channels'],
    ];
}

/** Secciones de spec de producto: ocultas del índice (viven en la app). */
function recommendation_hidden_titles(): array
{
    return [
        'PRODUCT SPECIFICATION',
        'NAVIGATION DECISION',
        'FIXED TECHNOLOGY SCOPE',
        '1. Plan',
        '2. Kit',
        '3. HR FAQ',
        '4. Tracker',
        'Core MySQL tables',
        'Calculations and controls',
        'Recommended implementation',
        'NO-AUTH DEPLOYMENT BOUNDARY',
        'MVP acceptance criteria',
    ];
}

function recommendation_title_es(string $title): string
{
    return match ($title) {
        'Campaña' => 'Resumen de campaña',
        'CAMPAIGN PROMISE' => 'Promesa de la campaña',
        'READ THIS FIRST' => 'Leé esto primero',
        'THE TWO PHASES' => 'Las dos fases',
        'SUSTAINABLE PACE' => 'Ritmo sostenible (5/día)',
        'What counts as an application' => 'Qué cuenta como postulación',
        'Operating assumptions and safeguards' => 'Supuestos y salvaguardas',
        'Candidate and AI copilot responsibilities' => 'Responsabilidades: candidato vs AI',
        'Definition of a completed day' => 'Definición de día completo',
        'TARGETS' => 'Targets de compensación',
        'NEGOTIATION RULE' => 'Regla de negociación',
        'Six CV role families' => 'CVs y documentos',
        'KEYWORD DISCIPLINE' => 'Disciplina de keywords',
        'CHANNEL MAP' => 'Mapa de canales',
        'PORTAL AVAILABILITY' => 'Disponibilidad de portales',
        'Argentina — job boards and search' => 'Argentina · bolsas de trabajo',
        'Argentina — recruiters and communities' => 'Argentina · recruiters y comunidades',
        'Direct outreach channels' => 'Outreach directo (cuotas diarias)',
        'PRODUCT SPECIFICATION' => 'Spec del producto',
        'NAVIGATION DECISION' => 'Navegación',
        'FIXED TECHNOLOGY SCOPE' => 'Stack fijo',
        '1. Plan' => 'Módulo Plan',
        '2. Kit' => 'Módulo Kit',
        '3. HR FAQ' => 'Módulo HR FAQ',
        '4. Tracker' => 'Módulo Tracker',
        'Core MySQL tables' => 'Tablas MySQL',
        'Calculations and controls' => 'Cálculos y controles',
        'Recommended implementation' => 'Implementación recomendada',
        'NO-AUTH DEPLOYMENT BOUNDARY' => 'Límite sin autenticación',
        'MVP acceptance criteria' => 'Criterios de aceptación MVP',
        'DAILY EXECUTION' => 'Ejecución diaria',
        'DAILY ORDER' => 'Orden del día',
        default => $title,
    };
}

function app_config_value(string $key, mixed $default = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    $parts = explode('.', $key);
    $cursor = $config;
    foreach ($parts as $part) {
        if (!is_array($cursor) || !array_key_exists($part, $cursor)) {
            return $default;
        }
        $cursor = $cursor[$part];
    }
    return $cursor;
}

function base_path(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $appUrl = (string) app_config_value('app.url', '');
    if ($appUrl !== '') {
        $urlPath = parse_url($appUrl, PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '' && $urlPath !== '/') {
            $cached = rtrim($urlPath, '/');
            return $cached;
        }
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (str_contains($script, '/actions/')) {
        $script = dirname(dirname($script));
    } else {
        $script = dirname($script);
    }
    if ($script === '/' || $script === '\\' || $script === '.') {
        $cached = '';
        return $cached;
    }
    $cached = rtrim($script, '/');
    return $cached;
}

function url(string $path = '/'): string
{
    $hash = '';
    if (str_contains($path, '#')) {
        [$path, $hash] = explode('#', $path, 2);
        $hash = '#' . $hash;
    }
    $query = '';
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }
    $path = '/' . ltrim($path, '/');
    if ($path === '//') {
        $path = '/';
    }
    return base_path() . $path . $query . $hash;
}

function redirect_tab(string $tab, array $query = [], string $hash = ''): never
{
    $params = array_merge(['tab' => $tab], $query);
    $target = url('/index.php?' . http_build_query($params));
    if ($hash !== '') {
        $target .= '#' . ltrim($hash, '#');
    }
    header('Location: ' . $target);
    exit;
}

function stage_labels(): array
{
    return [
        'discovered' => 'Descubierto',
        'selected' => 'Seleccionado',
        'preparing' => 'Preparando',
        'applied' => 'Postulado',
        'follow_up' => 'Follow-up',
        'recruiter_screen' => 'Screen reclutador',
        'technical' => 'Técnica',
        'leadership' => 'Liderazgo',
        'final' => 'Final',
        'offer' => 'Oferta',
        'accepted' => 'Aceptada',
        'rejected' => 'Rechazada',
        'closed' => 'Cerrada',
    ];
}

function stage_label(string $stage): string
{
    return stage_labels()[$stage] ?? $stage;
}

function stage_badge_class(string $stage): string
{
    return match ($stage) {
        'discovered', 'selected', 'preparing' => 'badge-muted',
        'applied', 'follow_up' => 'badge-ok',
        'recruiter_screen', 'technical', 'leadership', 'final' => 'badge-info',
        'offer' => 'badge-warn',
        'accepted' => 'badge-ok',
        'rejected' => 'badge-danger',
        'closed' => 'badge-muted',
        default => 'badge-muted',
    };
}

function format_display_date(?string $date): string
{
    if ($date === null) {
        return '—';
    }
    $raw = trim($date);
    if ($raw === '') {
        return '—';
    }
    $dt = DateTime::createFromFormat('Y-m-d', substr($raw, 0, 10));
    if (!$dt instanceof DateTime) {
        return $raw;
    }
    return $dt->format('d/m/Y');
}

function role_families(): array
{
    return [
        'Technical Lead / Software Delivery Lead',
        'Engineering Manager / Head of Engineering',
        'Senior Full-stack Software Engineer',
        'Platform / DevOps / Observability Engineer',
        'Solutions / Implementation / Technical Account Manager',
        'IT Manager / Application Support / Infrastructure Lead',
    ];
}

function tech_categories(): array
{
    return [
        'known' => 'Sé / producción reciente',
        'old' => 'Vieja / producción previa',
        'new' => 'Nueva',
        'learning' => 'Aprendiendo',
        'exclude' => 'Excluir',
    ];
}

function is_submitted_stage(string $stage): bool
{
    return !in_array($stage, ['discovered', 'selected', 'preparing'], true);
}

function sync_day_logged_applications(PDO $pdo, ?int $dayNumber): void
{
    if ($dayNumber === null || $dayNumber < 1 || $dayNumber > 100) {
        return;
    }
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM applications
         WHERE day_number = :day
           AND stage NOT IN ('discovered','selected','preparing')"
    );
    $stmt->execute([':day' => $dayNumber]);
    $count = (int) $stmt->fetchColumn();
    $upd = $pdo->prepare(
        'UPDATE day_plans SET applications_logged = :c WHERE day_number = :day'
    );
    $upd->execute([':c' => $count, ':day' => $dayNumber]);
}

function ensure_offer_score(PDO $pdo, int $applicationId): void
{
    $check = $pdo->prepare('SELECT id FROM offer_scores WHERE application_id = ?');
    $check->execute([$applicationId]);
    if ($check->fetch()) {
        return;
    }
    $ins = $pdo->prepare('INSERT INTO offer_scores (application_id) VALUES (?)');
    $ins->execute([$applicationId]);
}

function flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Ideal de ranking (100 pts): full remoto desde Argentina + sueldo excelente + horario flexible.
 * Excelente: ARS 7.000.000 / USD 10.000 mensuales (según playbook).
 *
 * @return array{score:float,breakdown:array<string,float>,label:string}
 */
function compute_offer_ranking(array $app): array
{
    $o = is_array($app['offer'] ?? null) ? $app['offer'] : [];
    $market = (string) ($app['market'] ?? 'ar');
    $eligible = (int) ($app['location_eligible'] ?? 0) === 1;
    $currency = strtoupper((string) ($o['currency'] ?? $app['currency'] ?? ''));
    $comp = $o['total_comp_monthly'] ?? $app['salary_max'] ?? $app['salary_min'] ?? null;
    $comp = $comp !== null && $comp !== '' ? (float) $comp : null;

    $remote = strtolower(trim((string) ($o['remote_policy'] ?? '')));
    $scheduleSel = strtolower(trim((string) ($o['schedule_type'] ?? '')));
    $scheduleScore = max(0, min(10, (int) ($o['schedule_score'] ?? 0)));
    $fit = max(0, min(10, (int) ($o['role_fit_score'] ?? 0)));
    $growth = max(0, min(10, (int) ($o['growth_score'] ?? 0)));
    $culture = max(0, min(10, (int) ($o['culture_score'] ?? 0)));
    $risk = max(0, min(10, (int) ($o['risk_score'] ?? 0)));
    // Auto-fill compensation_score from money if blank but keep for display
    $compManual = max(0, min(10, (int) ($o['compensation_score'] ?? 0)));

    // --- Compensación (0–35) vs excelente ---
    $excellent = match ($currency) {
        'ARS' => 7_000_000.0,
        'USD' => 10_000.0,
        'EUR' => 9_000.0,
        default => $market === 'ar' ? 7_000_000.0 : 10_000.0,
    };
    if ($comp !== null && $comp > 0) {
        $compPts = min(35.0, ($comp / $excellent) * 35.0);
    } elseif ($compManual > 0) {
        $compPts = ($compManual / 10) * 35.0;
    } else {
        $compPts = 0.0;
    }

    // --- Remoto AR (0–25) ---
    // Ideal: full remote + elegible desde Argentina
    $remotePts = 0.0;
    if (
        in_array($remote, ['full_remote', 'remote', 'full remote', '100% remote', 'full-remote'], true)
        || (str_contains($remote, 'full') && str_contains($remote, 'remote'))
    ) {
        $remotePts = $eligible || $market === 'ar' ? 25.0 : 16.0;
    } elseif (in_array($remote, ['hybrid', 'híbrido', 'hibrido'], true) || str_contains($remote, 'hybrid') || str_contains($remote, 'híbrid')) {
        $remotePts = $eligible || $market === 'ar' ? 12.0 : 7.0;
    } elseif (in_array($remote, ['onsite', 'office', 'presencial'], true) || str_contains($remote, 'onsite') || str_contains($remote, 'office')) {
        $remotePts = 2.0;
    } elseif ($remote !== '') {
        $remotePts = 8.0;
    } elseif ($eligible && $market === 'intl') {
        $remotePts = 10.0;
    }

    // --- Horario flexible (0–15) ---
    // Ideal: flexible / async-friendly
    if (in_array($scheduleSel, ['flexible', 'async', 'very_flexible'], true) || str_contains($scheduleSel, 'flex')) {
        $schedulePts = 15.0;
    } elseif (in_array($scheduleSel, ['standard', 'core_hours'], true)) {
        $schedulePts = 8.0;
    } elseif (in_array($scheduleSel, ['strict', 'oncall_heavy', 'fixed'], true)) {
        $schedulePts = 3.0;
    } else {
        $schedulePts = ($scheduleScore / 10) * 15.0;
    }

    // --- Calidad subjetiva fit/growth/culture (0–15) ---
    $qualityPts = (($fit + $growth + $culture) / 30) * 15.0;

    // --- Riesgo (−15 máx) ---
    $riskPenalty = ($risk / 10) * 15.0;

    $raw = $compPts + $remotePts + $schedulePts + $qualityPts - $riskPenalty;
    $score = max(0.0, min(100.0, round($raw, 1)));

    $label = match (true) {
        $score >= 85 => 'Ideal / cerca del techo',
        $score >= 70 => 'Muy buena',
        $score >= 55 => 'Competitive',
        $score >= 40 => 'Aceptable con trade-offs',
        default => 'Lejos del ideal',
    };

    return [
        'score' => $score,
        'label' => $label,
        'breakdown' => [
            'compensacion' => round($compPts, 1),
            'remoto_ar' => round($remotePts, 1),
            'horario' => round($schedulePts, 1),
            'calidad' => round($qualityPts, 1),
            'riesgo' => round(-$riskPenalty, 1),
        ],
    ];
}

function remote_policy_options(): array
{
    return [
        'full_remote' => 'Full remoto',
        'hybrid' => 'Híbrido',
        'onsite' => 'Presencial',
        'unknown' => 'No definido',
    ];
}

function schedule_type_options(): array
{
    return [
        'flexible' => 'Flexible / async',
        'core_hours' => 'Core hours',
        'standard' => 'Horario estándar',
        'strict' => 'Estricto / poco flexible',
        'oncall_heavy' => 'On-call pesado',
    ];
}

function write_data_file(string $path, string $contents): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("No se pudo crear directorio: $dir");
    }
    if (is_file($path) && !is_writable($path)) {
        @chmod($path, 0666);
    }
    if (is_file($path) && !is_writable($path)) {
        @unlink($path);
    }
    $tmp = $path . '.tmp.' . getmypid();
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
        throw new RuntimeException("No se pudo escribir: $path");
    }
    @chmod($tmp, 0666);
    if (!@rename($tmp, $path)) {
        @unlink($path);
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("No se pudo reemplazar: $path");
        }
    }
    @chmod($path, 0666);
}

function null_if_blank(?string $value): ?string
{
    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function uploads_dir(): string
{
    $config = require __DIR__ . '/../config.php';
    $dir = $config['paths']['uploads'] . '/documents';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}

function detect_format_from_upload(string $filename, string $mime): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $map = [
        'docx' => 'docx',
        'pdf' => 'pdf',
        'txt' => 'txt',
        'md' => 'md',
    ];
    if (isset($map[$ext])) {
        return $map[$ext];
    }
    if (str_contains($mime, 'pdf')) {
        return 'pdf';
    }
    if (str_contains($mime, 'word') || str_contains($mime, 'officedocument')) {
        return 'docx';
    }
    if (str_contains($mime, 'text/plain')) {
        return 'txt';
    }
    return null;
}
