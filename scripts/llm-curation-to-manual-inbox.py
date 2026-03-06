# === BEGIN BLOCK: LLM CURATION TO MANUAL INBOX (Sources sheet -> data/inbox_manual.json) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Nutzt bestehende LLM-Discovery-Helfer minimal-invasiv weiter
# - Liest aktivierte Sources mit pipeline_mode=llm aus dem Google-Sheet-Tab "Sources"
# - Sammelt/kuratiert Event-Kandidaten
# - Deduped gegen bestehende data/inbox.tsv und innerhalb des aktuellen Laufs
# - Schreibt nur review-taugliche Kandidaten in data/inbox_manual.json
#
# Output:
# - data/inbox_manual.json
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
# - optional: TAB_SOURCES (default: Sources)
# - optional: MANUAL_INBOX_JSON_PATH (default: data/inbox_manual.json)
# - optional: INBOX_TSV_PATH (default: data/inbox.tsv)
# - optional: MAX_NEW_PER_RUN (default: 25)
#
# Regeln:
# - Kein UI-Work
# - Kein Direktimport in inbox.tsv / inbox.json
# - Fail-fast bei fehlenden Secrets / ungültigem Sources-Tab / Schreibfehlern
# === END BLOCK: LLM CURATION TO MANUAL INBOX (Sources sheet -> data/inbox_manual.json) ===

from __future__ import annotations

import asyncio
import csv
import importlib.util
import json
import os
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Tuple


# === BEGIN BLOCK: PATHS + FIXED OUTPUT SCHEMA (manual inbox json) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Definiert Pfade und fixes JSON-Schema für data/inbox_manual.json
# Umfang:
# - Nur Konfiguration / Schema
# === END BLOCK: PATHS + FIXED OUTPUT SCHEMA (manual inbox json) ===
ROOT = Path(__file__).resolve().parents[1]
SCRIPTS_DIR = ROOT / "scripts"
HELPER_PATH = SCRIPTS_DIR / "llm-discovery-to-inbox.py"

MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(ROOT / "data" / "inbox_manual.json")))
INBOX_TSV_PATH = Path(os.environ.get("INBOX_TSV_PATH", str(ROOT / "data" / "inbox.tsv")))
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
MAX_NEW_PER_RUN = int(os.environ.get("MAX_NEW_PER_RUN", "25"))

MANUAL_JSON_COLUMNS = [
    "status",
    "id_suggestion",
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
    "match_score",
    "matched_event_id",
    "notes",
    "created_at",
]
# === END BLOCK: PATHS + FIXED OUTPUT SCHEMA (manual inbox json) ===


# === BEGIN BLOCK: BASIC HELPERS (logging + normalization) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Kleine Hilfsfunktionen für Logging, Normalisierung, Datei-I/O
# Umfang:
# - Nur lokale Helper
# === END BLOCK: BASIC HELPERS (logging + normalization) ===
def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def norm(s: str) -> str:
    return (s or "").strip()


def norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", norm(s)).lower()


def now_iso_local() -> str:
    from datetime import datetime
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def title_date_location_fp(title: str, date_value: str, location: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
        norm_key(location),
    ])


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
# === END BLOCK: BASIC HELPERS (logging + normalization) ===


# === BEGIN BLOCK: LOAD EXISTING LLM DISCOVERY HELPERS (import via file path) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Nutzt vorhandene Collector-/Extractor-Logik aus scripts/llm-discovery-to-inbox.py weiter
# Umfang:
# - importlib file-load
# === END BLOCK: LOAD EXISTING LLM DISCOVERY HELPERS (import via file path) ===
def load_llm_helper_module():
    if not HELPER_PATH.exists():
        fail(f"Helferdatei fehlt: {HELPER_PATH}")

    spec = importlib.util.spec_from_file_location("llm_discovery_helper", HELPER_PATH)
    if spec is None or spec.loader is None:
        fail(f"Import von Helferdatei fehlgeschlagen: {HELPER_PATH}")

    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module
# === END BLOCK: LOAD EXISTING LLM DISCOVERY HELPERS (import via file path) ===


# === BEGIN BLOCK: READ LOCAL INBOX TSV FOR DEDUPE (repo source of truth) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Liest bestehende data/inbox.tsv als Dedupe-Basis
# Umfang:
# - Kein Schreiben, nur Lesen
# === END BLOCK: READ LOCAL INBOX TSV FOR DEDUPE (repo source of truth) ===
def read_inbox_tsv(path: Path) -> Tuple[set[str], set[str]]:
    if not path.exists():
        return set(), set()

    with path.open("r", encoding="utf-8", newline="") as f:
        reader = csv.DictReader(f, delimiter="\t")
        if not reader.fieldnames:
            fail(f"Inbox-TSV hat keine Header-Zeile: {path}")

        source_urls: set[str] = set()
        fingerprints: set[str] = set()

        for row in reader:
            raw_source_url = norm(row.get("source_url", ""))
            raw_url = norm(row.get("url", ""))
            raw_title = norm(row.get("title", ""))
            raw_date = norm(row.get("date", ""))
            raw_location = norm(row.get("location", ""))

            if raw_source_url:
                source_urls.add(norm_key(raw_source_url))
            if raw_url:
                source_urls.add(norm_key(raw_url))

            fp = title_date_location_fp(raw_title, raw_date, raw_location)
            if fp != "||":
                fingerprints.add(fp)

        return source_urls, fingerprints
# === END BLOCK: READ LOCAL INBOX TSV FOR DEDUPE (repo source of truth) ===


# === BEGIN BLOCK: QUALITY GATES (curation-first prefilter) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Filtert offensichtliche Nicht-Events vor dem Manual-Import
# - Hält inbox_manual.json review-tauglich
# Umfang:
# - Nur leichte Vorfilterung, keine Veröffentlichung
# === END BLOCK: QUALITY GATES (curation-first prefilter) ===
NON_EVENT_PATTERNS = [
    r"\bgottesdienst\b",
    r"\bmesse\b",
    r"\bandacht\b",
    r"\btrauerfeier\b",
    r"\bimpressum\b",
    r"\bdatenschutz\b",
    r"\bnewsletter\b",
    r"\babonnieren\b",
    r"\banmeldung\b",
    r"\bkontakt\b",
    r"\böffnungszeiten\b",
    r"\bpressemitteilung\b",
    r"\bstellenangebot\b",
    r"\bjob\b",
    r"\bhomepage\b",
    r"\bveranstaltungskalender\b",
    r"\btermine\b",
    r"\bprogrammübersicht\b",
]

NON_EVENT_RE = re.compile("|".join(f"(?:{p})" for p in NON_EVENT_PATTERNS), re.IGNORECASE)


def is_reviewable_candidate(item: Dict[str, str], helper: Any) -> Tuple[bool, str]:
    title = norm(item.get("title", ""))
    date_value = norm(item.get("date", ""))
    source_url = norm(item.get("source_url", ""))
    url = norm(item.get("url", ""))
    source_name = norm(item.get("source_name", ""))
    text_blob = " ".join([
        title,
        norm(item.get("description", "")),
        norm(item.get("location", "")),
        norm(item.get("city", "")),
        source_name,
    ])

    if not title:
        return False, "missing_title"
    if not date_value:
        return False, "missing_date"
    if not (source_url or url):
        return False, "missing_source_url"
    if NON_EVENT_RE.search(text_blob):
        return False, "non_event_pattern"

    try:
        if not helper.date_in_horizon(date_value, 180):
            return False, "outside_horizon"
    except Exception:
        return False, "invalid_date"

    return True, ""
# === END BLOCK: QUALITY GATES (curation-first prefilter) ===


# === BEGIN BLOCK: MAP EXTRACTED FIELDS TO MANUAL JSON SCHEMA (stable contract) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Baut aus Discovery-Feldern das feste inbox_manual.json-Schema
# Umfang:
# - Nur Mapping / keine Dedupe-Logik
# === END BLOCK: MAP EXTRACTED FIELDS TO MANUAL JSON SCHEMA (stable contract) ===
def build_manual_item(cfg: Any, fields: Dict[str, str], helper: Any) -> Dict[str, str]:
    event_url = norm(fields.get("url", ""))
    source_url = helper.normalize_url(event_url or fields.get("source_url", "") or cfg.url)

    item = {
        "status": "review",
        "id_suggestion": norm(fields.get("id_suggestion", "")),
        "title": norm(fields.get("title", "")),
        "date": norm(fields.get("date", "")),
        "endDate": norm(fields.get("endDate", "")),
        "time": norm(fields.get("time", "")),
        "city": norm(fields.get("city", "")) or norm(getattr(cfg, "default_city", "")) or "Bocholt",
        "location": norm(fields.get("location", "")),
        "kategorie_suggestion": norm(fields.get("kategorie_suggestion", "")) or norm(getattr(cfg, "default_category", "")),
        "url": helper.normalize_url(event_url) if event_url else source_url,
        "description": norm(fields.get("description", "")),
        "source_name": norm(getattr(cfg, "source_name", "")),
        "source_url": source_url,
        "match_score": "",
        "matched_event_id": "",
        "notes": "llm_manual_curation",
        "created_at": now_iso_local(),
    }
    return {k: item.get(k, "") for k in MANUAL_JSON_COLUMNS}
# === END BLOCK: MAP EXTRACTED FIELDS TO MANUAL JSON SCHEMA (stable contract) ===


# === BEGIN BLOCK: WRITE OUTPUT JSON + SUMMARY (deterministic) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Schreibt data/inbox_manual.json deterministisch
# - Schreibt klare Summary ins Log / GitHub Step Summary
# Umfang:
# - Nur Output
# === END BLOCK: WRITE OUTPUT JSON + SUMMARY (deterministic) ===
def write_manual_json(path: Path, items: List[Dict[str, str]]) -> None:
    ensure_parent(path)
    payload = [{k: v for k, v in item.items() if k in MANUAL_JSON_COLUMNS and v != ""} for item in items]
    path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_summary(stats: Dict[str, int], source_count: int, out_path: Path) -> None:
    lines = [
        "LLM CURATION TO MANUAL INBOX SUMMARY",
        f"- sources_selected: {source_count}",
        f"- candidates_seen: {stats.get('candidates_seen', 0)}",
        f"- written_manual_json: {stats.get('written_manual_json', 0)}",
        f"- skipped_invalid: {stats.get('skipped_invalid', 0)}",
        f"- skipped_deduped: {stats.get('skipped_deduped', 0)}",
        f"- skipped_quality: {stats.get('skipped_quality', 0)}",
        f"- output_file: {out_path}",
    ]
    summary = "\n".join(lines)
    print(summary)

    step_summary_path = norm(os.environ.get("GITHUB_STEP_SUMMARY", ""))
    if step_summary_path:
        Path(step_summary_path).write_text(summary + "\n", encoding="utf-8")
# === END BLOCK: WRITE OUTPUT JSON + SUMMARY (deterministic) ===


# === BEGIN BLOCK: COLLECT + CURATE CANDIDATES (reuse existing LLM discovery helpers) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Führt die eigentliche KI-Kuration aus
# - Nutzt bestehende html/rss collector + extractor Helfer
# Umfang:
# - Nur data/inbox_manual.json als Output
# === END BLOCK: COLLECT + CURATE CANDIDATES (reuse existing LLM discovery helpers) ===
async def collect_manual_candidates(helper: Any) -> Tuple[List[Dict[str, str]], Dict[str, int], int]:
    service, sheet_id = helper.build_sheets_service()
    sources_values = helper.read_tab(service, sheet_id, TAB_SOURCES)
    llm_sources = helper.parse_sources(sources_values)

    if not llm_sources:
        fail("Keine aktivierten Sources mit pipeline_mode=llm gefunden.")

    existing_source_urls, existing_fps = read_inbox_tsv(INBOX_TSV_PATH)
    batch_source_urls: set[str] = set()
    batch_fps: set[str] = set()

    output_items: List[Dict[str, str]] = []
    stats = {
        "candidates_seen": 0,
        "written_manual_json": 0,
        "skipped_invalid": 0,
        "skipped_deduped": 0,
        "skipped_quality": 0,
    }

    for cfg in llm_sources:
        details: List[str] = []
        list_items_by_url: Dict[str, Dict[str, str]] = {}
        rss_items_by_url: Dict[str, Dict[str, str]] = {}
        err = ""

        if cfg.source_type == "html":
            res = await helper.collect_detail_urls_playwright(cfg)
            details = res.get("detail_urls", []) or []
            err = res.get("error", "") or ""

            host = helper._host_norm(helper.urlparse(cfg.url).netloc)
            if ("django-flint.de" in host) or ("coltplay.de" in host):
                fb = await helper.collect_listpage_items_playwright(cfg)
                list_items_by_url = fb.get("items_by_url", {}) or {}
                if fb.get("error"):
                    err = fb["error"]
                elif list_items_by_url:
                    details = list(list_items_by_url.keys())

        elif cfg.source_type == "rss":
            res = helper.collect_rss_items(cfg)
            details = res.get("detail_urls", []) or []
            rss_items_by_url = res.get("items_by_url", {}) or {}
            err = res.get("error", "") or ""
        else:
            continue

        if err:
            info(f"Quelle übersprungen wegen Fetch-/Collector-Fehler: {cfg.source_name} | {err}")
            continue

        for u in details:
            if stats["written_manual_json"] >= MAX_NEW_PER_RUN:
                break

            stats["candidates_seen"] += 1

            fields: Dict[str, str] = {}
            try:
                if u in list_items_by_url:
                    fields = dict(list_items_by_url.get(u) or {})
                    fields["url"] = u
                elif u in rss_items_by_url:
                    fields = dict(rss_items_by_url.get(u) or {})
                    fields["url"] = u
                else:
                    extracted = helper.llm_extract_event_fields("", u, cfg)
                    if extracted:
                        fields = dict(extracted)
                        fields["url"] = fields.get("url", "") or u
                    else:
                        fallback = helper.extract_event_fields("", u, cfg)
                        fields = dict(fallback)
                        fields["url"] = fields.get("url", "") or u
            except Exception:
                stats["skipped_invalid"] += 1
                continue

            item = build_manual_item(cfg, fields, helper)

            is_ok, reason = is_reviewable_candidate(item, helper)
            if not is_ok:
                stats["skipped_quality"] += 1
                continue

            source_url_key = norm_key(item.get("source_url", "") or item.get("url", ""))
            fp = title_date_location_fp(item.get("title", ""), item.get("date", ""), item.get("location", ""))

            if source_url_key in existing_source_urls or source_url_key in batch_source_urls:
                stats["skipped_deduped"] += 1
                continue

            if fp in existing_fps or fp in batch_fps:
                stats["skipped_deduped"] += 1
                continue

            output_items.append(item)
            batch_source_urls.add(source_url_key)
            batch_fps.add(fp)
            stats["written_manual_json"] += 1

        if stats["written_manual_json"] >= MAX_NEW_PER_RUN:
            break

    return output_items, stats, len(llm_sources)
# === END BLOCK: COLLECT + CURATE CANDIDATES (reuse existing LLM discovery helpers) ===


# === BEGIN BLOCK: MAIN ENTRYPOINT (generate data/inbox_manual.json only) ===
# Datei: scripts/llm-curation-to-manual-inbox.py
# Zweck:
# - Orchestriert den Generator
# - Erzeugt nur data/inbox_manual.json
# Umfang:
# - Kein Import in inbox.tsv / inbox.json
# === END BLOCK: MAIN ENTRYPOINT (generate data/inbox_manual.json only) ===
async def main_async() -> None:
    helper = load_llm_helper_module()

    info(f"Quelle für LLM-Sources: Sheet-Tab '{TAB_SOURCES}'")
    info(f"Output-Datei: {MANUAL_JSON_PATH}")
    info(f"Dedupe-Basis: {INBOX_TSV_PATH}")

    items, stats, source_count = await collect_manual_candidates(helper)
    write_manual_json(MANUAL_JSON_PATH, items)
    write_summary(stats, source_count, MANUAL_JSON_PATH)


if __name__ == "__main__":
    asyncio.run(main_async())
# === END BLOCK: MAIN ENTRYPOINT (generate data/inbox_manual.json only) ===
