<?php

declare(strict_types=1);

/**
 * Asegura grupos de resumen ES/EN sin reinstalar (no borra archivos).
 */
function ensure_summary_document_groups(): void
{
    if (!db_available()) {
        return;
    }
    try {
        $pdo = db();
    } catch (Throwable $e) {
        return;
    }

    $wanted = [
        [
            'name' => 'Hechos de carrera / Summary (ES)',
            'slug' => 'summary-facts-es',
            'language' => 'es',
            'sort_order' => 300,
            'body' => "HECHOS DE CARRERA (fuente de verdad)\n\nNombre:\nUbicación / modalidad:\nDisponibilidad:\n\nEmpleadores (nombre, título, fechas, alcance, tamaño de equipo, tecnologías, resultados):\n-\n\nProyectos públicos / portfolio:\n-\n\nMétricas verificables:\n-\n\nClaims a verificar / no publicar:\n-",
        ],
        [
            'name' => 'Career Facts / Summary (EN)',
            'slug' => 'summary-facts-en',
            'language' => 'en',
            'sort_order' => 305,
            'body' => "CAREER FACTS (source of truth)\n\nName:\nLocation / work mode:\nAvailability:\n\nEmployers (name, title, dates, scope, team size, technologies, outcomes):\n-\n\nPublic projects / portfolio:\n-\n\nVerifiable metrics:\n-\n\nClaims to verify / do not publish:\n-",
        ],
        [
            'name' => 'Banco de logros (ES)',
            'slug' => 'summary-achievements-es',
            'language' => 'es',
            'sort_order' => 310,
            'body' => "BANCO DE LOGROS\nFormato: acción + contexto + resultado + evidencia\n\n1)\n2)\n3)\n4)\n5)\n\nUsar solo hechos aprobados en Hechos de carrera.",
        ],
        [
            'name' => 'Achievement bank (EN)',
            'slug' => 'summary-achievements-en',
            'language' => 'en',
            'sort_order' => 315,
            'body' => "ACHIEVEMENT BANK\nFormat: action + context + result + evidence\n\n1)\n2)\n3)\n4)\n5)\n\nUse only facts approved in Career Facts.",
        ],
    ];

    $exists = $pdo->prepare('SELECT id FROM document_groups WHERE slug = :slug LIMIT 1');
    $insert = $pdo->prepare(
        'INSERT INTO document_groups (name, slug, category, language, body_text, sort_order)
         VALUES (:name, :slug, :category, :language, :body_text, :sort_order)'
    );
    $rename = $pdo->prepare(
        'UPDATE document_groups SET name = :name, language = :language, sort_order = :sort_order
         WHERE slug = :slug'
    );

    foreach ($wanted as $row) {
        $exists->execute([':slug' => $row['slug']]);
        if ($exists->fetch()) {
            $rename->execute([
                ':name' => $row['name'],
                ':language' => $row['language'],
                ':sort_order' => $row['sort_order'],
                ':slug' => $row['slug'],
            ]);
            continue;
        }
        $insert->execute([
            ':name' => $row['name'],
            ':slug' => $row['slug'],
            ':category' => 'summary',
            ':language' => $row['language'],
            ':body_text' => $row['body'],
            ':sort_order' => $row['sort_order'],
        ]);
    }

    // Legacy bilingüe → renombrar a EN si no hay summary-facts-en aún
    $legacy = $pdo->query(
        "SELECT id, slug, name FROM document_groups WHERE slug IN ('summary-facts','summary-achievements')"
    )->fetchAll();
    foreach ($legacy as $leg) {
        if ($leg['slug'] === 'summary-facts') {
            $exists->execute([':slug' => 'summary-facts-en']);
            if (!$exists->fetch()) {
                $pdo->prepare(
                    "UPDATE document_groups SET slug='summary-facts-en', name='Career Facts / Summary (EN)', language='en', sort_order=305 WHERE id=:id"
                )->execute([':id' => $leg['id']]);
            }
        }
        if ($leg['slug'] === 'summary-achievements') {
            $exists->execute([':slug' => 'summary-achievements-en']);
            if (!$exists->fetch()) {
                $pdo->prepare(
                    "UPDATE document_groups SET slug='summary-achievements-en', name='Achievement bank (EN)', language='en', sort_order=315 WHERE id=:id"
                )->execute([':id' => $leg['id']]);
            }
        }
    }
}
