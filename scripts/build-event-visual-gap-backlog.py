# === BEGIN FILE: scripts/build-event-visual-gap-backlog.py | Zweck: idempotenter Visual-Gap-Prozess mit Rueckfuehrung fertiger Asset-Kandidaten ===
from __future__ import annotations

import csv
import hashlib
import json
import os
import sys
from pathlib import Path
from typing import Any, Dict, Iterable, List

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_motifs import infer_event_visual_fit, load_event_visual_pool
from content_ops_visual_feedback import classify_visual_issue, load_visual_feedback_contract

OUT_PATH = ROOT / "data" / "event_visual_gap_backlog.tsv"
JSON_OUT_PATH = ROOT / "data" / "event_visual_gap_backlog.json"
DEFAULT_SOURCES = [ROOT / "data" / "events.json", ROOT / "data" / "inbox.json", ROOT / "data" / "inbox_manual.json"]
FIELDS = [
    # Keep the historical columns first so existing CSV/TSV consumers remain compatible.
    "status", "priority", "event_title", "event_date", "visual_key", "visual_motif",
    "problem", "recommended_action", "source_url", "notes",
    # Extended per-event process and return-loop fields.
    "gap_id", "request_group_key", "event_id", "visual_problem_type", "decision_class",
    "followup_route", "requires_task", "candidate_asset_id",
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
    return [normalize_event_item(item, source=path.relative_to(ROOT).as_posix()) for item in as_list(payload)]


def read_table_items(path: Path) -> Iterable[Dict[str, str]]:
    delimiter = "\t" if path.suffix.lower() == ".tsv" else ","
    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle, delimiter=delimiter)
        return [normalize_event_item(row, source=path.name) for row in reader]


def normalize_event_item(item: Dict[str, Any], source: str) -> Dict[str, str]:
    return {
        "source": source,
        "id": clean(item.get("id_suggestion") or item.get("id") or item.get("event_id")),
        "title": clean(item.get("title") or item.get("eventName")),
        "date": clean(item.get("date") or item.get("start_date")),
        "description": clean(item.get("description") or item.get("beschreibung") or item.get("final_description")),
        "category": clean(item.get("kategorie") or item.get("category") or item.get("kategorie_suggestion")),
        "location": clean(item.get("location") or item.get("ort") or item.get("venue")),
        "visual_key": clean(item.get("visual_key") or item.get("visualKey") or item.get("image_visual_key")),
        "visual_motif": clean(item.get("visual_motif") or item.get("visualMotif") or item.get("image_visual_motif")),
        "visual_asset_id": clean(item.get("visual_asset_id") or item.get("image_asset_id")),
        "visual_gap_id": clean(item.get("visual_gap_id")),
        "url": clean(item.get("source_url") or item.get("url") or item.get("event_url")),
    }


def stable_event_identity(item: Dict[str, str]) -> str:
    if item.get("id"):
        return item["id"]
    return "|".join([item.get("title", ""), item.get("date", ""), item.get("url", "")])


def stable_gap_id(item: Dict[str, str], visual_key: str, visual_motif: str) -> str:
    if item.get("visual_gap_id"):
        return item["visual_gap_id"]
    seed = f"{stable_event_identity(item)}|{visual_key}|{visual_motif}".encode("utf-8")
    return "visual-gap-" + hashlib.sha256(seed).hexdigest()[:20]


def ready_asset_candidates(pool: Dict[str, Any], visual_key: str, visual_motif: str) -> List[Dict[str, str]]:
    images = (((pool.get("pools") or {}).get(visual_key) or {}).get("images") or [])
    out: List[Dict[str, str]] = []
    for image in images:
        if not isinstance(image, dict) or clean(image.get("status")) != "ready":
            continue
        if clean(image.get("visual_motif")) != visual_motif:
            continue
        asset_id = clean(image.get("id"))
        src = clean(image.get("src"))
        if asset_id and src:
            out.append({"id": asset_id, "src": src, "alt": clean(image.get("alt"))})
    return out


def priority_for(fit: Dict[str, str], item: Dict[str, str]) -> str:
    high = {
        ("indoor_sport_competition", "fencing"), ("indoor_sport_competition", "darts"),
        ("classical_music", "choir"), ("classical_music", "organ_concert"),
        ("kids_stage_story", "puppet_theater"), ("food_drink_festival", "wine_festival"),
        ("market_stalls", "fabric_market"), ("business_messe_info", "health_career_fair"),
    }
    if (fit.get("visual_key", ""), fit.get("visual_motif", "")) in high:
        return "high"
    if "2026" in item.get("date", "") or item.get("title"):
        return "medium"
    return "low"


def build_rows(items: Iterable[Dict[str, str]]) -> List[Dict[str, str]]:
    pool = load_event_visual_pool()
    feedback_contract = load_visual_feedback_contract()
    rows_by_gap: Dict[str, Dict[str, str]] = {}
    for item in items:
        if not item.get("title"):
            continue
        fit = infer_event_visual_fit(
            title=item.get("title", ""), description=item.get("description", ""),
            category=item.get("category", ""), location=item.get("location", ""),
            visual_key=item.get("visual_key", ""), visual_motif=item.get("visual_motif", ""), pool_payload=pool,
        )
        visual_key = fit.get("visual_key", "")
        visual_motif = fit.get("visual_motif", "")
        if not visual_key or not visual_motif:
            continue
        candidates = ready_asset_candidates(pool, visual_key, visual_motif)
        has_open_gap = bool(item.get("visual_gap_id"))
        if fit.get("visual_asset_status") == "ok" and not has_open_gap:
            continue

        gap_id = stable_gap_id(item, visual_key, visual_motif)
        status = "candidate_ready" if candidates else ("review" if fit.get("visual_asset_status") == "review" else "open")
        problem = "Motiv erkannt, aber kein passendes ready-Bild im Event-Visual-Pool."
        recommended = "Bild automatisch erzeugen oder rechtssicher beschaffen und anschließend als ready registrieren."
        candidate_asset_id = ""
        if status == "review":
            problem = "Motiv oder Key ist ein Review-Fall und braucht eine fachliche Motiventscheidung."
            recommended = "Motiv in der Steuerzentrale bestätigen oder korrigieren."
        elif status == "candidate_ready":
            candidate_asset_id = candidates[0]["id"]
            problem = "Für den offenen Visual-Gap ist jetzt ein freigegebenes Asset verfügbar."
            recommended = "Asset-Kandidat in derselben Eventprüfung anzeigen und verbindlich bestätigen lassen."

        problem_type = "asset_missing" if status == "open" else "visual_review"
        feedback = classify_visual_issue({"visual_problem_type": problem_type}, feedback_contract)
        rows_by_gap[gap_id] = {
            "gap_id": gap_id,
            "request_group_key": f"{visual_key}|{visual_motif}",
            "status": status,
            "priority": priority_for(fit, item),
            "event_id": item.get("id", ""),
            "event_title": item.get("title", ""),
            "event_date": item.get("date", ""),
            "visual_problem_type": feedback.get("problem_type", problem_type),
            "decision_class": feedback.get("decision_class", "needs_visual_fix"),
            "followup_route": feedback.get("followup_route", "visual_review"),
            "requires_task": "true" if feedback.get("requires_task", True) else "false",
            "visual_key": visual_key,
            "visual_motif": visual_motif,
            "candidate_asset_id": candidate_asset_id,
            "problem": problem,
            "recommended_action": recommended,
            "source_url": item.get("url", ""),
            "notes": f"Quelle: {item.get('source', '')}; Rueckfuehrung erfolgt ueber visual_gap_id und erneute Pool-Aufloesung.",
        }
    order = {"high": 0, "medium": 1, "low": 2}
    return sorted(rows_by_gap.values(), key=lambda row: (order.get(row["priority"], 9), row["status"], row["visual_key"], row["visual_motif"], row["event_date"]))


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
    JSON_OUT_PATH.write_text(json.dumps({"schema_version": 2, "items": rows}, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"OK: {len(rows)} idempotente Event-Visual-Gaps geschrieben: {OUT_PATH.relative_to(ROOT)} und {JSON_OUT_PATH.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-gap-backlog.py ===
