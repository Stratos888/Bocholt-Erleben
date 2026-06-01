# === BEGIN FILE: scripts/audit-event-visual-generation-batches.py | Zweck: prueft die aus Phase-1-Plan und AI Style Guide erzeugten Generierungsbatches; Umfang: Konsistenzcheck fuer Batch-JSON, Request-Abdeckung und Prompt-Felder ===
from __future__ import annotations

import csv
import json
from pathlib import Path
from typing import Any, Dict, List, Set, Tuple

ROOT = Path(__file__).resolve().parents[1]
PHASE1_PATH = ROOT / "data" / "event_visual_phase1_plan.tsv"
STYLE_GUIDE_PATH = ROOT / "data" / "event_visual_ai_style_guide.json"
BATCHES_PATH = ROOT / "data" / "event_visual_generation_batches_phase1.json"


def read_json(path: Path) -> Dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
    except json.JSONDecodeError as exc:
        raise SystemExit(
            f"FEHLER: {path.relative_to(ROOT)} ist kein gültiges JSON "
            f"({exc.msg}, Zeile {exc.lineno}, Spalte {exc.colno})."
        )
    if not isinstance(value, dict):
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} muss ein JSON-Objekt sein.")
    return value


def read_tsv(path: Path) -> List[Dict[str, str]]:
    if not path.exists():
        raise SystemExit(f"FEHLER: {path.relative_to(ROOT)} fehlt.")
    with path.open("r", encoding="utf-8", newline="") as handle:
        return list(csv.DictReader(handle, delimiter="\t"))


def clean_text(value: Any) -> str:
    return str(value or "").strip()


def as_dict(value: Any) -> Dict[str, Any]:
    return value if isinstance(value, dict) else {}


def as_list(value: Any) -> List[Any]:
    return value if isinstance(value, list) else []


def main() -> int:
    phase1_rows = [row for row in read_tsv(PHASE1_PATH) if clean_text(row.get("visual_key"))]
    style_guide = read_json(STYLE_GUIDE_PATH)
    batches_payload = read_json(BATCHES_PATH)

    errors: List[str] = []

    expected_requests = len(phase1_rows)
    expected_ids: Set[Tuple[str, str, str]] = {
        (
            clean_text(row.get("visual_key")),
            clean_text(row.get("slot")),
            clean_text(row.get("image_id")),
        )
        for row in phase1_rows
    }

    generation_defaults = as_dict(batches_payload.get("generation_defaults"))
    batches = as_list(batches_payload.get("batches"))
    preferred_aspect_ratio = clean_text(as_dict(style_guide.get("generation_contract")).get("preferred_generation_aspect_ratio")) or "4:3"
    style_guide_rules = as_dict(style_guide.get("visual_key_rules"))

    if clean_text(generation_defaults.get("aspect_ratio")) != preferred_aspect_ratio:
        errors.append("generation_defaults.aspect_ratio passt nicht zum AI Style Guide.")

    if int(generation_defaults.get("max_requests_per_batch", 0) or 0) != 10:
        errors.append("generation_defaults.max_requests_per_batch muss 10 sein.")

    if int(generation_defaults.get("total_requests", 0) or 0) != expected_requests:
        errors.append("generation_defaults.total_requests passt nicht zur erwarteten Phase-1-Anzahl.")

    request_count = 0
    request_ids: Set[str] = set()
    represented_rows: Set[Tuple[str, str, str]] = set()

    for batch in batches:
        batch_dict = as_dict(batch)
        batch_id = clean_text(batch_dict.get("batch_id"))
        batch_visual_keys = {clean_text(value) for value in as_list(batch_dict.get("visual_keys"))}
        requests = as_list(batch_dict.get("requests"))

        if not batch_id:
            errors.append("Ein Batch hat keine batch_id.")
        if len(requests) > 10:
            errors.append(f"{batch_id}: mehr als 10 Requests im Batch.")

        for request in requests:
            req = as_dict(request)
            request_id = clean_text(req.get("request_id"))
            visual_key = clean_text(req.get("visual_key"))
            slot = clean_text(req.get("slot"))
            image_id = clean_text(req.get("image_id"))
            prompt = clean_text(req.get("prompt"))
            aspect_ratio = clean_text(req.get("aspect_ratio"))
            metadata = as_dict(req.get("metadata"))

            request_count += 1

            if not request_id:
                errors.append(f"{batch_id}: Request ohne request_id.")
            elif request_id in request_ids:
                errors.append(f"Doppelte request_id: {request_id}")
            else:
                request_ids.add(request_id)

            if visual_key not in batch_visual_keys:
                errors.append(f"{request_id}: visual_key {visual_key!r} ist nicht in batch.visual_keys enthalten.")

            if visual_key not in style_guide_rules:
                errors.append(f"{request_id}: visual_key {visual_key!r} fehlt im AI Style Guide.")

            if aspect_ratio != preferred_aspect_ratio:
                errors.append(f"{request_id}: aspect_ratio {aspect_ratio!r} passt nicht.")

            if len(prompt) < 200:
                errors.append(f"{request_id}: Prompt ist zu kurz.")

            if not image_id or not slot:
                errors.append(f"{request_id}: slot oder image_id fehlt.")

            if not isinstance(metadata.get("review_gates"), dict):
                errors.append(f"{request_id}: metadata.review_gates fehlt oder ist kein Objekt.")

            represented_rows.add((visual_key, slot, image_id))

    missing_rows = sorted(expected_ids - represented_rows)
    unknown_rows = sorted(represented_rows - expected_ids)

    if request_count != expected_requests:
        errors.append(f"Request-Anzahl passt nicht: erwartet {expected_requests}, gefunden {request_count}")
    if missing_rows:
        errors.append(f"Fehlende Phase-1-Requests: {missing_rows[:10]}")
    if unknown_rows:
        errors.append(f"Unbekannte Requests im Batch-JSON: {unknown_rows[:10]}")

    print("Event Visual Generation Batches Audit")
    print("====================================")
    print(f"Datei: {BATCHES_PATH.relative_to(ROOT)}")
    print(f"Erwartete Phase-1-Requests: {expected_requests}")
    print(f"Batches: {len(batches)}")
    print(f"Gefundene Requests: {request_count}")

    if errors:
        print("\nFEHLER:")
        for error in errors:
            print(f"- {error}")
        return 1

    print("\nOK: Generation-Batches sind konsistent und decken alle 22 Phase-1-Requests ab.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/audit-event-visual-generation-batches.py ===
