<?php
/**
 * One-shot installer: schema + primer admin + catálogos desde JSON.
 * Run once: http://localhost/job_search_kit/install.php
 */

declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require __DIR__ . '/includes/helpers.php';

header('Content-Type: text/html; charset=utf-8');

function pdo_server(array $db): PDO
{
    $dsn = sprintf(
        'mysql:host=%s;port=%d;charset=%s',
        $db['host'],
        (int) $db['port'],
        $db['charset']
    );
    return new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
}

function apply_schema_sql(PDO $server, string $dbName, string $schemaPath): void
{
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('No se pudo leer sql/schema.sql');
    }

    $server->exec("DROP DATABASE IF EXISTS `{$dbName}`");
    $server->exec(
        "CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );
    $server->exec("USE `{$dbName}`");

    // Quitar CREATE DATABASE / USE del archivo (ya hechos arriba).
    $schema = preg_replace('/CREATE\s+DATABASE\b.*?;/is', '', $schema) ?? $schema;
    $schema = preg_replace('/USE\s+`?[\w]+`?\s*;/i', '', $schema) ?? $schema;
    // Base ya vacía: CREATE TABLE a secas (evita tablas viejas con IF NOT EXISTS).
    $schema = preg_replace('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\b/i', 'CREATE TABLE', $schema) ?? $schema;

    // Ejecutar statement por statement, respetando orden del archivo.
    $buffer = '';
    $lines = preg_split("/\r\n|\n|\r/", $schema) ?: [];
    foreach ($lines as $line) {
        $trim = trim($line);
        if ($trim === '' || str_starts_with($trim, '--')) {
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), ';')) {
            $sql = trim($buffer);
            $buffer = '';
            if ($sql === '') {
                continue;
            }
            $server->exec($sql);
        }
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        $server->exec($tail);
    }
}

/**
 * Chequea estado sin tocar el PDO estático de db() (si no, un DROP DATABASE
 * deja la conexión cacheada apuntando a tablas viejas → "Unknown column user_id").
 */
function install_probe(array $db): array
{
    $out = [
        'reachable' => false,
        'has_users' => false,
        'schema_ok' => false,
        'needs_recreate' => true,
    ];
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            (int) $db['port'],
            $db['name'],
            $db['charset']
        );
        $pdo = new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $out['reachable'] = true;

        $cols = $pdo->query("SHOW COLUMNS FROM applications LIKE 'user_id'")->fetchAll();
        $out['schema_ok'] = $cols !== [];
        $out['needs_recreate'] = !$out['schema_ok'];

        try {
            $n = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $out['has_users'] = $n > 0;
        } catch (Throwable $e) {
            $out['has_users'] = false;
            $out['needs_recreate'] = true;
        }
    } catch (Throwable $e) {
        $out['needs_recreate'] = true;
    }
    return $out;
}

function nullable_str($value): ?string
{
    if ($value === null) {
        return null;
    }
    $s = trim((string) $value);
    return $s === '' ? null : $s;
}

function seed_plan_days(PDO $pdo, string $path): void
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $days = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $pdo->exec('DELETE FROM plan_days');
    $stmt = $pdo->prepare(
        'INSERT INTO plan_days (
            day_number, phase, phase_label, quota_label,
            applications_target, market_split, outcome, special_focus,
            candidate_tasks, ai_tasks, execute_today, definition_of_done,
            source_allocations, blog_publish, blog_title, blog_angle, blog_draft,
            x_copy, linkedin_copy, instagram_task, close_day_proof
        ) VALUES (
            :day_number, :phase, :phase_label, :quota_label,
            :applications_target, :market_split, :outcome, :special_focus,
            :candidate_tasks, :ai_tasks, :execute_today, :definition_of_done,
            :source_allocations, :blog_publish, :blog_title, :blog_angle, :blog_draft,
            :x_copy, :linkedin_copy, :instagram_task, :close_day_proof
        )'
    );

    foreach ($days as $day) {
        $stmt->execute([
            ':day_number' => (int) ($day['day'] ?? 0),
            ':phase' => $day['phase'] ?? 'build',
            ':phase_label' => (string) ($day['phase_label'] ?? ''),
            ':quota_label' => (string) ($day['quota_label'] ?? ''),
            ':applications_target' => (int) ($day['applications_target'] ?? 0),
            ':market_split' => (string) ($day['market_split'] ?? ''),
            ':outcome' => nullable_str($day['outcome'] ?? null),
            ':special_focus' => nullable_str($day['special_focus'] ?? null),
            ':candidate_tasks' => json_encode($day['candidate_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
            ':ai_tasks' => json_encode($day['ai_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
            ':execute_today' => json_encode($day['execute_today'] ?? [], JSON_UNESCAPED_UNICODE),
            ':definition_of_done' => nullable_str($day['definition_of_done'] ?? null),
            ':source_allocations' => json_encode($day['source_allocations'] ?? [], JSON_UNESCAPED_UNICODE),
            ':blog_publish' => !empty($day['blog_publish']) ? 1 : 0,
            ':blog_title' => (string) ($day['blog_title'] ?? ''),
            ':blog_angle' => nullable_str($day['blog_angle'] ?? null),
            ':blog_draft' => nullable_str($day['blog_draft'] ?? null),
            ':x_copy' => nullable_str($day['x_copy'] ?? null),
            ':linkedin_copy' => nullable_str($day['linkedin_copy'] ?? null),
            ':instagram_task' => nullable_str($day['instagram_task'] ?? null),
            ':close_day_proof' => nullable_str($day['close_day_proof'] ?? null),
        ]);
    }
}

function seed_recommendations(PDO $pdo, string $path): void
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $sections = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $pdo->exec('DELETE FROM recommendation_sections');
    $stmt = $pdo->prepare(
        'INSERT INTO recommendation_sections (sort_order, title, body) VALUES (:sort_order, :title, :body)'
    );
    foreach ($sections as $i => $section) {
        $stmt->execute([
            ':sort_order' => $i + 1,
            ':title' => (string) ($section['title'] ?? ''),
            ':body' => (string) ($section['body'] ?? ''),
        ]);
    }
}

function seed_technologies(PDO $pdo, string $path): void
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $groups = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $pdo->exec('DELETE FROM technologies');
    $pdo->exec('DELETE FROM technology_groups');

    $gStmt = $pdo->prepare(
        'INSERT INTO technology_groups (area, sort_order) VALUES (:area, :sort_order)'
    );
    $tStmt = $pdo->prepare(
        'INSERT INTO technologies (group_id, name, category, sort_order)
         VALUES (:group_id, :name, :category, :sort_order)'
    );

    foreach ($groups as $gi => $group) {
        $gStmt->execute([
            ':area' => (string) ($group['area'] ?? ''),
            ':sort_order' => $gi + 1,
        ]);
        $groupId = (int) $pdo->lastInsertId();
        foreach (($group['technologies'] ?? []) as $ti => $name) {
            $tStmt->execute([
                ':group_id' => $groupId,
                ':name' => (string) $name,
                ':category' => 'known',
                ':sort_order' => $ti + 1,
            ]);
        }
    }
}

function seed_portals(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $sections = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($sections)) {
        return;
    }

    $pdo->exec('DELETE FROM portals');
    $stmt = $pdo->prepare(
        'INSERT INTO portals (name, url, market, category, notes, sort_order)
         VALUES (:name, :url, :market, :category, :notes, :sort_order)'
    );

    $order = 0;
    foreach ($sections as $section) {
        $sectionTitle = (string) ($section['section'] ?? '');
        $market = (stripos($sectionTitle, 'argentina') !== false
            || stripos($sectionTitle, ' ar ') !== false)
            ? 'ar'
            : 'ar';
        $category = $sectionTitle !== '' ? $sectionTitle : null;
        foreach (($section['portals'] ?? []) as $portal) {
            $name = trim((string) ($portal['name'] ?? ''));
            $url = trim((string) ($portal['url'] ?? ''));
            if ($name === '' || $url === '') {
                continue;
            }
            $order++;
            $stmt->execute([
                ':name' => $name,
                ':url' => $url,
                ':market' => $market,
                ':category' => $category,
                ':notes' => nullable_str($portal['use'] ?? null),
                ':sort_order' => $order,
            ]);
        }
    }
}

function seed_hr_faq(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $items = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $pdo->exec('DELETE FROM hr_faq');
    $stmt = $pdo->prepare(
        'INSERT INTO hr_faq (category, question, answer, sort_order)
         VALUES (:category, :question, :answer, :sort_order)'
    );
    foreach ($items as $i => $item) {
        $stmt->execute([
            ':category' => (string) ($item['category'] ?? ''),
            ':question' => (string) ($item['question'] ?? ''),
            ':answer' => (string) ($item['answer'] ?? ''),
            ':sort_order' => (int) ($item['id'] ?? ($i + 1)),
        ]);
    }
}

function seed_company_questions(PDO $pdo, string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $items = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $pdo->exec('DELETE FROM company_questions');
    $stmt = $pdo->prepare(
        'INSERT INTO company_questions (question, why, tip, sort_order)
         VALUES (:question, :why, :tip, :sort_order)'
    );
    foreach ($items as $i => $item) {
        $stmt->execute([
            ':question' => (string) ($item['question'] ?? ''),
            ':why' => nullable_str($item['why'] ?? null),
            ':tip' => nullable_str($item['tip'] ?? null),
            ':sort_order' => (int) ($item['id'] ?? ($i + 1)),
        ]);
    }
}

$messages = [];
$errors = [];
$formName = '';
$formEmail = '';
$appName = (string) ($config['app']['name'] ?? 'JobKit');
$dataDir = $config['paths']['data'];
$dbCfg = $config['db'];
$probe = install_probe($dbCfg);
$alreadyInstalled = $probe['has_users'] && $probe['schema_ok'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $force = !empty($_POST['force_reinstall']);
    if ($alreadyInstalled && !$force) {
        $errors[] = 'Ya hay usuarios en la base. Entrá con login.php; no se crea otro admin desde el instalador.';
    } else {
        $formName = trim((string) ($_POST['name'] ?? ''));
        $formEmail = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $password2 = (string) ($_POST['password_confirm'] ?? '');

        try {
            if ($formName === '' || $formEmail === '') {
                throw new InvalidArgumentException('Completá nombre y email.');
            }
            if (!filter_var($formEmail, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Email inválido.');
            }
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
            }
            if ($password !== $password2) {
                throw new InvalidArgumentException('Las contraseñas no coinciden.');
            }

            $server = pdo_server($dbCfg);
            $dbName = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $dbCfg['name']) ?: 'job_search_kit';

            apply_schema_sql($server, $dbName, __DIR__ . '/sql/schema.sql');
            $messages[] = 'Schema aplicado (base recreada).';

            require_once __DIR__ . '/includes/db.php';
            require_once __DIR__ . '/includes/repositories.php';
            require_once __DIR__ . '/includes/auth.php';

            // Importante: nueva conexión después del DROP DATABASE.
            $pdo = db(true);

            $cols = $pdo->query("SHOW COLUMNS FROM applications LIKE 'user_id'")->fetchAll();
            if ($cols === []) {
                throw new RuntimeException(
                    'El schema no tiene user_id. Revisá que sql/schema.sql esté actualizado en el server.'
                );
            }

            $userId = create_user($formEmail, $password, $formName, 'admin');
            $messages[] = 'Usuario admin creado (id ' . $userId . ').';

            seed_plan_days($pdo, $dataDir . '/days.json');
            $messages[] = 'Plan de 100 días cargado.';

            seed_recommendations($pdo, $dataDir . '/recommendations.json');
            $messages[] = 'Recomendaciones cargadas.';

            seed_technologies($pdo, $dataDir . '/technologies.json');
            $messages[] = 'Tecnologías cargadas.';

            seed_portals($pdo, $dataDir . '/portals.json');
            $messages[] = 'Portales cargados.';

            seed_hr_faq($pdo, $dataDir . '/hr_faq.json');
            $messages[] = 'HR FAQ cargado.';

            seed_company_questions($pdo, $dataDir . '/company_questions.json');
            $messages[] = 'Preguntas a la empresa cargadas.';

            foreach (['uploads', 'uploads/documents'] as $rel) {
                $path = $config['paths']['root'] . '/' . $rel;
                if (!is_dir($path)) {
                    mkdir($path, 0775, true);
                }
            }
            $messages[] = 'Carpetas de uploads listas.';
            $messages[] = 'Instalación completa. Entrá con login.php y protegés o borrás install.php.';

            $alreadyInstalled = true;
            $formName = '';
            $formEmail = '';
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalar · <?= e($appName) ?></title>
  <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
  <style>
    .auth-body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      background:
        radial-gradient(ellipse 80% 50% at 10% 0%, rgba(4, 120, 87, 0.08), transparent 55%),
        radial-gradient(ellipse 60% 40% at 90% 100%, rgba(59, 130, 246, 0.07), transparent 50%),
        var(--bg);
    }
    .auth-shell { width: 100%; max-width: 440px; }
    .auth-card { padding: 1.75rem 1.5rem 1.5rem; }
    .auth-hero { text-align: center; margin-bottom: 1.25rem; }
    .auth-hero h1 {
      margin: 0.35rem 0 0.4rem;
      font-size: 1.65rem;
      letter-spacing: -0.02em;
    }
    .auth-hero .muted { margin: 0; font-size: 0.95rem; line-height: 1.45; }
    .illus {
      width: 3rem;
      height: 3rem;
      margin: 0 auto;
      display: grid;
      place-items: center;
      border-radius: 12px;
      background: var(--accent-soft);
      font-size: 1.5rem;
    }
    .stack { display: flex; flex-direction: column; gap: 0.85rem; }
    .hint { margin: 1rem 0 0; font-size: 0.85rem; line-height: 1.4; }
    .actions { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.25rem; }
    .actions .btn { flex: 1 1 auto; text-align: center; }
  </style>
</head>
<body class="auth-body">
  <main class="auth-shell">
    <section class="auth-card panel">
      <div class="auth-hero">
        <div class="illus" aria-hidden="true">🌱</div>
        <h1>Instalar <?= e($appName) ?></h1>
        <p class="muted">Crea la base, el primer admin y los catálogos (días, FAQ, portales…).</p>
      </div>

      <?php foreach ($errors as $err): ?>
        <div class="flash flash-danger" style="margin-bottom:0.75rem"><?= e($err) ?></div>
      <?php endforeach; ?>
      <?php foreach ($messages as $msg): ?>
        <div class="flash flash-ok" style="margin-bottom:0.75rem"><?= e($msg) ?></div>
      <?php endforeach; ?>

      <?php if ($alreadyInstalled && $messages === [] && $errors === []): ?>
        <div class="flash flash-info" style="margin-bottom:1rem">
          La app ya tiene usuarios. No hace falta reinstalar desde acá.
        </div>
        <div class="actions">
          <a class="btn btn-accent" href="<?= e(url('/login.php')) ?>">Ir a login</a>
          <a class="btn" href="<?= e(url('/index.php')) ?>">Ir al portal</a>
        </div>
      <?php elseif ($alreadyInstalled && $messages !== []): ?>
        <div class="actions">
          <a class="btn btn-accent" href="<?= e(url('/login.php')) ?>">Entrar</a>
          <a class="btn" href="<?= e(url('/index.php')) ?>">Ir al portal</a>
        </div>
      <?php else: ?>
        <?php if (!empty($probe['reachable']) && !empty($probe['needs_recreate'])): ?>
          <div class="flash flash-danger" style="margin-bottom:0.75rem">
            La base existe pero el schema es viejo (falta <code>user_id</code>).
            Al instalar se va a borrar y recrear la base MySQL.
          </div>
        <?php endif; ?>
        <form method="post" class="stack" autocomplete="off">
          <input type="hidden" name="force_reinstall" value="1">
          <div class="field">
            <label for="name">Nombre</label>
            <input id="name" type="text" name="name" required maxlength="120"
                   value="<?= e($formName) ?>" autocomplete="name">
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" required maxlength="190"
                   value="<?= e($formEmail) ?>" autocomplete="username">
          </div>
          <div class="field">
            <label for="password">Contraseña (mín. 8)</label>
            <input id="password" type="password" name="password" required minlength="8"
                   autocomplete="new-password">
          </div>
          <div class="field">
            <label for="password_confirm">Confirmar contraseña</label>
            <input id="password_confirm" type="password" name="password_confirm" required minlength="8"
                   autocomplete="new-password">
          </div>
          <div class="actions">
            <button type="submit" class="btn btn-accent">Instalar</button>
            <a class="btn" href="<?= e(url('/login.php')) ?>">Login</a>
          </div>
        </form>
        <p class="muted hint">
          Ajustá MySQL en <code>config.php</code> o <code>config.local.php</code> antes de instalar.
          Esto recrea la base <code><?= e((string) $dbCfg['name']) ?></code> (borra tablas viejas).
        </p>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
