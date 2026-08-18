<?php

declare(strict_types=1);

/**
 * File-based storage so Tracker works without MySQL.
 */

function applications_store_path(): string
{
    $config = require __DIR__ . '/../config.php';
    $dir = $config['paths']['data'];
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . '/applications.json';
}

function load_applications(): array
{
    $path = applications_store_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }
    return is_array($data) ? array_values($data) : [];
}

function save_applications(array $apps): void
{
    $path = applications_store_path();
    $json = json_encode(array_values($apps), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar applications.json');
    }
    write_data_file($path, $json . "\n");
}

function next_application_id(array $apps): int
{
    $max = 0;
    foreach ($apps as $app) {
        $max = max($max, (int) ($app['id'] ?? 0));
    }
    return $max + 1;
}

function find_application(int $id): ?array
{
    foreach (load_applications() as $app) {
        if ((int) ($app['id'] ?? 0) === $id) {
            return $app;
        }
    }
    return null;
}

function upsert_application(array $app): array
{
    $apps = load_applications();
    $id = (int) ($app['id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    if ($id < 1) {
        $app['id'] = next_application_id($apps);
        $app['created_at'] = $now;
        $app['updated_at'] = $now;
        $apps[] = $app;
        save_applications($apps);
        return $app;
    }

    $found = false;
    foreach ($apps as $i => $existing) {
        if ((int) $existing['id'] === $id) {
            $app['created_at'] = $existing['created_at'] ?? $now;
            $app['updated_at'] = $now;
            $apps[$i] = $app;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $app['created_at'] = $now;
        $app['updated_at'] = $now;
        $apps[] = $app;
    }
    save_applications($apps);
    return $app;
}

function delete_application_by_id(int $id): bool
{
    $apps = load_applications();
    $before = count($apps);
    $apps = array_values(array_filter($apps, static fn ($a) => (int) ($a['id'] ?? 0) !== $id));
    if (count($apps) === $before) {
        return false;
    }
    save_applications($apps);
    return true;
}

function application_stats(array $apps): array
{
    $counts = [];
    $market = ['ar' => 0, 'intl' => 0];
    $overdue = 0;
    $today = date('Y-m-d');

    foreach ($apps as $app) {
        $stage = (string) ($app['stage'] ?? 'discovered');
        $counts[$stage] = ($counts[$stage] ?? 0) + 1;
        if (is_submitted_stage($stage)) {
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
        'submitted' => $market['ar'] + $market['intl'],
        'overdue' => $overdue,
    ];
}

function document_groups_store_path(): string
{
    $config = require __DIR__ . '/../config.php';
    return $config['paths']['data'] . '/document_groups.json';
}

function document_files_store_path(): string
{
    $config = require __DIR__ . '/../config.php';
    return $config['paths']['data'] . '/document_files.json';
}

function load_document_groups(): array
{
    $path = document_groups_store_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }
    if (!is_array($data)) {
        return [];
    }
    usort($data, static fn ($a, $b) => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));
    return array_values($data);
}

function save_document_groups(array $groups): void
{
    $path = document_groups_store_path();
    $json = json_encode(array_values($groups), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar document_groups.json');
    }
    write_data_file($path, $json . "\n");
}

function load_document_files(): array
{
    $path = document_files_store_path();
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return [];
    }
    return is_array($data) ? array_values($data) : [];
}

function save_document_files(array $files): void
{
    $path = document_files_store_path();
    $json = json_encode(array_values($files), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar document_files.json');
    }
    write_data_file($path, $json . "\n");
}

function find_document_group(int $id): ?array
{
    foreach (load_document_groups() as $g) {
        if ((int) ($g['id'] ?? 0) === $id) {
            return $g;
        }
    }
    return null;
}

function update_document_group_text(int $id, string $body, ?string $description = null): bool
{
    $groups = load_document_groups();
    foreach ($groups as $i => $g) {
        if ((int) ($g['id'] ?? 0) === $id) {
            $groups[$i]['body_text'] = $body === '' ? null : $body;
            $groups[$i]['description'] = $description;
            save_document_groups($groups);
            return true;
        }
    }
    return false;
}

/**
 * Asegura filas de CV maestro ES/EN (archivo JSON y MySQL si está disponible).
 */
function ensure_cv_master_document_groups(): void
{
    $wanted = [
        [
            'id' => 35,
            'name' => 'CV maestro (ES)',
            'slug' => 'cv-master-es',
            'category' => 'cv',
            'language' => 'es',
            'body_text' => null,
            'sort_order' => 1,
            'description' => 'CV completo en español.',
        ],
        [
            'id' => 36,
            'name' => 'Master CV (EN)',
            'slug' => 'cv-master-en',
            'category' => 'cv',
            'language' => 'en',
            'body_text' => null,
            'sort_order' => 2,
            'description' => 'Full CV in English.',
        ],
    ];

    $groups = load_document_groups();
    $bySlug = [];
    foreach ($groups as $i => $g) {
        $bySlug[(string) ($g['slug'] ?? '')] = $i;
    }
    $changed = false;
    $maxId = 0;
    foreach ($groups as $g) {
        $maxId = max($maxId, (int) ($g['id'] ?? 0));
    }
    foreach ($wanted as $row) {
        $slug = $row['slug'];
        if (isset($bySlug[$slug])) {
            $i = $bySlug[$slug];
            foreach (['name', 'category', 'language', 'sort_order', 'description'] as $key) {
                if (($groups[$i][$key] ?? null) !== $row[$key]) {
                    $groups[$i][$key] = $row[$key];
                    $changed = true;
                }
            }
            continue;
        }
        $maxId++;
        $row['id'] = $maxId;
        $groups[] = $row;
        $changed = true;
    }
    if ($changed) {
        save_document_groups($groups);
    }

    if (!function_exists('db_available') || !db_available()) {
        return;
    }
    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }

    $exists = $pdo->prepare('SELECT id FROM document_groups WHERE slug = :slug LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO document_groups (name, slug, category, language, body_text, sort_order, description)
         VALUES (:name, :slug, :category, :language, :body_text, :sort_order, :description)'
    );
    $update = $pdo->prepare(
        'UPDATE document_groups
         SET name = :name, category = :category, language = :language, sort_order = :sort_order, description = :description
         WHERE slug = :slug'
    );

    foreach ($wanted as $row) {
        $exists->execute([':slug' => $row['slug']]);
        if ($exists->fetch()) {
            $update->execute([
                ':name' => $row['name'],
                ':category' => $row['category'],
                ':language' => $row['language'],
                ':sort_order' => $row['sort_order'],
                ':description' => $row['description'],
                ':slug' => $row['slug'],
            ]);
            continue;
        }
        try {
            $insert->execute([
                ':name' => $row['name'],
                ':slug' => $row['slug'],
                ':category' => $row['category'],
                ':language' => $row['language'],
                ':body_text' => $row['body_text'],
                ':sort_order' => $row['sort_order'],
                ':description' => $row['description'],
            ]);
        } catch (Throwable $e) {
            // description column may be missing on older schemas
            $pdo->prepare(
                'INSERT INTO document_groups (name, slug, category, language, body_text, sort_order)
                 VALUES (:name, :slug, :category, :language, :body_text, :sort_order)'
            )->execute([
                ':name' => $row['name'],
                ':slug' => $row['slug'],
                ':category' => $row['category'],
                ':language' => $row['language'],
                ':body_text' => $row['body_text'],
                ':sort_order' => $row['sort_order'],
            ]);
        }
    }
}

function next_document_file_id(array $files): int
{
    $max = 0;
    foreach ($files as $f) {
        $max = max($max, (int) ($f['id'] ?? 0));
    }
    return $max + 1;
}

function find_document_file(int $id): ?array
{
    foreach (load_document_files() as $f) {
        if ((int) ($f['id'] ?? 0) === $id) {
            return $f;
        }
    }
    return null;
}

function upsert_document_file(array $file): array
{
    $files = load_document_files();
    $groupId = (int) ($file['group_id'] ?? 0);
    $format = (string) ($file['format'] ?? '');
    $version = (string) ($file['version'] ?? '1.0');
    $now = date('Y-m-d H:i:s');

    foreach ($files as $i => $existing) {
        if (
            (int) ($existing['group_id'] ?? 0) === $groupId
            && ($existing['format'] ?? '') === $format
            && ($existing['version'] ?? '') === $version
        ) {
            $file['id'] = (int) $existing['id'];
            $file['uploaded_at'] = $now;
            $files[$i] = array_merge($existing, $file);
            save_document_files($files);
            return $files[$i];
        }
    }

    $file['id'] = next_document_file_id($files);
    $file['uploaded_at'] = $now;
    $files[] = $file;
    save_document_files($files);
    return $file;
}

function delete_document_file_by_id(int $id): ?array
{
    $files = load_document_files();
    $removed = null;
    $out = [];
    foreach ($files as $f) {
        if ((int) ($f['id'] ?? 0) === $id) {
            $removed = $f;
            continue;
        }
        $out[] = $f;
    }
    if ($removed === null) {
        return null;
    }
    save_document_files($out);
    return $removed;
}

function ats_lakes_store_path(): string
{
    $config = require __DIR__ . '/../config.php';
    $dir = $config['paths']['data'];
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir . '/ats_lakes.json';
}

function default_ats_lakes(): array
{
    return [
        'es' => [
            'text' => '',
            'source_name' => null,
            'updated_at' => null,
        ],
        'en' => [
            'text' => '',
            'source_name' => null,
            'updated_at' => null,
        ],
    ];
}

function load_ats_lakes(): array
{
    $defaults = default_ats_lakes();
    $path = ats_lakes_store_path();
    if (!is_file($path)) {
        return $defaults;
    }
    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return $defaults;
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $e) {
        return $defaults;
    }
    if (!is_array($data)) {
        return $defaults;
    }
    foreach (['es', 'en'] as $lang) {
        $row = is_array($data[$lang] ?? null) ? $data[$lang] : [];
        $defaults[$lang] = [
            'text' => (string) ($row['text'] ?? ''),
            'source_name' => isset($row['source_name']) && $row['source_name'] !== ''
                ? (string) $row['source_name']
                : null,
            'updated_at' => isset($row['updated_at']) && $row['updated_at'] !== ''
                ? (string) $row['updated_at']
                : null,
        ];
    }
    return $defaults;
}

function save_ats_lakes(array $lakes): void
{
    $normalized = default_ats_lakes();
    foreach (['es', 'en'] as $lang) {
        $row = is_array($lakes[$lang] ?? null) ? $lakes[$lang] : [];
        $normalized[$lang] = [
            'text' => (string) ($row['text'] ?? ''),
            'source_name' => isset($row['source_name']) && $row['source_name'] !== ''
                ? (string) $row['source_name']
                : null,
            'updated_at' => isset($row['updated_at']) && $row['updated_at'] !== ''
                ? (string) $row['updated_at']
                : null,
        ];
    }
    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar ats_lakes.json');
    }
    write_data_file(ats_lakes_store_path(), $json . "\n");
}

function update_ats_lake(string $lang, string $text, ?string $sourceName = null): array
{
    if (!in_array($lang, ['es', 'en'], true)) {
        throw new InvalidArgumentException('Idioma ATS inválido.');
    }
    $lakes = load_ats_lakes();
    $lakes[$lang] = [
        'text' => $text,
        'source_name' => $sourceName !== null && $sourceName !== ''
            ? $sourceName
            : ($lakes[$lang]['source_name'] ?? null),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    save_ats_lakes($lakes);
    return $lakes[$lang];
}

/**
 * Parse a free-text ATS lake into unique keyword tokens.
 *
 * @return list<string>
 */
function parse_ats_keywords(string $text): array
{
    $parts = preg_split('/[\r\n,;|]+/u', $text) ?: [];
    $seen = [];
    $out = [];
    foreach ($parts as $part) {
        $word = trim(preg_replace('/\s+/u', ' ', (string) $part) ?? '');
        if ($word === '') {
            continue;
        }
        $key = function_exists('mb_strtolower')
            ? mb_strtolower($word, 'UTF-8')
            : strtolower($word);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $word;
    }
    return $out;
}

function extract_text_from_docx(string $path): string
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive no está disponible para leer DOCX.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('No se pudo abrir el DOCX.');
    }
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false || $xml === '') {
        throw new RuntimeException('El DOCX no tiene word/document.xml legible.');
    }
    $xml = str_replace(['</w:p>', '</w:tr>', '</w:tbl>'], "\n", $xml);
    $xml = str_replace(['<w:tab/>', '<w:br/>', '<w:cr/>'], "\t", $xml);
    $text = strip_tags($xml);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
    $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
    return trim($text);
}

function extract_text_from_upload(string $tmpPath, string $originalName, string $mime = ''): string
{
    $format = detect_format_from_upload($originalName, $mime) ?? '';
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($format === 'docx' || $ext === 'docx') {
        return extract_text_from_docx($tmpPath);
    }
    if (in_array($format, ['txt', 'md'], true) || in_array($ext, ['txt', 'md', 'csv'], true)) {
        $raw = file_get_contents($tmpPath);
        if ($raw === false) {
            throw new RuntimeException('No se pudo leer el archivo de texto.');
        }
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        return trim($raw);
    }
    throw new RuntimeException('Formato no soportado. Usá DOCX, TXT o MD.');
}
