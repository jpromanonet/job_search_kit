#!/usr/bin/env python3
"""Translate days.json EN→ES, strip calendar dates, regenerate days.php."""
from __future__ import annotations

import json
import re
import time
from pathlib import Path

from deep_translator import GoogleTranslator

ROOT = Path(__file__).resolve().parents[1]
DAYS_JSON = ROOT / "data" / "days.json"
CACHE = ROOT / "data" / ".translate_cache.json"
BATCH = 25  # chars-safe: translate one string at a time for quality

STRING_KEYS = [
    "phase_label",
    "quota_label",
    "market_split",
    "outcome",
    "special_focus",
    "definition_of_done",
    "close_day_proof",
    "blog_title",
    "blog_angle",
    "blog_draft",
    "x_copy",
    "linkedin_copy",
    "instagram_task",
]
LIST_KEYS = ["candidate_tasks", "ai_tasks", "execute_today"]
SOURCE_KEYS = ["market", "allocation", "validation"]

PHASE_MAP = {
    "BUILD": "CONSTRUCCIÓN",
    "LAUNCH": "LANZAMIENTO",
    "HIGH-VOLUME": "ALTO VOLUMEN",
    "OPTIMIZE": "OPTIMIZACIÓN",
    "FINISH": "CIERRE",
    "CLOSE": "CIERRE",
    "DEPTH": "PROFUNDIDAD",
    "INTERVIEW": "ENTREVISTAS",
    "PIPELINE": "PIPELINE",
    "FINISH LINE": "RECTA FINAL",
}

# Manual fixes for common quota patterns (faster + cleaner)
QUOTA_PATTERNS = [
    (re.compile(r"^0 applications\s*[•·]\s*construction sprint$", re.I), "0 postulaciones · sprint de construcción"),
    (re.compile(r"^(\d+)\s+applications\s*[•·]\s*(\d+)\s*AR\s*/\s*(\d+)\s*Intl$", re.I), r"\1 postulaciones · \2 AR / \3 Intl"),
]


def load_cache() -> dict[str, str]:
    if CACHE.is_file():
        return json.loads(CACHE.read_text(encoding="utf-8"))
    return {}


def save_cache(cache: dict[str, str]) -> None:
    CACHE.write_text(json.dumps(cache, ensure_ascii=False, indent=0), encoding="utf-8")


def looks_spanish(text: str) -> bool:
    # Heuristic: already has Spanish markers and few English function words
    t = text.lower()
    es = sum(1 for w in (" de ", " la ", " el ", " los ", " las ", " que ", " para ", " con ", " una ", " del ") if w in f" {t} ")
    en = sum(1 for w in (" the ", " and ", " with ", " from ", " application", " create ", " draft ") if w in f" {t} ")
    return es >= 2 and en == 0


def translate_text(text: str, cache: dict[str, str], translator: GoogleTranslator) -> str:
    text = (text or "").strip()
    if not text:
        return text
    if text in cache:
        return cache[text]
    if looks_spanish(text):
        cache[text] = text
        return text

    # Preserve placeholders with ASCII markers (Windows console-safe)
    protected: list[str] = []

    def protect(m: re.Match[str]) -> str:
        protected.append(m.group(0))
        return f"__PH{len(protected) - 1}__"

    work = re.sub(r"\[ARTICLE LINK\]|\{[A-Z_]+\}|#\w+", protect, text)

    # Chunk long text
    chunks: list[str] = []
    if len(work) <= 4500:
        chunks = [work]
    else:
        parts = re.split(r"(?<=[.!?])\s+", work)
        buf = ""
        for p in parts:
            if len(buf) + len(p) + 1 > 4500:
                chunks.append(buf)
                buf = p
            else:
                buf = f"{buf} {p}".strip() if buf else p
        if buf:
            chunks.append(buf)

    out_parts: list[str] = []
    for chunk in chunks:
        translated = None
        for attempt in range(5):
            try:
                translated = translator.translate(chunk)
                if not translated:
                    raise RuntimeError("empty translation")
                break
            except Exception as exc:  # noqa: BLE001
                wait = 1.5 * (attempt + 1)
                msg = str(exc).encode("ascii", "replace").decode("ascii")
                print(f"  retry ({msg[:120]}); sleep {wait:.1f}s", flush=True)
                time.sleep(wait)
        out_parts.append(translated if translated else chunk)
        time.sleep(0.12)

    result = " ".join(out_parts)
    for i, orig in enumerate(protected):
        result = result.replace(f"__PH{i}__", orig)

    cache[text] = result
    return result


def translate_quota(q: str) -> str:
    q = q.strip()
    for pat, repl in QUOTA_PATTERNS:
        m = pat.match(q)
        if m:
            if isinstance(repl, str) and "\\" in repl:
                return pat.sub(repl, q)
            return repl
    return q


def translate_day(day: dict, cache: dict[str, str], translator: GoogleTranslator) -> dict:
    out = dict(day)
    out["date_label"] = ""  # no calendar dates

    pl = (day.get("phase_label") or "").strip().upper()
    out["phase_label"] = PHASE_MAP.get(pl, translate_text(day.get("phase_label") or "", cache, translator))

    q = day.get("quota_label") or ""
    q2 = translate_quota(q)
    out["quota_label"] = q2 if q2 != q else translate_text(q, cache, translator)

    ms = day.get("market_split") or ""
    out["market_split"] = ms.replace("Intl", "Intl")  # keep compact code

    for key in STRING_KEYS:
        if key in ("phase_label", "quota_label", "market_split"):
            continue
        val = day.get(key) or ""
        if isinstance(val, str) and val.strip():
            out[key] = translate_text(val, cache, translator)

    for key in LIST_KEYS:
        items = day.get(key) or []
        out[key] = [translate_text(str(i), cache, translator) for i in items]

    sources = []
    for src in day.get("source_allocations") or []:
        s = dict(src)
        for k in SOURCE_KEYS:
            if s.get(k):
                s[k] = translate_text(str(s[k]), cache, translator)
        # apps stays numeric/string as-is
        sources.append(s)
    out["source_allocations"] = sources
    return out


def embed_php(days: list) -> None:
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    out = ROOT / "data" / "days.php"
    out.write_text(
        "<?php\ndeclare(strict_types=1);\n\n"
        "/** Plan 100 días en español — sin fechas de calendario. */\n"
        "return json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )
    print(f"wrote {out} ({out.stat().st_size} bytes)")


def main() -> None:
    src = ROOT / "data" / "days_en.json"
    if not src.is_file():
        src = DAYS_JSON
    original = json.loads(src.read_text(encoding="utf-8"))
    cache = load_cache()
    translator = GoogleTranslator(source="en", target="es")
    progress_path = ROOT / "data" / "days_es_progress.json"
    start = 0
    out: list[dict] = []
    if progress_path.is_file():
        out = json.loads(progress_path.read_text(encoding="utf-8"))
        start = len(out)
        print(f"resuming from day index {start}")

    total = len(original)
    for i in range(start, total):
        day = original[i]
        print(f"[{i + 1}/{total}] Día {day.get('day')}…")
        out.append(translate_day(day, cache, translator))
        if (i + 1) % 3 == 0 or i + 1 == total:
            save_cache(cache)
            progress_path.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")

    DAYS_JSON.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    save_cache(cache)
    embed_php(out)
    progress_path.unlink(missing_ok=True)
    print("done")


if __name__ == "__main__":
    main()
