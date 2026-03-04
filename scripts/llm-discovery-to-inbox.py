# === BEGIN BLOCK: LLM DISCOVERY TO INBOX (Playwright collector + minimal extraction) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck:
# - Parallel zum Legacy-Prozess: LLM-Pipeline (pipeline_mode=="llm") betreiben, ohne Legacy zu verändern.
# - Phase 1 (Collector): Browser-basiertes Laden (Playwright) inkl. Pagination und Detail-URL-Splitting.
# - Phase 2 (Extraction): Minimal-Extraktion aus Detailseiten (ohne externen LLM), um Inbox befüllbar zu machen.
# - Schreibt:
#   - Source_Health: Status + candidates_count + new_rows_written
#   - Discovery_Candidates: pro Detail-URL eine Zeile (reason=llm_candidate_detail_url)
#   - Inbox: pro Detail-URL eine Zeile mit best-effort Feldern (title/date/time/location)
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
# - TAB_SOURCES, TAB_SOURCE_HEALTH, TAB_DISCOVERY_CANDIDATES, TAB_INBOX
#
# Hinweise:
# - Cloudflare: Ziel ist, dass Chromium (Playwright) durchkommt, wo requests 403 bekommt.
# - Das ist bewusst robust/heuristisch; Feldqualität wird später durch LLM Extraction ersetzt.
# === END BLOCK: LLM DISCOVERY TO INBOX (Playwright collector + minimal extraction) ===

from __future__ import annotations

# === BEGIN REPLACEMENT BLOCK: imports — LLM DISCOVERY | Scope: add hashlib + url normalization helpers ===
import json
import os
import re
import hashlib
from dataclasses import dataclass
from datetime import datetime, timedelta, date
from typing import Any, Dict, List, Optional, Set
from urllib.parse import urljoin, urlparse, parse_qsl, urlencode, urlunparse
# === END REPLACEMENT BLOCK: imports — LLM DISCOVERY | Scope: add hashlib + url normalization helpers ===

from bs4 import BeautifulSoup
from playwright.async_api import async_playwright
# Optional dependency: script must run even if openai is not installed (fallback extractor)
try:
    from openai import OpenAI  # type: ignore
except Exception:
    OpenAI = None  # type: ignore

from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: SHEET TAB CONFIG (ENV override) ===
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
TAB_SOURCE_HEALTH = os.environ.get("TAB_SOURCE_HEALTH", "Source_Health")
TAB_DISCOVERY_CANDIDATES = os.environ.get("TAB_DISCOVERY_CANDIDATES", "Discovery_Candidates")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
# === END BLOCK: SHEET TAB CONFIG (ENV override) ===


# Columns must match existing tabs (we append rows, so only ordering matters)
SOURCE_HEALTH_COLUMNS = [
    "source_name",
    "type",
    "url",
    "status",
    "http_status",
    "error",
    "last_checked_at",
    "candidates_count",
    "new_rows_written",
]

DISCOVERY_CANDIDATES_COLUMNS = [
    "run_ts",
    "source_name",
    "source_type",
    "source_url",
    "status",
    "reason",
    "is_written_to_inbox",
    "is_already_live",
    "is_already_inbox",
    "matched_event_id",
    "match_score",
    "fingerprint",
    "id_suggestion",
    "title",
    "event_date",
    "endDate",
    "time",
    "city",
    "location",
    "kategorie_suggestion",
    "url",
    "notes",
    "created_at",
]


# === BEGIN BLOCK: INBOX COLUMNS (stable) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck: Spaltenreihenfolge des Google Sheet Tabs "Inbox" (wie in discovery-to-inbox.py)
# Umfang: Definiert nur INBOX_COLUMNS.
# === END BLOCK: INBOX COLUMNS (stable) ===
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


@dataclass
class SourceCfg:
    enabled: bool
    source_name: str
    source_type: str
    url: str
    default_city: str
    default_category: str
    pipeline_mode: str
    horizon_days: int
    max_pages: int
    include_detail_pages: bool
    llm_batch_size: int


def truthy(v: str) -> bool:
    return str(v).strip().lower() in ("true", "1", "yes", "y", "ja")


def env_flag(name: str, default: str = "false") -> bool:
    return truthy(os.environ.get(name, default))


def safe_int(v: str, default: int) -> int:
    try:
        vv = str(v).strip()
        if vv == "":
            return default
        return int(float(vv))
    except Exception:
        return default


# === BEGIN BLOCK: RSS COLLECTOR HELPERS (llm pipeline) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck: RSS/Atom Feed lesen, Items normalisieren (title/date/url/description), um LLM-Pipeline auch für RSS zu nutzen.
# Umfang: Helper-Funktionen + collect_rss_items(cfg). Keine Roh-HTML Speicherung.
# === END BLOCK: RSS COLLECTOR HELPERS (llm pipeline) ===
import html as _html_mod
import xml.etree.ElementTree as _ET
from email.utils import parsedate_to_datetime as _parsedate_to_datetime

import requests


def _rss_strip_tags(s: str) -> str:
    s = (s or "").strip()
    if not s:
        return ""
    s = re.sub(r"<[^>]+>", " ", s)
    s = _html_mod.unescape(s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def _rss_to_yyyy_mm_dd(dt_str: str) -> str:
    dt_str = (dt_str or "").strip()
    if not dt_str:
        return ""
    try:
        dt = _parsedate_to_datetime(dt_str)
        return dt.date().isoformat()
    except Exception:
        pass
    m = re.match(r"^(\d{4}-\d{2}-\d{2})", dt_str)
    return m.group(1) if m else ""


def collect_rss_items(cfg: "SourceCfg") -> Dict[str, Any]:
    """
    Returns:
      {
        "detail_urls": [...],
        "items_by_url": {url: fields_dict},
        "http_status_last": "200",
        "error": ""
      }
    fields_dict keys match make_inbox_row usage.
    """
    feed_url = cfg.url
    http_status_last = ""
    error_msg = ""
    items_by_url: Dict[str, Dict[str, str]] = {}

    try:
        r = requests.get(
            feed_url,
            timeout=30,
            headers={
                "User-Agent": (
                    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
                    "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
                ),
                "Accept": "application/rss+xml, application/atom+xml, application/xml;q=0.9, text/xml;q=0.8, */*;q=0.7",
            },
        )
        http_status_last = str(r.status_code)
        if r.status_code >= 400:
            return {
                "detail_urls": [],
                "items_by_url": {},
                "http_status_last": http_status_last,
                "error": f"rss_fetch_non_200:{r.status_code}",
            }

        root = _ET.fromstring(r.content or b"")

        # RSS 2.0: <rss><channel><item>...
        channel = root.find("channel")
        if channel is not None:
            for it in channel.findall("item"):
                link = (it.findtext("link") or "").strip()
                title = (it.findtext("title") or "").strip()
                pub = (it.findtext("pubDate") or "").strip()
                desc = (it.findtext("description") or "").strip()

                if not link:
                    continue

                items_by_url[link] = {
                    "title": title,
                    "date": _rss_to_yyyy_mm_dd(pub),
                    "time": "",
                    "city": cfg.default_city or "Bocholt",
                    "location": cfg.default_city or "Bocholt",
                    "kategorie_suggestion": cfg.default_category or "",
                    "url": link,
                    "description": _rss_strip_tags(desc)[:500],
                }

        else:
            # Atom: <feed><entry>...
            ns = ""
            if root.tag.startswith("{") and "}" in root.tag:
                ns = root.tag.split("}", 1)[0] + "}"

            for entry in root.findall(f"{ns}entry"):
                title = (entry.findtext(f"{ns}title") or "").strip()
                updated = (entry.findtext(f"{ns}updated") or "").strip() or (entry.findtext(f"{ns}published") or "").strip()

                link = ""
                for l in entry.findall(f"{ns}link"):
                    href = (l.attrib.get("href") or "").strip()
                    rel = (l.attrib.get("rel") or "").strip()
                    if href and (rel in ("", "alternate")):
                        link = href
                        break
                if not link:
                    continue

                summary = (entry.findtext(f"{ns}summary") or "").strip()
                content = (entry.findtext(f"{ns}content") or "").strip()
                desc = summary or content

                items_by_url[link] = {
                    "title": title,
                    "date": _rss_to_yyyy_mm_dd(updated),
                    "time": "",
                    "city": cfg.default_city or "Bocholt",
                    "location": cfg.default_city or "Bocholt",
                    "kategorie_suggestion": cfg.default_category or "",
                    "url": link,
                    "description": _rss_strip_tags(desc)[:500],
                }

    except Exception as e:
        error_msg = f"rss_parse_error:{e}"

    detail_urls = sorted(items_by_url.keys())
    return {
        "detail_urls": detail_urls,
        "items_by_url": items_by_url,
        "http_status_last": http_status_last,
        "error": error_msg,
    }


def build_sheets_service() -> Any:
    sheet_id = os.environ.get("SHEET_ID")
    sa_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not sheet_id:
        raise RuntimeError("Missing env SHEET_ID")
    if not sa_json:
        raise RuntimeError("Missing env GOOGLE_SERVICE_ACCOUNT_JSON")
    info = json.loads(sa_json)
    creds = service_account.Credentials.from_service_account_info(
        info, scopes=["https://www.googleapis.com/auth/spreadsheets"]
    )
    return build("sheets", "v4", credentials=creds), sheet_id


def read_tab(service: Any, sheet_id: str, tab_name: str) -> List[List[str]]:
    resp = (
        service.spreadsheets()
        .values()
        .get(spreadsheetId=sheet_id, range=f"{tab_name}!A:ZZ")
        .execute()
    )
    return resp.get("values", [])


def append_rows(service: Any, sheet_id: str, tab_name: str, rows: List[List[str]]) -> None:
    if not rows:
        return
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A:ZZ",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body={"values": rows},
    ).execute()


def rows_to_dicts(values: List[List[str]]) -> List[Dict[str, str]]:
    if not values:
        return []
    header = values[0]
    out: List[Dict[str, str]] = []
    for r in values[1:]:
        row = {header[i]: (r[i] if i < len(r) else "") for i in range(len(header))}
        out.append(row)
    return out


def parse_sources(values: List[List[str]]) -> List[SourceCfg]:
    rows = rows_to_dicts(values)
    sources: List[SourceCfg] = []
    for s in rows:
        enabled = truthy(s.get("enabled", "false"))
        pipeline_mode = (s.get("pipeline_mode", "") or "").strip().lower()
        if not enabled or pipeline_mode != "llm":
            continue

        sources.append(
            SourceCfg(
                enabled=True,
                source_name=s.get("source_name", "").strip(),
                source_type=s.get("type", "").strip().lower(),
                url=s.get("url", "").strip(),
                default_city=s.get("default_city", "").strip() or "Bocholt",
                default_category=s.get("default_category", "").strip(),
                pipeline_mode=pipeline_mode,
                horizon_days=safe_int(s.get("horizon_days", ""), 180),
                max_pages=safe_int(s.get("max_pages", ""), 3),
                include_detail_pages=truthy(s.get("include_detail_pages", "false")),
                llm_batch_size=safe_int(s.get("llm_batch_size", ""), 10),
            )
        )
    return sources


NEXT_TEXT_HINTS = ("weiter", "nächste", "naechste", "next", ">", "»")
BLOCKED_EXTS = (".pdf", ".jpg", ".jpeg", ".png", ".zip", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx")
# === BEGIN REPLACEMENT BLOCK: url normalization + tracking filters — LLM DISCOVERY | Scope: stable dedupe/fingerprints ===
BLOCKED_QUERY_HINTS = ("download=1",)

TRACKING_QUERY_KEYS = {
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
    "gclid",
    "fbclid",
    "mc_cid",
    "mc_eid",
    "cmpid",
    "pk_campaign",
    "pk_kwd",
    "pk_source",
    "pk_medium",
    "ref",
    "referrer",
}

def normalize_url(u: str) -> str:
    """Normalize URL for dedupe (remove fragment + common tracking params; keep meaningful params like event=)."""
    try:
        p = urlparse((u or "").strip())
        if not p.scheme or not p.netloc:
            return (u or "").strip()

        qs = []
        for k, v in parse_qsl(p.query, keep_blank_values=True):
            kk = (k or "").strip().lower()
            if kk in TRACKING_QUERY_KEYS:
                continue
            qs.append((k, v))

        new_query = urlencode(qs, doseq=True)
        # drop fragment
        p2 = p._replace(query=new_query, fragment="")
        return urlunparse(p2).strip()
    except Exception:
        return (u or "").strip()


def extract_datetime_from_url(u: str) -> Optional[Dict[str, str]]:
    """Some sites (e.g., isselburg.de) encode the real occurrence datetime in query param 'from'."""
    try:
        p = urlparse((u or "").strip())
        q = dict(parse_qsl(p.query, keep_blank_values=True))
        raw = (q.get("from") or "").strip()
        # expected: "YYYY-MM-DD HH:MM:SS"
        m = re.match(r"^(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})", raw)
        if not m:
            return None
        return {"date": m.group(1), "time": m.group(2)}
    except Exception:
        return None


# === BEGIN REPLACEMENT BLOCK: url_fingerprint + non-event filter — LLM DISCOVERY | Scope: stable fingerprint + block obvious non-events ===
def url_fingerprint(u: str) -> str:
    nu = normalize_url(u)
    return hashlib.sha1(nu.encode("utf-8", errors="ignore")).hexdigest()

NON_EVENT_TITLE_HINTS = (
    "cookie",
    "cookies",
    "datenschutz",
    "privacy",
    "impressum",
    "kontakt",
    "login",
    "anmeldung",
    "registrierung",
    "newsletter",
    "sitemap",
    "agb",
    "terms",
)

NON_EVENT_URL_HINTS = (
    "/cookie",
    "/cookies",
    "/datenschutz",
    "/privacy",
    "/impressum",
    "/kontakt",
    "/contact",
    "/login",
    "/signin",
    "/signup",
    "/register",
    "/anmeldung",
    "/registrierung",
    "/newsletter",
    "/sitemap",
    "/agb",
    "/terms",
)

def is_non_event_fields(fields: Dict[str, str], url: str) -> bool:
    t = (fields.get("title") or "").strip().lower()
    u = (url or "").strip().lower()
    loc = (fields.get("location") or "").strip().lower()

    if any(h in t for h in NON_EVENT_TITLE_HINTS):
        return True
    if any(h in u for h in NON_EVENT_URL_HINTS):
        return True

    # Noise that already appeared in your Inbox
    if "local storage" in loc or "rc::" in loc:
        return True

    # Münsterland listing/hub pages that are not a single event
    if "events in the münsterland" in t:
        return True

    return False
# === END REPLACEMENT BLOCK: url_fingerprint + non-event filter — LLM DISCOVERY | Scope: stable fingerprint + block obvious non-events ===

def _is_safe_nav_url(listing_url: str, candidate_url: str) -> bool:
# === END REPLACEMENT BLOCK: url normalization + tracking filters — LLM DISCOVERY | Scope: stable dedupe/fingerprints ===
    try:
        lu = urlparse(listing_url)
        cu = urlparse(candidate_url)
        if not cu.scheme.startswith("http"):
            return False
        if lu.netloc != cu.netloc:
            return False
        path = (cu.path or "").lower()
        if any(path.endswith(ext) for ext in BLOCKED_EXTS):
            return False
        q = (cu.query or "").lower()
        if any(h in q for h in BLOCKED_QUERY_HINTS):
            return False
        return True
    except Exception:
        return False


def find_next_url(base_url: str, soup: BeautifulSoup) -> Optional[str]:
    # 1) rel=next
    a = soup.find("a", attrs={"rel": re.compile(r"\bnext\b", re.I)})
    if a and a.get("href"):
        cand = urljoin(base_url, a["href"])
        return cand if _is_safe_nav_url(base_url, cand) else None

    # 2) aria-label contains next/weiter
    for a in soup.find_all("a"):
        label = (a.get("aria-label") or "").strip().lower()
        if any(h in label for h in ("next", "weiter", "nächste", "naechste")) and a.get("href"):
            cand = urljoin(base_url, a["href"])
            if _is_safe_nav_url(base_url, cand):
                return cand

    # 3) link text heuristic
    for a in soup.find_all("a"):
        text = (a.get_text(" ", strip=True) or "").strip().lower()
        if any(h in text for h in NEXT_TEXT_HINTS) and a.get("href"):
            href = a["href"]
            if href and href != "#" and "javascript:" not in href.lower():
                cand = urljoin(base_url, href)
                if _is_safe_nav_url(base_url, cand):
                    return cand

    return None


# === BEGIN REPLACEMENT BLOCK: DETAIL_PATH_HINTS — LLM DISCOVERY | Scope: expand path hints for aggregators ===
DETAIL_PATH_HINTS = (
    "/veranstaltungskalender/",
    "/veranstaltungen/",
    "/event/",
    "/events/",
    "/termin/",
    "/termine/",
    "/agenda/",
    "/kalender/",
    "/eventkalender/",
)
# === END REPLACEMENT BLOCK: DETAIL_PATH_HINTS — LLM DISCOVERY | Scope: expand path hints for aggregators ===

# === BEGIN REPLACEMENT BLOCK: EXCLUDE_PATH_HINTS — LLM DISCOVERY | Scope: block non-event utility/legal/cookie pages ===
EXCLUDE_PATH_HINTS = (
    "/bocholt_media/",
    "/media/",
    "/cookie",
    "/cookies",
    "/datenschutz",
    "/privacy",
    "/impressum",
    "/kontakt",
    "/contact",
    "/login",
    "/signin",
    "/signup",
    "/register",
    "/anmeldung",
    "/registrierung",
    "/newsletter",
    "/agb",
    "/terms",
    "/sitemap",
)
# === END REPLACEMENT BLOCK: EXCLUDE_PATH_HINTS — LLM DISCOVERY | Scope: block non-event utility/legal/cookie pages ===

# === BEGIN REPLACEMENT BLOCK: is_probable_detail_url — LLM DISCOVERY | Scope: stronger heuristics for aggregator detail pages ===
def is_probable_detail_url(listing_url: str, candidate_url: str) -> bool:
    """Return True if candidate_url looks like an event/detail page for listing_url host.

    Design goals:
    - Stay conservative (avoid media/downloads, tracking, category/list pages)
    - Be less brittle for aggregators (numeric IDs, event query params, deeper paths)
    """
    try:
        u = urlparse(candidate_url)
        if not (u.scheme or "").startswith("http"):
            return False

        # same host only
        lu = urlparse(listing_url)
        if lu.netloc != u.netloc:
            return False

        # reject same listing root
        if candidate_url.rstrip("/") == listing_url.rstrip("/"):
            return False

        path = (u.path or "").lower()
        q = (u.query or "").lower()

        # exclude media/downloads
        if any(x in path for x in EXCLUDE_PATH_HINTS):
            return False
        if any(path.endswith(ext) for ext in BLOCKED_EXTS):
            return False
        if any(h in q for h in BLOCKED_QUERY_HINTS):
            return False

        # Bocholt-Kalender: echte Events haben "?event=<id>"
        if "bocholt.de" in lu.netloc and "/veranstaltungskalender/" in path:
            return "event=" in q

        # Strong positive signals
        has_path_hint = any(h in path for h in DETAIL_PATH_HINTS)
        has_event_query = any(k in q for k in ("event=", "termin=", "id=", "eid=", "eventid="))
        has_numeric_id = bool(re.search(r"/(\d{4,})(?:/|$)", path))

        # Avoid obvious listing/category pages
        looks_like_listing = bool(
            re.search(r"/(kategorie|kategorien|rubrik|suche|search|archiv|archive)(?:/|$)", path)
        )

        if looks_like_listing and not (has_event_query or has_numeric_id):
            return False

        if has_event_query or has_numeric_id or has_path_hint:
            # keep a minimum path length to avoid /events/ root pages
            return len(path.strip("/")) >= 4

        return False
    except Exception:
        return False
# === END REPLACEMENT BLOCK: is_probable_detail_url — LLM DISCOVERY | Scope: stronger heuristics for aggregator detail pages ===


def norm_text(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


# === BEGIN REPLACEMENT BLOCK: DATE/TIME EXTRACTION HELPERS — LLM DISCOVERY | Scope: robust date parsing for German sources ===
DATE_RE_DDMMYYYY = re.compile(r"\b(\d{1,2})\.(\d{1,2})\.(\d{2,4})\b")
DATE_RE_ISO = re.compile(r"\b(\d{4}-\d{2}-\d{2})\b")
DATE_RE_DDMM_NOYEAR = re.compile(r"\b(\d{1,2})\.(\d{1,2})\.(?!\d)")  # dd.mm. (no year)
TIME_RE = re.compile(r"\b(\d{1,2}:\d{2})\b")

_MONTHS_DE = {
    "januar": 1,
    "jan": 1,
    "februar": 2,
    "feb": 2,
    "maerz": 3,
    "märz": 3,
    "mrz": 3,
    "april": 4,
    "apr": 4,
    "mai": 5,
    "juni": 6,
    "jun": 6,
    "juli": 7,
    "jul": 7,
    "august": 8,
    "aug": 8,
    "september": 9,
    "sep": 9,
    "sept": 9,
    "oktober": 10,
    "okt": 10,
    "november": 11,
    "nov": 11,
    "dezember": 12,
    "dez": 12,
}

DATE_RE_MONTHNAME = re.compile(
    r"\b(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*(\d{4})?\b",
    re.UNICODE,
)


def _normalize_month_token(s: str) -> str:
    t = (s or "").strip().lower()
    t = (
        t.replace("ä", "ae")
        .replace("ö", "oe")
        .replace("ü", "ue")
        .replace("ß", "ss")
    )
    return t


def _safe_yyyy_mm_dd(year: int, month: int, day: int) -> Optional[str]:
    try:
        d = datetime(year, month, day).date()
        return d.strftime("%Y-%m-%d")
    except Exception:
        return None


def _choose_year_for_partial_date(day: int, month: int, today: "date") -> int:
    """If year is missing, assume current year, but roll to next year if the date is clearly in the past."""
    y = today.year
    try:
        cand = datetime(y, month, day).date()
        if cand < (today - timedelta(days=14)):
            return y + 1
        return y
    except Exception:
        return y


def _try_jsonld_event_date(soup: BeautifulSoup) -> Optional[str]:
    """Try to extract Event.startDate from JSON-LD."""
    scripts = soup.find_all("script", attrs={"type": re.compile(r"application/ld\+json", re.I)})
    for s in scripts:
        raw = (s.string or "").strip()
        if not raw:
            continue
        try:
            data = json.loads(raw)
        except Exception:
            continue

        stack: List[Any] = [data]
        while stack:
            cur = stack.pop()
            if isinstance(cur, list):
                stack.extend(cur)
                continue
            if isinstance(cur, dict):
                t = cur.get("@type") or cur.get("type")
                if isinstance(t, list):
                    is_event = any(str(x).lower() == "event" for x in t)
                else:
                    is_event = str(t).lower() == "event"

                if is_event:
                    sd = (cur.get("startDate") or cur.get("startdate") or "").strip()
                    if sd:
                        m = DATE_RE_ISO.search(sd)
                        if m:
                            return m.group(1)
                        m2 = re.match(r"^(\d{4}-\d{2}-\d{2})", sd)
                        if m2:
                            return m2.group(1)

                for v in cur.values():
                    if isinstance(v, (dict, list)):
                        stack.append(v)

    return None


def extract_best_date(soup: BeautifulSoup, text_blob: str) -> str:
    """Best-effort extraction of a single event date in YYYY-MM-DD."""
    today = datetime.now().date()

    jd = _try_jsonld_event_date(soup)
    if jd:
        return jd

    t = soup.find("time")
    if t and t.get("datetime"):
        dt = norm_text(t.get("datetime", ""))
        m = DATE_RE_ISO.search(dt)
        if m:
            return m.group(1)

    meta = soup.find(attrs={"itemprop": re.compile(r"startdate", re.I)})
    if meta and meta.get("content"):
        m = DATE_RE_ISO.search(meta.get("content", ""))
        if m:
            return m.group(1)

    m = DATE_RE_ISO.search(text_blob)
    if m:
        return m.group(1)

    m = DATE_RE_DDMMYYYY.search(text_blob)
    if m:
        dd, mm, yy = int(m.group(1)), int(m.group(2)), m.group(3)
        y = int(yy) if len(yy) == 4 else int("20" + yy)
        out = _safe_yyyy_mm_dd(y, mm, dd)
        if out:
            return out

    m = DATE_RE_MONTHNAME.search(text_blob)
    if m:
        dd = int(m.group(1))
        month_tok = _normalize_month_token(m.group(2))
        month = _MONTHS_DE.get(month_tok)
        if month:
            if m.group(3):
                y = int(m.group(3))
            else:
                y = _choose_year_for_partial_date(dd, month, today)
            out = _safe_yyyy_mm_dd(y, month, dd)
            if out:
                return out

    m = DATE_RE_DDMM_NOYEAR.search(text_blob)
    if m:
        dd, mm = int(m.group(1)), int(m.group(2))
        y = _choose_year_for_partial_date(dd, mm, today)
        out = _safe_yyyy_mm_dd(y, mm, dd)
        if out:
            return out

    return ""
# === END REPLACEMENT BLOCK: DATE/TIME EXTRACTION HELPERS — LLM DISCOVERY | Scope: robust date parsing for German sources ===


# === BEGIN REPLACEMENT BLOCK: extract_event_fields — LLM DISCOVERY | Scope: structured selectors first, better title/date/location ===
def extract_event_fields(detail_html: str, detail_url: str, cfg: SourceCfg) -> Dict[str, str]:
    """Heuristic fallback extraction (used if LLM is not configured or fails)."""
    soup = BeautifulSoup(detail_html, "html.parser")

    def sel_text(css: str) -> str:
        el = soup.select_one(css)
        return norm_text(el.get_text(" ", strip=True)) if el else ""

    # title (structured first)
    title = (
        sel_text(".detail-title")
        or sel_text("h1")
        or sel_text("h2")
    )
    if not title:
        og = soup.find("meta", property="og:title")
        if og and og.get("content"):
            title = norm_text(og["content"])

    # remove noisy parts before building text_blob (cookie/storage/scripts)
    for tag in soup(["script", "style", "noscript"]):
        tag.decompose()

    text_blob = norm_text(soup.get_text(" ", strip=True))
    # common noise patterns that poisoned location/date on some pages
    text_blob = re.sub(r"\brc::[a-z]\b", " ", text_blob)
    text_blob = re.sub(r"\bLocal Storage\b.*", " ", text_blob, flags=re.IGNORECASE)
    text_blob = norm_text(text_blob)

    # date/time (structured first)
    datetime_text = sel_text(".detail-meta-row.is-datetime .detail-meta-text")
    date = ""
    if datetime_text:
        date = extract_best_date(BeautifulSoup(detail_html, "html.parser"), datetime_text) or extract_best_date(
            BeautifulSoup(detail_html, "html.parser"), text_blob
        )
    else:
        date = extract_best_date(BeautifulSoup(detail_html, "html.parser"), text_blob)

    # time (prefer structured datetime text; keep simple start time)
    time = ""
    if datetime_text:
        mt = TIME_RE.search(datetime_text)
        if mt:
            time = mt.group(1)
    if not time:
        mt = TIME_RE.search(text_blob)
        if mt:
            time = mt.group(1)

    # override for sources that encode occurrence datetime in URL query (?from=YYYY-MM-DD HH:MM:SS)
    dtq = extract_datetime_from_url(detail_url)
    if dtq:
        if dtq.get("date"):
            date = dtq["date"]
        if dtq.get("time"):
            time = dtq["time"]

    # location (structured first, then heuristic)
    location = sel_text(".detail-meta-row.is-location .detail-meta-text")
    if not location:
        for label in ("Ort", "Veranstaltungsort", "Location"):
            idx = text_blob.lower().find(label.lower())
            if idx != -1:
                snippet = text_blob[idx : idx + 160]
                if ":" in snippet:
                    snippet = snippet.split(":", 1)[1]
                location = norm_text(snippet)[:120]
                break

    # guard: avoid navigation/utility text becoming "location" (seen on isselburg.de)
    if location and any(
        h in location.lower()
        for h in (
            "bildung",
            "schulen",
            "hochwasserschutz",
            "kommunalwahl",
            "notfall",
            "notfallnummern",
            "feuerwehr",
        )
    ):
        location = ""

    if not location and "bocholt" in text_blob.lower():
        location = "Bocholt"

    description = ""
    ogd = soup.find("meta", property="og:description")
    if ogd and ogd.get("content"):
        description = norm_text(ogd["content"])[:500]

    return {
        "title": title,
        "date": date,
        "time": time,
        "city": cfg.default_city or "Bocholt",
        "location": location,
        "kategorie_suggestion": cfg.default_category or "",
        "url": detail_url,
        "description": description,
    }
# === END REPLACEMENT BLOCK: extract_event_fields — LLM DISCOVERY | Scope: structured selectors first, better title/date/location ===


def _build_llm_input(detail_html: str, detail_url: str, cfg: SourceCfg) -> str:
    soup = BeautifulSoup(detail_html, "html.parser")
    for tag in soup(["script", "style", "noscript"]):
        tag.decompose()

    text = norm_text(soup.get_text(" ", strip=True))
    # hard cap to keep costs predictable
    text = text[:60000]

    return (
        f"Quelle (Detail-URL): {detail_url}\n"
        f"Default-Stadt: {cfg.default_city or 'Bocholt'}\n"
        f"Default-Kategorie: {cfg.default_category or ''}\n"
        "Extrahiere Eventdaten aus folgendem Text (HTML in Text umgewandelt):\n"
        f"{text}\n"
    )


def llm_extract_event_fields(detail_html: str, detail_url: str, cfg: SourceCfg) -> Optional[Dict[str, str]]:
    if OpenAI is None:
        return None
    api_key = os.environ.get("OPENAI_API_KEY", "").strip()
    if not api_key:
        return None

    model = os.environ.get("OPENAI_MODEL", "gpt-5-mini").strip()
    client = OpenAI(api_key=api_key)

    llm_input = _build_llm_input(detail_html, detail_url, cfg)

    schema = {
        "type": "object",
        "additionalProperties": False,
        "properties": {
            "title": {"type": "string"},
            "date": {"type": "string", "description": "YYYY-MM-DD oder leer"},
            "time": {"type": "string", "description": "z.B. 19:30 oder leer"},
            "location": {"type": "string"},
            "city": {"type": "string"},
            "category": {"type": "string"},
            "url": {"type": "string"},
            "description": {"type": "string"},
        },
        "required": ["title", "date", "time", "location", "city", "category", "url", "description"],
    }

    try:
        resp = client.responses.create(
            model=model,
            input=[
                {
                    "role": "system",
                    "content": (
                        "Du extrahierst Veranstaltungsdaten. "
                        "Antworte ausschließlich im vorgegebenen JSON-Schema. "
                        "Wenn ein Feld nicht sicher ist, gib einen leeren String zurück."
                    ),
                },
                {"role": "user", "content": llm_input},
            ],
            text={
                "format": {
                    "type": "json_schema",
                    "name": "event_extraction",
                    "strict": True,
                    "schema": schema,
                }
            },
            store=False,
        )

        out = (resp.output_text or "").strip()
        if not out:
            return None
        data = json.loads(out)

        data["url"] = detail_url
        if not (data.get("city") or "").strip():
            data["city"] = cfg.default_city or "Bocholt"
        if not (data.get("category") or "").strip():
            data["category"] = cfg.default_category or ""

        fields = {
            "title": (data.get("title") or "").strip(),
            "date": (data.get("date") or "").strip(),
            "time": (data.get("time") or "").strip(),
            "location": (data.get("location") or "").strip(),
            "city": (data.get("city") or "").strip(),
            "kategorie_suggestion": (data.get("category") or "").strip(),
            "url": detail_url,
            "description": (data.get("description") or "").strip(),
        }

        # override for sources that encode occurrence datetime in URL query (?from=YYYY-MM-DD HH:MM:SS)
        dtq = extract_datetime_from_url(detail_url)
        if dtq:
            if dtq.get("date"):
                fields["date"] = dtq["date"]
            if dtq.get("time"):
                fields["time"] = dtq["time"]

        return fields
    except Exception:
        return None


async def collect_detail_urls_playwright(cfg: SourceCfg) -> Dict[str, Any]:
    # === BEGIN BLOCK: Collector (Playwright) with CF-proof logging ===
    listing_url = cfg.url
    detail_urls: Set[str] = set()

    http_status_first = ""
    http_status_last = ""
    error_msg = ""

    # Proof fields
    nav2_method = ""
    nav2_url = ""
    nav2_status = ""
    nav2_cf_mitigated = ""
    nav2_cf_ray = ""
    nav2_server = ""
    nav2_location = ""

    debug_cf = os.environ.get("DEBUG_CF", "").strip().lower() in ("1", "true", "yes")

    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--disable-blink-features=AutomationControlled",
            ],
        )
        context = await browser.new_context(
            user_agent=(
                "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
            ),
            locale="de-DE",
            accept_downloads=False,
        )
        page = await context.new_page()

        # First navigation
        try:
            resp = await page.goto(listing_url, wait_until="domcontentloaded", timeout=60_000)
            if resp is not None:
                http_status_first = str(resp.status)
                http_status_last = http_status_first
            if http_status_first and int(http_status_first) >= 400:
                error_msg = f"non_200_on_navigation:first={http_status_first}"
                await context.close()
                await browser.close()
                return {
                    "listing_url": listing_url,
                    "pages_visited": 0,
                    "detail_urls": [],
                    "http_status_last": http_status_last,
                    "http_status_first": http_status_first,
                    "error": error_msg,
                    "nav2_method": nav2_method,
                    "nav2_url": nav2_url,
                    "nav2_status": nav2_status,
                    "nav2_cf_mitigated": nav2_cf_mitigated,
                    "nav2_cf_ray": nav2_cf_ray,
                    "nav2_server": nav2_server,
                    "nav2_location": nav2_location,
                }
        except Exception as e:
            error_msg = f"playwright_fetch_error:first={e}"
            await context.close()
            await browser.close()
            return {
                "listing_url": listing_url,
                "pages_visited": 0,
                "detail_urls": [],
                "http_status_last": http_status_last,
                "http_status_first": http_status_first,
                "error": error_msg,
                "nav2_method": nav2_method,
                "nav2_url": nav2_url,
                "nav2_status": nav2_status,
                "nav2_cf_mitigated": nav2_cf_mitigated,
                "nav2_cf_ray": nav2_cf_ray,
                "nav2_server": nav2_server,
                "nav2_location": nav2_location,
            }

        pages_visited = 0

        for page_idx in range(max(cfg.max_pages, 1)):
            pages_visited += 1

            await page.wait_for_timeout(1200)
            html = await page.content()
            soup = BeautifulSoup(html, "html.parser")

            for a in soup.find_all("a", href=True):
                href = urljoin(listing_url, a["href"])
                if is_probable_detail_url(listing_url, href):
                    detail_urls.add(href)

            if page_idx >= max(cfg.max_pages, 1) - 1:
                break

            # Bocholt pagination: POST via button[aria-label="Nächste Seite"]
            next_btn = await page.query_selector('button[aria-label="Nächste Seite"]')
            if not next_btn:
                break

            try:
                is_disabled = await next_btn.evaluate(
                    "btn => btn.closest('.pagination__item')?.classList.contains('disabled') || btn.disabled === true"
                )
                if is_disabled:
                    break
            except Exception:
                pass

            # Click and capture the navigation response for proof
            try:
                async with page.expect_navigation(wait_until="domcontentloaded", timeout=60_000) as nav:
                    await next_btn.click()
                nav_resp = await nav.value

                if nav_resp is not None:
                    req = nav_resp.request
                    nav2_method = (req.method or "")
                    nav2_url = (req.url or "")
                    nav2_status = str(nav_resp.status)
                    http_status_last = nav2_status

                    h = nav_resp.headers or {}
                    nav2_cf_mitigated = h.get("cf-mitigated", "")
                    nav2_cf_ray = h.get("cf-ray", "")
                    nav2_server = h.get("server", "")
                    nav2_location = h.get("location", "")

                    if debug_cf:
                        print(f"[CF-PROOF] nav2 method={nav2_method} status={nav2_status}")
                        print(f"[CF-PROOF] nav2 url={nav2_url}")
                        print(f"[CF-PROOF] cf-mitigated={nav2_cf_mitigated} cf-ray={nav2_cf_ray} server={nav2_server} location={nav2_location}")

                    if int(nav2_status) >= 400:
                        error_msg = (
                            f"non_200_on_navigation: {nav2_status} "
                            f"(method={nav2_method}, cf-mitigated={nav2_cf_mitigated}, server={nav2_server})"
                        )
                        break
            except Exception as e:
                error_msg = f"pagination_click_error:{e}"
                break

        await context.close()
        await browser.close()

    return {
        "listing_url": listing_url,
        "pages_visited": pages_visited,
        "detail_urls": sorted(detail_urls),
        "http_status_last": http_status_last,
        "http_status_first": http_status_first,
        "error": error_msg,
        "nav2_method": nav2_method,
        "nav2_url": nav2_url,
        "nav2_status": nav2_status,
        "nav2_cf_mitigated": nav2_cf_mitigated,
        "nav2_cf_ray": nav2_cf_ray,
        "nav2_server": nav2_server,
        "nav2_location": nav2_location,
    }
  

# === BEGIN REPLACEMENT BLOCK: make_candidate_row — LLM DISCOVERY | Scope: real flags + stable fingerprint ===
def make_candidate_row(
    run_ts: str,
    cfg: SourceCfg,
    detail_url: str,
    *,
    is_already_inbox: bool,
    is_written_to_inbox: bool,
    notes: str,
) -> List[str]:
    created_at = run_ts
    return [
        run_ts,
        cfg.source_name,
        cfg.source_type,
        cfg.url,
        "candidate",
        "llm_candidate_detail_url",
        "TRUE" if is_written_to_inbox else "FALSE",
        "FALSE",  # is_already_live
        "TRUE" if is_already_inbox else "FALSE",
        "",  # matched_event_id
        "",  # match_score
        url_fingerprint(detail_url),  # fingerprint (stable)
        "",  # id_suggestion
        "",  # title
        "",  # event_date
        "",  # endDate
        "",  # time
        cfg.default_city or "Bocholt",  # city
        "",  # location
        cfg.default_category or "",  # kategorie_suggestion
        detail_url,  # url (raw)
        (notes or "")[:240],
        created_at,
    ]
# === END REPLACEMENT BLOCK: make_candidate_row — LLM DISCOVERY | Scope: real flags + stable fingerprint ===


def make_health_row(
    cfg: SourceCfg,
    status: str,
    http_status: str,
    error: str,
    run_ts: str,
    candidates_count: int,
    new_rows_written: int,
) -> List[str]:
    # Keep error short for Sheets cell readability
    error_short = (error or "").strip()
    if len(error_short) > 240:
        error_short = error_short[:240] + "…"
    return [
        cfg.source_name,
        cfg.source_type,
        cfg.url,
        status,
        http_status,
        error_short,
        run_ts,
        str(candidates_count),
        str(new_rows_written),
    ]


def make_inbox_row(run_ts: str, cfg: SourceCfg, fields: Dict[str, str]) -> List[str]:
    return [
        "neu",  # status
        "",  # id_suggestion
        fields.get("title", ""),
        fields.get("date", ""),
        "",  # endDate
        fields.get("time", ""),
        fields.get("city", cfg.default_city or "Bocholt"),
        fields.get("location", ""),
        fields.get("kategorie_suggestion", cfg.default_category or ""),
        fields.get("url", ""),
        fields.get("description", ""),
        cfg.source_name,
        cfg.url,  # source_url (listing)
        "",  # match_score
        "",  # matched_event_id
# === BEGIN REPLACEMENT BLOCK: inbox notes — LLM DISCOVERY | Scope: truthful LLM+fallback note ===
        "llm_pipeline (OpenAI LLM + heuristic fallback; html via Playwright detail fetch)",
# === END REPLACEMENT BLOCK: inbox notes — LLM DISCOVERY | Scope: truthful LLM+fallback note ===
        run_ts,
    ]


async def main_async() -> None:
    service, sheet_id = build_sheets_service()
    run_ts = datetime.utcnow().isoformat(timespec="seconds") + "Z"

    sources_values = read_tab(service, sheet_id, TAB_SOURCES)
    llm_sources = parse_sources(sources_values)

    if not llm_sources:
        append_rows(
            service,
            sheet_id,
            TAB_SOURCE_HEALTH,
            [
                [
                    "LLM_COLLECT",
                    "n/a",
                    "n/a",
                    "llm_collect_none",
                    "",
                    "No enabled sources with pipeline_mode=llm",
                    run_ts,
                    "0",
                    "0",
                ]
            ],
        )
        print("LLM collector: no sources selected")
        return

    health_rows: List[List[str]] = []
    candidate_rows: List[List[str]] = []
    inbox_rows: List[List[str]] = []

    # Dedupe against existing Inbox URLs (best effort)
    # Can be disabled for controlled end-to-end tests via ENV DISABLE_INBOX_DEDUPE=true
    existing_inbox_urls: Set[str] = set()
    disable_inbox_dedupe = env_flag("DISABLE_INBOX_DEDUPE", "false")

    if not disable_inbox_dedupe:
        try:
            inbox_values = read_tab(service, sheet_id, TAB_INBOX)
            if inbox_values and len(inbox_values) > 1:
                header = inbox_values[0]
                url_idx = header.index("url") if "url" in header else None
                if url_idx is not None:
                    for r in inbox_values[1:]:
                        if url_idx < len(r) and r[url_idx]:
                            existing_inbox_urls.add(normalize_url(r[url_idx].strip()))
        except Exception:
            pass

    for cfg in llm_sources:
        details: List[str] = []
        http_status_first = ""
        err = ""
        rss_items_by_url: Dict[str, Dict[str, str]] = {}

        # === BEGIN BLOCK: Collector switch (html vs rss) ===
        if cfg.source_type == "html":
            res = await collect_detail_urls_playwright(cfg)
            details = res["detail_urls"]
            http_status_last = res["http_status_last"]
            http_status_first = res.get("http_status_first", "") or http_status_last

            # Append CF proof summary into error (short)
            err = res["error"]
            if not err and res.get("nav2_status") and int(res["nav2_status"] or "0") >= 400:
                err = f"nav2_non_200:{res.get('nav2_status')} method={res.get('nav2_method')} cf-mitigated={res.get('nav2_cf_mitigated')}"

        elif cfg.source_type == "rss":
            res = collect_rss_items(cfg)
            details = res["detail_urls"]
            rss_items_by_url = res["items_by_url"]
            http_status_last = res["http_status_last"]
            http_status_first = http_status_last
            err = res["error"]

        else:
            health_rows.append(
                make_health_row(
                    cfg,
                    "llm_collect_skip_unsupported_type",
                    "",
                    f"Unsupported type for llm pipeline: {cfg.source_type}",
                    run_ts,
                    0,
                    0,
                )
            )
            continue
        # === END BLOCK: Collector switch (html vs rss) ===

        # We treat llm_batch_size as "target number of NEW inbox rows" per run.
        target_new = max(cfg.llm_batch_size, 10)
        new_inbox_written = 0

        # Log candidates for all collected detail URLs (collector proof),
        # but only write up to target_new NEW items to Inbox (skip duplicates).
        if cfg.source_type == "html":
            # Reuse a single browser/context/page for all detail fetches of this source (major speed-up)
            async with async_playwright() as p_det:
                browser_det = await p_det.chromium.launch(
                    headless=True,
                    args=[
                        "--no-sandbox",
                        "--disable-dev-shm-usage",
                        "--disable-blink-features=AutomationControlled",
                    ],
                )
                context_det = await browser_det.new_context(
                    user_agent=(
                        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                        "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
                    ),
                    locale="de-DE",
                    accept_downloads=False,
                )
                page_det = await context_det.new_page()

                for u in details:
                    nu = normalize_url(u)
                    already_inbox = (not disable_inbox_dedupe) and (nu in existing_inbox_urls)

                    will_write = False
                    note = "collector_only (playwright)"
                    if already_inbox:
                        note = "collector_only (dup: already_inbox)"
                    elif new_inbox_written >= target_new:
                        note = "collector_only (cap reached)"
                    else:
                        try:
                            await page_det.goto(u, wait_until="domcontentloaded", timeout=60_000)
                            await page_det.wait_for_timeout(1200)
                            detail_html = await page_det.content()

                            fields = llm_extract_event_fields(detail_html, u, cfg)
                            if fields is None:
                                fields = extract_event_fields(detail_html, u, cfg)

                            # === BEGIN REPLACEMENT BLOCK: write gate — LLM DISCOVERY | Scope: skip non-event utility/legal pages ===
                            # Pflichtfelder-Gate (minimal, robust)
                            if not fields.get("title") or not fields.get("date"):
                                note = "skipped (missing title/date)"
                            elif is_non_event_fields(fields, u):
                                note = "skipped (non_event_page)"
                            else:
                                if not fields.get("location"):
                                    fields["location"] = cfg.default_city or "Bocholt"

                                inbox_rows.append(make_inbox_row(run_ts, cfg, fields))
                                existing_inbox_urls.add(nu)
                                new_inbox_written += 1
                                will_write = True
                                note = "written_to_inbox"
                            # === END REPLACEMENT BLOCK: write gate — LLM DISCOVERY | Scope: skip non-event utility/legal pages ===
                        except Exception:
                            note = "skipped (detail_fetch_or_extract_error)"

                    candidate_rows.append(
                        make_candidate_row(
                            run_ts,
                            cfg,
                            u,
                            is_already_inbox=already_inbox,
                            is_written_to_inbox=will_write,
                            notes=note,
                        )
                    )

                await context_det.close()
                await browser_det.close()

        else:
            # RSS: no detail fetch; write directly using feed fields
            for u in details:
                nu = normalize_url(u)
                already_inbox = (not disable_inbox_dedupe) and (nu in existing_inbox_urls)

                will_write = False
                note = "collector_only (rss)"
                if already_inbox:
                    note = "collector_only (dup: already_inbox)"
                elif new_inbox_written >= target_new:
                    note = "collector_only (cap reached)"
                else:
                    try:
                        fields = (rss_items_by_url.get(u) or {}).copy()
                        fields["url"] = u

                        # === BEGIN REPLACEMENT BLOCK: rss write gate — LLM DISCOVERY | Scope: skip non-event utility/legal pages ===
                        # Pflichtfelder-Gate (minimal, robust)
                        if not fields.get("title") or not fields.get("date"):
                            note = "skipped (missing title/date)"
                        elif is_non_event_fields(fields, u):
                            note = "skipped (non_event_page)"
                        else:
                            if not fields.get("location"):
                                fields["location"] = cfg.default_city or "Bocholt"
                            if not fields.get("city"):
                                fields["city"] = cfg.default_city or "Bocholt"
                            if not fields.get("kategorie_suggestion"):
                                fields["kategorie_suggestion"] = cfg.default_category or ""

                            inbox_rows.append(make_inbox_row(run_ts, cfg, fields))
                            existing_inbox_urls.add(nu)
                            new_inbox_written += 1
                            will_write = True
                            note = "written_to_inbox"
                        # === END REPLACEMENT BLOCK: rss write gate — LLM DISCOVERY | Scope: skip non-event utility/legal pages ===
                    except Exception:
                        note = "skipped (rss_parse_or_write_error)"

                candidate_rows.append(
                    make_candidate_row(
                        run_ts,
                        cfg,
                        u,
                        is_already_inbox=already_inbox,
                        is_written_to_inbox=will_write,
                        notes=note,
                    )
                )

        if err:
            status = "llm_collect_fetch_error"
        else:
            status = "llm_collect_ok" if new_inbox_written > 0 else "llm_collect_ok_no_new"

        health_rows.append(
# === END REPLACEMENT BLOCK: per-source reuse + candidate flags — LLM DISCOVERY | Scope: performance + better logging ===
            make_health_row(
                cfg,
                status,
                http_status_first,
                err,
                run_ts,
                len(details),
                new_inbox_written,
            )
        )

    append_rows(service, sheet_id, TAB_SOURCE_HEALTH, health_rows)
    append_rows(service, sheet_id, TAB_DISCOVERY_CANDIDATES, candidate_rows)
    append_rows(service, sheet_id, TAB_INBOX, inbox_rows)

    print(
        f"LLM Playwright collector done: sources={len(llm_sources)} candidates_logged={len(candidate_rows)} inbox_new={len(inbox_rows)}"
    )


if __name__ == "__main__":
    import asyncio

    asyncio.run(main_async())
