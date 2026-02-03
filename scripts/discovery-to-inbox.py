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


# === BEGIN BLOCK: TEXT NORMALIZATION HELPERS (clean v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Saubere, stabile Textwerte in Inbox schreiben (Whitespace, HTML-Tags, Entities).
# Umfang: Nur Helper-Funktionen, keine Verhaltensänderung bei Status/Import-Flow.
# === END BLOCK: TEXT NORMALIZATION HELPERS (clean v1) ===

def norm(s: str) -> str:
    return (s or "").strip()


def norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", norm(s)).lower()


# === BEGIN BLOCK: CLEAN TEXT (strip html + normalize spaces) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Entfernt HTML-Tags, decodiert Entities, normalisiert Whitespace.
# Umfang: Reine Helferfunktionen (facts-only), keine Side-Effects.
# === END BLOCK: CLEAN TEXT (strip html + normalize spaces) ===
import html as _html


def strip_html(s: str) -> str:
    s = norm(s)
    if not s:
        return ""
    # Tags raus
    s = re.sub(r"<[^>]+>", " ", s)
    return s


def normalize_ws(s: str) -> str:
    s = norm(s)
    if not s:
        return ""
    s = re.sub(r"\s+", " ", s)
    return s.strip()


def clean_text(s: str) -> str:
    s = strip_html(s)
    s = _html.unescape(s)
    s = normalize_ws(s)
    return s



# === BEGIN BLOCK: SLUG + CANONICAL URL + ID SUGGESTION (dedupe v1) ===
# Zweck: robustes Dedupe (slug) + stabile id_suggestion + URL-Normalisierung (utm/v/fbclid etc.)
# Umfang: reine Hilfsfunktionen, keine Side-Effects

import hashlib
from urllib.parse import urlparse, urlunparse, parse_qsl, urlencode


def slugify(s: str) -> str:
    s = norm_key(s)

    # de-umlaut (konservativ)
    s = (
        s.replace("ä", "ae")
        .replace("ö", "oe")
        .replace("ü", "ue")
        .replace("ß", "ss")
    )

    # nur [a-z0-9-]
    s = re.sub(r"[^a-z0-9]+", "-", s)
    s = re.sub(r"-{2,}", "-", s).strip("-")
    return s


def canonical_url(u: str) -> str:
    u = norm(u)
    if not u:
        return ""

    try:
        p = urlparse(u)
    except Exception:
        return u

    # drop fragment
    fragment = ""

    # keep only non-tracking query params; also drop build/cache params
    drop_prefixes = ("utm_",)
    drop_keys = {"fbclid", "gclid", "yclid", "mc_cid", "mc_eid", "ref", "source", "v"}

    q = []
    for k, v in parse_qsl(p.query, keep_blank_values=True):
        lk = (k or "").lower()
        if any(lk.startswith(pref) for pref in drop_prefixes):
            continue
        if lk in drop_keys:
            continue
        q.append((k, v))

    query = urlencode(q, doseq=True)

    # normalize scheme/host casing
    netloc = (p.netloc or "").lower()
    scheme = (p.scheme or "").lower()

    return urlunparse((scheme, netloc, p.path, p.params, query, fragment))


def short_hash(s: str, n: int = 4) -> str:
    return hashlib.md5(s.encode("utf-8")).hexdigest()[:n]


def make_id_suggestion(title: str, d: str, t: str, source_url: str) -> str:
    """
    Stable, collision-resistant ID:
      <slug(title)>-<yyyymmdd>-<hash4>
    """
    st = slugify(title)
    if not st:
        st = "event"

    ymd = re.sub(r"[^0-9]", "", norm(d))  # "2026-03-15" -> "20260315"
    if len(ymd) != 8:
        ymd = "00000000"

    key = f"{norm_key(canonical_url(source_url))}|{st}|{norm(d)}|{norm(t)}"
    return f"{st[:60]}-{ymd}-{short_hash(key, 4)}"
# === END BLOCK: SLUG + CANONICAL URL + ID SUGGESTION (dedupe v1) ===



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
    if not m:
        return (None, None)
    d = datetime.strptime(m.group(1), "%Y%m%d").date()
    hh = m.group(2)
    mm = m.group(3)
    return (d.strftime("%Y-%m-%d"), f"{int(hh):02d}:{int(mm):02d}")


def parse_ics_events(ics_text: str) -> List[Dict[str, str]]:
    lines = ics_unfold_lines(ics_text)
    events: List[Dict[str, str]] = []
    cur: Dict[str, str] = {}
    in_event = False

    for line in lines:
        if line == "BEGIN:VEVENT":
            in_event = True
            cur = {}
            continue
        if line == "END:VEVENT":
            if cur:
                events.append(cur)
            in_event = False
            cur = {}
            continue
        if not in_event:
            continue

        if ":" not in line:
            continue
        k, v = line.split(":", 1)
        k = k.split(";", 1)[0].strip().upper()
        v = v.strip()

        # store first occurrence only (v0)
        if k not in cur:
            cur[k] = v

    out: List[Dict[str, str]] = []
    for e in events:
        title = norm(e.get("SUMMARY", ""))
        url = norm(e.get("URL", "")) or norm(e.get("LOCATION-URL", ""))
        location = norm(e.get("LOCATION", ""))
        desc = norm(e.get("DESCRIPTION", ""))

        dtstart = e.get("DTSTART", "")
        dtend = e.get("DTEND", "")

        d1, t1 = parse_ics_date(dtstart) if dtstart else (None, None)
        d2, t2 = parse_ics_date(dtend) if dtend else (None, None)

        if not d1:
            continue

        time_str = ""
        if t1 and t2:
            time_str = f"{t1}–{t2}"
        elif t1:
            time_str = t1

        out.append(
            {
                "title": title,
                "date": d1 or "",
                "endDate": d2 or "",
                "time": time_str,
                "location": location,
                "url": url,
                "description": desc,
            }
        )
    return out


# -------------------------
# RSS minimal parser
# -------------------------

def parse_rss(xml_text: str) -> List[Dict[str, str]]:
    # Works for RSS/Atom basic fields. Date extraction is heuristic (v0).
    xml_text = xml_text.strip()
    root = ET.fromstring(xml_text)

    # Atom
    ns = {"atom": "http://www.w3.org/2005/Atom"}
    entries = root.findall(".//atom:entry", ns)
    items = root.findall(".//item")

    out: List[Dict[str, str]] = []

    def parse_date_any(s: str) -> str:
        s = (s or "").strip()
        if not s:
            return ""
        # common: RFC2822 pubDate
        for fmt in ("%a, %d %b %Y %H:%M:%S %z", "%a, %d %b %Y %H:%M:%S %Z"):
            try:
                return datetime.strptime(s, fmt).date().strftime("%Y-%m-%d")
            except Exception:
                pass
        # ISO
        try:
            return datetime.fromisoformat(s.replace("Z", "+00:00")).date().strftime("%Y-%m-%d")
        except Exception:
            return ""

    if entries:
        for e in entries:
            title = norm(e.findtext("atom:title", default="", namespaces=ns))
            link_el = e.find("atom:link", ns)
            link = norm(link_el.attrib.get("href", "")) if link_el is not None else ""
            updated = norm(e.findtext("atom:updated", default="", namespaces=ns))
            published = norm(e.findtext("atom:published", default="", namespaces=ns))
            d = parse_date_any(published) or parse_date_any(updated)

            out.append(
                {
                    "title": title,
                    "date": d,
                    "endDate": "",
                    "time": "",
                    "location": "",
                    "url": link,
                    "description": "",
                }
            )

    if items:
        for it in items:
            title = norm(it.findtext("title", default=""))
            link = norm(it.findtext("link", default=""))
            pub = norm(it.findtext("pubDate", default=""))
            d = parse_date_any(pub)

            out.append(
                {
                    "title": title,
                    "date": d,
                    "endDate": "",
                    "time": "",
                    "location": "",
                    "url": link,
                    "description": "",
                }
            )

    # RSS is too ambiguous for event dates; v0 allows empty date -> we skip those
    return [x for x in out if x.get("date")]


# -------------------------
# Google Sheets helpers
# -------------------------

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


def sheet_rows_to_dicts(values: List[List[str]]) -> Tuple[List[str], List[Dict[str, str]]]:
    if not values:
        return ([], [])
    header = [norm(h) for h in values[0]]
    dicts: List[Dict[str, str]] = []
    for row in values[1:]:
        d: Dict[str, str] = {}
        for i, h in enumerate(header):
            d[h] = norm(row[i]) if i < len(row) else ""
        # ignore empty rows
        if any(v for v in d.values()):
            dicts.append(d)
    return (header, dicts)


# -------------------------
# Dedupe / matching (v0)
# -------------------------

# === BEGIN BLOCK: DEDUPE INDEXES (canonical url + slug+date) ===
def build_live_index(live_events: List[Dict[str, str]]) -> Tuple[Dict[str, str], Dict[Tuple[str, str], str]]:
    by_url: Dict[str, str] = {}
    by_slug_date: Dict[Tuple[str, str], str] = {}

    for ev in live_events:
        ev_id = ev.get("id", "")
        url = canonical_url(ev.get("url", ""))
        title = norm(ev.get("title", ""))
        d = norm(ev.get("date", ""))

        if url:
            by_url[norm_key(url)] = ev_id
        if title and d:
            by_slug_date[(slugify(title), d)] = ev_id

    return by_url, by_slug_date


def inbox_fingerprint(row: Dict[str, str]) -> Tuple[str, str, str]:
    return (
        norm_key(canonical_url(row.get("source_url", ""))),
        slugify(row.get("title", "")),
        norm(row.get("date", "")),
    )


def make_candidate_fingerprint(c: Dict[str, str], source_url: str) -> Tuple[str, str, str]:
    return (
        norm_key(canonical_url(source_url)),
        slugify(c.get("title", "")),
        norm(c.get("date", "")),
    )
# === END BLOCK: DEDUPE INDEXES (canonical url + slug+date) ===



# -------------------------
# Main discovery flow (v0)
# -------------------------

def main() -> None:
    sheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info("Lese Tabs aus Google Sheet …")
    events_values = read_tab(service, sheet_id, TAB_EVENTS)
    inbox_values = read_tab(service, sheet_id, TAB_INBOX)
    sources_values = read_tab(service, sheet_id, TAB_SOURCES)

    _, live_events = sheet_rows_to_dicts(events_values)
    _, inbox_rows = sheet_rows_to_dicts(inbox_values)
    sources_header, sources_rows = sheet_rows_to_dicts(sources_values)

    if not sources_header:
        fail("Tab 'Sources' ist leer oder hat keine Headerzeile.")

    # Inbox header sanity (optional but helpful)
    inbox_header = [norm(h) for h in (inbox_values[0] if inbox_values else [])]
    if inbox_header and inbox_header[: len(INBOX_COLUMNS)] != INBOX_COLUMNS:
        info("Hinweis: Inbox-Header weicht ab. Script schreibt trotzdem spaltenweise in definierter Reihenfolge.")

    live_by_url, live_by_title_date = build_live_index(live_events)

    existing_inbox_fps = set(inbox_fingerprint(r) for r in inbox_rows)

    enabled_sources = [s for s in sources_rows if norm(s.get("enabled", "")).upper() in ("TRUE", "1", "YES", "JA")]
    info(f"Sources enabled: {len(enabled_sources)}")

    new_inbox_rows: List[List[str]] = []
    total_candidates = 0
    total_written = 0

    for s in enabled_sources:
        source_name = norm(s.get("source_name", s.get("name", ""))) or "Source"
        stype = norm(s.get("type", "")).lower()
        url = norm(s.get("url", ""))
        default_city = norm(s.get("default_city", "")) or "Bocholt"
        default_cat = norm(s.get("default_category", ""))

        if not url or not stype:
            info(f"Skip source (missing type/url): {source_name}")
            continue

              info(f"Fetch: {source_name} ({stype})")

        # === BEGIN BLOCK: FETCH + PARSE DISPATCH (candidates defined, v1) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - candidates ist IMMER definiert (verhindert NameError)
        # - Fetch + Parse Dispatch nach Source-Typ (rss/ical)
        # Umfang:
        # - ersetzt ausschließlich den defekten try/except-Block vor total_candidates
        # === END BLOCK: FETCH + PARSE DISPATCH (candidates defined, v1) ===

        candidates: List[Dict[str, str]] = []
        try:
            content = safe_fetch(url)
            if stype in ("ical", "ics"):
                candidates = parse_ics_events(content)
            elif stype == "rss":
                candidates = parse_rss(content)
            else:
                info(f"Skip source (unsupported type): {source_name} ({stype})")
                candidates = []
        except Exception as e:
            info(f"Parse failed: {source_name}: {e}")
            candidates = []

        total_candidates += len(candidates)



        for c in candidates:
            title = clean_text(c.get("title", ""))
            d = norm(c.get("date", ""))
            if not title or not d:
                continue


            # === BEGIN BLOCK: URL NORMALIZATION (indent fix) ===
            # Zweck: Korrigiert Einrückung von c_url_raw, damit der Block syntaktisch stabil ist.
            # Umfang: Ersetzt nur die beiden Zeilen c_url_raw/c_url.
            c_url_raw = norm(c.get("url", ""))
            c_url = canonical_url(c_url_raw)
            # === END BLOCK: URL NORMALIZATION (indent fix) ===


            matched_id = ""
            score = 0.0

            if c_url and norm_key(c_url) in live_by_url:
                matched_id = live_by_url[norm_key(c_url)]
                score = 1.0
            else:
                key = (slugify(title), d)
                if key in live_by_title_date:
                    matched_id = live_by_title_date[key]
                    score = 0.95


            # Already live -> ignore
            if matched_id:
                continue

            # Already in inbox -> ignore
            fp = make_candidate_fingerprint(c, url)
            if fp in existing_inbox_fps:
                continue

            # Write to inbox as suggestion
            row = {
                "status": "neu",
                "id_suggestion": make_id_suggestion(title, d, norm(c.get("time", "")), url),
                "title": title,
                "date": d,
                "endDate": norm(c.get("endDate", "")),
                "time": norm(c.get("time", "")),
                "city": default_city,
                "location": clean_text(c.get("location", "")),
                "kategorie_suggestion": default_cat,
                "url": c_url,
                "description": clean_text(c.get("description", "")),

                "source_name": source_name,
                "source_url": url,
                "match_score": f"{score:.2f}",
                "matched_event_id": "",
                "notes": "",
                "created_at": now_iso(),
            }

            new_inbox_rows.append([row.get(col, "") for col in INBOX_COLUMNS])
            existing_inbox_fps.add(fp)
            total_written += 1

        # Gentle rate-limit to be nice to sources
        time.sleep(1)

    info(f"Candidates parsed: {total_candidates}")
    info(f"New inbox rows: {total_written}")

    if new_inbox_rows:
        append_rows(service, sheet_id, TAB_INBOX, new_inbox_rows)
        info("✅ Inbox updated.")
    else:
        info("✅ Nothing new to add.")

    return


if __name__ == "__main__":
    main()
