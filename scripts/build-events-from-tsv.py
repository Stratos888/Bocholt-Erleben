# === BEGIN BLOCK: BUILD EVENTS FROM TSV (fail-fast + validation) ===
# Datei: scripts/build-events-from-tsv.py
# Zweck:
# - Quelle: data/events.tsv (im Deploy aus dem Google Sheet exportiert)
# - Output: data/events.json (generiertes Deploy-/Runtime-Artefakt, nicht im Repo pflegen)
# - Fail-Fast: bricht mit Exit 1 ab, wenn Validierung fehlschlägt
# - Validierung: Pflichtfelder, eindeutige IDs, Duplikate, Datumsformat, Kategorien, Steuerzeichen
# - Optional: endDate (YYYY-MM-DD) wird übernommen, wenn vorhanden
# Umfang:
# - Liest TSV (Tab-separated) mit Headerzeile
# - Schreibt JSON (UTF-8, ensure_ascii=False, pretty)
# - Keine Netzwerkzugriffe
# === END BLOCK: BUILD EVENTS FROM TSV (fail-fast + validation) ===

from __future__ import annotations

import csv
import json
import re
import sys
import unicodedata
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Tuple, Optional

ROOT = Path(__file__).resolve().parents[1]
TSV_PATH = ROOT / "data" / "events.tsv"
OUT_JSON_PATH = ROOT / "data" / "events.json"

RE_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
RE_ID = re.compile(r"^[a-z0-9][a-z0-9-]{2,120}$")  # slug-like
RE_TIME = re.compile(r"^\s*(\d{1,2})[:.](\d{2})(?:\s*[-–]\s*(\d{1,2})[:.](\d{2}))?\s*$")

CANONICAL_CATEGORIES = [
    "Märkte & Feste",
    "Kultur & Kunst",
    "Musik & Bühne",
    "Kinder & Familie",
    "Sport & Bewegung",
    "Natur & Draußen",
    "Innenstadt & Leben",
    "Sonstiges",
]

# Synonyme/Altwerte -> Canonical (damit ihr schnell migrieren könnt)
CATEGORY_MAP = {
    # Innenstadt / Leben
    "party": "Innenstadt & Leben",
    "kneipe": "Innenstadt & Leben",
    "quiz": "Innenstadt & Leben",
    "innenstadt": "Innenstadt & Leben",
    "leben": "Innenstadt & Leben",
    "stadtleben": "Innenstadt & Leben",

    # Musik / Bühne
    "musik": "Musik & Bühne",
    "konzert": "Musik & Bühne",
    "live": "Musik & Bühne",
    "bühne": "Musik & Bühne",
    "buehne": "Musik & Bühne",
    "show": "Musik & Bühne",

    # Kultur / Kunst
    "kultur": "Kultur & Kunst",
    "kunst": "Kultur & Kunst",
    "ausstellung": "Kultur & Kunst",
    "lesung": "Kultur & Kunst",
    "kabarett": "Kultur & Kunst",
    "comedy": "Kultur & Kunst",

    # Kinder / Familie
    "kinder": "Kinder & Familie",
    "familie": "Kinder & Familie",
    "familien": "Kinder & Familie",

    # Märkte / Feste
    "markt": "Märkte & Feste",
    "märkte": "Märkte & Feste",
    "maerkte": "Märkte & Feste",
    "fest": "Märkte & Feste",
    "feste": "Märkte & Feste",
    "festival": "Märkte & Feste",
    "messe": "Märkte & Feste",

    # Sport / Bewegung
    "sport": "Sport & Bewegung",
    "bewegung": "Sport & Bewegung",
    "lauf": "Sport & Bewegung",

    # Natur / Draußen
    "natur": "Natur & Draußen",
    "draußen": "Natur & Draußen",
    "draussen": "Natur & Draußen",
}

REQUIRED_FIELDS = ["id", "title", "date", "city", "location", "kategorie"]


@dataclass
class EventRow:
    id: str
    title: str
    date: str
    endDate: str
    time: str
    city: str
    location: str
    kategorie: str
    url: str
    description: str
    situation_tags: List[str]
    weather_profile: List[str]
    audience_tags: List[str]
    planning_level: str
    cost_level: str
    recommendation_weight: str


def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def warn(msg: str) -> None:
    print(f"⚠️  {msg}", file=sys.stderr)


def has_bad_control_chars(s: str) -> bool:
    # erlaubt: \t \n \r
    for ch in s:
        o = ord(ch)
        if o < 32 and ch not in ("\t", "\n", "\r"):
            return True
    return False


def normalize_text(s: str) -> str:
    # Stabil gegen „kaputte Sonderzeichen“ aus Copy/Paste: NFC normalisieren
    s2 = s.replace("\u00A0", " ")  # NBSP -> normal space
    return unicodedata.normalize("NFC", s2).strip()


def normalize_category(raw: str) -> str:
    r = normalize_text(raw)
    if not r:
        return ""
    low = r.lower()

    # direkte Canonical-Akzeptanz
    if r in CANONICAL_CATEGORIES:
        return r

    # Mapping über Schlüsselwörter
    for key, canon in CATEGORY_MAP.items():
        if key in low:
            return canon

    return r  # wird später gegen Canonical geprüft (fail-fast)


# === BEGIN BLOCK: EVENT_RECOMMENDATION_OPTIONAL_FIELDS_V1 | Zweck: optionale Recommendation-Spalten aus dem Events-Sheet additiv in events.json übernehmen; Umfang: Normalisierung ohne neue Pflichtfelder ===
RECOMMENDATION_LIST_FIELDS = {
    "situation_tags",
    "weather_profile",
    "audience_tags",
}

RECOMMENDATION_STRING_FIELDS = {
    "planning_level",
    "cost_level",
    "recommendation_weight",
}


def normalize_list_field(raw: str) -> List[str]:
    value = normalize_text(raw)
    if not value:
        return []

    parts = re.split(r"[,;|]", value)
    out: List[str] = []

    for part in parts:
        item = normalize_text(part)
        if item and item not in out:
            out.append(item)

    return out


def optional_text(data: Dict[str, str], key: str) -> str:
    return normalize_text(data.get(key, ""))


def optional_list(data: Dict[str, str], key: str) -> List[str]:
    return normalize_list_field(data.get(key, ""))
# === END BLOCK: EVENT_RECOMMENDATION_OPTIONAL_FIELDS_V1 ===


def validate_date(iso: str, rowno: int) -> None:
    if not RE_DATE.match(iso):
        fail(f"Zeile {rowno}: date muss YYYY-MM-DD sein, erhalten: {iso!r}")
    try:
        datetime.strptime(iso, "%Y-%m-%d")
    except Exception:
        fail(f"Zeile {rowno}: date ist kein gültiges Datum: {iso!r}")


# === BEGIN BLOCK: BUILD_EVENTS_TIME_VALIDATION_LEGACY_COMPAT_V2 | Zweck: finale Events-TSV weiter mit bestehenden Zeitspannen kompatibel halten | Umfang: validiert Startzeit oder Zeitspanne im finalen Events-Build; Weekly-/Manual-Import bleibt davon unberührt ===
def validate_time(t: str, rowno: int) -> None:
    if not t:
        return
    if not RE_TIME.match(t):
        fail(f"Zeile {rowno}: time hat ein unerwartetes Format. Erlaubt sind z.B. '19:30' oder '19:30–22:00': {t!r}")
# === END BLOCK: BUILD_EVENTS_TIME_VALIDATION_LEGACY_COMPAT_V2 ===

def parse_time_minutes(t: str) -> Optional[int]:
    if not t:
        return None
    m = RE_TIME.match(t)
    if not m:
        return None
    hh = int(m.group(1))
    mm = int(m.group(2))
    return hh * 60 + mm


def read_tsv(path: Path) -> List[Dict[str, str]]:
    if not path.exists():
        fail(f"TSV-Datei fehlt: {path}")
    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        if not reader.fieldnames:
            fail("TSV hat keine Headerzeile.")
        rows: List[Dict[str, str]] = []
        for row in reader:
            rows.append({k: (v if v is not None else "") for k, v in row.items()})
        return rows


def main() -> None:
    rows = read_tsv(TSV_PATH)

    # Header-Check
    header = set(rows[0].keys()) if rows else set()
    missing = [f for f in REQUIRED_FIELDS if f not in header]
    if missing:
        fail(f"TSV Header unvollständig. Fehlende Spalten: {missing}. Erwartet mindestens: {REQUIRED_FIELDS}")

       # === BEGIN BLOCK: RANGE_AWARE_PUBLISHING_AND_OCCURRENCE_DEDUPE_V2 | Zweck: abgelaufene Events inkl. Mehrtagesevents entfernen und gleiche URL für verschiedene Instanzen erlauben | Umfang: ersetzt Dedupe-/Publish-Fenster-Validierung ===
    events: List[EventRow] = []
    seen_ids = set()
    seen_fingerprints = set()
    seen_url_occurrences = set()
    skipped_expired_events = 0

    today_date = datetime.now().date()
    # === END BLOCK: RANGE_AWARE_PUBLISHING_AND_OCCURRENCE_DEDUPE_V2 ===

    for idx, raw in enumerate(rows, start=2):  # Header ist Zeile 1

        # Normalisieren
        data = {k: normalize_text(v) for k, v in raw.items()}

        # Steuerzeichen-Check (alle Felder)
        for k, v in data.items():
            if has_bad_control_chars(v):
                fail(f"Zeile {idx}: Feld {k!r} enthält unerlaubte Steuerzeichen.")

        # Pflichtfelder
        for f in REQUIRED_FIELDS:
            if not data.get(f, ""):
                fail(f"Zeile {idx}: Pflichtfeld fehlt/leer: {f}")

        ev_id = data["id"]
        if not RE_ID.match(ev_id):
            fail(
                f"Zeile {idx}: id muss slug-like sein (kleinbuchstaben/zahlen/-, 3..121 chars), erhalten: {ev_id!r}"
            )
        if ev_id in seen_ids:
            fail(f"Zeile {idx}: duplicate id: {ev_id!r}")
        seen_ids.add(ev_id)

                # === BEGIN BLOCK: RANGE_AWARE_PUBLISHING_AND_URL_OCCURRENCE_CHECK_V2 | Zweck: date/endDate prüfen, abgelaufene Events entfernen und URL-Dedupe instanzbasiert statt url+date ausführen | Umfang: ersetzt alte url+date-Dedupe-Logik ===
        validate_date(data["date"], idx)
        start_d = datetime.strptime(data["date"], "%Y-%m-%d").date()

        end_d = start_d
        if data.get("endDate", ""):
            validate_date(data["endDate"], idx)
            end_d = datetime.strptime(data["endDate"], "%Y-%m-%d").date()
            if end_d < start_d:
                fail(f"Zeile {idx}: endDate liegt vor date: date={data['date']!r}, endDate={data['endDate']!r}")

        validate_time(data.get("time", ""), idx)

        # Events bleiben bis einschließlich endDate sichtbar.
        # Abgelaufene Ein-Tages- und Mehrtagesevents werden nicht veröffentlicht.
        if end_d < today_date:
            skipped_expired_events += 1
            continue

        # Gleiche URL ist erlaubt, wenn es unterschiedliche Instanzen sind.
        # Verboten bleibt nur dieselbe URL für dieselbe erkennbare Instanz.
        url_raw = (data.get("url", "") or "").strip()
        if url_raw:
            norm_url = url_raw.lower().rstrip("/")
            url_occurrence_key = (
                norm_url,
                data["title"].lower(),
                data["date"],
                (data.get("time", "") or "").lower(),
                data["city"].lower(),
                data["location"].lower(),
            )
            if url_occurrence_key in seen_url_occurrences:
                fail(
                    f"Zeile {idx}: Duplikat erkannt (url+title+date+time+city+location). "
                    f"Bitte eine der Zeilen entfernen: url={url_raw!r}, date={data['date']!r}, time={data.get('time','')!r}"
                )
            seen_url_occurrences.add(url_occurrence_key)
        # === END BLOCK: RANGE_AWARE_PUBLISHING_AND_URL_OCCURRENCE_CHECK_V2 ===

        cat = normalize_category(data["kategorie"])

        if cat not in CANONICAL_CATEGORIES:
            fail(
                f"Zeile {idx}: kategorie ist nicht canonical: {data['kategorie']!r} -> {cat!r}. "
                f"Erlaubt: {CANONICAL_CATEGORIES}"
            )

        # Duplikat-Fingerprint (praktisch gegen Copy/Paste-Doppler)
        fp = (
            data["title"].lower(),
            data["date"],
            (data.get("time", "") or "").lower(),
            data["city"].lower(),
            data["location"].lower(),
        )
        if fp in seen_fingerprints:
            fail(
                f"Zeile {idx}: Duplikat erkannt (title/date/time/city/location). "
                f"Bitte prüfen: {data['title']!r} @ {data['date']} {data.get('time','')!r}"
            )
        seen_fingerprints.add(fp)

        events.append(
            EventRow(
                id=ev_id,
                title=data["title"],
                date=data["date"],
                endDate=data.get("endDate", ""),
                time=data.get("time", ""),
                city=data["city"],
                location=data["location"],
                kategorie=cat,
                url=data.get("url", ""),
                description=data.get("description", ""),
                situation_tags=optional_list(data, "situation_tags"),
                weather_profile=optional_list(data, "weather_profile"),
                audience_tags=optional_list(data, "audience_tags"),
                planning_level=optional_text(data, "planning_level"),
                cost_level=optional_text(data, "cost_level"),
                recommendation_weight=optional_text(data, "recommendation_weight"),
            )
        )

    # Sortierung (Datum + optionale Uhrzeit)
    def sort_key(e: EventRow) -> Tuple[str, int]:
        tm = parse_time_minutes(e.time)
        return (e.date, tm if tm is not None else 10**9)

    events.sort(key=sort_key)

    out = []
    for e in events:
        item = {
            "id": e.id,
            "title": e.title,
            "date": e.date,
            "time": e.time,
            "city": e.city,
            "location": e.location,
            "kategorie": e.kategorie,
        }
        if e.endDate:
            item["endDate"] = e.endDate
        if e.url:
            item["url"] = e.url
        if e.description:
            item["description"] = e.description

        recommendation = {}
        if e.situation_tags:
            recommendation["situation_tags"] = e.situation_tags
        if e.weather_profile:
            recommendation["weather_profile"] = e.weather_profile
        if e.audience_tags:
            recommendation["audience_tags"] = e.audience_tags
        if e.planning_level:
            recommendation["planning_level"] = e.planning_level
        if e.cost_level:
            recommendation["cost_level"] = e.cost_level
        if e.recommendation_weight:
            recommendation["recommendation_weight"] = e.recommendation_weight

        if recommendation:
            item["recommendation"] = recommendation

        out.append(item)

    OUT_JSON_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSON_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        # === BEGIN BLOCK: REPORT_SKIPPED_EXPIRED_EVENTS_V2 | Zweck: CI-Log für automatisch entfernte abgelaufene Events inklusive Mehrtagesevents | Umfang: ersetzt nur die Log-Ausgabe ===
    if skipped_expired_events:
        print(f"ℹ️ Hinweis: {skipped_expired_events} abgelaufene Events wurden nicht veröffentlicht.")
    # === END BLOCK: REPORT_SKIPPED_EXPIRED_EVENTS_V2 ===
    print(f"✅ OK: {len(out)} Events geschrieben: {OUT_JSON_PATH}")


if __name__ == "__main__":
    main()
