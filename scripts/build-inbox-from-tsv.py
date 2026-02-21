# === BEGIN BLOCK: BUILD INBOX FROM TSV (render-ready feed, tolerant) ===
# Datei: scripts/build-inbox-from-tsv.py
# Zweck:
# - Quelle: data/inbox.tsv (Export aus Google Sheet Tab "Inbox")
# - Output: data/inbox.json (Feed für PWA Inbox Review)
# - Fail-Fast nur bei: fehlender TSV / fehlender Header / fehlende Kernspalten
# - Tolerant bei Datenqualität: leere date/endDate/time sind erlaubt; ungültige Datumswerte -> Warnung
# Umfang:
# - Liest TSV (Tab-separated) mit Headerzeile
# - Schreibt JSON (UTF-8, ensure_ascii=False, pretty)
# - Keine Netzwerkzugriffe
# === END BLOCK: BUILD INBOX FROM TSV (render-ready feed, tolerant) ===

from __future__ import annotations

import csv
import json
import re
import sys
import unicodedata
from dataclasses import dataclass
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple


ROOT = Path(__file__).resolve().parents[1]
TSV_PATH = ROOT / "data" / "inbox.tsv"
OUT_JSON_PATH = ROOT / "data" / "inbox.json"

RE_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")

# Inbox-Tab ist stabil über discovery-to-inbox.py definiert.
# Für das Review-Feed brauchen wir nur die Kernfelder – Rest wird optional übernommen.
REQUIRED_FIELDS = ["status", "title", "source_url", "created_at"]


@dataclass
# === BEGIN BLOCK: INBOX ITEM DATACLASS (incl. row_number for writeback) ===
# Zweck:
# - Ergänzt row_number (echte Sheet-Zeile), damit Writeback exakt die Zeile updaten kann.
# Umfang:
# - Ersetzt ausschließlich die InboxItem-Dataclass.
@dataclass
class InboxItem:
    row_number: int  # echte Sheet-Zeile (Header=1, erste Datenzeile=2, ...)
    status: str
    id_suggestion: str
    title: str
    date: str
    endDate: str
    time: str
    city: str
    location: str
    kategorie_suggestion: str
    url: str
    description: str
    source_name: str
    source_url: str
    match_score: str
    matched_event_id: str
    notes: str
    created_at: str
# === END BLOCK: INBOX ITEM DATACLASS (incl. row_number for writeback) ===


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
    s2 = (s or "").replace("\u00A0", " ")
    return unicodedata.normalize("NFC", s2).strip()


def validate_date_lenient(iso: str, rowno: int, field: str) -> None:
    if not iso:
        return
    if not RE_DATE.match(iso):
        warn(f"Zeile {rowno}: {field} hat unerwartetes Format (erwartet YYYY-MM-DD): {iso!r}")
        return
    try:
        datetime.strptime(iso, "%Y-%m-%d")
    except Exception:
        warn(f"Zeile {rowno}: {field} ist kein gültiges Datum: {iso!r}")


def parse_created_at(ts: str) -> Optional[datetime]:
    # discovery-to-inbox.py schreibt: YYYY-MM-DD HH:MM:SS
    if not ts:
        return None
    try:
        return datetime.strptime(ts, "%Y-%m-%d %H:%M:%S")
    except Exception:
        return None


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

    items: List[InboxItem] = []
    warnings_missing_open_url = 0

    for idx, raw in enumerate(rows, start=2):  # Header ist Zeile 1
        data = {k: normalize_text(v) for k, v in raw.items()}

        # Steuerzeichen-Check (alle Felder)
        for k, v in data.items():
            if has_bad_control_chars(v):
                fail(f"Zeile {idx}: Feld {k!r} enthält unerlaubte Steuerzeichen.")

        # Kernfelder müssen existieren (dürfen aber im Einzelfall leer sein -> Warnung)
        for f in REQUIRED_FIELDS:
            if data.get(f, "") == "":
                warn(f"Zeile {idx}: Kernfeld leer: {f}")

        # Leniente Date-Checks (für Review darf missing/invalid vorkommen)
        validate_date_lenient(data.get("date", ""), idx, "date")
        validate_date_lenient(data.get("endDate", ""), idx, "endDate")

        if not (data.get("url", "") or data.get("source_url", "")):
            warnings_missing_open_url += 1

        # === BEGIN BLOCK: APPEND INBOX ITEM (incl. row_number for writeback) ===
        # Zweck:
        # - Persistiert die echte Sheet-Zeile (idx) in row_number.
        # Umfang:
        # - Ersetzt ausschließlich den items.append(...) Block.
        items.append(
            InboxItem(
                row_number=idx,
                status=data.get("status", ""),
                id_suggestion=data.get("id_suggestion", ""),
                title=data.get("title", ""),
                date=data.get("date", ""),
                endDate=data.get("endDate", ""),
                time=data.get("time", ""),
                city=data.get("city", ""),
                location=data.get("location", ""),
                kategorie_suggestion=data.get("kategorie_suggestion", ""),
                url=data.get("url", ""),
                description=data.get("description", ""),
                source_name=data.get("source_name", ""),
                source_url=data.get("source_url", ""),
                match_score=data.get("match_score", ""),
                matched_event_id=data.get("matched_event_id", ""),
                notes=data.get("notes", ""),
                created_at=data.get("created_at", ""),
            )
        )
        # === END BLOCK: APPEND INBOX ITEM (incl. row_number for writeback) ===

    # Sortierung: Neueste zuerst (created_at desc), Fallback stabil (title)
    def sort_key(it: InboxItem) -> Tuple[int, str, str]:
        dt = parse_created_at(it.created_at)
        ts = int(dt.timestamp()) if dt else -1
        return (-ts, it.created_at or "", it.title.lower())

    items.sort(key=sort_key)

    out: List[Dict[str, str]] = []
    for it in items:
        # === BEGIN BLOCK: OUTPUT OBJECT BASE FIELDS (incl. row_number for writeback) ===
        # Zweck:
        # - row_number ist Pflichtfeld für Writeback.
        # Umfang:
        # - Ersetzt ausschließlich das Basis-obj-Dict.
        obj: Dict[str, str] = {
            "row_number": it.row_number,
            "status": it.status,
            "title": it.title,
            "source_url": it.source_url,
            "created_at": it.created_at,
        }
        # === END BLOCK: OUTPUT OBJECT BASE FIELDS (incl. row_number for writeback) ===

        # Optionalfelder (nur wenn befüllt)
        if it.id_suggestion:
            obj["id_suggestion"] = it.id_suggestion
        if it.date:
            obj["date"] = it.date
        if it.endDate:
            obj["endDate"] = it.endDate
        if it.time:
            obj["time"] = it.time
        if it.city:
            obj["city"] = it.city
        if it.location:
            obj["location"] = it.location
        if it.kategorie_suggestion:
            obj["kategorie_suggestion"] = it.kategorie_suggestion
        if it.url:
            obj["url"] = it.url
        if it.description:
            obj["description"] = it.description
        if it.source_name:
            obj["source_name"] = it.source_name
        if it.match_score:
            obj["match_score"] = it.match_score
        if it.matched_event_id:
            obj["matched_event_id"] = it.matched_event_id
        if it.notes:
            obj["notes"] = it.notes

        out.append(obj)

    OUT_JSON_PATH.parent.mkdir(parents=True, exist_ok=True)
    OUT_JSON_PATH.write_text(json.dumps(out, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    if warnings_missing_open_url:
        warn(
            f"Inbox Feed Hinweis: {warnings_missing_open_url} Zeilen haben weder url noch source_url. "
            "PWA-Review kann diese nicht direkt öffnen."
        )

    print(f"✅ OK: {len(out)} Inbox-Items geschrieben: {OUT_JSON_PATH}")


if __name__ == "__main__":
    main()
