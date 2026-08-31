#!/usr/bin/env python3
from __future__ import annotations

import datetime as dt
import json
import re
from collections import Counter, defaultdict
from pathlib import Path
from typing import Any, Callable, Dict, Iterable, List, Mapping, Sequence
from urllib.parse import urlparse


def _value(obj: Any, key: str, default: Any = None) -> Any:
    if isinstance(obj, Mapping):
        return obj.get(key, default)
    return getattr(obj, key, default)


def _as_int(value: Any) -> int | None:
    if value is None or value == "":
        return None
    try:
        return int(value)
    except (TypeError, ValueError):
        return None


def _clean(value: Any) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def _fold(value: Any) -> str:
    text = _clean(value).lower().translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    return re.sub(r"[^a-z0-9]+", " ", text).strip()


def _domain(raw_url: Any) -> str:
    raw = _clean(raw_url)
    if not raw:
        return "unknown"
    try:
        host = (urlparse(raw).hostname or "").lower()
    except Exception:
        host = ""
    return host.removeprefix("www.") or "unknown"


def _percent(numerator: int | float, denominator: int | float) -> float | None:
    if not denominator:
        return None
    return round(float(numerator) / float(denominator), 4)


def _median(values: Sequence[int]) -> float | None:
    if not values:
        return None
    ordered = sorted(values)
    midpoint = len(ordered) // 2
    if len(ordered) % 2:
        return float(ordered[midpoint])
    return (ordered[midpoint - 1] + ordered[midpoint]) / 2.0


def response_telemetry(response: Any) -> Dict[str, Any]:
    """Extract cost-relevant evidence from the already executed Responses call.

    No request is made here. Missing SDK fields stay explicit ``None`` rather
    than being guessed.
    """
    usage = _value(response, "usage")
    input_details = _value(usage, "input_tokens_details") if usage is not None else None
    output_details = _value(usage, "output_tokens_details") if usage is not None else None

    web_search_calls = 0
    source_urls: set[str] = set()
    for item in _value(response, "output", []) or []:
        item_type = _clean(_value(item, "type"))
        if item_type == "web_search_call":
            web_search_calls += 1
            action = _value(item, "action")
            for source in _value(action, "sources", []) or []:
                url = _clean(_value(source, "url"))
                if url:
                    source_urls.add(url)
        if item_type == "message":
            for content in _value(item, "content", []) or []:
                for annotation in _value(content, "annotations", []) or []:
                    citation = _value(annotation, "url_citation")
                    url = _clean(_value(citation, "url")) if citation is not None else ""
                    if url:
                        source_urls.add(url)

    return {
        "responses_api_calls": 1,
        "web_search_calls": web_search_calls,
        "cited_web_sources": len(source_urls),
        "input_tokens": _as_int(_value(usage, "input_tokens")) if usage is not None else None,
        "output_tokens": _as_int(_value(usage, "output_tokens")) if usage is not None else None,
        "total_tokens": _as_int(_value(usage, "total_tokens")) if usage is not None else None,
        "cached_input_tokens": _as_int(_value(input_details, "cached_tokens")) if input_details is not None else None,
        "reasoning_tokens": _as_int(_value(output_details, "reasoning_tokens")) if output_details is not None else None,
        "usage_status": "available" if usage is not None else "not_exposed_by_response",
    }


def augment_weekly_diagnostics(
    diagnostics_path: Path,
    telemetry: Mapping[str, Any],
    *,
    scheduled_runs_per_week: int = 1,
) -> Dict[str, Any]:
    payload = json.loads(diagnostics_path.read_text(encoding="utf-8"))
    candidate_rows = [row for row in payload.get("candidate_diagnostics", []) if isinstance(row, dict)]
    selected_rows = [row for row in payload.get("selected_candidate_summaries", []) if isinstance(row, dict)]

    by_domain: Dict[str, Dict[str, Any]] = defaultdict(
        lambda: {"observed": 0, "selected": 0, "dropped": 0, "drop_reasons": Counter()}
    )
    for row in candidate_rows:
        domain = _domain(row.get("source_url") or row.get("url"))
        bucket = by_domain[domain]
        bucket["observed"] += 1
        reason = _clean(row.get("reason")) or "unknown"
        if reason == "selected":
            bucket["selected"] += 1
        else:
            bucket["dropped"] += 1
            bucket["drop_reasons"][reason] += 1

    source_yield = []
    for domain, bucket in sorted(by_domain.items(), key=lambda item: (-item[1]["observed"], item[0])):
        source_yield.append({
            "domain": domain,
            "observed_candidates": bucket["observed"],
            "selected_candidates": bucket["selected"],
            "dropped_candidates": bucket["dropped"],
            "selected_yield": _percent(bucket["selected"], bucket["observed"]),
            "drop_reasons": dict(sorted(bucket["drop_reasons"].items())),
        })

    generated = _clean(payload.get("generated_at_utc"))
    try:
        run_date = dt.datetime.fromisoformat(generated.replace("Z", "+00:00")).date()
    except Exception:
        run_date = dt.date.today()
    lead_days: list[int] = []
    for row in selected_rows:
        raw_date = _clean(row.get("date"))
        try:
            event_date = dt.date.fromisoformat(raw_date)
        except ValueError:
            continue
        lead_days.append((event_date - run_date).days)

    coverage_rows = [row for row in payload.get("coverage_audit", []) if isinstance(row, dict)]
    inactive = {"PAST_TARGET", "TARGET_OUT_OF_ACTIVE_WINDOW"}
    missing = {"MISSING_FROM_RAW"}
    eligible_rows = [row for row in coverage_rows if _clean(row.get("status")) not in inactive]
    present_rows = [row for row in eligible_rows if _clean(row.get("status")) not in missing]
    missing_rows = [row for row in eligible_rows if _clean(row.get("status")) in missing]

    raw_count = int(payload.get("raw_candidates_returned", 0) or 0)
    selected_count = int(payload.get("selected_candidates", 0) or 0)
    dropped_count = int(payload.get("dropped_candidates", 0) or 0)
    raw_source_count = int(payload.get("raw_source_candidates_returned", 0) or 0)
    added_source_count = int(payload.get("source_candidates_added", 0) or 0)

    measurement = {
        "version": 1,
        "cost_and_cadence": {
            "model": payload.get("model", ""),
            **dict(telemetry),
            "scheduled_runs_per_week": scheduled_runs_per_week,
            "additional_paid_calls_added_by_measurement": 0,
            "recurring_cost_delta": "neutral",
            "additional_runtime_work": "local deterministic post-processing only",
        },
        "candidate_yield": {
            "raw_candidates": raw_count,
            "selected_candidates": selected_count,
            "dropped_candidates": dropped_count,
            "selected_yield": _percent(selected_count, raw_count),
            "by_source_domain": source_yield,
        },
        "source_learning_yield": {
            "raw_source_candidates": raw_source_count,
            "source_candidates_added": added_source_count,
            "added_yield": _percent(added_source_count, raw_source_count),
            "reason_counts": payload.get("source_candidate_reasons", {}),
        },
        "discovery_lead_time_days": {
            "count": len(lead_days),
            "min": min(lead_days) if lead_days else None,
            "median": _median(lead_days),
            "max": max(lead_days) if lead_days else None,
            "under_7_days": sum(1 for value in lead_days if value < 7),
            "days_7_to_29": sum(1 for value in lead_days if 7 <= value < 30),
            "days_30_plus": sum(1 for value in lead_days if value >= 30),
        },
        "known_target_baseline": {
            "eligible_targets": len(eligible_rows),
            "present_or_detected_targets": len(present_rows),
            "missing_targets": len(missing_rows),
            "presence_rate": _percent(len(present_rows), len(eligible_rows)),
            "missing_target_ids": [_clean(row.get("id")) for row in missing_rows if _clean(row.get("id"))][:40],
            "interpretation": "Curated known-target baseline; not an estimate of total local-market recall.",
        },
        "review_workload_proxy": {
            "new_candidates_requiring_review": selected_count,
            "manual_review_minutes": None,
            "status": "duration_not_persisted",
        },
        "evidence_limits": [
            "Known targets measure only curated must-have coverage, not unknown misses.",
            "Manual review duration is not persisted by the current review owner.",
        ],
    }
    payload["coverage_measurement"] = measurement
    diagnostics_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return measurement


def _records_from_sheet_rows(rows: Sequence[Sequence[Any]]) -> List[Dict[str, str]]:
    if not rows:
        return []
    header = [_clean(value) for value in rows[0]]
    records: list[dict[str, str]] = []
    for raw in rows[1:]:
        row = {name: _clean(raw[index]) if index < len(raw) else "" for index, name in enumerate(header) if name}
        if any(row.values()):
            records.append(row)
    return records


def _offer_text(offer: Mapping[str, Any]) -> str:
    values: list[str] = []
    for key in ("title", "kategorie", "location", "description", "mode", "season"):
        values.append(_clean(offer.get(key)))
    for key in ("tags", "filter_tags"):
        values.extend(_clean(value) for value in (offer.get(key) or []) if _clean(value))
    filter_block = offer.get("filter") or {}
    if isinstance(filter_block, Mapping):
        for value in filter_block.values():
            if isinstance(value, list):
                values.extend(_clean(item) for item in value if _clean(item))
            else:
                values.append(_clean(value))
    recommendation = offer.get("recommendation") or {}
    if isinstance(recommendation, Mapping):
        values.extend(_clean(value) for value in (recommendation.get("interest_tags") or []) if _clean(value))
    return _fold(" ".join(values))


def _record_text(record: Mapping[str, Any]) -> str:
    return _fold(" ".join(_clean(value) for value in record.values()))


INTENT_MATCH_TERMS: Dict[str, tuple[str, ...]] = {
    "bad-weather-indoor": ("indoor", "drinnen", "hallenbad", "kino", "museum", "theater", "bowling", "escape", "klettern"),
    "family-kids": ("familie", "kinder", "kind", "spielplatz", "familien", "jugend"),
    "swimming": ("schwimmen", "hallenbad", "schwimmbad", "freibad", "bahia", "badesee", "aasee", "baden"),
    "food-after-activity": ("cafe", "restaurant", "essen", "eiscafe", "gastronomie"),
}


def _text_matches_intent(text: str, intent_key: str, raw_topics: Iterable[str]) -> bool:
    if intent_key in {"today-events", "weekend-events"}:
        return False
    mapped_terms = INTENT_MATCH_TERMS.get(intent_key, ())
    if mapped_terms:
        terms = set(mapped_terms)
    else:
        stop = {
            "bocholt", "rhede", "borken", "isselburg", "heute", "morgen", "wochenende",
            "veranstaltung", "veranstaltungen", "event", "events",
        }
        terms = {
            word
            for topic in raw_topics
            for word in _fold(topic).split()
            if len(word) >= 4 and word not in stop
        }
    return any(term in text for term in terms if term)


def _event_temporal_match(record: Mapping[str, Any], intent_key: str, today: dt.date) -> bool:
    raw = _clean(record.get("date"))
    try:
        event_date = dt.date.fromisoformat(raw)
    except ValueError:
        return False
    if intent_key == "today-events":
        return event_date == today
    if intent_key == "weekend-events":
        days_until_saturday = (5 - today.weekday()) % 7
        saturday = today + dt.timedelta(days=days_until_saturday)
        sunday = saturday + dt.timedelta(days=1)
        return saturday <= event_date <= sunday
    return False


def _target_present(target: Mapping[str, Any], events: Sequence[Mapping[str, Any]]) -> bool:
    target_title = _fold(target.get("title"))
    target_date = _clean(target.get("expected_date") or target.get("date"))
    source_hint = _fold(target.get("source_hint"))
    aliases = [_fold(value) for value in (target.get("aliases") or []) if _fold(value)]
    for event in events:
        if target_date and _clean(event.get("date")) != target_date:
            continue
        event_text = _record_text(event)
        if target_title and target_title in event_text:
            return True
        if any(alias in event_text for alias in aliases):
            return True
        if source_hint and source_hint in event_text:
            return True
    return False


def build_portfolio_coverage(
    gsc_rows: Sequence[Mapping[str, Any]],
    event_sheet_rows: Sequence[Sequence[Any]],
    offers_payload: Mapping[str, Any],
    event_targets_payload: Mapping[str, Any] | Sequence[Mapping[str, Any]],
    *,
    ga4_rows: Sequence[Mapping[str, Any]] = (),
    value_rows: Sequence[Mapping[str, Any]] = (),
    infer_intent: Callable[[str], Mapping[str, str]],
    canonical_topic: Callable[[str], str],
    min_impressions: int = 40,
    today: dt.date | None = None,
) -> Dict[str, Any]:
    today = today or dt.date.today()
    events = _records_from_sheet_rows(event_sheet_rows)
    offers = [
        item
        for item in (offers_payload.get("offers", []) if isinstance(offers_payload, Mapping) else [])
        if isinstance(item, Mapping)
    ]
    targets = event_targets_payload.get("targets", []) if isinstance(event_targets_payload, Mapping) else event_targets_payload
    targets = [item for item in (targets or []) if isinstance(item, Mapping)]

    grouped: Dict[str, Dict[str, Any]] = {}
    for row in gsc_rows:
        query = _clean(row.get("query"))
        impressions = float(row.get("impressions", 0) or 0)
        if not query or impressions < min_impressions:
            continue
        intent = dict(infer_intent(query))
        key = _clean(intent.get("key")) or "general"
        bucket = grouped.setdefault(key, {
            "intent": intent,
            "impressions": 0.0,
            "clicks": 0.0,
            "queries": Counter(),
            "topics": Counter(),
        })
        bucket["impressions"] += impressions
        bucket["clicks"] += float(row.get("clicks", 0) or 0)
        bucket["queries"][query] += 1
        bucket["topics"][_clean(canonical_topic(query))] += 1

    offer_index = [(str(offer.get("id", "")), _offer_text(offer)) for offer in offers]
    event_index = [
        (_clean(event.get("id")) or _clean(event.get("title")), _record_text(event), event)
        for event in events
    ]
    demand_clusters: list[dict[str, Any]] = []
    for key, bucket in sorted(grouped.items(), key=lambda item: (-item[1]["impressions"], item[0])):
        topics = list(bucket["topics"].keys())
        activity_matches = [offer_id for offer_id, text in offer_index if _text_matches_intent(text, key, topics)]
        if key in {"today-events", "weekend-events"}:
            event_matches = [
                event_id for event_id, _text, event in event_index if _event_temporal_match(event, key, today)
            ]
        else:
            event_matches = [
                event_id for event_id, text, _event in event_index if _text_matches_intent(text, key, topics)
            ]
        impressions = bucket["impressions"]
        clicks = bucket["clicks"]
        demand_clusters.append({
            "intent_key": key,
            "intent_label": _clean(bucket["intent"].get("label")),
            "impressions": int(impressions),
            "clicks": int(clicks),
            "ctr": round(clicks / impressions, 4) if impressions else 0.0,
            "top_queries": [query for query, _count in bucket["queries"].most_common(8)],
            "activity_match_count": len(activity_matches),
            "event_match_count": len(event_matches),
            "activity_match_ids": activity_matches[:20],
            "event_match_ids": event_matches[:20],
            "coverage_state": "gap" if not activity_matches and not event_matches else "covered",
        })

    category_counts = Counter(_clean(offer.get("kategorie")) or "Unkategorisiert" for offer in offers)
    proximity_counts: Counter[str] = Counter()
    for offer in offers:
        block = offer.get("filter") or {}
        if isinstance(block, Mapping):
            for value in block.get("proximity", []) or []:
                if _clean(value):
                    proximity_counts[_clean(value)] += 1

    eligible_targets: list[Mapping[str, Any]] = []
    for target in targets:
        raw_date = _clean(target.get("expected_date") or target.get("date"))
        try:
            target_date = dt.date.fromisoformat(raw_date) if raw_date else None
        except ValueError:
            target_date = None
        if target_date and target_date < today:
            continue
        eligible_targets.append(target)
    present_targets = [target for target in eligible_targets if _target_present(target, events)]
    missing_targets = [target for target in eligible_targets if not _target_present(target, events)]

    organic_sessions = 0.0
    landing_sessions: Counter[str] = Counter()
    for row in ga4_rows:
        channel = _clean(row.get("channel"))
        if channel and "organic" not in channel.lower() and channel not in {"Unassigned"}:
            continue
        sessions = float(row.get("sessions", 0) or 0)
        organic_sessions += sessions
        landing = _clean(row.get("landing_page")) or "/"
        landing_sessions[landing] += int(sessions)

    value_metric_totals: Counter[str] = Counter()
    value_entities: Counter[str] = Counter()
    for row in value_rows:
        metric_key = _clean(row.get("metric_key")) or "unknown"
        total = int(float(row.get("total", 0) or 0))
        value_metric_totals[metric_key] += total
        entity = _clean(
            row.get("reporting_target_title")
            or row.get("entity_title")
            or row.get("reporting_target_id")
            or row.get("entity_id")
        )
        if entity:
            value_entities[entity] += total

    gap_clusters = [cluster for cluster in demand_clusters if cluster["coverage_state"] == "gap"]
    return {
        "version": 1,
        "generated_at_utc": dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z"),
        "demand_clusters": demand_clusters,
        "demand_gap_count": len(gap_clusters),
        "demand_gap_intents": [cluster["intent_key"] for cluster in gap_clusters],
        "usage_evidence": {
            "ga4_rows": len(ga4_rows),
            "organic_or_unassigned_sessions": int(organic_sessions),
            "top_landing_pages": [
                {"path": path, "sessions": sessions}
                for path, sessions in landing_sessions.most_common(10)
            ],
            "value_metric_rows": len(value_rows),
            "value_metric_totals": dict(sorted(value_metric_totals.items())),
            "top_value_entities": [
                {"entity": entity, "interactions": interactions}
                for entity, interactions in value_entities.most_common(10)
            ],
        },
        "activity_inventory": {
            "offers_total": len(offers),
            "categories": dict(sorted(category_counts.items())),
            "proximity": dict(sorted(proximity_counts.items())),
        },
        "event_inventory": {
            "events_total": len(events),
            "known_targets_eligible": len(eligible_targets),
            "known_targets_present": len(present_targets),
            "known_targets_missing": len(missing_targets),
            "known_target_presence_rate": _percent(len(present_targets), len(eligible_targets)),
            "missing_target_ids": [
                _clean(target.get("id")) for target in missing_targets if _clean(target.get("id"))
            ][:40],
        },
        "cost": {
            "additional_ai_calls": 0,
            "additional_web_search_calls": 0,
            "additional_gsc_calls": 0,
            "additional_ga4_calls": 0,
            "recurring_cost_delta": "neutral",
            "evidence_source": "already-returned Growth Intelligence API data + current Sheets inventory + repo offers/targets",
        },
        "evidence_limits": [
            "Demand matching is deterministic and intentionally conservative; semantic near-matches may remain unmatched.",
            "Known event targets are a curated baseline and do not define total local-market recall.",
            "Activity inventory is curated; absence from demand clusters does not mean the activity lacks user value.",
        ],
    }


def augment_growth_summary(summary_path: Path, portfolio_coverage: Mapping[str, Any]) -> Dict[str, Any]:
    payload = json.loads(summary_path.read_text(encoding="utf-8"))
    payload["portfolio_coverage"] = dict(portfolio_coverage)
    summary_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return payload
