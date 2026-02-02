# === BEGIN BLOCK: DISCOVERY TO INBOX (sheet-driven, safe, v0) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Liest Google Sheet Tabs: "Events" (LIVE), "Inbox" (Vorschläge), "Sources" (Quellen)
# - Discovery v0: Holt neue Events aus RSS und iCal(ICS), dedupliziert grob, schreibt NUR in "Inbox"
# - Niemals automatische Veröffentlichung (Events-Tab wird nicht beschrieben)
#
# Eingaben (ENV):
# - SHEET_ID: Google Sheet ID (der lange String in der URL)
# - GOOGLE_SERVICE_ACCOUNT_JSON: Service-Account JSON (als Secret), im ENV als String
#
# Verhalten:
# - Für jede enabled Source: fetch + parse -> Kandidaten
# - Dedupe gegen LIVE Events (url oder (title+date)) und gegen Inbox (source_url+date+title)
# - Schreibt neue Vorschläge als "status=neu" in Inbox, inkl. match_score/notes/created_at
# === END BLOCK: DISCOVERY TO INBOX (sheet-driven, safe, v0) ===

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.request
import xml.etree.ElementTree as ET
from datetime import datetime, date
from typing import Dict, List, Optional, Tuple

# Google API (wird im Workflow per pip installiert)
from google.oauth2 import service_account
from googleapiclient.discovery import build


TAB_EVENTS = "Events"
TAB_INBOX = "Inbox"
TAB_SOURCES = "Sources"

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


def safe_fetch(url: str, timeout: int = 20) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": "BocholtErlebenDiscovery/1.0"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        charset = resp.headers.get_content_charset() or "utf-8"
        return resp.read().decode(charset, errors="replace")


# -------------------------
# ICS (iCal) minimal parser
# -------------------------

def ics_unfold_lines(text: str) -> List[str]:
    # RFC5545 line folding: lines starting with space/tab are continuations
    lines = text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    out: List[str] = []
    for line in lines:
        if not line:
            continue
        if line.startswith((" ", "\t")) and out:
            out[-1] += line[1:]
        else:
            out.append(line)
    return out


def parse_ics_date(val: str) -> Tuple[Optional[str], Optional[str]]:
    """
    Returns (date_iso, time_str_optional)
    Handles:
      - DTSTART:20260204T193000
      - DTSTART;VALUE=DATE:20260204
      - DTSTART:20260204T193000Z  (treated as local date/time string)
    """
    v = val.strip()
    v = v.replace("Z", "")
    if re.fullmatch(r"\d{8}", v):
        d = datetime.strptime(v, "%Y%m%d").date()
        return (d.strftime("%Y-%m-%d"), "")
    m = re.fullmatch(r"(\d{8})T(\d{2})(\d{2})(\d{2})?", v)
    if not m
