# -*- coding: utf-8 -*-
"""Blog only on publish days: 1, 8, 15, …, 99. Strip blog from other days."""
from __future__ import annotations

import json
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


def is_blog_day(day: int) -> bool:
    # Primera publicación = Día 1, después cada 7 días
    return day >= 1 and (day - 1) % 7 == 0


def scrub_blog_mentions(text: str) -> str:
    if not text:
        return text
    drop_frags = [
        "Sin obligación de publicar contenido hoy (el blog es 1 vez por semana).",
        "Sin publicación de blog hoy (cadencia semanal).",
        "Publicar el artículo de la semana y promocionarlo en LinkedIn, X e Instagram Story.",
        "Publicar el artículo de la semana + LinkedIn + X + Instagram Story.",
        "artículo de la semana publicado + redes",
        "artículo semanal + redes",
    ]
    out = text
    for frag in drop_frags:
        out = out.replace(frag, "")
    # tidy separators
    while ";;" in out:
        out = out.replace(";;", ";")
    out = out.replace(" ;", ";").strip(" ;.")
    if out and not out.endswith("."):
        out += "."
    return out


def main() -> None:
    days = json.loads(DAYS_PATH.read_text(encoding="utf-8"))
    publish = []
    for d in days:
        day = int(d["day"])
        blog = is_blog_day(day)
        d["blog_publish"] = blog
        if blog:
            publish.append(day)
        else:
            for k in BLOG_FIELDS:
                d[k] = "" if k != "blog_draft" else ""
            # also clear null-style
            for k in BLOG_FIELDS:
                d[k] = None

        # refresh execute / proof without non-publish blog noise
        ex = d.get("execute_today")
        if isinstance(ex, list):
            cleaned = []
            for item in ex:
                s = str(item)
                if not blog and (
                    "blog" in s.lower()
                    or "artículo de la semana" in s.lower()
                    or "instagram story" in s.lower()
                    or s.lower().startswith("publicar el artículo")
                    or "sin obligación de publicar" in s.lower()
                    or "sin publicación de blog" in s.lower()
                ):
                    continue
                if blog and "Sin obligación" in s:
                    continue
                if blog and "Sin publicación" in s:
                    continue
                cleaned.append(item)
            # ensure publish task on blog days
            if blog and not any("artículo" in str(x).lower() or "publicar" in str(x).lower() for x in cleaned):
                cleaned.append(
                    "Publicar el artículo de la semana + LinkedIn + X + Instagram Story."
                )
            d["execute_today"] = cleaned

        proof = d.get("close_day_proof")
        if isinstance(proof, str):
            if not blog:
                d["close_day_proof"] = scrub_blog_mentions(proof)
            else:
                if "artículo" not in proof.lower():
                    d["close_day_proof"] = proof.rstrip(".") + "; artículo semanal + redes."

    DAYS_PATH.write_text(json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    PHP_PATH.write_text(
        "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )
    d1 = next(x for x in days if x["day"] == 1)
    print("publish days:", publish)
    print("D1 title:", d1.get("blog_title"))
    print("D1 publish:", d1.get("blog_publish"))
    print("wrote", DAYS_PATH.name, PHP_PATH.name)


if __name__ == "__main__":
    main()
