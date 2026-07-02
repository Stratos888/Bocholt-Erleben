# === BEGIN FILE: scripts/audit-event-feed-visual-diversity.py | Zweck: simuliert die Event-Feed-Bildauswahl und meldet sichtbare Wiederholungen/Serien-Risiken; Umfang: lokaler Audit ohne Webzugriff, optional nicht-blockierend im Content-Workflow ===
from __future__ import annotations

import argparse
import csv
import json
import re
import sys
from collections import Counter, defaultdict, deque
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Optional, Sequence, Tuple

ROOT = Path(__file__).resolve().parents[1]
SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from event_visual_keys import infer_event_visual_key, normalize_event_visual_key
from event_visual_motifs import (
    EVENT_VISUAL_MOTIF_RULES,
    fallback_event_visual_motif,
    infer_event_visual_fit,
    normalize_event_visual_motif,
)

DEFAULT_EVENT_SOURCES = [
    ROOT / "data" / "events.tsv",
    ROOT / "data" / "events.json",
    ROOT / "data" / "inbox.json",
    ROOT / "data" / "inbox_manual.json",
]
DEFAULT_VISUAL_POOL = ROOT / "data" / "event_visual_pool.json"
FALLBACK_MOTIFS = {
    key
    for key, rules in EVENT_VISUAL_MOTIF_RULES.items()
    for key, meta in rules.items()
    if isinstance(meta, Mapping) and meta.get("role") == "fallback"
}
MIN_MOTIF_DIVERSITY = 3
DIVERSITY_RELATED_MOTIFS_BY_KEY = {
    "live_music_stage": {
        "local_band_concert",
        "music_school_fest",
        "open_air_concert",
        "tribute_band",
    },
}


def norm(value: Any) -> str:
    return str(value or "").strip()


def token(value: Any) -> str:
    raw = norm(value).lower()
    raw = (
        raw.replace("ä", "ae")
        .replace("ö", "oe")
        .replace("ü", "ue")
        .replace("ß", "ss")
    )
    raw = re.sub(r"[\s\-]+", "_", raw)
    raw = re.sub(r"[^a-z0-9_]", "", raw)
    raw = re.sub(r"_+", "_", raw).strip("_")
    return raw


def stable_hash(value: Any) -> int:
    text = norm(value)
    h = 2166136261
    for ch in text:
        h ^= ord(ch)
        h = (h * 16777619) & 0xFFFFFFFF
    return h


def parse_local_date(value: Any) -> Optional[date]:
    raw = norm(value)[:10]
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except ValueError:
        return None


def parse_time_minutes(value: Any) -> int:
    match = re.search(r"\b(\d{1,2})[:.](\d{2})\b", norm(value))
    if not match:
        return 24 * 60 + 99
    return int(match.group(1)) * 60 + int(match.group(2))


def effective_date(item: Mapping[str, Any], today: date) -> Optional[date]:
    start = parse_local_date(item.get("date"))
    if not start:
        return None
    end = parse_local_date(item.get("endDate") or item.get("end_date"))
    if end and start <= today <= end:
        return today
    return start


def read_json(path: Path) -> Any:
    if not path.exists():
        return None
    try:
        raw = path.read_text(encoding="utf-8").strip()
    except OSError:
        return None
    if not raw:
        return None
    try:
        return json.loads(raw)
    except json.JSONDecodeError:
        return None


def rows_from_json_payload(payload: Any) -> List[Dict[str, Any]]:
    if isinstance(payload, list):
        return [item for item in payload if isinstance(item, dict)]
    if isinstance(payload, dict):
        for key in ("events", "items", "data"):
            value = payload.get(key)
            if isinstance(value, list):
                return [item for item in value if isinstance(item, dict)]
    return []


def read_tsv(path: Path) -> List[Dict[str, Any]]:
    with path.open("r", encoding="utf-8", newline="") as handle:
        return [dict(row) for row in csv.DictReader(handle, delimiter="\t")]


def load_events(sources: Sequence[Path]) -> Tuple[Path, List[Dict[str, Any]]]:
    for source in sources:
        if not source.exists() or source.stat().st_size <= 0:
            continue
        if source.suffix.lower() == ".tsv":
            rows = read_tsv(source)
        else:
            rows = rows_from_json_payload(read_json(source))
        rows = [normalize_event_row(row, source) for row in rows]
        if rows:
            return source, rows
    return sources[0], []


def normalize_event_row(row: Mapping[str, Any], source: Path) -> Dict[str, Any]:
    return {
        "source": source.relative_to(ROOT).as_posix() if source.is_relative_to(ROOT) else source.as_posix(),
        "id": norm(row.get("id") or row.get("event_id") or row.get("slug")),
        "title": norm(row.get("title") or row.get("eventName") or row.get("name")),
        "description": norm(row.get("description") or row.get("beschreibung") or row.get("text")),
        "category": norm(row.get("kategorie") or row.get("category") or row.get("kategorie_suggestion")),
        "location": norm(row.get("location") or row.get("ort") or row.get("venue")),
        "date": norm(row.get("date") or row.get("datum") or row.get("startDate") or row.get("start_date")),
        "endDate": norm(row.get("endDate") or row.get("end_date") or row.get("bis") or row.get("end")),
        "time": norm(row.get("time") or row.get("uhrzeit") or row.get("startTime") or row.get("start_time")),
        "visual_key": norm(row.get("visual_key") or row.get("visualKey") or row.get("image_visual_key")),
        "visual_motif": norm(row.get("visual_motif") or row.get("visualMotif") or row.get("image_visual_motif")),
    }


def is_ready_image(image: Mapping[str, Any]) -> bool:
    return norm(image.get("status")) == "ready" and bool(norm(image.get("src")))


def normalize_asset_url(src: Any) -> str:
    value = norm(src)
    if not value:
        return ""
    if value.startswith("/"):
        return value
    return "/" + value.lstrip("./")


def build_ready_pools(pool_payload: Mapping[str, Any]) -> Dict[str, List[Dict[str, str]]]:
    out: Dict[str, List[Dict[str, str]]] = {}
    pools = pool_payload.get("pools") if isinstance(pool_payload, Mapping) else {}
    if not isinstance(pools, Mapping):
        return out

    for raw_key, pool in pools.items():
        visual_key = normalize_event_visual_key(raw_key)
        images = pool.get("images") if isinstance(pool, Mapping) else []
        ready: List[Dict[str, str]] = []
        for image in images if isinstance(images, list) else []:
            if not isinstance(image, Mapping) or not is_ready_image(image):
                continue
            ready.append(
                {
                    "id": norm(image.get("id")),
                    "src": normalize_asset_url(image.get("src")),
                    "visual_motif": normalize_event_visual_motif(image.get("visual_motif") or image.get("visualMotif"), visual_key),
                    "visual_motif_role": token(image.get("visual_motif_role") or image.get("visualMotifRole")),
                }
            )
        if visual_key and ready:
            out[visual_key] = ready
    return out


def is_fallback_visual(candidate: Mapping[str, Any], visual_key: str) -> bool:
    motif = normalize_event_visual_motif(candidate.get("visual_motif"), visual_key)
    if not motif:
        return True
    if token(candidate.get("visual_motif_role")) == "fallback":
        return True
    return motif == fallback_event_visual_motif(visual_key) or motif in FALLBACK_MOTIFS


def is_related_diversity_visual(candidate: Mapping[str, Any], visual_key: str, visual_motif: str) -> bool:
    related = DIVERSITY_RELATED_MOTIFS_BY_KEY.get(normalize_event_visual_key(visual_key), set())
    if not related:
        return False
    candidate_motif = normalize_event_visual_motif(candidate.get("visual_motif"), visual_key)
    motif = normalize_event_visual_motif(visual_motif, visual_key)
    return bool(candidate_motif and candidate_motif != motif and candidate_motif in related)


def dedupe_candidates(candidates: Iterable[Mapping[str, Any]]) -> List[Dict[str, str]]:
    seen = set()
    out: List[Dict[str, str]] = []
    for candidate in candidates:
        src = norm(candidate.get("src"))
        if not src:
            continue
        key = norm(candidate.get("id")) or src
        if not key or key in seen:
            continue
        seen.add(key)
        out.append(dict(candidate))
    return out


def candidate_pool(pool: Sequence[Mapping[str, Any]], visual_key: str, visual_motif: str, min_motif_diversity: int) -> List[Dict[str, str]]:
    ready = [dict(candidate) for candidate in pool if norm(candidate.get("src"))]
    if not ready:
        return []

    motif = normalize_event_visual_motif(visual_motif, visual_key)
    if motif:
        exact = [candidate for candidate in ready if normalize_event_visual_motif(candidate.get("visual_motif"), visual_key) == motif]
        if len(exact) >= min_motif_diversity:
            return exact
        if exact:
            neutral = [
                candidate for candidate in ready
                if normalize_event_visual_motif(candidate.get("visual_motif"), visual_key) != motif
                and is_fallback_visual(candidate, visual_key)
            ]
            related = [candidate for candidate in ready if is_related_diversity_visual(candidate, visual_key, motif)]
            expanded = dedupe_candidates([*exact, *neutral, *related])
            return expanded or exact

    neutral = [candidate for candidate in ready if is_fallback_visual(candidate, visual_key)]
    return neutral or ready


def visual_usage_key(visual: Mapping[str, Any]) -> str:
    return norm(visual.get("id")) or norm(visual.get("src"))


def pick_visual(candidates: Sequence[Mapping[str, Any]], seed: str, used_for_scope: set[str], recent: deque[str]) -> Optional[Dict[str, str]]:
    if not candidates:
        return None
    start = stable_hash(seed) % len(candidates)

    for predicate in (
        lambda key: key and key not in used_for_scope and key not in recent,
        lambda key: key and key not in recent,
        lambda key: key and key not in used_for_scope,
        lambda key: bool(key),
    ):
        for offset in range(len(candidates)):
            candidate = dict(candidates[(start + offset) % len(candidates)])
            key = visual_usage_key(candidate)
            if predicate(key):
                return candidate
    return None


def resolve_visual(
    item: Mapping[str, Any],
    ready_pools: Mapping[str, Sequence[Mapping[str, Any]]],
    pool_payload: Mapping[str, Any],
    usage_by_scope: Dict[str, set[str]],
    recent: deque[str],
    min_motif_diversity: int,
) -> Tuple[str, str, Optional[Dict[str, str]], int]:
    fit = infer_event_visual_fit(
        title=item.get("title", ""),
        description=item.get("description", ""),
        category=item.get("category", ""),
        location=item.get("location", ""),
        visual_key=item.get("visual_key", ""),
        visual_motif=item.get("visual_motif", ""),
        pool_payload=pool_payload,
    )
    visual_key = fit.get("visual_key", "")
    visual_motif = fit.get("visual_motif", "")
    pool = ready_pools.get(visual_key, [])
    candidates = candidate_pool(pool, visual_key, visual_motif, min_motif_diversity)
    if not candidates:
        return visual_key, visual_motif, None, 0

    scope = f"{visual_key}:{visual_motif}" if visual_motif else visual_key
    used = usage_by_scope.setdefault(scope, set())
    seed = "|".join(
        [
            norm(item.get("id")),
            norm(item.get("date")),
            norm(item.get("endDate")),
            norm(item.get("title")),
            visual_key,
            visual_motif,
        ]
    )
    visual = pick_visual(candidates, seed, used, recent)
    if visual:
        key = visual_usage_key(visual)
        if key:
            used.add(key)
            recent.append(key)
    return visual_key, visual_motif, visual, len(candidates)


def normalized_series_title(title: Any) -> str:
    value = norm(title).lower()
    value = (
        value.replace("ä", "ae")
        .replace("ö", "oe")
        .replace("ü", "ue")
        .replace("ß", "ss")
    )
    value = re.sub(r"\b\d{1,2}[.:]\d{2}\b", " ", value)
    value = re.sub(r"\b\d{4}\b", " ", value)
    value = re.sub(r"\b\d{1,2}\b", " ", value)
    value = re.sub(r"\s+", " ", value).strip()
    return value


def audit(args: argparse.Namespace) -> int:
    pool_payload = read_json(args.visual_pool)
    if not isinstance(pool_payload, Mapping):
        print(f"FEHLER: Visual-Pool nicht lesbar: {args.visual_pool}")
        return 1

    sources = [Path(path) for path in args.source] if args.source else DEFAULT_EVENT_SOURCES
    source, events = load_events(sources)
    if not events:
        print("Event Feed Visual Diversity Audit")
        print("================================")
        print(f"HINWEIS: Keine Eventdaten gefunden. Quellen: {', '.join(str(path.relative_to(ROOT) if path.is_relative_to(ROOT) else path) for path in sources)}")
        return 0

    today = date.today()
    horizon = today + timedelta(days=args.days)
    sortable = []
    for item in events:
        day = effective_date(item, today)
        if not day or day < today or day > horizon:
            continue
        sortable.append((day, parse_time_minutes(item.get("time")), norm(item.get("title")), item))
    sortable.sort(key=lambda row: (row[0], row[1], row[2]))

    ready_pools = build_ready_pools(pool_payload)
    usage_by_scope: Dict[str, set[str]] = defaultdict(set)
    recent: deque[str] = deque(maxlen=args.window)
    rendered: List[Dict[str, Any]] = []

    for index, (day, _minutes, _title, item) in enumerate(sortable):
        visual_key, visual_motif, visual, candidate_count = resolve_visual(
            item=item,
            ready_pools=ready_pools,
            pool_payload=pool_payload,
            usage_by_scope=usage_by_scope,
            recent=recent,
            min_motif_diversity=args.min_motif_diversity,
        )
        rendered.append(
            {
                "index": index,
                "date": day.isoformat(),
                "title": norm(item.get("title")),
                "location": norm(item.get("location")),
                "visual_key": visual_key,
                "visual_motif": visual_motif,
                "candidate_count": candidate_count,
                "image": visual_usage_key(visual or {}),
                "src": norm((visual or {}).get("src")),
            }
        )

    duplicate_issues: List[Tuple[Dict[str, Any], Dict[str, Any]]] = []
    last_seen_by_image: Dict[str, Dict[str, Any]] = {}
    for item in rendered:
        image = norm(item.get("image"))
        if not image:
            continue
        previous = last_seen_by_image.get(image)
        if previous and item["index"] - previous["index"] <= args.window:
            duplicate_issues.append((previous, item))
        last_seen_by_image[image] = item

    low_diversity = []
    motif_counts = Counter((row["visual_key"], row["visual_motif"]) for row in rendered if row["visual_key"] and row["visual_motif"])
    candidate_counts_by_motif: Dict[Tuple[str, str], int] = {}
    for row in rendered:
        key = (row["visual_key"], row["visual_motif"])
        if key[0] and key[1]:
            candidate_counts_by_motif[key] = max(candidate_counts_by_motif.get(key, 0), int(row.get("candidate_count") or 0))
    for key, count in motif_counts.items():
        candidates = candidate_counts_by_motif.get(key, 0)
        if count >= 2 and candidates < min(args.min_motif_diversity, count):
            low_diversity.append((key[0], key[1], count, candidates))

    series_groups: Dict[Tuple[str, str], List[Dict[str, Any]]] = defaultdict(list)
    for row in rendered:
        series_title = normalized_series_title(row["title"])
        if len(series_title) >= 12:
            series_groups[(series_title, row["location"].lower())].append(row)
    series_issues = [items for items in series_groups.values() if len(items) >= 3]

    print("Event Feed Visual Diversity Audit")
    print("================================")
    print(f"Quelle: {source.relative_to(ROOT) if source.is_relative_to(ROOT) else source}")
    print(f"Ausgewertete Feed-Events: {len(rendered)} von {len(events)} Eventzeilen; Horizont: {args.days} Tage")
    print(f"Resolver-Regel: exaktes Motiv ab {args.min_motif_diversity} Bildern exklusiv, darunter exakt + neutrale Pool-Fallbacks")
    print(f"Duplikat-Fenster: {args.window} sichtbare Karten")

    if duplicate_issues:
        print("\nSichtbare Bild-Wiederholungen:")
        for previous, current in duplicate_issues[:20]:
            print(
                f"- {current['image']}: #{previous['index'] + 1} {previous['date']} {previous['title']} "
                f"→ #{current['index'] + 1} {current['date']} {current['title']}"
            )
    else:
        print("\nOK: Keine gleiche Bild-ID innerhalb des sichtbaren Fensters gefunden.")

    if low_diversity:
        print("\nMotiv-Diversitaet unter Feed-Bedarf:")
        for visual_key, visual_motif, count, candidates in low_diversity[:20]:
            print(f"- {visual_key}/{visual_motif}: {count} Events, {candidates} nutzbare Kandidaten")
    else:
        print("\nOK: Keine akute Motiv-Diversitaetswarnung im betrachteten Feed-Horizont.")

    if series_issues:
        print("\nMoegliche Dachveranstaltungs-/Seriencluster:")
        for items in series_issues[:10]:
            titles = Counter(row["title"] for row in items)
            first = min(row["date"] for row in items)
            last = max(row["date"] for row in items)
            common_title = titles.most_common(1)[0][0]
            print(f"- {common_title}: {len(items)} Karten zwischen {first} und {last}; pruefen: Master-Card oder echte Programmpunkt-Titel")
    else:
        print("\nOK: Keine grossen gleichnamigen Seriencluster erkannt.")

    has_issues = bool(duplicate_issues or low_diversity or series_issues)
    if has_issues and not args.warn_only:
        print("\nFEHLER: Feed-Visual-Diversitaet braucht Review.")
        return 1

    if has_issues:
        print("\nHINWEIS: Review-Bedarf, aber warn-only aktiv; Workflow bleibt gruen.")
    else:
        print("\nOK: Feed-Visual-Diversitaet ohne akute Auffaelligkeit.")
    return 0


def parse_args(argv: Optional[Sequence[str]] = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Audit fuer sichtbare Event-Feed-Bildwiederholungen.")
    parser.add_argument("--source", action="append", type=Path, help="Eventquelle als TSV oder JSON. Mehrfach erlaubt; erste nicht-leere Quelle gewinnt.")
    parser.add_argument("--visual-pool", type=Path, default=DEFAULT_VISUAL_POOL, help="Pfad zu data/event_visual_pool.json")
    parser.add_argument("--window", type=int, default=6, help="Sichtbares Kartenfenster fuer Wiederholungspruefung")
    parser.add_argument("--days", type=int, default=180, help="Zukunftshorizont fuer Feedsimulation")
    parser.add_argument("--min-motif-diversity", type=int, default=MIN_MOTIF_DIVERSITY, help="Mindestzahl exakter Motivbilder fuer exklusives Motivrendering")
    parser.add_argument("--warn-only", action="store_true", help="Nur berichten, nicht mit Fehlercode abbrechen")
    return parser.parse_args(argv)


if __name__ == "__main__":
    raise SystemExit(audit(parse_args()))
# === END FILE: scripts/audit-event-feed-visual-diversity.py ===
