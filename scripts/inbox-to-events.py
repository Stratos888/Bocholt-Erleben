# === BEGIN BLOCK: INBOX TO EVENTS IMPORTER (sheet-driven, safe, v0) ===
# Datei: scripts/inbox-to-events.py
# Zweck:
# - Liest Google Sheet Tabs: "Events" (LIVE), "Inbox" (Vorschläge)
# - Importiert ausschließlich Inbox-Zeilen mit status == "übernehmen" (case-insensitive)
# - Appendet in "Events" (in der Spaltenreihenfolge des Events-Headers)
# - Markiert importierte Inbox-Zeilen als status="übernommen" (damit kein Doppelimport passiert)
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
#
# WICHTIG:
# - Dieses Script schreibt NICHT in Repo-Dateien, nur ins Sheet.
# - Veröffentlichung passiert erst durch euren Deploy-Workflow (separat).
# === END BLOCK: INBOX TO EVENTS IMPORTER (sheet-driven, safe, v0) ===

from __future__ import annotations

import json
import os
import re
import sys
from datetime import datetime
from typing import Dict, List, Tuple

from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
# Datei: scripts/inbox-to-events.py
# Zweck:
# - Ziel-Tabs per ENV steuerbar machen, damit wir Events_Test nutzen können,
#   ohne Prod anzutasten.
# === END BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")



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


def slugify(s: str) -> str:
    s = norm_key(s)
    s = s.replace("–", "-").replace("—", "-")
    s = re.sub(r"[^\w\s-]", "", s, flags=re.UNICODE)
    s = re.sub(r"[\s_]+", "-", s)
    s = re.sub(r"-{2,}", "-", s).strip("-")
    return s or "event"


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


def read_tab(service: object, sheet_id: str, tab_name: str) -> List[List[str]]:
    rng = f"{tab_name}!A:ZZ"
    res = service.spreadsheets().values().get(spreadsheetId=sheet_id, range=rng).execute()
    return res.get("values", []) or []


def append_rows(service: object, sheet_id: str, tab_name: str, rows: List[List[str]]) -> None:
    body = {"values": rows}
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A1",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body=body,
    ).execute()


def update_cells(service: object, sheet_id: str, updates: List[Tuple[str, List[List[str]]]]) -> None:
    """
    updates: list of (A1_range, values_matrix)
    """
    if not updates:
        return
    data = [{"range": rng, "values": vals} for (rng, vals) in updates]
    body = {"valueInputOption": "RAW", "data": data}
    service.spreadsheets().values().batchUpdate(spreadsheetId=sheet_id, body=body).execute()


def sheet_rows_to_dicts(values: List[List[str]]) -> Tuple[List[str], List[Dict[str, str]]]:
    if not values:
        return ([], [])
    header = [norm(h) for h in values[0]]
    dicts: List[Dict[str, str]] = []
    for row in values[1:]:
        d: Dict[str, str] = {}
        for i, h in enumerate(header):
            d[h] = norm(row[i]) if i < len(row) else ""
        if any(v for v in d.values()):
            dicts.append(d)
    return (header, dicts)


# === BEGIN BLOCK: EXISTING EVENT INDEXES (dedupe safety, v1) ===
# Datei: scripts/inbox-to-events.py
# Zweck:
# - baut Indexe aus bestehendem Events-Tab, um Dubletten beim Import zu verhindern
# - bevorzugt URL-match, sonst (title+date+time+city+location)
# Umfang:
# - nur Leselogik/Indexe, keine Änderung an bestehenden Spalten oder Workflows
# === END BLOCK: EXISTING EVENT INDEXES (dedupe safety, v1) ===

def build_existing_ids(events: List[Dict[str, str]]) -> set:
    s = set()
    for e in events:
        if e.get("id"):
            s.add(norm_key(e["id"]))
    return s


def event_fingerprint(title: str, d: str, t: str, city: str, location: str, url: str) -> str:
    """
    Stabiler Vergleichsschlüssel:
    1) Wenn URL vorhanden -> URL dominiert (Tracking sollte idealerweise vorher schon gecleant sein).
    2) Sonst: title+date+time+city+location.
    """
    url = norm(url)
    if url:
        return f"url|{norm_key(url)}"
    return "|".join(
        [
            "txt",
            norm_key(title),
            norm(d),
            norm(t),
            norm_key(city),
            norm_key(location),
        ]
    )


def build_existing_fingerprint_map(events: List[Dict[str, str]]) -> Dict[str, str]:
    """
    return: fingerprint -> event_id (erste gefundene ID)
    """
    m: Dict[str, str] = {}
    for e in events:
        eid = norm(e.get("id", ""))
        fp = event_fingerprint(
            title=e.get("title", ""),
            d=e.get("date", ""),
            t=e.get("time", ""),
            city=e.get("city", ""),
            location=e.get("location", ""),
            url=e.get("url", ""),
        )
        if fp and fp not in m and eid:
            m[fp] = eid
    return m



def generate_unique_id(title: str, d: str, existing_ids: set) -> str:
    base = f"{slugify(title)}-{d}"
    cand = base
    n = 2
    while norm_key(cand) in existing_ids:
        cand = f"{base}-{n}"
        n += 1
    existing_ids.add(norm_key(cand))
    return cand


def main() -> None:
    sheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info("Lese Tabs aus Google Sheet …")
    events_values = read_tab(service, sheet_id, TAB_EVENTS)
    inbox_values = read_tab(service, sheet_id, TAB_INBOX)

    events_header, events_rows = sheet_rows_to_dicts(events_values)
    inbox_header, inbox_rows = sheet_rows_to_dicts(inbox_values)

    if not events_header:
        fail("Tab 'Events' hat keine Headerzeile.")
    if not inbox_header:
        fail("Tab 'Inbox' hat keine Headerzeile.")

    # Required columns for importer
    if "status" not in inbox_header:
        fail("Inbox: Spalte 'status' fehlt.")
    if "title" not in inbox_header or "date" not in inbox_header:
        fail("Inbox: Spalten 'title' und/oder 'date' fehlen.")

    existing_ids = build_existing_ids(events_rows)
    existing_fp_to_id = build_existing_fingerprint_map(events_rows)
    existing_fps = set(existing_fp_to_id.keys())


    # Build mapping from Inbox -> Events columns if present
    # Events columns are authoritative order.
    def build_event_row_from_inbox(inb: Dict[str, str]) -> List[str]:
        # Choose id: use id_suggestion if provided, else generate.
        inb_id = norm(inb.get("id_suggestion", ""))
        title = norm(inb.get("title", ""))
        d = norm(inb.get("date", ""))
        if not title or not d:
            return []

        if inb_id:
            eid = slugify(inb_id)
            # keep user-provided but still unique
            if norm_key(eid) in existing_ids:
                eid = generate_unique_id(title, d, existing_ids)
            else:
                existing_ids.add(norm_key(eid))
        else:
            eid = generate_unique_id(title, d, existing_ids)

        source_fields = {
            "id": eid,
            "title": title,
            "date": d,
            "endDate": norm(inb.get("endDate", "")),
            "time": norm(inb.get("time", "")),
            "city": norm(inb.get("city", "")),
            "location": norm(inb.get("location", "")),
            # Einige Sheets heißen "kategorie" im Events-Tab, Inbox hat "kategorie_suggestion"
            "kategorie": norm(inb.get("kategorie", "")) or norm(inb.get("kategorie_suggestion", "")),
            "url": norm(inb.get("url", "")) or norm(inb.get("source_url", "")),

            "description": norm(inb.get("description", "")),
        }

        row: List[str] = []
        for col in events_header:
            row.append(source_fields.get(col, ""))  # unknown columns stay blank
        return row

    # Determine which inbox rows to import (we need original row index for updates)
    # inbox_values includes header row at index 0. Data starts at row 2 in sheet.
    import_indices: List[int] = []
    import_rows: List[List[str]] = []

    for idx, inb in enumerate(inbox_rows):
        status = norm_key(inb.get("status", ""))
        if status != "übernehmen":
            continue

        # Dedupe-Safety: verhindert Doppelimporte, falls gleicher Event bereits im Events-Tab existiert.
        fp = event_fingerprint(
            title=inb.get("title", ""),
            d=inb.get("date", ""),
            t=inb.get("time", ""),
            city=inb.get("city", ""),
            location=inb.get("location", ""),
            url=inb.get("url", "") or inb.get("source_url", ""),
        )

        if fp in existing_fps:

            # Merken für Status-Update (duplikat), aber NICHT importieren.
            # Wir aktualisieren später in einem batchUpdate.
            if "___duplicate_rows" not in locals():
                ___duplicate_rows = []  # type: ignore[var-annotated]
            ___duplicate_rows.append((idx, existing_fp_to_id.get(fp, "")))  # type: ignore[name-defined]
            continue

        event_row = build_event_row_from_inbox(inb)
        if not event_row:
            continue

        # innerhalb des gleichen Runs ebenfalls Dubletten vermeiden
        existing_fps.add(fp)

        import_indices.append(idx)  # 0-based within inbox_rows (excluding header)
        import_rows.append(event_row)


    info(f"Import-Kandidaten (status=übernehmen): {len(import_rows)}")

    if not import_rows:
        info("✅ Nichts zu importieren.")
        return

    # Append to Events first
    append_rows(service, sheet_id, TAB_EVENTS, import_rows)
    info("✅ Events: neue Zeilen appended.")

    # Mark inbox rows as imported: set status="übernommen" and add note timestamp
    # Additionally: mark detected duplicates as status="duplikat"
    status_col = inbox_header.index("status")
    notes_col = inbox_header.index("notes") if "notes" in inbox_header else None
    matched_col = inbox_header.index("matched_event_id") if "matched_event_id" in inbox_header else None

    updates: List[Tuple[str, List[List[str]]]] = []

    # 1) imported rows
    for idx in import_indices:
        sheet_row_number = idx + 2  # +1 for header, +1 because sheet rows are 1-based
        status_a1 = f"{TAB_INBOX}!{col_to_a1(status_col)}{sheet_row_number}"
        updates.append((status_a1, [["übernommen"]]))
        if notes_col is not None:
            notes_a1 = f"{TAB_INBOX}!{col_to_a1(notes_col)}{sheet_row_number}"
            updates.append((notes_a1, [[f"imported {now_iso()}"]]))

    # 2) duplicate rows (if any)
    duplicate_rows = locals().get("___duplicate_rows", [])
    for (idx, existing_id) in duplicate_rows:
        sheet_row_number = idx + 2
        status_a1 = f"{TAB_INBOX}!{col_to_a1(status_col)}{sheet_row_number}"
        updates.append((status_a1, [["duplikat"]]))
        if matched_col is not None and existing_id:
            matched_a1 = f"{TAB_INBOX}!{col_to_a1(matched_col)}{sheet_row_number}"
            updates.append((matched_a1, [[existing_id]]))
        if notes_col is not None:
            notes_a1 = f"{TAB_INBOX}!{col_to_a1(notes_col)}{sheet_row_number}"
            updates.append((notes_a1, [[f"skipped duplicate {now_iso()}"]]))


    update_cells(service, sheet_id, updates)
    info("✅ Inbox: importierte Zeilen als 'übernommen' markiert.")

    return


def col_to_a1(idx: int) -> str:
    """0-based column index -> A1 column letters"""
    idx += 1
    letters = ""
    while idx:
        idx, rem = divmod(idx - 1, 26)
        letters = chr(65 + rem) + letters
    return letters


if __name__ == "__main__":
    main()
