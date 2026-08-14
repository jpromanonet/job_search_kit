# -*- coding: utf-8 -*-
from pathlib import Path
import re

path = Path(__file__).resolve().parents[1] / "install.php"
text = path.read_text(encoding="utf-8")

for key in [
    "summary-facts-es",
    "summary-facts-en",
    "summary-achievements-es",
    "summary-achievements-en",
]:
    text2, n = re.subn(
        rf"\n        '{key}' => \".*?\",",
        "",
        text,
        count=1,
        flags=re.S,
    )
    if n:
        print(f"removed body {key}")
        text = text2

old = """    $groups = [
        // Master CVs first
        ['CV maestro (ES)', 'cv-master-es', 'cv', 'es', 1],
        ['Master CV (EN)', 'cv-master-en', 'cv', 'en', 2],
        // CVs ES
        ['Technical Lead / Software Delivery Lead', 'cv-tech-lead-es', 'cv', 'es', 10],
        ['Engineering Manager / Head of Engineering', 'cv-em-es', 'cv', 'es', 20],
        ['Senior Full-stack Software Engineer', 'cv-fullstack-es', 'cv', 'es', 30],
        ['Platform / DevOps / Observability', 'cv-devops-es', 'cv', 'es', 40],
        ['Solutions / Implementation / TAM', 'cv-tam-es', 'cv', 'es', 50],
        ['IT Manager / App Support / Infra Lead', 'cv-it-manager-es', 'cv', 'es', 60],
        // CVs EN
        ['Technical Lead / Software Delivery Lead', 'cv-tech-lead-en', 'cv', 'en', 110],
        ['Engineering Manager / Head of Engineering', 'cv-em-en', 'cv', 'en', 120],
        ['Senior Full-stack Software Engineer', 'cv-fullstack-en', 'cv', 'en', 130],
        ['Platform / DevOps / Observability', 'cv-devops-en', 'cv', 'en', 140],
        ['Solutions / Implementation / TAM', 'cv-tam-en', 'cv', 'en', 150],
        ['IT Manager / App Support / Infra Lead', 'cv-it-manager-en', 'cv', 'en', 160],
        // Covers
        ['Carta de presentación (ES)', 'cover-es', 'cover_letter', 'es', 200],
        ['Cover letter (EN)', 'cover-en', 'cover_letter', 'en', 210],
        // Summaries bilingual
        ['Hechos de carrera / Summary (ES)', 'summary-facts-es', 'summary', 'es', 300],
        ['Career Facts / Summary (EN)', 'summary-facts-en', 'summary', 'en', 305],
        ['Banco de logros (ES)', 'summary-achievements-es', 'summary', 'es', 310],
        ['Achievement bank (EN)', 'summary-achievements-en', 'summary', 'en', 315],
        // Messages bilingual
"""

new = """    $groups = [
        ['CV maestro (ES)', 'cv-master-es', 'cv', 'es', 1],
        ['Master CV (EN)', 'cv-master-en', 'cv', 'en', 2],
        ['Carta de presentación (ES)', 'cover-es', 'cover_letter', 'es', 200],
        ['Cover letter (EN)', 'cover-en', 'cover_letter', 'en', 210],
        // Messages bilingual
"""

if old not in text:
    raise SystemExit("groups block not found")
path.write_text(text.replace(old, new, 1), encoding="utf-8")
print("install.php updated")
