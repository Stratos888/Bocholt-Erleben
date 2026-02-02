# === BEGIN BLOCK: INBOX ARCHIVE + CLEANUP (sheet-driven, safe, v0) ===
# Datei: scripts/inbox-archive-cleanup.py
# Zweck:
# - Verschiebt (archiviert) alle Inbox-Zeilen mit status ∈ {"übernommen","verworfen"} nach "Inbox_Archive"
# - Entfernt diese Zeilen anschließend aus "Inbox" (Header bleibt erhalten)
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
#
# Voraussetzungen:
# - Google Sheet enthält Tabs: "Inbox" und "Inbox_Archive"
#
# Sicherheit:
# - Archivieren passiert vor dem Löschen (kein Datenverlust bei Fehlern)
# - Löschen erfolgt in absteigender Reihenfolge der Zeilenindizes
# === END BLOCK: INBOX ARCHIVE + CLEANUP (sheet-driven, safe, v0) ===

from __future__ import annotations

import json
import os
import re
import sys
from typing import Dict, List, Tuple

from google.oauth2 import service_account
from googleapiclient.discovery import build


TAB_INBOX = "Inbox"
TAB_ARCHIVE = "Inbox_Archive"

FINAL_STATUSES = {"übernommen", "verworfen"}


def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def norm(s: str) -> str:
    return (s or "").strip()


def norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", norm(s)).lower()


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
    try:
        res = service.spreadsheets().values().get(spreadsheetId=sheet_id, range=rng).execute()
        return res.get("values", []) or []
    except Exception as e:
        fail(f"Tab '{tab_name}' konnte nicht gelesen werden. Existiert er? ({e})")


def append_rows(service: object, sheet_id: str, tab_name: str, rows: List[List[str]]) -> None:
    body = {"values": rows}
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A1",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body=body,
    ).execute()


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


def get_sheet_id_by_title(service: object, spreadsheet_id: str, title: str) -> int:
    meta = service.spreadsheets().get(spreadsheetId=spreadsheet_id).execute()
    sheets = meta.get("sheets", []) or []
    for sh in sheets:
        props = sh.get("properties", {})
        if props.get("title") == title:
            return int(props.get("sheetId"))
    fail(f"Sheet-Tab '{title}' nicht gefunden. Bitte Tab anlegen.")


def delete_rows(service: object, spreadsheet_id: str, sheet_id: int, row_indices_1based: List[int]) -> None:
    """
    Löscht komplette Zeilen in einem Sheet-Tab.
    row_indices_1based: Datenzeilen als 1-based Zeilennummern in Google Sheets (inkl. Header=1).
    Wir löschen Datenzeilen >1 und lassen Header stehen.
    """
    if not row_indices_1based:
        return

    # In batchUpdate sind startIndex/endIndex 0-based, endIndex exklusiv.
    # Wir löschen in absteigender Reihenfolge, damit Indizes stabil bleiben.
    requests = []
    for r in sorted(row_indices_1based, reverse=True):
        # r ist 1-based; 0-based index = r-1
        start = r - 1
        end = r
        requests.append(
            {
                "deleteDimension": {
                    "range": {
                        "sheetId": sheet_id,
                        "dimension": "ROWS",
                        "startIndex": start,
                        "endIndex": end,
                    }
                }
            }
        )

    service.spreadsheets().batchUpdate(
        spreadsheetId=spreadsheet_id,
        body={"requests": requests},
    ).execute()


def main() -> None:
    spreadsheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info("Lese Inbox + Inbox_Archive …")
    inbox_values = read_tab(service, spreadsheet_id, TAB_INBOX)
    archive_values = read_tab(service, spreadsheet_id, TAB_ARCHIVE)

    inbox_header, inbox_rows = sheet_rows_to_dicts(inbox_values)
    archive_header, _ = sheet_rows_to_dicts(archive_values)

    if not inbox_header:
        fail("Inbox hat keine Headerzeile.")
    if "status" not in inbox_header:
        fail("Inbox: Spalte 'status' fehlt.")

    # Falls Archive leer ist, schreiben wir zuerst den Header
    archive_needs_header = not archive_values or not archive_header
    rows_to_append: List[List[str]] = []
    rows_to_delete_1based: List[int] = []  # Sheet row numbers (1-based)

    status_key = "status"

    for i, row in enumerate(inbox_rows):
        # i ist 0-based innerhalb inbox_rows (ohne Header)
        sheet_row_number = i + 2  # +1 Header, +1 1-based
        status = norm_key(row.get(status_key, ""))

        if status in FINAL_STATUSES:
            # Zeile in originaler Spaltenreihenfolge (Inbox-Header) archivieren
            rows_to_append.append([row.get(col, "") for col in inbox_header])
            rows_to_delete_1based.append(sheet_row_number)

    info(f"Archiv-Kandidaten: {len(rows_to_append)}")

    if not rows_to_append:
        info("✅ Nichts zu archivieren.")
        return

    # 1) Archive append (Header ggf. zuerst)
    if archive_needs_header:
        info("Archive ist leer → schreibe Header.")
        append_rows(service, spreadsheet_id, TAB_ARCHIVE, [inbox_header])

    append_rows(service, spreadsheet_id, TAB_ARCHIVE, rows_to_append)
    info("✅ Inbox_Archive: Zeilen appended.")

    # 2) Delete from Inbox (nach erfolgreichem Append)
    inbox_sheet_id = get_sheet_id_by_title(service, spreadsheet_id, TAB_INBOX)
    delete_rows(service, spreadsheet_id, inbox_sheet_id, rows_to_delete_1based)
    info("✅ Inbox: archivierte Zeilen gelöscht (Header bleibt).")


if __name__ == "__main__":
    main()
