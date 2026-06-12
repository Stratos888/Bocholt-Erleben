# === BEGIN FILE: scripts/audit-event-visual-pool.py | Zweck: prueft Event-Visual-Key-, Motif- und Bildpool-Abdeckung; Umfang: Diagnose fuer lokale kuratierte Event-Bildbibliothek ohne Web-/KI-Suche ===
from __future__ import annotations

import json
import sys
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any, Dict, Iterable, List, Tuple

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_keys import ALLOWED_EVENT_VISUAL_KEYS, infer_event_visual_key, normalize_event_visual_key
from event_visual_motifs import (
    EVENT_VISUAL_MOTIF_RULES,
    fallback_event_visual_motif,
    infer_event_visual_fit,
    normalize_event_visual_motif,
)


POOL_PATH = ROOT / "data" / "event_visual_pool.json"
EVENT_SOURCES = [
    ROOT / "data" / "events.json",
    ROOT / "data" / "inbox.json",
    ROOT / "data" / "inbox_manual.json",
]


def read_json(path: Path) -> Any:
    if not path.exists():
        return None

    try:
        raw = path.read_text(encoding="utf-8").strip()
    except OSError as exc:
        print(f"HINWEIS: Überspringe {path.relative_to(ROOT)}: Datei nicht lesbar ({exc}).")
        return None

    if not raw:
        print(f"HINWEIS: Überspringe {path.relative_to(ROOT)}: Datei ist leer.")
        return None

    try:
        return json.loads(raw)
    except json.JSONDecodeError as exc:
        print(
            f"HINWEIS: Überspringe {path.relative_to(ROOT)}: kein gültiges JSON "
            f"({exc.msg}, Zeile {exc.lineno}, Spalte {exc.colno})."
        )
        return None


def as_list(payload: Any) -> List[Dict[str, Any]]:
    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, dict)]

    if isinstance(payload, dict):
        for key in ("events", "items", "data"):
            value = payload.get(key)
            if isinstance(value, list):
                return [item for item in value if isinstance(item, dict)]

    return []


def text_value(item: Dict[str, Any], *keys: str) -> str:
    for key in keys:
        value = item.get(key)
        if value is not None and str(value).strip():
            return str(value).strip()
    return ""


def iter_event_like_items() -> Iterable[Dict[str, Any]]:
    for source in EVENT_SOURCES:
        payload = read_json(source)
        for item in as_list(payload):
            yield {
                "source": source.relative_to(ROOT).as_posix(),
                "title": text_value(item, "title", "eventName"),
                "description": text_value(item, "description", "beschreibung"),
                "category": text_value(item, "kategorie", "category", "kategorie_suggestion"),
                "location": text_value(item, "location", "ort"),
                "visual_key": text_value(item, "visual_key", "visualKey", "image_visual_key"),
                "visual_motif": text_value(item, "visual_motif", "visualMotif", "image_visual_motif"),
            }


def resolve_visual_key(item: Dict[str, Any]) -> str:
    return normalize_event_visual_key(item.get("visual_key", "")) or infer_event_visual_key(
        title=item.get("title", ""),
        description=item.get("description", ""),
        category=item.get("category", ""),
        location=item.get("location", ""),
    )


def local_asset_exists(src: str) -> bool:
    value = str(src or "").strip()
    if not value.startswith("/"):
        return False
    return (ROOT / value.lstrip("/")).exists()


def validate_pool_motifs(pools: Dict[str, Any]) -> Tuple[List[str], List[str], Counter, Dict[str, Counter]]:
    errors: List[str] = []
    warnings: List[str] = []
    motif_status_counts: Counter = Counter()
    ready_motifs_by_key: Dict[str, Counter] = defaultdict(Counter)

    for visual_key, pool in pools.items():
        if visual_key not in EVENT_VISUAL_MOTIF_RULES:
            errors.append(f"Motif-Regeln fehlen fuer Pool-Key: {visual_key}")
            continue

        images = pool.get("images") if isinstance(pool, dict) else []
        for image in images if isinstance(images, list) else []:
            if not isinstance(image, dict):
                continue

            status = str(image.get("status") or "unknown").strip() or "unknown"
            raw_motif = str(image.get("visual_motif") or "").strip()
            if not raw_motif:
                continue

            motif = normalize_event_visual_motif(raw_motif, visual_key)
            if not motif:
                errors.append(f"{visual_key}/{image.get('id') or '<no-id>'}: unbekanntes visual_motif {raw_motif!r}")
                continue

            motif_status_counts[(visual_key, motif, status)] += 1
            if status == "ready":
                ready_motifs_by_key[visual_key][motif] += 1

    for visual_key in sorted(EVENT_VISUAL_MOTIF_RULES):
        fallback = fallback_event_visual_motif(visual_key)
        if fallback and ready_motifs_by_key.get(visual_key, Counter()).get(fallback, 0) < 1:
            warnings.append(f"{visual_key}: kein ready-Standardmotiv/Fallback im Pool ({fallback})")

    return errors, warnings, motif_status_counts, ready_motifs_by_key


def main() -> int:
    manifest = read_json(POOL_PATH)
    if not isinstance(manifest, dict):
        print(f"FEHLER: {POOL_PATH} nicht gefunden oder kein JSON-Objekt.")
        return 1

    pools = manifest.get("pools")
    if not isinstance(pools, dict):
        print("FEHLER: data/event_visual_pool.json enthaelt kein pools-Objekt.")
        return 1

    pool_keys = set(pools.keys())
    allowed_keys = set(ALLOWED_EVENT_VISUAL_KEYS)

    missing_pool_keys = sorted(allowed_keys - pool_keys)
    unknown_pool_keys = sorted(pool_keys - allowed_keys)
    missing_motif_rule_keys = sorted(allowed_keys - set(EVENT_VISUAL_MOTIF_RULES))
    unknown_motif_rule_keys = sorted(set(EVENT_VISUAL_MOTIF_RULES) - allowed_keys)

    status_counts = Counter()
    target_total = 0
    ready_missing_assets = []

    for key, pool in pools.items():
        images = pool.get("images") if isinstance(pool, dict) else []
        target_total += int(pool.get("target_count", 0) or 0) if isinstance(pool, dict) else 0

        for image in images if isinstance(images, list) else []:
            if not isinstance(image, dict):
                continue

            status = str(image.get("status") or "unknown").strip() or "unknown"
            status_counts[status] += 1

            if status == "ready" and not local_asset_exists(str(image.get("src") or "")):
                ready_missing_assets.append((key, str(image.get("src") or "")))

    motif_errors, motif_warnings, _, ready_motifs_by_key = validate_pool_motifs(pools)

    distribution = Counter()
    motif_distribution = Counter()
    asset_status_distribution = Counter()
    source_counts = Counter()
    for item in iter_event_like_items():
        fit = infer_event_visual_fit(
            title=item.get("title", ""),
            description=item.get("description", ""),
            category=item.get("category", ""),
            location=item.get("location", ""),
            visual_key=item.get("visual_key", ""),
            visual_motif=item.get("visual_motif", ""),
            pool_payload=manifest,
        )
        distribution[fit["visual_key"]] += 1
        motif_distribution[(fit["visual_key"], fit["visual_motif"])] += 1
        asset_status_distribution[fit["visual_asset_status"]] += 1
        source_counts[item["source"]] += 1

    print("Event Visual Pool Audit")
    print("=======================")
    print(f"Pool-Datei: {POOL_PATH.relative_to(ROOT)}")
    print(f"Pool-Keys: {len(pool_keys)} / erlaubt: {len(allowed_keys)}")
    print(f"Motif-Key-Regeln: {len(EVENT_VISUAL_MOTIF_RULES)} / erlaubt: {len(allowed_keys)}")
    print(f"Geplante Zielslots: {target_total}")
    print("Slot-Status:")
    for status, count in sorted(status_counts.items()):
        print(f"- {status}: {count}")

    print("\nReady-Motive pro Visual-Key:")
    for key in sorted(ready_motifs_by_key):
        rendered = ", ".join(f"{motif}:{count}" for motif, count in sorted(ready_motifs_by_key[key].items()))
        print(f"- {key}: {rendered}")

    if motif_warnings:
        print("\nMotif-Hinweise:")
        for warning in motif_warnings:
            print(f"- {warning}")

    if source_counts:
        print("\nAusgewertete Event-Quellen:")
        for source, count in sorted(source_counts.items()):
            print(f"- {source}: {count}")

    if distribution:
        print("\nAktuelle Visual-Key-Verteilung:")
        for key, count in distribution.most_common():
            print(f"- {key}: {count}")

        print("\nAktuelle Visual-Motif-Verteilung:")
        for (key, motif), count in motif_distribution.most_common(30):
            print(f"- {key}/{motif}: {count}")

        print("\nAktuelle Visual-Asset-Status-Verteilung:")
        for status, count in sorted(asset_status_distribution.items()):
            print(f"- {status}: {count}")
    else:
        print("\nAktuelle Visual-Key-Verteilung: keine Eventdaten gefunden.")

    errors = []
    if missing_pool_keys:
        errors.append(f"Fehlende Pool-Keys: {', '.join(missing_pool_keys)}")
    if unknown_pool_keys:
        errors.append(f"Unbekannte Pool-Keys: {', '.join(unknown_pool_keys)}")
    if missing_motif_rule_keys:
        errors.append(f"Fehlende Motif-Regeln: {', '.join(missing_motif_rule_keys)}")
    if unknown_motif_rule_keys:
        errors.append(f"Unbekannte Motif-Regel-Keys: {', '.join(unknown_motif_rule_keys)}")
    if ready_missing_assets:
        errors.append("Ready-Assets fehlen lokal: " + ", ".join(f"{key}:{src}" for key, src in ready_missing_assets[:12]))
    errors.extend(motif_errors)

    if errors:
        print("\nFEHLER:")
        for error in errors:
            print(f"- {error}")
        return 1

    print("\nOK: Pool-Struktur und Motif-Regeln sind konsistent. Motif-Hinweise koennen als Gap-Backlog weiterbearbeitet werden. Planned-Slots werden noch nicht live genutzt.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
# === END FILE: scripts/audit-event-visual-pool.py ===
