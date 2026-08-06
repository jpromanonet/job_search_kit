#!/usr/bin/env python3
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
bodies = {
    "summary-facts-es": (
        "HECHOS DE CARRERA (fuente de verdad)\n\nNombre:\nUbicación / modalidad:\nDisponibilidad:\n\n"
        "Empleadores (nombre, título, fechas, alcance, tamaño de equipo, tecnologías, resultados):\n-\n\n"
        "Proyectos públicos / portfolio:\n-\n\nMétricas verificables:\n-\n\nClaims a verificar / no publicar:\n-"
    ),
    "summary-facts-en": (
        "CAREER FACTS (source of truth)\n\nName:\nLocation / work mode:\nAvailability:\n\n"
        "Employers (name, title, dates, scope, team size, technologies, outcomes):\n-\n\n"
        "Public projects / portfolio:\n-\n\nVerifiable metrics:\n-\n\nClaims to verify / do not publish:\n-"
    ),
    "summary-achievements-es": (
        "BANCO DE LOGROS\nFormato: acción + contexto + resultado + evidencia\n\n"
        "1)\n2)\n3)\n4)\n5)\n\nUsar solo hechos aprobados en Hechos de carrera."
    ),
    "summary-achievements-en": (
        "ACHIEVEMENT BANK\nFormat: action + context + result + evidence\n\n"
        "1)\n2)\n3)\n4)\n5)\n\nUse only facts approved in Career Facts."
    ),
    "cover-es": (
        "Estimado equipo,\n\nMe postulo a {ROL} en {EMPRESA}. Puedo aportar {EVIDENCIA} "
        "y un plan práctico de 90 días orientado a {NECESIDAD}.\n\nGracias,\nJuan Romano"
    ),
    "cover-en": (
        "Dear Hiring Team,\n\nI am applying for the {ROLE} role at {COMPANY}. I can contribute {EVIDENCE} "
        "and a practical 90-day approach focused on {NEED}.\n\nThank you for your consideration,\nJuan Romano"
    ),
    "msg-recruiter-es": (
        "Hola {NOMBRE},\n\nVi la búsqueda de {ROL} en {EMPRESA} y creo que hay buen fit por {EVIDENCIA}.\n"
        "Estoy abierto/a a una conversación breve esta semana.\n\nSaludos,\nJuan Romano"
    ),
    "msg-recruiter-en": (
        "Hi {NAME},\n\nI saw the {ROLE} opening at {COMPANY}. Based on {EVIDENCE}, I may be a strong fit.\n"
        "Open to a short conversation this week.\n\nBest,\nJuan Romano"
    ),
    "msg-cto-es": (
        "Hola {NOMBRE},\n\nSoy Juan Romano (technical lead / engineering management). "
        "Vi {NECESIDAD} en {EMPRESA} y puedo aportar {EVIDENCIA}.\n"
        "¿Tenés 15 minutos para validar si tiene sentido?\n\nGracias"
    ),
    "msg-cto-en": (
        "Hi {NAME},\n\nI'm Juan Romano (technical leadership + hands-on delivery). "
        "I noticed {NEED} at {COMPANY} and can bring {EVIDENCE}.\nWould a 15-minute chat be useful?\n\nThanks"
    ),
    "msg-hm-es": (
        "Hola {NOMBRE},\n\nMe postulo a {ROL}. Evidencia relevante: {EVIDENCIA}. "
        "Puedo compartir un enfoque concreto de 90 días.\n\nJuan Romano"
    ),
    "msg-hm-en": (
        "Hi {NAME},\n\nApplying for {ROLE}. Relevant proof: {EVIDENCE}. "
        "Happy to walk through a concrete 90-day approach.\n\nJuan Romano"
    ),
    "msg-referral-es": (
        "Hola {NOMBRE},\n\nEspero que estés bien. Estoy explorando roles de {ROLE_FAMILY} y vi {EMPRESA}/{ROL}.\n"
        "¿Podrías referirme o presentarme al hiring manager?\n\nGracias"
    ),
    "msg-referral-en": (
        "Hi {NAME},\n\nHope you're well. I'm exploring {ROLE_FAMILY} roles and saw {COMPANY}/{ROLE}.\n"
        "Would you be open to a referral or intro to the hiring manager?\n\nThanks"
    ),
    "msg-followup-1-es": (
        "Hola {NOMBRE},\n\nTe escribo por mi postulación a {ROL} ({FECHA}). "
        "Puedo compartir un case study corto o aclarar fit.\n\nJuan"
    ),
    "msg-followup-1-en": (
        "Hi {NAME},\n\nFollowing up on my application for {ROLE} ({DATE}). "
        "Happy to share a short case study or clarify fit.\n\nJuan"
    ),
    "msg-followup-2-es": (
        "Hola {NOMBRE},\n\nSegundo follow-up sobre {ROL}. "
        "Sigo interesado; puedo adaptar disponibilidad a su proceso.\n\nJuan"
    ),
    "msg-followup-2-en": (
        "Hi {NAME},\n\nQuick second follow-up on {ROLE}. "
        "Still interested; I can adapt availability around your process.\n\nJuan"
    ),
    "msg-thankyou-es": (
        "Hola {NOMBRE},\n\nGracias por la conversación sobre {ROL}. Valoro especialmente {PUNTO}.\n"
        "Puedo enviar cualquier material de seguimiento.\n\nJuan Romano"
    ),
    "msg-thankyou-en": (
        "Hi {NAME},\n\nThank you for the conversation about {ROLE}. I especially valued {POINT}.\n"
        "Happy to send any follow-up materials.\n\nJuan Romano"
    ),
    "msg-salary-es": (
        "Gracias por la pregunta. ¿Qué rango de compensación está aprobado para este rol?\n"
        "Según el alcance y el paquete total, estoy apuntando a {RANGO}, "
        "con flexibilidad según responsabilidades y términos."
    ),
    "msg-salary-en": (
        "Thanks for asking. What compensation range is approved for this role?\n"
        "Based on scope and total package, I'm targeting {RANGE}, "
        "with flexibility for responsibilities and terms."
    ),
}

groups_meta = [
    ("Technical Lead / Software Delivery Lead", "cv-tech-lead-es", "cv", "es", 10),
    ("Engineering Manager / Head of Engineering", "cv-em-es", "cv", "es", 20),
    ("Senior Full-stack Software Engineer", "cv-fullstack-es", "cv", "es", 30),
    ("Platform / DevOps / Observability", "cv-devops-es", "cv", "es", 40),
    ("Solutions / Implementation / TAM", "cv-tam-es", "cv", "es", 50),
    ("IT Manager / App Support / Infra Lead", "cv-it-manager-es", "cv", "es", 60),
    ("Technical Lead / Software Delivery Lead", "cv-tech-lead-en", "cv", "en", 110),
    ("Engineering Manager / Head of Engineering", "cv-em-en", "cv", "en", 120),
    ("Senior Full-stack Software Engineer", "cv-fullstack-en", "cv", "en", 130),
    ("Platform / DevOps / Observability", "cv-devops-en", "cv", "en", 140),
    ("Solutions / Implementation / TAM", "cv-tam-en", "cv", "en", 150),
    ("IT Manager / App Support / Infra Lead", "cv-it-manager-en", "cv", "en", 160),
    ("Carta de presentación (ES)", "cover-es", "cover_letter", "es", 200),
    ("Cover letter (EN)", "cover-en", "cover_letter", "en", 210),
    ("Hechos de carrera / Summary (ES)", "summary-facts-es", "summary", "es", 300),
    ("Career Facts / Summary (EN)", "summary-facts-en", "summary", "en", 305),
    ("Banco de logros (ES)", "summary-achievements-es", "summary", "es", 310),
    ("Achievement bank (EN)", "summary-achievements-en", "summary", "en", 315),
    ("Mensaje reclutador (ES)", "msg-recruiter-es", "message", "es", 400),
    ("Recruiter message (EN)", "msg-recruiter-en", "message", "en", 410),
    ("Mensaje CTO/CEO (ES)", "msg-cto-es", "message", "es", 420),
    ("CTO/CEO message (EN)", "msg-cto-en", "message", "en", 430),
    ("Mensaje hiring manager (ES)", "msg-hm-es", "message", "es", 440),
    ("Hiring manager message (EN)", "msg-hm-en", "message", "en", 450),
    ("Pedido de referral (ES)", "msg-referral-es", "message", "es", 460),
    ("Referral ask (EN)", "msg-referral-en", "message", "en", 470),
    ("Follow-up 1 (ES)", "msg-followup-1-es", "message", "es", 480),
    ("Follow-up 1 (EN)", "msg-followup-1-en", "message", "en", 490),
    ("Follow-up 2 (ES)", "msg-followup-2-es", "message", "es", 500),
    ("Follow-up 2 (EN)", "msg-followup-2-en", "message", "en", 510),
    ("Agradecimiento (ES)", "msg-thankyou-es", "message", "es", 520),
    ("Thank-you note (EN)", "msg-thankyou-en", "message", "en", 530),
    ("Script salarial (ES)", "msg-salary-es", "message", "es", 540),
    ("Salary script (EN)", "msg-salary-en", "message", "en", 550),
]

out = []
for i, (name, slug, cat, lang, sort) in enumerate(groups_meta, 1):
    out.append(
        {
            "id": i,
            "name": name,
            "slug": slug,
            "category": cat,
            "language": lang,
            "body_text": bodies.get(slug),
            "sort_order": sort,
            "description": None,
        }
    )

(root / "data" / "document_groups.json").write_text(
    json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
)
(root / "data" / "document_files.json").write_text("[]\n", encoding="utf-8")
print(f"wrote {len(out)} groups")
