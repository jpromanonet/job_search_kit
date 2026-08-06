<?php
/**
 * One-shot installer: creates schema + seeds days / recommendations / technologies.
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
    ]);
}

$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = $config['db'];
        $server = pdo_server($db);

        $schema = file_get_contents(__DIR__ . '/sql/schema.sql');
        if ($schema === false) {
            throw new RuntimeException('No se pudo leer sql/schema.sql');
        }
        $server->exec($schema);
        $messages[] = 'Schema aplicado.';

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

        seed_days($pdo, $config['paths']['data'] . '/days.json');
        $messages[] = '100 días cargados.';

        seed_recommendations($pdo, $config['paths']['data'] . '/recommendations.json');
        $messages[] = 'Recomendaciones cargadas.';

        seed_technologies($pdo, $config['paths']['data'] . '/technologies.json');
        $messages[] = 'Tecnologías cargadas.';

        try {
            $pdo->exec('ALTER TABLE document_groups ADD COLUMN body_text MEDIUMTEXT NULL AFTER description');
            $messages[] = 'Columna body_text asegurada.';
        } catch (Throwable $e) {
            // already exists
        }

        seed_document_groups($pdo);
        $messages[] = 'Grupos de documentos semilla creados.';

        foreach (['uploads', 'uploads/documents'] as $rel) {
            $path = $config['paths']['root'] . '/' . $rel;
            if (!is_dir($path)) {
                mkdir($path, 0775, true);
            }
        }
        $messages[] = 'Carpetas de uploads listas.';
        $messages[] = 'Instalación completa. Abrí index.php y borrá o protegés install.php.';
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

function seed_days(PDO $pdo, string $path): void
{
    $raw = file_get_contents($path);
    if ($raw === false) {
        throw new RuntimeException("No se pudo leer $path");
    }
    $days = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $pdo->exec('DELETE FROM day_plans');
    $stmt = $pdo->prepare(
        'INSERT INTO day_plans (
            day_number, plan_date, date_label, phase, phase_label, quota_label,
            applications_target, market_split, outcome, special_focus,
            candidate_tasks, ai_tasks, execute_today, definition_of_done,
            source_allocations, blog_title, blog_angle, blog_draft,
            x_copy, linkedin_copy, instagram_task, close_day_proof
        ) VALUES (
            :day_number, :plan_date, :date_label, :phase, :phase_label, :quota_label,
            :applications_target, :market_split, :outcome, :special_focus,
            :candidate_tasks, :ai_tasks, :execute_today, :definition_of_done,
            :source_allocations, :blog_title, :blog_angle, :blog_draft,
            :x_copy, :linkedin_copy, :instagram_task, :close_day_proof
        )'
    );

    foreach ($days as $day) {
        $planDate = parse_plan_date($day['date_label'] ?? '');
        if ($planDate === null) {
            $start = new DateTimeImmutable('2026-08-10');
            $planDate = $start->modify('+' . ((int) $day['day'] - 1) . ' days')->format('Y-m-d');
        }

        $stmt->execute([
            ':day_number' => (int) $day['day'],
            ':plan_date' => $planDate,
            ':date_label' => $day['date_label'] ?? '',
            ':phase' => $day['phase'] ?? 'build',
            ':phase_label' => $day['phase_label'] ?? '',
            ':quota_label' => $day['quota_label'] ?? '',
            ':applications_target' => (int) ($day['applications_target'] ?? 0),
            ':market_split' => $day['market_split'] ?? '',
            ':outcome' => $day['outcome'] ?: null,
            ':special_focus' => $day['special_focus'] ?: null,
            ':candidate_tasks' => json_encode($day['candidate_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
            ':ai_tasks' => json_encode($day['ai_tasks'] ?? [], JSON_UNESCAPED_UNICODE),
            ':execute_today' => json_encode($day['execute_today'] ?? [], JSON_UNESCAPED_UNICODE),
            ':definition_of_done' => $day['definition_of_done'] ?: null,
            ':source_allocations' => json_encode($day['source_allocations'] ?? [], JSON_UNESCAPED_UNICODE),
            ':blog_title' => $day['blog_title'] ?? '',
            ':blog_angle' => $day['blog_angle'] ?: null,
            ':blog_draft' => $day['blog_draft'] ?: null,
            ':x_copy' => $day['x_copy'] ?: null,
            ':linkedin_copy' => $day['linkedin_copy'] ?: null,
            ':instagram_task' => $day['instagram_task'] ?: null,
            ':close_day_proof' => $day['close_day_proof'] ?: null,
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
            ':title' => $section['title'],
            ':body' => $section['body'],
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
            ':area' => $group['area'],
            ':sort_order' => $gi + 1,
        ]);
        $groupId = (int) $pdo->lastInsertId();
        foreach ($group['technologies'] as $ti => $name) {
            // Default: known. User can reclassify in Tecnologías tab later.
            $tStmt->execute([
                ':group_id' => $groupId,
                ':name' => $name,
                ':category' => 'known',
                ':sort_order' => $ti + 1,
            ]);
        }
    }
}

function seed_document_groups(PDO $pdo): void
{
    $pdo->exec('DELETE FROM document_files');
    $pdo->exec('DELETE FROM document_groups');

    $bodies = [
        'cover-es' => "Estimado equipo,\n\nMe postulo a {ROL} en {EMPRESA}. Puedo aportar {EVIDENCIA} y un plan práctico de 90 días orientado a {NECESIDAD}.\n\nGracias,\nJuan Romano",
        'cover-en' => "Dear Hiring Team,\n\nI am applying for the {ROLE} role at {COMPANY}. I can contribute {EVIDENCE} and a practical 90-day approach focused on {NEED}.\n\nThank you for your consideration,\nJuan Romano",
        'msg-recruiter-es' => "Hola {NOMBRE},\n\nVi la búsqueda de {ROL} en {EMPRESA} y creo que hay buen fit por {EVIDENCIA}.\nEstoy abierto/a a una conversación breve esta semana.\n\nSaludos,\nJuan Romano",
        'msg-recruiter-en' => "Hi {NAME},\n\nI saw the {ROLE} opening at {COMPANY}. Based on {EVIDENCE}, I may be a strong fit.\nOpen to a short conversation this week.\n\nBest,\nJuan Romano",
        'msg-cto-es' => "Hola {NOMBRE},\n\nSoy Juan Romano (technical lead / engineering management). Vi {NECESIDAD} en {EMPRESA} y puedo aportar {EVIDENCIA}.\n¿Tenés 15 minutos para validar si tiene sentido?\n\nGracias",
        'msg-cto-en' => "Hi {NAME},\n\nI'm Juan Romano (technical leadership + hands-on delivery). I noticed {NEED} at {COMPANY} and can bring {EVIDENCE}.\nWould a 15-minute chat be useful?\n\nThanks",
        'msg-hm-es' => "Hola {NOMBRE},\n\nMe postulo a {ROL}. Evidencia relevante: {EVIDENCIA}. Puedo compartir un enfoque concreto de 90 días.\n\nJuan Romano",
        'msg-hm-en' => "Hi {NAME},\n\nApplying for {ROLE}. Relevant proof: {EVIDENCE}. Happy to walk through a concrete 90-day approach.\n\nJuan Romano",
        'msg-referral-es' => "Hola {NOMBRE},\n\nEspero que estés bien. Estoy explorando roles de {ROLE_FAMILY} y vi {EMPRESA}/{ROL}.\n¿Podrías referirme o presentarme al hiring manager?\n\nGracias",
        'msg-referral-en' => "Hi {NAME},\n\nHope you're well. I'm exploring {ROLE_FAMILY} roles and saw {COMPANY}/{ROLE}.\nWould you be open to a referral or intro to the hiring manager?\n\nThanks",
        'msg-followup-1-es' => "Hola {NOMBRE},\n\nTe escribo por mi postulación a {ROL} ({FECHA}). Puedo compartir un case study corto o aclarar fit.\n\nJuan",
        'msg-followup-1-en' => "Hi {NAME},\n\nFollowing up on my application for {ROLE} ({DATE}). Happy to share a short case study or clarify fit.\n\nJuan",
        'msg-followup-2-es' => "Hola {NOMBRE},\n\nSegundo follow-up sobre {ROL}. Sigo interesado; puedo adaptar disponibilidad a su proceso.\n\nJuan",
        'msg-followup-2-en' => "Hi {NAME},\n\nQuick second follow-up on {ROLE}. Still interested; I can adapt availability around your process.\n\nJuan",
        'msg-thankyou-es' => "Hola {NOMBRE},\n\nGracias por la conversación sobre {ROL}. Valoro especialmente {PUNTO}.\nPuedo enviar cualquier material de seguimiento.\n\nJuan Romano",
        'msg-thankyou-en' => "Hi {NAME},\n\nThank you for the conversation about {ROLE}. I especially valued {POINT}.\nHappy to send any follow-up materials.\n\nJuan Romano",
        'msg-salary-es' => "Gracias por la pregunta. ¿Qué rango de compensación está aprobado para este rol?\nSegún el alcance y el paquete total, estoy apuntando a {RANGO}, con flexibilidad según responsabilidades y términos.",
        'msg-salary-en' => "Thanks for asking. What compensation range is approved for this role?\nBased on scope and total package, I'm targeting {RANGE}, with flexibility for responsibilities and terms.",
        'summary-facts-es' => "HECHOS DE CARRERA (fuente de verdad)\n\nNombre:\nUbicación / modalidad:\nDisponibilidad:\n\nEmpleadores (nombre, título, fechas, alcance, tamaño de equipo, tecnologías, resultados):\n-\n\nProyectos públicos / portfolio:\n-\n\nMétricas verificables:\n-\n\nClaims a verificar / no publicar:\n-",
        'summary-facts-en' => "CAREER FACTS (source of truth)\n\nName:\nLocation / work mode:\nAvailability:\n\nEmployers (name, title, dates, scope, team size, technologies, outcomes):\n-\n\nPublic projects / portfolio:\n-\n\nVerifiable metrics:\n-\n\nClaims to verify / do not publish:\n-",
        'summary-achievements-es' => "BANCO DE LOGROS\nFormato: acción + contexto + resultado + evidencia\n\n1)\n2)\n3)\n4)\n5)\n\nUsar solo hechos aprobados en Hechos de carrera.",
        'summary-achievements-en' => "ACHIEVEMENT BANK\nFormat: action + context + result + evidence\n\n1)\n2)\n3)\n4)\n5)\n\nUse only facts approved in Career Facts.",
    ];

    $groups = [
        // CVs ES first
        ['Technical Lead / Software Delivery Lead', 'cv-tech-lead-es', 'cv', 'es', 10],
        ['Engineering Manager / Head of Engineering', 'cv-em-es', 'cv', 'es', 20],
        ['Senior Full-stack Software Engineer', 'cv-fullstack-es', 'cv', 'es', 30],
        ['Platform / DevOps / Observability', 'cv-devops-es', 'cv', 'es', 40],
        ['Solutions / Implementation / TAM', 'cv-tam-es', 'cv', 'es', 50],
        ['IT Manager / App Support / Infra Lead', 'cv-it-manager-es', 'cv', 'es', 60],
        // CVs EN
        ['Technical Lead / Software Delivery Lead', 'cv-tech-lead-en', 'cv', 'en', 110],
        ['Engineering Manager / Head of Engineering', 'cv-em-en', 'cv', 'en', 120],
        ['Senior Full-stack Software Engineer', 'cv-fullstack-en', 'cv', 'en', 130],
        ['Platform / DevOps / Observability', 'cv-devops-en', 'cv', 'en', 140],
        ['Solutions / Implementation / TAM', 'cv-tam-en', 'cv', 'en', 150],
        ['IT Manager / App Support / Infra Lead', 'cv-it-manager-en', 'cv', 'en', 160],
        // Covers
        ['Carta de presentación (ES)', 'cover-es', 'cover_letter', 'es', 200],
        ['Cover letter (EN)', 'cover-en', 'cover_letter', 'en', 210],
        // Summaries bilingual
        ['Hechos de carrera / Summary (ES)', 'summary-facts-es', 'summary', 'es', 300],
        ['Career Facts / Summary (EN)', 'summary-facts-en', 'summary', 'en', 305],
        ['Banco de logros (ES)', 'summary-achievements-es', 'summary', 'es', 310],
        ['Achievement bank (EN)', 'summary-achievements-en', 'summary', 'en', 315],
        // Messages bilingual
        ['Mensaje reclutador (ES)', 'msg-recruiter-es', 'message', 'es', 400],
        ['Recruiter message (EN)', 'msg-recruiter-en', 'message', 'en', 410],
        ['Mensaje CTO/CEO (ES)', 'msg-cto-es', 'message', 'es', 420],
        ['CTO/CEO message (EN)', 'msg-cto-en', 'message', 'en', 430],
        ['Mensaje hiring manager (ES)', 'msg-hm-es', 'message', 'es', 440],
        ['Hiring manager message (EN)', 'msg-hm-en', 'message', 'en', 450],
        ['Pedido de referral (ES)', 'msg-referral-es', 'message', 'es', 460],
        ['Referral ask (EN)', 'msg-referral-en', 'message', 'en', 470],
        ['Follow-up 1 (ES)', 'msg-followup-1-es', 'message', 'es', 480],
        ['Follow-up 1 (EN)', 'msg-followup-1-en', 'message', 'en', 490],
        ['Follow-up 2 (ES)', 'msg-followup-2-es', 'message', 'es', 500],
        ['Follow-up 2 (EN)', 'msg-followup-2-en', 'message', 'en', 510],
        ['Agradecimiento (ES)', 'msg-thankyou-es', 'message', 'es', 520],
        ['Thank-you note (EN)', 'msg-thankyou-en', 'message', 'en', 530],
        ['Script salarial (ES)', 'msg-salary-es', 'message', 'es', 540],
        ['Salary script (EN)', 'msg-salary-en', 'message', 'en', 550],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO document_groups (name, slug, category, language, body_text, sort_order)
         VALUES (:name, :slug, :category, :language, :body_text, :sort_order)'
    );
    foreach ($groups as $g) {
        $stmt->execute([
            ':name' => $g[0],
            ':slug' => $g[1],
            ':category' => $g[2],
            ':language' => $g[3],
            ':body_text' => $bodies[$g[1]] ?? null,
            ':sort_order' => $g[4],
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Instalar — Job Search Kit</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <main class="container py-5" style="max-width:640px">
    <h1 class="h3 mb-3">Instalar Job Search Kit</h1>
    <p class="text-secondary">Crea la base <code>job_search_kit</code>, aplica el schema y carga los 100 días desde el DOCX parseado.</p>
    <?php foreach ($errors as $err): ?>
      <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php foreach ($messages as $msg): ?>
      <div class="alert alert-success"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <form method="post">
      <button type="submit" class="btn btn-primary">Instalar / reinstalar</button>
      <a class="btn btn-outline-secondary" href="index.php">Ir al portal</a>
    </form>
    <p class="small text-secondary mt-4 mb-0">
      Ajustá credenciales en <code>config.php</code> o <code>config.local.php</code> antes de instalar.
    </p>
  </main>
</body>
</html>
