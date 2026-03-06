# === BEGIN BLOCK: MANUAL INBOX IMPORTER (JSON -> Google Sheet Inbox, curaton-first) ===
# Datei: scripts/inbox-manual-to-sheet.py
# Zweck:
# - Liest data/inbox_manual.json
# - Validiert feste Manual-Intake-Struktur
# - Deduped gegen bestehenden Sheet-Tab "Inbox"
# - Appendet nur neue Zeilen mit status="review"
# - Schreibt klare Run-Summary ins Log
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
# - optional: TAB_INBOX (default: Inbox)
# - optional: MANUAL_INBOX_JSON_PATH (default: data/inbox_manual.json)
#
# Regeln:
# - Kein UI-Work
# - Fail-fast bei fehlenden Secrets / ungültigem JSON / fehlenden Inbox-Spalten
# - Dedupe mindestens über source_url, zusätzlich title+date+location
# === END BLOCK: MANUAL INBOX IMPORTER (JSON -> Google Sheet Inbox, curaton-first) ===

from __future__ import annotations

import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Tuple
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: CONFIG + FIXED MANUAL SCHEMA (aligned to Inbox columns) ===
# Datei: scripts/inbox-manual-to-sheet.py
# Zweck:
# - Definiert festen Schema-Vertrag für data/inbox_manual.json
# - Hält Spaltenreihenfolge exakt kompatibel zum bestehenden Inbox-Sheet
# Umfang:
# - Nur Konfiguration / Schema
# === END BLOCK: CONFIG + FIXED MANUAL SCHEMA (aligned to Inbox columns) ===
ROOT = Path(__file__).resolve().parents[1]
MANUAL_JSON_PATH = Path(os.environ.get("MANUAL_INBOX_JSON_PATH", str(ROOT / "data" / "inbox_manual.json")))
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")

INBOX_COLUMNS = [
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

ALLOWED_JSON_KEYS = set(INBOX_COLUMNS)

REQUIRED_JSON_KEYS = [
    "title",
    "date",
    "city",
    "location",
    "source_name",
    "source_url",
]
# === END BLOCK: CONFIG + FIXED MANUAL SCHEMA (aligned to Inbox columns) ===


def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def now_iso() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def norm(s: str) -> str:
    return (s or "").strip()


def norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", norm(s)).lower()


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


def dedupe_fp(title: str, date_value: str, location: str) -> str:
    return "|".join([
        norm_key(title),
        norm(date_value),
        norm_key(location),
    ])


def get_sheet_service() -> object:
    sheet_id = os.environ.get("SHEET_ID")
    sa_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not sheet_id:
        fail("ENV SHEET_ID fehlt.")
    if not sa_json:
        fail("ENV GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")

    try:
        sa_info = json.loads(sa_json)
    except Exception:
        fail("GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON.")

    scopes = ["https://www.googleapis.com/auth/spreadsheets"]
    creds = service_account.Credentials.from_service_account_info(sa_info, scopes=scopes)
    return build("sheets", "v4", credentials=creds, cache_discovery=False)


def read_tab(service: object, sheet_id: str, tab_name: str) -> list[list[str]]:
    rng = f"{tab_name}!A:ZZ"
    try:
        res = service.spreadsheets().values().get(spreadsheetId=sheet_id, range=rng).execute()
        return res.get("values", []) or []
    except Exception as e:
        fail(f"Tab '{tab_name}' konnte nicht gelesen werden. Existiert er? ({e})")


def append_rows(service: object, sheet_id: str, tab_name: str, rows: list[list[str]]) -> None:
    if not rows:
        return

    body = {"values": rows}
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A1",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body=body,
    ).execute()


def sheet_header(values: list[list[str]]) -> list[str]:
    if not values:
        return []
    return [norm(h) for h in values[0]]


def sheet_rows_to_dicts(values: list[list[str]]) -> Tuple[list[str], list[Dict[str, str]]]:
    header = sheet_header(values)
    if not header:
        return [], []

    rows: list[Dict[str, str]] = []
    for row in values[1:]:
        obj: Dict[str, str] = {}
        for i, col in enumerate(header):
            obj[col] = norm(row[i]) if i < len(row) else ""
        if any(v for v in obj.values()):
            rows.append(obj)
    return header, rows


def load_manual_json(path: Path) -> list[Dict[str, str]]:
    if not path.exists():
        fail(f"Manual-JSON fehlt: {path}")

    try:
        raw = json.loads(path.read_text(encoding="utf-8"))
    except Exception as e:
        fail(f"Manual-JSON ist ungültig: {path} ({e})")

    if not isinstance(raw, list):
        fail("data/inbox_manual.json muss ein JSON-Array sein.")

    items: list[Dict[str, str]] = []
    for idx, item in enumerate(raw, start=1):
        if not isinstance(item, dict):
            fail(f"data/inbox_manual.json: Element #{idx} ist kein Objekt.")
        obj: Dict[str, str] = {}
        for key, value in item.items():
            if value is None:
                obj[str(key)] = ""
            elif isinstance(value, (str, int, float, bool)):
                obj[str(key)] = str(value).strip()
            else:
                fail(f"data/inbox_manual.json: Feld '{key}' in Element #{idx} ist kein skalarer Wert.")
        items.append(obj)

    return items


def validate_item(item: Dict[str, str], idx: int) -> Tuple[bool, str]:
    unknown_keys = sorted(set(item.keys()) - ALLOWED_JSON_KEYS)
    if unknown_keys:
        return False, f"item#{idx}: unknown_keys={','.join(unknown_keys)}"

    missing = [k for k in REQUIRED_JSON_KEYS if not norm(item.get(k, ""))]
    if missing:
        return False, f"item#{idx}: missing_required={','.join(missing)}"

    date_value = norm(item.get("date", ""))
    if date_value and not re.match(r"^\d{4}-\d{2}-\d{2}$", date_value):
        return False, f"item#{idx}: invalid_date_format"

    end_date_value = norm(item.get("endDate", ""))
    if end_date_value and not re.match(r"^\d{4}-\d{2}-\d{2}$", end_date_value):
        return False, f"item#{idx}: invalid_endDate_format"

    source_url = canonical_url(item.get("source_url", ""))
    if not source_url:
        return False, f"item#{idx}: invalid_source_url"

    return True, ""


def build_existing_indexes(rows: list[Dict[str, str]]) -> Tuple[set[str], set[str]]:
    source_urls: set[str] = set()
    fingerprints: set[str] = set()

    for row in rows:
        source_url = canonical_url(row.get("source_url", ""))
        if source_url:
            source_urls.add(norm_key(source_url))

        fp = dedupe_fp(
            row.get("title", ""),
            row.get("date", ""),
            row.get("location", ""),
        )
        if fp != "||":
            fingerprints.add(fp)

    return source_urls, fingerprints


def build_output_row(item: Dict[str, str], inbox_header: list[str], created_at: str) -> list[str]:
    normalized = {key: norm(value) for key, value in item.items()}
    normalized["status"] = "review"
    normalized["created_at"] = norm(normalized.get("created_at", "")) or created_at
    normalized["source_url"] = canonical_url(normalized.get("source_url", ""))
    normalized["url"] = canonical_url(normalized.get("url", ""))

    row_map = {col: "" for col in inbox_header}
    for col in inbox_header:
        if col in normalized:
            row_map[col] = normalized[col]

    return [row_map.get(col, "") for col in inbox_header]


def write_summary(total_input: int, added: int, deduped: int, skipped_invalid: int, invalid_details: list[str]) -> None:
    lines = [
        "MANUAL KI EVENT INTAKE SUMMARY",
        f"- input_items: {total_input}",
        f"- added: {added}",
        f"- deduped: {deduped}",
        f"- skipped_invalid: {skipped_invalid}",
    ]

    if invalid_details:
        lines.append("- invalid_details:")
        lines.extend([f"  - {detail}" for detail in invalid_details])

    summary = "\n".join(lines)
    print(summary)

    step_summary_path = norm(os.environ.get("GITHUB_STEP_SUMMARY", ""))
    if step_summary_path:
        Path(step_summary_path).write_text(summary + "\n", encoding="utf-8")


def main() -> None:
    spreadsheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info(f"Lese Manual-JSON: {MANUAL_JSON_PATH}")
    manual_items = load_manual_json(MANUAL_JSON_PATH)

    info(f"Lese Sheet-Tab '{TAB_INBOX}'")
    inbox_values = read_tab(service, spreadsheet_id, TAB_INBOX)
    inbox_header, inbox_rows = sheet_rows_to_dicts(inbox_values)

    if not inbox_header:
        fail("Inbox-Tab hat keine Header-Zeile.")

    missing_columns = [col for col in INBOX_COLUMNS if col not in inbox_header]
    if missing_columns:
        fail(f"Inbox-Header unvollständig. Fehlende Spalten: {missing_columns}")

    existing_source_urls, existing_fps = build_existing_indexes(inbox_rows)

    batch_source_urls: set[str] = set()
    batch_fps: set[str] = set()
    rows_to_append: list[list[str]] = []
    invalid_details: list[str] = []

    added = 0
    deduped = 0
    skipped_invalid = 0
    created_at = now_iso()

    for idx, item in enumerate(manual_items, start=1):
        ok, reason = validate_item(item, idx)
        if not ok:
            skipped_invalid += 1
            invalid_details.append(reason)
            continue

        source_url_key = norm_key(canonical_url(item.get("source_url", "")))
        fp = dedupe_fp(item.get("title", ""), item.get("date", ""), item.get("location", ""))

        if source_url_key in existing_source_urls or source_url_key in batch_source_urls:
            deduped += 1
            continue

        if fp in existing_fps or fp in batch_fps:
            deduped += 1
            continue

        rows_to_append.append(build_output_row(item, inbox_header, created_at))
        batch_source_urls.add(source_url_key)
        batch_fps.add(fp)
        added += 1

    append_rows(service, spreadsheet_id, TAB_INBOX, rows_to_append)
    write_summary(
        total_input=len(manual_items),
        added=added,
        deduped=deduped,
        skipped_invalid=skipped_invalid,
        invalid_details=invalid_details,
    )


if __name__ == "__main__":
    main()
