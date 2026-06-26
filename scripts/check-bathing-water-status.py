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

SCRIPT_VERSION = "BATHING_WATER_GUARD_V1_2_LOCAL_SUITABILITY_REPORT_ONLY"
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
    "actuele situatie:in orde",
    "actuele situatie in orde",
    "zwemplek status: goed",
    "zwemplek status:goed",
    "zwemplek status goed",
    "zwemplek status: in orde",
    "zwemplek status:in orde",
    "zwemplek status in orde",
    "de waterkwaliteit is goed",
]

STRICT_NEGATIVE_ZWEMWATER_PHRASES = [
    "actuele situatie: negatief zwemadvies",
    "actuele situatie:negatief zwemadvies",
    "actuele situatie negatief zwemadvies",
    "zwemplek status: negatief zwemadvies",
    "zwemplek status:negatief zwemadvies",
    "zwemplek status negatief zwemadvies",
    "actuele situatie: zwemmen wordt afgeraden",
    "actuele situatie:zwemmen wordt afgeraden",
    "zwemplek status: zwemmen wordt afgeraden",
    "zwemplek status:zwemmen wordt afgeraden",
]
STRICT_WATCH_ZWEMWATER_PHRASES = [
    "actuele situatie: waarschuwing",
    "actuele situatie:waarschuwing",
    "zwemplek status: waarschuwing",
    "zwemplek status:waarschuwing",
    "actuele situatie: waterkwaliteit wordt onderzocht",
    "actuele situatie:waterkwaliteit wordt onderzocht",
    "zwemplek status: waterkwaliteit wordt onderzocht",
    "zwemplek status:waterkwaliteit wordt onderzocht",
]
STRICT_POSITIVE_ZWEMWATER_PHRASES = [
    "actuele situatie: in orde",
    "actuele situatie:in orde",
    "actuele situatie in orde",
    "zwemplek status: goed",
    "zwemplek status:goed",
    "zwemplek status goed",
    "zwemplek status: in orde",
    "zwemplek status:in orde",
    "zwemplek status in orde",
]

# V1.2 separates microbiological/legal water status from practical local
# suitability. Good lab values and no official ban are necessary, but not enough
# for an active public bathing recommendation.
LOCAL_BLOCK_PHRASES = [
    "badeverbot",
    "badebucht geschlossen",
    "badestelle geschlossen",
    "badebereich geschlossen",
    "gesperrt",
    "blaualgen",
    "cyanobakterien",
    "microcystin",
    "einsinkgefahr",
    "nicht ins wasser",
    "baden verboten",
    "vom baden wird abgeraten",
]

LOCAL_WATCH_PHRASES = [
    "schlamm",
    "schlammig",
    "geruch",
    "stinkt",
    "ablagerung",
    "ablagerungen",
    "algen",
    "trübung",
    "truebung",
    "sichttiefe",
    "keine badegäste",
    "keine badegaeste",
    "badegäste bleiben aus",
    "badegaeste bleiben aus",
    "badegäste abgeschreckt",
    "badegaeste abgeschreckt",
]

LOCAL_POSITIVE_PHRASES = [
    "grüne flagge",
    "gruene flagge",
    "badestelle freigegeben",
    "badebucht geöffnet",
    "badebucht geoeffnet",
    "baden möglich",
    "baden moeglich",
    "wassersport möglich",
    "wassersport moeglich",
    "schwimmen möglich",
    "schwimmen moeglich",
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
    dimension: str = "water_quality"
    source_role: str = "official_status"
    valid_until: Optional[str] = None
    max_age_days: Optional[int] = None


@dataclass(frozen=True)
class GroupConfig:
    group_id: str
    activity_ids: List[str]
    title: str
    season_start: str
    season_end: str
    sources: List[SourceConfig]
    local_sources: List[SourceConfig] = field(default_factory=list)


@dataclass
class SourceResult:
    source_id: str
    title: str
    type: str
    authority_type: str
    dimension: str
    source_role: str
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
        local_sources=[
            SourceConfig(
                id="aasee-bocholt-official-aasee-page",
                type="local_suitability_page",
                title="Stadt Bocholt - Aasee",
                authority_type="official_city",
                url="https://www.bocholt.de/aasee",
                dimension="local_suitability",
                source_role="official_positive_or_negative",
                max_age_days=45,
            ),
            SourceConfig(
                id="aasee-bocholt-local-press-sludge-signal",
                type="local_suitability_page",
                title="Lokales Negativsignal - Aasee Schlamm/Geruch",
                authority_type="local_press_negative_signal",
                url="https://www.bbv-net.de/bocholt/bocholter-aasee-schlamm-geruch-stinkt-keine-badegaeste-seezustand-probleme-w1212295-6000574615/",
                dimension="local_suitability",
                source_role="negative_signal_only",
                valid_until="2026-07-15",
            ),
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
            dimension=source.dimension,
            source_role=source.source_role,
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
            dimension=source.dimension,
            source_role=source.source_role,
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
            dimension=source.dimension,
            source_role=source.source_role,
            state="unknown",
            confidence="no_current_year_samples",
            checked_at=checked_at,
            source_url=source.url,
            rows_seen=len(rows),
            reason="No dated current-year sample row found in NRW endpoint.",
            raw_signals={"endpoint_url": url, "http_status": status, "content_type": content_type},
        )
    warnings: List[str] = []
    future_dated = [
        item for item in dated
        if dt.date.fromisoformat(str(item["sample_date"])) > today
    ]
    dated_non_future = [
        item for item in dated
        if dt.date.fromisoformat(str(item["sample_date"])) <= today
    ]
    if future_dated:
        warnings.append(f"{len(future_dated)} future sample rows ignored")
    if not dated_non_future:
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            dimension=source.dimension,
            source_role=source.source_role,
            state="unknown",
            confidence="no_non_future_sample",
            checked_at=checked_at,
            source_url=source.url,
            rows_seen=len(rows),
            reason="Only future-dated NRW sample rows were found; no current status can be derived.",
            warnings=warnings,
            raw_signals={"endpoint_url": url, "http_status": status, "content_type": content_type},
        )
    dated_non_future.sort(key=lambda item: item["sample_date"])
    latest = dated_non_future[-1]
    latest_date = dt.date.fromisoformat(str(latest["sample_date"]))
    age_days = (today - latest_date).days
    state_from_values = str(latest.get("state_from_values") or "unknown")
    state = state_from_values
    confidence = "official_current_sample"
    reason = "Latest NRW non-future sample is recent enough and no blocking signal was parsed."
    if age_days > max_age_days:
        state = "unknown"
        confidence = "stale_sample"
        reason = f"Latest NRW non-future sample is older than {max_age_days} days."
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
        dimension=source.dimension,
        source_role=source.source_role,
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
            dimension=source.dimension,
            source_role=source.source_role,
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
    strict_negative = [phrase for phrase in STRICT_NEGATIVE_ZWEMWATER_PHRASES if phrase in plain]
    strict_watch = [phrase for phrase in STRICT_WATCH_ZWEMWATER_PHRASES if phrase in plain]
    strict_positive = [phrase for phrase in STRICT_POSITIVE_ZWEMWATER_PHRASES if phrase in plain]

    state = "unknown"
    confidence = "no_specific_status_signal"
    reason = "No sufficiently specific Zwemwater status phrase was parsed."

    # Zwemwater pages can contain complete status legends or advice text for all
    # possible states. Page-wide phrase matches are therefore not enough for a
    # public status decision when positive and negative/watch terms coexist.
    if strict_negative:
        state = "blocked"
        confidence = "official_scoped_negative_status_phrase"
        reason = "Zwemwater page contains a scoped negative current-status phrase."
    elif strict_watch:
        state = "watch"
        confidence = "official_scoped_watch_status_phrase"
        reason = "Zwemwater page contains a scoped watch-level current-status phrase."
    elif strict_positive:
        state = "ok"
        confidence = "official_scoped_positive_status_phrase"
        reason = "Zwemwater page contains a scoped positive current-status phrase."
    elif found_positive and (found_negative or found_watch):
        state = "unknown"
        confidence = "mixed_unscoped_status_phrases"
        reason = (
            "Zwemwater page contains mixed positive and negative/watch phrases outside "
            "a scoped current-status context; no status decision is safe."
        )
    elif found_negative:
        state = "blocked"
        confidence = "unscoped_negative_status_phrase"
        reason = "Zwemwater page contains a negative phrase and no positive phrase was parsed."
    elif found_watch:
        state = "watch"
        confidence = "unscoped_watch_status_phrase"
        reason = "Zwemwater page contains a watch phrase and no positive phrase was parsed."
    elif found_positive:
        state = "ok"
        confidence = "unscoped_positive_status_phrase"
        reason = "Zwemwater page contains a positive phrase and no negative/watch phrase was parsed."

    return SourceResult(
        source_id=source.id,
        title=source.title,
        type=source.type,
        authority_type=source.authority_type,
        dimension=source.dimension,
        source_role=source.source_role,
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
            "strict_negative_phrases": strict_negative,
            "strict_watch_phrases": strict_watch,
            "strict_positive_phrases": strict_positive,
        },
    )


def parse_iso_date_optional(value: Optional[str]) -> Optional[dt.date]:
    if not value:
        return None
    try:
        return dt.date.fromisoformat(value)
    except ValueError:
        return None


def find_local_signals(plain: str) -> Dict[str, List[str]]:
    return {
        "block_phrases": [phrase for phrase in LOCAL_BLOCK_PHRASES if phrase in plain],
        "watch_phrases": [phrase for phrase in LOCAL_WATCH_PHRASES if phrase in plain],
        "positive_phrases": [phrase for phrase in LOCAL_POSITIVE_PHRASES if phrase in plain],
    }


def check_local_suitability_source(source: SourceConfig, today: dt.date) -> SourceResult:
    checked_at = today_utc_iso()
    valid_until = parse_iso_date_optional(source.valid_until)
    if valid_until and today > valid_until:
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            dimension=source.dimension,
            source_role=source.source_role,
            state="unknown",
            confidence="local_signal_expired",
            checked_at=checked_at,
            source_url=source.url,
            reason=f"Configured local suitability signal expired on {valid_until.isoformat()}.",
            raw_signals={"valid_until": source.valid_until},
        )
    try:
        status, content_type, text = request_url(source.url, accept="text/html,*/*")
    except Exception as exc:  # noqa: BLE001
        return SourceResult(
            source_id=source.id,
            title=source.title,
            type=source.type,
            authority_type=source.authority_type,
            dimension=source.dimension,
            source_role=source.source_role,
            state="unknown",
            confidence="fetch_failed",
            checked_at=checked_at,
            source_url=source.url,
            reason="Local suitability page could not be fetched.",
            errors=[str(exc)],
            raw_signals={"valid_until": source.valid_until},
        )
    plain = norm_text(text)
    signals = find_local_signals(plain)
    block_phrases = signals["block_phrases"]
    watch_phrases = signals["watch_phrases"]
    positive_phrases = signals["positive_phrases"]
    state = "unknown"
    confidence = "no_local_suitability_signal"
    reason = "No local suitability signal was parsed."

    # Local press is strong enough to suppress a public recommendation, but not
    # to prove an official legal bathing ban. Therefore negative press signals
    # become `watch`, not `blocked`, unless an explicit official/ban phrase is
    # present on an official source. `watch` still prevents public highlights.
    if source.source_role == "negative_signal_only":
        if block_phrases or watch_phrases:
            state = "watch"
            confidence = "local_negative_signal"
            reason = "Local negative suitability signal parsed; do not actively recommend bathing."
        else:
            state = "unknown"
            confidence = "negative_signal_source_without_match"
            reason = "Negative-signal source fetched, but configured watch/block phrases were not parsed."
    elif source.source_role in {"official_positive_or_negative", "operator_positive_or_negative"}:
        if block_phrases:
            state = "blocked"
            confidence = "official_local_block_signal"
            reason = "Official/operator source contains a local block signal."
        elif watch_phrases:
            state = "watch"
            confidence = "official_local_watch_signal"
            reason = "Official/operator source contains a local watch signal."
        elif positive_phrases:
            state = "ok"
            confidence = "official_local_positive_signal"
            reason = "Official/operator source contains a positive local suitability signal."
        else:
            state = "unknown"
            confidence = "official_source_without_status_phrase"
            reason = "Official/operator source fetched, but no local suitability phrase was parsed."
    else:
        state = "unknown"
        confidence = "unsupported_local_source_role"
        reason = f"Unsupported local source role: {source.source_role}."

    return SourceResult(
        source_id=source.id,
        title=source.title,
        type=source.type,
        authority_type=source.authority_type,
        dimension=source.dimension,
        source_role=source.source_role,
        state=state,
        confidence=confidence,
        checked_at=checked_at,
        source_url=source.url,
        rows_seen=1,
        reason=reason,
        warnings=[],
        raw_signals={
            "http_status": status,
            "content_type": content_type,
            "valid_until": source.valid_until,
            "block_phrases": block_phrases,
            "watch_phrases": watch_phrases,
            "positive_phrases": positive_phrases,
        },
    )


def check_source(source: SourceConfig, today: dt.date, max_age_days: int, warn_age_days: int) -> SourceResult:
    if source.type == "nrw_datatables":
        return check_nrw_source(source, today, max_age_days, warn_age_days)
    if source.type == "zwemwater_page":
        return check_zwemwater_source(source, today)
    if source.type == "local_suitability_page":
        return check_local_suitability_source(source, today)
    raise ValueError(f"Unsupported source type: {source.type}")


def aggregate_dimension_state(
    source_results: List[SourceResult],
    active_season: bool,
    *,
    dimension_label: str,
) -> Tuple[str, str, str]:
    if not active_season:
        return "out_of_season", "season_inactive", "Configured swimming season is not active."
    if not source_results:
        return "unknown", "no_sources", f"No {dimension_label} source result exists."
    states = [result.state for result in source_results]
    if "blocked" in states:
        return "blocked", "blocked_source", f"At least one {dimension_label} source produced a blocking signal."
    if "watch" in states:
        return "watch", "watch_source", f"At least one {dimension_label} source produced a watch-level signal."
    if all(state == "ok" for state in states):
        return "ok", "all_sources_ok", f"All configured {dimension_label} sources are currently positive."
    if any(state == "ok" for state in states):
        return "watch", "partial_ok", f"At least one {dimension_label} source is ok, but not all configured sources are positive."
    return "unknown", "no_positive_source", f"No configured {dimension_label} source produced a fresh positive status."


def aggregate_final_state(water_state: str, local_state: str, active_season: bool) -> Tuple[str, str, str]:
    if not active_season:
        return "out_of_season", "season_inactive", "Configured swimming season is not active."
    if water_state == "blocked":
        return "blocked", "water_blocked", "Water-quality/legal source blocks bathing."
    if local_state == "blocked":
        return "blocked", "local_blocked", "Local suitability source blocks an active bathing recommendation."
    if local_state == "watch":
        return "watch", "local_watch", "Local suitability warning suppresses an active bathing recommendation."
    if water_state == "watch":
        return "watch", "water_watch", "Water-quality source is only watch-level."
    if water_state == "unknown":
        return "unknown", "water_unknown", "Water-quality status is unknown or stale."
    if water_state == "ok" and local_state == "ok":
        return "ok", "water_and_local_ok", "Water status and local suitability are both positive."
    if water_state == "ok" and local_state == "unknown":
        return "watch", "local_not_proven", "Water status is positive, but local suitability is not positively proven."
    return "unknown", "not_fully_positive", "Final status is not fully positive."


def result_to_dict(result: SourceResult) -> Dict[str, Any]:
    return {
        "source_id": result.source_id,
        "title": result.title,
        "type": result.type,
        "authority_type": result.authority_type,
        "dimension": result.dimension,
        "source_role": result.source_role,
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
        water_results = [check_source(source, today, max_age_days, warn_age_days) for source in group.sources]
        # Local suitability is intentionally separate from lab/legal water status.
        # It can suppress an active recommendation even if water values are `ok`.
        local_results = [check_source(source, today, max_age_days, warn_age_days) for source in group.local_sources]
        source_results = water_results + local_results
        all_source_results.extend(source_results)
        water_state, water_confidence, water_reason = aggregate_dimension_state(
            water_results, active_season, dimension_label="water-quality"
        )
        local_state, local_confidence, local_reason = aggregate_dimension_state(
            local_results, active_season, dimension_label="local-suitability"
        )
        group_state, group_confidence, group_reason = aggregate_final_state(water_state, local_state, active_season)
        groups_output.append(
            {
                "group_id": group.group_id,
                "activity_ids": group.activity_ids,
                "title": group.title,
                "state": group_state,
                "confidence": group_confidence,
                "reason": group_reason,
                "water_state": water_state,
                "water_confidence": water_confidence,
                "water_reason": water_reason,
                "local_suitability_state": local_state,
                "local_suitability_confidence": local_confidence,
                "local_suitability_reason": local_reason,
                "in_season": active_season,
                "season_start": group.season_start,
                "season_end": group.season_end,
                "source_ids": [source.id for source in group.sources],
                "local_source_ids": [source.id for source in group.local_sources],
                "sources": [result_to_dict(result) for result in source_results],
                "water_sources": [result_to_dict(result) for result in water_results],
                "local_suitability_sources": [result_to_dict(result) for result in local_results],
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
        "scope": "bathing_water_guard_v1_2_report_only_no_product_writeback",
        "guard_date": today.isoformat(),
        "policy": {
            "max_measurement_age_days": max_age_days,
            "warn_measurement_age_days": warn_age_days,
            "state_order": STATE_ORDER,
            "important": "This report never writes public activity highlight status. A public bathing recommendation requires both positive water status and positive local suitability; positive lab values alone are not enough.",
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
    lines.append("# Bathing Water Guard V1.2 Report")
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
    lines.append("| Group | Final | Water | Local suitability | In season | Reason | Latest samples |")
    lines.append("|---|---|---|---|---:|---|---|")
    for group in report["groups"]:
        latest_samples = []
        for source in group["water_sources"]:
            if source.get("latest_sample_date"):
                latest_samples.append(f"{source['source_id']}: {source['latest_sample_date']} ({source.get('latest_sample_age_days')}d)")
            else:
                latest_samples.append(f"{source['source_id']}: n/a")
        lines.append(
            "| {group_id} | `{state}` | `{water_state}` | `{local_state}` | {in_season} | {reason} | {samples} |".format(
                group_id=group["group_id"],
                state=group["state"],
                water_state=group.get("water_state"),
                local_state=group.get("local_suitability_state"),
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
            lines.append(f"- Dimension: `{source.get('dimension')}`")
            lines.append(f"- Source role: `{source.get('source_role')}`")
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
            raw_signals = source.get("raw_signals") or {}
            if raw_signals.get("block_phrases"):
                lines.append(f"- Local block phrases: `{', '.join(raw_signals.get('block_phrases') or [])}`")
            if raw_signals.get("watch_phrases"):
                lines.append(f"- Local watch phrases: `{', '.join(raw_signals.get('watch_phrases') or [])}`")
            if raw_signals.get("positive_phrases"):
                lines.append(f"- Local positive phrases: `{', '.join(raw_signals.get('positive_phrases') or [])}`")
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
