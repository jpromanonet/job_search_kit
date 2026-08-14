<?php

declare(strict_types=1);

require_once __DIR__ . '/../storage.php';

$days = load_plan_days();
$apps = load_applications();
$config = require __DIR__ . '/../../config.php';

/** @return list<array{label:string,value:float|int,color?:string}> */
function metrics_nonzero(array $items): array
{
    return array_values(array_filter($items, static fn ($x) => (float) ($x['value'] ?? 0) > 0));
}

function metrics_svg_line(array $values, array $labels = [], string $stroke = '#0f766e', string $gid = 'g'): string
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
    // grid
    for ($g = 0; $g < 4; $g++) {
        $gy = $padY + $g * (($h - 2 * $padY) / 3);
        $html .= '<line x1="' . $padX . '" y1="' . $gy . '" x2="' . ($w - $padX) . '" y2="' . $gy . '" class="chart-grid"/>';
    }
    $html .= '<polygon points="' . e($area) . '" fill="url(#' . e($gid) . ')"/>';
    $html .= '<polyline points="' . e($poly) . '" fill="none" stroke="' . e($stroke) . '" stroke-width="3.2" stroke-linejoin="round" stroke-linecap="round"/>';
    foreach ($points as $p) {
        $html .= '<circle cx="' . $p[0] . '" cy="' . $p[1] . '" r="4.5" fill="#fff" stroke="' . e($stroke) . '" stroke-width="2.5"/>';
    }
    $html .= '</svg>';
    if ($labels) {
        $html .= '<div class="chart-axis">';
        foreach ($labels as $i => $lab) {
            $html .= '<span title="' . e((string) $lab) . '">' . e((string) $lab) . '</span>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

function metrics_svg_bars(array $items, string $color = '#0f766e'): string
{
    $items = array_values($items);
    if (!$items) {
        return '<p class="muted" style="margin:0">Sin datos.</p>';
    }
    $max = max(1, ...array_map(static fn ($x) => (float) $x['value'], $items));
    $n = count($items);
    $w = max(320, $n * 56);
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
        $html .= '<rect x="' . $x . '" y="' . $y . '" width="' . $barW . '" height="' . max(2, $bh) . '" rx="8" fill="' . e((string) $c) . '" class="bar-anim"/>';
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
    $palette = ['#0f766e', '#175cd3', '#b54708', '#067647', '#7c3aed', '#be185d', '#0e7490', '#a16207'];
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

$contentDays = 0;
$daysDone = 0;
$daysInProgress = 0;
$daysBlocked = 0;
$daysMissed = 0;
$daysNotStarted = 0;
$appsLoggedPlan = 0;
$li = 0;
$xPosts = 0;
$ig = 0;
$articles = 0;
$contentCum = [];
$appsLoggedCum = [];
$phaseDone = ['build' => 0, 'high_volume' => 0, 'finish' => 0];
$phaseTotal = ['build' => 0, 'high_volume' => 0, 'finish' => 0];

$runContent = 0;
$runApps = 0;
foreach ($days as $d) {
    $n = (int) $d['day_number'];
    $phase = (string) ($d['phase'] ?? 'build');
    if (isset($phaseTotal[$phase])) {
        $phaseTotal[$phase]++;
    }
    if (!empty($d['article_url'])) {
        $articles++;
    }
    if ((int) ($d['linkedin_posted'] ?? 0)) {
        $li++;
    }
    if ((int) ($d['x_posted'] ?? 0)) {
        $xPosts++;
    }
    if ((int) ($d['instagram_done'] ?? 0)) {
        $ig++;
    }
    $hasContent = !empty($d['article_url'])
        || (int) ($d['linkedin_posted'] ?? 0)
        || (int) ($d['x_posted'] ?? 0)
        || (int) ($d['instagram_done'] ?? 0);
    if ($hasContent) {
        $contentDays++;
        $runContent++;
    }
    $st = $d['status'] ?? 'not_started';
    match ($st) {
        'done' => $daysDone++,
        'in_progress' => $daysInProgress++,
        'blocked' => $daysBlocked++,
        'missed' => $daysMissed++,
        default => $daysNotStarted++,
    };
    if ($st === 'done' && isset($phaseDone[$phase])) {
        $phaseDone[$phase]++;
    }
    $logged = (int) ($d['applications_logged'] ?? 0);
    $appsLoggedPlan += $logged;
    $runApps += $logged;
    if ($n % 5 === 0 || $n === 1 || $n === 100) {
        $contentCum[] = ['n' => $n, 'c' => $runContent, 'a' => $runApps];
    }
}

$byStage = [];
$byMarket = ['ar' => 0, 'intl' => 0];
$byPlatform = [];
$byFamily = [];
$byRemote = [];
$submitted = 0;
$interviews = 0;
$salArs = [];
$salUsd = [];
$weekly = [];
$weeklyAr = [];
$weeklyIntl = [];
$fitScores = [];
$fitBuckets = ['0–40' => 0, '41–60' => 0, '61–80' => 0, '81–100' => 0];

foreach ($apps as $app) {
    $stage = (string) ($app['stage'] ?? 'discovered');
    $byStage[$stage] = ($byStage[$stage] ?? 0) + 1;
    $m = $app['market'] ?? 'ar';
    if (isset($byMarket[$m])) {
        $byMarket[$m]++;
    }
    $plat = trim((string) ($app['platform'] ?? '')) ?: 'Sin portal';
    $byPlatform[$plat] = ($byPlatform[$plat] ?? 0) + 1;
    $fam = trim((string) ($app['role_family'] ?? '')) ?: 'Sin familia';
    // shorten family for charts
    $famShort = preg_replace('/\s*\/.*$/', '', $fam) ?: $fam;
    $byFamily[$famShort] = ($byFamily[$famShort] ?? 0) + 1;

    if (is_submitted_stage($stage)) {
        $submitted++;
    }
    if (in_array($stage, ['recruiter_screen', 'technical', 'leadership', 'final', 'offer', 'accepted'], true)) {
        $interviews++;
    }

    $o = is_array($app['offer'] ?? null) ? $app['offer'] : [];
    if (in_array($stage, ['offer', 'accepted'], true)) {
        $rp = (string) ($o['remote_policy'] ?? 'unknown');
        $rpLabel = remote_policy_options()[$rp] ?? ($rp !== '' ? $rp : 'No definido');
        $byRemote[$rpLabel] = ($byRemote[$rpLabel] ?? 0) + 1;
    }

    $comp = $o['total_comp_monthly'] ?? $app['salary_max'] ?? $app['salary_min'] ?? null;
    $cur = strtoupper((string) ($o['currency'] ?? $app['currency'] ?? ''));
    if ($comp !== null && $comp !== '' && (float) $comp > 0) {
        if ($cur === 'ARS') {
            $salArs[] = (float) $comp;
        } elseif ($cur === 'USD') {
            $salUsd[] = (float) $comp;
        }
    }
    if ($app['fit_score'] !== null && $app['fit_score'] !== '') {
        $fs = (int) $app['fit_score'];
        $fitScores[] = $fs;
        if ($fs <= 40) {
            $fitBuckets['0–40']++;
        } elseif ($fs <= 60) {
            $fitBuckets['41–60']++;
        } elseif ($fs <= 80) {
            $fitBuckets['61–80']++;
        } else {
            $fitBuckets['81–100']++;
        }
    }
    $date = $app['application_date'] ?? ($app['created_at'] ?? null);
    if ($date) {
        $ts = strtotime((string) $date);
        if ($ts) {
            $key = date('o-\WW', $ts);
            $weekly[$key] = ($weekly[$key] ?? 0) + 1;
            if ($m === 'ar') {
                $weeklyAr[$key] = ($weeklyAr[$key] ?? 0) + 1;
            } else {
                $weeklyIntl[$key] = ($weeklyIntl[$key] ?? 0) + 1;
            }
        }
    }
}

$offers = ($byStage['offer'] ?? 0) + ($byStage['accepted'] ?? 0);
$accepted = $byStage['accepted'] ?? 0;
$rejected = $byStage['rejected'] ?? 0;

$avg = static function (array $xs): ?float {
    return $xs ? array_sum($xs) / count($xs) : null;
};
$avgArs = $avg($salArs);
$avgUsd = $avg($salUsd);
$avgFit = $avg($fitScores);
$maxArs = $salArs ? max($salArs) : null;
$maxUsd = $salUsd ? max($salUsd) : null;

ksort($weekly);
$weekLabels = array_keys($weekly);
$weekValues = array_values($weekly);
$cum = 0;
$weekCum = [];
foreach ($weekValues as $v) {
    $cum += $v;
    $weekCum[] = $cum;
}
$weekAxis = array_map(static function ($lab, $i) use ($weekValues) {
    return substr((string) $lab, -2) . '·' . $weekValues[$i];
}, $weekLabels, array_keys($weekLabels));

$contentVsOffersMax = max(1, $contentDays, $offers, $interviews);
$ratio = $offers > 0 ? round($contentDays / $offers, 2) : ($contentDays > 0 ? null : 0);

$funnel = [
    'Descubierto' => ($byStage['discovered'] ?? 0) + ($byStage['selected'] ?? 0),
    'Preparando' => $byStage['preparing'] ?? 0,
    'Postulado' => $byStage['applied'] ?? 0,
    'Follow-up' => $byStage['follow_up'] ?? 0,
    'Entrevista' => ($byStage['recruiter_screen'] ?? 0) + ($byStage['technical'] ?? 0) + ($byStage['leadership'] ?? 0) + ($byStage['final'] ?? 0),
    'Oferta' => $offers,
];
$funnelMax = max(1, ...array_values($funnel));

$target = (int) $config['app']['target_applications'];
$progressPct = min(100, round(($submitted / max(1, $target)) * 100, 1));
$arTarget = (int) $config['app']['target_ar'];
$intlTarget = (int) $config['app']['target_intl'];

$convApply = $submitted > 0 ? round(($interviews / $submitted) * 100, 1) : 0;
$convOffer = $interviews > 0 ? round(($offers / $interviews) * 100, 1) : 0;
$convAccept = $offers > 0 ? round(($accepted / $offers) * 100, 1) : 0;

arsort($byPlatform);
$platformItems = [];
$colors = ['#0f766e', '#175cd3', '#b54708', '#067647', '#7c3aed', '#be185d', '#0e7490', '#a16207'];
$pi = 0;
foreach (array_slice($byPlatform, 0, 8, true) as $lab => $val) {
    $platformItems[] = ['label' => $lab, 'value' => $val, 'color' => $colors[$pi++ % count($colors)]];
}

arsort($byFamily);
$familyItems = [];
$fi = 0;
foreach (array_slice($byFamily, 0, 6, true) as $lab => $val) {
    $familyItems[] = ['label' => $lab, 'value' => $val, 'color' => $colors[$fi++ % count($colors)]];
}

$stagePie = [];
foreach (stage_labels() as $k => $lab) {
    if (($byStage[$k] ?? 0) > 0) {
        $stagePie[] = ['label' => $lab, 'value' => $byStage[$k]];
    }
}

$statusPie = metrics_nonzero([
    ['label' => 'Hechos', 'value' => $daysDone, 'color' => '#067647'],
    ['label' => 'En curso', 'value' => $daysInProgress, 'color' => '#175cd3'],
    ['label' => 'Sin empezar', 'value' => $daysNotStarted, 'color' => '#94a3b8'],
    ['label' => 'Bloqueados', 'value' => $daysBlocked, 'color' => '#b54708'],
    ['label' => 'Perdidos', 'value' => $daysMissed, 'color' => '#b42318'],
]);

$channelPie = metrics_nonzero([
    ['label' => 'Artículos', 'value' => $articles, 'color' => '#0f766e'],
    ['label' => 'LinkedIn', 'value' => $li, 'color' => '#175cd3'],
    ['label' => 'X', 'value' => $xPosts, 'color' => '#334155'],
    ['label' => 'Instagram', 'value' => $ig, 'color' => '#be185d'],
]);

$remotePie = [];
foreach ($byRemote as $lab => $val) {
    $remotePie[] = ['label' => $lab, 'value' => $val];
}

$fitBars = [];
foreach ($fitBuckets as $lab => $val) {
    $fitBars[] = ['label' => $lab, 'value' => $val];
}

$phaseBars = [];
foreach (['build' => 'Construcción', 'high_volume' => 'Ejecución', 'finish' => 'Cierre'] as $k => $lab) {
    $phaseBars[] = ['label' => $lab, 'value' => $phaseDone[$k], 'color' => match ($k) {
        'build' => '#175cd3',
        'high_volume' => '#0f766e',
        default => '#067647',
    }];
}

$contentLineVals = array_map(static fn ($r) => $r['c'], $contentCum);
$contentLineLabs = array_map(static fn ($r) => 'D' . $r['n'], $contentCum);
$appsLineVals = array_map(static fn ($r) => $r['a'], $contentCum);

$ranked = [];
foreach ($apps as $app) {
    if (!in_array($app['stage'] ?? '', ['offer', 'accepted'], true)) {
        continue;
    }
    $r = compute_offer_ranking($app);
    $ranked[] = [
        'company' => $app['company'] ?? '',
        'score' => $r['score'],
        'label' => $r['label'],
    ];
}
usort($ranked, static fn ($a, $b) => $b['score'] <=> $a['score']);
$rankBars = [];
foreach (array_slice($ranked, 0, 8) as $r) {
    $rankBars[] = ['label' => mb_substr((string) $r['company'], 0, 10), 'value' => $r['score'], 'color' => '#0f766e'];
}

$kpiTriple = [
    ['label' => 'Contenido', 'value' => $contentDays, 'color' => '#0f766e'],
    ['label' => 'Entrevistas+', 'value' => $interviews, 'color' => '#175cd3'],
    ['label' => 'Ofertas', 'value' => $offers, 'color' => '#b54708'],
];
?>
<div class="page-head">
  <div>
    <h1>Métricas</h1>
    <p class="subtitle">Dashboard visual de la campaña: crecimiento, contenido, pipeline y compensación.</p>
  </div>
</div>

<div class="stats metrics-hero">
  <div class="stat"><div class="stat-label">Contenido</div><div class="stat-value"><?= e((string) $contentDays) ?></div><div class="stat-meta">días publicados</div></div>
  <div class="stat"><div class="stat-label">Ofertas</div><div class="stat-value"><?= e((string) $offers) ?></div><div class="stat-meta"><?= e((string) $accepted) ?> aceptadas</div></div>
  <div class="stat"><div class="stat-label">Contenido / oferta</div><div class="stat-value"><?= $ratio === null ? '∞' : e((string) $ratio) ?></div><div class="stat-meta">eficiencia de marca</div></div>
  <div class="stat"><div class="stat-label">Postulaciones</div><div class="stat-value"><?= e((string) $submitted) ?><span class="stat-slash">/<?= e((string) $target) ?></span></div><div class="stat-meta"><?= e((string) $progressPct) ?>%</div></div>
  <div class="stat"><div class="stat-label">Días hechos</div><div class="stat-value"><?= e((string) $daysDone) ?><span class="stat-slash">/100</span></div><div class="stat-meta"><?= e((string) $daysInProgress) ?> en curso</div></div>
  <div class="stat"><div class="stat-label">Conv. entrevista</div><div class="stat-value"><?= e((string) $convApply) ?>%</div><div class="stat-meta">sobre enviadas</div></div>
</div>

<div class="metrics-grid">

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Contenido · entrevistas · ofertas</h2></div>
    <?= metrics_svg_bars($kpiTriple) ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Canales de contenido</h2></div>
    <?= metrics_pie($channelPie, 'posts') ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Estado de los 100 días</h2></div>
    <?= metrics_pie($statusPie, 'días') ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Mercado AR / Intl</h2></div>
    <?= metrics_pie([
        ['label' => 'Argentina', 'value' => $byMarket['ar'], 'color' => '#0f766e'],
        ['label' => 'Internacional', 'value' => $byMarket['intl'], 'color' => '#175cd3'],
    ], 'apps') ?>
    <div class="target-mini">
      <div><span>AR</span><strong><?= e((string) $byMarket['ar']) ?>/<?= e((string) $arTarget) ?></strong></div>
      <div><span>Intl</span><strong><?= e((string) $byMarket['intl']) ?>/<?= e((string) $intlTarget) ?></strong></div>
    </div>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head"><h2>Embudo del pipeline</h2></div>
    <div class="funnel funnel--pretty">
      <?php foreach ($funnel as $label => $n):
          $w = round(($n / $funnelMax) * 100);
      ?>
        <div class="funnel-row">
          <div class="funnel-row__label"><?= e($label) ?></div>
          <div class="funnel-row__track"><div class="funnel-row__fill" style="width:<?= e((string) max($n > 0 ? 8 : 0, $w)) ?>%"></div></div>
          <div class="funnel-row__n"><?= e((string) $n) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head">
      <h2>Crecimiento acumulado (semanal)</h2>
      <span class="count">total <?= e((string) $cum) ?></span>
    </div>
    <?php if ($weekCum): ?>
      <?= metrics_svg_line($weekCum, $weekAxis, '#0f766e', 'areaWeek') ?>
    <?php else: ?>
      <p class="muted" style="margin:0">Cargá fechas en Tracker para ver la curva.</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head"><h2>Postulaciones por semana</h2></div>
    <?php if ($weekValues): ?>
      <?php
        $weekBars = [];
        foreach ($weekLabels as $i => $lab) {
            $weekBars[] = ['label' => 'W' . substr((string) $lab, -2), 'value' => $weekValues[$i]];
        }
      ?>
      <?= metrics_svg_bars($weekBars, '#175cd3') ?>
    <?php else: ?>
      <p class="muted" style="margin:0">Sin series semanales todavía.</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head"><h2>Contenido acumulado por día de plan</h2></div>
    <?= metrics_svg_line($contentLineVals, $contentLineLabs, '#0f766e', 'areaContent') ?>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head"><h2>Apps logueadas en el plan (acumulado)</h2></div>
    <?= metrics_svg_line($appsLineVals, $contentLineLabs, '#175cd3', 'areaApps') ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Top portales</h2></div>
    <?= metrics_svg_bars($platformItems) ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Familias de rol</h2></div>
    <?= metrics_svg_bars($familyItems, '#7c3aed') ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Stages (torta)</h2></div>
    <?= metrics_pie($stagePie, 'apps') ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Fit score (barras)</h2></div>
    <?= metrics_svg_bars($fitBars, '#0e7490') ?>
    <?php if ($avgFit !== null): ?>
      <p class="muted metrics-caption">Promedio <?= e(number_format($avgFit, 1)) ?>%</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Fases del plan hechas</h2></div>
    <?= metrics_svg_bars($phaseBars) ?>
    <div class="phase-meta">
      <?php foreach (['build' => 'Build', 'high_volume' => 'Ejecución', 'finish' => 'Cierre'] as $k => $lab): ?>
        <span><?= e($lab) ?> <?= e((string) $phaseDone[$k]) ?>/<?= e((string) $phaseTotal[$k]) ?></span>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Modalidad de ofertas</h2></div>
    <?= $remotePie ? metrics_pie($remotePie, 'ofertas') : '<p class="muted" style="margin:0">Sin ofertas con modalidad.</p>' ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Conversión</h2></div>
    <div class="conv-stack">
      <?php foreach ([
          ['Entrevista / enviada', $convApply, '#175cd3'],
          ['Oferta / entrevista', $convOffer, '#0f766e'],
          ['Aceptada / oferta', $convAccept, '#067647'],
      ] as [$lab, $pct, $col]): ?>
        <div class="conv-row">
          <div class="conv-row__top"><span><?= e($lab) ?></span><strong><?= e((string) $pct) ?>%</strong></div>
          <div class="conv-row__track"><div class="conv-row__fill" style="width:<?= e((string) min(100, $pct)) ?>%;background:<?= e($col) ?>"></div></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Avance a <?= e((string) $target) ?></h2></div>
    <div class="progress-ring" style="--p:<?= e((string) $progressPct) ?>"><strong><?= e((string) $progressPct) ?>%</strong></div>
    <p class="muted metrics-caption" style="text-align:center"><?= e((string) $submitted) ?> enviadas · faltan <?= e((string) max(0, $target - $submitted)) ?></p>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Compensación</h2></div>
    <div class="salary-cards">
      <div class="salary-card">
        <div class="stat-label">Promedio ARS</div>
        <div class="salary-card__value"><?= $avgArs !== null ? e(number_format($avgArs, 0, ',', '.')) : '—' ?></div>
        <div class="muted">máx <?= $maxArs !== null ? e(number_format($maxArs, 0, ',', '.')) : '—' ?> · n=<?= e((string) count($salArs)) ?></div>
        <?php if ($avgArs !== null): ?><div class="mini-track"><div class="mini-fill" style="width:<?= e((string) min(100, round(($avgArs / 7_000_000) * 100))) ?>%"></div></div><?php endif; ?>
      </div>
      <div class="salary-card">
        <div class="stat-label">Promedio USD</div>
        <div class="salary-card__value"><?= $avgUsd !== null ? e(number_format($avgUsd, 0, ',', '.')) : '—' ?></div>
        <div class="muted">máx <?= $maxUsd !== null ? e(number_format($maxUsd, 0, ',', '.')) : '—' ?> · n=<?= e((string) count($salUsd)) ?></div>
        <?php if ($avgUsd !== null): ?><div class="mini-track"><div class="mini-fill" style="width:<?= e((string) min(100, round(($avgUsd / 10_000) * 100))) ?>%"></div></div><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel metrics-card metrics-card--wide">
    <div class="panel-head">
      <h2>Scores de ranking (ofertas)</h2>
      <a class="btn btn-sm" href="<?= e(url('/index.php?tab=comparador')) ?>">Comparador</a>
    </div>
    <?php if ($rankBars): ?>
      <?= metrics_svg_bars($rankBars) ?>
    <?php else: ?>
      <p class="muted" style="margin:0">Todavía no hay ofertas rankeadas.</p>
    <?php endif; ?>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Contenido vs ofertas</h2></div>
    <div class="compare-chart">
      <div class="compare-col">
        <div class="compare-col__value"><?= e((string) $contentDays) ?></div>
        <div class="compare-col__bar" style="--h:<?= e((string) round(($contentDays / $contentVsOffersMax) * 100)) ?>"></div>
        <div class="compare-col__label">Contenido</div>
      </div>
      <div class="compare-col">
        <div class="compare-col__value"><?= e((string) $interviews) ?></div>
        <div class="compare-col__bar compare-col__bar--blue" style="--h:<?= e((string) round(($interviews / $contentVsOffersMax) * 100)) ?>"></div>
        <div class="compare-col__label">Entrevistas</div>
      </div>
      <div class="compare-col compare-col--accent">
        <div class="compare-col__value"><?= e((string) $offers) ?></div>
        <div class="compare-col__bar" style="--h:<?= e((string) round(($offers / $contentVsOffersMax) * 100)) ?>"></div>
        <div class="compare-col__label">Ofertas</div>
      </div>
    </div>
  </section>

  <section class="panel metrics-card">
    <div class="panel-head"><h2>Top ranking</h2></div>
    <?php if (!$ranked): ?>
      <p class="muted" style="margin:0">Sin ofertas.</p>
    <?php else: ?>
      <ol class="rank-list">
        <?php foreach (array_slice($ranked, 0, 6) as $i => $r): ?>
          <li>
            <span class="rank-list__n">#<?= e((string) ($i + 1)) ?></span>
            <span class="rank-list__name"><?= e($r['company']) ?></span>
            <span class="rank-list__score"><?= e(number_format((float) $r['score'], 1)) ?></span>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </section>

</div>
