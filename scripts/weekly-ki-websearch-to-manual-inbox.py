# === BEGIN BLOCK: WEEKLY KI WEBSEARCH TO MANUAL INBOX (repo + sheets snapshots + responses web_search) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zieht die aktuellen Referenzdaten direkt aus dem Live-Stand (Events/Inbox/Inbox_Archive)
# - Nutzt das Regelwerk + OpenAI Responses API mit Web Search für eine echte KI-Eventsuche
# - Schreibt nur neue Delta-Kandidaten nach data/inbox_manual.json
# - Keine Parser-/Venue-Logik als Suchkern
# Umfang:
# - Dünner Automations-/Datei-Layer um die KI-Suche
# - Harte Guards für offene Inbox / nicht geleerte Manual-Inbox
# - Lokale Nachvalidierung / Nach-Dedupe für robuste JSON-Ausgabe
# === END BLOCK: WEEKLY KI WEBSEARCH TO MANUAL INBOX (repo + sheets snapshots + responses web_search) ===

from __future__ import annotations

import csv
import json
import os
import re
import sys
from dataclasses import dataclass
from datetime import date, datetime, timedelta
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

from google.oauth2 import service_account
from googleapiclient.discovery import build
from openai import OpenAI


# === BEGIN BLOCK: CONFIG (paths, tabs, limits, runtime knobs) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zentraler Owner für Pfade, Sheet-Tabs, Guards und Modell-Konfiguration
# Umfang:
# - Nur Konfiguration / keine Logik
# === END BLOCK: CONFIG (paths, tabs, limits, runtime knobs) ===
ROOT = Path(__file__).resolve().parents[1]
DATA_DIR = ROOT / "data"
TMP_DIR = ROOT / ".tmp" / "weekly-ki-eventsuche"

REGELWERK_PATH = Path(os.environ.get("EVENT_RULEBOOK_PATH", str(ROOT / "bocholt-erleben_eventsuche_regelwerk_v3.md")))
MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(DATA_DIR / "inbox_manual.json")))

TMP_EVENTS_TSV_PATH = Path(os.environ.get("TMP_EVENTS_TSV_PATH", str(TMP_DIR / "events.tsv")))
TMP_INBOX_TSV_PATH = Path(os.environ.get("TMP_INBOX_TSV_PATH", str(TMP_DIR / "inbox.tsv")))
TMP_ARCHIVE_TSV_PATH = Path(os.environ.get("TMP_ARCHIVE_TSV_PATH", str(TMP_DIR / "inbox_archive.tsv")))

TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
TAB_ARCHIVE = os.environ.get("TAB_ARCHIVE", "Inbox_Archive")

OPENAI_MODEL = os.environ.get("OPENAI_MODEL", "gpt-5.4").strip()
MAX_NEW_CANDIDATES = int(os.environ.get("MAX_NEW_CANDIDATES", "40"))
SEARCH_WINDOW_DAYS = int(os.environ.get("SEARCH_WINDOW_DAYS", "180"))
ALLOW_RADIUS_KM = int(os.environ.get("ALLOW_RADIUS_KM", "20"))

FAIL_ON_PENDING_INBOX = os.environ.get("FAIL_ON_PENDING_INBOX", "true").strip().lower() in {"1", "true", "yes"}
FAIL_ON_PENDING_MANUAL = os.environ.get("FAIL_ON_PENDING_MANUAL", "true").strip().lower() in {"1", "true", "yes"}

FINAL_INBOX_STATUSES = {"übernommen", "verworfen"}
MANUAL_NOTES_VALUE = "manual chat search v3"
ALLOWED_CATEGORIES = [
    "Märkte & Feste",
    "Kultur & Kunst",
    "Musik & Bühne",
    "Kinder & Familie",
    "Sport & Bewegung",
    "Natur & Draußen",
    "Innenstadt & Leben",
    "Sonstiges",
]
# === END BLOCK: CONFIG (paths, tabs, limits, runtime knobs) ===


# === BEGIN BLOCK: BASIC HELPERS (logging, normalization, dates, urls) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Kleine Hilfsfunktionen für Logging, Dateihandling, Datum und Dedupe-Key-Normalisierung
# Umfang:
# - Nur lokale Helper
# === END BLOCK: BASIC HELPERS (logging, normalization, dates, urls) ===
def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def norm(value: Any) -> str:
    return str(value or "").strip()


def norm_key(value: Any) -> str:
    return re.sub(r"\s+", " ", norm(value)).lower()


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def today_local() -> date:
    return datetime.now().date()


def parse_iso_date(raw: str) -> Optional[date]:
    raw = norm(raw)
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except ValueError:
        return None


def canonical_url(raw: str) -> str:
    raw = norm(raw)
    if not raw:
        return ""
    try:
        parsed = urlparse(raw)
    except Exception:
        return raw

    query_pairs = []
    for key, value in parse_qsl(parsed.query, keep_blank_values=True):
        k = key.lower().strip()
        if k.startswith("utm_") or k in {"fbclid", "gclid", "mc_cid", "mc_eid", "ref", "ref_src"}:
            continue
        query_pairs.append((key, value))

    cleaned = parsed._replace(
        scheme=(parsed.scheme or "https").lower(),
        netloc=parsed.netloc.lower(),
        query=urlencode(query_pairs, doseq=True),
        fragment="",
    )
    return urlunparse(cleaned).rstrip("/")


def instance_fp(title: str, date_value: str, time_value: str, location: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
        norm(time_value),
        norm_key(location),
    ])


def date_in_window(date_value: str, *, start: date, end: date) -> bool:
    parsed = parse_iso_date(date_value)
    if parsed is None:
        return False
    return start <= parsed <= end
# === END BLOCK: BASIC HELPERS (logging, normalization, dates, urls) ===


# === BEGIN BLOCK: SHEETS SNAPSHOT EXPORT (events/inbox/archive -> temp tsv) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zieht vor jedem Lauf den aktuellen Datenstand direkt aus Google Sheets
# - Stellt so sicher, dass die KI gegen den neuesten Bestand deduped
# Umfang:
# - Exportiert nur lokale Laufzeit-Snapshots, kein Repo-Write an TSV-Dateien
# === END BLOCK: SHEETS SNAPSHOT EXPORT (events/inbox/archive -> temp tsv) ===
def build_sheets_service() -> Tuple[Any, str]:
    sheet_id = norm(os.environ.get("SHEET_ID", ""))
    sa_json = norm(os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON", ""))
    if not sheet_id:
        fail("ENV SHEET_ID fehlt.")
    if not sa_json:
        fail("ENV GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")

    try:
        sa_info = json.loads(sa_json)
    except Exception:
        fail("GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON.")

    scopes = ["https://www.googleapis.com/auth/spreadsheets.readonly"]
    creds = service_account.Credentials.from_service_account_info(sa_info, scopes=scopes)
    service = build("sheets", "v4", credentials=creds, cache_discovery=False)
    return service, sheet_id


def read_sheet_values(service: Any, sheet_id: str, tab_name: str) -> List[List[str]]:
    rng = f"{tab_name}!A:ZZ"
    res = service.spreadsheets().values().get(
        spreadsheetId=sheet_id,
        range=rng,
        majorDimension="ROWS",
    ).execute(num_retries=4)
    return res.get("values", []) or []


def write_tsv_snapshot(out_path: Path, values: List[List[str]], required_columns: Iterable[str], *, allow_empty_rows: bool) -> int:
    if not values:
        fail(f"Sheet-Tab leer oder nicht lesbar: {out_path.name}")

    header = [norm(h) for h in (values[0] or [])]
    missing = [col for col in required_columns if col not in header]
    if missing:
        fail(f"Header unvollständig in {out_path.name}. Fehlende Spalten: {missing}. Header={header}")

    rows: List[List[str]] = []
    for raw_row in values[1:]:
        row = [(norm(raw_row[i]) if i < len(raw_row) else "") for i in range(len(header))]
        if allow_empty_rows or any(v for v in row):
            rows.append(row)

    ensure_parent(out_path)
    with out_path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f, delimiter="\t", lineterminator="\n")
        writer.writerow(header)
        if rows:
            writer.writerows(rows)

    return len(rows)


def export_current_snapshots() -> None:
    service, sheet_id = build_sheets_service()

    events_rows = write_tsv_snapshot(
        TMP_EVENTS_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_EVENTS),
        required_columns=["id", "title", "date", "city", "location", "kategorie"],
        allow_empty_rows=False,
    )
    info(f"Events-Snapshot exportiert: {events_rows} Zeilen -> {TMP_EVENTS_TSV_PATH}")

    inbox_rows = write_tsv_snapshot(
        TMP_INBOX_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_INBOX),
        required_columns=["status", "title", "source_url", "created_at"],
        allow_empty_rows=False,
    )
    info(f"Inbox-Snapshot exportiert: {inbox_rows} Zeilen -> {TMP_INBOX_TSV_PATH}")

    archive_rows = write_tsv_snapshot(
        TMP_ARCHIVE_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_ARCHIVE),
        required_columns=["status", "title", "source_url", "created_at"],
        allow_empty_rows=False,
    )
    info(f"Inbox_Archive-Snapshot exportiert: {archive_rows} Zeilen -> {TMP_ARCHIVE_TSV_PATH}")
# === END BLOCK: SHEETS SNAPSHOT EXPORT (events/inbox/archive -> temp tsv) ===


# === BEGIN BLOCK: LOCAL DATA LOADERS (events/inbox/archive/manual -> normalized records) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Lädt alle Referenzbestände in ein gemeinsames Record-Format
# - Dient sowohl Guards als auch Prompt-Bundle und lokalem Nach-Dedupe
# Umfang:
# - Nur Lesen / Normalisieren
# === END BLOCK: LOCAL DATA LOADERS (events/inbox/archive/manual -> normalized records) ===
@dataclass
class RefRecord:
    source: str
    title: str
    date: str
    time: str
    city: str
    location: str
    url: str
    source_url: str
    status: str = ""


def read_tsv_records(path: Path, source: str, mapping: Dict[str, str]) -> List[RefRecord]:
    if not path.exists():
        return []

    records: List[RefRecord] = []
    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        if not reader.fieldnames:
            return records

        for row in reader:
            title = norm(row.get(mapping.get("title", "title"), ""))
            date_value = norm(row.get(mapping.get("date", "date"), ""))
            time_value = norm(row.get(mapping.get("time", "time"), ""))
            city = norm(row.get(mapping.get("city", "city"), ""))
            location = norm(row.get(mapping.get("location", "location"), ""))
            url = canonical_url(row.get(mapping.get("url", "url"), ""))
            source_url = canonical_url(row.get(mapping.get("source_url", "source_url"), ""))
            status = norm(row.get(mapping.get("status", "status"), ""))
            if not any([title, date_value, location, source_url, url]):
                continue
            records.append(
                RefRecord(
                    source=source,
                    title=title,
                    date=date_value,
                    time=time_value,
                    city=city,
                    location=location,
                    url=url,
                    source_url=source_url,
                    status=status,
                )
            )
    return records


def read_events_records() -> List[RefRecord]:
    records = read_tsv_records(
        TMP_EVENTS_TSV_PATH,
        source="events",
        mapping={
            "title": "title",
            "date": "date",
            "time": "time",
            "city": "city",
            "location": "location",
            "url": "url",
            "source_url": "url",
        },
    )
    if records:
        return records

    events_json_path = DATA_DIR / "events.json"
    if not events_json_path.exists():
        return []

    try:
        raw = json.loads(events_json_path.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/events.json ist ungültig: {exc}")

    items = raw if isinstance(raw, list) else raw.get("events", []) if isinstance(raw, dict) else []
    records: List[RefRecord] = []
    for item in items:
        if not isinstance(item, dict):
            continue
        records.append(
            RefRecord(
                source="events_json",
                title=norm(item.get("title", "")),
                date=norm(item.get("date", "")),
                time=norm(item.get("time", "")),
                city=norm(item.get("city", "")),
                location=norm(item.get("location", "")),
                url=canonical_url(item.get("url", "")),
                source_url=canonical_url(item.get("url", "")),
            )
        )
    return [r for r in records if any([r.title, r.date, r.location, r.source_url, r.url])]


def read_inbox_records() -> List[RefRecord]:
    return read_tsv_records(
        TMP_INBOX_TSV_PATH,
        source="inbox",
        mapping={
            "status": "status",
            "title": "title",
            "date": "date",
            "time": "time",
            "city": "city",
            "location": "location",
            "url": "url",
            "source_url": "source_url",
        },
    )


def read_archive_records() -> List[RefRecord]:
    return read_tsv_records(
        TMP_ARCHIVE_TSV_PATH,
        source="archive",
        mapping={
            "status": "status",
            "title": "title",
            "date": "date",
            "time": "time",
            "city": "city",
            "location": "location",
            "url": "url",
            "source_url": "source_url",
        },
    )


def read_manual_records() -> List[RefRecord]:
    if not MANUAL_JSON_PATH.exists():
        return []
    try:
        raw = json.loads(MANUAL_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/inbox_manual.json ist ungültig: {exc}")
    if not isinstance(raw, list):
        fail("data/inbox_manual.json muss ein JSON-Array sein.")

    records: List[RefRecord] = []
    for item in raw:
        if not isinstance(item, dict):
            continue
        records.append(
            RefRecord(
                source="manual_json",
                title=norm(item.get("title", "")),
                date=norm(item.get("date", "")),
                time=norm(item.get("time", "")),
                city=norm(item.get("city", "")),
                location=norm(item.get("location", "")),
                url=canonical_url(item.get("url", "")),
                source_url=canonical_url(item.get("source_url", "")),
                status=norm(item.get("status", "")),
            )
        )
    return [r for r in records if any([r.title, r.date, r.location, r.source_url, r.url])]
# === END BLOCK: LOCAL DATA LOADERS (events/inbox/archive/manual -> normalized records) ===


# === BEGIN BLOCK: PRE-RUN GUARDS (pending inbox/manual protection) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Verhindert neue Suchläufe, wenn Review- oder Manual-Puffer noch offen sind
# - Entspricht der Prozessregel des Regelwerks
# Umfang:
# - Nur Guard-Checks, keine KI-Logik
# === END BLOCK: PRE-RUN GUARDS (pending inbox/manual protection) ===
def has_pending_inbox_rows(records: List[RefRecord]) -> bool:
    for rec in records:
        status_key = norm_key(rec.status)
        if status_key not in {"", *FINAL_INBOX_STATUSES}:
            return True
        if status_key == "":
            return True
    return False


def assert_preconditions(inbox_records: List[RefRecord], manual_records: List[RefRecord]) -> None:
    if FAIL_ON_PENDING_INBOX and has_pending_inbox_rows(inbox_records):
        fail("Inbox enthält noch offene Review-Fälle. Wochenlauf wird bewusst abgebrochen.")
    if FAIL_ON_PENDING_MANUAL and manual_records:
        fail("data/inbox_manual.json ist nicht leer. Wochenlauf wird bewusst abgebrochen.")
# === END BLOCK: PRE-RUN GUARDS (pending inbox/manual protection) ===


# === BEGIN BLOCK: PROMPT BUNDLE BUILDERS (compact manifests from current files) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Baut aus den aktuellen Dateien ein kompaktes, modellfreundliches Bundle
# - Hält den Suchkern KI-basiert, reduziert aber unnötigen Prompt-Ballast
# Umfang:
# - Nur Vorbereitung des Inputs für die Responses API
# === END BLOCK: PROMPT BUNDLE BUILDERS (compact manifests from current files) ===
def build_manifest(records: List[RefRecord], *, start: date, end: date, label: str) -> str:
    filtered: List[RefRecord] = []
    for rec in records:
        if not rec.date:
            continue
        if date_in_window(rec.date, start=start, end=end):
            filtered.append(rec)

    lines = [f"[{label}]"]
    for rec in filtered:
        lines.append(
            " | ".join([
                f"title={rec.title}",
                f"date={rec.date}",
                f"time={rec.time}",
                f"city={rec.city}",
                f"location={rec.location}",
                f"source_url={rec.source_url}",
                f"url={rec.url}",
                f"status={rec.status}",
            ])
        )
    if len(lines) == 1:
        lines.append("<empty>")
    lines.append(f"[/{label}]")
    return "\n".join(lines)


def build_rulebook_text(path: Path) -> str:
    if not path.exists():
        fail(f"Regelwerk fehlt: {path}")
    return path.read_text(encoding="utf-8")


def build_llm_messages(rulebook_text: str, events_records: List[RefRecord], inbox_records: List[RefRecord], archive_records: List[RefRecord], manual_records: List[RefRecord]) -> List[Dict[str, Any]]:
    start = today_local()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    context_bundle = "\n\n".join([
        build_manifest(events_records, start=start, end=end, label="BESTAND_EVENTS"),
        build_manifest(inbox_records, start=start, end=end, label="OFFENE_INBOX"),
        build_manifest(archive_records, start=start, end=end, label="ARCHIV"),
        build_manifest(manual_records, start=start, end=end, label="MANUAL_JSON"),
    ])

    system_prompt = (
        "Du führst die wöchentliche KI-Eventsuche für Bocholt erleben aus. "
        "Nutze aktiv die Websuche, suche systematisch und liefere nur neue Delta-Kandidaten. "
        "Der Suchkern ist eine echte KI-Suche, keine Parser- oder Venue-Listenlogik. "
        "Halte das Regelwerk strikt ein. "
        "Wenn Informationen nicht sicher belegt sind, lasse Felder weg oder verwerfe den Kandidaten. "
        "Kein Fließtext, kein Kommentar, keine Begründungen im Ergebnis."
    )

    user_prompt = (
        f"[REGELWERK]\n{rulebook_text}\n[/REGELWERK]\n\n"
        "[AUFGABE]\n"
        f"Suche neue, echte, veröffentlichungsreife Events für Bocholt erleben ab heute bis {SEARCH_WINDOW_DAYS} Tage in die Zukunft.\n"
        f"Suchgebiet: Bocholt + maximal ca. {ALLOW_RADIUS_KM} km Umkreis, inklusive niederländischer Orte innerhalb dieses Radius.\n"
        f"Liefere höchstens {MAX_NEW_CANDIDATES} neue Delta-Kandidaten, aber nur wenn sie die Kriterien klar erfüllen.\n"
        "Suche systematisch über offizielle kommunale Kalender, offizielle Veranstalterseiten, offizielle Kultur-/Museumsseiten und offizielle NL-Seiten im Radius.\n"
        "Nutze keine monetarisierungssensiblen privaten Locations als aktive Suchliste, wenn das Regelwerk sie ausschließt.\n"
        "Deduped strikt gegen Bestand, offene Inbox, Archiv und vorhandene Manual-Kandidaten aus dem Kontext-Bundle.\n"
        "Jedes Objekt muss genau eine konkrete besuchbare Termin-Instanz repräsentieren.\n"
        "Wenn mehrere Termine existieren, gib mehrere Objekte aus.\n"
        "Beschreibungen müssen kurz, neutral, facts-only und neu formuliert sein.\n"
        "[/AUFGABE]\n\n"
        f"{context_bundle}"
    )

    return [
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": user_prompt},
    ]
# === END BLOCK: PROMPT BUNDLE BUILDERS (compact manifests from current files) ===


# === BEGIN BLOCK: OPENAI SEARCH CALL (responses api + web_search + strict json schema) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Führt die eigentliche KI-Suche über die Responses API aus
# - Nutzt das Web-Search-Tool direkt im Modelllauf
# Umfang:
# - Nur der Modellaufruf + Rohantwort-Extraktion
# === END BLOCK: OPENAI SEARCH CALL (responses api + web_search + strict json schema) ===
def search_with_openai(messages: List[Dict[str, Any]]) -> Tuple[List[Dict[str, Any]], Any]:
    api_key = norm(os.environ.get("OPENAI_API_KEY", ""))
    if not api_key:
        fail("ENV OPENAI_API_KEY fehlt.")

    client = OpenAI(api_key=api_key)

    schema = {
        "type": "object",
        "additionalProperties": False,
        "properties": {
            "candidates": {
                "type": "array",
                "items": {
                    "type": "object",
                    "additionalProperties": False,
                    "properties": {
                        "title": {"type": "string"},
                        "date": {"type": "string"},
                        "endDate": {"type": "string"},
                        "time": {"type": "string"},
                        "city": {"type": "string"},
                        "location": {"type": "string"},
                        "kategorie_suggestion": {"type": "string"},
                        "url": {"type": "string"},
                        "description": {"type": "string"},
                        "source_name": {"type": "string"},
                        "source_url": {"type": "string"},
                        "notes": {"type": "string"},
                    },
                    "required": [
                        "title",
                        "date",
                        "endDate",
                        "time",
                        "city",
                        "location",
                        "kategorie_suggestion",
                        "url",
                        "description",
                        "source_name",
                        "source_url",
                        "notes",
                    ],
                },
            }
        },
        "required": ["candidates"],
    }

    response = client.responses.create(
        model=OPENAI_MODEL,
        tools=[{"type": "web_search"}],
        input=messages,
        text={
            "format": {
                "type": "json_schema",
                "name": "weekly_event_search_result",
                "strict": True,
                "schema": schema,
            }
        },
        store=False,
    )

    output_text = norm(getattr(response, "output_text", ""))
    if not output_text:
        fail("OpenAI Responses API lieferte keinen output_text zurück.")

    try:
        payload = json.loads(output_text)
    except Exception as exc:
        fail(f"OpenAI-Antwort ist kein gültiges JSON: {exc}")

    raw_candidates = payload.get("candidates", []) if isinstance(payload, dict) else []
    if not isinstance(raw_candidates, list):
        fail("OpenAI-Antwort enthält kein gültiges candidates-Array.")

    return raw_candidates, response
# === END BLOCK: OPENAI SEARCH CALL (responses api + web_search + strict json schema) ===


# === BEGIN BLOCK: LOCAL POST-VALIDATION + DEDUPE (thin safety net, not search core) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Validiert Pflichtfelder, Datumsformat und Kategorien lokal nach
# - Fängt Rest-Dubletten gegen aktuelle Referenzdateien ab
# Umfang:
# - Nur Sicherungsnetz hinter der KI-Suche
# === END BLOCK: LOCAL POST-VALIDATION + DEDUPE (thin safety net, not search core) ===
def build_existing_dedupe_sets(*record_groups: List[RefRecord]) -> Tuple[set[str], set[str], set[str]]:
    source_urls: set[str] = set()
    fps: set[str] = set()
    fps_without_time: set[str] = set()
    for group in record_groups:
        for rec in group:
            source_url_key = norm_key(rec.source_url or rec.url)
            if source_url_key:
                source_urls.add(source_url_key)
            fp = instance_fp(rec.title, rec.date, rec.time, rec.location)
            if fp != "|||":
                fps.add(fp)
            fp_no_time = instance_fp(rec.title, rec.date, "", rec.location)
            if fp_no_time != "|||":
                fps_without_time.add(fp_no_time)
    return source_urls, fps, fps_without_time


def normalize_candidate(item: Dict[str, Any]) -> Dict[str, str]:
    normalized = {key: norm(value) for key, value in item.items()}
    normalized.setdefault("endDate", "")
    normalized.setdefault("time", "")
    normalized.setdefault("notes", MANUAL_NOTES_VALUE)

    normalized["source_url"] = canonical_url(normalized.get("source_url", ""))
    normalized["url"] = canonical_url(normalized.get("url", ""))
    if not normalized["url"]:
        normalized["url"] = normalized["source_url"]
    if not normalized["source_url"]:
        normalized["source_url"] = normalized["url"]

    if normalized.get("kategorie_suggestion") not in ALLOWED_CATEGORIES:
        normalized["kategorie_suggestion"] = "Sonstiges"

    normalized["notes"] = MANUAL_NOTES_VALUE
    return normalized


def validate_candidate(item: Dict[str, str], *, start: date, end: date) -> Tuple[bool, str]:
    required = ["title", "date", "city", "location", "source_name", "source_url"]
    missing = [key for key in required if not norm(item.get(key, ""))]
    if missing:
        return False, f"missing_required:{','.join(missing)}"

    if not parse_iso_date(item.get("date", "")):
        return False, "invalid_date"
    if item.get("endDate") and not parse_iso_date(item.get("endDate", "")):
        return False, "invalid_endDate"
    if not date_in_window(item.get("date", ""), start=start, end=end):
        return False, "out_of_window"
    if item.get("endDate") and not date_in_window(item.get("endDate", ""), start=start, end=end + timedelta(days=7)):
        return False, "endDate_out_of_window"
    if item.get("time") and not re.match(r"^\d{2}:\d{2}$", item.get("time", "")):
        return False, "invalid_time"
    if item.get("description") and len(item["description"]) > 220:
        return False, "description_too_long"
    return True, "ok"


def filter_delta_candidates(raw_candidates: List[Dict[str, Any]], events_records: List[RefRecord], inbox_records: List[RefRecord], archive_records: List[RefRecord], manual_records: List[RefRecord]) -> List[Dict[str, str]]:
    start = today_local()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    existing_source_urls, existing_fps, existing_fps_without_time = build_existing_dedupe_sets(events_records, inbox_records, archive_records, manual_records)
    batch_source_urls: set[str] = set()
    batch_fps: set[str] = set()
    batch_fps_without_time: set[str] = set()

    output: List[Dict[str, str]] = []
    for raw_item in raw_candidates:
        if not isinstance(raw_item, dict):
            continue
        item = normalize_candidate(raw_item)
        is_valid, _reason = validate_candidate(item, start=start, end=end)
        if not is_valid:
            continue

        source_url_key = norm_key(item.get("source_url", "") or item.get("url", ""))
        fp = instance_fp(item.get("title", ""), item.get("date", ""), item.get("time", ""), item.get("location", ""))
        fp_without_time = instance_fp(item.get("title", ""), item.get("date", ""), "", item.get("location", ""))

        if source_url_key and (source_url_key in existing_source_urls or source_url_key in batch_source_urls):
            continue
        if fp in existing_fps or fp in batch_fps:
            continue
        if fp_without_time != "|||" and (fp_without_time in existing_fps_without_time or fp_without_time in batch_fps_without_time):
            continue

        output.append({k: v for k, v in item.items() if v != ""})
        if source_url_key:
            batch_source_urls.add(source_url_key)
        batch_fps.add(fp)
        if fp_without_time != "|||":
            batch_fps_without_time.add(fp_without_time)

    return output
# === END BLOCK: LOCAL POST-VALIDATION + DEDUPE (thin safety net, not search core) ===


# === BEGIN BLOCK: RESPONSE SUMMARY + OUTPUT WRITE (manual json + traceable log) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Schreibt data/inbox_manual.json deterministisch
# - Protokolliert Kandidatenanzahl und Web-Sources im Step Summary
# Umfang:
# - Nur Output / Observability
# === END BLOCK: RESPONSE SUMMARY + OUTPUT WRITE (manual json + traceable log) ===
def write_manual_json(items: List[Dict[str, str]]) -> None:
    ensure_parent(MANUAL_JSON_PATH)
    MANUAL_JSON_PATH.write_text(json.dumps(items, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def collect_response_sources(response: Any) -> List[str]:
    urls: List[str] = []
    try:
        output = getattr(response, "output", None) or []
        for item in output:
            if getattr(item, "type", "") != "message":
                continue
            for content_item in getattr(item, "content", []) or []:
                for annotation in getattr(content_item, "annotations", []) or []:
                    url_obj = getattr(annotation, "url_citation", None)
                    if url_obj is None:
                        continue
                    url = norm(getattr(url_obj, "url", ""))
                    if url:
                        urls.append(url)
    except Exception:
        return []
    unique = []
    seen = set()
    for url in urls:
        key = norm_key(url)
        if key in seen:
            continue
        seen.add(key)
        unique.append(url)
    return unique


def write_summary(delta_items: List[Dict[str, str]], response: Any) -> None:
    source_urls = collect_response_sources(response)
    lines = [
        "WEEKLY KI EVENTSUCHE SUMMARY",
        f"- model: {OPENAI_MODEL}",
        f"- written_manual_json: {len(delta_items)}",
        f"- output_file: {MANUAL_JSON_PATH}",
        f"- cited_web_sources: {len(source_urls)}",
    ]
    for url in source_urls[:25]:
        lines.append(f"  - {url}")
    summary = "\n".join(lines)
    print(summary)

    step_summary = norm(os.environ.get("GITHUB_STEP_SUMMARY", ""))
    if step_summary:
        Path(step_summary).write_text(summary + "\n", encoding="utf-8")
# === END BLOCK: RESPONSE SUMMARY + OUTPUT WRITE (manual json + traceable log) ===


# === BEGIN BLOCK: MAIN ENTRYPOINT (snapshot -> guards -> websearch -> manual json) ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Orchestriert den kompletten V1-Lauf für die automatisierte KI-Eventsuche
# Umfang:
# - Noch kein automatischer Intake-Trigger; nur data/inbox_manual.json als Ergebnis
# === END BLOCK: MAIN ENTRYPOINT (snapshot -> guards -> websearch -> manual json) ===
def main() -> None:
    export_current_snapshots()

    events_records = read_events_records()
    inbox_records = read_inbox_records()
    archive_records = read_archive_records()
    manual_records = read_manual_records()

    assert_preconditions(inbox_records, manual_records)

    rulebook_text = build_rulebook_text(REGELWERK_PATH)
    messages = build_llm_messages(rulebook_text, events_records, inbox_records, archive_records, manual_records)
    raw_candidates, response = search_with_openai(messages)
    delta_items = filter_delta_candidates(raw_candidates, events_records, inbox_records, archive_records, manual_records)

    write_manual_json(delta_items)
    write_summary(delta_items, response)


if __name__ == "__main__":
    main()
# === END BLOCK: MAIN ENTRYPOINT (snapshot -> guards -> websearch -> manual json) ===
