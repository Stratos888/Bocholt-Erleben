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
from collections import Counter
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

from event_visual_keys import infer_event_visual_key, normalize_event_visual_key
from event_visual_motifs import infer_event_visual_fit


# === BEGIN BLOCK: CONFIG ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zentraler Owner für Pfade, Sheet-Tabs, Guards und Modell-Konfiguration
# Umfang:
# - Nur Konfiguration / keine Logik
# === END BLOCK: CONFIG ===
ROOT = Path(__file__).resolve().parents[1]
TMP_DIR = ROOT / ".tmp" / "weekly-ki-eventsuche"

# === BEGIN BLOCK: WEEKLY_DIAGNOSTICS_PATH_CONFIG_V1 | Zweck: Output-, Quellen- und passive Diagnosepfade zentral konfigurieren | Umfang: ergänzt Coverage-Targets und Diagnose-JSON ohne Suchprompt-Einfluss ===
REGELWERK_PATH = Path(os.environ.get("EVENT_RULEBOOK_PATH", str(ROOT / "bocholt-erleben_eventsuche_regelwerk_v3.md")))
SOURCES_REGISTER_PATH = Path(os.environ.get("EVENT_SOURCES_REGISTER_PATH", str(ROOT / "eventsuche_quellenregister_v1.md")))
MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(ROOT / "data" / "inbox_manual.json")))
SOURCE_CANDIDATES_JSON_PATH = Path(os.environ.get("SOURCE_CANDIDATES_JSON_PATH", str(ROOT / "data" / "source_candidates.json")))
COVERAGE_TARGETS_JSON_PATH = Path(os.environ.get("COVERAGE_TARGETS_JSON_PATH", str(ROOT / "data" / "event_coverage_targets.json")))
CONTENT_SEARCH_FEEDBACK_JSON_PATH = Path(os.environ.get("CONTENT_SEARCH_FEEDBACK_JSON_PATH", str(ROOT / "data" / "content-search-feedback.json")))
DIAGNOSTICS_JSON_PATH = Path(os.environ.get("WEEKLY_DIAGNOSTICS_JSON_PATH", str(TMP_DIR / "weekly_event_diagnostics.json")))

TMP_EVENTS_TSV_PATH = Path(os.environ.get("TMP_EVENTS_TSV_PATH", str(TMP_DIR / "events.tsv")))
TMP_INBOX_TSV_PATH = Path(os.environ.get("TMP_INBOX_TSV_PATH", str(TMP_DIR / "inbox.tsv")))
TMP_ARCHIVE_TSV_PATH = Path(os.environ.get("TMP_ARCHIVE_TSV_PATH", str(TMP_DIR / "inbox_archive.tsv")))
TMP_CONTENT_SEARCH_FEEDBACK_TSV_PATH = Path(os.environ.get("TMP_CONTENT_SEARCH_FEEDBACK_TSV_PATH", str(TMP_DIR / "content_search_feedback.tsv")))
# === END BLOCK: WEEKLY_DIAGNOSTICS_PATH_CONFIG_V1 ===

TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
TAB_ARCHIVE = os.environ.get("TAB_ARCHIVE", "Inbox_Archive")
TAB_CONTENT_SEARCH_FEEDBACK = os.environ.get("TAB_CONTENT_SEARCH_FEEDBACK", "Content_Search_Feedback")

# === BEGIN BLOCK: WEEKLY_PRODUCTION_CONFIG_APPEND_READY_V5 | Zweck: Aufbau-/Backfill-Lauf mit 210-Tage-Suchfenster; offene Inbox und Manual-Puffer blockieren nicht, sondern bleiben Dedupe-/Append-Bestand | Umfang: setzt Suchhorizont, Kandidatenlimit und Warn-/Kompatibilitätskonfiguration ===
OPENAI_MODEL = os.environ.get("OPENAI_MODEL", "gpt-5.4").strip()
MAX_NEW_CANDIDATES = int(os.environ.get("MAX_NEW_CANDIDATES", "24"))
SEARCH_WINDOW_DAYS = int(os.environ.get("SEARCH_WINDOW_DAYS", "210"))
MIN_CANDIDATE_LEAD_DAYS = int(os.environ.get("MIN_CANDIDATE_LEAD_DAYS", "2"))
ALLOW_RADIUS_KM = int(os.environ.get("ALLOW_RADIUS_KM", "20"))

WARN_ON_PENDING_INBOX = os.environ.get(
    "WARN_ON_PENDING_INBOX",
    os.environ.get("FAIL_ON_PENDING_INBOX", "true"),
).lower() in {"1", "true", "yes"}
FAIL_ON_PENDING_MANUAL = os.environ.get("FAIL_ON_PENDING_MANUAL", "false").lower() in {"1", "true", "yes"}

# === BEGIN BLOCK: SELF_IMPROVING_SEARCH_FEEDBACK_LIMITS_FINAL | Zweck: verhindert Prompt-/Regel-Bloat im KI-Suchlauf; Umfang: harte Caps fuer Feedback-Pool, Prompt-Kontext und Inbox-Ablehnungsverlauf ===
MAX_SEARCH_FEEDBACK_POOL_RULES = int(os.environ.get("MAX_SEARCH_FEEDBACK_POOL_RULES", "48"))
MAX_PROMPT_SEARCH_FEEDBACK_RULES = int(os.environ.get("MAX_PROMPT_SEARCH_FEEDBACK_RULES", "18"))
INBOX_REJECTION_FEEDBACK_DAYS = int(os.environ.get("INBOX_REJECTION_FEEDBACK_DAYS", "365"))
MAX_FEEDBACK_RULE_TEXT_CHARS = int(os.environ.get("MAX_FEEDBACK_RULE_TEXT_CHARS", "420"))
MAX_FEEDBACK_EXAMPLE_TEXT_CHARS = int(os.environ.get("MAX_FEEDBACK_EXAMPLE_TEXT_CHARS", "220"))
# === END BLOCK: SELF_IMPROVING_SEARCH_FEEDBACK_LIMITS_FINAL ===
# === END BLOCK: WEEKLY_PRODUCTION_CONFIG_APPEND_READY_V5 ===

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
# === BEGIN BLOCK: OUTPUT_TEXT_NORMALIZATION_HELPERS_V1 | Zweck: Modelltexte für JSON/Inbox-Ausgabe einzeilig und URL-sicher normalisieren | Umfang: ergänzt lokale Helper ohne globale norm()-Semantik zu verändern ===
def clean_output_text(value: Any) -> str:
    return re.sub(r"\s+", " ", norm(value))


def clean_url_text(value: Any) -> str:
    return re.sub(r"\s+", "", norm(value))
# === END BLOCK: OUTPUT_TEXT_NORMALIZATION_HELPERS_V1 ===

# === BEGIN BLOCK: URL_CANONICALIZATION_OUTPUT_SAFE_V2 | Zweck: URLs vor Parsing/JSON-Ausgabe von Whitespace und Trackingparametern bereinigen | Umfang: ersetzt canonical_url vollständig ===
def canonical_url(raw: str) -> str:
    raw = clean_url_text(raw)
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
# === END BLOCK: URL_CANONICALIZATION_OUTPUT_SAFE_V2 ===


# === BEGIN BLOCK: WEEKLY_DOWNLOAD_DOCUMENT_URL_GUARD_V1 | Zweck: KI-Suchergebnisse mit PDF-/Download-URLs nicht in die manuelle Inbox uebernehmen; Umfang: URL-Klassifikation + Validierungs-Drop-Grund ===
DOCUMENT_URL_RE = re.compile(r"(?:\.pdf|\.docx?|\.xlsx?|\.pptx?)(?:$|[?#])", re.I)
DOWNLOAD_QUERY_RE = re.compile(r"(?:^|[?&])download(?:=1|=true|&|$)", re.I)


def is_download_document_url(value: str) -> bool:
    url = canonical_url(value).lower()
    if not url:
        return False
    if DOCUMENT_URL_RE.search(url):
        return True
    if DOWNLOAD_QUERY_RE.search(url):
        return True
    if "/bocholt_media/" in url and any(ext in url for ext in (".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx")):
        return True
    return False
# === END BLOCK: WEEKLY_DOWNLOAD_DOCUMENT_URL_GUARD_V1 ===


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


def read_optional_sheet_values(service: Any, sheet_id: str, tab_name: str) -> list[list[str]]:
    try:
        return read_sheet_values(service, sheet_id, tab_name)
    except Exception as exc:
        info(f"Optionaler Sheet-Tab nicht lesbar: {tab_name} ({exc})")
        return []


def write_optional_tsv_snapshot(out_path: Path, values: list[list[str]]) -> int:
    ensure_parent(out_path)
    if not values:
        out_path.write_text("", encoding="utf-8")
        return 0

    header = [norm(h) for h in (values[0] or [])]
    rows = []
    for raw_row in values[1:]:
        row = [(norm(raw_row[i]) if i < len(raw_row) else "") for i in range(len(header))]
        if any(v for v in row):
            rows.append(row)

    with out_path.open("w", encoding="utf-8", newline="") as f:
        writer = csv.writer(f, delimiter="\t", lineterminator="\n")
        writer.writerow(header)
        writer.writerows(rows)

    return len(rows)


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

    feedback_rows = write_optional_tsv_snapshot(
        TMP_CONTENT_SEARCH_FEEDBACK_TSV_PATH,
        read_optional_sheet_values(service, sheet_id, TAB_CONTENT_SEARCH_FEEDBACK),
    )
    info(f"Content-Search-Feedback-Snapshot exportiert: {feedback_rows} Zeilen")
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


# === BEGIN BLOCK: MANUAL_INBOX_BUFFER_LOADERS_APPEND_SAFE_V1 | Zweck: liest bestehende Manual-JSON-Einträge als erhaltbaren Puffer und zusätzlich als Dedupe-Records | Umfang: ersetzt read_manual_records vollständig und ergänzt read_manual_items/read_manual_records_from_items ===
def read_manual_items() -> List[Dict[str, Any]]:
    if not MANUAL_JSON_PATH.exists():
        return []

    try:
        raw = json.loads(MANUAL_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/inbox_manual.json ist ungültig: {exc}")

    if not isinstance(raw, list):
        fail("data/inbox_manual.json muss ein JSON-Array sein.")

    out: List[Dict[str, Any]] = []
    for idx, item in enumerate(raw, start=1):
        if not isinstance(item, dict):
            fail(f"data/inbox_manual.json Element #{idx} ist kein Objekt.")
        out.append(dict(item))

    return out


def read_manual_records_from_items(manual_items: List[Dict[str, Any]]) -> List[RefRecord]:
    out: List[RefRecord] = []

    for item in manual_items:
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


def read_manual_records() -> List[RefRecord]:
    return read_manual_records_from_items(read_manual_items())
# === END BLOCK: MANUAL_INBOX_BUFFER_LOADERS_APPEND_SAFE_V1 ===
# === END BLOCK: DATA LOADERS ===


# === BEGIN BLOCK: PRE_RUN_GUARDS_PENDING_INBOX_AS_DEDUPE_BASIS_V1 | Zweck: offene Inbox-Zeilen zählen, aber nicht mehr als Lauf-Blocker behandeln | Umfang: Manual-Puffer-Guard bleibt unverändert, Inbox bleibt Dedupe-Bestand ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Zählt offene Review-Fälle in der Inbox für Observability
# - Erhält Kompatibilität zu has_pending_inbox_rows(...)
# - Die offene Inbox wird weiterhin in Prompt, lokalem Dedupe und Intake-Dedupe berücksichtigt
# Umfang:
# - Nur Guard-/Zähl-Helper
def is_pending_inbox_record(rec: RefRecord) -> bool:
    return norm_key(rec.status) not in {"übernommen", "verworfen"}


def count_pending_inbox_rows(records: List[RefRecord]) -> int:
    return sum(1 for rec in records if is_pending_inbox_record(rec))


def has_pending_inbox_rows(records: List[RefRecord]) -> bool:
    return count_pending_inbox_rows(records) > 0
# === END BLOCK: PRE_RUN_GUARDS_PENDING_INBOX_AS_DEDUPE_BASIS_V1 ===



# === BEGIN BLOCK: SELF_IMPROVING_SEARCH_FEEDBACK_CONTEXT_FINAL | Zweck: verdichtet Audit-Feedback und Inbox-Ablehnungen zu begrenztem Lernkontext fuer den Weekly-KI-Suchlauf; Umfang: Prompt-Kontext, harte Caps, Decay, keine automatische Datenmutation ===
def feedback_join(value: Any, limit: int = MAX_FEEDBACK_EXAMPLE_TEXT_CHARS) -> str:
    if isinstance(value, list):
        text = "; ".join(clean_output_text(item) for item in value if clean_output_text(item))
    else:
        text = clean_output_text(value)
    return text[:limit]


def read_tsv_dicts(path: Path) -> list[dict[str, str]]:
    if not path.exists() or not path.read_text(encoding="utf-8").strip():
        return []
    try:
        with path.open("r", encoding="utf-8", newline="") as f:
            reader = csv.DictReader(f, delimiter="\t")
            return [dict(row) for row in reader if any(norm(v) for v in row.values())]
    except Exception as exc:
        info(f"TSV nicht lesbar: {path} ({exc})")
        return []


def load_json_search_feedback(path: Path) -> list[dict[str, Any]]:
    if not path.exists():
        return []
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        info(f"Content-Search-Feedback-JSON unlesbar: {path} ({exc})")
        return []
    rules = payload.get("rules") if isinstance(payload, dict) else []
    return [dict(item) for item in rules if isinstance(item, dict)]


def load_tsv_search_feedback(path: Path) -> list[dict[str, Any]]:
    return [dict(row) for row in read_tsv_dicts(path)]


INBOX_REJECTION_FEEDBACK_CLASSES: dict[str, dict[str, Any]] = {
    "rejected_duplicate_existing": {
        "field": "dedupe",
        "priority": 78,
        "prompt_rule": "Wenn ein Kandidat einem bestehenden, offenen, archivierten oder im aktuellen Lauf erkannten Event gleicht, nicht erneut ausgeben. Dedupe nach Titel, Datum, Ort und source_url konsequent anwenden.",
    },
    "rejected_event_past": {
        "field": "event_timing",
        "priority": 96,
        "prompt_rule": "Keine abgelaufenen, gleichen-Tag- oder zu kurzfristigen Termine als normalen Inbox-Kandidaten ausgeben. Fuer woechentliche manuelle Pruefung nur Kandidaten mit ausreichendem Vorlauf liefern.",
    },
    "rejected_unclear_date_time_place": {
        "field": "date_time_location",
        "priority": 90,
        "prompt_rule": "Datum, Uhrzeit und Ort müssen aus der konkreten Instanzquelle belastbar sein. Bei unklaren Terminangaben, unsicherer Ortsgranularitaet oder zweifelhafter URL nicht als FINAL ausgeben.",
    },
    "rejected_not_public": {
        "field": "public_access",
        "priority": 84,
        "prompt_rule": "Nur oeffentlich besuchbare Veranstaltungen ausgeben. Interne, geschlossene, ausgebuchte, reine Gruppen-/Kurs- oder nicht klar zugaengliche Termine nicht als FINAL ausgeben.",
    },
    "rejected_no_concrete_single_event": {
        "field": "event_scope",
        "priority": 88,
        "prompt_rule": "Keine Dauerangebote, Oeffnungszeiten, Kursuebersichten, allgemeinen Infoseiten, Betreuungswochen oder losen Reihen als Event ausgeben. Nur konkrete Einzeltermine oder klar zusammenhaengende Mehrtagesevents.",
    },
    "rejected_source_insufficient": {
        "field": "source_quality",
        "priority": 86,
        "prompt_rule": "Quelle muss event-spezifisch, oeffentlich lesbar und kanonisch genug sein. Bei schwacher, unklarer oder nicht instanzpassender Quelle zuerst bessere offizielle Detailquelle suchen; sonst nicht ausgeben.",
    },
    "rejected_not_local_enough": {
        "field": "location_scope",
        "priority": 82,
        "prompt_rule": "Keine Kandidaten ausgeben, die raeumlich nicht klar zu Bocholt und dem definierten Nahraum passen. Randgebiet nur bei starkem Eventcharakter und sicherer Naehe.",
    },
    "rejected_editorial_fit": {
        "field": "editorial_fit",
        "priority": 76,
        "prompt_rule": "Nicht nur formal korrekte Termine ausgeben. Kandidaten muessen redaktionell stark genug, lokal relevant und fuer normale Nutzer der Kuratierungs-PWA nuetzlich sein.",
    },
    "rejected_advertising_or_commercial": {
        "field": "commercial_fit",
        "priority": 82,
        "prompt_rule": "Eintraege, die primaer Werbung, Verkauf, Dienstleistung, Anbieterpromotion oder monetarisierungsrelevante Venue-Promo sind, nicht als redaktionellen Event-Kandidaten ausgeben.",
    },
    "rejected_activity_scope_mismatch": {
        "field": "activity_event_boundary",
        "priority": 74,
        "prompt_rule": "Aktivitaeten, Dauerorte und Aktivitaetspraesenzen nicht mit Event-Einzelterminen vermischen. Activity-Signale duerfen nicht als Event-Backfill in die Event-Inbox wandern.",
    },
    "rejected_other_review_signal": {
        "field": "manual_review_quality",
        "priority": 52,
        "prompt_rule": "Manuelle Ablehnungen als Qualitaetssignal verstehen: aehnliche Kandidaten nur ausgeben, wenn Quelle, Termin, Oeffentlichkeit, Lokalitaet und redaktioneller Nutzen eindeutig besser belegt sind.",
    },
}


BUILTIN_DEFAULT_SEARCH_FEEDBACK_RULES: list[dict[str, Any]] = [
    {
        "feedback_class": "rejected_event_past",
        "issue_code": "inbox_rejection_reason_default",
        "content_type": "event",
        "source_system": "inbox_rejection_defaults",
        "source_host": "all",
        "field": "event_timing",
        "count": 0,
        "priority": 90,
        "status": "default_context",
        "prompt_rule": INBOX_REJECTION_FEEDBACK_CLASSES["rejected_event_past"]["prompt_rule"],
    },
    {
        "feedback_class": "rejected_unclear_date_time_place",
        "issue_code": "inbox_rejection_reason_default",
        "content_type": "event",
        "source_system": "inbox_rejection_defaults",
        "source_host": "all",
        "field": "date_time_location",
        "count": 0,
        "priority": 80,
        "status": "default_context",
        "prompt_rule": INBOX_REJECTION_FEEDBACK_CLASSES["rejected_unclear_date_time_place"]["prompt_rule"],
    },
    {
        "feedback_class": "rejected_no_concrete_single_event",
        "issue_code": "inbox_rejection_reason_default",
        "content_type": "event",
        "source_system": "inbox_rejection_defaults",
        "source_host": "all",
        "field": "event_scope",
        "count": 0,
        "priority": 75,
        "status": "default_context",
        "prompt_rule": INBOX_REJECTION_FEEDBACK_CLASSES["rejected_no_concrete_single_event"]["prompt_rule"],
    },
    {
        "feedback_class": "rejected_source_insufficient",
        "issue_code": "inbox_rejection_reason_default",
        "content_type": "event",
        "source_system": "inbox_rejection_defaults",
        "source_host": "all",
        "field": "source_quality",
        "count": 0,
        "priority": 75,
        "status": "default_context",
        "prompt_rule": INBOX_REJECTION_FEEDBACK_CLASSES["rejected_source_insufficient"]["prompt_rule"],
    },
]


def parse_feedback_date(value: Any) -> date | None:
    raw = norm(value)
    if not raw:
        return None
    for candidate in [raw[:10], raw.replace("/", "-")[:10]]:
        parsed = parse_iso_date(candidate)
        if parsed:
            return parsed
    for fmt in ("%d.%m.%Y", "%d.%m.%y"):
        try:
            return datetime.strptime(raw[:10], fmt).date()
        except Exception:
            pass
    return None


def is_feedback_rule_expired(item: dict[str, Any], today: date) -> bool:
    status = norm(item.get("status") or "active")
    if status == "default_context":
        return False
    expires_at = parse_feedback_date(item.get("expires_at"))
    if expires_at and expires_at < today:
        return True
    last_seen_at = parse_feedback_date(item.get("last_seen_at") or item.get("generated_at"))
    if last_seen_at and (today - last_seen_at).days > INBOX_REJECTION_FEEDBACK_DAYS:
        try:
            count = int(float(norm(item.get("count") or "0")))
        except Exception:
            count = 0
        return count <= 1
    return False


def classify_inbox_rejection(row: dict[str, str]) -> tuple[str, dict[str, Any]] | None:
    status = norm_key(row.get("status", ""))
    if status not in {"verworfen", "rejected", "abgelehnt"}:
        return None

    reason = " ".join(
        norm(row.get(key, ""))
        for key in [
            "ablehnungsgrund",
            "Ablehnungsgrund",
            "rejection_reason",
            "reject_reason",
            "reason",
            "notes",
            "notes_text",
            "review_notes",
            "review_note",
        ]
        if norm(row.get(key, ""))
    )
    reason_key = norm_key(reason)

    if not reason_key:
        feedback_class = "rejected_other_review_signal"
    elif any(token in reason_key for token in ["vergangenheit", "abgelaufen", "termin vorbei", "termin ist vorbei", "past event"]):
        feedback_class = "rejected_event_past"
    elif any(token in reason_key for token in ["doppelt", "dublette", "bereits vorhanden", "bereits ausreichend", "abgedeckt"]):
        feedback_class = "rejected_duplicate_existing"
    elif any(token in reason_key for token in ["terminangaben", "datum", "uhrzeit", "ort", "link", "unklar", "nicht belastbar"]):
        feedback_class = "rejected_unclear_date_time_place"
    elif any(token in reason_key for token in ["nicht öffentlich", "nicht oeffentlich", "nicht klar zugänglich", "nicht klar zugaenglich", "geschlossen", "intern"]):
        feedback_class = "rejected_not_public"
    elif any(token in reason_key for token in ["dauerangebot", "öffnungszeit", "oeffnungszeit", "kursübersicht", "kursuebersicht", "allgemeine information", "kein konkreter einzeltermin", "betreuungswoche"]):
        feedback_class = "rejected_no_concrete_single_event"
    elif any(token in reason_key for token in ["quelle", "angaben reichen", "verlässliche veröffentlichung", "verlaessliche veroeffentlichung", "nicht ausreichend", "zu schwach"]):
        feedback_class = "rejected_source_insufficient"
    elif any(token in reason_key for token in ["nicht lokal", "räumlich", "raeumlich", "umgebung", "zu weit"]):
        feedback_class = "rejected_not_local_enough"
    elif any(token in reason_key for token in ["werbung", "dienstleistung", "verkauf", "anbieter", "promotion", "kommerziell"]):
        feedback_class = "rejected_advertising_or_commercial"
    elif any(token in reason_key for token in ["einzeltermin statt aktivität", "einzeltermin statt aktivitaet", "keine eigene aktivitätskarte", "keine eigene aktivitaetskarte"]):
        feedback_class = "rejected_activity_scope_mismatch"
    elif any(token in reason_key for token in ["redaktionell", "nicht passend", "fokus", "nicht eigenständig", "nicht eigenstaendig"]):
        feedback_class = "rejected_editorial_fit"
    else:
        feedback_class = "rejected_other_review_signal"

    config = INBOX_REJECTION_FEEDBACK_CLASSES[feedback_class]
    source_url = canonical_url(row.get("source_url", "")) or canonical_url(row.get("url", ""))
    host = source_domain(source_url) if source_url else "all"
    if host.startswith("www."):
        host = host[4:]

    # Allgemeine Ablehnungsgruende werden bewusst nicht pro Host aufgefaechert, damit der Prompt nicht mit Einzelfallquellen waechst.
    host_scope = host if feedback_class in {"rejected_source_insufficient", "rejected_advertising_or_commercial"} else "all"
    content_type = norm(row.get("content_type") or row.get("submission_kind") or row.get("type") or "event")
    if content_type not in {"event", "activity"}:
        content_type = "event"

    seen_at = (
        parse_feedback_date(row.get("updated_at"))
        or parse_feedback_date(row.get("rejected_at"))
        or parse_feedback_date(row.get("created_at"))
    )
    if seen_at and (date.today() - seen_at).days > INBOX_REJECTION_FEEDBACK_DAYS:
        return None

    return feedback_class, {
        "feedback_class": feedback_class,
        "issue_code": "inbox_rejection_reason",
        "content_type": content_type,
        "source_system": "inbox_rejection_history",
        "source_host": host_scope,
        "field": config["field"],
        "count": 1,
        "priority": int(config["priority"]),
        "status": "active",
        "prompt_rule": config["prompt_rule"],
        "example_titles": [norm(row.get("title", ""))] if norm(row.get("title", "")) else [],
        "example_urls": [source_url] if source_url else [],
        "last_seen_at": seen_at.isoformat() if seen_at else "",
        "source": "inbox_rejection_history",
    }


def read_inbox_rejection_feedback_rules() -> list[dict[str, Any]]:
    rows = read_tsv_dicts(TMP_ARCHIVE_TSV_PATH) + read_tsv_dicts(TMP_INBOX_TSV_PATH)
    grouped: dict[tuple[str, str, str, str], dict[str, Any]] = {}

    for row in rows:
        classified = classify_inbox_rejection(row)
        if not classified:
            continue
        _feedback_class, rule = classified
        key = (
            norm(rule.get("feedback_class")),
            norm(rule.get("content_type")),
            norm(rule.get("source_host")),
            norm(rule.get("field")),
        )
        if key not in grouped:
            grouped[key] = dict(rule)
            continue

        current = grouped[key]
        current["count"] = int(current.get("count") or 0) + 1
        current["priority"] = max(int(current.get("priority") or 0), int(rule.get("priority") or 0))
        titles = current.setdefault("example_titles", [])
        for title in rule.get("example_titles", []):
            if title and title not in titles and len(titles) < 6:
                titles.append(title)
        urls = current.setdefault("example_urls", [])
        for url in rule.get("example_urls", []):
            if url and url not in urls and len(urls) < 4:
                urls.append(url)
        if rule.get("last_seen_at") and (not current.get("last_seen_at") or rule["last_seen_at"] > current["last_seen_at"]):
            current["last_seen_at"] = rule["last_seen_at"]

    for rule in grouped.values():
        # Wiederholte reale Ablehnungen duerfen etwas nach oben, aber nie unbegrenzt eskalieren.
        try:
            count = int(rule.get("count") or 0)
        except Exception:
            count = 0
        rule["priority"] = min(98, int(rule.get("priority") or 0) + min(10, max(0, count - 1)))

    return list(grouped.values())


def normalize_search_feedback_rule(item: dict[str, Any], source: str = "unknown") -> dict[str, str] | None:
    if is_feedback_rule_expired(item, date.today()):
        return None

    feedback_class = norm(item.get("feedback_class"))
    prompt_rule = clean_output_text(item.get("prompt_rule"))[:MAX_FEEDBACK_RULE_TEXT_CHARS]
    status = norm(item.get("status") or "active")
    if not feedback_class or not prompt_rule:
        return None
    if status not in {"active", "default_context"}:
        return None
    try:
        priority_int = int(float(norm(item.get("priority") or "0")))
    except Exception:
        priority_int = 0
    try:
        count_int = int(float(norm(item.get("count") or "0")))
    except Exception:
        count_int = 0

    return {
        "feedback_class": feedback_class,
        "issue_code": norm(item.get("issue_code")),
        "content_type": norm(item.get("content_type")),
        "source_system": norm(item.get("source_system")),
        "source_host": norm(item.get("source_host")) or "all",
        "field": norm(item.get("field")),
        "priority": str(priority_int),
        "count": str(count_int),
        "status": status,
        "prompt_rule": prompt_rule,
        "example_titles": feedback_join(item.get("example_titles")),
        "example_urls": feedback_join(item.get("example_urls")),
        "last_seen_at": norm(item.get("last_seen_at")),
        "source": norm(item.get("source")) or source,
    }


def merge_feedback_rules(raw_rules: list[dict[str, Any]]) -> list[dict[str, str]]:
    merged: dict[tuple[str, str, str, str, str], dict[str, str]] = {}
    order: list[tuple[str, str, str, str, str]] = []

    for item in raw_rules:
        source = norm(item.get("source")) or norm(item.get("source_system")) or "unknown"
        rule = normalize_search_feedback_rule(item, source)
        if not rule:
            continue
        key = (
            rule["feedback_class"],
            rule["content_type"],
            rule["source_host"],
            rule["field"],
            rule["prompt_rule"],
        )
        if key not in merged:
            merged[key] = rule
            order.append(key)
            continue
        current = merged[key]
        current["count"] = str(int(current.get("count") or 0) + int(rule.get("count") or 0))
        current["priority"] = str(max(int(current.get("priority") or 0), int(rule.get("priority") or 0)))
        if rule.get("example_titles") and rule["example_titles"] not in current.get("example_titles", ""):
            current["example_titles"] = feedback_join([current.get("example_titles", ""), rule["example_titles"]])
        if rule.get("source") and rule["source"] not in current.get("source", ""):
            current["source"] = feedback_join([current.get("source", ""), rule["source"]], 120)

    out = [merged[key] for key in order]
    # Built-in Defaults nur ergänzen, wenn keine aktive/Default-Regel derselben Klasse existiert.
    existing_classes = {rule.get("feedback_class") for rule in out}
    for item in BUILTIN_DEFAULT_SEARCH_FEEDBACK_RULES:
        if item["feedback_class"] not in existing_classes:
            normalized = normalize_search_feedback_rule(item, "builtin_default")
            if normalized:
                out.append(normalized)
                existing_classes.add(item["feedback_class"])

    out.sort(
        key=lambda row: (
            row.get("status") != "active",
            -int(row.get("priority") or 0),
            -int(row.get("count") or 0),
            row.get("feedback_class", ""),
        )
    )
    return out[:MAX_SEARCH_FEEDBACK_POOL_RULES]


def read_search_feedback_rules(limit: int = MAX_PROMPT_SEARCH_FEEDBACK_RULES) -> list[dict[str, str]]:
    raw_rules: list[dict[str, Any]] = []

    sheet_rules = load_tsv_search_feedback(TMP_CONTENT_SEARCH_FEEDBACK_TSV_PATH)
    for item in sheet_rules:
        item.setdefault("source", "content_search_feedback_sheet")
    raw_rules.extend(sheet_rules)

    if not sheet_rules:
        json_rules = load_json_search_feedback(CONTENT_SEARCH_FEEDBACK_JSON_PATH)
        for item in json_rules:
            item.setdefault("source", "content_search_feedback_json")
        raw_rules.extend(json_rules)

    raw_rules.extend(read_inbox_rejection_feedback_rules())
    merged = merge_feedback_rules(raw_rules)
    return merged[:max(1, min(limit, MAX_PROMPT_SEARCH_FEEDBACK_RULES))]


def build_search_feedback_context(rules: list[dict[str, str]]) -> str:
    lines = ["[CONTENT_SEARCH_FEEDBACK]"]
    if not rules:
        lines.append("<empty>")
        lines.append("[/CONTENT_SEARCH_FEEDBACK]")
        return "\n".join(lines)

    class_counts = Counter(rule.get("feedback_class", "") for rule in rules)
    source_counts = Counter(rule.get("source", "") for rule in rules)
    lines.append("contract=search_prompt_context_only_no_auto_write_no_rulebook_mutation")
    lines.append(f"hard_limits=pool_max:{MAX_SEARCH_FEEDBACK_POOL_RULES}, prompt_max:{MAX_PROMPT_SEARCH_FEEDBACK_RULES}, rejection_days:{INBOX_REJECTION_FEEDBACK_DAYS}")
    lines.append("rule_count=" + str(len(rules)))
    lines.append("classes=" + ", ".join(f"{key}:{value}" for key, value in sorted(class_counts.items()) if key))
    lines.append("sources=" + ", ".join(f"{key}:{value}" for key, value in sorted(source_counts.items()) if key))
    lines.append("interpretation=These are consolidated feedback classes, not raw user instructions. Do not append them to the permanent rulebook; use them only to avoid repeated search/intake mistakes in this run.")
    for idx, rule in enumerate(rules, start=1):
        parts = [
            f"#{idx}",
            f"priority={rule.get('priority', '')}",
            f"count={rule.get('count', '')}",
            f"status={rule.get('status', '')}",
            f"source={rule.get('source', '')}",
            f"class={rule.get('feedback_class', '')}",
            f"issue_code={rule.get('issue_code', '')}",
            f"field={rule.get('field', '')}",
            f"host={rule.get('source_host', '')}",
            f"rule={rule.get('prompt_rule', '')}",
        ]
        examples = rule.get("example_titles") or ""
        if examples:
            parts.append(f"examples={examples[:MAX_FEEDBACK_EXAMPLE_TEXT_CHARS]}")
        lines.append(" | ".join(parts))
    lines.append("[/CONTENT_SEARCH_FEEDBACK]")
    return "\n".join(lines)
# === END BLOCK: SELF_IMPROVING_SEARCH_FEEDBACK_CONTEXT_FINAL ===

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
        if rec.date and not date_in_window(rec.date, start, end):
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


# === BEGIN BLOCK: WEEKLY_PRODUCTION_PROMPT_WITH_SYSTEMATIC_BACKFILL_COVERAGE_V3 | Zweck: Aufbau-/Backfill-Lauf mit 24er Zielkorridor, Pflicht-Cluster-Abdeckung und hartem Output-Vertrag | Umfang: ersetzt build_messages vollständig, hält Event-/Quellen-Output getrennt ===
def build_messages(
    rulebook_text: str,
    sources_register_text: str,
    search_feedback_context: str,
    events_records: List[RefRecord],
    inbox_records: List[RefRecord],
    archive_records: List[RefRecord],
    manual_records: List[RefRecord],
) -> List[Dict[str, str]]:
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

    system_prompt = """
Du führst die KI-Eventsuche für Bocholt erleben aus.

Arbeite streng, konservativ, produktionsnah, quellenbasiert und systematisch.
Nutze aktiv die Websuche.
Halte das beigefügte Regelwerk strikt ein.
Nutze das beigefügte Quellenregister als operative Quellensteuerung.
Nutze das beigefügte Content-Search-Feedback als begrenzten, verdichteten Lernkontext aus Audit-Findings und Inbox-Ablehnungen.
Visual-Fit-Feedback im Content-Search-Feedback ist nur Such-/Extraktionskontext: Erhalte konkrete Motivbegriffe in Titel/Beschreibung/notes, erzeuge aber niemals neue Visual Keys und starte keine Bildlogik.
Liefere nur neue Delta-Kandidaten.

Dieser Lauf ist in der aktuellen Projektphase ein Aufbau-/Backfill-orientierter Produktionslauf:
- Ziel ist möglichst vollständige Abdeckung der starken Quellen- und Eventcluster im konfigurierten Suchzeitraum.
- Du musst die definierten Quellencluster systematisch prüfen, bevor du den Lauf abschließt.
- Du darfst und sollst mehr als 12 Kandidaten liefern, wenn mehr als 12 starke FINAL-Kandidaten vorhanden sind.
- Keine künstliche Auffüllung mit schwachen Treffern.
- Wenn eine Quelle viele echte starke Instanzen liefert, dürfen mehrere Instanzen ausgegeben werden.
- Wenn eine Quelle nur schwache oder bereits bekannte Treffer liefert, gib daraus nichts aus.

Im Automationsmodus gilt für Events:
- nur FINAL
- kein REVIEW
- kein Fließtext außerhalb des JSON
- keine Kommentare außerhalb des JSON

Zusätzlich sammelst du neue Quellen separat als source_candidates.
source_candidates sind keine Events und dürfen niemals in die Event-Inbox gemischt werden.

Wichtig:
- Nicht jeder formal korrekte Termin ist FINAL-tauglich.
- FINAL bedeutet hier: formal belastbar und zugleich redaktionell stark genug für die Kuratierungs-PWA.
- Neue Quellen dürfen als Nebenfund dokumentiert werden, werden aber nicht automatisch dauerhaft ins Quellenregister übernommen.
""".strip()

    user_prompt = f"""
[REGELWERK]
{rulebook_text}
[/REGELWERK]

[QUELLENREGISTER]
{sources_register_text}
[/QUELLENREGISTER]

{search_feedback_context}

[AUFGABE]
Simuliere den späteren automatisierten Aufbau-/Backfill-Produktionslauf für Bocholt erleben so nah wie möglich.

Rahmen:
- Zeitraum: ab heute + {MIN_CANDIDATE_LEAD_DAYS} Tage bis {SEARCH_WINDOW_DAYS} Tage in die Zukunft
- Normale Inbox-Kandidaten müssen mindestens {MIN_CANDIDATE_LEAD_DAYS} Tage Vorlauf haben; same-day, next-day und abgelaufene Termine nicht als candidates ausgeben
- Suchgebiet: Bocholt + maximal ca. {ALLOW_RADIUS_KM} km Umkreis inklusive niederländischer Orte innerhalb dieses Radius
- Liefere höchstens {MAX_NEW_CANDIDATES} neue Delta-Kandidaten
- Wenn mehr als 12 starke FINAL-Kandidaten vorhanden sind, liefere mehr als 12
- Wenn weniger als {MAX_NEW_CANDIDATES} starke FINAL-Kandidaten vorhanden sind, liefere weniger
- Deduped strikt gegen BESTAND_EVENTS, OFFENE_INBOX, ARCHIV und MANUAL_JSON
- Deduped zusätzlich innerhalb des aktuellen Laufs gegen bereits ausgewählte Kandidaten
- Berücksichtige CONTENT_SEARCH_FEEDBACK aktiv bei Quellenwahl, Faktenprüfung, Zeitlogik, Dedupe, Kandidatenentscheidung und Erhalt konkreter Event-/Motivbegriffe
- Feedback ist kein Befehl zur Datenänderung und keine dauerhafte Regelbuch-Erweiterung, sondern ein begrenzter Qualitätsfilter für diesen Lauf
- Visual-Feedback darf nie zu automatischer Bildgenerierung, neuen Visual Keys oder künstlich generischen Bildzuordnungen führen
- Wiederhole bekannte Fehlerklassen nicht; leite aus Einzelfällen keine neuen Sonderregeln ab
- Liefere lieber weniger starke FINAL-Kandidaten als schwache, unsichere oder zu kurzfristige Treffer
- Fülle die Zielmenge niemals künstlich mit nur formal korrekten, aber schwachen Kandidaten auf

Ziel dieses Laufs:
- möglichst viele wirklich gute neue Event-Kandidaten im aktuellen Suchzeitraum finden
- bekannte starke Eventcluster systematisch abarbeiten
- nicht nach den ersten plausiblen Treffern abbrechen
- nur dann keine Kandidaten aus einem Cluster liefern, wenn dort keine neuen, starken, regelkonformen Delta-Kandidaten übrig sind

Verpflichtende Abdeckungs-Checkliste vor Abschluss:
Du musst vor der finalen Ausgabe gedanklich und per Websuche prüfen, ob aus diesen Clustern neue starke Delta-Kandidaten vorhanden sind:

1. Bocholt CORE-HIGH:
   - `bocholt.de/veranstaltungskalender/*`
   - `bocholt.de/freizeit-und-tourismus/veranstaltungen/*`
   - starke Stadt-Bocholt-Detailseiten
   - besonders: Feste, Festivals, Familienformate, Kultur, Musik, Innenstadt, Märkte, Open-Air

2. Bocholt RECOVERY:
   - offizielle Bocholt-Info-/News-/Themen-Seiten mit klarem Eventfokus
   - bekannte starke Recovery-Muster wie Kulturtage, Interkulturelle Woche, Weinfest, Aasee, Kirmes, Lichtersonntag, Weihnachtsmarkt, Innenstadt- und Familienformate
   - diese Recovery-URLs explizit prüfen:
     - `https://www.bocholt.de/kulturtage`
     - `https://www.bocholt.de/Interkulturellewoche`
     - `https://www.bocholt.de/freizeit-und-tourismus/veranstaltungen/weinfest`
     - `https://www.bocholt.de/veranstaltungskalender/aasee-festival-1`
     - `https://www.bocholt.de/veranstaltungskalender/kirmes-2026`
     - `https://www.bocholt.de/veranstaltungskalender/lichtersonntag-2026`
     - `https://www.bocholt.de/veranstaltungskalender/bocholter-weihnachtsmarkt-2026`
   - nur übernehmen, wenn konkreter Eventblock mit Titel, Datum, Ort und Besucherfokus belastbar ist

   Ergänzend Bocholt Familien-/Jugend-RECOVERY gezielt prüfen:
   - `jugendfarm-mitdir.de/*`
   - `unser-ferienprogramm.de/bocholt/*`
   - `kinderschutzbund-bocholt.de/*`
   - `juboh.de/*`
   - `bocholt.de/*kulturrucksack*`
   - `bocholt.de/*stadtbibliothek*`
   - `jusina.de/*`
   - `cafe-karton.de/*`
   - `fabi-bocholt.de/*` nur für öffentliche Sondertermine, nicht als Kurs-Komplettquelle
   - nur starke öffentliche Einzeltermine übernehmen; keine Öffnungszeiten, Schließtage, Betreuungswochen, ausgebuchten Ferienangebote ohne offenen Besuchsanlass, internen Gruppentermine oder normalen Kursreihen

3. Rhede CORE-MID:
   - `rhede.de/regional/veranstaltungen/detail-*`
   - starke öffentliche Stadt-/Kultur-/Innenstadttermine
   - kleine Führungen, Standardmärkte und nischige Einzeltermine nur bei klarer öffentlicher Relevanz

4. Aalten / NL-Nahraum:
   - `aaltendagen.nl/*`
   - alle belegbaren AaltenDagen 2026 im Suchzeitraum aktiv prüfen
   - AaltenDag-Termine als getrennte Tagesinstanzen ausgeben, wenn Datum/Instanz getrennt sind
   - bei AaltenDag keine einzelne Tagesblock-Zeit als allgemeine Startzeit verwenden, wenn mehrere Tagesblöcke genannt sind; dann `time` leer lassen

5. Borken / FARB / Jubiläums-/Kulturquellen:
   - `farb.borken.de`
   - `800.borken.de`
   - nur starke öffentliche Formate übernehmen
   - Ausstellungen, Vorschau- und Museumsseiten streng filtern

6. Bredevoort / Koppelkerk:
   - `koppelkerk.nl/agenda/*`
   - `koppelkerk.nl/evenementen/*`
   - alle belegbaren Koppelkerk-Bücherbörsen im Suchzeitraum aktiv prüfen:
     - Internationale Pinksterboekenmarkt
     - Internationale Zomerboekenmarkt
     - Internationale Augustusboekenmarkt
     - Internationale Herfstboekenmarkt
   - Bücherbörsen und Märkte bevorzugt als `Märkte & Feste` kategorisieren
   - kleine Buchvorstellungen, Spezialkonzerte oder Nischenformate nur bei erkennbarem Breiteninteresse

7. Isselburg / Hamminkeln / Randgebiet:
   - offizielle kommunale Veranstaltungsseiten
   - nur bei klarer Nähe, starkem Eventcharakter und sicherer Instanz-URL übernehmen
   - keine falschen Datums-/Serien-URLs übernehmen

8. Saisonale starke Einzelseiten aus dem Quellenregister:
   - `wijnfeest-aalten.nl/*`
   - `countryfair.de/*`
   - `bredevoortschittert.nl/*`
   - `cityart-bocholt.de/*`
   - `oldtimertreffenaalten.nl/*`
   - `grensmarktdinxperlo.com/*`
   - nur konkrete Jahresinstanz übernehmen
   - bei unsicherem Jahr, unklarer Aktualität oder fehlender Detailbasis nicht als FINAL ausgeben

9. Kontrollierte offene Suche:
   - Suche ergänzend nach starken Eventmustern im Suchgebiet, z. B. Festival, Markt, Open Air, Stadtfest, Familienfest, Kultur, Konzert, Theater, Ausstellung mit Eventcharakter
   - Wenn dabei eine neue gute Quelle auftaucht, dokumentiere sie nur in source_candidates
   - Neue Quelle nur als Eventquelle nutzen, wenn sie regelkonform, erreichbar, instanzpassend und nicht gesperrt ist

Pflichtprüfung bekannter Highlight-Cluster:
Vor der finalen Ausgabe aktiv prüfen, ob diese starken Kandidaten vorhanden, bereits entschieden oder nicht FINAL-sicher sind:
- Bocholter Weinfest
- Bocholter Aasee-Festival
- 2. Bocholter CSD
- Bocholter Kulturtage
- OPEN AIR am Marktplatz
- Weltkindertagsfest / Eröffnung Interkulturelle Woche
- Bocholter Kirmes
- Lichtersonntag
- Bocholter Weihnachtsmarkt
- City Food Festival
- WattExtra Open Air am Bahia
- CityArt Bocholt
- Farm & Country Fair
- Bredevoort Leuchtet
- alle AaltenDagen 2026 im Zeitraum
- alle Koppelkerk-Bücherbörsen 2026 im Zeitraum
- starke neue Stadt-Bocholt-Kultur-/Familien-/Open-Air-Termine

Wenn einer dieser Kandidaten nicht in candidates erscheint, darf das nur einen dieser Gründe haben:
- bereits in BESTAND_EVENTS vorhanden
- bereits in OFFENE_INBOX vorhanden
- bereits in ARCHIV entschieden
- bereits in MANUAL_JSON vorhanden
- außerhalb Zeitraum oder zu kurzfristig für manuelle Wochenprüfung
- nicht FINAL-sicher belegbar
- wegen Regelwerk ausgeschlossen

Konkrete Einzeltermine vor Sammelklammern:
- Wenn eine Quelle eine Themenwoche oder Reihe enthält, bevorzuge konkrete starke Einzeltermine.
- Bei `Interkulturelle Woche` nicht automatisch die ganze Woche als Event ausgeben.
- Wenn der konkrete Einzeltermin `Weltkindertagsfest / Eröffnung Interkulturelle Woche` mit Datum, Uhrzeit und Ort belegbar ist, gib diesen Einzeltermin aus.
- Die ganze Interkulturelle Woche nur ausgeben, wenn kein konkreter starker Einzeltermin sauber extrahierbar ist.

Keine Routine-Fülltreffer:
- Regelmäßig wiederkehrende Standardmärkte, Krammärkte, Wochenmärkte, Routine-Führungen, Standardkurse und reine Serienformate NICHT massenhaft als FINAL ausgeben.
- Solche Formate nur aufnehmen, wenn sie einen klar besonderen Eventcharakter, außergewöhnliche öffentliche Relevanz oder deutlichen PWA-Nutzen haben.
- Mehrere normale Krammarkt-Termine aus derselben generischen Quelle nicht als Backfill-Füllmasse ausgeben.
- Wenn starke Highlight-Kandidaten verfügbar sind, haben sie Vorrang vor Routine-/Serienterminen.

Harte Prüfregeln vor FINAL:
- Ein FINAL-Eintrag repräsentiert entweder genau eine einzelne besuchbare Termin-Instanz oder genau ein zusammenhängendes Mehrtagesevent.
- Zusammenhängende Mehrtagesevents bleiben ein Eintrag mit `date` und `endDate`, wenn die Quelle sie klar als ein zusammenhängendes Event darstellt.
- Unterschiedliche tägliche Zeiten oder Öffnungszeiten erzwingen bei zusammenhängenden Mehrtagesevents nicht automatisch ein Splitting.
- Splitten nur dann, wenn Tage, Slots oder Programmpunkte als eigenständige separat besuchbare Instanzen zu verstehen sind.
- `time` ist ausschließlich eine Startzeit im Format `HH:MM`.
- Niemals Zeiträume wie `16:00–23:59`, `13:00-20:00` oder Öffnungszeitspannen in `time` ausgeben.
- Wenn ein Event als zusammenhängendes Mehrtagesevent bestehen bleibt und pro Tag unterschiedliche Zeiten nennt, muss `time` leer bleiben.
- Wenn ein Event als zusammenhängendes Mehrtagesevent eine wirklich einheitliche Startzeit für das gesamte Event nennt, darf `time` als `HH:MM` gesetzt werden.
- Niemals die erste sichtbare Tageszeit oder irgendeine Einzelzeit als allgemeine `time` für ein zusammenhängendes Mehrtagesevent übernehmen.
- Wenn die URL erkennbar nicht zur gewählten Instanz passt, ist FINAL unzulässig.
- Wenn eine Unterlocation nicht 100% sicher belegt ist, auf die sicher belegte Hauptlocation zurückfallen.
- Offizielle event-spezifische Info-/News-Seiten bleiben FINAL-fähig, wenn sie klar auch für Besucher gedacht sind und Titel, Datum, Ort, Eventcharakter sauber tragen.
- Zusätzliche Organisations-, Anmelde-, Aussteller- oder CTA-Elemente machen eine offizielle event-spezifische Seite nicht automatisch ungeeignet.
- Vorschau-/Teaser-/Save-the-date-Seiten nur dann FINAL, wenn Titel, Datum, Ort und Besucherfokus im gleichen klaren sichtbaren Kernblock belastbar sind.
- Wenn Scope unklar ist: nicht ausgeben.
- Wenn Pflichtfelder nicht 100% sicher sind: nicht ausgeben.
- Wenn eine Quelle vor allem eine monetarisierungsrelevante Venue promotet und kein neutraler/öffentlicher Drittquellen-Fall vorliegt: nicht ausgeben.

Redaktionelle Qualitäts-Schwelle für FINAL:
Ein Kandidat ist nur dann FINAL-tauglich, wenn er nicht nur formal korrekt ist, sondern auch einen klaren Mehrwert für die Kuratierungs-PWA hat.

Nicht ausreichend für FINAL ist ein Termin, wenn er hauptsächlich:
- formal korrekt, aber für normale Nutzer nur schwach interessant ist
- sehr speziell, nischig oder kleinteilig wirkt
- eher wie ein interner, edukativer oder halbgeschlossener Spezialtermin wirkt
- eher wie eine Vorschau-, Programm- oder Hintergrundseite ohne starken Event-Zug wirkt
- eher eine Dauer-/Ausstellungspräsenz als ein wirklich starker Event-Kandidat ist
- eher eine kleine Kurs-, Workshop-, Resilienz-, Seminar- oder Mitmach-Einheit mit begrenztem Breiteninteresse ist
- eher eine Innenstadt-/Marketing-/Shopping-Aktion ohne klar starken Eventcharakter ist

Besonders streng behandeln:
- länger laufende Ausstellungen
- Vorschau-/Museumseinträge
- Kurse
- Workshops
- Resilienz-, Bildungs- oder Spezialformate
- kleine Ferienprogramme
- Innenstadtaktionen
- Handels- und Promotionsformate

Solche Fälle nur dann FINAL, wenn zusätzlich mindestens einer dieser Punkte klar erfüllt ist:
- hohe öffentliche Relevanz für Bocholt oder den Nahraum
- klarer Eventcharakter mit starkem Besuchsanlass
- außergewöhnlich hoher PWA-Nutzen
- deutliche Breitenattraktivität
- besondere lokale Relevanz oder Stadtbedeutung
- starkes öffentliches Programm statt bloßer Hintergrund-/Informationsseite

Bevorzugt in FINAL:
- Stadtfeste
- Märkte
- Festivals
- Konzerte
- Theater / Bühne
- familienrelevante Großtermine
- Innenstadtfeste mit klarem Programm
- Messen mit klarer Besucherrelevanz
- Open-Air-Events
- besondere Aktionstage / Tage der offenen Tür / öffentlich starke Kulturtermine
- klar sichtbare Highlights mit Bocholt- oder Nahraum-Relevanz

Priorisierung:
Priorisiere FINAL-Kandidaten nach:
1. öffentlicher Relevanz
2. Bocholt-Nähe
3. Breiteninteresse
4. PWA-Nutzen
5. Faktenvollständigkeit / Quellensauberkeit

Wenn ein Kandidat zwar formal korrekt, aber redaktionell schwach ist:
- nicht ausgeben

Output-Vertrag:
Gib ausschließlich ein valides JSON-Objekt mit genau diesen zwei Schlüsseln zurück:

{{
  "candidates": [],
  "source_candidates": []
}}

Jeder Eintrag in candidates MUSS exakt diese Felder enthalten:
- title
- date
- endDate
- time
- city
- location
- kategorie_suggestion
- url
- description
- source_name
- source_url
- notes
- visual_key
- visual_motif

Wenn ein Wert nicht vorhanden ist, setze ihn als leeren String "".
Lasse niemals ein Pflichtfeld weg.

Beispiele:
- Ein-Tages-Event ohne Enddatum: "endDate": ""
- Event ohne sichere Startzeit: "time": ""
- Mehrtagesevent ohne einheitliche Startzeit: "time": ""

Jeder Eintrag in source_candidates MUSS exakt diese Felder enthalten:
- domain
- source_name
- source_url_pattern
- example_url
- suggested_status
- quality_signal
- risk_signal
- reason
- usage_rule
- evidence_events
- notes

Jeder Eintrag in evidence_events MUSS exakt diese Felder enthalten:
- title
- date
- url

Wenn ein Wert nicht vorhanden ist, setze ihn als leeren String "".
Lasse niemals ein Pflichtfeld weg.

JSON-Strenge:
- visual_key und visual_motif sind nur Vorschläge aus bestehenden Taxonomiebegriffen; wenn unsicher, leer lassen. Lokale Regeln validieren und überschreiben die endgültige Zuordnung.
- Keine neuen visual_key-Namen erfinden. Keine Bildgenerierung, keine Bildbeschreibung als Asset-Ersatz.
- Gib ausschließlich valides JSON nach RFC 8259 zurück.
- Keine Markdown-Codeblöcke.
- Keine Erklärung vor oder nach dem JSON.
- Keine Kommentare.
- Keine Tabellen.
- Keine Zeilenumbrüche innerhalb von Stringwerten.
- Keine sichtbaren Umbrüche in description, notes, url oder source_url.
- URLs müssen getrimmt sein: keine Leerzeichen, keine Zeilenumbrüche.
- url und source_url müssen normale HTML-Landingpages oder konkrete Event-/Veranstalterseiten sein. Keine PDF-/Office-Dateien, keine direkten Downloads, keine Links mit download=1, keine Bild-/Mediendateien als primaere Eventquelle.
- Wenn nur ein PDF/Download gefunden wird und keine HTML-Landingpage existiert, gib den Kandidaten nicht aus.
- Anführungszeichen innerhalb von Texten müssen korrekt escaped werden.
- Jeder String muss gültig JSON-escaped sein.
- Das Ergebnis muss direkt mit JSON.parse parsebar sein.
- Die Antwort muss mit `{{` beginnen und mit `}}` enden.

Zusätzliche interne Pflichtprüfung vor Event-Aufnahme:
- echtes Event?
- belastbare Quelle?
- event-spezifische URL oder zulässige offizielle Ausnahme?
- ist die URL wirklich instanzpassend?
- Datum sicher?
- Ort sicher?
- ist die Ortsgranularität wirklich sicher?
- Zeit nur wenn eindeutig?
- gilt die Zeit wirklich für das gesamte Event?
- falls Mehrtagesevent mit unterschiedlichen Tageszeiten: muss `time` leer bleiben?
- repräsentiert der Eintrag genau eine besuchbare Instanz?
- Mehrtageslogik korrekt?
- Beschreibung neutral, sachlich und quellenbasiert?
- keine Dublette gegen BESTAND_EVENTS, OFFENE_INBOX, ARCHIV und MANUAL_JSON?
- keine Dublette innerhalb des aktuellen Outputs?
- wirklich nützlich für die Kuratierungs-PWA?
- ist der Kandidat nicht nur korrekt, sondern auch stark genug?
- gibt es noch eine bessere offizielle Detailseite oder kanonischere URL?

Zusätzliche interne Pflichtprüfung vor Quellenvorschlag:
- Quelle noch nicht im Quellenregister enthalten?
- Quelle belegt mindestens ein starkes oder fast starkes Event im Suchgebiet?
- Quelle ist nicht Social-only?
- Quelle ist nicht Ticketshop-only ohne offizielle Einordnung?
- Quelle ist keine direkte Venue-Promo einer geschützten oder monetarisierungsrelevanten Location?
- Quelle ist nicht EXCLUDE / GESPERRT?
- Quelle hat eine plausible Einsatzregel für spätere Registerpflege?

[KONTEXT_BUNDLE]
{context_bundle}
[/KONTEXT_BUNDLE]
""".strip()

    return [
        {"role": "system", "content": system_prompt},
        {"role": "user", "content": user_prompt},
    ]
# === END BLOCK: WEEKLY_PRODUCTION_PROMPT_WITH_SYSTEMATIC_BACKFILL_COVERAGE_V3 ===
# === END BLOCK: PROMPT BUNDLE BUILDERS ===


# === BEGIN BLOCK: OPENAI SEARCH CALL ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Führt die eigentliche KI-Suche über die Responses API aus
# - Nutzt das Web-Search-Tool direkt im Modelllauf
# Umfang:
# - Nur der Modellaufruf + Rohantwort-Extraktion
# === END BLOCK: OPENAI SEARCH CALL ===
# === BEGIN BLOCK: OPENAI SEARCH CALL WITH SOURCE CANDIDATES V1 | Zweck: Responses-Output um separaten source_candidates-Kanal erweitern | Umfang: candidates bleiben Event-Importdaten, source_candidates nur Quellen-Merkliste ===
def search_with_openai(messages: List[Dict[str, str]]) -> tuple[list[dict[str, Any]], list[dict[str, Any]], Any]:
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
                                    "visual_key": {"type": "string"},
                                    "visual_motif": {"type": "string"},
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
                                    "visual_key",
                                    "visual_motif",
                                ],
                            },
                        },
                        "source_candidates": {
                            "type": "array",
                            "items": {
                                "type": "object",
                                "additionalProperties": False,
                                "properties": {
                                    "domain": {"type": "string"},
                                    "source_name": {"type": "string"},
                                    "source_url_pattern": {"type": "string"},
                                    "example_url": {"type": "string"},
                                    "suggested_status": {"type": "string"},
                                    "quality_signal": {"type": "string"},
                                    "risk_signal": {"type": "string"},
                                    "reason": {"type": "string"},
                                    "usage_rule": {"type": "string"},
                                    "evidence_events": {
                                        "type": "array",
                                        "items": {
                                            "type": "object",
                                            "additionalProperties": False,
                                            "properties": {
                                                "title": {"type": "string"},
                                                "date": {"type": "string"},
                                                "url": {"type": "string"},
                                            },
                                            "required": ["title", "date", "url"],
                                        },
                                    },
                                    "notes": {"type": "string"},
                                },
                                "required": [
                                    "domain",
                                    "source_name",
                                    "source_url_pattern",
                                    "example_url",
                                    "suggested_status",
                                    "quality_signal",
                                    "risk_signal",
                                    "reason",
                                    "usage_rule",
                                    "evidence_events",
                                    "notes",
                                ],
                            },
                        },
                    },
                    "required": ["candidates", "source_candidates"],
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

    raw_source_candidates = payload.get("source_candidates", []) if isinstance(payload, dict) else []
    if not isinstance(raw_source_candidates, list):
        fail("OpenAI-Antwort enthält kein gültiges source_candidates-Array.")

    return raw_candidates, raw_source_candidates, response
# === END BLOCK: OPENAI SEARCH CALL WITH SOURCE CANDIDATES V1 ===


# === BEGIN BLOCK: LOCAL POST-VALIDATION + DEDUPE ===
# Datei: scripts/weekly-ki-websearch-to-manual-inbox.py
# Zweck:
# - Validiert Pflichtfelder, Datumsformat und Kategorien lokal nach
# - Fängt Rest-Dubletten gegen aktuelle Referenzdateien ab
# Umfang:
# - Nur Sicherungsnetz hinter der KI-Suche
# === END BLOCK: LOCAL POST-VALIDATION + DEDUPE ===
# === BEGIN BLOCK: LOCAL_POST_VALIDATION_DEDUPE_DIAGNOSTICS_V1 | Zweck: Pflichtfelder, Einzeilen-Strings, URL-Trim, Zeitformat, interne Dedupe und Drop-Gründe technisch diagnostizieren | Umfang: ersetzt lokale Post-Validation + Dedupe komplett ===
CANDIDATE_OUTPUT_FIELDS = [
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
    "visual_key",
    "visual_motif",
    "visual_asset_status",
]


CANDIDATE_REQUIRED_NON_EMPTY_FIELDS = [
    "title",
    "date",
    "city",
    "location",
    "kategorie_suggestion",
    "url",
    "description",
    "source_name",
    "source_url",
    "notes",
]


def occurrence_fp(title: str, date_value: str, time_value: str, location: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
        norm(time_value),
        norm_key(location),
    ])


def occurrence_fp_no_time(title: str, date_value: str, location: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
        "",
        norm_key(location),
    ])


def title_date_fp(title: str, date_value: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
    ])


def source_date_fp(source_url: str, url: str, date_value: str) -> str:
    source_key = norm_key(canonical_url(source_url) or canonical_url(url))
    if not source_key:
        return ""
    return "|".join([
        source_key,
        norm(date_value),
    ])


def source_occurrence_fp(source_url: str, url: str, title: str, date_value: str, time_value: str, location: str) -> str:
    source_key = norm_key(canonical_url(source_url) or canonical_url(url))
    if not source_key:
        return ""

    return "|".join([
        source_key,
        norm_key(title),
        norm(date_value),
        norm(time_value),
        norm_key(location),
    ])


def existing_sets(*groups: List[RefRecord]) -> tuple[set[str], set[str], set[str], set[str], set[str]]:
    source_occurrences: set[str] = set()
    exact_occurrences: set[str] = set()
    no_time_occurrences_all: set[str] = set()
    no_time_occurrences_missing_time: set[str] = set()
    title_dates: set[str] = set()

    for group in groups:
        for rec in group:
            fp = occurrence_fp(rec.title, rec.date, rec.time, rec.location)
            fp_no_time = occurrence_fp_no_time(rec.title, rec.date, rec.location)
            src_fp = source_occurrence_fp(rec.source_url, rec.url, rec.title, rec.date, rec.time, rec.location)
            title_date = title_date_fp(rec.title, rec.date)

            if src_fp and src_fp != "|||||":
                source_occurrences.add(src_fp)
            if fp != "|||":
                exact_occurrences.add(fp)
            if fp_no_time != "|||":
                no_time_occurrences_all.add(fp_no_time)
            if not norm(rec.time) and fp_no_time != "|||":
                no_time_occurrences_missing_time.add(fp_no_time)
            if title_date != "|":
                title_dates.add(title_date)

    return source_occurrences, exact_occurrences, no_time_occurrences_all, no_time_occurrences_missing_time, title_dates


def normalize_candidate(item: Dict[str, Any]) -> Dict[str, str]:
    out: Dict[str, str] = {}

    for field in CANDIDATE_OUTPUT_FIELDS:
        if field in {"url", "source_url"}:
            out[field] = canonical_url(item.get(field, ""))
        else:
            out[field] = clean_output_text(item.get(field, ""))

    out["url"] = canonical_url(out.get("url", "")) or canonical_url(out.get("source_url", ""))
    out["source_url"] = canonical_url(out.get("source_url", "")) or out["url"]

    if out.get("kategorie_suggestion", "") not in ALLOWED_CATEGORIES:
        out["kategorie_suggestion"] = "Sonstiges"

    if not out.get("notes"):
        out["notes"] = "manual chat search v3"

    # Visual-Zuordnung: Modellwerte sind nur Hinweise. Die lokale,
    # deterministische Visual-Regel bleibt fuehrend, damit Feedback nicht zu
    # Taxonomie-Wildwuchs oder zufaelligen KI-Bildzuordnungen fuehrt.
    local_visual_key = infer_event_visual_key(
        title=out.get("title", ""),
        description=out.get("description", ""),
        category=out.get("kategorie_suggestion", ""),
        location=out.get("location", ""),
    )
    model_visual_key = normalize_event_visual_key(out.get("visual_key", ""))
    visual_key_for_fit = local_visual_key or model_visual_key
    model_visual_motif = out.get("visual_motif", "") if (not model_visual_key or model_visual_key == visual_key_for_fit) else ""
    fit = infer_event_visual_fit(
        title=out.get("title", ""),
        description=out.get("description", ""),
        category=out.get("kategorie_suggestion", ""),
        location=out.get("location", ""),
        visual_key=visual_key_for_fit,
        visual_motif=model_visual_motif,
    )
    out["visual_key"] = fit.get("visual_key", "") or visual_key_for_fit
    out["visual_motif"] = fit.get("visual_motif", "")
    out["visual_asset_status"] = fit.get("visual_asset_status", "")

    return {field: out.get(field, "") for field in CANDIDATE_OUTPUT_FIELDS}


def candidate_in_window(item: Dict[str, str], start: date, end: date) -> bool:
    start_date = parse_iso_date(item.get("date", ""))
    if not start_date:
        return False

    end_date = parse_iso_date(item.get("endDate", "")) if item.get("endDate") else start_date
    if not end_date:
        return False

    if end_date < start_date:
        return False

    return start_date <= end and end_date >= start


def candidate_invalid_reason(item: Dict[str, str], start: date, end: date) -> str:
    missing_fields = [field for field in CANDIDATE_OUTPUT_FIELDS if field not in item]
    if missing_fields:
        return "missing_output_field:" + ",".join(missing_fields)

    missing_required = [field for field in CANDIDATE_REQUIRED_NON_EMPTY_FIELDS if not norm(item.get(field, ""))]
    if missing_required:
        return "missing_required:" + ",".join(missing_required)

    if is_download_document_url(item.get("source_url", "")):
        return "source_url_download_document"
    if is_download_document_url(item.get("url", "")):
        return "url_download_document"

    if not parse_iso_date(item.get("date", "")):
        return "invalid_date"
    if item.get("endDate") and not parse_iso_date(item.get("endDate", "")):
        return "invalid_endDate"
    if item.get("endDate"):
        start_date = parse_iso_date(item.get("date", ""))
        end_date = parse_iso_date(item.get("endDate", ""))
        if start_date and end_date and end_date < start_date:
            return "endDate_before_date"
    if item.get("time") and not re.match(r"^\d{2}:\d{2}$", item["time"]):
        return "bad_time"
    start_date = parse_iso_date(item.get("date", ""))
    end_date = parse_iso_date(item.get("endDate", "")) if item.get("endDate") else start_date
    if end_date and end_date < start:
        return "past_event"
    if start_date and start_date < start + timedelta(days=MIN_CANDIDATE_LEAD_DAYS):
        return "too_short_notice"
    if not candidate_in_window(item, start, end):
        return "out_of_window"

    return ""


def valid_candidate(item: Dict[str, str], start: date, end: date) -> bool:
    return candidate_invalid_reason(item, start, end) == ""


def candidate_diag_summary(item: Dict[str, Any], reason: str, stage: str) -> Dict[str, str]:
    normalized = normalize_candidate(item) if isinstance(item, dict) else {field: "" for field in CANDIDATE_OUTPUT_FIELDS}
    return {
        "stage": stage,
        "reason": reason,
        "title": clean_output_text(normalized.get("title", "")),
        "date": clean_output_text(normalized.get("date", "")),
        "endDate": clean_output_text(normalized.get("endDate", "")),
        "time": clean_output_text(normalized.get("time", "")),
        "city": clean_output_text(normalized.get("city", "")),
        "location": clean_output_text(normalized.get("location", "")),
        "url": canonical_url(normalized.get("url", "")),
        "source_url": canonical_url(normalized.get("source_url", "")),
    }


def filter_delta_with_diagnostics(
    raw_candidates: List[Dict[str, Any]],
    events_records: List[RefRecord],
    inbox_records: List[RefRecord],
    archive_records: List[RefRecord],
    manual_records: List[RefRecord],
) -> tuple[List[Dict[str, str]], List[Dict[str, str]]]:
    start = datetime.now().date()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    existing_source_occurrences, existing_exact, existing_no_time_all, existing_no_time_missing, existing_title_dates = existing_sets(
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )

    batch_source_occurrences: set[str] = set()
    batch_source_dates: set[str] = set()
    batch_exact: set[str] = set()
    batch_no_time_all: set[str] = set()
    batch_no_time_missing: set[str] = set()
    batch_title_dates: set[str] = set()

    out: List[Dict[str, str]] = []
    diagnostics: List[Dict[str, str]] = []

    for raw in raw_candidates:
        if not isinstance(raw, dict):
            diagnostics.append(candidate_diag_summary({}, "non_object_raw_candidate", "validation"))
            continue

        item = normalize_candidate(raw)
        invalid_reason = candidate_invalid_reason(item, start, end)
        if invalid_reason:
            diagnostics.append(candidate_diag_summary(item, invalid_reason, "validation"))
            continue

        title = item.get("title", "")
        date_value = item.get("date", "")
        time_value = item.get("time", "")
        location = item.get("location", "")
        has_time = bool(norm(time_value))

        fp = occurrence_fp(title, date_value, time_value, location)
        fp_no_time = occurrence_fp_no_time(title, date_value, location)
        title_date = title_date_fp(title, date_value)
        src_date = source_date_fp(item.get("source_url", ""), item.get("url", ""), date_value)
        src_fp = source_occurrence_fp(
            item.get("source_url", ""),
            item.get("url", ""),
            title,
            date_value,
            time_value,
            location,
        )

        drop_reason = ""
        if title_date in existing_title_dates:
            drop_reason = "existing_title_date"
        elif title_date in batch_title_dates:
            drop_reason = "batch_title_date"
        elif src_date and src_date in batch_source_dates:
            drop_reason = "batch_source_date"
        elif src_fp and src_fp in existing_source_occurrences:
            drop_reason = "existing_source_occurrence"
        elif src_fp and src_fp in batch_source_occurrences:
            drop_reason = "batch_source_occurrence"
        elif has_time and fp in existing_exact:
            drop_reason = "existing_exact_occurrence"
        elif has_time and fp in batch_exact:
            drop_reason = "batch_exact_occurrence"
        elif has_time and fp_no_time in existing_no_time_missing:
            drop_reason = "existing_no_time_missing_occurrence"
        elif has_time and fp_no_time in batch_no_time_missing:
            drop_reason = "batch_no_time_missing_occurrence"
        elif not has_time and fp_no_time in existing_no_time_all:
            drop_reason = "existing_no_time_occurrence"
        elif not has_time and fp_no_time in batch_no_time_all:
            drop_reason = "batch_no_time_occurrence"

        if drop_reason:
            diagnostics.append(candidate_diag_summary(item, drop_reason, "dedupe"))
            continue

        selected = {field: item.get(field, "") for field in CANDIDATE_OUTPUT_FIELDS}
        out.append(selected)
        diagnostics.append(candidate_diag_summary(selected, "selected", "selected"))

        if src_fp:
            batch_source_occurrences.add(src_fp)
        if src_date:
            batch_source_dates.add(src_date)
        batch_exact.add(fp)
        batch_no_time_all.add(fp_no_time)
        batch_title_dates.add(title_date)
        if not has_time:
            batch_no_time_missing.add(fp_no_time)

    return out, diagnostics


def filter_delta(
    raw_candidates: List[Dict[str, Any]],
    events_records: List[RefRecord],
    inbox_records: List[RefRecord],
    archive_records: List[RefRecord],
    manual_records: List[RefRecord],
) -> List[Dict[str, str]]:
    selected, _diagnostics = filter_delta_with_diagnostics(
        raw_candidates,
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )
    return selected
# === END BLOCK: LOCAL_POST_VALIDATION_DEDUPE_DIAGNOSTICS_V1 ===

# === BEGIN BLOCK: SOURCE_CANDIDATE_LEARNING_DIAGNOSTICS_V2 | Zweck: neue Quellenkandidaten separat sammeln und Annahme-/Ablehnungsgründe diagnostizieren | Umfang: ersetzt Source-Candidate-Normalisierung und Merge vollständig ===
def source_domain(raw_url: str) -> str:
    raw = norm(raw_url)
    if not raw:
        return ""

    try:
        parsed = urlparse(raw)
        if not parsed.netloc and not raw.startswith(("http://", "https://")):
            parsed = urlparse("https://" + raw)
    except Exception:
        return ""

    netloc = parsed.netloc.lower().strip()
    if netloc.startswith("www."):
        netloc = netloc[4:]
    return netloc


def source_key_from_item(item: Dict[str, Any]) -> str:
    domain = norm_key(item.get("domain", "")) or source_domain(str(item.get("example_url", "")))
    pattern = norm_key(item.get("source_url_pattern", ""))
    return "|".join([domain, pattern])


def source_known_in_register(item: Dict[str, Any], sources_register_text: str) -> bool:
    register = norm_key(sources_register_text)
    domain = norm_key(item.get("domain", ""))
    pattern = norm_key(item.get("source_url_pattern", ""))
    compact_pattern = pattern.replace("/*", "").strip()

    if domain and domain in register:
        return True
    if compact_pattern and compact_pattern in register:
        return True
    return False


def read_source_candidates() -> List[Dict[str, Any]]:
    if not SOURCE_CANDIDATES_JSON_PATH.exists():
        return []

    try:
        raw = json.loads(SOURCE_CANDIDATES_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/source_candidates.json ist ungültig: {exc}")

    if not isinstance(raw, list):
        fail("data/source_candidates.json muss ein JSON-Array sein.")

    return [item for item in raw if isinstance(item, dict)]


def source_candidate_diag_summary(
    raw: Dict[str, Any] | None,
    reason: str,
    stage: str,
    normalized: Dict[str, Any] | None = None,
) -> Dict[str, Any]:
    data = normalized if normalized is not None else (raw if isinstance(raw, dict) else {})
    evidence_events = data.get("evidence_events", []) if isinstance(data, dict) else []
    if not isinstance(evidence_events, list):
        evidence_events = []

    evidence_titles: List[str] = []
    for ev in evidence_events[:5]:
        if isinstance(ev, dict):
            title = clean_output_text(ev.get("title", ""))
            date_value = clean_output_text(ev.get("date", ""))
            if title or date_value:
                evidence_titles.append(f"{date_value} | {title}".strip())

    example_url = canonical_url(data.get("example_url", "")) if isinstance(data, dict) else ""
    domain = clean_output_text(data.get("domain", "")) if isinstance(data, dict) else ""
    if not domain and example_url:
        domain = source_domain(example_url)
    if domain.startswith("www."):
        domain = domain[4:]

    return {
        "stage": stage,
        "reason": reason,
        "domain": domain,
        "source_name": clean_output_text(data.get("source_name", "")) if isinstance(data, dict) else "",
        "source_url_pattern": clean_output_text(data.get("source_url_pattern", "")) if isinstance(data, dict) else "",
        "example_url": example_url,
        "suggested_status": clean_output_text(data.get("suggested_status", "")) if isinstance(data, dict) else "",
        "evidence_events_count": len(evidence_events),
        "evidence_event_titles": evidence_titles,
    }


def normalize_source_candidate_with_reason(item: Dict[str, Any], today: date) -> tuple[Dict[str, Any] | None, str]:
    if not isinstance(item, dict):
        return None, "non_object_source_candidate"

    evidence_events = item.get("evidence_events", [])
    if not isinstance(evidence_events, list):
        evidence_events = []

    clean_evidence: List[Dict[str, str]] = []
    for ev in evidence_events[:5]:
        if not isinstance(ev, dict):
            continue
        clean_evidence.append(
            {
                "title": clean_output_text(ev.get("title", "")),
                "date": clean_output_text(ev.get("date", "")),
                "url": canonical_url(ev.get("url", "")),
            }
        )

    example_url = canonical_url(item.get("example_url", ""))
    domain = clean_output_text(item.get("domain", "")) or source_domain(example_url)
    if domain.startswith("www."):
        domain = domain[4:]

    out: Dict[str, Any] = {
        "domain": domain,
        "source_name": clean_output_text(item.get("source_name", "")),
        "source_url_pattern": clean_output_text(item.get("source_url_pattern", "")) or (f"{domain}/*" if domain else ""),
        "example_url": example_url,
        "suggested_status": clean_output_text(item.get("suggested_status", "")) or "NEW-SOURCE-CANDIDATE",
        "quality_signal": clean_output_text(item.get("quality_signal", "")),
        "risk_signal": clean_output_text(item.get("risk_signal", "")),
        "reason": clean_output_text(item.get("reason", "")),
        "usage_rule": clean_output_text(item.get("usage_rule", "")),
        "evidence_events": clean_evidence,
        "first_seen": today.isoformat(),
        "last_seen": today.isoformat(),
        "found_via": "weekly-ki-websearch",
        "status": "neu",
        "notes": clean_output_text(item.get("notes", "")),
    }

    required = [
        "domain",
        "source_name",
        "source_url_pattern",
        "example_url",
        "suggested_status",
        "reason",
        "usage_rule",
    ]
    missing_required = [k for k in required if not norm(out.get(k, ""))]
    if missing_required:
        return out, "missing_required:" + ",".join(missing_required)
    if not clean_evidence:
        return out, "missing_evidence_events"

    return out, ""


def normalize_source_candidate(item: Dict[str, Any], today: date) -> Dict[str, Any] | None:
    normalized, reason = normalize_source_candidate_with_reason(item, today)
    if reason:
        return None
    return normalized


def merge_source_candidates_with_diagnostics(
    raw_source_candidates: List[Dict[str, Any]],
    sources_register_text: str,
) -> tuple[List[Dict[str, Any]], int, List[Dict[str, Any]]]:
    existing = read_source_candidates()
    today = datetime.now().date()

    by_key: Dict[str, Dict[str, Any]] = {}
    order: List[str] = []
    diagnostics: List[Dict[str, Any]] = []

    for item in existing:
        if not isinstance(item, dict):
            continue
        key = source_key_from_item(item)
        if not key.strip("|"):
            continue
        if key not in by_key:
            by_key[key] = item
            order.append(key)

    added = 0
    for raw in raw_source_candidates:
        item, reason = normalize_source_candidate_with_reason(raw, today)
        if reason:
            diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, reason, "validation", item))
            continue
        if item is None:
            diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, "normalization_failed", "validation"))
            continue

        if source_known_in_register(item, sources_register_text):
            diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, "known_in_register", "register", item))
            continue

        key = source_key_from_item(item)
        if not key.strip("|"):
            diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, "empty_source_key", "validation", item))
            continue

        if key in by_key:
            current = by_key[key]
            current["last_seen"] = today.isoformat()

            existing_evidence = current.get("evidence_events", [])
            if not isinstance(existing_evidence, list):
                existing_evidence = []

            seen_ev = {
                "|".join(
                    [
                        norm_key(ev.get("title", "")),
                        norm(ev.get("date", "")),
                        canonical_url(ev.get("url", "")),
                    ]
                )
                for ev in existing_evidence
                if isinstance(ev, dict)
            }

            for ev in item["evidence_events"]:
                ev_key = "|".join(
                    [
                        norm_key(ev.get("title", "")),
                        norm(ev.get("date", "")),
                        canonical_url(ev.get("url", "")),
                    ]
                )
                if ev_key and ev_key not in seen_ev:
                    existing_evidence.append(ev)
                    seen_ev.add(ev_key)

            current["evidence_events"] = existing_evidence[:10]
            diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, "merged_existing_candidate", "merge", item))
            continue

        by_key[key] = item
        order.append(key)
        added += 1
        diagnostics.append(source_candidate_diag_summary(raw if isinstance(raw, dict) else None, "added", "added", item))

    merged = [by_key[key] for key in order]
    return merged, added, diagnostics


def merge_source_candidates(
    raw_source_candidates: List[Dict[str, Any]],
    sources_register_text: str,
) -> tuple[List[Dict[str, Any]], int]:
    merged, added, _diagnostics = merge_source_candidates_with_diagnostics(raw_source_candidates, sources_register_text)
    return merged, added
# === END BLOCK: SOURCE_CANDIDATE_LEARNING_DIAGNOSTICS_V2 ===
# === BEGIN BLOCK: WEEKLY_DIAGNOSTICS_AND_COVERAGE_AUDIT_V1 | Zweck: Raw-/Selected-/Drop-Diagnose und passive Coverage-Targets nach dem Lauf auswerten | Umfang: ergänzt Diagnoseausgabe ohne Suchprompt zu beeinflussen ===
def count_by_key(items: List[Dict[str, Any]], key: str) -> Dict[str, int]:
    out: Dict[str, int] = {}
    for item in items:
        value = clean_output_text(item.get(key, "")) if isinstance(item, dict) else ""
        value = value or "<empty>"
        out[value] = out.get(value, 0) + 1
    return dict(sorted(out.items(), key=lambda kv: (-kv[1], kv[0])))


def candidate_title_list(items: List[Dict[str, Any]], limit: int = 40) -> List[str]:
    titles: List[str] = []
    for item in items[:limit]:
        if isinstance(item, dict):
            title = clean_output_text(item.get("title", ""))
            date_value = clean_output_text(item.get("date", ""))
            if title or date_value:
                titles.append(f"{date_value} | {title}".strip())
    return titles


def read_coverage_targets() -> List[Dict[str, Any]]:
    if not COVERAGE_TARGETS_JSON_PATH.exists():
        return []

    try:
        raw = json.loads(COVERAGE_TARGETS_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"data/event_coverage_targets.json ist ungültig: {exc}")

    if not isinstance(raw, list):
        fail("data/event_coverage_targets.json muss ein JSON-Array sein.")

    return [item for item in raw if isinstance(item, dict)]


def target_aliases(target: Dict[str, Any]) -> List[str]:
    aliases: List[str] = []
    for value in [target.get("title", ""), *(target.get("aliases", []) if isinstance(target.get("aliases", []), list) else [])]:
        cleaned = clean_output_text(value)
        if cleaned:
            aliases.append(cleaned)
    return aliases


def target_matches_title(target: Dict[str, Any], title: str) -> bool:
    title_key = norm_key(title)
    if not title_key:
        return False

    for alias in target_aliases(target):
        alias_key = norm_key(alias)
        if alias_key and (alias_key in title_key or title_key in alias_key):
            return True
    return False


def target_matches_url(target: Dict[str, Any], url_value: str) -> bool:
    hint = norm_key(target.get("source_hint", ""))
    url_key = norm_key(canonical_url(url_value))
    return bool(hint and url_key and hint in url_key)


def target_matches_candidate(target: Dict[str, Any], item: Dict[str, Any]) -> bool:
    expected_date = norm(target.get("expected_date", ""))
    item_date = norm(item.get("date", ""))
    if expected_date and item_date != expected_date:
        return False

    return (
        target_matches_title(target, clean_output_text(item.get("title", "")))
        or target_matches_url(target, item.get("url", ""))
        or target_matches_url(target, item.get("source_url", ""))
    )


def target_matches_record(target: Dict[str, Any], rec: RefRecord) -> bool:
    expected_date = norm(target.get("expected_date", ""))
    if expected_date and rec.date != expected_date:
        return False

    return (
        target_matches_title(target, rec.title)
        or target_matches_url(target, rec.url)
        or target_matches_url(target, rec.source_url)
    )


def first_matching_record_status(target: Dict[str, Any], label: str, records: List[RefRecord]) -> Dict[str, str] | None:
    for rec in records:
        if target_matches_record(target, rec):
            return {
                "status": label,
                "matched_title": rec.title,
                "matched_date": rec.date,
                "matched_url": rec.source_url or rec.url,
            }
    return None


# === BEGIN BLOCK: COVERAGE_AUDIT_WITH_WINDOW_STATUS_V2 | Zweck: Coverage-Ziele gegen Selected/Raw/Bestand prüfen und abgelaufene bzw. außerhalb liegende Targets sauber diagnostizieren | Umfang: ersetzt coverage_audit vollständig ===
def coverage_audit(
    targets: List[Dict[str, Any]],
    raw_candidates: List[Dict[str, Any]],
    selected_candidates: List[Dict[str, str]],
    drop_diagnostics: List[Dict[str, str]],
    events_records: List[RefRecord],
    inbox_records: List[RefRecord],
    archive_records: List[RefRecord],
    manual_records: List[RefRecord],
) -> List[Dict[str, str]]:
    audit: List[Dict[str, str]] = []
    today = datetime.now().date()
    window_end = today + timedelta(days=SEARCH_WINDOW_DAYS)

    for target in targets:
        target_id = clean_output_text(target.get("id", "")) or norm_key(target.get("title", ""))
        title = clean_output_text(target.get("title", ""))
        priority = clean_output_text(target.get("priority", "")) or "should"
        cluster = clean_output_text(target.get("cluster", ""))
        expected_date_value = parse_iso_date(norm(target.get("expected_date", "")))

        selected_match = next((item for item in selected_candidates if target_matches_candidate(target, item)), None)
        if selected_match:
            audit.append({
                "id": target_id,
                "title": title,
                "priority": priority,
                "cluster": cluster,
                "status": "FOUND_SELECTED",
                "matched_title": clean_output_text(selected_match.get("title", "")),
                "matched_date": clean_output_text(selected_match.get("date", "")),
                "matched_url": canonical_url(selected_match.get("source_url", "") or selected_match.get("url", "")),
                "drop_reason": "",
            })
            continue

        raw_match = next((item for item in raw_candidates if isinstance(item, dict) and target_matches_candidate(target, normalize_candidate(item))), None)
        if raw_match:
            raw_norm = normalize_candidate(raw_match)
            drop_match = next((d for d in drop_diagnostics if target_matches_candidate(target, d) and d.get("reason") != "selected"), None)
            audit.append({
                "id": target_id,
                "title": title,
                "priority": priority,
                "cluster": cluster,
                "status": "FOUND_RAW_DROPPED",
                "matched_title": clean_output_text(raw_norm.get("title", "")),
                "matched_date": clean_output_text(raw_norm.get("date", "")),
                "matched_url": canonical_url(raw_norm.get("source_url", "") or raw_norm.get("url", "")),
                "drop_reason": clean_output_text(drop_match.get("reason", "unknown_drop_reason")) if drop_match else "unknown_drop_reason",
            })
            continue

        for label, records in [
            ("IN_EVENTS", events_records),
            ("IN_INBOX", inbox_records),
            ("IN_INBOX_ARCHIVE", archive_records),
            ("IN_MANUAL_JSON", manual_records),
        ]:
            record_match = first_matching_record_status(target, label, records)
            if record_match:
                audit.append({
                    "id": target_id,
                    "title": title,
                    "priority": priority,
                    "cluster": cluster,
                    "status": label,
                    "matched_title": clean_output_text(record_match.get("matched_title", "")),
                    "matched_date": clean_output_text(record_match.get("matched_date", "")),
                    "matched_url": canonical_url(record_match.get("matched_url", "")),
                    "drop_reason": "",
                })
                break
        else:
            if expected_date_value and expected_date_value < today:
                status = "PAST_TARGET"
                drop_reason = "target_before_run_date"
                matched_date = expected_date_value.isoformat()
            elif expected_date_value and expected_date_value > window_end:
                status = "TARGET_OUT_OF_ACTIVE_WINDOW"
                drop_reason = "target_after_search_window"
                matched_date = expected_date_value.isoformat()
            else:
                status = "MISSING_FROM_RAW"
                drop_reason = ""
                matched_date = ""

            audit.append({
                "id": target_id,
                "title": title,
                "priority": priority,
                "cluster": cluster,
                "status": status,
                "matched_title": "",
                "matched_date": matched_date,
                "matched_url": "",
                "drop_reason": drop_reason,
            })

    return audit
# === END BLOCK: COVERAGE_AUDIT_WITH_WINDOW_STATUS_V2 ===


# === BEGIN BLOCK: WEEKLY_DIAGNOSTICS_SUMMARY_WITH_SOURCE_CANDIDATES_V2 | Zweck: Event-, Coverage- und Source-Candidate-Diagnose zentral in JSON und Log zusammenführen | Umfang: ersetzt build_weekly_diagnostics und print_weekly_diagnostics_summary ===
def build_weekly_diagnostics(
    raw_candidates: List[Dict[str, Any]],
    raw_source_candidates: List[Dict[str, Any]],
    selected_candidates: List[Dict[str, str]],
    drop_diagnostics: List[Dict[str, str]],
    coverage: List[Dict[str, str]],
    source_candidate_diagnostics: List[Dict[str, Any]],
    search_feedback_rules: list[dict[str, str]],
) -> Dict[str, Any]:
    dropped = [item for item in drop_diagnostics if item.get("reason") != "selected"]
    return {
        "generated_at_utc": datetime.utcnow().replace(microsecond=0).isoformat() + "Z",
        "model": OPENAI_MODEL,
        "max_new_candidates": MAX_NEW_CANDIDATES,
        "raw_candidates_returned": len(raw_candidates),
        "raw_source_candidates_returned": len(raw_source_candidates),
        "selected_candidates": len(selected_candidates),
        "dropped_candidates": len(dropped),
        "drop_reasons": count_by_key(dropped, "reason"),
        "raw_candidate_titles": candidate_title_list([normalize_candidate(item) for item in raw_candidates if isinstance(item, dict)]),
        "selected_candidate_titles": candidate_title_list(selected_candidates),
        "dropped_candidate_titles": [
            f"{item.get('reason', '')} | {item.get('date', '')} | {item.get('title', '')}".strip()
            for item in dropped[:80]
        ],
        "source_candidate_reasons": count_by_key(source_candidate_diagnostics, "reason"),
        "source_candidate_stage_counts": count_by_key(source_candidate_diagnostics, "stage"),
        "source_candidates_added": sum(1 for item in source_candidate_diagnostics if item.get("reason") == "added"),
        "source_candidate_summaries": [
            f"{item.get('reason', '')} | {item.get('domain', '')} | {item.get('source_name', '')}".strip()
            for item in source_candidate_diagnostics[:80]
        ],
        "source_candidate_diagnostics": source_candidate_diagnostics,
        "search_feedback_rules_applied": len(search_feedback_rules),
        "search_feedback_class_counts": dict(Counter(rule.get("feedback_class", "") for rule in search_feedback_rules if rule.get("feedback_class"))),
        "search_feedback_rule_summaries": [
            f"{rule.get('priority', '')} | {rule.get('feedback_class', '')} | {rule.get('field', '')} | {rule.get('source_host', '')}".strip()
            for rule in search_feedback_rules[:40]
        ],
        "coverage_targets_total": len(coverage),
        "coverage_status_counts": count_by_key(coverage, "status"),
        "coverage_audit": coverage,
    }


def print_weekly_diagnostics_summary(diagnostics: Dict[str, Any]) -> None:
    print(
        "WEEKLY KI EVENTSUCHE DIAGNOSTICS\n"
        f"- raw_candidates_returned: {diagnostics.get('raw_candidates_returned', 0)}\n"
        f"- selected_candidates: {diagnostics.get('selected_candidates', 0)}\n"
        f"- dropped_candidates: {diagnostics.get('dropped_candidates', 0)}\n"
        f"- drop_reasons: {json.dumps(diagnostics.get('drop_reasons', {}), ensure_ascii=False)}\n"
        f"- raw_source_candidates_returned: {diagnostics.get('raw_source_candidates_returned', 0)}\n"
        f"- source_candidate_reasons: {json.dumps(diagnostics.get('source_candidate_reasons', {}), ensure_ascii=False)}\n"
        f"- source_candidate_stage_counts: {json.dumps(diagnostics.get('source_candidate_stage_counts', {}), ensure_ascii=False)}\n"
        f"- search_feedback_rules_applied: {diagnostics.get('search_feedback_rules_applied', 0)}\n"
        f"- search_feedback_class_counts: {json.dumps(diagnostics.get('search_feedback_class_counts', {}), ensure_ascii=False)}\n"
        f"- coverage_targets_total: {diagnostics.get('coverage_targets_total', 0)}\n"
        f"- coverage_status_counts: {json.dumps(diagnostics.get('coverage_status_counts', {}), ensure_ascii=False)}\n"
        f"- diagnostics_file: {DIAGNOSTICS_JSON_PATH}"
    )
# === END BLOCK: WEEKLY_DIAGNOSTICS_SUMMARY_WITH_SOURCE_CANDIDATES_V2 ===
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
# === BEGIN BLOCK: WEEKLY_PRODUCTION_MAIN_APPEND_MANUAL_BUFFER_V3 | Zweck: Weekly-Lauf bricht bei bestehendem Manual-Puffer nicht ab, nutzt ihn als Dedupe-Bestand und hängt neue Delta-Kandidaten hinten an | Umfang: ersetzt main vollständig, Suchprompt bleibt unverändert ===
def main() -> None:
    export_current_snapshots()

    events_records = read_tsv_records(TMP_EVENTS_TSV_PATH, {"source_url": "url"})
    inbox_records = read_tsv_records(TMP_INBOX_TSV_PATH, {})
    archive_records = read_tsv_records(TMP_ARCHIVE_TSV_PATH, {})
    manual_items = read_manual_items()
    manual_records = read_manual_records_from_items(manual_items)

    # === BEGIN BLOCK: PENDING_INBOX_AND_MANUAL_CONTINUE_AS_DEDUPE_BASIS_V2 | Zweck: offene Inbox und bestehender Manual-Puffer blockieren den Weekly-Lauf nicht, bleiben aber Dedupe-/Prompt-Bestand | Umfang: ersetzt harten Abbruch bei bestehendem Manual-Puffer durch Warnlog ===
    pending_inbox_rows = count_pending_inbox_rows(inbox_records)
    if WARN_ON_PENDING_INBOX and pending_inbox_rows:
        info(
            f"Inbox enthält {pending_inbox_rows} offene Review-Fälle. "
            "Weekly-Lauf wird fortgesetzt; OFFENE_INBOX bleibt Prompt-, Dedupe- und Intake-Bestand."
        )

    if manual_items:
        if FAIL_ON_PENDING_MANUAL:
            info(
                f"data/inbox_manual.json enthält bereits {len(manual_items)} Einträge. "
                "Weekly-Lauf wird fortgesetzt; neue Delta-Kandidaten werden hinten angehängt."
            )
        else:
            info(
                f"data/inbox_manual.json enthält bereits {len(manual_items)} Einträge. "
                "Bestehender Puffer bleibt erhalten; neue Delta-Kandidaten werden hinten angehängt."
            )
    # === END BLOCK: PENDING_INBOX_AND_MANUAL_CONTINUE_AS_DEDUPE_BASIS_V2 ===

    if not REGELWERK_PATH.exists():
        fail(f"Regelwerk fehlt: {REGELWERK_PATH}")

    if not SOURCES_REGISTER_PATH.exists():
        fail(f"Quellenregister fehlt: {SOURCES_REGISTER_PATH}")

    rulebook_text = REGELWERK_PATH.read_text(encoding="utf-8")
    sources_register_text = SOURCES_REGISTER_PATH.read_text(encoding="utf-8")
    search_feedback_rules = read_search_feedback_rules()
    search_feedback_context = build_search_feedback_context(search_feedback_rules)
    info(f"Content-Search-Feedback-Regeln für Prompt: {len(search_feedback_rules)}")

    raw_candidates, raw_source_candidates, response = search_with_openai(
        build_messages(
            rulebook_text,
            sources_register_text,
            search_feedback_context,
            events_records,
            inbox_records,
            archive_records,
            manual_records,
        )
    )

    filtered_delta, drop_diagnostics = filter_delta_with_diagnostics(
        raw_candidates,
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )
    delta = filtered_delta[:MAX_NEW_CANDIDATES]
    manual_output = manual_items + delta

    merged_source_candidates, added_source_candidates, source_candidate_diagnostics = merge_source_candidates_with_diagnostics(
        raw_source_candidates,
        sources_register_text,
    )

    coverage_targets = read_coverage_targets()
    coverage = coverage_audit(
        coverage_targets,
        raw_candidates,
        delta,
        drop_diagnostics,
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )
    diagnostics = build_weekly_diagnostics(
        raw_candidates,
        raw_source_candidates,
        delta,
        drop_diagnostics,
        coverage,
        source_candidate_diagnostics,
        search_feedback_rules,
    )

    ensure_parent(MANUAL_JSON_PATH)
    MANUAL_JSON_PATH.write_text(json.dumps(manual_output, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    ensure_parent(SOURCE_CANDIDATES_JSON_PATH)
    SOURCE_CANDIDATES_JSON_PATH.write_text(
        json.dumps(merged_source_candidates, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    ensure_parent(DIAGNOSTICS_JSON_PATH)
    DIAGNOSTICS_JSON_PATH.write_text(
        json.dumps(diagnostics, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    source_urls = collect_response_sources(response)

    print(
        "WEEKLY KI EVENTSUCHE SUMMARY\n"
        f"- model: {OPENAI_MODEL}\n"
        f"- max_new_candidates: {MAX_NEW_CANDIDATES}\n"
        f"- raw_candidates_returned: {len(raw_candidates)}\n"
        f"- production_selected: {len(delta)}\n"
        f"- existing_manual_json: {len(manual_items)}\n"
        f"- appended_manual_json: {len(delta)}\n"
        f"- written_manual_json_total: {len(manual_output)}\n"
        f"- output_file: {MANUAL_JSON_PATH}\n"
        f"- source_candidates_file: {SOURCE_CANDIDATES_JSON_PATH}\n"
        f"- source_candidates_returned: {len(raw_source_candidates)}\n"
        f"- source_candidates_added: {added_source_candidates}\n"
        f"- source_candidates_total: {len(merged_source_candidates)}\n"
        f"- source_candidate_reasons: {json.dumps(diagnostics.get('source_candidate_reasons', {}), ensure_ascii=False)}\n"
        f"- search_feedback_rules_applied: {len(search_feedback_rules)}\n"
        f"- cited_web_sources: {len(source_urls)}"
    )
    print_weekly_diagnostics_summary(diagnostics)
# === END BLOCK: WEEKLY_PRODUCTION_MAIN_APPEND_MANUAL_BUFFER_V3 ===


if __name__ == "__main__":
    main()
# === END BLOCK: WEEKLY_PRODUCTION_MAIN_WITH_SOURCE_DIAGNOSTICS_V2 ===
