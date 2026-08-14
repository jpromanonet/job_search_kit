# -*- coding: utf-8 -*-
"""Align day copy with 2 master CVs (ES + EN), not 36/6 families."""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DAYS_PATH = ROOT / "data" / "days.json"
PHP_PATH = ROOT / "data" / "days.php"

days = json.loads(DAYS_PATH.read_text(encoding="utf-8"))


def replace_in_str(s: str) -> str:
    reps = [
        (r"(?i)36 archivos", "2 CVs maestros (ES/EN) en DOCX/PDF/TXT"),
        (r"(?i)paquete ATS completo de 36 archivos", "paquete de CVs maestros ES/EN"),
        (r"(?i)Doce versiones de contenido aprobadas,\s*36 archivos utilizables,\s*matriz de selección,\s*manifiesto de activos y una biblioteca de activos de CV organizada",
         "CVs maestros ES/EN aprobados, exportados (DOCX/PDF/TXT) y matriz simple de cuándo usar cada idioma"),
        (r"(?i)seis familias de roles", "roles objetivo"),
        (r"(?i)las seis familias de roles", "los roles objetivo"),
        (r"(?i)seis bancos de palabras clave en inglés a partir de 20 descripciones de puestos representativas por familia de roles",
         "un banco de palabras clave ATS a partir de JDs representativos en Argentina"),
        (r"(?i)Redacte seis CV ATS en inglés, cada uno con un título, un resumen, un orden de habilidades, un énfasis en los logros y una estructura simple de una columna específica de la función",
         "Redactar/actualizar el Master CV (EN) ATS: título, resumen, skills y logros defendibles en una columna"),
        (r"(?i)Redactá seis CV ATS en español con las mismas familias de roles y evidencia que las versiones en inglés",
         "Redactar/actualizar el CV maestro (ES) ATS alineado al Master CV EN (misma evidencia, sin inventar hechos)"),
        (r"(?i)Cree una matriz de selección de una página que asigne los títulos de trabajo comunes a la familia de CV y al idioma correctos",
         "Crear una matriz de una página: título del rol → idioma (ES/EN) y cuándo adaptar el CV maestro"),
        (r"(?i)palabras clave de familia de roles", "palabras clave del rol"),
        (r"(?i)fuente, CV, familia de roles o mensaje", "fuente, CV maestro, mensaje o canal"),
        (r"(?i)conversión de entrevistas por familia de roles y el posicionamiento correcto antes de agregar nuevos materiales",
         "conversión de entrevistas por tipo de rol y ajustar el CV maestro antes de agregar materiales"),
        (r"(?i)CV de la familia más cercana", "CV maestro"),
        (r"(?i)CV del familiar más cercano", "CV maestro"),
        (r"(?i)familia de CV", "CV maestro"),
        (r"(?i)familias de roles", "roles objetivo"),
        (r"(?i)familia de roles", "rol"),
    ]
    out = s
    for pat, rep in reps:
        out = re.sub(pat, rep, out)
    return out


def walk(v):
    if isinstance(v, str):
        return replace_in_str(v)
    if isinstance(v, list):
        return [walk(x) for x in v]
    if isinstance(v, dict):
        return {k: walk(x) for k, x in v.items()}
    return v


for d in days:
    day = int(d["day"])
    for k in list(d.keys()):
        d[k] = walk(d[k])

    # Explicit rewrites for build days
    if day == 1:
        d["candidate_tasks"] = [
            "Aprobar los roles objetivo de la campaña y marcar tecnologías como producción reciente, producción previa, aprendizaje o excluir.",
            "Definir qué va en el CV maestro ES vs Master CV EN (misma evidencia, distinto idioma/énfasis).",
        ]
        d["ai_tasks"] = [
            "Redactar/actualizar el Master CV (EN) ATS: título, resumen, skills y logros defendibles en una columna.",
            "Armar un banco de keywords ATS a partir de JDs representativos en Argentina; marcar términos no respaldados.",
        ]
        d["outcome"] = replace_in_str(d.get("outcome") or "") or (
            "Base de verdad de carrera lista y CVs maestros EN/ES definidos."
        )
    if day == 2:
        d["outcome"] = "Completar el sistema de CV: maestros ES/EN exportados y listos para usar."
        d["definition_of_done"] = (
            "CV maestro ES y Master CV EN aprobados; archivos DOCX/PDF/TXT cargados en Documentos; "
            "matriz simple de cuándo usar cada idioma."
        )
        d["ai_tasks"] = [
            "Redactar/actualizar el CV maestro (ES) ATS alineado al Master CV EN (misma evidencia).",
            "Crear una matriz de una página: título del rol → idioma (ES/EN) y cuándo adaptar el CV maestro.",
        ]
        d["candidate_tasks"] = [
            "Aprobar ambos CVs maestros y subirlos a Documentos.",
            "Validar que no haya claims inventados entre ES y EN.",
        ]
    if day == 3:
        d["outcome"] = (
            "Reconstruir LinkedIn para que el perfil soporte los roles objetivo sin volverse vago, "
            "alineado a los CVs maestros."
        )

DAYS_PATH.write_text(json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
payload = json.dumps(days, ensure_ascii=False, indent=2)
PHP_PATH.write_text(
    "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
    + payload
    + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
    encoding="utf-8",
)

# verify leftover bad phrases
bad = re.compile(
    r"(?i)36 archivo|seis CV|6 CV ATS|seis familias|doce versiones|familia de CV|familiar m[aá]s"
)
hits = 0
for d in days:
    blob = json.dumps(d, ensure_ascii=False)
    if bad.search(blob):
        # ignore blog drafts
        for k in ["special_focus", "outcome", "definition_of_done", "execute_today", "candidate_tasks", "ai_tasks", "close_day_proof"]:
            v = d.get(k)
            s = json.dumps(v, ensure_ascii=False)
            if bad.search(s):
                print("still", d["day"], k, bad.search(s).group(0))
                hits += 1
print("remaining", hits)
