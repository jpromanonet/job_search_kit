# JobKit

JobKit es un portal personal para llevar una búsqueda laboral de punta a punta: cargar postulaciones, seguir el pipeline, preparar entrevistas, guardar CVs y cartas, comparar ofertas y ver métricas.

Corre en PHP + MySQL, con login. Cada usuario ve solo su campaña.

## Qué hace

- **Mapa** — resumen del día: objetivo, envíos de la campaña, entrevistas y follow-ups.
- **Diario** — alta y edición de postulaciones (lista o kanban). Estado, sueldo, CV, carta, modalidad.
- **Charlas** — notas de entrevista: prep, en vivo y debrief.
- **Grimorio** — CVs y cartas en PDF; mensajes a reclutadores como texto para copiar.
- **Roles** — banco de frases ATS por idioma.
- **Comparar** — techo salarial y ranking de ofertas (comp, remote, horario, calidad, riesgo).
- **Métricas** — embudo, conversión, ritmo de la campaña y portales.
- **Portales, Preguntar, HR, Stack** — listas propias más un catálogo semilla.
- **Perfil** — ficha con links y mercado preferido.

La campaña arranca el día que marcás. Se puede reiniciar sin borrar el historial.

## Requisitos

- PHP 8.1+
- MySQL 8+
- Extensiones: `pdo_mysql`, `json`, `session`

## Instalación

1. Copiá el proyecto a la carpeta web del server (Apache, nginx, XAMPP, etc.).
2. Copiá `config.local.example.php` a `config.local.php` y completá host, usuario, clave y nombre de la base.
3. Dejá `app.url` vacío para que se detecte solo, o poné la URL pública (por ejemplo `http://localhost/jobkit`).
4. Abrí `install.php` en el navegador.
   - Aplica el schema de [`sql/schema.sql`](sql/schema.sql)
   - Crea el primer usuario admin (el formulario va vacío)
   - Siembra catálogos desde `/data` (días, FAQ, portales, tecnologías, preguntas)
5. Entrá por `login.php`.
6. Protegé o borrá `install.php`. **Volver a instalar dropea la base** y se pierde todo lo cargado.

El schema no trae usuarios, postulaciones ni archivos. Es el único `.sql` del repo.

## Cómo usarlo

1. Completá **Perfil** y, si querés, el techo en **Comparar**.
2. Subí CVs y cartas PDF en **Grimorio**. Los mensajes se editan como texto.
3. Cargá cada envío en **Diario**. Con sueldo (o “No indiqué sueldo”), CV y carta el registro queda usable para métricas y el comparador.
4. Mové el estado en la lista o arrastrando cards en el kanban.
5. Cuando haya una charla, anotarla en **Charlas**.
6. Mirálas en **Mapa** y **Métricas**. El objetivo se cambia en el panel de la campaña.

## Datos

| Dónde | Qué |
|---|---|
| MySQL | Usuarios, perfil, postulaciones, entrevistas, documentos, settings |
| `/data` | Solo seeds de catálogo para `install.php` |
| `/uploads/documents` | PDFs que subís |

No commitees `config.local.php` ni archivos de `/uploads`.
