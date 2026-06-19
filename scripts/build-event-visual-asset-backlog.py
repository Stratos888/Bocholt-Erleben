# === BEGIN FILE: scripts/build-event-visual-asset-backlog.py | Zweck: baut eine praktische TSV-Arbeitsliste fuer Event-Visual-Assets aus Pool und Asset-Brief; Umfang: keine UI, keine Websuche, keine OpenAI-Nutzung ===
from __future__ import annotations

import csv
import json
import sys
from pathlib import Path
from typing import Any, Dict, List

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_keys import ALLOWED_EVENT_VISUAL_KEYS


POOL_PATH = ROOT / "data" / "event_visual_pool.json"
BRIEF_PATH = ROOT / "data" / "event_visual_asset_brief.json"
OUT_PATH = ROOT / "data" / "event_visual_asset_backlog.tsv"


FIELDNAMES = [
    "phase",
    "phase_label",
    "priority",
    "visual_key",
    "visual_label",
    "slot",
    "image_id",
    "planned_src",
    "asset_status",
    "source_recommendation",
    "rights_requirement",
    "local_context",
    "mood",
    "brief",
    "must_have",
    "avoid",
    "action_note",
]


PHASE_1_COUNTS = {
    "market_food": 3,
    "city_festival": 2,
    "music_stage": 2,
    "culture_exhibition": 2,
    "theater_show": 1,
    "kids_family": 2,
    "creative_workshop": 3,
    "sport_active": 1,
    "outdoor_nature": 2,
    "city_walk": 2,
    "evening_social": 0,
    "default_city": 2,
}

PHASE_2_COUNTS = {
    "market_food": 5,
    "city_festival": 4,
    "music_stage": 4,
    "culture_exhibition": 4,
    "theater_show": 2,
    "kids_family": 4,
    "creative_workshop": 4,
    "sport_active": 3,
    "outdoor_nature": 4,
    "city_walk": 4,
    "evening_social": 2,
    "default_city": 4,
}

PRIORITY_SORT = {
    "high": 1,
    "medium": 2,
    "low": 3,
}


def read_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
    except json.JSONDecodeError as exc:
        raise SystemExit(
            f"FEHLER: {path.relative_to(ROOT)} ist kein gültiges JSON "
            f"({exc.msg}, Zeile {exc.lineno}, Spalte {exc.colno})."
        )


def as_dict(value: Any) -> Dict[str, Any]:
    return value if isinstance(value, dict) else {}


def as_list(value: Any) -> List[Any]:
    return value if isinstance(value, list) else []


def join_list(value: Any) -> str:
    return " | ".join(str(item).strip() for item in as_list(value) if str(item).strip())


def phase_for(key: str, slot: int) -> tuple[int, str]:
    phase_1_max = PHASE_1_COUNTS.get(key, 0)
    phase_2_max = PHASE_2_COUNTS.get(key, phase_1_max)

    if slot <= phase_1_max:
        return 1, "Basis: zuerst beschaffen, damit Today/Eventkarten schnell hochwertig wirken"
    if slot <= phase_2_max:
        return 2, "Abdeckung: Pool verbreitern und Wiederholungen sichtbar reduzieren"
    return 3, "Variation: Luxusabdeckung gegen Wiederholung bei wachsendem Feed"


def action_note_for(phase: int, priority: str, key: str) -> str:
    if phase == 1:
        return "Zuerst befüllen. Nur starke, sofort nutzbare Premium-Assets akzeptieren."
    if phase == 2:
        return "Nach Phase 1 befüllen. Motivvariation und breitere Eventabdeckung sichern."
    if key == "default_city":
        return "Nur ergänzen, wenn ein wirklich hochwertiges neutrales Bocholt-/Brand-Visual verfügbar ist."
    if priority == "low":
        return "Später befüllen. Nicht mit schwachen generischen Bildern erzwingen."
    return "Später befüllen, wenn Wiederholungen im Feed sichtbar werden."


def main() -> int:
    pool_payload = as_dict(read_json(POOL_PATH))
    brief_payload = as_dict(read_json(BRIEF_PATH))

    pools = as_dict(pool_payload.get("pools"))
    visual_briefs = as_dict(brief_payload.get("visual_keys"))

    allowed_keys = set(ALLOWED_EVENT_VISUAL_KEYS)
    pool_keys = set(pools.keys())
    brief_keys = set(visual_briefs.keys())

    errors: List[str] = []

    if pool_keys != allowed_keys:
        errors.append(
            "Pool-Keys weichen von erlaubten Visual-Keys ab: "
            f"missing={sorted(allowed_keys - pool_keys)}, unknown={sorted(pool_keys - allowed_keys)}"
        )

    if brief_keys != allowed_keys:
        errors.append(
            "Brief-Keys weichen von erlaubten Visual-Keys ab: "
            f"missing={sorted(allowed_keys - brief_keys)}, unknown={sorted(brief_keys - allowed_keys)}"
        )

    rows: List[Dict[str, str]] = []

    for key in sorted(allowed_keys):
        pool = as_dict(pools.get(key))
        brief = as_dict(visual_briefs.get(key))

        images = as_list(pool.get("images"))
        slot_briefs = as_list(brief.get("slot_briefs"))
        target_count = int(pool.get("target_count", 0) or 0)

        if len(images) != target_count:
            errors.append(f"{key}: images={len(images)} passt nicht zu target_count={target_count}")
        if len(slot_briefs) != target_count:
            errors.append(f"{key}: slot_briefs={len(slot_briefs)} passt nicht zu target_count={target_count}")

        priority = str(brief.get("priority", "") or "medium").strip()
        visual_label = str(brief.get("label") or pool.get("label") or key).strip()

        for index in range(1, target_count + 1):
            image = as_dict(images[index - 1]) if index - 1 < len(images) else {}
            slot_brief = as_dict(slot_briefs[index - 1]) if index - 1 < len(slot_briefs) else {}

            phase, phase_label = phase_for(key, index)

            rows.append({
                "phase": str(phase),
                "phase_label": phase_label,
                "priority": priority,
                "visual_key": key,
                "visual_label": visual_label,
                "slot": f"{index:02d}",
                "image_id": str(image.get("id") or f"{key}-{index:02d}").strip(),
                "planned_src": str(image.get("src") or "").strip(),
                "asset_status": str(image.get("status") or "planned").strip(),
                "source_recommendation": join_list(brief.get("preferred_sources")),
                "rights_requirement": str(image.get("rights_status") or "owned_or_cleared_required_before_ready").strip(),
                "local_context": str(brief.get("local_context") or "").strip(),
                "mood": str(brief.get("mood") or "").strip(),
                "brief": str(slot_brief.get("brief") or "").strip(),
                "must_have": join_list(brief.get("must_have")),
                "avoid": join_list(brief.get("avoid")),
                "action_note": action_note_for(phase, priority, key),
            })

    if errors:
        print("FEHLER:")
        for error in errors:
            print(f"- {error}")
        return 1

    rows.sort(
        key=lambda row: (
            int(row["phase"]),
            PRIORITY_SORT.get(row["priority"], 9),
            row["visual_key"],
            int(row["slot"]),
        )
    )

    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    with OUT_PATH.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDNAMES, delimiter="\t", lineterminator="\n")
        writer.writeheader()
        writer.writerows(rows)

    phase_counts: Dict[str, int] = {}
    for row in rows:
        phase_counts[row["phase"]] = phase_counts.get(row["phase"], 0) + 1

    print("Event Visual Asset Backlog gebaut")
    print("================================")
    print(f"Output: {OUT_PATH.relative_to(ROOT)}")
    print(f"Zeilen: {len(rows)}")
    for phase in sorted(phase_counts, key=int):
        print(f"- Phase {phase}: {phase_counts[phase]}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-asset-backlog.py ===
