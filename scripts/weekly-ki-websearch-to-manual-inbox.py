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
from typing import Any, Dict, Iterable, List, Tuple
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

from google.oauth2 import service_account
from googleapiclient.discovery import build
from openai import OpenAI


# === BEGIN BLOCK: CONFIG ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zentraler Owner für Pfade, Sheet-Tabs, Guards und Modell-Konfiguration
# Umfang:
# - Nur Konfiguration / keine Logik
# === END BLOCK: CONFIG ===
ROOT = Path(__file__).resolve().parents[1]
TMP_DIR = ROOT / ".tmp" / "weekly-ki-eventsuche"

REGELWERK_PATH = Path(os.environ.get("EVENT_RULEBOOK_PATH", str(ROOT / "bocholt-erleben_eventsuche_regelwerk_v3.md")))
MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(ROOT / "data" / "inbox_manual.json")))

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

FAIL_ON_PENDING_INBOX = os.environ.get("FAIL_ON_PENDING_INBOX", "true").lower() in {"1", "true", "yes"}
FAIL_ON_PENDING_MANUAL = os.environ.get("FAIL_ON_PENDING_MANUAL", "true").lower() in {"1", "true", "yes"}

ALLOWED_CATEGORIES = {
    "Märkte & Feste",
    "Kultur & Kunst",
    "Musik & Bühne",
    "Kinder & Familie",
    "Sport & Bewegung",
    "Natur & Draußen",
    "Innenstadt & Leben",
    "Sonstiges",
}
# === END BLOCK: CONFIG ===


# === BEGIN BLOCK: BASIC HELPERS ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Kleine Hilfsfunktionen für Logging, Dateihandling, Datum und Dedupe-Key-Normalisierung
# Umfang:
# - Nur lokale Helper
# === END BLOCK: BASIC HELPERS ===
def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def norm(value: Any) -> str:
    return str(value or "").strip()


def norm_key(value: Any) -> str:
    return re.sub(r"\s+", " ", norm(value)).lower()


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


def parse_iso_date(raw: str) -> date | None:
    raw = norm(raw)
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except ValueError:
        return None


def date_in_window(raw: str, start: date, end: date) -> bool:
    d = parse_iso_date(raw)
    return bool(d and start <= d <= end)


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
# === END BLOCK: BASIC HELPERS ===


# === BEGIN BLOCK: REF RECORD MODEL ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Vereinheitlicht Referenzdaten aus Events/Inbox/Archiv/Manual-JSON
# Umfang:
# - Nur Datenträgerklasse
# === END BLOCK: REF RECORD MODEL ===
@dataclass
class RefRecord:
    title: str
    date: str
    time: str
    city: str
    location: str
    url: str
    source_url: str
    status: str = ""
# === END BLOCK: REF RECORD MODEL ===


# === BEGIN BLOCK: SHEETS SNAPSHOT EXPORT ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zieht vor jedem Lauf den aktuellen Datenstand direkt aus Google Sheets
# - Stellt so sicher, dass die KI gegen den neuesten Bestand deduped
# Umfang:
# - Exportiert nur lokale Laufzeit-Snapshots, kein Repo-Write an TSV-Dateien
# === END BLOCK: SHEETS SNAPSHOT EXPORT ===
def build_sheets_service() -> tuple[Any, str]:
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


def read_sheet_values(service: Any, sheet_id: str, tab_name: str) -> list[list[str]]:
    res = service.spreadsheets().values().get(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A:ZZ",
        majorDimension="ROWS",
    ).execute(num_retries=4)
    return res.get("values", []) or []


def write_tsv_snapshot(out_path: Path, values: list[list[str]], required_columns: Iterable[str]) -> int:
    if not values:
        fail(f"Sheet-Tab leer oder nicht lesbar: {out_path.name}")

    header = [norm(h) for h in (values[0] or [])]
    missing = [col for col in required_columns if col not in header]
    if missing:
        fail(f"Header unvollständig in {out_path.name}. Fehlende Spalten: {missing}")

    rows = []
    for raw_row in values[1:]:
        row = [(norm(raw_row[i]) if i < len(raw_row) else "") for i in range(len(header))]
        if any(v for v in row):
            rows.append(row)

    ensure_parent(out_path)
    with out_path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f, delimiter="\t", lineterminator="\n")
        writer.writerow(header)
        writer.writerows(rows)

    return len(rows)


def export_current_snapshots() -> None:
    service, sheet_id = build_sheets_service()

    events_rows = write_tsv_snapshot(
        TMP_EVENTS_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_EVENTS),
        ["id", "title", "date", "city", "location", "kategorie"],
    )
    info(f"Events-Snapshot exportiert: {events_rows} Zeilen")

    inbox_rows = write_tsv_snapshot(
        TMP_INBOX_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_INBOX),
        ["status", "title", "source_url", "created_at"],
    )
    info(f"Inbox-Snapshot exportiert: {inbox_rows} Zeilen")

    archive_rows = write_tsv_snapshot(
        TMP_ARCHIVE_TSV_PATH,
        read_sheet_values(service, sheet_id, TAB_ARCHIVE),
        ["status", "title", "source_url", "created_at"],
    )
    info(f"Archive-Snapshot exportiert: {archive_rows} Zeilen")
# === END BLOCK: SHEETS SNAPSHOT EXPORT ===


# === BEGIN BLOCK: DATA LOADERS ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Lädt alle Referenzbestände in ein gemeinsames Record-Format
# - Dient sowohl Guards als auch Prompt-Bundle und lokalem Nach-Dedupe
# Umfang:
# - Nur Lesen / Normalisieren
# === END BLOCK: DATA LOADERS ===
def read_tsv_records(path: Path, mapping: Dict[str, str]) -> List[RefRecord]:
    if not path.exists():
        return []

    records: List[RefRecord] = []
    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        for row in reader:
            title = norm(row.get(mapping.get("title", "title"), ""))
            date_value = norm(row.get(mapping.get("date", "date"), ""))
            time_value = norm(row.get(mapping.get("time", "time"), ""))
            city = norm(row.get(mapping.get("city", "city"), ""))
            location = norm(row.get(mapping.get("location", "location"), ""))
            url = canonical_url(row.get(mapping.get("url", "url"), ""))
            source_url = canonical_url(row.get(mapping.get("source_url", "source_url"), ""))
            status = norm(row.get(mapping.get("status", "status"), ""))

            if any([title, date_value, location, url, source_url]):
                records.append(RefRecord(title, date_value, time_value, city, location, url, source_url, status))

    return records


def read_manual_records() -> List[RefRecord]:
    if not MANUAL_JSON_PATH.exists():
        return []

    try:
        raw = json.loads(MANUAL_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/inbox_manual.json ist ungültig: {exc}")

    if not isinstance(raw, list):
        fail("data/inbox_manual.json muss ein JSON-Array sein.")

    out: List[RefRecord] = []
    for item in raw:
        if not isinstance(item, dict):
            continue
        out.append(
            RefRecord(
                norm(item.get("title", "")),
                norm(item.get("date", "")),
                norm(item.get("time", "")),
                norm(item.get("city", "")),
                norm(item.get("location", "")),
                canonical_url(item.get("url", "")),
                canonical_url(item.get("source_url", "")),
                norm(item.get("status", "")),
            )
        )
    return out
# === END BLOCK: DATA LOADERS ===


# === BEGIN BLOCK: PRE-RUN GUARDS ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Verhindert neue Suchläufe, wenn Review- oder Manual-Puffer noch offen sind
# - Entspricht der Prozessregel des Regelwerks
# Umfang:
# - Nur Guard-Checks
# === END BLOCK: PRE-RUN GUARDS ===
def has_pending_inbox_rows(records: List[RefRecord]) -> bool:
    for rec in records:
        if norm_key(rec.status) not in {"übernommen", "verworfen"}:
            return True
    return False
# === END BLOCK: PRE-RUN GUARDS ===


# === BEGIN BLOCK: PROMPT BUNDLE BUILDERS ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Baut aus den aktuellen Dateien ein kompaktes, modellfreundliches Bundle
# - Hält den Suchkern KI-basiert, reduziert aber unnötigen Prompt-Ballast
# Umfang:
# - Nur Vorbereitung des Inputs für die Responses API
# === END BLOCK: PROMPT BUNDLE BUILDERS ===
def build_manifest(label: str, records: List[RefRecord], start: date, end: date) -> str:
    lines = [f"[{label}]"]
    for rec in records:
        if not rec.date or not date_in_window(rec.date, start, end):
            continue
        lines.append(
            " | ".join(
                [
                    f"title={rec.title}",
                    f"date={rec.date}",
                    f"time={rec.time}",
                    f"city={rec.city}",
                    f"location={rec.location}",
                    f"source_url={rec.source_url}",
                    f"url={rec.url}",
                    f"status={rec.status}",
                ]
            )
        )
    if len(lines) == 1:
        lines.append("<empty>")
    lines.append(f"[/{label}]")
    return "\n".join(lines)


def build_messages(rulebook_text: str, events_records: List[RefRecord], inbox_records: List[RefRecord], archive_records: List[RefRecord], manual_records: List[RefRecord]) -> List[Dict[str, str]]:
    start = datetime.now().date()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    context_bundle = "\n\n".join(
        [
            build_manifest("BESTAND_EVENTS", events_records, start, end),
            build_manifest("OFFENE_INBOX", inbox_records, start, end),
            build_manifest("ARCHIV", archive_records, start, end),
            build_manifest("MANUAL_JSON", manual_records, start, end),
        ]
    )

    system_prompt = (
        "Du führst die wöchentliche KI-Eventsuche für Bocholt erleben aus. "
        "Nutze aktiv die Websuche, suche systematisch und liefere nur neue Delta-Kandidaten. "
        "Halte das Regelwerk strikt ein. Kein Fließtext, keine Kommentare im Ergebnis."
    )

    user_prompt = (
        f"[REGELWERK]\n{rulebook_text}\n[/REGELWERK]\n\n"
        "[AUFGABE]\n"
        f"Suche neue, echte, veröffentlichungsreife Events für Bocholt erleben ab heute bis {SEARCH_WINDOW_DAYS} Tage in die Zukunft.\n"
        f"Suchgebiet: Bocholt + maximal ca. {ALLOW_RADIUS_KM} km Umkreis, inklusive niederländischer Orte innerhalb dieses Radius.\n"
        f"Liefere höchstens {MAX_NEW_CANDIDATES} neue Delta-Kandidaten.\n"
        "Suche systematisch über offizielle kommunale Kalender, offizielle Veranstalterseiten, offizielle Kultur-/Museumsseiten und offizielle NL-Seiten im Radius.\n"
        "Deduped strikt gegen Bestand, offene Inbox, Archiv und vorhandene Manual-Kandidaten aus dem Kontext-Bundle.\n"
        "Jedes Objekt muss genau eine konkrete Termin-Instanz repräsentieren.\n"
        "Beschreibungen müssen kurz, neutral, facts-only und neu formuliert sein.\n"
        "[/AUFGABE]\n\n"
        f"{context_bundle}"
    )

    return [
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": user_prompt},
    ]
# === END BLOCK: PROMPT BUNDLE BUILDERS ===


# === BEGIN BLOCK: OPENAI SEARCH CALL ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Führt die eigentliche KI-Suche über die Responses API aus
# - Nutzt das Web-Search-Tool direkt im Modelllauf
# Umfang:
# - Nur der Modellaufruf + Rohantwort-Extraktion
# === END BLOCK: OPENAI SEARCH CALL ===
def search_with_openai(messages: List[Dict[str, str]]) -> tuple[list[dict[str, Any]], Any]:
    api_key = norm(os.environ.get("OPENAI_API_KEY", ""))
    if not api_key:
        fail("ENV OPENAI_API_KEY fehlt.")

    client = OpenAI(api_key=api_key)

    response = client.responses.create(
        model=OPENAI_MODEL,
        tools=[{"type": "web_search"}],
        tool_choice="auto",
        include=["web_search_call.action.sources"],
        input=messages,
        text={
            "format": {
                "type": "json_schema",
                "name": "weekly_event_search_result",
                "strict": True,
                "schema": {
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
                },
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
# === END BLOCK: OPENAI SEARCH CALL ===


# === BEGIN BLOCK: LOCAL POST-VALIDATION + DEDUPE ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Validiert Pflichtfelder, Datumsformat und Kategorien lokal nach
# - Fängt Rest-Dubletten gegen aktuelle Referenzdateien ab
# Umfang:
# - Nur Sicherungsnetz hinter der KI-Suche
# === END BLOCK: LOCAL POST-VALIDATION + DEDUPE ===
def existing_sets(*groups: List[RefRecord]) -> tuple[set[str], set[str], set[str]]:
    source_urls: set[str] = set()
    with_time: set[str] = set()
    without_time: set[str] = set()

    for group in groups:
        for rec in group:
            if rec.source_url or rec.url:
                source_urls.add(norm_key(rec.source_url or rec.url))

            fp = "|".join([norm_key(rec.title), rec.date, rec.time, norm_key(rec.location)])
            fp_no_time = "|".join([norm_key(rec.title), rec.date, "", norm_key(rec.location)])

            if fp != "|||":
                with_time.add(fp)
            if fp_no_time != "|||":
                without_time.add(fp_no_time)

    return source_urls, with_time, without_time


def normalize_candidate(item: Dict[str, Any]) -> Dict[str, str]:
    out = {k: norm(v) for k, v in item.items()}
    out.setdefault("endDate", "")
    out.setdefault("time", "")

    out["source_url"] = canonical_url(out.get("source_url", ""))
    out["url"] = canonical_url(out.get("url", "")) or out["source_url"]
    out["source_url"] = out["source_url"] or out["url"]

    if out.get("kategorie_suggestion", "") not in ALLOWED_CATEGORIES:
        out["kategorie_suggestion"] = "Sonstiges"

    out["notes"] = "manual chat search v3"
    return out


def valid_candidate(item: Dict[str, str], start: date, end: date) -> bool:
    required = ["title", "date", "city", "location", "source_name", "source_url"]
    if any(not norm(item.get(k, "")) for k in required):
        return False

    if not parse_iso_date(item.get("date", "")):
        return False
    if item.get("endDate") and not parse_iso_date(item.get("endDate", "")):
        return False
    if not date_in_window(item["date"], start, end):
        return False
    if item.get("time") and not re.match(r"^\d{2}:\d{2}$", item["time"]):
        return False

    return True


def filter_delta(raw_candidates: List[Dict[str, Any]], events_records: List[RefRecord], inbox_records: List[RefRecord], archive_records: List[RefRecord], manual_records: List[RefRecord]) -> List[Dict[str, str]]:
    start = datetime.now().date()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    existing_source_urls, existing_fps, existing_fps_without_time = existing_sets(
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )

    batch_source_urls: set[str] = set()
    batch_fps: set[str] = set()
    batch_fps_without_time: set[str] = set()

    out: List[Dict[str, str]] = []

    for raw in raw_candidates:
        if not isinstance(raw, dict):
            continue

        item = normalize_candidate(raw)
        if not valid_candidate(item, start, end):
            continue

        source_url_key = norm_key(item.get("source_url", "") or item.get("url", ""))
        fp = "|".join([norm_key(item.get("title", "")), item.get("date", ""), item.get("time", ""), norm_key(item.get("location", ""))])
        fp_no_time = "|".join([norm_key(item.get("title", "")), item.get("date", ""), "", norm_key(item.get("location", ""))])

        if source_url_key and (source_url_key in existing_source_urls or source_url_key in batch_source_urls):
            continue
        if fp in existing_fps or fp in batch_fps:
            continue
        if fp_no_time in existing_fps_without_time or fp_no_time in batch_fps_without_time:
            continue

        out.append({k: v for k, v in item.items() if v != ""})

        if source_url_key:
            batch_source_urls.add(source_url_key)
        batch_fps.add(fp)
        batch_fps_without_time.add(fp_no_time)

    return out
# === END BLOCK: LOCAL POST-VALIDATION + DEDUPE ===


# === BEGIN BLOCK: RESPONSE SUMMARY + OUTPUT WRITE ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Schreibt data/inbox_manual.json deterministisch
# - Protokolliert Kandidatenanzahl und Web-Sources im Step Summary
# Umfang:
# - Nur Output / Observability
# === END BLOCK: RESPONSE SUMMARY + OUTPUT WRITE ===
def collect_response_sources(response: Any) -> List[str]:
    urls: List[str] = []

    for item in getattr(response, "output", None) or []:
        if getattr(item, "type", "") == "web_search_call":
            action = getattr(item, "action", None)
            if action and getattr(action, "sources", None):
                for src in action.sources:
                    url = norm(getattr(src, "url", ""))
                    if url:
                        urls.append(url)

        if getattr(item, "type", "") == "message":
            for content_item in getattr(item, "content", []) or []:
                for annotation in getattr(content_item, "annotations", []) or []:
                    citation = getattr(annotation, "url_citation", None)
                    if citation is not None:
                        url = norm(getattr(citation, "url", ""))
                        if url:
                            urls.append(url)

    seen = set()
    unique = []
    for url in urls:
        key = norm_key(url)
        if key in seen:
            continue
        seen.add(key)
        unique.append(url)

    return unique
# === END BLOCK: RESPONSE SUMMARY + OUTPUT WRITE ===


# === BEGIN BLOCK: MAIN ENTRYPOINT ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Orchestriert den kompletten V1-Lauf für die automatisierte KI-Eventsuche
# Umfang:
# - Noch kein automatischer Intake-Trigger; nur data/inbox_manual.json als Ergebnis
# === END BLOCK: MAIN ENTRYPOINT ===
def main() -> None:
    export_current_snapshots()

    events_records = read_tsv_records(TMP_EVENTS_TSV_PATH, {"source_url": "url"})
    inbox_records = read_tsv_records(TMP_INBOX_TSV_PATH, {})
    archive_records = read_tsv_records(TMP_ARCHIVE_TSV_PATH, {})
    manual_records = read_manual_records()

    if FAIL_ON_PENDING_INBOX and has_pending_inbox_rows(inbox_records):
        fail("Inbox enthält noch offene Review-Fälle. Wochenlauf wird bewusst abgebrochen.")

    if FAIL_ON_PENDING_MANUAL and manual_records:
        fail("data/inbox_manual.json ist nicht leer. Wochenlauf wird bewusst abgebrochen.")

    if not REGELWERK_PATH.exists():
        fail(f"Regelwerk fehlt: {REGELWERK_PATH}")

    rulebook_text = REGELWERK_PATH.read_text(encoding="utf-8")
    raw_candidates, response = search_with_openai(
        build_messages(rulebook_text, events_records, inbox_records, archive_records, manual_records)
    )

    delta = filter_delta(raw_candidates, events_records, inbox_records, archive_records, manual_records)

    ensure_parent(MANUAL_JSON_PATH)
    MANUAL_JSON_PATH.write_text(json.dumps(delta, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    source_urls = collect_response_sources(response)
    print(
        "WEEKLY KI EVENTSUCHE SUMMARY\n"
        f"- model: {OPENAI_MODEL}\n"
        f"- written_manual_json: {len(delta)}\n"
        f"- output_file: {MANUAL_JSON_PATH}\n"
        f"- cited_web_sources: {len(source_urls)}"
    )


if __name__ == "__main__":
    main()
# === END BLOCK: MAIN ENTRYPOINT ===
