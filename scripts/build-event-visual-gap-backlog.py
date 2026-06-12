# === BEGIN FILE: scripts/build-event-visual-gap-backlog.py | Zweck: erzeugt ein Backlog fuer fehlende motivgenaue Eventbilder; Umfang: lokale Events/Inboxes/optionale CSV ohne UI-Aenderung ===
from __future__ import annotations

import csv
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List, Tuple

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_motifs import infer_event_visual_fit, load_event_visual_pool

OUT_PATH = ROOT / "data" / "event_visual_gap_backlog.tsv"
DEFAULT_SOURCES = [
    ROOT / "data" / "events.json",
    ROOT / "data" / "inbox.json",
    ROOT / "data" / "inbox_manual.json",
]
FIELDS = [
    "status",
    "priority",
    "event_title",
    "event_date",
    "visual_key",
    "visual_motif",
    "problem",
    "recommended_action",
    "source_url",
    "notes",
]


def clean(value: object) -> str:
    return str(value or "").replace("\n", " ").replace("\r", " ").strip()


def as_list(payload: Any) -> List[Dict[str, Any]]:
    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, dict)]
    if isinstance(payload, dict):
        for key in ("events", "items", "data"):
            value = payload.get(key)
            if isinstance(value, list):
                return [item for item in value if isinstance(item, dict)]
    return []


def read_json_items(path: Path) -> Iterable[Dict[str, str]]:
    if not path.exists() or not path.read_text(encoding="utf-8").strip():
        return []
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError:
        print(f"HINWEIS: {path.relative_to(ROOT)} uebersprungen, kein gueltiges JSON.")
        return []
    out = []
    for item in as_list(payload):
        out.append(normalize_event_item(item, source=path.relative_to(ROOT).as_posix()))
    return out


def read_table_items(path: Path) -> Iterable[Dict[str, str]]:
    delimiter = "\t" if path.suffix.lower() == ".tsv" else ","
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle, delimiter=delimiter)
        return [normalize_event_item(row, source=path.name) for row in reader]


def normalize_event_item(item: Dict[str, Any], source: str) -> Dict[str, str]:
    return {
        "source": source,
        "title": clean(item.get("title") or item.get("eventName")),
        "date": clean(item.get("date")),
        "description": clean(item.get("description") or item.get("beschreibung")),
        "category": clean(item.get("kategorie") or item.get("category") or item.get("kategorie_suggestion")),
        "location": clean(item.get("location") or item.get("ort")),
        "visual_key": clean(item.get("visual_key") or item.get("visualKey") or item.get("image_visual_key")),
        "visual_motif": clean(item.get("visual_motif") or item.get("visualMotif") or item.get("image_visual_motif")),
        "url": clean(item.get("url") or item.get("source_url")),
    }


def priority_for(fit: Dict[str, str], item: Dict[str, str]) -> str:
    key = fit.get("visual_key", "")
    motif = fit.get("visual_motif", "")
    title = item.get("title", "")

    high = {
        ("indoor_sport_competition", "fencing"),
        ("indoor_sport_competition", "darts"),
        ("classical_music", "choir"),
        ("classical_music", "organ_concert"),
        ("kids_stage_story", "puppet_theater"),
        ("food_drink_festival", "wine_festival"),
        ("market_stalls", "fabric_market"),
        ("business_messe_info", "health_career_fair"),
    }
    if (key, motif) in high:
        return "high"
    if "2026" in item.get("date", ""):
        return "medium"
    if title:
        return "medium"
    return "low"


def build_rows(items: Iterable[Dict[str, str]]) -> List[Dict[str, str]]:
    pool = load_event_visual_pool()
    grouped: Dict[Tuple[str, str], Dict[str, str]] = {}
    counts: Dict[Tuple[str, str], int] = {}

    for item in items:
        if not item.get("title"):
            continue
        fit = infer_event_visual_fit(
            title=item.get("title", ""),
            description=item.get("description", ""),
            category=item.get("category", ""),
            location=item.get("location", ""),
            visual_key=item.get("visual_key", ""),
            visual_motif=item.get("visual_motif", ""),
            pool_payload=pool,
        )
        if fit["visual_asset_status"] == "ok":
            continue

        grouping_key = (fit["visual_key"], fit["visual_motif"])
        counts[grouping_key] = counts.get(grouping_key, 0) + 1
        if grouping_key in grouped:
            continue

        problem = "Motiv erkannt, aber kein passendes ready-Bild im Event-Visual-Pool."
        if fit["visual_asset_status"] == "review":
            problem = "Motiv oder Key ist ein Review-Fall und braucht redaktionelle Pruefung."

        grouped[grouping_key] = {
            "status": "open",
            "priority": priority_for(fit, item),
            "event_title": item.get("title", ""),
            "event_date": item.get("date", ""),
            "visual_key": fit["visual_key"],
            "visual_motif": fit["visual_motif"],
            "problem": problem,
            "recommended_action": "Motivbild generieren oder vorhandenes Bild explizit diesem Motiv zuordnen.",
            "source_url": item.get("url", ""),
            "notes": f"Quelle: {item.get('source', '')}",
        }

    rows = list(grouped.values())
    for row in rows:
        key = (row["visual_key"], row["visual_motif"])
        row["notes"] = f"{row['notes']}; Treffer in ausgewerteten Quellen: {counts.get(key, 0)}"

    order = {"high": 0, "medium": 1, "low": 2}
    rows.sort(key=lambda row: (order.get(row["priority"], 9), row["visual_key"], row["visual_motif"], row["event_date"]))
    return rows


def main() -> int:
    items: List[Dict[str, str]] = []

    for source in DEFAULT_SOURCES:
        items.extend(read_json_items(source))

    extra = os.environ.get("EVENT_VISUAL_GAP_INPUT", "").strip()
    if extra:
        extra_path = Path(extra)
        if not extra_path.is_absolute():
            extra_path = ROOT / extra_path
        if not extra_path.exists():
            print(f"FEHLER: EVENT_VISUAL_GAP_INPUT nicht gefunden: {extra_path}", file=sys.stderr)
            return 1
        items.extend(read_table_items(extra_path))

    rows = build_rows(items)
    OUT_PATH.parent.mkdir(parents=True, exist_ok=True)
    with OUT_PATH.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, delimiter="\t", lineterminator="\n")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in FIELDS})

    print(f"OK: {len(rows)} Event-Visual-Gap-Zeilen geschrieben: {OUT_PATH.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-gap-backlog.py ===
