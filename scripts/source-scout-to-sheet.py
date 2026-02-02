# === BEGIN BLOCK: SOURCE SCOUT TO SHEET (weekly source candidates, facts-only links) ===
# Datei: scripts/source-scout-to-sheet.py
# Zweck:
# - Liest Seeds aus Google Sheet Tab "Source_Seeds" (enabled=TRUE)
# - Ruft jede Seed-URL 1x ab (kein Deep-Crawl) und extrahiert Links (href)
# - Filtert auf "eventverdächtige" URLs (veranstaltungen/kalender/termine/ics/ical/rss)
# - Schreibt neue Kandidaten dedupliziert in Tab "Source_Candidates"
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
#
# Rechtlich/konservativ:
# - Es werden KEINE Eventinhalte extrahiert oder gespeichert, nur Kandidaten-URLs + Metadaten.
# - Kein Suchmaschinen-Scraping, nur deine selbst gepflegten Seed-Seiten.
# - Sehr niedrige Last: 1 Request pro Seed (plus kleine Pause).
# === END BLOCK: SOURCE SCOUT TO SHEET (weekly source candidates, facts-only links) ===

from __future__ import annotations

import json
import os
import re
import sys
import time
from datetime import datetime
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

from google.oauth2 import service_account
from googleapiclient.discovery import build


TAB_SEEDS = "Source_Seeds"
TAB_CANDIDATES = "Source_Candidates"


KEYWORDS = [
    "veranstaltung",
    "veranstaltungen",
    "veranstaltungskalender",
    "termine",
    "kalender",
    "event",
    "events",
    "ical",
    "ics",
    "rss",
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


def fetch_html(url: str, timeout_s: int = 20) -> str:
    req = Request(
        url,
        headers={
            "User-Agent": "bocholt-erleben-source-scout/1.0 (+contact: admin@bocholt-erleben.de)"
        },
    )
    with urlopen(req, timeout=timeout_s) as resp:
        ctype = (resp.headers.get("Content-Type") or "").lower()
        if "text/html" not in ctype and "application/xhtml" not in ctype:
            return ""
        data = resp.read()
        # best-effort decode
        for enc in ("utf-8", "iso-8859-1", "cp1252"):
            try:
                return data.decode(enc, errors="replace")
            except Exception:
                pass
        return data.decode("utf-8", errors="replace")


def extract_links(html: str) -> list[str]:
    # konservativ: nur href="..."
    return re.findall(r'href=["\']([^"\']+)["\']', html, flags=re.IGNORECASE)


def is_candidate_url(u: str) -> tuple[bool, str, float]:
    """
    Returns (is_candidate, hint, confidence)
    """
    lu = u.lower()
    hits = [k for k in KEYWORDS if k in lu]
    if not hits:
        return (False, "", 0.0)

    # simple confidence: more hits => higher; prefer direct calendar hints
    conf = min(1.0, 0.25 + 0.15 * len(hits))
    hint = ",".join(sorted(set(hits))[:4])

    if any(x in lu for x in ("ical", ".ics", "rss", "feed")):
        conf = min(1.0, conf + 0.25)

    return (True, hint, conf)


def domain_of(url: str) -> str:
    try:
        return urlparse(url).netloc.lower()
    except Exception:
        return ""


def main() -> None:
    spreadsheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info("Lese Source_Seeds + Source_Candidates + Source_Blocklist …")

    seeds_values = read_tab(service, spreadsheet_id, TAB_SEEDS)
    cand_values = read_tab(service, spreadsheet_id, TAB_CANDIDATES)
    block_values = read_tab(service, spreadsheet_id, "Source_Blocklist")

    seeds_header = sheet_header(seeds_values)
    cand_header = sheet_header(cand_values)
    block_header = sheet_header(block_values)

    # Blocklist laden
    patterns = []
    if block_values:
        pat_idx = block_header.index("pattern")
        for row in block_values[1:]:
            if pat_idx < len(row):
                p = norm(row[pat_idx])
                if p:
                    patterns.append(p.lower())

    info(f"Blocklist patterns: {len(patterns)}")

    # bestehende URLs merken
    cand_url_idx = cand_header.index("candidate_url")
    existing = set()
    for row in cand_values[1:]:
        if cand_url_idx < len(row):
            u = norm(row[cand_url_idx])
            if u:
                existing.add(norm_key(u))

    enabled_idx = seeds_header.index("enabled")
    url_idx = seeds_header.index("seed_url")

    seeds = []
    for row in seeds_values[1:]:
        if enabled_idx >= len(row) or url_idx >= len(row):
            continue
        if norm_key(row[enabled_idx]) not in ("true", "1", "yes", "ja", "y"):
            continue
        u = norm(row[url_idx])
        if u:
            seeds.append(u)

    out_rows = []

    status_col = cand_header.index("status")
    domain_col = cand_header.index("source_domain")
    found_on_col = cand_header.index("found_on")
    hint_col = cand_header.index("hint")
    conf_col = cand_header.index("confidence")
    notes_col = cand_header.index("notes")
    created_col = cand_header.index("created_at")

    def is_blocked(url: str) -> bool:
        lu = url.lower()
        return any(p in lu for p in patterns)

    def mk_row(candidate_url: str, found_on: str, hint: str, conf: float):
        row = [""] * len(cand_header)

        if is_blocked(candidate_url):
            row[status_col] = "blockiert"
            row[notes_col] = "auto-blocked by pattern"
        else:
            row[status_col] = "neu"

        row[cand_url_idx] = candidate_url
        row[domain_col] = domain_of(candidate_url)
        row[found_on_col] = found_on
        row[hint_col] = hint
        row[conf_col] = f"{conf:.2f}"
        row[created_col] = now_iso()

        return row

    for seed in seeds:
        info(f"Fetch seed: {seed}")
        html = fetch_html(seed)
        links = extract_links(html)

        for href in links:
            abs_url = urljoin(seed, href)

            ok, hint, conf = is_candidate_url(abs_url)
            if not ok:
                continue

            key = norm_key(abs_url)
            if key in existing:
                continue

            existing.add(key)
            out_rows.append(mk_row(abs_url, seed, hint, conf))

        time.sleep(1)

    info(f"Neue Kandidaten: {len(out_rows)}")

    if out_rows:
        append_rows(service, spreadsheet_id, TAB_CANDIDATES, out_rows)
        info("✅ Kandidaten gespeichert.")
    else:
        info("✅ Keine neuen Kandidaten.")
