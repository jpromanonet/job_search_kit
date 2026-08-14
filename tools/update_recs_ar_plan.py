# -*- coding: utf-8 -*-
"""Update campaign recommendations for AR-only, 5/day, weekly blog."""
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / "data" / "recommendations.json"
recs = json.loads(path.read_text(encoding="utf-8"))

updates = {
    "Campaña": (
        "100 DÍAS  •  465 POSTULACIONES\n"
        "Campaña sostenible · solo Argentina\n"
        "Playbook día a día para mercado local\n"
        "INICIO\nDía 1\nFIN\nDía 100\n"
        "META\n465 POSTULACIONES VÁLIDAS\n"
        "MERCADOS\nSolo Argentina\n"
        "CONTENIDO\n1 artículo / semana (~14)\n"
        "RITMO\nMáx. 5 apps / día\n"
        "BASE SALARIAL\nARS 3M+\n"
        "Preparado para\nJUAN ROMANO"
    ),
    "CAMPAIGN PROMISE": (
        "Construí el sistema de búsqueda en los Días 1–7. Desde el Día 8, "
        "cumplí una cuota diaria de hasta 5 postulaciones solo en Argentina, "
        "contactá decisores locales, publicá 1 artículo por semana y medí cada resultado. "
        "El volumen nunca justifica la imprecisión.\n\nVERSIÓN 2.0 · AR-only"
    ),
    "READ THIS FIRST": (
        "Cómo funciona la campaña\n"
        "Las reglas que mantienen útiles, éticas y medibles las 465 postulaciones en Argentina."
    ),
    "THE TWO PHASES": (
        "Los Días 1–7 son un sprint de construcción con cero postulaciones. "
        "Los Días 8–100 son la ejecución sostenida: máximo 5 apps/día, solo Argentina. "
        "El blog se publica 1 vez por semana (días 7, 14, 21… 98) con LinkedIn, X e Instagram Story.\n\n"
        "Fase\nDías\nApps\nResultado principal\n"
        "Construcción\n1–7\n0\nAssets, perfiles AR, plantillas, FAQ, tracker\n"
        "Ejecución\n8–77\n350\n5/día · solo Argentina · portales rotativos\n"
        "Cierre\n78–100\n115\n5/día · solo Argentina\n"
        "Total\n1–100\n465\nSolo mercado Argentina"
    ),
    "What counts as an application": (
        "Contá solo un envío completo a un rol específico y abierto. Crear un perfil, unirte a un talent pool, "
        "mandar un mensaje o guardar una vacante no cuenta.\n"
        "Nunca cuentes duplicados. Un rol enviado por un portal y otra vez por el ATS de la empresa sigue siendo una sola postulación.\n"
        "Postulate solo a roles en Argentina (o remoto que acepte AR) y si cumplís aproximadamente el 60% o más de los requisitos reales.\n"
        "Nada de skills, fechas, títulos, empleadores, alcance o métricas inventadas.\n"
        "Si un portal del día no tiene vacante válida, mové ese slot a un ATS directo de empresa en Argentina y registrá la sustitución.\n"
        "Adaptá al menos una postulación prioritaria del día; el resto usa el CV de la familia de rol más cercana."
    ),
    "Operating assumptions and safeguards": (
        "Ritmo sostenible: hasta 5 postulaciones por día en Argentina y ~2–4 horas enfocadas en días de ejecución. "
        "La semana de construcción puede pedir un poco más.\n"
        "Un segundo trabajo tiene que ser legal y compatible con el actual. Revisá exclusividad, IP, non-compete y horarios antes de aceptar.\n"
        "No engañes a ningún empleador. Nunca reutilices info confidencial.\n"
        "Nunca pagues para postularte ni compartas credenciales bancarias en screening.\n"
        "El contenido semanal tiene que estar fact-checked y anonimizado.\n"
        "Si perdés un día, repartí el déficit en los siguientes siete sin bajar el filtro de calidad."
    ),
    "Definition of a completed day": (
        "La cuota del día (0 en build, hasta 5 en ejecución) está enviada y logueada con links, fuente, mercado AR, CV y follow-up.\n"
        "En días de postulación: 2 outreach nuevos en AR + follow-ups vencidos.\n"
        "Si es día de blog semanal: artículo publicado + LinkedIn + X + Instagram Story.\n"
        "Estados y respuestas actualizados en el tracker antes de cerrar."
    ),
    "TARGETS": (
        "Compensación y posicionamiento (foco Argentina).\n"
        "Mercado\nBase / walk-in\nBuen outcome\nExcelente\nCómo plantearlo\n"
        "Argentina\nARS 3.000.000 bruto/mes\nARS 4.000.000–6.000.000\nARS 7.000.000+\n"
        "Confirmá bruto/neto, beneficios, reviews, bonus y ajuste por inflación."
    ),
    "Direct outreach channels": (
        "Audiencia\nVolumen/día\nDónde identificarlos\nPropósito\n"
        "Reclutador Argentina\n1\nLinkedIn, staffing, talent de la empresa\nFit de rol y vacantes actuales\n"
        "CTO/CEO/HM Argentina\n1\nLinkedIn, página de liderazgo, comunidad\nHipótesis de valor para una necesidad real\n"
        "Referral\n1 cada 2–3 días\nEx colegas, clientes, comunidad\nIntro cálida a un rol concreto\n"
        "Follow-ups\nlos vencidos\nVista del tracker\nCortés, específico, con un valor nuevo"
    ),
    "DAILY EXECUTION": (
        "Día 1 al Día 100\n"
        "Construí siete días. Postulate noventa y tres a ritmo de 5/día en Argentina. "
        "Publicá un artículo útil una vez por semana."
    ),
    "DAILY ORDER": (
        "Abrí Plan → procesá respuestas y follow-ups → sourceá roles AR → adaptá prioritarias → "
        "enviá y logueá (máx. 5) → outreach AR → enfoque especial → "
        "si es día de blog semanal, publicá y redes → cerrá con evidencia."
    ),
}

# Trim intl blob from Argentina recruiters section
ar_recruiters_title = "Argentina — recruiters and communities"

changed = 0
for row in recs:
    title = row.get("title") or ""
    if title in updates:
        row["body"] = updates[title]
        changed += 1
    if title == ar_recruiters_title:
        body = row.get("body") or ""
        cut = body.find("\n\nInternacional")
        if cut != -1:
            row["body"] = body[:cut].rstrip() + "\n"
            changed += 1

path.write_text(json.dumps(recs, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
print(f"updated {changed} recommendation bodies")
