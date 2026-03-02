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

import json
import os
import re
from dataclasses import dataclass
from datetime import datetime
from typing import Any, Dict, List, Optional, Set
from urllib.parse import urljoin, urlparse

from bs4 import BeautifulSoup
from playwright.async_api import async_playwright
from openai import OpenAI

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
BLOCKED_QUERY_HINTS = ("download=1",)


def _is_safe_nav_url(listing_url: str, candidate_url: str) -> bool:
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


DETAIL_PATH_HINTS = (
    "/veranstaltungskalender/",
    "/event/",
    "/events/",
    "/termine/",
    "/kalender/",
)

EXCLUDE_PATH_HINTS = (
    "/bocholt_media/",
    "/media/",
)

def is_probable_detail_url(listing_url: str, candidate_url: str) -> bool:
    try:
        u = urlparse(candidate_url)
        if not u.scheme.startswith("http"):
            return False

        # same host only
        lu = urlparse(listing_url)
        if lu.netloc != u.netloc:
            return False

        # reject same listing root
        if candidate_url.rstrip("/") == listing_url.rstrip("/"):
            return False

        path = (u.path or "").lower()

        # exclude media/downloads
        if any(x in path for x in EXCLUDE_PATH_HINTS):
            return False
        if any(path.endswith(ext) for ext in BLOCKED_EXTS):
            return False

        q = (u.query or "").lower()
        if any(h in q for h in BLOCKED_QUERY_HINTS):
            return False

        # Bocholt-Kalender: echte Events haben "?event=<id>"
        if "bocholt.de" in lu.netloc and "/veranstaltungskalender/" in path:
            if "event=" not in q:
                return False

        return any(h in path for h in DETAIL_PATH_HINTS) and len(path) > 3
    except Exception:
        return False
    except Exception:
        return False


def norm_text(s: str) -> str:
    return re.sub(r"\s+", " ", (s or "").strip())


DATE_RE = re.compile(r"\b(\d{1,2})\.(\d{1,2})\.(\d{2,4})\b")
TIME_RE = re.compile(r"\b(\d{1,2}:\d{2})\b")


def extract_event_fields(detail_html: str, detail_url: str, cfg: SourceCfg) -> Dict[str, str]:
    """Heuristic fallback extraction (used if LLM is not configured or fails)."""
    soup = BeautifulSoup(detail_html, "html.parser")

    # title
    title = ""
    h1 = soup.find("h1")
    if h1:
        title = norm_text(h1.get_text(" ", strip=True))
    if not title:
        og = soup.find("meta", property="og:title")
        if og and og.get("content"):
            title = norm_text(og["content"])

    # text blob for regex
    text_blob = norm_text(soup.get_text(" ", strip=True))

    # date
    date = ""
    # 1) <time datetime="YYYY-MM-DD">
    t = soup.find("time")
    if t and t.get("datetime"):
        dt = norm_text(t.get("datetime", ""))
        m_iso = re.match(r"^(\d{4}-\d{2}-\d{2})", dt)
        if m_iso:
            date = m_iso.group(1)
    # 2) dd.mm.yyyy
    if not date:
        m = DATE_RE.search(text_blob)
        if m:
            dd, mm, yy = m.group(1), m.group(2), m.group(3)
            if len(yy) == 2:
                yy = "20" + yy
            date = f"{yy.zfill(4)}-{mm.zfill(2)}-{dd.zfill(2)}"

    # time
    time = ""
    mt = TIME_RE.search(text_blob)
    if mt:
        time = mt.group(1)

    # location (very heuristic)
    location = ""
    for label in ("Ort", "Veranstaltungsort", "Location"):
        idx = text_blob.lower().find(label.lower())
        if idx != -1:
            snippet = text_blob[idx : idx + 160]
            if ":" in snippet:
                snippet = snippet.split(":", 1)[1]
            location = norm_text(snippet)[:120]
            break

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

        # Responses API: when using structured outputs, we expect JSON text in output_text
        out = (resp.output_text or "").strip()
        if not out:
            return None
        data = json.loads(out)

        # normalize / fallbacks
        data["url"] = detail_url
        if not (data.get("city") or "").strip():
            data["city"] = cfg.default_city or "Bocholt"
        if not (data.get("category") or "").strip():
            data["category"] = cfg.default_category or ""
        return {
            "title": (data.get("title") or "").strip(),
            "date": (data.get("date") or "").strip(),
            "time": (data.get("time") or "").strip(),
            "location": (data.get("location") or "").strip(),
            "city": (data.get("city") or "").strip(),
            "kategorie_suggestion": (data.get("category") or "").strip(),
            "url": detail_url,
            "description": (data.get("description") or "").strip(),
        }
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
  

def make_candidate_row(
    run_ts: str,
    cfg: SourceCfg,
    detail_url: str,
) -> List[str]:
    created_at = run_ts
    # keep minimal fields empty; this is only a collector proof / candidate log
    return [
        run_ts,
        cfg.source_name,
        cfg.source_type,
        cfg.url,
        "candidate",
        "llm_candidate_detail_url",
        "FALSE",  # is_written_to_inbox
        "FALSE",  # is_already_live
        "FALSE",  # is_already_inbox
        "",  # matched_event_id
        "",  # match_score
        detail_url,  # fingerprint (temporary)
        "",  # id_suggestion
        "",  # title
        "",  # event_date
        "",  # endDate
        "",  # time
        cfg.default_city or "Bocholt",  # city
        "",  # location
        cfg.default_category or "",  # kategorie_suggestion
        detail_url,  # url
        "collector_only (playwright)",  # notes
        created_at,
    ]


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
        "llm_pipeline_playwright_collector (best-effort extraction, no external LLM yet)",
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
                            existing_inbox_urls.add(r[url_idx].strip())
        except Exception:
            pass

    for cfg in llm_sources:
        if cfg.source_type != "html":
            health_rows.append(
                make_health_row(
                    cfg,
                    "llm_collect_skip_non_html",
                    "",
                    "Collector implemented only for html in this script",
                    run_ts,
                    0,
                    0,
                )
            )
            continue

        res = await collect_detail_urls_playwright(cfg)
        details: List[str] = res["detail_urls"]
        http_status_last = res["http_status_last"]
        http_status_first = res.get("http_status_first", "") or http_status_last

        # Append CF proof summary into error (short)
        err = res["error"]
        if not err and res.get("nav2_status") and int(res["nav2_status"] or "0") >= 400:
            err = f"nav2_non_200:{res.get('nav2_status')} method={res.get('nav2_method')} cf-mitigated={res.get('nav2_cf_mitigated')}"

        # We treat llm_batch_size as "target number of NEW inbox rows" per run.
        target_new = max(cfg.llm_batch_size, 10)

        new_inbox_written = 0

        # Log candidates for all collected detail URLs (collector proof),
        # but only write up to target_new NEW items to Inbox (skip duplicates).
        for u in details:
            candidate_rows.append(make_candidate_row(run_ts, cfg, u))

            if new_inbox_written >= target_new:
                continue

            if u in existing_inbox_urls:
                continue

            try:
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
                    await page.goto(u, wait_until="domcontentloaded", timeout=60_000)
                    await page.wait_for_timeout(1200)
                    detail_html = await page.content()
                    await context.close()
                    await browser.close()

                fields = llm_extract_event_fields(detail_html, u, cfg)
                if fields is None:
                    fields = extract_event_fields(detail_html, u, cfg)

                # Pflichtfelder-Gate (minimal, robust)
                # Pflicht nur: title + date
                if not fields.get("title") or not fields.get("date"):
                    continue

                # location fallback
                if not fields.get("location"):
                    fields["location"] = cfg.default_city or "Bocholt"

                inbox_rows.append(make_inbox_row(run_ts, cfg, fields))
                existing_inbox_urls.add(u)
                new_inbox_written += 1
            except Exception:
                continue

        if err:
            status = "llm_collect_fetch_error"
        else:
            status = "llm_collect_ok" if new_inbox_written > 0 else "llm_collect_ok_no_new"

        health_rows.append(
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
