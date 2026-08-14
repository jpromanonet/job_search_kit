# -*- coding: utf-8 -*-
"""Rebalance 100-day plan: AR-only, max 5 apps/day, 1 blog/week."""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DAYS_PATH = ROOT / "data" / "days.json"
PHP_PATH = ROOT / "data" / "days.php"

TARGET_TOTAL = 465  # 93 execution days * 5
APPS_PER_DAY = 5

AR_PATTERNS = [
    {
        "allocation": "LinkedIn ×2; Bumeran ×1; Computrabajo ×1; ATS directo ×1",
        "validation": "Roles AR específicos; CV maestro aprobado",
    },
    {
        "allocation": "LinkedIn ×2; ZonaJobs ×1; Indeed AR ×1; ATS directo ×1",
        "validation": "Roles AR específicos; CV maestro aprobado",
    },
    {
        "allocation": "LinkedIn ×1; Bumeran ×1; Computrabajo ×1; Torre ×1; ATS directo ×1",
        "validation": "Roles AR específicos; CV maestro aprobado",
    },
    {
        "allocation": "LinkedIn ×2; Glassdoor AR ×1; ZonaJobs ×1; ATS directo ×1",
        "validation": "Roles AR específicos; CV maestro aprobado",
    },
    {
        "allocation": "LinkedIn ×1; Bumeran ×1; Indeed AR ×1; Computrabajo ×1; ATS directo ×1",
        "validation": "Roles AR específicos; CV maestro aprobado",
    },
]


def is_blog_week(day: int) -> bool:
    """1 artículo/semana: Día 7, 14, 21, …, 98."""
    return day >= 7 and day % 7 == 0


def phase_for(day: int) -> tuple[str, str]:
    if day <= 7:
        return "build", "Construcción"
    if day <= 77:
        return "high_volume", "Ejecución"
    return "finish", "Cierre"


def execute_build(day: int, blog: bool) -> list[str]:
    tasks = [
        "Avanzar el enfoque especial del día y dejar evidencia en Documentos / Tracker.",
        "Revisar perfiles AR (LinkedIn, Bumeran, Computrabajo, Get on Board) sin enviar postulaciones todavía.",
        "Preparar plantillas y CV maestro ES para la ejecución.",
    ]
    if blog:
        tasks.append("Publicar el artículo de la semana y promocionarlo en LinkedIn, X e Instagram Story.")
    tasks.append("Cerrar el día con definición de hecho cumplida (0 postulaciones en construcción).")
    return tasks


def execute_run(day: int, cum: int, blog: bool) -> list[str]:
    tasks = [
        f"Buscar y preseleccionar ~10 roles en los portales AR del día; descartar duplicados, cerrados y mal fit.",
        "Elegir la familia de CV más cercana; carta solo si suma. Sin inventar claims.",
        f"Enviar y loguear hasta {APPS_PER_DAY} postulaciones válidas (solo Argentina).",
        "Enviar 2 outreach nuevos en AR (reclutador y/o hiring manager) + follow-ups vencidos.",
    ]
    if blog:
        tasks.append("Publicar el artículo de la semana + LinkedIn + X + Instagram Story.")
    tasks.append(f"Cerrar el día con evidencia. Acumulado objetivo del día: {cum}/{TARGET_TOTAL}.")
    return tasks


def proof_build(blog: bool) -> str:
    bits = ["Enfoque especial hecho", "perfiles/plantillas AR listos", "0 postulaciones"]
    if blog:
        bits.append("artículo de la semana publicado + redes")
    return "; ".join(bits) + "."


def proof_run(day_apps: int, cum: int, blog: bool) -> str:
    bits = [
        f"Hasta {day_apps} registros AR válidos",
        "2 outreach / follow-ups del día",
        f"Acumulado {cum}/{TARGET_TOTAL}",
    ]
    if blog:
        bits.insert(2, "artículo semanal + redes")
    return "; ".join(bits) + ". Si un portal no rinde, reemplazar por ATS directo AR y anotar el motivo."


def main() -> None:
    days = json.loads(DAYS_PATH.read_text(encoding="utf-8"))
    cumulative = 0
    blog_days = 0

    for d in days:
        day = int(d["day"])
        phase, phase_label = phase_for(day)
        blog = is_blog_week(day)
        d["phase"] = phase
        d["phase_label"] = phase_label
        d["blog_publish"] = blog
        if blog:
            blog_days += 1

        if day <= 7:
            d["applications_target"] = 0
            d["quota_label"] = "0 postulaciones · construcción AR"
            d["market_split"] = "0AR"
            d["source_allocations"] = [
                {
                    "market": "Argentina",
                    "allocation": "Sin envíos — solo setup de perfiles, alertas y plantillas AR",
                    "apps": "0",
                    "validation": "Assets listos para Día 8",
                }
            ]
            d["execute_today"] = execute_build(day, blog)
            d["close_day_proof"] = proof_build(blog)
        else:
            cumulative += APPS_PER_DAY
            pat = AR_PATTERNS[(day - 8) % len(AR_PATTERNS)]
            d["applications_target"] = APPS_PER_DAY
            d["quota_label"] = f"{APPS_PER_DAY} postulaciones · solo Argentina"
            d["market_split"] = f"{APPS_PER_DAY}AR"
            d["source_allocations"] = [
                {
                    "market": "Argentina",
                    "allocation": pat["allocation"],
                    "apps": str(APPS_PER_DAY),
                    "validation": pat["validation"],
                }
            ]
            d["execute_today"] = execute_run(day, cumulative, blog)
            d["close_day_proof"] = proof_run(APPS_PER_DAY, cumulative, blog)

        # Soften special_focus / outcome mentions of intl volume if present
        for key in ("special_focus", "outcome", "definition_of_done"):
            val = d.get(key)
            if isinstance(val, str):
                val2 = re.sub(r"(?i)\binternacional\b", "Argentina", val)
                val2 = re.sub(r"(?i)\bintl\b", "AR", val2)
                d[key] = val2

        # Non-publish weeks: keep article draft as bank, but clear "must post" social pressure in labels via blog_publish
        # Keep blog_* content always (technical bank). Publishing only when blog_publish.

    assert cumulative == TARGET_TOTAL, (cumulative, TARGET_TOTAL)

    DAYS_PATH.write_text(
        json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    PHP_PATH.write_text(
        "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )
    print(f"days={len(days)} apps_total={cumulative} blog_days={blog_days}")
    print(f"wrote {DAYS_PATH}")
    print(f"wrote {PHP_PATH}")


if __name__ == "__main__":
    main()
