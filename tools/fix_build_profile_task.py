# -*- coding: utf-8 -*-
import json
from pathlib import Path

root = Path(__file__).resolve().parents[1]
days = json.loads((root / "data" / "days.json").read_text(encoding="utf-8"))
profile_task = (
    "Revisar perfiles AR (LinkedIn, Bumeran, Computrabajo, Get on Board) "
    "sin enviar postulaciones todavía."
)
for d in days:
    day = int(d["day"])
    if day <= 7 and not d.get("blog_publish"):
        ex = list(d.get("execute_today") or [])
        if not any("perfiles AR" in str(x) for x in ex):
            ex.insert(1 if ex else 0, profile_task)
            d["execute_today"] = ex
            print("fixed D", day)

(root / "data" / "days.json").write_text(
    json.dumps(days, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
)
payload = json.dumps(days, ensure_ascii=False, indent=2)
(root / "data" / "days.php").write_text(
    "<?php\n\ndeclare(strict_types=1);\n\nreturn json_decode(<<<'JSON'\n"
    + payload
    + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
    encoding="utf-8",
)
print("ok")
