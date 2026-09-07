<?php

declare(strict_types=1);

/** @var array $user */
$userId = (int) ($user['id'] ?? 0);
$apps = load_applications_for_user($userId);
$stats = application_stats_for_user($userId);
$campaign = campaign_settings_for_user($userId);
$notes = load_interview_notes_for_user($userId);
$offers = load_offers_for_user($userId);

$today = date('Y-m-d');
$target = (int) $campaign['target_applications'];
$runStart = (string) $campaign['run_started_on'];
$runDays = run_day_count($runStart, $today);
$targets = [
    'ideal_comp_ars' => (float) $campaign['ideal_comp_ars'],
    'ideal_comp_usd' => (float) $campaign['ideal_comp_usd'],
    'ideal_comp_eur' => (float) $campaign['ideal_comp_eur'],
    'ideal_remote' => (string) ($campaign['ideal_remote'] ?? 'full_remote'),
    'ideal_schedule' => (string) ($campaign['ideal_schedule'] ?? 'flexible'),
    'ideal_require_ar' => (int) ($campaign['ideal_require_ar'] ?? 1),
    'weight_comp' => (int) ($campaign['weight_comp'] ?? 35),
    'weight_remote' => (int) ($campaign['weight_remote'] ?? 25),
    'weight_schedule' => (int) ($campaign['weight_schedule'] ?? 15),
    'weight_quality' => (int) ($campaign['weight_quality'] ?? 15),
    'weight_risk' => (int) ($campaign['weight_risk'] ?? 15),
];

function metrics_nonzero(array $items): array
{
    return array_values(array_filter($items, static fn ($x) => (float) ($x['value'] ?? 0) > 0));
}

function metrics_svg_line(array $values, array $labels = [], string $stroke = '#E89A00', string $gid = 'g'): string
{
    $w = 640;
    $h = 200;
    $padX = 28;
    $padY = 28;
    $n = count($values);
    if ($n < 1) {
        return '';
    }
    $max = max(1, ...array_map('floatval', $values));
    $points = [];
    for ($i = 0; $i < $n; $i++) {
        $x = $padX + ($n === 1 ? ($w - 2 * $padX) / 2 : $i * (($w - 2 * $padX) / max(1, $n - 1)));
        $y = $h - $padY - (((float) $values[$i]) / $max) * ($h - 2 * $padY);
        $points[] = [round($x, 1), round($y, 1)];
    }
    $poly = implode(' ', array_map(static fn ($p) => $p[0] . ',' . $p[1], $points));
    $area = $padX . ',' . ($h - $padY) . ' ' . $poly . ' ' . ($w - $padX) . ',' . ($h - $padY);
    $html = '<div class="chart-frame"><svg viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none" class="chart-svg">';
    $html .= '<defs><linearGradient id="' . e($gid) . '" x1="0" y1="0" x2="0" y2="1">';
    $html .= '<stop offset="0%" stop-color="' . e($stroke) . '" stop-opacity="0.35"/><stop offset="100%" stop-color="' . e($stroke) . '" stop-opacity="0"/>';
    $html .= '</linearGradient></defs>';
    for ($g = 0; $g < 4; $g++) {
        $gy = $padY + $g * (($h - 2 * $padY) / 3);
        $html .= '<line x1="' . $padX . '" y1="' . $gy . '" x2="' . ($w - $padX) . '" y2="' . $gy . '" class="chart-grid"/>';
    }
    $html .= '<polygon points="' . e($area) . '" fill="url(#' . e($gid) . ')"/>';
    $html .= '<polyline points="' . e($poly) . '" fill="none" stroke="' . e($stroke) . '" stroke-width="3.2" stroke-linejoin="round" stroke-linecap="round"/>';
    foreach ($points as $p) {
        $html .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="4.5" fill="#FFF9F2" stroke="' . e($stroke) . '" stroke-width="2.5"/>';
    }
    $html .= '</svg>';
    if ($labels) {
        $html .= '<div class="chart-axis">';
        foreach ($labels as $lab) {
            $html .= '<span>' . e((string) $lab) . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function metrics_svg_bars(array $items, string $color = '#2B6FDB'): string
{
    $items = array_values($items);
    if (!$items) {
        return '<p class="muted" style="margin:0">Sin datos.</p>';
    }
    $max = max(1, ...array_map(static fn ($x) => (float) $x['value'], $items));
    $n = count($items);
    $w = max(320, $n * 52);
    $h = 210;
    $padX = 24;
    $padY = 28;
    $gap = 10;
    $barW = max(12, (($w - 2 * $padX) - ($n - 1) * $gap) / $n);
    $html = '<div class="chart-frame"><svg viewBox="0 0 ' . $w . ' ' . $h . '" class="chart-svg chart-svg--bars">';
    for ($g = 0; $g < 4; $g++) {
        $gy = $padY + $g * (($h - 2 * $padY - 28) / 3);
        $html .= '<line x1="' . $padX . '" y1="' . $gy . '" x2="' . ($w - $padX) . '" y2="' . $gy . '" class="chart-grid"/>';
    }
    foreach ($items as $i => $it) {
        $val = (float) $it['value'];
        $bh = ($val / $max) * ($h - 2 * $padY - 28);
        $x = $padX + $i * ($barW + $gap);
        $y = $h - $padY - 28 - $bh;
        $c = $it['color'] ?? $color;
        $html .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barW . '" height="' . max(2, $bh) . '" fill="' . e((string) $c) . '"/>';
        $html .= '<text x="' . ($x + $barW / 2) . '" y="' . max(14, $y - 8) . '" text-anchor="middle" class="bar-val">' . e((string) (int) $val) . '</text>';
        $lab = (string) $it['label'];
        $short = mb_strlen($lab) > 8 ? mb_substr($lab, 0, 7) . '…' : $lab;
        $html .= '<text x="' . ($x + $barW / 2) . '" y="' . ($h - 10) . '" text-anchor="middle" class="bar-lab">' . e($short) . '</text>';
    }
    $html .= '</svg></div>';
    return $html;
}

function metrics_pie(array $items, string $titleHole = ''): string
{
    $items = metrics_nonzero($items);
    if (!$items) {
        return '<p class="muted" style="margin:0">Sin datos.</p>';
    }
    $total = array_sum(array_map(static fn ($x) => (float) $x['value'], $items));
    $palette = chart_palette();
    $parts = [];
    $acc = 0.0;
    foreach ($items as $i => $it) {
        $pct = ((float) $it['value'] / max(0.0001, $total)) * 100;
        $color = $it['color'] ?? $palette[$i % count($palette)];
        $parts[] = $color . ' ' . $acc . '% ' . ($acc + $pct) . '%';
        $acc += $pct;
    }
    $html = '<div class="pie-wrap"><div class="pie" style="background:conic-gradient(' . e(implode(', ', $parts)) . ')">';
    $html .= '<div class="pie__hole"><strong>' . e((string) (int) $total) . '</strong>';
    if ($titleHole !== '') {
        $html .= '<span>' . e($titleHole) . '</span>';
    }
    $html .= '</div></div><ul class="pie-legend">';
    foreach ($items as $i => $it) {
        $color = $it['color'] ?? $palette[$i % count($palette)];
        $pct = round(((float) $it['value'] / max(0.0001, $total)) * 100);
        $html .= '<li><i style="background:' . e((string) $color) . '"></i>' . e((string) $it['label']) . ' <b>' . e((string) (int) $it['value']) . '</b> <em>' . e((string) $pct) . '%</em></li>';
    }
    $html .= '</ul></div>';
    return $html;
}

$counts = $stats['counts'] ?? [];
$submitted = (int) ($stats['submitted'] ?? 0);
$todayCount = (int) ($stats['today'] ?? 0);
$overdue = (int) ($stats['overdue'] ?? 0);
$historialDays = (int) ($stats['days_active'] ?? 0);
$interviewApps = (int) ($stats['interviews'] ?? 0);
$offerCount = (int) ($stats['offers'] ?? 0);
$rejected = (int) ($stats['rejected'] ?? 0);
$accepted = (int) ($counts['accepted'] ?? 0);

$runApps = 0;
$runDates = [];
$byStage = [];
$byPlatform = [];
$weekly = [];
$salaries = ['ARS' => [], 'USD' => [], 'EUR' => []];

foreach ($apps as $app) {
    $stage = (string) ($app['stage'] ?? 'applied');
    $byStage[$stage] = ($byStage[$stage] ?? 0) + 1;
    $plat = trim((string) ($app['platform'] ?? '')) ?: 'Sin portal';
    $byPlatform[$plat] = ($byPlatform[$plat] ?? 0) + 1;

    $d = application_date_of($app);
    if ($d !== '' && $d >= $runStart) {
        $runApps++;
        $runDates[$d] = true;
    }
    if ($d !== '') {
        $ts = strtotime($d);
        if ($ts) {
            $key = date('o-\WW', $ts);
            $weekly[$key] = ($weekly[$key] ?? 0) + 1;
        }
    }
}

foreach ($offers as $offer) {
    $o = is_array($offer['offer'] ?? null) ? $offer['offer'] : [];
    $comp = $o['total_comp_monthly'] ?? null;
    $cur = strtoupper((string) ($o['currency'] ?? ''));
    if ($comp !== null && $comp !== '' && (float) $comp > 0 && isset($salaries[$cur])) {
        $salaries[$cur][] = (float) $comp;
    }
}

$progressPct = $target > 0 ? round(($submitted / $target) * 100, 1) : 0;
$missing = max(0, $target - $submitted);
$runActiveDays = count($runDates);
$pace = $runDays > 0 ? round($runApps / max(1, $runDays), 1) : ($runApps > 0 ? (float) $runApps : 0.0);
$rejectRate = $submitted > 0 ? round(($rejected / $submitted) * 100, 1) : 0;
$convInterview = $submitted > 0 ? round(($interviewApps / $submitted) * 100, 1) : 0;
$convOffer = $interviewApps > 0 ? round(($offerCount / $interviewApps) * 100, 1) : 0;
$convAccept = $offerCount > 0 ? round(($accepted / $offerCount) * 100, 1) : 0;

$funnel = [
    ['label' => 'Postulado', 'value' => (int) ($counts['applied'] ?? 0), 'color' => stage_color('applied')],
    ['label' => 'Follow-up', 'value' => (int) ($counts['follow_up'] ?? 0), 'color' => stage_color('follow_up')],
    ['label' => 'Entrevistas', 'value' => $interviewApps, 'color' => stage_color('recruiter_screen')],
    ['label' => 'Ofertas', 'value' => $offerCount, 'color' => stage_color('offer')],
    ['label' => 'Rechazadas', 'value' => $rejected, 'color' => stage_color('rejected')],
];
$funnelMax = max(1, ...array_column($funnel, 'value'));

$runWindow = min(30, max(0, $runDays));
$runBars = [];
for ($i = $runWindow; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime($today . " -{$i} days"));
    if ($d < $runStart) {
        continue;
    }
    $runBars[$d] = 0;
}
if (!$runBars) {
    $runBars[$today] = 0;
}
foreach ($apps as $app) {
    $d = application_date_of($app);
    if (isset($runBars[$d])) {
        $runBars[$d]++;
    }
}
$runBarItems = [];
foreach ($runBars as $d => $n) {
    $runBarItems[] = [
        'label' => date('d/m', strtotime((string) $d)),
        'value' => $n,
        'color' => $d === $today ? stage_color('technical') : stage_color('follow_up'),
    ];
}

ksort($weekly);
$weekLabels = array_keys($weekly);
$weekValues = array_values($weekly);
$cum = 0;
$weekCum = [];
$weekAxis = [];
foreach ($weekValues as $i => $v) {
    $cum += $v;
    $weekCum[] = $cum;
    $weekAxis[] = 'S' . substr((string) $weekLabels[$i], -2);
}
$weekBarItems = [];
foreach ($weekLabels as $i => $lab) {
    $weekBarItems[] = ['label' => 'S' . substr((string) $lab, -2), 'value' => $weekValues[$i], 'color' => stage_color('applied')];
}

$stagePie = [];
foreach (stage_labels() as $key => $label) {
    if (in_array($key, ['discovered', 'selected', 'preparing', 'leadership', 'closed'], true)) {
        continue;
    }
    if (($byStage[$key] ?? 0) > 0) {
        $stagePie[] = ['label' => $label, 'value' => $byStage[$key], 'color' => stage_color($key)];
    }
}

arsort($byPlatform);
$platformItems = [];
$colors = chart_palette();
$pi = 0;
foreach (array_slice($byPlatform, 0, 8, true) as $lab => $val) {
    $platformItems[] = ['label' => $lab, 'value' => $val, 'color' => $colors[$pi++ % count($colors)]];
}

$noteOutcomes = [];
$outcomeLabels = [
    'pending' => 'Pendiente',
    'passed' => 'Avanzó',
    'rejected' => 'Rechazada',
    'ghosted' => 'Sin respuesta',
    'offer' => 'Oferta',
    'other' => 'Otro',
];
foreach ($notes as $note) {
    $out = (string) ($note['outcome'] ?? 'pending');
    $noteOutcomes[$out] = ($noteOutcomes[$out] ?? 0) + 1;
}
$notePie = [];
$outcomeColors = [
    'pending' => '#E89A00',
    'passed' => '#1F9B4A',
    'rejected' => '#C71F2A',
    'ghosted' => '#6B3FDB',
    'offer' => '#2B6FDB',
    'other' => '#1A9B8A',
];
foreach ($outcomeLabels as $key => $label) {
    if (($noteOutcomes[$key] ?? 0) > 0) {
        $notePie[] = ['label' => $label, 'value' => $noteOutcomes[$key], 'color' => $outcomeColors[$key] ?? '#8A7464'];
    }
}

$ranked = [];
foreach ($offers as $app) {
    $rank = compute_offer_ranking($app, $targets);
    $ranked[] = [
        'company' => (string) ($app['company'] ?? ''),
        'score' => $rank['score'],
        'label' => $rank['label'],
    ];
}
usort($ranked, static fn ($a, $b) => $b['score'] <=> $a['score']);
$rankBars = [];
$rankColors = chart_palette();
foreach (array_slice($ranked, 0, 8) as $i => $r) {
    $rankBars[] = [
        'label' => mb_substr((string) $r['company'], 0, 10),
        'value' => $r['score'],
        'color' => $rankColors[$i % count($rankColors)],
    ];
}

$avg = static function (array $xs): ?float {
    return $xs ? array_sum($xs) / count($xs) : null;
};

$goalReturn = 'metricas';
?>
<div class="page-head">
  <div>
    <h1>Métricas</h1>
    <p class="subtitle">Objetivo, campaña actual, embudo y conversión. Todo sale del diario, no del plan de 100 días.</p>
  </div>
</div>

<?php require __DIR__ . '/../goal_panel.php'; ?>

<section class="metrics-grid">
  <article class="metric-card metric-card--gold">
    <span class="metric-card__label">Objetivo</span>
    <strong class="metric-card__value"><?= e((string) $submitted) ?>/<?= e((string) $target) ?></strong>
    <span class="metric-card__hint"><?= e((string) $progressPct) ?>% · faltan <?= e((string) $missing) ?></span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Esta campaña</span>
    <strong class="metric-card__value"><?= e((string) $runApps) ?></strong>
    <span class="metric-card__hint"><?= e((string) $runDays) ?> días · <?= e((string) $pace) ?> / día</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Hoy</span>
    <strong class="metric-card__value"><?= e((string) $todayCount) ?></strong>
    <span class="metric-card__hint"><?= e((string) $runActiveDays) ?> días con envío</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Entrevistas</span>
    <strong class="metric-card__value"><?= e((string) $interviewApps) ?></strong>
    <span class="metric-card__hint"><?= e((string) $convInterview) ?>% de enviadas</span>
  </article>
  <article class="metric-card">
    <span class="metric-card__label">Ofertas</span>
    <strong class="metric-card__value"><?= e((string) $offerCount) ?></strong>
    <span class="metric-card__hint"><?= e((string) $convOffer) ?>% de entrevistas</span>
  </article>
  <article class="metric-card <?= $overdue > 0 ? 'metric-card--warn' : '' ?>">
    <span class="metric-card__label">Follow-ups</span>
    <strong class="metric-card__value"><?= e((string) $overdue) ?></strong>
    <span class="metric-card__hint"><?= e((string) $rejectRate) ?>% rechazadas</span>
  </article>
</section>

<div class="metrics-board">
  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head"><h2>Embudo</h2></div>
    <div class="funnel">
      <?php foreach ($funnel as $row):
          $w = round(($row['value'] / $funnelMax) * 100);
      ?>
        <div class="funnel-row">
          <div class="funnel-row__label"><?= e($row['label']) ?></div>
          <div class="funnel-row__track"><div class="funnel-row__fill" style="width:<?= e((string) max($row['value'] > 0 ? 8 : 0, $w)) ?>%;background:<?= e($row['color']) ?>"></div></div>
          <div class="funnel-row__n"><?= e((string) $row['value']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Conversión</h2></div>
    <div class="conv-stack">
      <?php foreach ([
          ['Entrevista / enviada', $convInterview, stage_color('recruiter_screen')],
          ['Oferta / entrevista', $convOffer, stage_color('offer')],
          ['Aceptada / oferta', $convAccept, stage_color('accepted')],
          ['Rechazo / enviada', $rejectRate, stage_color('rejected')],
      ] as [$lab, $pct, $col]): ?>
        <div class="conv-row">
          <div class="conv-row__top"><span><?= e($lab) ?></span><strong><?= e((string) $pct) ?>%</strong></div>
          <div class="conv-row__track"><div class="conv-row__fill" style="width:<?= e((string) min(100, $pct)) ?>%;background:<?= e($col) ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Estados</h2></div>
    <?= $stagePie ? metrics_pie($stagePie, 'quests') : '<p class="muted" style="margin:0">Todavía no hay envíos.</p>' ?>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head">
      <h2>Envíos de esta campaña</h2>
      <span class="count">desde <?= e(format_display_date($runStart)) ?></span>
    </div>
    <?= $runBarItems ? metrics_svg_bars($runBarItems, stage_color('follow_up')) : '<p class="muted" style="margin:0">Reiniciá o cargá un envío para ver la campaña.</p>' ?>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head">
      <h2>Historial acumulado</h2>
      <span class="count"><?= e((string) $submitted) ?> en <?= e((string) $historialDays) ?> días</span>
    </div>
    <?php if ($weekCum): ?>
      <?= metrics_svg_line($weekCum, $weekAxis, stage_color('technical'), 'areaHist') ?>
    <?php else: ?>
      <p class="muted" style="margin:0">Cargá fechas en el diario para ver la curva.</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Por semana</h2></div>
    <?= $weekBarItems ? metrics_svg_bars($weekBarItems) : '<p class="muted" style="margin:0">Sin series semanales.</p>' ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Portales</h2></div>
    <?= $platformItems ? metrics_svg_bars($platformItems) : '<p class="muted" style="margin:0">Sin portales cargados en el diario.</p>' ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Charlas</h2></div>
    <?php if ($notePie): ?>
      <?= metrics_pie($notePie, 'notas') ?>
    <?php else: ?>
      <p class="muted" style="margin:0">Todavía no hay notas de entrevista.</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head">
      <h2>Techo vs ofertas</h2>
      <a href="<?= e(url('/index.php?tab=comparador')) ?>">Comparar</a>
    </div>
    <div class="salary-cards">
      <?php
        $salaryColors = ['ARS' => stage_color('technical'), 'USD' => stage_color('applied'), 'EUR' => stage_color('offer')];
        foreach (['ARS' => $targets['ideal_comp_ars'], 'USD' => $targets['ideal_comp_usd'], 'EUR' => $targets['ideal_comp_eur']] as $cur => $ideal):
          $avgVal = $avg($salaries[$cur]);
          $pct = $avgVal !== null ? min(100, round(($avgVal / max(1, $ideal)) * 100)) : 0;
      ?>
        <div class="salary-card">
          <div class="metric-card__label"><?= e($cur) ?></div>
          <strong><?= $avgVal !== null ? e(number_format($avgVal, 0, ',', '.')) : '—' ?></strong>
          <span class="muted">techo <?= e(number_format($ideal, 0, ',', '.')) ?></span>
          <div class="conv-row__track"><div class="conv-row__fill" style="width:<?= e((string) $pct) ?>%;background:<?= e($salaryColors[$cur]) ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head">
      <h2>Ranking de ofertas</h2>
      <a class="btn btn-sm" href="<?= e(url('/index.php?tab=comparador')) ?>">Abrir comparador</a>
    </div>
    <?php if ($rankBars): ?>
      <?= metrics_svg_bars($rankBars) ?>
      <ol class="rank-list">
        <?php foreach (array_slice($ranked, 0, 6) as $i => $r): ?>
          <li>
            <span class="rank-list__n">#<?= e((string) ($i + 1)) ?></span>
            <span><?= e($r['company']) ?></span>
            <strong><?= e(number_format((float) $r['score'], 1)) ?></strong>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php else: ?>
      <p class="muted" style="margin:0">Pasá una quest a Oferta y cargá el sueldo en Comparar.</p>
    <?php endif; ?>
  </section>
</div>
