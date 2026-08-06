<?php

declare(strict_types=1);

require __DIR__ . '/../includes/helpers.php';
require __DIR__ . '/../includes/storage.php';

$rows = load_applications();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="applications_' . date('Ymd_His') . '.csv"');

$out = fopen('php://output', 'w');
if ($out === false) {
    exit;
}
fwrite($out, "\xEF\xBB\xBF");

$headers = ['id','day_number','company','role_title','market','platform','canonical_url','stage','application_date','follow_up_date','fit_score','notes'];
fputcsv($out, $headers);
foreach ($rows as $row) {
    $line = [];
    foreach ($headers as $h) {
        $line[] = $row[$h] ?? '';
    }
    fputcsv($out, $line);
}
fclose($out);
exit;
