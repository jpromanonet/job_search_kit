# Job Search Kit

Portal local (sin login) para la campaña de 100 días / 1.000 applications.

Stack: **PHP + MySQL + Bootstrap 5 + HTML5 + CSS3 + vanilla JS**.

## Pestañas

| Tab | Qué hace |
|-----|----------|
| **Plan 100 días** | Cards día a día del DOCX, estados, evidencia, content checklist, link al Tracker |
| **Recomendaciones** | Prefacio del playbook (sin tecnologías) |
| **Tecnologías** | Inventario agrupado + reclasificar (sé / vieja / nueva / learning / exclude) |
| **Documentos** | CVs, cover letters, summaries y mensajes: upload/download DOCX·PDF·TXT + texto editable |
| **Tracker** | CRUD de applications, filtros, tabla/kanban, CSV, anti-duplicado, sync con día |
| **Comparador** | Ranking de ofertas (scores 0–10) cuando stage = Offer / Accepted |

## Setup

1. PHP 8.1+ y MySQL 8.
2. Credenciales en `config.php` o `config.local.php`:

```php
<?php
return [
    'db' => [
        'host' => '127.0.0.1',
        'user' => 'root',
        'pass' => 'tu_password',
        'name' => 'job_search_kit',
    ],
];
```

3. Abrí `install.php` → **Instalar** (schema + 100 días + recomendaciones + tecnologías + documentos).
4. Entrá a `index.php`.

```bash
php -S localhost:8080 -t .
```

## Datos del DOCX

```bash
python tools/parse_plan.py
```

Genera `data/days.json`, `data/recommendations.json`, `data/technologies.json`.

## Notas

- Sin auth: solo localhost / red privada.
- Timezone: `America/Argentina/Buenos_Aires`.
- Campaña: 10 Aug 2026 → 17 Nov 2026.
- Archivos subidos en `uploads/documents/`.
