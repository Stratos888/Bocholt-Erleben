# === BEGIN BLOCK: LLM DISCOVERY TO INBOX (smoke test, sheet wired, no LLM yet) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck:
# - Parallel zum Legacy-Prozess: testet nur Verkabelung (Sheet Zugriff + Source-Auswahl).
# - Liest "Sources" und filtert enabled==TRUE und pipeline_mode=="llm".
# - Schreibt pro Quelle eine Zeile in "Source_Health" (status=llm_smoke_ok / llm_smoke_none).
# - Schreibt NOCH NICHT in Inbox und extrahiert noch keine Events (kommt in Step 3).
# Umfang:
# - Minimaler Google Sheets Read/Append, keine Parser-Logik.
# === END BLOCK: LLM DISCOVERY TO INBOX (smoke test, sheet wired, no LLM yet) ===

from __future__ import annotations

import json
import os
from datetime import datetime
from typing import List, Dict, Any

from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck: Tabs per ENV überschreibbar (Test/Prod ohne Codeänderung)
# === END BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
TAB_SOURCE_HEALTH = os.environ.get("TAB_SOURCE_HEALTH", "Source_Health")


# === BEGIN BLOCK: SHEET CLIENT (service account) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck: Google Sheets API Client initialisieren
# === END BLOCK: SHEET CLIENT (service account) ===
def build_sheets_service() -> Any:
    sheet_id = os.environ.get("SHEET_ID")
    sa_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")

    if not sheet_id:
        raise RuntimeError("Missing env SHEET_ID")
    if not sa_json:
        raise RuntimeError("Missing env GOOGLE_SERVICE_ACCOUNT_JSON")

    info = json.loads(sa_json)
    creds = service_account.Credentials.from_service_account_info(
        info,
        scopes=["https://www.googleapis.com/auth/spreadsheets"],
    )
    return build("sheets", "v4", credentials=creds), sheet_id


def read_tab(service: Any, sheet_id: str, tab_name: str) -> List[List[str]]:
    resp = service.spreadsheets().values().get(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A:Z",
    ).execute()
    return resp.get("values", [])


def append_rows(service: Any, sheet_id: str, tab_name: str, rows: List[List[str]]) -> None:
    if not rows:
        return
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A:Z",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body={"values": rows},
    ).execute()


# === BEGIN BLOCK: SOURCE PARSE (from Sources tab) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck: Sources tab in dicts mappen + LLM-quellen filtern
# === END BLOCK: SOURCE PARSE (from Sources tab) ===
def rows_to_dicts(values: List[List[str]]) -> List[Dict[str, str]]:
    if not values:
        return []
    header = values[0]
    out: List[Dict[str, str]] = []
    for r in values[1:]:
        row = {header[i]: (r[i] if i < len(r) else "") for i in range(len(header))}
        out.append(row)
    return out


def truthy(v: str) -> bool:
    return str(v).strip().lower() in ("true", "1", "yes", "y", "ja")


def main() -> None:
    service, sheet_id = build_sheets_service()
    now = datetime.utcnow().isoformat(timespec="seconds") + "Z"

    sources_raw = read_tab(service, sheet_id, TAB_SOURCES)
    sources = rows_to_dicts(sources_raw)

    llm_sources = []
    for s in sources:
        enabled = truthy(s.get("enabled", "false"))
        mode = (s.get("pipeline_mode", "") or "").strip().lower()
        if enabled and mode == "llm":
            llm_sources.append(s)

    # Write to Source_Health as a visible smoke-test proof
    rows = []
    if not llm_sources:
        rows.append([
            "LLM_SMOKE",
            "n/a",
            "n/a",
            "llm_smoke_none",
            "",
            "No enabled sources with pipeline_mode=llm",
            now,
            "0",
            "0",
        ])
    else:
        for s in llm_sources:
            rows.append([
                s.get("source_name", ""),
                s.get("type", ""),
                s.get("url", ""),
                "llm_smoke_ok",
                "",
                "Selected for LLM pipeline (no extraction yet)",
                now,
                "0",
                "0",
            ])

    append_rows(service, sheet_id, TAB_SOURCE_HEALTH, rows)
    print(f"LLM smoke test: selected_sources={len(llm_sources)}")


if __name__ == "__main__":
    main()
