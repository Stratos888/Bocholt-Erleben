# === BEGIN FILE: scripts/build-event-visual-motif-matrix.py | Zweck: baut die Event-Visual-Motiv-Matrix aus Sheet-TSV, Taxonomie, Pool und Kandidatenlog; Umfang: generierter Steuerreport ohne UI-Aenderung ===
from __future__ import annotations

import argparse
import csv
import json
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Tuple

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_motifs import (  # noqa: E402
    EVENT_VISUAL_MOTIF_RULES,
    event_visual_motif_role,
    infer_event_visual_fit,
    load_event_visual_pool,
    normalize_event_visual_motif,
)
from event_visual_keys import normalize_event_visual_key  # noqa: E402

DEFAULT_EVENTS_TSV = ROOT / "data" / "events.tsv"
DEFAULT_POOL_PATH = ROOT / "data" / "event_visual_pool.json"
DEFAULT_CANDIDATES_PATH = ROOT / "data" / "event_visual_phase2_acceptance_notes.json"
DEFAULT_OUTPUT_PATH = ROOT / "data" / "event_visual_motif_matrix.tsv"

FIELDS = [
    "visual_key",
    "visual_motif",
    "motif_role",
    "motif_label",
    "sheet_needed",
    "sheet_event_count",
    "sheet_example_events",
    "ready_image_count",
    "ready_image_ids",
    "candidate_count",
    "candidate_image_ids",
    "candidate_statuses",
    "gap",
    "next_action",
    "priority",
    "notes",
]

HIGH_PRIORITY_MOTIFS = {
    ("indoor_sport_competition", "fencing"),
    ("indoor_sport_competition", "darts"),
    ("classical_music", "choir"),
    ("classical_music", "organ_concert"),
    ("kids_stage_story", "puppet_theater"),
    ("food_drink_festival", "wine_festival"),
    ("market_stalls", "fabric_market"),
    ("business_messe_info", "health_career_fair"),
}

ACCEPTED_CANDIDATE_STATUSES = {
    "accepted",
    "downloaded_confirmed",
    "selected_pending_confirmation",
}


def clean(value: object) -> str:
    return str(value or "").replace("\n", " ").replace("\r", " ").strip()


def rel(path: Path) -> str:
    try:
        return path.relative_to(ROOT).as_posix()
    except ValueError:
        return path.as_posix()


def read_sheet_events(path: Path) -> Tuple[List[Dict[str, str]], int, int]:
    if not path.exists():
        raise FileNotFoundError(f"Events-TSV nicht gefunden: {path}")

    with path.open("r", encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle, delimiter="\t")
        rows = list(reader)

    events: List[Dict[str, str]] = []
    empty_rows = 0
    for row in rows:
        normalized = {clean(key): clean(value) for key, value in row.items() if key is not None}
        if not any(normalized.values()):
            empty_rows += 1
            continue
        title = normalized.get("title", "")
        if not title:
            empty_rows += 1
            continue
        events.append(
            {
                "id": normalized.get("id", ""),
                "title": title,
                "date": normalized.get("date", ""),
                "endDate": normalized.get("endDate", ""),
                "time": normalized.get("time", ""),
                "city": normalized.get("city", ""),
                "location": normalized.get("location", ""),
                "category": normalized.get("kategorie", "") or normalized.get("category", ""),
                "url": normalized.get("url", ""),
                "description": normalized.get("description", ""),
                "visual_key": normalized.get("visual_key", ""),
                "visual_motif": normalized.get("visual_motif", ""),
            }
        )

    return events, len(rows), empty_rows


def image_id_for(image: Mapping[str, Any]) -> str:
    return clean(image.get("id")) or clean(image.get("src")).rsplit("/", 1)[-1]


def ready_images_by_motif(pool_payload: Mapping[str, Any]) -> Dict[Tuple[str, str], List[str]]:
    out: Dict[Tuple[str, str], List[str]] = defaultdict(list)
    pools = pool_payload.get("pools", {}) if isinstance(pool_payload, Mapping) else {}
    if not isinstance(pools, Mapping):
        return out

    for raw_key, pool in pools.items():
        key = normalize_event_visual_key(raw_key)
        if not key or not isinstance(pool, Mapping):
            continue
        images = pool.get("images", [])
        if not isinstance(images, list):
            continue
        for image in images:
            if not isinstance(image, Mapping):
                continue
            if clean(image.get("status")) != "ready":
                continue
            motif = normalize_event_visual_motif(image.get("visual_motif", ""), key)
            if not motif:
                continue
            out[(key, motif)].append(image_id_for(image))
    return out


def read_candidate_records(path: Path) -> List[Dict[str, Any]]:
    if not path.exists() or not path.read_text(encoding="utf-8").strip():
        return []
    payload = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(payload, dict) and isinstance(payload.get("records"), list):
        return [record for record in payload["records"] if isinstance(record, dict)]
    if isinstance(payload, list):
        return [record for record in payload if isinstance(record, dict)]
    return []


def candidate_images_by_motif(records: Iterable[Mapping[str, Any]]) -> Tuple[Dict[Tuple[str, str], List[Dict[str, str]]], int]:
    out: Dict[Tuple[str, str], List[Dict[str, str]]] = defaultdict(list)
    ignored_without_canonical_motif = 0

    for record in records:
        status = clean(record.get("status"))
        production_status = clean(record.get("production_status"))
        if status not in ACCEPTED_CANDIDATE_STATUSES and production_status not in ACCEPTED_CANDIDATE_STATUSES:
            continue

        key = normalize_event_visual_key(record.get("visual_key", ""))
        if not key:
            continue

        # visual_motif ist kanonisch. sub_motif wird nur genutzt, wenn es exakt auf ein erlaubtes Motiv normalisiert.
        motif = normalize_event_visual_motif(record.get("visual_motif", ""), key)
        if not motif:
            motif = normalize_event_visual_motif(record.get("sub_motif", ""), key)
        if not motif:
            ignored_without_canonical_motif += 1
            continue

        out[(key, motif)].append(
            {
                "image_id": clean(record.get("image_id")) or clean(record.get("target_webp")),
                "status": production_status or status,
            }
        )

    return out, ignored_without_canonical_motif


def event_example(item: Mapping[str, str]) -> str:
    title = clean(item.get("title"))
    date = clean(item.get("date"))
    return f"{title} ({date})" if date else title


def priority_for(pair: Tuple[str, str], needed_count: int, action: str) -> str:
    if action not in {"gap_to_produce", "candidate_to_integrate", "review_rules"}:
        return ""
    if pair in HIGH_PRIORITY_MOTIFS:
        return "high"
    if needed_count > 0:
        return "medium"
    return "low"


def infer_needed_by_motif(events: Iterable[Mapping[str, str]], pool_payload: Mapping[str, Any]) -> Dict[Tuple[str, str], List[Dict[str, str]]]:
    out: Dict[Tuple[str, str], List[Dict[str, str]]] = defaultdict(list)
    for item in events:
        fit = infer_event_visual_fit(
            title=item.get("title", ""),
            description=item.get("description", ""),
            category=item.get("category", ""),
            location=item.get("location", ""),
            visual_key=item.get("visual_key", ""),
            visual_motif=item.get("visual_motif", ""),
            pool_payload=pool_payload,
        )
        key = normalize_event_visual_key(fit.get("visual_key", ""))
        motif = normalize_event_visual_motif(fit.get("visual_motif", ""), key)
        if not key or not motif:
            continue
        out[(key, motif)].append(dict(item))
    return out


def build_matrix(events_tsv: Path, pool_path: Path, candidates_path: Path) -> Tuple[List[Dict[str, str]], Dict[str, int]]:
    events, raw_rows, empty_rows = read_sheet_events(events_tsv)
    pool_payload = load_event_visual_pool(pool_path)
    ready = ready_images_by_motif(pool_payload)
    candidates, ignored_candidates = candidate_images_by_motif(read_candidate_records(candidates_path))
    needed = infer_needed_by_motif(events, pool_payload)

    pairs = set(needed) | set(ready) | set(candidates)
    for key, motifs in EVENT_VISUAL_MOTIF_RULES.items():
        for motif in motifs:
            pairs.add((key, motif))

    rows: List[Dict[str, str]] = []
    for key, motif in sorted(pairs):
        motif_meta = EVENT_VISUAL_MOTIF_RULES.get(key, {}).get(motif, {})
        role = event_visual_motif_role(key, motif) or clean(motif_meta.get("role"))
        label = clean(motif_meta.get("label"))
        examples = needed.get((key, motif), [])
        ready_ids = ready.get((key, motif), [])
        candidate_records = candidates.get((key, motif), [])
        candidate_ids = [record["image_id"] for record in candidate_records if record.get("image_id")]
        candidate_statuses = Counter(record.get("status", "") for record in candidate_records if record.get("status"))
        needed_count = len(examples)
        ready_count = len(ready_ids)
        candidate_count = len(candidate_records)
        sheet_needed = "yes" if needed_count > 0 else "no"
        gap = "yes" if needed_count > 0 and role != "review" and ready_count == 0 and candidate_count == 0 else "no"

        if needed_count > 0 and role == "review":
            next_action = "review_rules"
        elif needed_count > 0 and ready_count > 0:
            next_action = "ready"
        elif needed_count > 0 and candidate_count > 0:
            next_action = "candidate_to_integrate"
        elif needed_count > 0:
            next_action = "gap_to_produce"
        elif candidate_count > 0:
            next_action = "parked_candidate"
        else:
            next_action = "not_needed"

        row = {
            "visual_key": key,
            "visual_motif": motif,
            "motif_role": role,
            "motif_label": label,
            "sheet_needed": sheet_needed,
            "sheet_event_count": str(needed_count),
            "sheet_example_events": " | ".join(event_example(item) for item in examples[:3]),
            "ready_image_count": str(ready_count),
            "ready_image_ids": "; ".join(ready_ids[:8]),
            "candidate_count": str(candidate_count),
            "candidate_image_ids": "; ".join(candidate_ids[:8]),
            "candidate_statuses": "; ".join(f"{status}:{count}" for status, count in sorted(candidate_statuses.items())),
            "gap": gap,
            "next_action": next_action,
            "priority": priority_for((key, motif), needed_count, next_action),
            "notes": "generated_from_sheet_tsv",
        }
        rows.append(row)

    action_order = {
        "review_rules": 0,
        "gap_to_produce": 1,
        "candidate_to_integrate": 2,
        "ready": 3,
        "parked_candidate": 4,
        "not_needed": 5,
    }
    priority_order = {"high": 0, "medium": 1, "low": 2, "": 9}
    rows.sort(
        key=lambda row: (
            action_order.get(row["next_action"], 9),
            priority_order.get(row["priority"], 9),
            row["visual_key"],
            row["visual_motif"],
        )
    )

    stats = {
        "raw_rows": raw_rows,
        "empty_rows_skipped": empty_rows,
        "events_used": len(events),
        "matrix_rows": len(rows),
        "candidate_records_without_canonical_motif": ignored_candidates,
    }
    return rows, stats


def write_matrix(rows: Iterable[Mapping[str, str]], output_path: Path) -> None:
    output_path.parent.mkdir(parents=True, exist_ok=True)
    with output_path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=FIELDS, delimiter="\t", lineterminator="\n")
        writer.writeheader()
        for row in rows:
            writer.writerow({field: row.get(field, "") for field in FIELDS})


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Build generated Event Visual motif matrix TSV.")
    parser.add_argument("--events-tsv", type=Path, default=DEFAULT_EVENTS_TSV, help="Sheet export TSV, default: data/events.tsv")
    parser.add_argument("--pool", type=Path, default=DEFAULT_POOL_PATH, help="Event visual pool JSON")
    parser.add_argument("--candidates", type=Path, default=DEFAULT_CANDIDATES_PATH, help="Accepted/selected candidate log JSON")
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT_PATH, help="Generated matrix TSV output")
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    events_tsv = args.events_tsv if args.events_tsv.is_absolute() else ROOT / args.events_tsv
    pool_path = args.pool if args.pool.is_absolute() else ROOT / args.pool
    candidates_path = args.candidates if args.candidates.is_absolute() else ROOT / args.candidates
    output_path = args.output if args.output.is_absolute() else ROOT / args.output

    rows, stats = build_matrix(events_tsv, pool_path, candidates_path)
    write_matrix(rows, output_path)

    action_counts = Counter(row["next_action"] for row in rows)
    print(f"OK: {stats['matrix_rows']} Matrix-Zeilen geschrieben: {rel(output_path)}")
    print(f"Quelle: {rel(events_tsv)}; Rohzeilen: {stats['raw_rows']}; Events genutzt: {stats['events_used']}; leere/ungueltige Zeilen uebersprungen: {stats['empty_rows_skipped']}")
    print("Aktionen: " + ", ".join(f"{key}={action_counts[key]}" for key in sorted(action_counts)))
    if stats["candidate_records_without_canonical_motif"]:
        print(f"Hinweis: {stats['candidate_records_without_canonical_motif']} Kandidaten ohne kanonisches visual_motif wurden nicht motifgenau gewertet.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/build-event-visual-motif-matrix.py ===
