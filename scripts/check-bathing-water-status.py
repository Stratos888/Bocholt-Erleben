#!/usr/bin/env python3
"""Report-only bathing-water status guard for Bocholt erleben.

This script reads official bathing-water sources found by the V2 source discovery
workpack and produces JSON/Markdown artifacts. It intentionally does not write to
`data/offers.json` and does not activate any public highlight.

Status policy is conservative:
- `blocked` beats all other states.
- `ok` is only emitted for current, parseable, positive source data.
- ambiguous, stale or unavailable source data becomes `unknown`.
"""

from __future__ import annotations

import argparse
import datetime as dt
import html
import json
import re
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

SCRIPT_VERSION = "BATHING_WATER_GUARD_V1_REPORT_ONLY"
USER_AGENT = "BocholtErlebenBathingWaterGuard/1.0 (+https://bocholt-erleben.de)"
REQUEST_TIMEOUT_SECONDS = 25

# The NRW endpoint exposes sample rows as a DataTables JSON response. V2 source
# discovery found this endpoint through browser/XHR inspection. `jahrDiff=0`
# represents the currently shown year on the NRW detail page.
NRW_SAMPLE_COLUMNS = [
    "datumProbenahme",
    "ecWithHinweis",
    "ieWithHinweis",
    "wassertemperatur",
    "sichttiefe",
    "badeverbotUI",
]

# Conservative technical thresholds for single-sample guard reporting. The
# official `badeverbotUI` flag remains the primary blocker. Values above these
# guardrails do not publish anything automatically; they only prevent an `ok`
# report state.
WATCH_E_COLI = 900
WATCH_ENTEROCOCCI = 330
BLOCK_E_COLI = 1800
BLOCK_ENTEROCOCCI = 700

NEGATIVE_ZWEMWATER_PHRASES = [
    "verboden te zwemmen",
    "zwemmen wordt afgeraden",
    "negatief zwemadvies",
    "niet zwemmen",
    "zwemverbod",
    "slechte waterkwaliteit",
    "blauwalgen",
]
WATCH_ZWEMWATER_PHRASES = [
    "waarschuwing",
    "waterkwaliteit wordt onderzocht",
    "wordt onderzocht",
    "kans op gezondheidsklachten",
]
POSITIVE_ZWEMWATER_PHRASES = [
    "actuele situatie: in orde",
    "actuele situatie in orde",
    "zwemplek status: goed",
    "zwemplek status goed",
    "de waterkwaliteit is goed",
]

STATE_ORDER = {"blocked": 3, "watch": 2, "unknown": 1, "ok": 0, "out_of_season": -1}


@dataclass(frozen=True)
class SourceConfig:
    id: str
    type: str
    title: str
    authority_type: str
    url: str
    nrw_id: Optional[int] = None
    zwemwater_id: Optional[int] = None


@dataclass(frozen=True)
class GroupConfig:
    group_id: str
    activity_ids: List[str]
    title: str
    season_start: str
    season_end: str
    sources: List[SourceConfig]


@dataclass
class SourceResult:
    source_id: str
    title: str
    type: str
    authority_type: str
    state: str
    confidence: str
    checked_at: str
    source_url: str
    latest_sample_date: Optional[str] = None
    latest_sample_age_days: Optional[int] = None
    latest_sample: Optional[Dict[str, Any]] = None
    rows_seen: int = 0
    reason: str = ""
    warnings: List[str] = field(default_factory=list)
    errors: List[str] = field(default_factory=list)
    raw_signals: Dict[str, Any] = field(default_factory=dict)


GROUPS: List[GroupConfig] = [
    GroupConfig(
        group_id="aasee-bocholt",
        activity_ids=["aasee-erleben"],
        title="Aasee Bocholt",
        season_start="06-01",
        season_end="09-15",
        sources=[
            SourceConfig(
                id="aasee-bocholt-nrw",
                type="nrw_datatables",
                title="Aasee Bocholt - Badestelle",
                authority_type="official_bathing_water_status_nrw",
                url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/48/",
                nrw_id=48,
            )
        ],
    ),
    GroupConfig(
        group_id="hilgelo-winterswijk",
        activity_ids=["hilgelo-erleben"],
        title="'t Hilgelo Winterswijk",
        season_start="05-01",
        season_end="10-01",
        sources=[
            SourceConfig(
                id="hilgelo-winterswijk-zwemwater",
                type="zwemwater_page",
                title="'t Hilgelo Winterswijk",
                authority_type="official_bathing_water_status_nl",
                url="https://www.zwemwater.nl/home?id=1750",
                zwemwater_id=1750,
            )
        ],
    ),
    GroupConfig(
        group_id="proebstingsee-borken",
        activity_ids=["proebstingsee-borken-erleben"],
        title="Pröbstingsee Borken",
        season_start="05-15",
        season_end="09-15",
        sources=[
            SourceConfig(
                id="proebstingsee-borken-nrw",
                type="nrw_datatables",
                title="Pröbstingsee Borken",
                authority_type="official_bathing_water_status_nrw",
                url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/50/",
                nrw_id=50,
            )
        ],
    ),
    GroupConfig(
        group_id="auesee-wesel",
        activity_ids=["auesee-wesel-erleben"],
        title="Auesee Wesel",
        season_start="05-15",
        season_end="09-15",
        sources=[
            SourceConfig(
                id="auesee-wesel-rettungsinsel-nrw",
                type="nrw_datatables",
                title="Auesee Wesel - Rettungsinsel",
                authority_type="official_bathing_water_status_nrw",
                url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/22/",
                nrw_id=22,
            ),
            SourceConfig(
                id="auesee-wesel-treibsand-nrw",
                type="nrw_datatables",
                title="Auesee Wesel - Treibsand",
                authority_type="official_bathing_water_status_nrw",
                url="https://db.badegewaesser.nrw.de/badegewaesser-nrw/23/",
                nrw_id=23,
            ),
        ],
    ),
]


def today_utc_iso() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat()


def parse_today(value: Optional[str]) -> dt.date:
    if not value:
        return dt.datetime.now(dt.timezone.utc).date()
    return dt.date.fromisoformat(value)


def season_bounds(year: int, start_mmdd: str, end_mmdd: str) -> Tuple[dt.date, dt.date]:
    start_month, start_day = [int(part) for part in start_mmdd.split("-")]
    end_month, end_day = [int(part) for part in end_mmdd.split("-")]
    return dt.date(year, start_month, start_day), dt.date(year, end_month, end_day)


def in_season(on_date: dt.date, start_mmdd: str, end_mmdd: str) -> bool:
    start, end = season_bounds(on_date.year, start_mmdd, end_mmdd)
    if start <= end:
        return start <= on_date <= end
    return on_date >= start or on_date <= end


def strip_markup(value: Any) -> str:
    if value is None:
        return ""
    text = str(value)
    text = re.sub(r"<script\b[^>]*>.*?</script>", " ", text, flags=re.I | re.S)
    text = re.sub(r"<style\b[^>]*>.*?</style>", " ", text, flags=re.I | re.S)
    text = re.sub(r"<[^>]+>", " ", text)
    text = html.unescape(text)
    text = re.sub(r"\s+", " ", text).strip()
    return text


def norm_text(value: Any) -> str:
    text = strip_markup(value).lower()
    text = text.replace("\u00a0", " ")
    return re.sub(r"\s+", " ", text).strip()


def parse_german_date(value: Any) -> Optional[dt.date]:
    text = strip_markup(value)
    match = re.search(r"(\d{1,2})\.(\d{1,2})\.(\d{4})", text)
    if not match:
        return None
    day, month, year = [int(part) for part in match.groups()]
    try:
        return dt.date(year, month, day)
    except ValueError:
        return None


def parse_number(value: Any) -> Optional[float]:
    text = strip_markup(value)
    # Treat <15 as 15 for conservative threshold comparison.
    match = re.search(r"-?\d+(?:[\.,]\d+)?", text)
    if not match:
        return None
    try:
        return float(match.group(0).replace(",", "."))
    except ValueError:
        return None


def indicates_badeverbot(value: Any) -> bool:
    text = norm_text(value)
    if not text:
        return False
    negative_negations = ["nein", "kein", "keine", "no", "false", "0"]
    affirmative = ["ja", "yes", "true", "1", "badeverbot", "gesperrt", "verbot"]
    if any(token in text for token in negative_negations) and not any(
        token in text for token in ["ja", "yes", "true", "gesperrt"]
    ):
        return False
    if text in {"ja", "yes", "true", "1"}:
        return True
    if "badeverbot" in text and not ("kein" in text or "nein" in text):
        return True
    if "gesperrt" in text or "verboten" in text:
        return True
    return False


def request_url(url: str, *, accept: str = "*/*") -> Tuple[int, str, str]:
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": USER_AGENT,
            "Accept": accept,
            "Cache-Control": "no-cache",
        },
    )
    with urllib.request.urlopen(req, timeout=REQUEST_TIMEOUT_SECONDS) as response:
        status = getattr(response, "status", 200)
        content_type = response.headers.get("Content-Type", "")
        raw = response.read()
    text = raw.decode("utf-8", errors="replace")
    return status, content_type, text


def nrw_datatables_url(nrw_id: int, *, year_diff: int = 0) -> str:
    base = f"https://db.badegewaesser.nrw.de/badegewaesser-nrw/{nrw_id}/probenahmeMesswertInternets/dt"
    params: List[Tuple[str, str]] = [("jahrDiff", str(year_diff)), ("draw", "1")]
    for index, column_name in enumerate(NRW_SAMPLE_COLUMNS):
        params.extend(
            [
                (f"columns[{index}][data]", column_name),
                (f"columns[{index}][name]", ""),
                (f"columns[{index}][searchable]", "true"),
                (f"columns[{index}][orderable]", "true" if index == 0 else "false"),
                (f"columns[{index}][search][value]", ""),
                (f"columns[{index}][search][regex]", "false"),
            ]
        )
    params.extend(
        [
            ("order[0][column]", "0"),
            ("order[0][dir]", "asc"),
            ("start", "0"),
            ("length", "-1"),
            ("search[value]", ""),
            ("search[regex]", "false"),
        ]
    )
    return base + "?" + urllib.parse.urlencode(params)


def parse_datatables_json(text: str) -> List[Dict[str, Any]]:
    payload = json.loads(text)
    rows = payload.get("data") or payload.get("aaData") or []
    if not isinstance(rows, list):
        return []
    normalized: List[Dict[str, Any]] = []
    for row in rows:
        if isinstance(row, dict):
            normalized.append(row)
        elif isinstance(row, list):
            normalized.append({name: row[index] if index < len(row) else None for index, name in enumerate(NRW_SAMPLE_COLUMNS)})
    return normalized


def evaluate_nrw_row(row: Dict[str, Any]) -> Dict[str, Any]:
    sample_date = parse_german_date(row.get("datumProbenahme"))
    ec = parse_number(row.get("ecWithHinweis"))
    ie = parse_number(row.get("ieWithHinweis"))
    badeverbot = indicates_badeverbot(row.get("badeverbotUI"))
    signals: List[str] = []
    state = "ok"
    if badeverbot:
        state = "blocked"
        signals.append("official_badeverbot_flag")
    if ec is not None:
        if ec >= BLOCK_E_COLI:
            state = "blocked"
            signals.append(f"e_coli_at_or_above_block_guardrail:{ec:g}")
        elif ec >= WATCH_E_COLI and state != "blocked":
            state = "watch"
            signals.append(f"e_coli_at_or_above_watch_guardrail:{ec:g}")
    if ie is not None:
        if ie >= BLOCK_ENTEROCOCCI:
            state = "blocked"
            signals.append(f"enterococci_at_or_above_block_guardrail:{ie:g}")
        elif ie >= WATCH_ENTEROCOCCI and state != "blocked":
            state = "watch"
            signals.append(f"enterococci_at_or_above_watch_guardrail:{ie:g}")
    raw_text = " ".join(norm_text(row.get(key)) for key in NRW_SAMPLE_COLUMNS)
    for phrase in ["auffällig", "überschritten", "warnung", "nicht baden", "gesundheitsgefahr"]:
        if phrase in raw_text:
            if state != "blocked":
                state = "watch"
            signals.append(f"text_signal:{phrase}")
    return {
        "sample_date": sample_date.isoformat() if sample_date else None,
        "ec": ec,
        "enterococci": ie,
        "badeverbot": badeverbot,
        "state_from_values": state,
        "signals": signals,
        "raw": {key: strip_markup(row.get(key)) for key in NRW_SAMPLE_COLUMNS},
    }


def check_nrw_source(source: SourceConfig, today: dt.date, max_age_days: int, warn_age_days: int) -> SourceResult:
    checked_at = today_utc_iso()
    assert source.nrw_id is not None
    url = nrw_datatables_url(source.nrw_id, year_diff=0)
    try:
        status, content_type, text = request_url(url, accept="application/json,text/javascript,*/*")
    except Exception as exc:  # noqa: BLE001 - report-only guard should not crash the workflow.
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            state="unknown",
            confidence="fetch_failed",
            checked_at=checked_at,
            source_url=source.url,
            reason="NRW sample endpoint could not be fetched.",
            errors=[str(exc)],
            raw_signals={"endpoint_url": url},
        )
    try:
        rows = parse_datatables_json(text)
    except Exception as exc:  # noqa: BLE001
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            state="unknown",
            confidence="parse_failed",
            checked_at=checked_at,
            source_url=source.url,
            reason="NRW sample endpoint did not return parseable DataTables JSON.",
            errors=[str(exc)],
            raw_signals={"endpoint_url": url, "http_status": status, "content_type": content_type, "body_preview": text[:500]},
        )
    evaluated = [evaluate_nrw_row(row) for row in rows]
    dated = [item for item in evaluated if item.get("sample_date")]
    if not dated:
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            state="unknown",
            confidence="no_current_year_samples",
            checked_at=checked_at,
            source_url=source.url,
            rows_seen=len(rows),
            reason="No dated current-year sample row found in NRW endpoint.",
            raw_signals={"endpoint_url": url, "http_status": status, "content_type": content_type},
        )
    dated.sort(key=lambda item: item["sample_date"])
    latest = dated[-1]
    latest_date = dt.date.fromisoformat(str(latest["sample_date"]))
    age_days = (today - latest_date).days
    warnings: List[str] = []
    state_from_values = str(latest.get("state_from_values") or "unknown")
    state = state_from_values
    confidence = "official_current_sample"
    reason = "Latest NRW sample is recent enough and no blocking signal was parsed."
    if age_days < 0:
        state = "unknown"
        confidence = "future_sample_date"
        reason = "Latest sample date is in the future relative to guard date."
    elif age_days > max_age_days:
        state = "unknown"
        confidence = "stale_sample"
        reason = f"Latest NRW sample is older than {max_age_days} days."
    elif age_days > warn_age_days and state == "ok":
        state = "watch"
        confidence = "sample_near_stale"
        reason = f"Latest NRW sample is older than {warn_age_days} days and should be watched."
    elif state == "blocked":
        confidence = "official_or_guardrail_blocked"
        reason = "Latest NRW sample contains a blocking signal."
    elif state == "watch":
        confidence = "guardrail_watch"
        reason = "Latest NRW sample contains a watch-level signal."
    if len(rows) != len(dated):
        warnings.append(f"{len(rows) - len(dated)} sample rows without parseable date ignored")
    return SourceResult(
        source_id=source.id,
        title=source.title,
        type=source.type,
        authority_type=source.authority_type,
        state=state,
        confidence=confidence,
        checked_at=checked_at,
        source_url=source.url,
        latest_sample_date=latest_date.isoformat(),
        latest_sample_age_days=age_days,
        latest_sample=latest,
        rows_seen=len(rows),
        reason=reason,
        warnings=warnings,
        raw_signals={"endpoint_url": url, "http_status": status, "content_type": content_type},
    )


def check_zwemwater_source(source: SourceConfig, today: dt.date) -> SourceResult:
    checked_at = today_utc_iso()
    try:
        status, content_type, text = request_url(source.url, accept="text/html,*/*")
    except Exception as exc:  # noqa: BLE001
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            state="unknown",
            confidence="fetch_failed",
            checked_at=checked_at,
            source_url=source.url,
            reason="Zwemwater page could not be fetched.",
            errors=[str(exc)],
        )
    plain = norm_text(text)
    found_negative = [phrase for phrase in NEGATIVE_ZWEMWATER_PHRASES if phrase in plain]
    found_watch = [phrase for phrase in WATCH_ZWEMWATER_PHRASES if phrase in plain]
    found_positive = [phrase for phrase in POSITIVE_ZWEMWATER_PHRASES if phrase in plain]
    state = "unknown"
    confidence = "no_specific_status_signal"
    reason = "No sufficiently specific Zwemwater status phrase was parsed."
    if found_negative:
        state = "blocked"
        confidence = "official_negative_status_phrase"
        reason = "Zwemwater page contains a negative swimming advice/status phrase."
    elif found_watch:
        state = "watch"
        confidence = "official_watch_status_phrase"
        reason = "Zwemwater page contains a watch-level warning phrase."
    elif found_positive:
        state = "ok"
        confidence = "official_positive_status_phrase"
        reason = "Zwemwater page contains a positive current status phrase."
    return SourceResult(
        source_id=source.id,
        title=source.title,
        type=source.type,
        authority_type=source.authority_type,
        state=state,
        confidence=confidence,
        checked_at=checked_at,
        source_url=source.url,
        rows_seen=1,
        reason=reason,
        raw_signals={
            "http_status": status,
            "content_type": content_type,
            "negative_phrases": found_negative,
            "watch_phrases": found_watch,
            "positive_phrases": found_positive,
        },
    )


def check_source(source: SourceConfig, today: dt.date, max_age_days: int, warn_age_days: int) -> SourceResult:
    if source.type == "nrw_datatables":
        return check_nrw_source(source, today, max_age_days, warn_age_days)
    if source.type == "zwemwater_page":
        return check_zwemwater_source(source, today)
    raise ValueError(f"Unsupported source type: {source.type}")


def aggregate_group_state(source_results: List[SourceResult], active_season: bool) -> Tuple[str, str, str]:
    if not active_season:
        return "out_of_season", "season_inactive", "Configured swimming season is not active."
    if not source_results:
        return "unknown", "no_sources", "No source result exists."
    states = [result.state for result in source_results]
    if "blocked" in states:
        return "blocked", "blocked_source", "At least one official source produced a blocking signal."
    if "watch" in states:
        return "watch", "watch_source", "At least one official source produced a watch-level signal."
    if all(state == "ok" for state in states):
        return "ok", "all_sources_ok", "All configured official sources are currently positive."
    if any(state == "ok" for state in states):
        return "watch", "partial_ok", "At least one source is ok, but not all configured sources are positive."
    return "unknown", "no_positive_source", "No configured source produced a fresh positive status."


def result_to_dict(result: SourceResult) -> Dict[str, Any]:
    return {
        "source_id": result.source_id,
        "title": result.title,
        "type": result.type,
        "authority_type": result.authority_type,
        "state": result.state,
        "confidence": result.confidence,
        "checked_at": result.checked_at,
        "source_url": result.source_url,
        "latest_sample_date": result.latest_sample_date,
        "latest_sample_age_days": result.latest_sample_age_days,
        "latest_sample": result.latest_sample,
        "rows_seen": result.rows_seen,
        "reason": result.reason,
        "warnings": result.warnings,
        "errors": result.errors,
        "raw_signals": result.raw_signals,
    }


def build_report(today: dt.date, max_age_days: int, warn_age_days: int) -> Dict[str, Any]:
    generated_at = today_utc_iso()
    groups_output: List[Dict[str, Any]] = []
    all_source_results: List[SourceResult] = []
    for group in GROUPS:
        active_season = in_season(today, group.season_start, group.season_end)
        source_results = [check_source(source, today, max_age_days, warn_age_days) for source in group.sources]
        all_source_results.extend(source_results)
        group_state, group_confidence, group_reason = aggregate_group_state(source_results, active_season)
        groups_output.append(
            {
                "group_id": group.group_id,
                "activity_ids": group.activity_ids,
                "title": group.title,
                "state": group_state,
                "confidence": group_confidence,
                "reason": group_reason,
                "in_season": active_season,
                "season_start": group.season_start,
                "season_end": group.season_end,
                "source_ids": [source.id for source in group.sources],
                "sources": [result_to_dict(result) for result in source_results],
            }
        )
        # Be polite to external sources and avoid a burst of requests.
        time.sleep(0.2)
    counts: Dict[str, int] = {state: 0 for state in ["ok", "watch", "blocked", "unknown", "out_of_season"]}
    for group in groups_output:
        counts[group["state"]] = counts.get(group["state"], 0) + 1
    operationally_positive = counts.get("ok", 0)
    guarded_or_blocked = counts.get("blocked", 0) + counts.get("watch", 0) + counts.get("unknown", 0)
    return {
        "generated_at": generated_at,
        "script_version": SCRIPT_VERSION,
        "scope": "bathing_water_guard_v1_report_only_no_product_writeback",
        "guard_date": today.isoformat(),
        "policy": {
            "max_measurement_age_days": max_age_days,
            "warn_measurement_age_days": warn_age_days,
            "state_order": STATE_ORDER,
            "important": "This report never writes public activity highlight status. Positive results require a separate review before product writeback.",
        },
        "summary": {
            "groups_total": len(groups_output),
            "state_counts": counts,
            "operationally_positive_groups": operationally_positive,
            "guarded_or_blocked_groups": guarded_or_blocked,
            "ready_for_product_writeback": False,
            "recommendation": "review_report_before_any_writeback",
        },
        "groups": groups_output,
    }


def render_markdown(report: Dict[str, Any]) -> str:
    lines: List[str] = []
    lines.append("# Bathing Water Guard V1 Report")
    lines.append("")
    lines.append(f"Generated: `{report['generated_at']}`")
    lines.append(f"Guard date: `{report['guard_date']}`")
    lines.append(f"Scope: `{report['scope']}`")
    lines.append("")
    lines.append("## Summary")
    lines.append("")
    summary = report["summary"]
    lines.append(f"- Groups total: `{summary['groups_total']}`")
    lines.append(f"- State counts: `{json.dumps(summary['state_counts'], ensure_ascii=False)}`")
    lines.append(f"- Ready for product writeback: `{str(summary['ready_for_product_writeback']).lower()}`")
    lines.append(f"- Recommendation: `{summary['recommendation']}`")
    lines.append("")
    lines.append("> Report-only: this artifact does not update `data/offers.json` and does not activate public bathing highlights.")
    lines.append("")
    lines.append("## Activity groups")
    lines.append("")
    lines.append("| Group | State | Confidence | In season | Reason | Latest samples |")
    lines.append("|---|---|---|---:|---|---|")
    for group in report["groups"]:
        latest_samples = []
        for source in group["sources"]:
            if source.get("latest_sample_date"):
                latest_samples.append(f"{source['source_id']}: {source['latest_sample_date']} ({source.get('latest_sample_age_days')}d)")
            else:
                latest_samples.append(f"{source['source_id']}: n/a")
        lines.append(
            "| {group_id} | `{state}` | `{confidence}` | {in_season} | {reason} | {samples} |".format(
                group_id=group["group_id"],
                state=group["state"],
                confidence=group["confidence"],
                in_season="yes" if group["in_season"] else "no",
                reason=str(group["reason"]).replace("|", "\\|"),
                samples="<br>".join(latest_samples).replace("|", "\\|"),
            )
        )
    lines.append("")
    lines.append("## Source details")
    lines.append("")
    for group in report["groups"]:
        lines.append(f"### {group['title']} (`{group['group_id']}`)")
        lines.append("")
        for source in group["sources"]:
            lines.append(f"#### {source['title']} (`{source['source_id']}`)")
            lines.append("")
            lines.append(f"- State: `{source['state']}`")
            lines.append(f"- Confidence: `{source['confidence']}`")
            lines.append(f"- Reason: {source['reason']}")
            lines.append(f"- Source URL: `{source['source_url']}`")
            lines.append(f"- Rows seen: `{source['rows_seen']}`")
            if source.get("latest_sample_date"):
                lines.append(f"- Latest sample: `{source['latest_sample_date']}` (`{source.get('latest_sample_age_days')}` days old)")
            if source.get("latest_sample"):
                sample = source["latest_sample"]
                lines.append(f"- E. coli: `{sample.get('ec')}`")
                lines.append(f"- Enterococci: `{sample.get('enterococci')}`")
                lines.append(f"- Badeverbot parsed: `{sample.get('badeverbot')}`")
                if sample.get("signals"):
                    lines.append(f"- Signals: `{', '.join(sample.get('signals') or [])}`")
            if source.get("warnings"):
                lines.append(f"- Warnings: `{', '.join(source['warnings'])}`")
            if source.get("errors"):
                lines.append(f"- Errors: `{', '.join(source['errors'])}`")
            lines.append("")
    return "\n".join(lines).rstrip() + "\n"


def ensure_parent(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def main(argv: Optional[List[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Report-only bathing-water status guard.")
    parser.add_argument("--today", help="Guard date as YYYY-MM-DD; defaults to current UTC date.")
    parser.add_argument("--max-measurement-age-days", type=int, default=45)
    parser.add_argument("--warn-measurement-age-days", type=int, default=35)
    parser.add_argument("--out-json", default="bathing-water-status-guard.json")
    parser.add_argument("--out-md", default="bathing-water-status-guard.md")
    args = parser.parse_args(argv)
    today = parse_today(args.today)
    if args.warn_measurement_age_days > args.max_measurement_age_days:
        parser.error("--warn-measurement-age-days must be <= --max-measurement-age-days")
    report = build_report(today, args.max_measurement_age_days, args.warn_measurement_age_days)
    out_json = Path(args.out_json)
    out_md = Path(args.out_md)
    ensure_parent(out_json)
    ensure_parent(out_md)
    out_json.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    out_md.write_text(render_markdown(report), encoding="utf-8")
    print(json.dumps(report["summary"], ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
