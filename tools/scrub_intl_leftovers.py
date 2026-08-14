# -*- coding: utf-8 -*-
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
days = json.loads((ROOT / "data" / "days.json").read_text(encoding="utf-8"))


def scrub(s: str) -> str:
    s = re.sub(r"(?i)canales internacionales", "perfiles y portales de Argentina", s)
    s = re.sub(r"(?i)conversi[oó]n de canales internacionales", "conversión de portales AR", s)
    s = re.sub(r"(?i)internacionales", "de Argentina", s)
    s = re.sub(r"(?i)\binternacional\b", "Argentina", s)
    s = re.sub(r"(?i)wellfound|remote\s*ok|toptal|turing|revelo|angellist|remotive", "portales AR", s)
    s = re.sub(r"(?i)500\s*/\s*500", "465 AR", s)
    return s


def walk(v):
    if isinstance(v, str):
        return scrub(v)
    if isinstance(v, list):
        return [walk(x) for x in v]
    if isinstance(v, dict):
        return {k: walk(x) for k, x in v.items()}
    return v


for d in days:
    for k in list(d.keys()):
        d[k] = walk(d[k])
    if d["day"] == 7:
        d["outcome"] = (
            "Dejá listos todos los canales de Argentina y el sistema editorial "
            "(primer artículo de la semana)."
        )
        d["definition_of_done"] = (
            "Portales y perfiles AR listos; alertas configuradas; "
            "primer artículo de la semana publicado con redes."
        )
    if d["day"] == 23:
        d["special_focus"] = (
            "Compará la conversión de portales AR y redirigí una fuente débil "
            "sin cambiar la cuota diaria."
        )

(ROOT / "data" / "days.json").write_text(
    json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
)
payload = json.dumps(days, ensure_ascii=False, indent=2)
(ROOT / "data" / "days.php").write_text(
    "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
    + payload
    + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
    encoding="utf-8",
)

pat = re.compile(r"(?i)internacional|wellfound|remote\s*ok|toptal|500/500")
hits = 0
for d in days:
    blob = json.dumps(
        {k: d.get(k) for k in [
            "execute_today", "special_focus", "outcome", "definition_of_done",
            "close_day_proof", "candidate_tasks", "ai_tasks", "source_allocations",
        ]},
        ensure_ascii=False,
    )
    if pat.search(blob):
        hits += 1
        print("still", d["day"], pat.search(blob).group(0))
print("hits", hits)
print("D7", d["day"] if False else next(x["blog_title"] for x in days if x["day"] == 7))
