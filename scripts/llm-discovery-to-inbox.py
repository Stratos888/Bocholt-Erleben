# === BEGIN BLOCK: LLM DISCOVERY TO INBOX (collector proof: html pagination + detail-url splitting, no LLM) ===
# Datei: scripts/llm-discovery-to-inbox.py
# Zweck:
# - Parallel zum Legacy-Prozess: Proof, dass wir aus HTML-Quellen (z.B. bocholt.de Kalender)
#   sauber paginieren und pro Event Detail-URLs extrahieren können.
# - NOCH KEIN LLM-Call, NOCH KEIN Schreiben in Inbox.
# - Schreibt nur Monitoring:
#   - Source_Health: llm_collect_ok + candidates_count
#   - Discovery_Candidates: pro Detail-URL eine Zeile (reason=llm_candidate_detail_url)
#
# Eingaben (ENV):
# - SHEET_ID
# - GOOGLE_SERVICE_ACCOUNT_JSON
# - TAB_SOURCES, TAB_SOURCE_HEALTH, TAB_DISCOVERY_CANDIDATES
#
# Verhalten:
# - Filtert Quellen: enabled==TRUE und pipeline_mode=="llm"
# - Lädt max_pages Seiten über "next"-Links (generisch)
# - Extrahiert Detail-Links, die nach Eventdetail aussehen (Heuristik)
# - Loggt Kandidaten in Discovery_Candidates
# === END BLOCK: LLM DISCOVERY TO INBOX (collector proof: html pagination + detail-url splitting, no LLM) ===

from __future__ import annotations

import json
import os
import re
from dataclasses import dataclass
from datetime import datetime
from typing import Any, Dict, List, Optional, Set
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: SHEET TAB CONFIG (ENV override) ===
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
TAB_SOURCE_HEALTH = os.environ.get("TAB_SOURCE_HEALTH", "Source_Health")
TAB_DISCOVERY_CANDIDATES = os.environ.get("TAB_DISCOVERY_CANDIDATES", "Discovery_Candidates")
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


def find_next_url(base_url: str, soup: BeautifulSoup) -> Optional[str]:
    # 1) rel=next
    a = soup.find("a", attrs={"rel": re.compile(r"\bnext\b", re.I)})
    if a and a.get("href"):
        return urljoin(base_url, a["href"])

    # 2) aria-label contains next/weiter
    for a in soup.find_all("a"):
        label = (a.get("aria-label") or "").strip().lower()
        if any(h in label for h in ("next", "weiter", "nächste", "naechste")) and a.get("href"):
            return urljoin(base_url, a["href"])

    # 3) link text heuristic
    for a in soup.find_all("a"):
        text = (a.get_text(" ", strip=True) or "").strip().lower()
        if any(h in text for h in NEXT_TEXT_HINTS) and a.get("href"):
            href = a["href"]
            # avoid self-links
            if href and href != "#" and "javascript:" not in href.lower():
                return urljoin(base_url, href)

    return None


DETAIL_PATH_HINTS = (
    "/veranstaltungskalender/",
    "/event/",
    "/events/",
    "/termine/",
    "/kalender/",
)


def is_probable_detail_url(listing_url: str, candidate_url: str) -> bool:
    try:
        u = urlparse(candidate_url)
        if not u.scheme.startswith("http"):
            return False
        # reject same listing root
        if candidate_url.rstrip("/") == listing_url.rstrip("/"):
            return False
        path = (u.path or "").lower()
        return any(h in path for h in DETAIL_PATH_HINTS) and len(path) > 3
    except Exception:
        return False


def collect_detail_urls(cfg: SourceCfg) -> Dict[str, Any]:
    session = requests.Session()
    session.headers.update(
        {
            "User-Agent": "Mozilla/5.0 (compatible; BocholtErlebenBot/1.0; +https://bocholt-erleben.de)",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        }
    )

    listing_url = cfg.url
    current_url = listing_url
    visited_pages: Set[str] = set()
    detail_urls: Set[str] = set()

    http_status_last = ""
    error_msg = ""

    for _ in range(max(cfg.max_pages, 1)):
        if current_url in visited_pages:
            break
        visited_pages.add(current_url)

        try:
            resp = session.get(current_url, timeout=30)
            http_status_last = str(resp.status_code)
            resp.raise_for_status()
        except Exception as e:
            error_msg = f"fetch_error: {e}"
            break

        soup = BeautifulSoup(resp.text, "html.parser")

        # collect all hrefs
        for a in soup.find_all("a", href=True):
            href = urljoin(current_url, a["href"])
            if is_probable_detail_url(listing_url, href):
                detail_urls.add(href)

        next_url = find_next_url(current_url, soup)
        if not next_url:
            break
        current_url = next_url

    return {
        "listing_url": listing_url,
        "pages_visited": len(visited_pages),
        "detail_urls": sorted(detail_urls),
        "http_status_last": http_status_last,
        "error": error_msg,
    }


def make_candidate_row(
    run_ts: str,
    cfg: SourceCfg,
    detail_url: str,
) -> List[str]:
    created_at = run_ts
    # keep minimal fields empty; this is only a collector proof
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
        "collector_only (no LLM yet)",  # notes
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
    return [
        cfg.source_name,
        cfg.source_type,
        cfg.url,
        status,
        http_status,
        error,
        run_ts,
        str(candidates_count),
        str(new_rows_written),
    ]


def main() -> None:
    service, sheet_id = build_sheets_service()
    run_ts = datetime.utcnow().isoformat(timespec="seconds") + "Z"

    sources_values = read_tab(service, sheet_id, TAB_SOURCES)
    llm_sources = parse_sources(sources_values)

    if not llm_sources:
        # still log something visible
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

    for cfg in llm_sources:
        if cfg.source_type != "html":
            health_rows.append(
                make_health_row(
                    cfg,
                    "llm_collect_skip_non_html",
                    "",
                    "Collector proof implemented only for html in Step 3",
                    run_ts,
                    0,
                    0,
                )
            )
            continue

        res = collect_detail_urls(cfg)
        details: List[str] = res["detail_urls"]
        http_status_last = res["http_status_last"]
        err = res["error"]

        # log limited number of candidates per run to avoid tab explosion
        limit = max(cfg.llm_batch_size, 10)
        for u in details[:limit]:
            candidate_rows.append(make_candidate_row(run_ts, cfg, u))

        status = "llm_collect_ok" if not err else "llm_collect_fetch_error"
        health_rows.append(
            make_health_row(
                cfg,
                status,
                http_status_last,
                err,
                run_ts,
                len(details),
                0,
            )
        )

    append_rows(service, sheet_id, TAB_SOURCE_HEALTH, health_rows)
    append_rows(service, sheet_id, TAB_DISCOVERY_CANDIDATES, candidate_rows)

    print(f"LLM collector proof done: sources={len(llm_sources)} candidates_logged={len(candidate_rows)}")


if __name__ == "__main__":
    main()
