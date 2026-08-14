# -*- coding: utf-8 -*-
import json
import re
from pathlib import Path

days = json.loads(Path("data/days.json").read_text(encoding="utf-8"))
keys = [
    "special_focus",
    "outcome",
    "definition_of_done",
    "close_day_proof",
    "execute_today",
    "candidate_tasks",
    "ai_tasks",
]
pat = re.compile(
    r"(?i)36|seis fam|6 fam|familias? de CV|seis CV|6 CV|variantes?|"
    r"CV ATS|role.?famil|familiar m[aá]s|familia de rol|familia de puesto|"
    r"DOCX/PDF|6 ingles|seis ingles|doce|12 CV|inventario maestro"
)
out = []
for d in days:
    for k in keys:
        v = d.get(k)
        items = v if isinstance(v, list) else ([v] if v else [])
        for item in items:
            s = str(item)
            if pat.search(s) or (
                re.search(r"(?i)\bCVs\b|curr[ií]cul", s)
                and re.search(r"(?i)seis|6 |36|familia|variante|DOCX", s)
            ):
                out.append(f"D{d['day']} {k}: {s}")

Path("tools/_cv_hits.txt").write_text("\n\n".join(out), encoding="utf-8")
print(len(out), "hits")
