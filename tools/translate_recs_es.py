#!/usr/bin/env python3
"""Translate recommendations.json EN→ES line-by-line; strip calendar dates."""
from __future__ import annotations

import json
import re
import time
from pathlib import Path

from deep_translator import GoogleTranslator

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "data" / "recommendations_en.json"
PATH = ROOT / "data" / "recommendations.json"
PROGRESS = ROOT / "data" / "recommendations_es_progress.json"
CACHE = ROOT / "data" / ".translate_rec_cache.json"

HAND = {
    "Campaña": (
        "100 DÍAS  •  1.000 POSTULACIONES\n"
        "La campaña full-time como segundo trabajo\n"
        "Playbook día a día para Argentina y mercados internacionales\n"
        "INICIO\n"
        "Día 1\n"
        "FIN\n"
        "Día 100\n"
        "META\n"
        "1.000 POSTULACIONES VÁLIDAS\n"
        "MERCADOS\n"
        "500 AR / 500 INTL\n"
        "CONTENIDO\n"
        "100 ARTÍCULOS\n"
        "BASE SALARIAL\n"
        "ARS 3M / USD 2.500\n"
        "Preparado para\n"
        "JUAN ROMANO"
    ),
    "CAMPAIGN PROMISE": (
        "Construí el sistema completo de búsqueda en los Días 1–7. "
        "Desde el Día 8, cumplí una cuota diaria precisa, contactá a decisores, "
        "publicá prueba de expertise y medí cada resultado. "
        "El volumen nunca justifica la imprecisión.\n\n"
        "VERSIÓN 1.0"
    ),
    "READ THIS FIRST": (
        "Cómo funciona la campaña\n"
        "Las reglas que mantienen útiles, éticas y medibles las 1.000 postulaciones."
    ),
}


def load_cache() -> dict[str, str]:
    if CACHE.is_file():
        return json.loads(CACHE.read_text(encoding="utf-8"))
    return {}


def save_cache(cache: dict[str, str]) -> None:
    CACHE.write_text(json.dumps(cache, ensure_ascii=False), encoding="utf-8")


def skip_line(line: str) -> bool:
    s = line.strip()
    if not s:
        return True
    if re.match(r"^https?://", s, re.I):
        return True
    if re.match(r"^www\.", s, re.I):
        return True
    if re.match(r"^[A-Za-z0-9._+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$", s):
        return True
    # Pure codes / names that shouldn't translate badly if kept
    return False


def translate_line(line: str, cache: dict[str, str], tr: GoogleTranslator) -> str:
    if skip_line(line):
        return line
    if line in cache:
        return cache[line]
    # Skip pure numbers / short tokens
    if re.fullmatch(r"[\d–\-./%+\sARIntlUSDARS•·]+", line.strip()):
        cache[line] = line
        return line
    for attempt in range(6):
        try:
            t = tr.translate(line)
            if not t:
                raise RuntimeError("empty translation")
            cache[line] = t
            time.sleep(0.08)
            return t
        except Exception as exc:  # noqa: BLE001
            time.sleep(1.2 * (attempt + 1))
            print("  retry", exc)
    cache[line] = line
    return line


def translate_body(text: str, cache: dict[str, str], tr: GoogleTranslator) -> str:
    lines = text.split("\n")
    return "\n".join(translate_line(line, cache, tr) for line in lines)


def main() -> None:
    src = SRC if SRC.is_file() else PATH
    rows = json.loads(src.read_text(encoding="utf-8"))
    cache = load_cache()
    tr = GoogleTranslator(source="en", target="es")
    out: list[dict] = []
    start = 0
    if PROGRESS.is_file():
        out = json.loads(PROGRESS.read_text(encoding="utf-8"))
        start = len(out)
        print(f"resuming at {start}")

    for i in range(start, len(rows)):
        row = rows[i]
        title = row["title"]
        print(f"[{i + 1}/{len(rows)}] {title}")
        if title in HAND:
            body = HAND[title]
        else:
            body = translate_body(row["body"], cache, tr)
        out.append({"title": title, "body": body})
        PROGRESS.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
        save_cache(cache)

    PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    PROGRESS.unlink(missing_ok=True)
    print("done")


if __name__ == "__main__":
    main()
