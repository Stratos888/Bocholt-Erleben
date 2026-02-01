# === BEGIN BLOCK: BUILD EVENTS FROM TSV (fail-fast + validation) ===
# Datei: scripts/build-events-from-tsv.py
# Zweck:
# - Quelle: data/events.tsv (copy/paste-freundlich)
# - Output: data/events.json (Single Source of Truth für die App)
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
    "party": "Innenstadt & Leben",
    "kneipe": "Innenstadt & Leben",
    "quiz": "Innenstadt & Leben",
    "musik": "Musik & Bühne",
    "kultur": "Kultur & Kunst",
    "kinder": "Kinder & Familie",
    "familie": "Kinder & Familie",
    "markt": "Märkte & Feste",
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


def validate_date(iso: str, rowno: int) -> None:
    if not RE_DATE.match(iso):
        fail(f"Zeile {rowno}: date muss YYYY-MM-DD sein, erhalten: {iso!r}")
    try:
        datetime.strptime(iso, "%Y-%m-%d")
    except Exception:
        fail(f"Zeile {rowno}: date ist kein gültiges Datum: {iso!r}")


def validate_time(t: str, rowno: int) -> None:
    if not t:
        return
    if not RE_TIME.match(t):
        fail(f"Zeile {rowno}: time hat ein unerwartetes Format (erlaubt z.B. '19:30' oder '19:30–22:00'): {t!r}")


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

       # === BEGIN BLOCK: DEDUPE + AUTO-SKIP PAST SINGLE-DAY EVENTS ===
    # Zweck:
    # - Zusätzliche, harte Duplikat-Regel: gleiche (url + date) darf nur einmal vorkommen.
    # - Alte Ein-Tages-Events (date < heute UND endDate leer) werden NICHT veröffentlicht.
    # Umfang:
    # - Initialisiert zusätzliche Seen-Sets + Zähler für übersprungene Einträge.
    # ===
    events: List[EventRow] = []
    seen_ids = set()
    seen_fingerprints = set()
    seen_url_date = set()  # (normalized_url, date)
    skipped_past_single_day = 0

    today_date = datetime.now().date()
    # === END BLOCK: DEDUPE + AUTO-SKIP PAST SINGLE-DAY EVENTS ===

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

                # === BEGIN BLOCK: RANGE-AWARE PUBLISHING + URL/DATE DEDUPE ===
        # Zweck:
        # - date/endDate validieren
        # - alte Ein-Tages-Events automatisch aus events.json herausfiltern
        # - harte Duplikate anhand (url + date) erkennen
        # Umfang:
        # - Erweitert nur die Validierungslogik, ohne Output-Schema zu ändern.
        # ===
        validate_date(data["date"], idx)
        if data.get("endDate", ""):
            validate_date(data["endDate"], idx)
        validate_time(data.get("time", ""), idx)

        # Alte Ein-Tages-Events (ohne endDate) werden nicht veröffentlicht.
        # (Events mit endDate bleiben bis inkl. endDate sichtbar – das macht das Frontend später korrekt.)
        if not data.get("endDate", ""):
            start_d = datetime.strptime(data["date"], "%Y-%m-%d").date()
            if start_d < today_date:
                skipped_past_single_day += 1
                continue

        # Harte Duplikat-Regel: gleiche URL am gleichen Datum darf nur einmal vorkommen.
        url_raw = (data.get("url", "") or "").strip()
        if url_raw:
            norm_url = url_raw.lower().rstrip("/")
            key = (norm_url, data["date"])
            if key in seen_url_date:
                fail(
                    f"Zeile {idx}: Duplikat erkannt (url+date). "
                    f"Bitte eine der Zeilen entfernen: url={url_raw!r}, date={data['date']!r}"
                )
            seen_url_date.add(key)
        # === END BLOCK: RANGE-AWARE PUBLISHING + URL/DATE DEDUPE ===

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
        out.append(item)

    OUT_JSON_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSON_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
        # === BEGIN BLOCK: REPORT SKIPPED PAST SINGLE-DAY EVENTS ===
    # Zweck: Transparenz im CI-Log, wenn alte Ein-Tages-Events automatisch nicht veröffentlicht wurden.
    # Umfang: Log-Ausgabe, keine Build-Änderung.
    # ===
    if skipped_past_single_day:
        print(f"ℹ️ Hinweis: {skipped_past_single_day} abgelaufene Ein-Tages-Events (ohne endDate) wurden nicht veröffentlicht.")
    # === END BLOCK: REPORT SKIPPED PAST SINGLE-DAY EVENTS ===
    print(f"✅ OK: {len(out)} Events geschrieben: {OUT_JSON_PATH}")


if __name__ == "__main__":
    main()
