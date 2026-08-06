#!/usr/bin/env python3
"""Parse _plan_text.txt into structured JSON for the Plan tab seed."""

from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DOCX_PATH = ROOT / "Juan_Romano_100_Day_Job_Search_Plan.docx"
TEXT_PATH = ROOT / "_plan_text.txt"
OUT_DAYS = ROOT / "data" / "days.json"
OUT_RECS = ROOT / "data" / "recommendations.json"
OUT_TECH = ROOT / "data" / "technologies.json"


def extract_docx_text(docx_path: Path) -> str:
    import zipfile
    from xml.etree import ElementTree as ET

    with zipfile.ZipFile(docx_path) as zf:
        xml = zf.read("word/document.xml")
    root = ET.fromstring(xml)
    ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    lines: list[str] = []
    for p in root.findall(".//w:p", ns):
        texts = [t.text or "" for t in p.findall(".//w:t", ns)]
        lines.append("".join(texts))
    return "\n".join(lines)


def split_days(text: str) -> list[tuple[int, str]]:
    matches = list(re.finditer(r"^Day (\d+)\s*$", text, re.M))
    days: list[tuple[int, str]] = []
    for i, m in enumerate(matches):
        day_num = int(m.group(1))
        start = m.end()
        end = matches[i + 1].start() if i + 1 < len(matches) else len(text)
        # Stop at appendix sections after Day 100
        body = text[start:end]
        if day_num == 100:
            for stopper in (
                "\nSAMPLE HR FAQ",
                "\nCAMPAIGN CONTROL",
                "\nDAY 5 QUALITY GATE",
                "\nFinal decision gate",
            ):
                cut = body.find(stopper)
                if cut != -1:
                    body = body[:cut]
                    break
        days.append((day_num, body.strip()))
    return days


def section_after(body: str, headers: list[str], until: list[str] | None = None) -> str:
    for header in headers:
        m = re.search(rf"^{re.escape(header)}\s*$", body, re.M)
        if not m:
            m = re.search(rf"^{re.escape(header)}\s+", body, re.M)
        if not m:
            continue
        start = m.end()
        rest = body[start:]
        if until:
            ends = []
            for u in until:
                um = re.search(rf"^{re.escape(u)}\b", rest, re.M)
                if um:
                    ends.append(um.start())
            if ends:
                rest = rest[: min(ends)]
        return rest.strip()
    return ""


def bullets(block: str) -> list[str]:
    items: list[str] = []
    for line in block.splitlines():
        line = line.strip()
        if line.startswith("•") or line.startswith("☐") or line.startswith("- "):
            items.append(re.sub(r"^[•☐\-]\s*", "", line).strip())
        elif line.startswith("☐"):
            items.append(line.lstrip("☐ ").strip())
    return [i for i in items if i]


def parse_blog_block(body: str) -> dict:
    blog = section_after(
        body,
        ["DAILY BLOG + DISTRIBUTION"],
        until=["CLOSE-DAY PROOF", "Day "],
    )
    result = {
        "blog_title": "",
        "blog_angle": "",
        "blog_draft": "",
        "x_copy": "",
        "linkedin_copy": "",
        "instagram_task": "",
    }
    if not blog:
        return result

    # Blog title + angle + draft often concatenated on few lines after "Blog"
    bm = re.search(
        r"^Blog\s*\n(.+?)(?=^X\s*$)",
        blog,
        re.M | re.S,
    )
    if bm:
        blog_text = bm.group(1).strip()
        angle_m = re.search(r"Core angle:\s*(.+?)(?=Draft:|$)", blog_text, re.S)
        draft_m = re.search(r"Draft:\s*(.+)$", blog_text, re.S)
        title = blog_text
        if angle_m:
            title = blog_text[: angle_m.start()].strip()
            result["blog_angle"] = angle_m.group(1).strip()
        if draft_m:
            if not angle_m:
                title = blog_text[: draft_m.start()].strip()
            result["blog_draft"] = draft_m.group(1).strip()
        # Title may still contain "Core angle" inline without newline
        if "Core angle:" in title:
            parts = title.split("Core angle:", 1)
            title = parts[0].strip()
            rest = parts[1]
            if "Draft:" in rest:
                angle, draft = rest.split("Draft:", 1)
                result["blog_angle"] = angle.strip()
                result["blog_draft"] = draft.strip()
            else:
                result["blog_angle"] = rest.strip()
        result["blog_title"] = title

    xm = re.search(r"^X\s*\n(.+?)(?=^LinkedIn\s*$)", blog, re.M | re.S)
    if xm:
        result["x_copy"] = xm.group(1).strip()

    lm = re.search(r"^LinkedIn\s*\n(.+?)(?=^Instagram\s*$)", blog, re.M | re.S)
    if lm:
        result["linkedin_copy"] = lm.group(1).strip()

    im = re.search(r"^Instagram\s*\n(.+?)$", blog, re.M | re.S)
    if im:
        result["instagram_task"] = im.group(1).strip()

    return result


def parse_source_table(body: str) -> list[dict]:
    block = section_after(
        body,
        ["Special focus:", "Market"],
        until=["EXECUTE TODAY", "DAILY BLOG", "DEFINITION OF DONE", "CANDIDATE"],
    )
    # Prefer the allocation table after the header lines
    lines = [ln.strip() for ln in body.splitlines()]
    rows: list[dict] = []
    try:
        idx = next(i for i, ln in enumerate(lines) if ln == "Market" and i + 1 < len(lines) and "Exact source" in lines[i + 1])
    except StopIteration:
        return rows

    # Skip header: Market / Exact source allocation / Apps / Validation
    i = idx + 4
    current_market = ""
    while i < len(lines):
        ln = lines[i]
        if ln in ("EXECUTE TODAY — IN ORDER", "DAILY BLOG + DISTRIBUTION", "CANDIDATE — EXECUTE"):
            break
        if ln in ("Argentina", "International"):
            current_market = ln
            if i + 3 < len(lines):
                allocation = lines[i + 1]
                apps = lines[i + 2]
                validation = lines[i + 3]
                if allocation and not allocation.startswith("EXECUTE"):
                    rows.append(
                        {
                            "market": current_market,
                            "allocation": allocation,
                            "apps": apps,
                            "validation": validation,
                        }
                    )
                    i += 4
                    continue
        i += 1
    return rows


def parse_day(day_num: int, body: str) -> dict:
    lines = [ln.strip() for ln in body.splitlines() if ln.strip()]
    date_line = lines[0] if lines else ""
    phase = lines[1] if len(lines) > 1 else ""
    quota_line = lines[2] if len(lines) > 2 else ""

    apps_target = 0
    market_split = ""
    am = re.search(r"(\d+)\s+applications?", quota_line, re.I)
    if am:
        apps_target = int(am.group(1))
    sm = re.search(r"(\d+\s*AR\s*/\s*\d+\s*Intl)", quota_line, re.I)
    if sm:
        market_split = sm.group(1).replace(" ", "")
    elif "construction sprint" in quota_line.lower():
        market_split = "0AR/0Intl"

    outcome = ""
    om = re.search(r"^Outcome:\s*(.+)$", body, re.M)
    if om:
        outcome = om.group(1).strip()
    special = ""
    sf = re.search(r"^Special focus:\s*(.+)$", body, re.M)
    if sf:
        special = sf.group(1).strip()

    candidate = section_after(
        body,
        ["CANDIDATE — EXECUTE"],
        until=["AI COPILOT", "DEFINITION OF DONE", "DAILY BLOG", "EXECUTE TODAY"],
    )
    ai = section_after(
        body,
        ["AI COPILOT — BUILD WITH CANDIDATE", "AI COPILOT — SUPPORT"],
        until=["DEFINITION OF DONE", "DAILY BLOG", "EXECUTE TODAY"],
    )
    dod = section_after(
        body,
        ["DEFINITION OF DONE"],
        until=["DAILY BLOG", "EXECUTE TODAY", "CLOSE-DAY"],
    )
    # DEFINITION OF DONE may be inline: "DEFINITION OF DONE  text"
    if not dod:
        dm = re.search(r"^DEFINITION OF DONE\s+(.+)$", body, re.M)
        if dm:
            dod = dm.group(1).strip()
    else:
        # may still start on same line after header capture
        pass
    dm2 = re.search(r"^DEFINITION OF DONE\s+(.+)$", body, re.M)
    if dm2 and (not dod or len(dod) < 10):
        dod = dm2.group(1).strip()

    execute = section_after(
        body,
        ["EXECUTE TODAY — IN ORDER"],
        until=["DAILY BLOG", "CLOSE-DAY", "DEFINITION OF DONE"],
    )
    close_proof = ""
    cm = re.search(r"^CLOSE-DAY PROOF\s+(.+)$", body, re.M)
    if cm:
        close_proof = cm.group(1).strip()

    blog = parse_blog_block(body)
    sources = parse_source_table(body) if apps_target > 0 else []

    # Normalize phase label
    phase_norm = phase.upper()
    if day_num <= 7:
        phase_key = "build"
    elif day_num <= 77:
        phase_key = "high_volume"
    else:
        phase_key = "finish"

    return {
        "day": day_num,
        "date_label": date_line,
        "phase_label": phase,
        "phase": phase_key,
        "quota_label": quota_line,
        "applications_target": apps_target,
        "market_split": market_split,
        "outcome": outcome,
        "special_focus": special,
        "candidate_tasks": bullets(candidate) if candidate else [],
        "ai_tasks": bullets(ai) if ai else [],
        "execute_today": bullets(execute) if execute else [],
        "definition_of_done": dod.strip(),
        "source_allocations": sources,
        "close_day_proof": close_proof,
        **blog,
    }


def parse_recommendations(text: str) -> list[dict]:
    """Everything before Day 1 except Master technology inventory."""
    end = re.search(r"^Day 1\s*$", text, re.M)
    preface = text[: end.start()] if end else text

    # Remove technology inventory block
    tech_start = re.search(r"^Master technology inventory\s*$", preface, re.M)
    tech_end = re.search(r"^KEYWORD DISCIPLINE\s*$", preface, re.M)
    if tech_start and tech_end:
        preface = preface[: tech_start.start()] + preface[tech_end.start() :]

    sections: list[dict] = []
    # Split by ALL-CAPS-ish headers or known section titles
    known = [
        "READ THIS FIRST",
        "THE TWO PHASES",
        "What counts as an application",
        "Operating assumptions and safeguards",
        "Candidate and AI copilot responsibilities",
        "Definition of a completed day",
        "TARGETS",
        "NEGOTIATION RULE",
        "Six CV role families",
        "KEYWORD DISCIPLINE",
        "CHANNEL MAP",
        "PORTAL AVAILABILITY",
        "Argentina — job boards and search",
        "Argentina — recruiters and communities",
        "International — job boards",
        "International — talent networks",
        "Freelance and contract channels",
        "Direct outreach channels",
        "PRODUCT SPECIFICATION",
        "NAVIGATION DECISION",
        "FIXED TECHNOLOGY SCOPE",
        "1. Plan",
        "2. Kit",
        "3. HR FAQ",
        "4. Tracker",
        "Core MySQL tables",
        "Calculations and controls",
        "Recommended implementation",
        "NO-AUTH DEPLOYMENT BOUNDARY",
        "MVP acceptance criteria",
        "DAILY EXECUTION",
        "DAILY ORDER",
        "CAMPAIGN PROMISE",
    ]

    # Simpler: chunk by blank-line separated titled blocks from known list
    positions = []
    for title in known:
        m = re.search(rf"^{re.escape(title)}\s*$", preface, re.M)
        if m:
            positions.append((m.start(), title))
    positions.sort()

    if not positions:
        sections.append({"title": "Introducción", "body": preface.strip()})
        return sections

    # Intro before first known header
    if positions[0][0] > 0:
        intro = preface[: positions[0][0]].strip()
        if intro:
            sections.append({"title": "Campaña", "body": intro})

    for i, (pos, title) in enumerate(positions):
        end_pos = positions[i + 1][0] if i + 1 < len(positions) else len(preface)
        body = preface[pos:end_pos]
        # drop the title line
        body = re.sub(rf"^{re.escape(title)}\s*\n?", "", body, count=1).strip()
        if body:
            sections.append({"title": title, "body": body})
    return sections


def parse_technologies(text: str) -> list[dict]:
    start = re.search(r"^Master technology inventory\s*$", text, re.M)
    end = re.search(r"^KEYWORD DISCIPLINE\s*$", text, re.M)
    if not start or not end:
        return []
    block = text[start.end() : end.start()].strip()
    lines = [ln.strip() for ln in block.splitlines() if ln.strip()]
    # Skip header Area / Truthful keyword inventory
    groups = []
    i = 0
    if lines and lines[0] == "Area":
        i = 2
    while i < len(lines):
        area = lines[i]
        inventory = lines[i + 1] if i + 1 < len(lines) else ""
        # Heuristic: next area is short and next next has semicolons
        if ";" not in area and inventory:
            techs = [t.strip() for t in inventory.split(";") if t.strip()]
            groups.append({"area": area, "technologies": techs})
            i += 2
        else:
            i += 1
    return groups


def main() -> None:
    if DOCX_PATH.is_file():
        text = extract_docx_text(DOCX_PATH)
        TEXT_PATH.write_text(text, encoding="utf-8")
    else:
        text = TEXT_PATH.read_text(encoding="utf-8")
    days = [parse_day(n, b) for n, b in split_days(text)]
    assert len(days) == 100, len(days)
    assert days[0]["day"] == 1 and days[-1]["day"] == 100

    OUT_DAYS.parent.mkdir(parents=True, exist_ok=True)
    OUT_DAYS.write_text(json.dumps(days, ensure_ascii=False, indent=2), encoding="utf-8")
    OUT_RECS.write_text(
        json.dumps(parse_recommendations(text), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    OUT_TECH.write_text(
        json.dumps(parse_technologies(text), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    print(f"Wrote {len(days)} days -> {OUT_DAYS}")
    print(f"Sample Day 1 title: {days[0]['blog_title'][:60]}")
    print(f"Sample Day 8 apps: {days[7]['applications_target']} sources={len(days[7]['source_allocations'])}")


if __name__ == "__main__":
    main()
