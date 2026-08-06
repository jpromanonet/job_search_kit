from pathlib import Path
import json

root = Path(__file__).resolve().parents[1]
days = json.loads((root / "data" / "days.json").read_text(encoding="utf-8"))
payload = json.dumps(days, ensure_ascii=False, indent=2)
out = root / "data" / "days.php"
out.write_text(
    "<?php\ndeclare(strict_types=1);\n\n"
    "/** Plan 100 dias — generado desde el DOCX. No editar a mano. */\n"
    "return json_decode(<<<'JSON'\n"
    + payload
    + "\nJSON\n, true, 512, JSON_THROW_ON_ERROR);\n",
    encoding="utf-8",
)
print(f"wrote {out} ({out.stat().st_size} bytes, {len(days)} days)")
