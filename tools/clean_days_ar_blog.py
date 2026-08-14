# -*- coding: utf-8 -*-
"""Full AR-only cleanup of 100 days + blog on 7,14,...,98."""
from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DAYS_PATH = ROOT / "data" / "days.json"
PHP_PATH = ROOT / "data" / "days.php"

BLOG_FIELDS = [
    "blog_title",
    "blog_angle",
    "blog_draft",
    "x_copy",
    "linkedin_copy",
    "instagram_task",
]

# Portales / redes internacionales o basura de reemplazos previos
BAN = re.compile(
    r"(?i)\b("
    r"get on board|wellfound|remote\s*ok|remoteok|remote rocketship|"
    r"working nomads|dynamite jobs|jobgether|remotive|toptal|turing|"
    r"revelo|andela|vanhack|himalayas|angellist|built\s*in|"
    r"we work remotely|latam jobs|yc work|work at a startup|"
    r"crossover|no.?desk|justremote|jobspresso|dailyremote|"
    r"welcome to the jungle|lathire|puente|arc\.dev|braintrust|"
    r"a\.team|lemon\.io|tecla|terminal\.io|howdy|clouddevs|"
    r"proxify|x-team|nearsure|strider|g2i|gun\.io|"
    r"internacionales?|\bintl\b|estados unidos|\busa\b|"
    r"portales AR at a Startup|500\s*/\s*500"
    r")\b"
)

AR_PATTERNS = [
    "LinkedIn ×2; Bumeran ×1; Computrabajo ×1; ATS directo ×1",
    "LinkedIn ×2; ZonaJobs ×1; Indeed AR ×1; ATS directo ×1",
    "LinkedIn ×1; Bumeran ×1; Computrabajo ×1; Torre ×1; ATS directo ×1",
    "LinkedIn ×2; Glassdoor AR ×1; ZonaJobs ×1; ATS directo ×1",
    "LinkedIn ×1; Bumeran ×1; Indeed AR ×1; Computrabajo ×1; ATS directo ×1",
]


def is_blog_day(day: int) -> bool:
    return day >= 7 and day % 7 == 0


def load_git_days() -> dict[int, dict]:
    raw = subprocess.check_output(["git", "show", "HEAD:data/days.json"], cwd=ROOT)
    return {int(d["day"]): d for d in json.loads(raw.decode("utf-8"))}


def scrub_str(s: str) -> str:
    if not s:
        return s
    # Remove banned tokens / phrases, keep readability
    s = BAN.sub("", s)
    s = re.sub(r"(?i)portales AR(?:\s*,\s*portales AR)+", "portales AR", s)
    s = re.sub(r"\s{2,}", " ", s)
    s = re.sub(r"\s+,", ",", s)
    s = re.sub(r",\s*,+", ", ", s)
    s = re.sub(r"\s+y\s+y\s+", " y ", s)
    s = re.sub(r":\s*,", ":", s)
    return s.strip(" ,;.")


def is_banned_item(s: str) -> bool:
    return bool(BAN.search(s))


def clean_list(items: list | None) -> list:
    out = []
    for item in items or []:
        if not isinstance(item, str):
            continue
        if is_banned_item(item):
            # try scrub; drop if still mostly garbage
            cleaned = scrub_str(item)
            if not cleaned or is_banned_item(cleaned) or len(cleaned) < 20:
                continue
            item = cleaned
        else:
            item = scrub_str(item)
        if item:
            out.append(item)
    return out


def main() -> None:
    days = json.loads(DAYS_PATH.read_text(encoding="utf-8"))
    git_days = load_git_days()
    by = {int(d["day"]): d for d in days}

    # Prefer current D1 article (if any) else git D1 for first publish on D7
    d1_blog = {k: (by[1].get(k) or git_days[1].get(k)) for k in BLOG_FIELDS}
    if not d1_blog.get("blog_title"):
        d1_blog = {k: git_days[1].get(k) for k in BLOG_FIELDS}

    for d in days:
        day = int(d["day"])
        blog = is_blog_day(day)
        d["blog_publish"] = blog
        apps = 0 if day <= 7 else 5

        d["applications_target"] = apps
        d["market_split"] = "0AR" if apps == 0 else "5AR"
        d["quota_label"] = (
            "0 postulaciones · construcción AR"
            if apps == 0
            else "5 postulaciones · solo Argentina"
        )

        if day <= 7:
            d["source_allocations"] = [
                {
                    "market": "Argentina",
                    "allocation": "Sin envíos — setup de perfiles y plantillas AR (LinkedIn, Bumeran, Computrabajo, ZonaJobs, Indeed AR, Glassdoor AR, Torre, ATS)",
                    "apps": "0",
                    "validation": "Assets listos para Día 8",
                }
            ]
            ex = [
                "Avanzar el enfoque especial del día y dejar evidencia en Documentos / Tracker.",
                "Revisar perfiles AR (LinkedIn, Bumeran, Computrabajo, ZonaJobs, Indeed AR, Glassdoor AR, Torre) sin enviar postulaciones todavía.",
                "Preparar plantillas y CV maestro ES para la ejecución.",
            ]
            if blog:
                ex.append("Publicar el artículo de la semana + LinkedIn + X + Instagram Story.")
            ex.append("Cerrar el día con definición de hecho cumplida (0 postulaciones en construcción).")
            d["execute_today"] = ex
            d["close_day_proof"] = (
                "Enfoque especial hecho; perfiles/plantillas AR listos; 0 postulaciones"
                + ("; artículo semanal + redes" if blog else "")
                + "."
            )
        else:
            pat = AR_PATTERNS[(day - 8) % len(AR_PATTERNS)]
            d["source_allocations"] = [
                {
                    "market": "Argentina",
                    "allocation": pat,
                    "apps": "5",
                    "validation": "Roles AR específicos; CV maestro aprobado",
                }
            ]
            cum = (day - 7) * 5
            ex = [
                "Buscar y preseleccionar ~10 roles en los portales AR del día; descartar duplicados, cerrados y mal fit.",
                "Adaptar el CV maestro ES al JD; carta solo si suma. Sin inventar claims.",
                "Enviar y loguear hasta 5 postulaciones válidas (solo Argentina).",
                "Enviar 2 outreach nuevos en AR (reclutador y/o hiring manager) + follow-ups vencidos.",
            ]
            if blog:
                ex.append("Publicar el artículo de la semana + LinkedIn + X + Instagram Story.")
            ex.append(f"Cerrar el día con evidencia. Acumulado objetivo del día: {cum}/465.")
            d["execute_today"] = ex
            d["close_day_proof"] = (
                "Hasta 5 registros AR válidos; 2 outreach/follow-ups"
                + ("; artículo semanal + redes" if blog else "")
                + f"; acumulado {cum}/465."
            )

        # Blog fields
        if blog:
            if day == 7:
                for k in BLOG_FIELDS:
                    d[k] = d1_blog.get(k)
            else:
                src = git_days.get(day, {})
                for k in BLOG_FIELDS:
                    d[k] = src.get(k) or d.get(k)
        else:
            for k in BLOG_FIELDS:
                d[k] = None

        # Scrub remaining free-text fields
        for key in ("special_focus", "outcome", "definition_of_done"):
            if isinstance(d.get(key), str):
                d[key] = scrub_str(d[key]) or d[key]
                if is_banned_item(d[key]):
                    # rewrite neutrally
                    if key == "special_focus":
                        d[key] = "Enfoque del día en mercado Argentina: evidencia, perfiles y seguimiento."
                    elif key == "outcome":
                        d[key] = "Avance medible del día en la campaña Argentina."
                    else:
                        d[key] = "Criterios del día cumplidos con evidencia en Tracker/Documentos."

        d["candidate_tasks"] = clean_list(d.get("candidate_tasks"))
        d["ai_tasks"] = clean_list(d.get("ai_tasks"))

        # Day-specific known bad leftovers
        if day == 6:
            d["outcome"] = "Perfiles AR nivel 1 listos: LinkedIn, Bumeran, Computrabajo, ZonaJobs, Indeed AR, Glassdoor AR y Torre."
            d["candidate_tasks"] = [
                "Completar perfiles y alertas en LinkedIn, Bumeran, Computrabajo, ZonaJobs, Indeed AR, Glassdoor AR y Torre.",
                "Documentar keywords y filtros usados por portal AR.",
            ]
            d["ai_tasks"] = [
                "Sugerir keywords ATS defendibles para perfiles AR.",
                "Armar checklist de calidad de perfil por portal AR.",
            ]
            d["definition_of_done"] = "Perfiles AR configurados con alertas; sin postulaciones todavía."
        if day == 7:
            d["outcome"] = "Canales AR listos y primer artículo de la semana publicado."
            d["definition_of_done"] = (
                "Perfiles/alertas AR listos; primer artículo de la semana publicado con LinkedIn, X e Instagram."
            )
            d["candidate_tasks"] = [
                "Cerrar gaps de perfiles AR pendientes.",
                "Publicar el artículo de la semana y cargar el link en el Plan.",
            ]
            d["ai_tasks"] = [
                "Revisar borrador del artículo y copies de LinkedIn/X/IG.",
            ]
        if day in (74, 75, 100):
            d["special_focus"] = scrub_str(d.get("special_focus") or "")
            d["special_focus"] = re.sub(r"(?i)465 AR", "465 postulaciones AR", d["special_focus"])
            if day == 74:
                d["special_focus"] = "Auditoría semanal: ritmo restante hacia 465 postulaciones AR."
            if day == 75:
                d["special_focus"] = "Preparar el tramo final priorizando portales AR de mayor conversión."
            if day == 100:
                d["special_focus"] = "Cerrar las postulaciones finales AR y escribir la retrospectiva de la campaña."

    DAYS_PATH.write_text(json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    PHP_PATH.write_text(
        "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )

    # verify
    text = DAYS_PATH.read_text(encoding="utf-8")
    print("blog days", [d["day"] for d in days if d.get("blog_publish")])
    print("D7 title", next(d["blog_title"] for d in days if d["day"] == 7))
    print("Get on Board left", len(re.findall(r"(?i)get on board", text)))
    print("intl left", len(re.findall(r"(?i)internacional|wellfound|remote ok|toptal", text)))


if __name__ == "__main__":
    main()
