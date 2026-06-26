#!/usr/bin/env python3
# === BEGIN FILE: scripts/discover-bathing-water-sources-v2.py | Zweck: tiefer technischer Discovery-Proof fuer aktuelle Badegewaesser-Statusquellen; Umfang: Playwright/HTML/API-Discovery ohne Produkt-Writeback ===
from __future__ import annotations

import argparse
import html
import json
import re
import sys
import time
import urllib.parse
import urllib.request
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

USER_AGENT = "Bocholt-erleben bathing-water-source-discovery-v2/1.0 (+https://bocholt-erleben.de/)"
ROOT = Path(__file__).resolve().parents[1]

STATUS_OK = "ok"
STATUS_WATCH = "watch"
STATUS_BLOCKED = "blocked"
STATUS_UNKNOWN = "unknown"

DISCOVERY_API_FOUND = "api_found"
DISCOVERY_BROWSER_RENDERED = "browser_rendered_current_data"
DISCOVERY_HTML_ONLY = "static_html_only"
DISCOVERY_ANNUAL_ONLY = "only_annual_quality_or_stammdaten"
DISCOVERY_NOT_USABLE = "not_usable_for_current_status"
DISCOVERY_ERROR = "technical_error"

CURRENT_DATA_KEYWORDS = (
    "probenahmedatum",
    "probenahme",
    "messwert",
    "messergebnis",
    "escherichia coli",
    "e. coli",
    "enterokokken",
    "enterococc",
    "sichttiefe",
    "wassertemperatur",
    "badeverbot",
    "baden verboten",
    "bathing prohibition",
    "zwemplek status",
    "actuele situatie",
    "zwemadvies",
    "zwemverbod",
    "waterkwaliteit",
)

ANNUAL_ONLY_KEYWORDS = (
    "ausgezeichnete wasserqualität",
    "gute wasserqualität",
    "ausreichende wasserqualität",
    "mangelhafte wasserqualität",
    "badewasserqualität",
    "qualitätseinstufung",
)

SOURCE_DISCOVERY_KEYWORDS = (
    "api",
    "ajax",
    "report",
    "list",
    "data",
    "json",
    "bade",
    "gewaesser",
    "gewasser",
    "mess",
    "probe",
    "water",
    "zwem",
    "kwaliteit",
    "arcgis",
    "odata",
    "service",
    "graphql",
)

STATUS_NEGATIVE_PATTERNS = (
    "badeverbot ja",
    "badeverbot: ja",
    "badeverbot=true",
    "baden verboten",
    "zwemplek status:verboden",
    "verboden te zwemmen",
    "zwemverbod",
    "negatief zwemadvies",
    "zwemmen wordt afgeraden",
)

STATUS_WATCH_PATTERNS = (
    "zwemplek status:waarschuwing",
    "waarschuwing",
    "waterkwaliteit wordt onderzocht",
    "wordt onderzocht",
    "kans op gezondheidsklachten",
    "erhöhte werte",
    "auffällige werte",
)

STATUS_POSITIVE_PATTERNS = (
    "actuele situatie: in orde",
    "zwemplek status:goed",
    "de waterkwaliteit is goed",
)


@dataclass(frozen=True)
class Source:
    id: str
    group_id: str
    activity_id: str
    title: str
    country: str
    source_url: str
    expected_title: str
    authority_type: str


SOURCES: tuple[Source, ...] = (
    Source(
        id="aasee-bocholt-nrw",
        group_id="aasee-bocholt",
        activity_id="aasee-erleben",
        title="Aasee Bocholt",
        country="DE-NRW",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/48/",
        expected_title="Aasee - Badestelle",
        authority_type="official_bathing_water_status_nrw",
    ),
    Source(
        id="proebstingsee-borken-nrw",
        group_id="proebstingsee-borken",
        activity_id="proebstingsee-borken-erleben",
        title="Pröbstingsee Borken",
        country="DE-NRW",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/50/",
        expected_title="Pröbstingsee - Badestelle",
        authority_type="official_bathing_water_status_nrw",
    ),
    Source(
        id="auesee-wesel-rettungsinsel-nrw",
        group_id="auesee-wesel",
        activity_id="auesee-wesel-erleben",
        title="Auesee Wesel - Rettungsinsel",
        country="DE-NRW",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/22/",
        expected_title="Auesee - Rettungsinsel",
        authority_type="official_bathing_water_status_nrw",
    ),
    Source(
        id="auesee-wesel-treibsand-nrw",
        group_id="auesee-wesel",
        activity_id="auesee-wesel-erleben",
        title="Auesee Wesel - Treibsand",
        country="DE-NRW",
        source_url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/23/",
        expected_title="Auesee - Treibsand",
        authority_type="official_bathing_water_status_nrw",
    ),
    Source(
        id="hilgelo-winterswijk-zwemwater",
        group_id="hilgelo-winterswijk",
        activity_id="hilgelo-erleben",
        title="'t Hilgelo Winterswijk",
        country="NL",
        source_url="https://www.zwemwater.nl/?id=1750",
        expected_title="Hilgelo",
        authority_type="official_bathing_water_status_nl",
    ),
)


def normalise_text(value: str) -> str:
    value = html.unescape(value or "")
    value = re.sub(r"<script\b.*?</script>", " ", value, flags=re.IGNORECASE | re.DOTALL)
    value = re.sub(r"<style\b.*?</style>", " ", value, flags=re.IGNORECASE | re.DOTALL)
    value = re.sub(r"<[^>]+>", " ", value)
    value = value.replace("\xa0", " ")
    value = re.sub(r"\s+", " ", value)
    return value.strip()


def key_text(value: str) -> str:
    text = normalise_text(value).lower()
    return text.replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")


def fetch_text(url: str, timeout_seconds: int, max_bytes: int = 2_000_000) -> dict[str, Any]:
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.5",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout_seconds) as response:
            raw = response.read(max_bytes + 1)
            truncated = len(raw) > max_bytes
            if truncated:
                raw = raw[:max_bytes]
            charset = response.headers.get_content_charset() or "utf-8"
            return {
                "ok": True,
                "status": getattr(response, "status", None),
                "url": response.geturl(),
                "content_type": response.headers.get("content-type", ""),
                "text": raw.decode(charset, errors="replace"),
                "truncated": truncated,
                "error": None,
            }
    except Exception as exc:  # noqa: BLE001 - Discovery soll konservativ weiterlaufen.
        return {
            "ok": False,
            "status": None,
            "url": url,
            "content_type": "",
            "text": "",
            "truncated": False,
            "error": f"{type(exc).__name__}: {exc}",
        }


def unique_preserve(values: list[str]) -> list[str]:
    seen: set[str] = set()
    result: list[str] = []
    for value in values:
        if value in seen:
            continue
        seen.add(value)
        result.append(value)
    return result


def extract_linked_assets(base_url: str, body: str) -> list[str]:
    urls: list[str] = []
    for attr in ("src", "href"):
        for match in re.finditer(rf"{attr}\s*=\s*[\"']([^\"']+)[\"']", body, flags=re.IGNORECASE):
            candidate = html.unescape(match.group(1).strip())
            if not candidate or candidate.startswith(("data:", "mailto:", "tel:")):
                continue
            abs_url = urllib.parse.urljoin(base_url, candidate)
            if any(part in abs_url.lower() for part in (".js", "/api", "ajax", "json", "report", "data")):
                urls.append(abs_url)
    return unique_preserve(urls)


def extract_embedded_candidate_urls(base_url: str, text: str) -> list[str]:
    urls: list[str] = []
    for match in re.finditer(r"https?://[^\s'\"<>\\)]+", text):
        urls.append(match.group(0).rstrip(".,;"))
    for match in re.finditer(r"[\"']((?:/|\.\.?/)[^\"']+)[\"']", text):
        rel = html.unescape(match.group(1).strip())
        if any(keyword in rel.lower() for keyword in SOURCE_DISCOVERY_KEYWORDS):
            urls.append(urllib.parse.urljoin(base_url, rel))
    parsed_base = urllib.parse.urlparse(base_url)
    filtered = []
    for url in urls:
        parsed = urllib.parse.urlparse(url)
        if parsed.netloc and parsed.netloc != parsed_base.netloc and "zwemwater" not in parsed.netloc and "badegewaesser" not in parsed.netloc and "nrw" not in parsed.netloc:
            continue
        if any(keyword in url.lower() for keyword in SOURCE_DISCOVERY_KEYWORDS):
            filtered.append(url)
    return unique_preserve(filtered)[:80]


def text_indicators(text: str, expected_title: str) -> dict[str, Any]:
    key = key_text(text)
    expected_key = key_text(expected_title)
    years = sorted(set(re.findall(r"\b20(?:2[4-9]|3[0-5])\b", key)))
    dates = sorted(set(re.findall(r"\b\d{1,2}\.\d{1,2}\.20\d{2}\b", key)))
    iso_dates = sorted(set(re.findall(r"\b20\d{2}-\d{2}-\d{2}\b", key)))
    current_keywords = sorted({kw for kw in CURRENT_DATA_KEYWORDS if kw in key})
    annual_keywords = sorted({kw for kw in ANNUAL_ONLY_KEYWORDS if kw in key})

    status_signals: list[str] = []
    for pattern in STATUS_NEGATIVE_PATTERNS:
        if pattern in key:
            status_signals.append(f"negative:{pattern}")
    for pattern in STATUS_WATCH_PATTERNS:
        if pattern in key:
            status_signals.append(f"watch:{pattern}")
    for pattern in STATUS_POSITIVE_PATTERNS:
        if pattern in key:
            status_signals.append(f"positive:{pattern}")

    return {
        "expected_title_present": expected_key in key if expected_key else False,
        "years": years[:12],
        "dates": (dates + iso_dates)[:24],
        "current_keywords": current_keywords,
        "annual_keywords": annual_keywords,
        "status_signals": status_signals[:12],
        "contains_empty_report": "empty report" in key or "no data available to generate a report" in key,
        "contains_client_error": "an unexpected error has occurred" in key or "error happened while loading list data" in key,
    }


def infer_candidate_status(indicators: dict[str, Any]) -> str:
    signals = indicators.get("status_signals") or []
    if any(str(signal).startswith("negative:") for signal in signals):
        return STATUS_BLOCKED
    if any(str(signal).startswith("watch:") for signal in signals):
        return STATUS_WATCH
    if any(str(signal).startswith("positive:") for signal in signals):
        return STATUS_OK
    return STATUS_UNKNOWN


def response_is_candidate(url: str, content_type: str, text: str) -> bool:
    low_url = url.lower()
    low_type = (content_type or "").lower()
    if any(keyword in low_url for keyword in SOURCE_DISCOVERY_KEYWORDS):
        return True
    if "json" in low_type:
        return True
    sample = key_text(text[:20000])
    return any(keyword in sample for keyword in CURRENT_DATA_KEYWORDS)


def summarize_response(url: str, status: int | None, content_type: str, text: str, expected_title: str, source_url: str) -> dict[str, Any]:
    indicators = text_indicators(text, expected_title)
    parsed = urllib.parse.urlparse(url)
    same_host = parsed.netloc == urllib.parse.urlparse(source_url).netloc
    return {
        "url": url,
        "http_status": status,
        "content_type": content_type,
        "same_host": same_host,
        "length": len(text or ""),
        "indicators": indicators,
        "looks_like_current_data": bool(indicators["current_keywords"] and (indicators["dates"] or indicators["years"] or indicators["status_signals"])),
        "candidate_status": infer_candidate_status(indicators),
    }


def static_discovery(source: Source, timeout_seconds: int, max_asset_fetches: int) -> dict[str, Any]:
    page = fetch_text(source.source_url, timeout_seconds)
    page_text = page.get("text") or ""
    linked_assets = extract_linked_assets(source.source_url, page_text)
    embedded_candidates = extract_embedded_candidate_urls(source.source_url, page_text)
    asset_candidates: list[dict[str, Any]] = []
    asset_urls = unique_preserve(linked_assets + embedded_candidates)

    fetched = 0
    for url in asset_urls:
        if fetched >= max_asset_fetches:
            break
        if not any(keyword in url.lower() for keyword in SOURCE_DISCOVERY_KEYWORDS) and not url.lower().endswith(".js"):
            continue
        fetched += 1
        result = fetch_text(url, timeout_seconds, max_bytes=1_500_000)
        text = result.get("text") or ""
        if not result.get("ok") and not text:
            continue
        embedded = extract_embedded_candidate_urls(url, text)
        summary = summarize_response(
            url=result.get("url") or url,
            status=result.get("status"),
            content_type=result.get("content_type") or "",
            text=text,
            expected_title=source.expected_title,
            source_url=source.source_url,
        )
        summary["embedded_candidate_url_count"] = len(embedded)
        summary["embedded_candidate_urls_sample"] = embedded[:12]
        if response_is_candidate(summary["url"], summary["content_type"], text) or embedded:
            asset_candidates.append(summary)

    return {
        "page_fetch": {k: v for k, v in page.items() if k != "text"},
        "page_indicators": text_indicators(page_text, source.expected_title),
        "linked_asset_count": len(linked_assets),
        "embedded_candidate_url_count": len(embedded_candidates),
        "asset_candidates": asset_candidates[:30],
    }


def browser_discovery(source: Source, timeout_seconds: int, max_response_bytes: int) -> dict[str, Any]:
    started_at = time.time()
    try:
        from playwright.sync_api import TimeoutError as PlaywrightTimeoutError  # type: ignore
        from playwright.sync_api import sync_playwright  # type: ignore
    except Exception as exc:  # noqa: BLE001
        return {
            "available": False,
            "error": f"playwright_not_available: {type(exc).__name__}: {exc}",
            "rendered_indicators": {},
            "responses": [],
            "resource_urls": [],
        }

    response_summaries: list[dict[str, Any]] = []
    resource_urls: list[str] = []
    rendered_text = ""
    browser_error = None

    def maybe_capture_response(response: Any) -> None:
        nonlocal response_summaries
        url = response.url
        content_type = response.headers.get("content-type", "") if response.headers else ""
        if not response_is_candidate(url, content_type, ""):
            return
        try:
            body = response.text()
        except Exception:
            body = ""
        if len(body) > max_response_bytes:
            body = body[:max_response_bytes]
        if not response_is_candidate(url, content_type, body):
            return
        summary = summarize_response(url, response.status, content_type, body, source.expected_title, source.source_url)
        response_summaries.append(summary)

    try:
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(headless=True, args=["--no-sandbox"])
            context = browser.new_context(user_agent=USER_AGENT, viewport={"width": 1366, "height": 1200})
            page = context.new_page()
            page.on("response", maybe_capture_response)
            try:
                page.goto(source.source_url, wait_until="networkidle", timeout=timeout_seconds * 1000)
            except PlaywrightTimeoutError:
                page.goto(source.source_url, wait_until="domcontentloaded", timeout=timeout_seconds * 1000)
            # Viele Datenlisten laden erst nach Tabs/Buttons. Klicke nur risikoarme sichtbare Texte.
            click_labels = ("2026", "Messwerte", "Messergebnisse", "Aktuelle Messwerte", "Proben", "Badeverbot", "Waterkwaliteit", "Actuele situatie")
            for label in click_labels:
                try:
                    locator = page.get_by_text(label, exact=False).first
                    if locator.count() > 0:
                        locator.click(timeout=900)
                        try:
                            page.wait_for_load_state("networkidle", timeout=2500)
                        except Exception:
                            page.wait_for_timeout(800)
                except Exception:
                    continue
            try:
                rendered_text = page.locator("body").inner_text(timeout=4000)
            except Exception:
                rendered_text = ""
            try:
                resource_urls = page.evaluate("() => performance.getEntriesByType('resource').map(e => e.name)")
            except Exception:
                resource_urls = []
            context.close()
            browser.close()
    except Exception as exc:  # noqa: BLE001
        browser_error = f"{type(exc).__name__}: {exc}"

    resource_urls = [url for url in resource_urls if any(keyword in url.lower() for keyword in SOURCE_DISCOVERY_KEYWORDS)]
    return {
        "available": True,
        "error": browser_error,
        "elapsed_seconds": round(time.time() - started_at, 2),
        "rendered_indicators": text_indicators(rendered_text, source.expected_title),
        "rendered_text_excerpt": normalise_text(rendered_text)[:1200],
        "responses": response_summaries[:60],
        "resource_urls": unique_preserve(resource_urls)[:80],
    }


def classify_source(source: Source, static: dict[str, Any], browser: dict[str, Any]) -> dict[str, Any]:
    page_indicators = static.get("page_indicators") or {}
    rendered_indicators = browser.get("rendered_indicators") or {}
    responses = list(browser.get("responses") or []) + list(static.get("asset_candidates") or [])

    api_like_current = [r for r in responses if r.get("looks_like_current_data") and ("json" in str(r.get("content_type", "")).lower() or any(part in str(r.get("url", "")).lower() for part in ("api", "ajax", "report", "list", "data")))]
    rendered_current = bool(rendered_indicators.get("current_keywords") and (rendered_indicators.get("dates") or rendered_indicators.get("years") or rendered_indicators.get("status_signals")))
    static_current = bool(page_indicators.get("current_keywords") and (page_indicators.get("dates") or page_indicators.get("years") or page_indicators.get("status_signals")))
    annual_only = bool(page_indicators.get("annual_keywords") or rendered_indicators.get("annual_keywords"))

    status_candidates = [infer_candidate_status(page_indicators), infer_candidate_status(rendered_indicators)]
    status_candidates.extend(str(r.get("candidate_status") or STATUS_UNKNOWN) for r in responses)
    if STATUS_BLOCKED in status_candidates:
        inferred_status = STATUS_BLOCKED
    elif STATUS_WATCH in status_candidates:
        inferred_status = STATUS_WATCH
    elif STATUS_OK in status_candidates:
        inferred_status = STATUS_OK
    else:
        inferred_status = STATUS_UNKNOWN

    notes: list[str] = []
    if static.get("page_fetch", {}).get("error"):
        notes.append("static_page_fetch_error")
    if browser.get("error"):
        notes.append("browser_error")
    if page_indicators.get("contains_empty_report") or rendered_indicators.get("contains_empty_report"):
        notes.append("empty_report_seen")
    if page_indicators.get("contains_client_error") or rendered_indicators.get("contains_client_error"):
        notes.append("client_side_report_error_seen")
    if annual_only:
        notes.append("annual_quality_seen_not_sufficient_for_daily_status")

    discovery_class = DISCOVERY_NOT_USABLE
    usable_for_next_stage = False
    if api_like_current:
        discovery_class = DISCOVERY_API_FOUND
        usable_for_next_stage = True
        notes.append("candidate_api_or_xhr_with_current_data_found")
    elif rendered_current:
        discovery_class = DISCOVERY_BROWSER_RENDERED
        usable_for_next_stage = True
        notes.append("browser_rendered_current_status_or_samples_found")
    elif static_current:
        discovery_class = DISCOVERY_HTML_ONLY
        usable_for_next_stage = True
        notes.append("static_html_current_status_or_samples_found")
    elif annual_only:
        discovery_class = DISCOVERY_ANNUAL_ONLY
    elif browser.get("error") and not responses:
        discovery_class = DISCOVERY_ERROR

    if source.authority_type == "official_bathing_water_status_nrw" and inferred_status == STATUS_OK:
        # Im NRW-Kontext ist ein positives Signal ohne explizite Tagesfreigabe nicht ausreichend.
        notes.append("nrw_positive_signal_not_auto_clearance")
        inferred_status = STATUS_UNKNOWN

    return {
        "source": asdict(source),
        "discovery_class": discovery_class,
        "usable_for_next_stage": usable_for_next_stage,
        "inferred_status_for_proof_only": inferred_status,
        "notes": unique_preserve(notes),
        "static": static,
        "browser": browser,
        "candidate_current_data_response_count": len(api_like_current),
        "candidate_current_data_responses": api_like_current[:12],
    }


def aggregate_groups(source_results: list[dict[str, Any]], min_usable_groups: int) -> dict[str, Any]:
    groups: dict[str, list[dict[str, Any]]] = {}
    for result in source_results:
        group_id = result["source"]["group_id"]
        groups.setdefault(group_id, []).append(result)

    group_results: list[dict[str, Any]] = []
    for group_id, items in sorted(groups.items()):
        statuses = [item["inferred_status_for_proof_only"] for item in items]
        usable = any(bool(item.get("usable_for_next_stage")) for item in items)
        classes = sorted(set(str(item.get("discovery_class")) for item in items))
        if STATUS_BLOCKED in statuses:
            group_status = STATUS_BLOCKED
        elif STATUS_WATCH in statuses:
            group_status = STATUS_WATCH
        elif STATUS_OK in statuses:
            group_status = STATUS_OK
        else:
            group_status = STATUS_UNKNOWN
        group_results.append(
            {
                "group_id": group_id,
                "activity_ids": sorted(set(item["source"]["activity_id"] for item in items)),
                "usable_for_next_stage": usable,
                "inferred_status_for_proof_only": group_status,
                "discovery_classes": classes,
                "sources": [item["source"]["id"] for item in items],
            }
        )

    usable_group_count = sum(1 for group in group_results if group["usable_for_next_stage"])
    nrw_groups_usable = [group for group in group_results if group["usable_for_next_stage"] and any("-nrw" in src for src in group["sources"])]
    return {
        "groups": group_results,
        "usable_activity_groups": usable_group_count,
        "min_usable_activity_groups": min_usable_groups,
        "nrw_usable_group_count": len(nrw_groups_usable),
        "ready_for_next_stage": usable_group_count >= min_usable_groups and len(nrw_groups_usable) >= 1,
        "recommendation": "design_guard_workflow_next" if usable_group_count >= min_usable_groups and len(nrw_groups_usable) >= 1 else "do_not_operationalize_yet_continue_manual_unknown_blocking",
    }


def markdown_report(payload: dict[str, Any]) -> str:
    lines: list[str] = []
    lines.append("# Bathing Water Source Discovery V2")
    lines.append("")
    lines.append(f"Generated: `{payload['generated_at']}`")
    lines.append("")
    lines.append("## Summary")
    lines.append("")
    summary = payload["summary"]
    lines.append(f"- `ready_for_next_stage`: `{str(summary['ready_for_next_stage']).lower()}`")
    lines.append(f"- `usable_activity_groups`: `{summary['usable_activity_groups']}/{len(summary['groups'])}`")
    lines.append(f"- `nrw_usable_group_count`: `{summary['nrw_usable_group_count']}`")
    lines.append(f"- `recommendation`: `{summary['recommendation']}`")
    lines.append("")
    lines.append("## Activity groups")
    lines.append("")
    lines.append("| Group | Usable | Proof status | Discovery classes | Sources |")
    lines.append("|---|---:|---|---|---|")
    for group in summary["groups"]:
        lines.append(
            "| "
            + group["group_id"]
            + " | "
            + ("yes" if group["usable_for_next_stage"] else "no")
            + " | `"
            + group["inferred_status_for_proof_only"]
            + "` | "
            + ", ".join(f"`{value}`" for value in group["discovery_classes"])
            + " | "
            + ", ".join(group["sources"])
            + " |"
        )
    lines.append("")
    lines.append("## Source details")
    lines.append("")
    for item in payload["sources"]:
        source = item["source"]
        lines.append(f"### {source['title']} (`{source['id']}`)")
        lines.append("")
        lines.append(f"- Discovery class: `{item['discovery_class']}`")
        lines.append(f"- Usable for next stage: `{str(item['usable_for_next_stage']).lower()}`")
        lines.append(f"- Proof-only inferred status: `{item['inferred_status_for_proof_only']}`")
        lines.append(f"- Candidate current-data responses: `{item['candidate_current_data_response_count']}`")
        if item.get("notes"):
            lines.append("- Notes: " + ", ".join(f"`{note}`" for note in item["notes"]))
        rendered = item.get("browser", {}).get("rendered_indicators") or {}
        static = item.get("static", {}).get("page_indicators") or {}
        lines.append(f"- Static current keywords: `{', '.join(static.get('current_keywords') or [])}`")
        lines.append(f"- Browser current keywords: `{', '.join(rendered.get('current_keywords') or [])}`")
        lines.append(f"- Browser status signals: `{', '.join(rendered.get('status_signals') or [])}`")
        candidate_urls = [resp.get("url") for resp in item.get("candidate_current_data_responses") or [] if resp.get("url")]
        if candidate_urls:
            lines.append("- Candidate data URLs:")
            for url in candidate_urls[:8]:
                lines.append(f"  - `{url}`")
        lines.append("")
    lines.append("## Interpretation")
    lines.append("")
    if summary["ready_for_next_stage"]:
        lines.append("Der Proof hat ausreichend technische Hinweise gefunden, um als naechsten Schritt einen separaten Guard-Workflow zu entwerfen. Das bedeutet noch keine automatische Badefreigabe.")
    else:
        lines.append("Der Proof reicht noch nicht fuer eine automatische Statuspflege. Bade-/Wasser-Highlights bleiben ohne frische positive Quelle unsichtbar oder manuell zu pruefen.")
    lines.append("")
    lines.append("Wichtig: `inferred_status_for_proof_only` ist kein Produktstatus und darf nicht direkt in `data/offers.json` uebernommen werden.")
    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Deep-discover bathing-water current-status sources before operationalization.")
    parser.add_argument("--output", default="tmp/bathing-water-source-discovery-v2.json")
    parser.add_argument("--markdown", default="tmp/bathing-water-source-discovery-v2.md")
    parser.add_argument("--timeout", type=int, default=25)
    parser.add_argument("--min-usable-activity-groups", type=int, default=3)
    parser.add_argument("--max-asset-fetches", type=int, default=24)
    parser.add_argument("--max-response-bytes", type=int, default=700_000)
    parser.add_argument("--skip-browser", action="store_true", help="Only run static discovery. Intended for local syntax/debug runs.")
    args = parser.parse_args()

    source_results: list[dict[str, Any]] = []
    for source in SOURCES:
        static = static_discovery(source, timeout_seconds=args.timeout, max_asset_fetches=args.max_asset_fetches)
        browser = {
            "available": False,
            "skipped": True,
            "error": None,
            "rendered_indicators": {},
            "responses": [],
            "resource_urls": [],
        }
        if not args.skip_browser:
            browser = browser_discovery(source, timeout_seconds=args.timeout, max_response_bytes=args.max_response_bytes)
        source_results.append(classify_source(source, static, browser))

    payload = {
        "generated_at": datetime.now(timezone.utc).replace(microsecond=0).isoformat(),
        "scope": "source_discovery_v2_no_product_writeback",
        "summary": aggregate_groups(source_results, min_usable_groups=args.min_usable_activity_groups),
        "sources": source_results,
    }

    output_path = Path(args.output)
    markdown_path = Path(args.markdown)
    output_path.parent.mkdir(parents=True, exist_ok=True)
    markdown_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    markdown_path.write_text(markdown_report(payload), encoding="utf-8")

    print(f"BATHING_WATER_SOURCE_DISCOVERY_V2_OK output={output_path} markdown={markdown_path}")
    print(json.dumps(payload["summary"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
# === END FILE: scripts/discover-bathing-water-sources-v2.py ===
