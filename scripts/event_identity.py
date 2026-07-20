from __future__ import annotations

import json
import math
import re
import unicodedata
from datetime import date, datetime
from pathlib import Path
from typing import Any, Iterable, Mapping
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CONTRACT_PATH = ROOT / "data" / "event_identity_contract.json"


def _text(value: Any) -> str:
    return str(value or "").strip()


def load_event_identity_contract(path: Path | None = None) -> dict[str, Any]:
    contract_path = path or DEFAULT_CONTRACT_PATH
    payload = json.loads(contract_path.read_text(encoding="utf-8"))
    if not isinstance(payload, dict):
        raise ValueError("event_identity_contract.json must contain an object")
    weights = payload.get("weights")
    if not isinstance(weights, dict) or not math.isclose(
        sum(float(weights.get(key, 0.0)) for key in ("title", "location", "city", "source_host")),
        1.0,
        abs_tol=1e-9,
    ):
        raise ValueError("event identity weights must sum to 1.0")
    return payload


def normalize_identity_text(value: Any) -> str:
    raw = _text(value).lower()
    raw = raw.replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
    raw = unicodedata.normalize("NFKD", raw)
    raw = "".join(ch for ch in raw if not unicodedata.combining(ch))
    raw = re.sub(r"[^a-z0-9]+", " ", raw)
    return re.sub(r"\s+", " ", raw).strip()


def canonical_identity_url(raw: Any, contract: Mapping[str, Any] | None = None) -> str:
    value = re.sub(r"\s+", "", _text(raw))
    if not value:
        return ""
    if "://" not in value:
        value = "https://" + value
    try:
        parsed = urlparse(value)
    except Exception:
        return value.rstrip("/")
    tracking = set((contract or {}).get("tracking_parameters") or [])
    kept = []
    for key, item_value in parse_qsl(parsed.query, keep_blank_values=True):
        lower = key.lower().strip()
        if lower.startswith("utm_") or lower in tracking:
            continue
        kept.append((key, item_value))
    cleaned = parsed._replace(
        scheme=(parsed.scheme or "https").lower(),
        netloc=parsed.netloc.lower(),
        query=urlencode(kept, doseq=True),
        fragment="",
    )
    return urlunparse(cleaned).rstrip("/")


def identity_url_host(raw: Any, contract: Mapping[str, Any] | None = None) -> str:
    url = canonical_identity_url(raw, contract)
    if not url:
        return ""
    host = urlparse(url).netloc.lower()
    return host[4:] if host.startswith("www.") else host


def _value(item: Mapping[str, Any], aliases: Iterable[str]) -> str:
    for alias in aliases:
        value = _text(item.get(alias))
        if value:
            return value
    return ""


def _event_fields(item: Mapping[str, Any]) -> dict[str, str]:
    return {
        "id": _value(item, ("id_suggestion", "id", "event_id")),
        "title": _value(item, ("title", "eventName")),
        "date": _value(item, ("date", "start_date")),
        "end_date": _value(item, ("endDate", "end_date", "enddate")),
        "city": _value(item, ("city", "stadt")),
        "location": _value(item, ("location", "venue", "ort")),
        "url": _value(item, ("source_url", "url", "event_url", "official_source_url")),
    }


def _parse_day(raw: str) -> date | None:
    if not raw:
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except ValueError:
        return None


def _date_ranges_overlap(a: Mapping[str, str], b: Mapping[str, str]) -> bool:
    a_start = _parse_day(a.get("date", ""))
    b_start = _parse_day(b.get("date", ""))
    if a_start is None or b_start is None:
        return False
    a_end = _parse_day(a.get("end_date", "")) or a_start
    b_end = _parse_day(b.get("end_date", "")) or b_start
    return a_start <= b_end and b_start <= a_end


def _tokens(value: str, contract: Mapping[str, Any], *, title: bool = False) -> set[str]:
    stopwords = set(contract.get("stopwords") or []) if title else set()
    aliases = dict(contract.get("token_aliases") or {})
    result: set[str] = set()
    for token in normalize_identity_text(value).split():
        token = aliases.get(token, token)
        if not token or token in stopwords:
            continue
        if title and re.fullmatch(r"20\d{2}", token):
            continue
        result.add(token)
    return result


def _token_similarity(a: str, b: str, contract: Mapping[str, Any], *, title: bool = False) -> tuple[float, int]:
    a_norm = normalize_identity_text(a)
    b_norm = normalize_identity_text(b)
    if a_norm and a_norm == b_norm:
        return 1.0, max(1, len(_tokens(a, contract, title=title)))
    a_tokens = _tokens(a, contract, title=title)
    b_tokens = _tokens(b, contract, title=title)
    if not a_tokens or not b_tokens:
        return 0.0, 0
    shared = len(a_tokens & b_tokens)
    if shared == 0:
        return 0.0, 0
    dice = (2.0 * shared) / (len(a_tokens) + len(b_tokens))
    containment = shared / min(len(a_tokens), len(b_tokens))
    score = max(dice, containment)
    if title and shared < int(contract.get("min_shared_title_tokens", 2)):
        score = min(score, float(contract.get("single_token_title_cap", 0.58)))
    return score, shared


def _score(candidate: Mapping[str, str], existing: Mapping[str, str], contract: Mapping[str, Any]) -> tuple[float, dict[str, Any]]:
    title_score, shared_title_tokens = _token_similarity(candidate["title"], existing["title"], contract, title=True)
    location_score, _ = _token_similarity(candidate["location"], existing["location"], contract)
    city_score = 1.0 if normalize_identity_text(candidate["city"]) and normalize_identity_text(candidate["city"]) == normalize_identity_text(existing["city"]) else 0.0
    candidate_host = identity_url_host(candidate["url"], contract)
    existing_host = identity_url_host(existing["url"], contract)
    source_host_score = 1.0 if candidate_host and candidate_host == existing_host else 0.0
    weights = contract["weights"]
    total = (
        float(weights["title"]) * title_score
        + float(weights["location"]) * location_score
        + float(weights["city"]) * city_score
        + float(weights["source_host"]) * source_host_score
    )
    return total, {
        "title_score": title_score,
        "shared_title_tokens": shared_title_tokens,
        "location_score": location_score,
        "city_score": city_score,
        "source_host_score": source_host_score,
    }


def _result(status: str = "none", **kwargs: Any) -> dict[str, Any]:
    return {
        "status": status,
        "matched_event_id": "",
        "matched_event_title": "",
        "matched_event_date": "",
        "matched_event_location": "",
        "matched_event_url": "",
        "score": 0.0,
        "match_type": "",
        "confidence": "",
        "reason": "",
        **kwargs,
    }


def compare_event_identity(candidate_item: Mapping[str, Any], existing_item: Mapping[str, Any], contract: Mapping[str, Any] | None = None) -> dict[str, Any]:
    cfg = dict(contract or load_event_identity_contract())
    candidate = _event_fields(candidate_item)
    existing = _event_fields(existing_item)
    same_id = bool(candidate["id"] and existing["id"] and normalize_identity_text(candidate["id"]) == normalize_identity_text(existing["id"]))
    date_overlap = _date_ranges_overlap(candidate, existing)
    if not same_id and not date_overlap:
        return _result()

    score, details = _score(candidate, existing, cfg)
    candidate_url = canonical_identity_url(candidate["url"], cfg)
    existing_url = canonical_identity_url(existing["url"], cfg)
    same_url = bool(candidate_url and candidate_url == existing_url)
    same_title = bool(normalize_identity_text(candidate["title"]) and normalize_identity_text(candidate["title"]) == normalize_identity_text(existing["title"]))
    same_location = bool(normalize_identity_text(candidate["location"]) and normalize_identity_text(candidate["location"]) == normalize_identity_text(existing["location"]))

    status = "none"
    match_type = ""
    reason = ""
    confidence = ""
    if same_id:
        if date_overlap and (same_url or (details["title_score"] >= float(cfg["review_threshold"]) and score >= float(cfg["review_threshold"]))):
            status = "same_identity"
            match_type = "same_event_id"
            reason = "Die stabile Event-ID ist bereits vorhanden und die fachlichen Merkmale passen zum selben Event."
            confidence = "high"
        else:
            status = "identity_conflict"
            match_type = "event_id_conflict"
            reason = "Die stabile Event-ID ist bereits belegt, aber Titel, Termin oder Quelle passen nicht sicher zusammen."
            confidence = "high"
    elif same_title and (same_location or details["city_score"] == 1.0):
        status = "exact"
        match_type = "same_title_and_date"
        reason = "Titel und Termin stimmen mit einem vorhandenen Event überein."
        confidence = "high"
        score = max(score, 0.99)
    elif same_url and details["shared_title_tokens"] >= 1:
        status = "possible"
        match_type = "same_source_url_and_date"
        reason = "Dieselbe kanonische Quelle und derselbe Termin sind bereits vorhanden; die abweichende Bezeichnung muss fachlich geprüft werden."
        confidence = "high"
        score = max(score, float(cfg["review_threshold"]))
    elif score >= float(cfg["review_threshold"]) and details["shared_title_tokens"] >= int(cfg.get("min_shared_title_tokens", 2)):
        status = "possible"
        match_type = "semantic_title_date_context"
        reason = "Termin und prägende Titelbegriffe stimmen stark überein; Ort, Stadt oder Quellkontext stützen den Verdacht."
        confidence = "high" if score >= 0.86 else "medium"

    if status == "none":
        return _result()
    return _result(
        status,
        matched_event_id=existing["id"],
        matched_event_title=existing["title"],
        matched_event_date=existing["date"],
        matched_event_location=existing["location"],
        matched_event_url=existing_url,
        score=round(min(1.0, max(0.0, score)), 3),
        match_type=match_type,
        confidence=confidence,
        reason=reason,
        details=details,
    )


def event_rows_from_sheet_values(values: Iterable[Iterable[Any]]) -> list[dict[str, str]]:
    rows = [list(row) for row in values if isinstance(row, (list, tuple))]
    header_index = -1
    header: list[str] = []
    for offset, raw_header in enumerate(rows):
        normalized = [normalize_identity_text(value).replace(" ", "_") for value in raw_header]
        if "title" not in normalized or "date" not in normalized:
            continue
        if not ({"id", "event_id"} & set(normalized)):
            continue
        header_index = offset
        header = normalized
        break
    if header_index < 0:
        raise ValueError("event sheet header not found")
    result: list[dict[str, str]] = []
    for raw in rows[header_index + 1 :]:
        if not any(_text(value) for value in raw):
            continue
        item = {name: _text(raw[pos]) if pos < len(raw) else "" for pos, name in enumerate(header) if name}
        if not item.get("id") and not item.get("event_id"):
            raise ValueError("event row without stable id")
        if not item.get("title") or not item.get("date"):
            raise ValueError("event row without title or date")
        result.append(item)
    return result


def find_best_event_match(candidate: Mapping[str, Any], existing_events: Iterable[Mapping[str, Any]], contract: Mapping[str, Any] | None = None) -> dict[str, Any]:
    cfg = dict(contract or load_event_identity_contract())
    rank = {"none": 0, "possible": 1, "same_identity": 2, "exact": 3, "identity_conflict": 4}
    best = _result()
    for existing in existing_events:
        if not isinstance(existing, Mapping):
            continue
        result = compare_event_identity(candidate, existing, cfg)
        current_key = (rank.get(result["status"], 0), float(result.get("score", 0.0)), result.get("matched_event_id", ""))
        best_key = (rank.get(best["status"], 0), float(best.get("score", 0.0)), best.get("matched_event_id", ""))
        if current_key > best_key:
            best = result
    return best


def apply_event_identity_match(candidate: Mapping[str, Any], match: Mapping[str, Any], *, allow_same_identity: bool = False) -> dict[str, Any]:
    enriched = dict(candidate)
    status = _text(match.get("status"))
    if status == "none" or (status == "same_identity" and allow_same_identity):
        for field in (
            "matched_event_id",
            "match_score",
            "duplicate_score",
            "duplicate_confidence",
            "duplicate_reason",
            "duplicate_match_type",
            "matched_event_title",
            "matched_event_date",
            "matched_event_location",
            "matched_event_url",
        ):
            enriched[field] = ""
        if normalize_identity_text(enriched.get("duplicate_status", "")) == "review":
            enriched["duplicate_status"] = ""
        enriched["hard_duplicate"] = False
        enriched["event_identity_status"] = status or "none"
        return enriched

    matched_id = _text(match.get("matched_event_id"))
    old_status = normalize_identity_text(enriched.get("duplicate_status", ""))
    old_match = _text(enriched.get("matched_event_id", ""))
    human_distinct = old_status == "distinct" and old_match and old_match == matched_id

    enriched.update(
        {
            "matched_event_id": matched_id,
            "match_score": f"{float(match.get('score', 0.0)):.3f}",
            "duplicate_score": f"{float(match.get('score', 0.0)):.3f}",
            "duplicate_confidence": _text(match.get("confidence")),
            "duplicate_reason": _text(match.get("reason")),
            "duplicate_match_type": _text(match.get("match_type")),
            "matched_event_title": _text(match.get("matched_event_title")),
            "matched_event_date": _text(match.get("matched_event_date")),
            "matched_event_location": _text(match.get("matched_event_location")),
            "matched_event_url": _text(match.get("matched_event_url")),
            "event_identity_status": status,
            "hard_duplicate": not human_distinct,
        }
    )
    if not human_distinct:
        enriched["duplicate_status"] = "review"
    return enriched
