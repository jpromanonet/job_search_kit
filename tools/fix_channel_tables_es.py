#!/usr/bin/env python3
"""Rebuild channel/portal recommendation tables: keep names+URLs, translate Use only."""
from __future__ import annotations

import json
import time
from pathlib import Path

from deep_translator import GoogleTranslator

ROOT = Path(__file__).resolve().parents[1]
EN = ROOT / "data" / "recommendations_en.json"
ES = ROOT / "data" / "recommendations.json"

CHANNEL_TITLES = {
    "Argentina — job boards and search",
    "Argentina — recruiters and communities",
    "Direct outreach channels",
    "Six CV role families",
    "THE TWO PHASES",
    "TARGETS",
    "Candidate and AI copilot responsibilities",
    "Core MySQL tables",
    "Calculations and controls",
    "Recommended implementation",
}

HEADER_MAP = {
    "Channel": "Canal",
    "Home": "URL",
    "Use": "Uso",
    "Audience": "Audiencia",
    "Daily volume": "Volumen/día",
    "Where to identify them": "Dónde identificarlos",
    "Purpose": "Propósito",
    "Phase": "Fase",
    "Days": "Días",
    "Applications": "Apps",
    "Primary outcome": "Resultado principal",
    "Market": "Mercado",
    "Base / walk-in target": "Base / walk-in",
    "Good outcome": "Buen outcome",
    "Excellent outcome": "Excelente",
    "How to state it": "Cómo plantearlo",
    "#": "#",
    "Family": "Familia",
    "Primary evidence": "Evidencia principal",
    "Candidate owns": "Candidato es dueño de",
    "AI copilot supports": "AI copilot apoya",
    "Table": "Tabla",
    "Minimum fields": "Campos mínimos",
    "Key relationship": "Relación",
    "Metric / rule": "Métrica / regla",
    "Definition": "Definición",
    "Layer": "Capa",
    "Recommendation": "Recomendación",
    "Reason": "Motivo",
}

SECTION_HEADERS = {
    "International — remote boards": "Internacional — bolsas remotas",
    "International — talent networks and firms": "Internacional — redes y firms de talento",
    "Long-term contract marketplaces": "Marketplaces de contrato largo",
}


def tr(text: str, translator: GoogleTranslator, cache: dict[str, str]) -> str:
    text = text.strip()
    if not text:
        return text
    if text in cache:
        return cache[text]
    if text in HEADER_MAP:
        cache[text] = HEADER_MAP[text]
        return HEADER_MAP[text]
    if text in SECTION_HEADERS:
        cache[text] = SECTION_HEADERS[text]
        return SECTION_HEADERS[text]
    # Don't translate URLs / short codes
    low = text.lower()
    if low.startswith("http") or low.startswith("www.") or "@" in text:
        cache[text] = text
        return text
    for attempt in range(5):
        try:
            out = translator.translate(text) or text
            cache[text] = out
            time.sleep(0.07)
            return out
        except Exception as exc:  # noqa: BLE001
            time.sleep(1.0 * (attempt + 1))
            print(" retry", exc)
    cache[text] = text
    return text


def rebuild_channel_body(body: str, cols: int, translator: GoogleTranslator, cache: dict[str, str]) -> str:
    """For Channel/Home/Use tables: translate only col index 2 (Use) and headers/section titles."""
    lines = body.split("\n")
    out: list[str] = []
    i = 0
    # optional intro lines before Channel
    while i < len(lines) and lines[i].strip() not in ("Channel", "Audience", "Phase", "Market", "#", "Candidate owns", "Table", "Metric / rule", "Layer"):
        # section title mid-body
        if lines[i].strip() in SECTION_HEADERS or "—" in lines[i]:
            out.append(tr(lines[i], translator, cache) if lines[i].strip() else "")
            i += 1
            continue
        if lines[i].strip() == "":
            out.append("")
            i += 1
            continue
        # intro prose
        out.append(tr(lines[i], translator, cache))
        i += 1

    # Now process remaining as repeating rows; detect header triplets
    while i < len(lines):
        line = lines[i]
        s = line.strip()
        if s == "":
            out.append("")
            i += 1
            continue
        if s in SECTION_HEADERS or (s.startswith("International") or s.startswith("Long-term")):
            out.append(tr(s, translator, cache))
            i += 1
            continue
        if s in HEADER_MAP:
            # emit mapped headers for this block
            hdrs = []
            for _ in range(cols):
                if i < len(lines) and lines[i].strip() in HEADER_MAP:
                    hdrs.append(HEADER_MAP[lines[i].strip()])
                    i += 1
                else:
                    break
            out.extend(hdrs)
            continue

        # data row: cols lines
        chunk = []
        for c in range(cols):
            if i >= len(lines):
                break
            chunk.append(lines[i])
            i += 1
        if len(chunk) < cols:
            out.extend(chunk)
            break

        # For 3-col channel tables: keep name+url, translate use
        if cols == 3:
            name, url, use = chunk
            # If middle doesn't look like URL, translate all carefully
            if url.lower().startswith("http") or url.lower().startswith("www.") or "." in url and " " not in url:
                out.append(name)  # keep brand
                out.append(url)
                out.append(tr(use, translator, cache))
            else:
                out.append(tr(name, translator, cache))
                out.append(tr(url, translator, cache))
                out.append(tr(use, translator, cache))
        elif cols == 4:
            # Direct outreach / phases: translate all cells except pure numbers
            for cell in chunk:
                if cell.strip().replace("–", "-").replace("/", "").replace(" ", "").isdigit() or cell.strip() in {"1", "4", "0", "770", "230", "1,000", "1.000"}:
                    out.append(cell)
                elif cell.strip().startswith("1 every") or "/day" in cell or "AR" in cell:
                    out.append(tr(cell, translator, cache))
                else:
                    out.append(tr(cell, translator, cache))
        else:
            for cell in chunk:
                out.append(tr(cell, translator, cache))

    return "\n".join(out)


COLS = {
    "Argentina — job boards and search": 3,
    "Argentina — recruiters and communities": 3,
    "Direct outreach channels": 4,
    "Six CV role families": 3,
    "THE TWO PHASES": 4,
    "TARGETS": 5,
    "Candidate and AI copilot responsibilities": 2,
    "Core MySQL tables": 3,
    "Calculations and controls": 2,
    "Recommended implementation": 3,
}


def main() -> None:
    en_rows = {r["title"]: r["body"] for r in json.loads(EN.read_text(encoding="utf-8"))}
    es_rows = json.loads(ES.read_text(encoding="utf-8"))
    cache: dict[str, str] = {}
    translator = GoogleTranslator(source="en", target="es")

    for row in es_rows:
        title = row["title"]
        if title not in CHANNEL_TITLES:
            continue
        print("fix", title)
        cols = COLS[title]
        row["body"] = rebuild_channel_body(en_rows[title], cols, translator, cache)

    ES.write_text(json.dumps(es_rows, ensure_ascii=False, indent=2), encoding="utf-8")
    print("done")


if __name__ == "__main__":
    main()
