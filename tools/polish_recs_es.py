#!/usr/bin/env python3
import json
from pathlib import Path

p = Path(__file__).resolve().parents[1] / "data" / "recommendations.json"
rows = json.loads(p.read_text(encoding="utf-8"))

HAND = {
    "THE TWO PHASES": (
        "Los Días 1–7 son un sprint de construcción con cero postulaciones. "
        "Los Días 8–100 son la campaña de ejecución. Todos los días —incluida la semana de build— "
        "publicás un artículo y lo promocionás en LinkedIn, X y una Instagram Story hecha a mano.\n\n"
        "Fase\nDías\nApps\nResultado principal\n"
        "Construcción\n1–7\n0\nAssets, perfiles, plantillas, FAQ, tracker y sistema de contenido\n"
        "Alto volumen\n8–77\n770\n11/día; alternar 6 AR + 5 Intl y 5 AR + 6 Intl\n"
        "Cierre\n78–100\n230\n10/día; 5 AR + 5 Intl\n"
        "Total\n1–100\n1.000\n500 Argentina + 500 internacional"
    ),
    "What counts as an application": (
        "Contá solo un envío completo a un rol específico y abierto. Crear un perfil, unirte a un talent pool, "
        "mandar un mensaje o guardar una vacante no cuenta.\n"
        "Nunca cuentes duplicados. Un rol enviado por un portal y otra vez por el ATS de la empresa sigue siendo una sola postulación.\n"
        "Postulate solo si el rol acepta candidatos basados en Argentina y cumplís aproximadamente el 60% o más de los requisitos reales.\n"
        "Nada de skills, fechas, títulos, empleadores, alcance o métricas inventadas. Cada claim tiene que ser defendible en entrevista.\n"
        "Si un portal del día no tiene vacante válida, mové ese slot a un ATS directo de empresa del mismo mercado y registrá la sustitución.\n"
        "Todos los días adaptá una postulación prioritaria AR y una prioritaria Intl. El resto usa el CV de la familia de rol aprobada más cercana."
    ),
    "Operating assumptions and safeguards": (
        "Es un plan exigente de siete días a la semana. Presupuestá unas 6–8 horas enfocadas en días de postulación "
        "y 7–9 en el sprint de construcción. Agrupá research y outlines de contenido para cuidar la calidad.\n"
        "Un segundo trabajo full-time tiene que ser legal y operativamente compatible con el actual. Revisá exclusividad, "
        "aprobación de empleo externo, cesión de IP, non-compete, solapamiento de horarios, on-call, confidencialidad, "
        "monitoreo y cláusulas de salida antes de aceptar.\n"
        "No engañes a ninguno de los empleadores. Si la política o el contrato pide divulgación o permiso, conseguilo antes de empezar. "
        "Nunca reutilices info confidencial, código, documentos, nombres de clientes ni equipos.\n"
        "Nunca pagues para postularte, no compartas credenciales bancarias en screening, no instales software desconocido "
        "ni sigas si el reclutador no puede verificar empresa y rol.\n"
        "El contenido diario tiene que estar fact-checked y anonimizado. No publiques arquitectura propietaria, incidentes internos, "
        "info privada del equipo ni logros sin respaldo.\n"
        "Si perdés un día, repartí el déficit en los siguientes siete. El tracker recalcula el ritmo; "
        "no mandes postulaciones de bajo fit solo para recuperar el contador."
    ),
    "Candidate and AI copilot responsibilities": (
        "Candidato es dueño de\nAI copilot apoya\n"
        "Verdad, aprobaciones, ediciones finales, logins, envíos, mensajes, publicación, entrevistas, decisiones legales/contractuales.\n"
        "Ranking de JDs, comparación de keywords ATS, borradores, adaptación de CV, cover letters, variantes de outreach, "
        "edición de blog, práctica de entrevistas, análisis del tracker.\n"
        "El soporte de AI ocurre en sesiones de trabajo activas después de que el candidato aporta el JD, los hechos y los archivos; "
        "no es una promesa autónoma de actuar en días futuros."
    ),
    "Definition of a completed day": (
        "La cuota exacta de postulaciones está enviada y logueada con links que funcionan, fuente, mercado, versión de CV "
        "y próxima fecha de follow-up.\n"
        "En días de postulación se envían cuatro outreach nuevos y cuatro follow-ups; cada dos días de postulación se manda un pedido de referral.\n"
        "El artículo del día está publicado, el link en LinkedIn y X, y el checkbox de Instagram Story marcado a mano.\n"
        "Cada cambio de estado y respuesta queda registrado antes de cerrar el día. El día siguiente empieza desde el tracker, no desde la memoria."
    ),
    "NEGOTIATION RULE": (
        "No ofrezcas el mínimo de entrada. Preguntá: “¿Qué rango de compensación está aprobado para este rol?” "
        "Si te presionan, planteá un rango objetivo según alcance, tipo de empleo, beneficios, riesgo cambiario y agenda "
        "— no el número más bajo aceptable."
    ),
    "KEYWORD DISCIPLINE": (
        "El inventario maestro no se copia entero a cada CV. Cada versión incluye solo tecnologías relevantes y defendibles, "
        "priorizadas por el JD y respaldadas por experiencia o un artefacto de portfolio."
    ),
    "CHANNEL MAP": (
        "Directorio de portales y outreach\n"
        "Cada fuente programada tiene nombre. Un perfil o registro en talent pool nunca cuenta como postulación."
    ),
    "PORTAL AVAILABILITY": (
        "Los sitios, reglas de elegibilidad y filtros de ubicación remota cambian. Validá cada listing antes de postularte. "
        "Si una fuente no tiene rol elegible, usá un ATS directo del empleador en el mismo mercado y registrá la sustitución."
    ),
    "DAILY EXECUTION": (
        "Día 1 al Día 100\n"
        "Construí siete días. Postulate noventa y tres. Publicá y distribuí un artículo útil todos los días."
    ),
    "DAILY ORDER": (
        "Abrí la pestaña Plan → procesá respuestas y follow-ups vencidos → sourceá y validá roles → "
        "adaptá las postulaciones prioritarias → enviá y logueá → mandá outreach → completá el enfoque especial → "
        "publicá el artículo y las redes → cerrá el día con evidencia."
    ),
    "NO-AUTH DEPLOYMENT BOUNDARY": (
        "Como la plataforma no tiene autenticación, no la expongas a internet pública. "
        "Correla en localhost o en una red privada de confianza. "
        "Si algún día hace falta acceso público, hay que agregar autenticación antes; eso queda fuera del alcance de esta versión."
    ),
}

n = 0
for row in rows:
    if row["title"] in HAND:
        row["body"] = HAND[row["title"]]
        n += 1

p.write_text(json.dumps(rows, ensure_ascii=False, indent=2), encoding="utf-8")
print(f"patched {n} sections")
