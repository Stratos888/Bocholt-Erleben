# === BEGIN FILE: scripts/audit-event-visual-asset-brief.py | Zweck: prueft Premium-Bildanforderungen je Event-Visual-Key gegen Pool-Slots; Umfang: Diagnose ohne Web-/KI-Suche und ohne UI-Eingriff ===
from __future__ import annotations

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


def read_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        print(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
        return None
    except json.JSONDecodeError as exc:
        print(
            f"FEHLER: {path.relative_to(ROOT)} ist kein gültiges JSON "
            f"({exc.msg}, Zeile {exc.lineno}, Spalte {exc.colno})."
        )
        return None


def as_dict(value: Any) -> Dict[str, Any]:
    return value if isinstance(value, dict) else {}


def as_list(value: Any) -> List[Any]:
    return value if isinstance(value, list) else []


def main() -> int:
    pool = as_dict(read_json(POOL_PATH))
    brief = as_dict(read_json(BRIEF_PATH))

    if not pool or not brief:
        return 1

    pools = as_dict(pool.get("pools"))
    visual_keys = as_dict(brief.get("visual_keys"))

    allowed_keys = set(ALLOWED_EVENT_VISUAL_KEYS)
    pool_keys = set(pools.keys())
    brief_keys = set(visual_keys.keys())

    errors: List[str] = []
    warnings: List[str] = []

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

    total_slots = 0
    high_priority_keys = []

    for key in sorted(allowed_keys):
        pool_entry = as_dict(pools.get(key))
        brief_entry = as_dict(visual_keys.get(key))

        target_count = int(pool_entry.get("target_count", 0) or 0)
        slot_briefs = as_list(brief_entry.get("slot_briefs"))
        total_slots += target_count

        if len(slot_briefs) != target_count:
            errors.append(
                f"{key}: slot_briefs={len(slot_briefs)} passt nicht zu target_count={target_count}"
            )

        if not str(brief_entry.get("label", "")).strip():
            errors.append(f"{key}: label fehlt.")
        if not str(brief_entry.get("mood", "")).strip():
            errors.append(f"{key}: mood fehlt.")
        if not as_list(brief_entry.get("must_have")):
            errors.append(f"{key}: must_have fehlt.")
        if not as_list(brief_entry.get("avoid")):
            errors.append(f"{key}: avoid fehlt.")

        if brief_entry.get("priority") == "high":
            high_priority_keys.append(key)

        for index, slot in enumerate(slot_briefs, start=1):
            slot_dict = as_dict(slot)
            if int(slot_dict.get("slot", 0) or 0) != index:
                errors.append(f"{key}: slot {index} hat falsche slot-Nummer.")
            if str(slot_dict.get("id_suffix", "")).strip() != f"{index:02d}":
                errors.append(f"{key}: slot {index} hat falschen id_suffix.")
            if len(str(slot_dict.get("brief", "")).strip()) < 30:
                errors.append(f"{key}: slot {index} brief ist zu kurz.")
            if slot_dict.get("status") != "needed":
                warnings.append(f"{key}: slot {index} status ist nicht needed.")

    print("Event Visual Asset Brief Audit")
    print("==============================")
    print(f"Brief-Datei: {BRIEF_PATH.relative_to(ROOT)}")
    print(f"Visual-Keys: {len(brief_keys)} / erlaubt: {len(allowed_keys)}")
    print(f"Beschriebene Slots: {sum(len(as_list(as_dict(v).get('slot_briefs'))) for v in visual_keys.values())}")
    print(f"Pool-Zielslots: {total_slots}")
    print(f"High-Priority-Keys: {', '.join(sorted(high_priority_keys))}")

    if warnings:
        print("\nWARNUNGEN:")
        for warning in warnings:
            print(f"- {warning}")

    if errors:
        print("\nFEHLER:")
        for error in errors:
            print(f"- {error}")
        return 1

    print("\nOK: Asset-Brief deckt alle Visual-Keys und Pool-Slots ab.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/audit-event-visual-asset-brief.py ===
