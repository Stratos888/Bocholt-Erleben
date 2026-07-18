#!/usr/bin/env python3
from __future__ import annotations

import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MOTIFS = ROOT / "scripts" / "event_visual_motifs.py"
POOL = ROOT / "data" / "event_visual_pool.json"
BACKLOG = ROOT / "data" / "event_visual_gap_backlog.tsv"
TEST = ROOT / "tests" / "test_children_magic_show_visual_gap.py"

EVENT_ID = "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04"
GAP_ID = "visual-gap-779a752ac282e81f7a99"
VISUAL_KEY = "kids_stage_story"
VISUAL_MOTIF = "children_magic_show"
ASSET_ID = "kids-stage-magic-show-01"

FIELDS = [
    "status", "priority", "event_title", "event_date", "visual_key", "visual_motif",
    "problem", "recommended_action", "source_url", "notes",
    "gap_id", "request_group_key", "event_id", "visual_problem_type", "decision_class",
    "followup_route", "requires_task", "candidate_asset_id",
]


def patch_motif_contract() -> None:
    text = MOTIFS.read_text(encoding="utf-8")
    rule_anchor = '        "neutral_kids_stage": {"role": "fallback", "label": "Kinderbuehne allgemein"},\n'
    rule_line = '        "children_magic_show": {"role": "specific", "label": "Kinderzaubershow"},\n'
    if rule_line not in text:
        if text.count(rule_anchor) != 1:
            raise RuntimeError("kids_stage_story rule anchor not unique")
        text = text.replace(rule_anchor, rule_anchor + rule_line, 1)

    inference_anchor = '    if key == "kids_stage_story":\n'
    inference_rule = '        if _match(hay, r"\\b(zauber(?:er|in|show)?|magie|magic|illusion(?:ist|istin|show)?)\\b"):\n            return "children_magic_show"\n'
    if inference_rule not in text:
        if text.count(inference_anchor) != 1:
            raise RuntimeError("kids_stage_story inference anchor not unique")
        text = text.replace(inference_anchor, inference_anchor + inference_rule, 1)

    MOTIFS.write_text(text, encoding="utf-8")


def patch_visual_pool() -> None:
    payload = json.loads(POOL.read_text(encoding="utf-8"))
    pool = payload.get("pools", {}).get(VISUAL_KEY)
    if not isinstance(pool, dict):
        raise RuntimeError("kids_stage_story pool missing")
    images = pool.get("images")
    if not isinstance(images, list):
        raise RuntimeError("kids_stage_story images missing")

    existing = [item for item in images if isinstance(item, dict) and item.get("id") == ASSET_ID]
    if len(existing) > 1:
        raise RuntimeError("planned magic asset duplicated")
    if not existing:
        images.append({
            "id": ASSET_ID,
            "src": "/assets/event-visuals/kids-stage-magic-show-01.webp",
            "status": "planned",
            "source": "ai_generated_or_cleared_local",
            "rights_status": "owned_or_cleared_required_before_ready",
            "alt": "",
            "source_type": "ai_generated_or_cleared_local",
            "is_symbolic": True,
            "is_documentary": False,
            "review_status": "planned_from_live_visual_gap_2026_07_18",
            "card_asset_format": "16:9_webp_1200x675",
            "visual_motif": VISUAL_MOTIF,
            "note": "Backlog: symbolisches Premium-Visual fuer Kinderzaubershow; keine erkennbaren Kinder, Logos, Poster oder fremde Marken.",
        })
    pool["target_count"] = max(int(pool.get("target_count") or 0), 5)
    POOL.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def patch_backlog() -> None:
    rows = []
    if BACKLOG.exists() and BACKLOG.read_text(encoding="utf-8").strip():
        with BACKLOG.open("r", encoding="utf-8-sig", newline="") as handle:
            reader = csv.DictReader(handle, delimiter="\t")
            rows = [dict(row) for row in reader if row]

    rows = [row for row in rows if row.get("gap_id") != GAP_ID and row.get("event_id") != EVENT_ID]
    rows.append({
        "status": "open",
        "priority": "high",
        "event_title": "Du wunderst mich! – Die Kinderzaubershow mit Endrik Thier · 14:30 & 15:30 Uhr",
        "event_date": "2026-09-04",
        "visual_key": VISUAL_KEY,
        "visual_motif": VISUAL_MOTIF,
        "problem": "Motiv erkannt, aber kein passendes ready-Bild im Event-Visual-Pool.",
        "recommended_action": "Symbolisches Premium-Visual für eine Kinderzaubershow erzeugen oder rechtssicher beschaffen und anschließend als ready registrieren.",
        "source_url": "",
        "notes": "Live-Testevent; das vorhandene neutral_kids_stage-Visual ist nur ein generischer Kinderbühnen-Fallback und nicht magie-spezifisch.",
        "gap_id": GAP_ID,
        "request_group_key": f"{VISUAL_KEY}|{VISUAL_MOTIF}",
        "event_id": EVENT_ID,
        "visual_problem_type": "asset_missing",
        "decision_class": "needs_visual_fix",
        "followup_route": "visual_review",
        "requires_task": "true",
        "candidate_asset_id": "",
    })

    with BACKLOG.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, delimiter="\t", lineterminator="\n")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in FIELDS})


def write_test() -> None:
    TEST.write_text('''#!/usr/bin/env python3
import csv
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from event_visual_motifs import infer_event_visual_fit, load_event_visual_pool

fit = infer_event_visual_fit(
    title="Du wunderst mich! – Die Kinderzaubershow mit Endrik Thier",
    description="Interaktive Magie und verblüffende Tricks für Kinder und Familien.",
    category="Kinder & Familie",
    location="Pfarrheim St. Josef, Bocholt",
    visual_key="kids_stage_story",
    pool_payload=load_event_visual_pool(),
)
assert fit == {
    "visual_key": "kids_stage_story",
    "visual_motif": "children_magic_show",
    "visual_asset_status": "needs_asset",
}, fit

pool = json.loads((ROOT / "data" / "event_visual_pool.json").read_text(encoding="utf-8"))
magic_assets = [
    item for item in pool["pools"]["kids_stage_story"]["images"]
    if item.get("visual_motif") == "children_magic_show"
]
assert len(magic_assets) == 1
assert magic_assets[0]["status"] == "planned"

with (ROOT / "data" / "event_visual_gap_backlog.tsv").open("r", encoding="utf-8-sig", newline="") as handle:
    rows = list(csv.DictReader(handle, delimiter="\t"))
rows = [row for row in rows if row.get("event_id") == "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04"]
assert len(rows) == 1
assert rows[0]["status"] == "open"
assert rows[0]["visual_key"] == "kids_stage_story"
assert rows[0]["visual_motif"] == "children_magic_show"
assert rows[0]["requires_task"] == "true"
assert not rows[0]["candidate_asset_id"]
print("=== Children Magic Show Visual Gap: OK ===")
''', encoding="utf-8")


def validate() -> None:
    text = MOTIFS.read_text(encoding="utf-8")
    if text.count('"children_magic_show"') < 2:
        raise RuntimeError("magic motif contract incomplete")
    payload = json.loads(POOL.read_text(encoding="utf-8"))
    assets = [item for item in payload["pools"][VISUAL_KEY]["images"] if item.get("id") == ASSET_ID]
    if len(assets) != 1 or assets[0].get("status") != "planned":
        raise RuntimeError("planned magic asset contract invalid")


def main() -> None:
    patch_motif_contract()
    patch_visual_pool()
    patch_backlog()
    write_test()
    validate()
    print("OK: children magic show visual gap registered")


if __name__ == "__main__":
    main()
