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
SOURCES_REGISTER_PATH = Path(os.environ.get("EVENT_SOURCES_REGISTER_PATH", str(ROOT / "eventsuche_quellenregister_v1.md")))
MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(ROOT / "data" / "inbox_manual.json")))
SOURCE_CANDIDATES_JSON_PATH = Path(os.environ.get("SOURCE_CANDIDATES_JSON_PATH", str(ROOT / "data" / "source_candidates.json")))

TMP_EVENTS_TSV_PATH = Path(os.environ.get("TMP_EVENTS_TSV_PATH", str(TMP_DIR / "events.tsv")))
TMP_INBOX_TSV_PATH = Path(os.environ.get("TMP_INBOX_TSV_PATH", str(TMP_DIR / "inbox.tsv")))
TMP_ARCHIVE_TSV_PATH = Path(os.environ.get("TMP_ARCHIVE_TSV_PATH", str(TMP_DIR / "inbox_archive.tsv")))

TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
TAB_ARCHIVE = os.environ.get("TAB_ARCHIVE", "Inbox_Archive")

# === BEGIN BLOCK: WEEKLY_PRODUCTION_CONFIG_CLEANUP_V1 | Zweck: Weekly-Lauf auf reinen Produktionsmodus zurückbauen | Umfang: entfernt Discovery-Pass-Konfiguration, behält nur produktionsrelevante Laufparameter ===
OPENAI_MODEL = os.environ.get("OPENAI_MODEL", "gpt-5.4").strip()
MAX_NEW_CANDIDATES = int(os.environ.get("MAX_NEW_CANDIDATES", "40"))
SEARCH_WINDOW_DAYS = int(os.environ.get("SEARCH_WINDOW_DAYS", "180"))
ALLOW_RADIUS_KM = int(os.environ.get("ALLOW_RADIUS_KM", "20"))

FAIL_ON_PENDING_INBOX = os.environ.get("FAIL_ON_PENDING_INBOX", "true").lower() in {"1", "true", "yes"}
FAIL_ON_PENDING_MANUAL = os.environ.get("FAIL_ON_PENDING_MANUAL", "true").lower() in {"1", "true", "yes"}
# === END BLOCK: WEEKLY_PRODUCTION_CONFIG_CLEANUP_V1 ===

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


# === BEGIN BLOCK: WEEKLY_PRODUCTION_PROMPT_WITH_SOURCE_LEARNING_V1 | Zweck: Produktionslauf um halbautomatisches Quellenlernen erweitern | Umfang: hält Event-Output streng und ergänzt separaten source_candidates-Kanal ===
def build_messages(
    rulebook_text: str,
    sources_register_text: str,
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
Du führst die wöchentliche KI-Eventsuche für Bocholt erleben aus.

Arbeite streng, konservativ, produktionsnah und redaktionell anspruchsvoll.
Nutze aktiv die Websuche.
Halte das beigefügte Regelwerk strikt ein.
Nutze das beigefügte Quellenregister als operative Quellensteuerung.
Liefere nur neue Delta-Kandidaten.

Im Automationsmodus gilt für Events:
- nur FINAL
- kein REVIEW
- kein Fließtext
- keine Kommentare

Zusätzlich sammelst du neue Quellen separat als source_candidates.
source_candidates sind keine Events und dürfen niemals in die Event-Inbox gemischt werden.

Wichtig:
- Nicht jeder formal korrekte Termin ist FINAL-tauglich.
- FINAL bedeutet hier: formal belastbar und zugleich redaktionell stark genug für die Kuratierungs-PWA.
- Dieser Lauf ist ein PRODUKTIONSLAUF mit kontrolliertem Quellenlernen.
- Neue Quellen dürfen als Nebenfund dokumentiert werden, werden aber nicht automatisch dauerhaft ins Quellenregister übernommen.
""".strip()

    user_prompt = f"""
[REGELWERK]
{rulebook_text}
[/REGELWERK]

[QUELLENREGISTER]
{sources_register_text}
[/QUELLENREGISTER]

[AUFGABE]
Simuliere den späteren automatisierten Produktionslauf für Bocholt erleben so nah wie möglich.

Rahmen:
- Zeitraum: ab heute bis {SEARCH_WINDOW_DAYS} Tage in die Zukunft
- Suchgebiet: Bocholt + maximal ca. {ALLOW_RADIUS_KM} km Umkreis inklusive niederländischer Orte innerhalb dieses Radius
- Liefere höchstens {MAX_NEW_CANDIDATES} neue Delta-Kandidaten
- Deduped strikt gegen BESTAND_EVENTS, OFFENE_INBOX, ARCHIV und MANUAL_JSON
- Deduped zusätzlich innerhalb des aktuellen Laufs gegen bereits ausgewählte Kandidaten
- Liefere lieber weniger starke FINAL-Kandidaten als schwache oder unsichere Treffer
- Fülle die Zielmenge niemals künstlich mit nur formal korrekten, aber schwachen Kandidaten auf

Operative Quellensteuerung für den Produktionslauf:
- Nutze das Quellenregister als Gewichtung, nicht als starre Whitelist
- Prüfe zuerst CORE-HIGH
- danach CORE-MID
- danach RECOVERY gezielt
- LOW-VALUE nicht aktiv priorisieren
- EXCLUDE / GESPERRT nicht aktiv als Quelle nutzen
- Gute Quellen dürfen mehrere echte Instanzen liefern, wenn diese regelkonform und stark sind
- Keine harte Quellenbegrenzung, aber keine schwachen Fülltreffer aus einer Quelle
- Kontrollierte offene Suche ist erlaubt, wenn sie ein starkes Event findet und die Quelle separat als source_candidate dokumentiert wird

Kontrolliertes Quellenlernen:
- Das Quellenregister ist keine Closed-Whitelist.
- Wenn du bei der Eventsuche eine gute neue Quelle außerhalb des Registers findest, darfst du sie als source_candidate dokumentieren.
- Eine neue Quelle darf nur dann zu source_candidates, wenn sie mindestens ein starkes, regelkonformes oder fast regelkonformes Event im Suchgebiet belegt.
- source_candidates dürfen keine Social-only-, Ticketshop-only-, Venue-Promo- oder klar gesperrten Quellen sein.
- source_candidates sind nur Vorschläge für spätere Registerpflege, keine automatische Freigabe.
- Nimm bekannte Quellen aus dem Quellenregister nicht erneut als source_candidate auf.

Recovery-Prüfung vor Verwerfen:
Wenn ein Kandidat fast FINAL-fähig wirkt, dann versuche vor dem Verwerfen aktiv:
1. eine bessere offizielle Detailseite zu finden
2. eine stabilere, kanonischere und instanzpassende Event-URL zu finden
3. die höchste sicher belegte Ortsgranularität zu bestimmen
4. bei Mehrtermin-Seiten zu prüfen, ob die sichtbare Einzelinstanz trotzdem sauber FINAL-fähig ist
5. bei offiziellen Info-/News-Seiten zu prüfen, ob trotz zusätzlicher Orga-/CTA-/Aussteller-Elemente ein klarer Besucherfokus vorliegt

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

Output-Regeln:
- Gib ausschließlich ein JSON-Objekt mit den Schlüsseln "candidates" und "source_candidates" zurück.
- "candidates" enthält nur neue FINAL-Events.
- "source_candidates" enthält nur neue Quellenvorschläge, keine Events für die Inbox.
- Keine zusätzlichen Schlüssel.
- Keine Einleitung.
- Keine Erklärung.
- Keine Kommentare.
- Keine REVIEW-Fälle.
- Keine EXCLUDE-Fälle.
- Keine unsicheren Event-Datensätze.
- Keine bereits vorhandenen oder bereits entschiedenen Event-Dubletten.

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
- keine Dublette?
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
# === END BLOCK: WEEKLY_PRODUCTION_PROMPT_WITH_SOURCE_LEARNING_V1 ===
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
# === BEGIN BLOCK: LOCAL_POST_VALIDATION_DEDUPE_RANGE_AWARE_V3 | Zweck: FINAL-Validierung, Zeitformat, Laufzeitfenster und Instanz-Dedupe regelwerkskonform absichern | Umfang: ersetzt lokale Post-Validation + Dedupe komplett ===
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


def existing_sets(*groups: List[RefRecord]) -> tuple[set[str], set[str], set[str], set[str]]:
    source_occurrences: set[str] = set()
    exact_occurrences: set[str] = set()
    no_time_occurrences_all: set[str] = set()
    no_time_occurrences_missing_time: set[str] = set()

    for group in groups:
        for rec in group:
            fp = occurrence_fp(rec.title, rec.date, rec.time, rec.location)
            fp_no_time = occurrence_fp_no_time(rec.title, rec.date, rec.location)
            src_fp = source_occurrence_fp(rec.source_url, rec.url, rec.title, rec.date, rec.time, rec.location)

            if src_fp and src_fp != "|||||":
                source_occurrences.add(src_fp)
            if fp != "|||":
                exact_occurrences.add(fp)
            if fp_no_time != "|||":
                no_time_occurrences_all.add(fp_no_time)
            if not norm(rec.time) and fp_no_time != "|||":
                no_time_occurrences_missing_time.add(fp_no_time)

    return source_occurrences, exact_occurrences, no_time_occurrences_all, no_time_occurrences_missing_time


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


def valid_candidate(item: Dict[str, str], start: date, end: date) -> bool:
    required = [
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
    if any(not norm(item.get(k, "")) for k in required):
        return False

    if not parse_iso_date(item.get("date", "")):
        return False
    if item.get("endDate") and not parse_iso_date(item.get("endDate", "")):
        return False
    if item.get("time") and not re.match(r"^\d{2}:\d{2}$", item["time"]):
        return False
    if not candidate_in_window(item, start, end):
        return False

    return True


def filter_delta(raw_candidates: List[Dict[str, Any]], events_records: List[RefRecord], inbox_records: List[RefRecord], archive_records: List[RefRecord], manual_records: List[RefRecord]) -> List[Dict[str, str]]:
    start = datetime.now().date()
    end = start + timedelta(days=SEARCH_WINDOW_DAYS)

    existing_source_occurrences, existing_exact, existing_no_time_all, existing_no_time_missing = existing_sets(
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )

    batch_source_occurrences: set[str] = set()
    batch_exact: set[str] = set()
    batch_no_time_all: set[str] = set()
    batch_no_time_missing: set[str] = set()

    out: List[Dict[str, str]] = []

    for raw in raw_candidates:
        if not isinstance(raw, dict):
            continue

        item = normalize_candidate(raw)
        if not valid_candidate(item, start, end):
            continue

        title = item.get("title", "")
        date_value = item.get("date", "")
        time_value = item.get("time", "")
        location = item.get("location", "")
        has_time = bool(norm(time_value))

        fp = occurrence_fp(title, date_value, time_value, location)
        fp_no_time = occurrence_fp_no_time(title, date_value, location)
        src_fp = source_occurrence_fp(
            item.get("source_url", ""),
            item.get("url", ""),
            title,
            date_value,
            time_value,
            location,
        )

        if src_fp and (src_fp in existing_source_occurrences or src_fp in batch_source_occurrences):
            continue

        if has_time:
            if fp in existing_exact or fp in batch_exact:
                continue
            if fp_no_time in existing_no_time_missing or fp_no_time in batch_no_time_missing:
                continue
        else:
            if fp_no_time in existing_no_time_all or fp_no_time in batch_no_time_all:
                continue

        out.append({k: v for k, v in item.items() if v != ""})

        if src_fp:
            batch_source_occurrences.add(src_fp)
        batch_exact.add(fp)
        batch_no_time_all.add(fp_no_time)
        if not has_time:
            batch_no_time_missing.add(fp_no_time)

    return out
# === END BLOCK: LOCAL_POST_VALIDATION_DEDUPE_RANGE_AWARE_V3 ===

# === BEGIN BLOCK: SOURCE_CANDIDATE_LEARNING_V1 | Zweck: neue Quellenkandidaten separat sammeln, ohne sie automatisch produktiv freizugeben | Umfang: liest/normalisiert/merged data/source_candidates.json ===
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


def normalize_source_candidate(item: Dict[str, Any], today: date) -> Dict[str, Any] | None:
    if not isinstance(item, dict):
        return None

    evidence_events = item.get("evidence_events", [])
    if not isinstance(evidence_events, list):
        evidence_events = []

    clean_evidence: List[Dict[str, str]] = []
    for ev in evidence_events[:5]:
        if not isinstance(ev, dict):
            continue
        clean_evidence.append(
            {
                "title": norm(ev.get("title", "")),
                "date": norm(ev.get("date", "")),
                "url": canonical_url(ev.get("url", "")),
            }
        )

    example_url = canonical_url(item.get("example_url", ""))
    domain = norm(item.get("domain", "")) or source_domain(example_url)
    if domain.startswith("www."):
        domain = domain[4:]

    out: Dict[str, Any] = {
        "domain": domain,
        "source_name": norm(item.get("source_name", "")),
        "source_url_pattern": norm(item.get("source_url_pattern", "")) or (f"{domain}/*" if domain else ""),
        "example_url": example_url,
        "suggested_status": norm(item.get("suggested_status", "")) or "NEW-SOURCE-CANDIDATE",
        "quality_signal": norm(item.get("quality_signal", "")),
        "risk_signal": norm(item.get("risk_signal", "")),
        "reason": norm(item.get("reason", "")),
        "usage_rule": norm(item.get("usage_rule", "")),
        "evidence_events": clean_evidence,
        "first_seen": today.isoformat(),
        "last_seen": today.isoformat(),
        "found_via": "weekly-ki-websearch",
        "status": "neu",
        "notes": norm(item.get("notes", "")),
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
    if any(not norm(out.get(k, "")) for k in required):
        return None
    if not clean_evidence:
        return None

    return out


def merge_source_candidates(
    raw_source_candidates: List[Dict[str, Any]],
    sources_register_text: str,
) -> tuple[List[Dict[str, Any]], int]:
    existing = read_source_candidates()
    today = datetime.now().date()

    by_key: Dict[str, Dict[str, Any]] = {}
    order: List[str] = []

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
        item = normalize_source_candidate(raw, today)
        if not item:
            continue
        if source_known_in_register(item, sources_register_text):
            continue

        key = source_key_from_item(item)
        if not key.strip("|"):
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
            continue

        by_key[key] = item
        order.append(key)
        added += 1

    merged = [by_key[key] for key in order]
    return merged, added
# === END BLOCK: SOURCE_CANDIDATE_LEARNING_V1 ===
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
# === BEGIN BLOCK: WEEKLY_PRODUCTION_MAIN_WITH_SOURCE_LEARNING_V1 | Zweck: Produktionslauf schreibt Event-Delta und sammelt neue Quellenkandidaten separat | Umfang: ergänzt data/source_candidates.json ohne automatisches Register-Update ===
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

    if not SOURCES_REGISTER_PATH.exists():
        fail(f"Quellenregister fehlt: {SOURCES_REGISTER_PATH}")

    rulebook_text = REGELWERK_PATH.read_text(encoding="utf-8")
    sources_register_text = SOURCES_REGISTER_PATH.read_text(encoding="utf-8")

    raw_candidates, raw_source_candidates, response = search_with_openai(
        build_messages(
            rulebook_text,
            sources_register_text,
            events_records,
            inbox_records,
            archive_records,
            manual_records,
        )
    )

    delta = filter_delta(
        raw_candidates,
        events_records,
        inbox_records,
        archive_records,
        manual_records,
    )[:MAX_NEW_CANDIDATES]

    merged_source_candidates, added_source_candidates = merge_source_candidates(
        raw_source_candidates,
        sources_register_text,
    )

    ensure_parent(MANUAL_JSON_PATH)
    MANUAL_JSON_PATH.write_text(json.dumps(delta, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    ensure_parent(SOURCE_CANDIDATES_JSON_PATH)
    SOURCE_CANDIDATES_JSON_PATH.write_text(
        json.dumps(merged_source_candidates, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    source_urls = collect_response_sources(response)

    print(
        "WEEKLY KI EVENTSUCHE SUMMARY\n"
        f"- model: {OPENAI_MODEL}\n"
        f"- max_new_candidates: {MAX_NEW_CANDIDATES}\n"
        f"- production_selected: {len(delta)}\n"
        f"- written_manual_json: {len(delta)}\n"
        f"- output_file: {MANUAL_JSON_PATH}\n"
        f"- source_candidates_file: {SOURCE_CANDIDATES_JSON_PATH}\n"
        f"- source_candidates_returned: {len(raw_source_candidates)}\n"
        f"- source_candidates_added: {added_source_candidates}\n"
        f"- source_candidates_total: {len(merged_source_candidates)}\n"
        f"- cited_web_sources: {len(source_urls)}"
    )


if __name__ == "__main__":
    main()
# === END BLOCK: WEEKLY_PRODUCTION_MAIN_WITH_SOURCE_LEARNING_V1 ===
