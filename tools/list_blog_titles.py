#!/usr/bin/env python3
import json
from pathlib import Path

days = json.loads(Path("data/days.json").read_text(encoding="utf-8"))
for d in days:
    print(f"{d['day']:3d}|{d.get('blog_title', '')}")
