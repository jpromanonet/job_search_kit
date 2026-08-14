# -*- coding: utf-8 -*-
"""Blog on 7,14,…,98 (D1 article moved to D7). Scrub non-AR portal refs."""
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


def is_blog_day(day: int) -> bool:
    return day >= 7 and day % 7 == 0


def load_git_days() -> list[dict]:
    raw = subprocess.check_output(
        ["git", "show", "HEAD:data/days.json"],
        cwd=ROOT,
    )
    return json.loads(raw.decode("utf-8"))


def scrub_text(s: str) -> str:
    if not s:
        return s
    repls = [
        (r"(?i)\binternacional\b", "Argentina"),
        (r"(?i)\bintl\b", "AR"),
        (r"(?i)equilibrio\s*500\s*/\s*500", "meta de 465 postulaciones AR"),
        (r"(?i)totales\s*500\s*/\s*500", "total de 465 en Argentina"),
        (r"(?i)saldo final\s*500\s*/\s*500", "meta AR de 465"),
        (r"(?i)en cada mercado", "en Argentina"),
        (r"(?i)ambos mercados", "Argentina"),
        (r"(?i)un AR prioritario y un CV internacional prioritario", "un CV AR prioritario"),
        (r"(?i)Wellfound|Remote OK|RemoteOK|Toptal|Turing|Revelo|Andela|VanHack|Himalayas|Remotive|AngelList|Built In|We Work Remotely|LATAM Jobs|YC Work|Work at a Startup|Crossover", "portales AR"),
    ]
    out = s
    for pat, rep in repls:
        out = re.sub(pat, rep, out)
    return out


def scrub_value(v):
    if isinstance(v, str):
        return scrub_text(v)
    if isinstance(v, list):
        return [scrub_value(x) for x in v]
    if isinstance(v, dict):
        # drop Internacional market rows
        if (v.get("market") or "").lower().startswith("internacional"):
            return None
        return {k: scrub_value(val) for k, val in v.items()}
    return v


def main() -> None:
    days = json.loads(DAYS_PATH.read_text(encoding="utf-8"))
    git_days = {int(d["day"]): d for d in load_git_days()}
    by_day = {int(d["day"]): d for d in days}

    # Capture D1 article (preferred current, else git)
    d1 = by_day[1]
    d1_blog = {k: d1.get(k) or git_days[1].get(k) for k in BLOG_FIELDS}

    publish = []
    for d in days:
        day = int(d["day"])
        blog = is_blog_day(day)
        d["blog_publish"] = blog

        if blog:
            publish.append(day)
            if day == 7:
                # First publish: article that was on Día 1
                for k in BLOG_FIELDS:
                    d[k] = d1_blog.get(k)
            else:
                # Restore weekly articles from git for 14,21,...
                src = git_days.get(day, {})
                for k in BLOG_FIELDS:
                    d[k] = src.get(k) or d.get(k)
            # Ensure publish task
            ex = list(d.get("execute_today") or [])
            ex = [
                x
                for x in ex
                if "Sin obligación" not in str(x)
                and "Sin publicación" not in str(x)
            ]
            if not any("artículo de la semana" in str(x).lower() or str(x).lower().startswith("publicar el artículo") for x in ex):
                ex.append("Publicar el artículo de la semana + LinkedIn + X + Instagram Story.")
            d["execute_today"] = ex
            proof = d.get("close_day_proof") or ""
            if isinstance(proof, str) and "artículo" not in proof.lower():
                d["close_day_proof"] = proof.rstrip(". ") + "; artículo semanal + redes."
        else:
            for k in BLOG_FIELDS:
                d[k] = None
            ex = list(d.get("execute_today") or [])
            d["execute_today"] = [
                x
                for x in ex
                if "artículo de la semana" not in str(x).lower()
                and not str(x).lower().startswith("publicar el artículo")
                and "Sin obligación de publicar" not in str(x)
                and "Sin publicación de blog" not in str(x)
                and "Instagram Story" not in str(x)
            ]
            if isinstance(d.get("close_day_proof"), str):
                p = d["close_day_proof"]
                p = re.sub(r"(?i);?\s*artículo semanal \+ redes\.?", "", p)
                p = re.sub(r"(?i);?\s*artículo de la semana publicado \+ redes\.?", "", p)
                d["close_day_proof"] = p.strip(" ;.") + ("." if p.strip() else "")

        # Scrub non-AR portal / market language everywhere relevant
        for key in [
            "execute_today",
            "special_focus",
            "outcome",
            "definition_of_done",
            "close_day_proof",
            "candidate_tasks",
            "ai_tasks",
            "source_allocations",
            "quota_label",
            "market_split",
        ]:
            if key not in d:
                continue
            cleaned = scrub_value(d[key])
            if key == "source_allocations" and isinstance(cleaned, list):
                cleaned = [row for row in cleaned if row is not None]
                # if somehow empty, keep AR-only stub
                if not cleaned:
                    cleaned = [
                        {
                            "market": "Argentina",
                            "allocation": "Portales AR del día",
                            "apps": str(d.get("applications_target") or 0),
                            "validation": "Solo Argentina",
                        }
                    ]
            d[key] = cleaned

        # candidate/ai task lists: drop items that still point to non-AR boards explicitly
        for key in ("candidate_tasks", "ai_tasks"):
            items = d.get(key)
            if not isinstance(items, list):
                continue
            kept = []
            for item in items:
                s = str(item)
                if re.search(
                    r"(?i)wellfound|remote ok|toptal|turing|revelo|yc |angellist|remotive|mercado internacional|perfil internacional",
                    s,
                ):
                    continue
                kept.append(item)
            d[key] = kept

    DAYS_PATH.write_text(json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    payload = json.dumps(days, ensure_ascii=False, indent=2)
    PHP_PATH.write_text(
        "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
        + payload
        + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
        encoding="utf-8",
    )

    d7 = by_day[7]
    print("publish:", publish)
    print("D1 blog:", by_day[1].get("blog_title"), "pub", by_day[1].get("blog_publish"))
    print("D7 blog:", d7.get("blog_title"), "pub", d7.get("blog_publish"))
    print("D14 blog:", by_day[14].get("blog_title"), "pub", by_day[14].get("blog_publish"))


if __name__ == "__main__":
    main()
