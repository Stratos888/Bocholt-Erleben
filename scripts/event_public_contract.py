"""Truth-preserving public event and Offer normalization."""
from __future__ import annotations

import json
import ipaddress
import math
import re
from datetime import datetime
from urllib.parse import urlparse
from typing import Any, Dict, List

PUBLIC_FIELDS = (
    "source_url", "admission_status", "price", "price_currency", "ticket_url",
    "availability", "valid_from", "organizer_name", "organizer_url",
    "performer_name", "performer_url", "organizer_type", "performer_type",
    "offer_verified_at", "offer_source_url",
)

# Source-backed corrections for the two editorial Sheet rows whose public
# contract fields were empty at the 2026-07-22 Search Console review.  Keep
# these keyed by the stable event id: generated events.tsv/events.json files
# are deploy artifacts and must not become a second source of truth.
CURATED_PUBLIC_FIELDS_BY_EVENT_ID = {
    "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04": {
        "admission_status": "paid",
        "price": "4",
        "price_currency": "EUR",
        "organizer_name": "Förderverein der Kita St. Josef",
        "organizer_type": "Organization",
        "performer_name": "Endrik Thier",
        "performer_type": "Person",
    },
    "2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27": {
        "organizer_name": "Stadt Bocholt – Freiwilligen-Agentur",
        "organizer_type": "Organization",
    },
}

def _text(value: Any) -> str:
    return "" if value is None else str(value).strip()

def public_http_url(value: Any) -> str:
    raw = _text(value)
    try: parsed = urlparse(raw)
    except ValueError: return ""
    if parsed.scheme not in {"http", "https"} or not parsed.hostname or parsed.username or parsed.password:
        return ""
    host = parsed.hostname.lower().rstrip(".")
    if host == "localhost" or host.endswith(".localhost"): return ""
    try:
        if not ipaddress.ip_address(host).is_global: return ""
    except ValueError:
        if "." not in host: return ""
    return raw

def iso_datetime(value: Any) -> str:
    raw = _text(value)
    if not raw: return ""
    try: datetime.fromisoformat(raw.replace("Z", "+00:00"))
    except ValueError: return ""
    return raw

def numeric_price(value: Any) -> float | int | None:
    try: number = float(_text(value).replace(",", "."))
    except ValueError: return None
    if not math.isfinite(number) or number < 0: return None
    return int(number) if number.is_integer() else number

def normalize_public_event(raw: Dict[str, Any]) -> Dict[str, Any]:
    curated = CURATED_PUBLIC_FIELDS_BY_EVENT_ID.get(_text(raw.get("id")), {})
    out = {key: _text(raw.get(key)) for key in PUBLIC_FIELDS}
    for key, value in curated.items():
        if not out[key]:
            out[key] = value
    out["source_url"] = out["source_url"] or _text(raw.get("url"))
    out["ticket_url"] = public_http_url(out["ticket_url"])
    if out["admission_status"] not in {"free", "paid", "unknown"}:
        out["admission_status"] = "unknown"
    # An informational source URL is never promoted to a ticket URL.
    if out["ticket_url"] and out["ticket_url"] == out["source_url"] and not _text(raw.get("ticket_url")):
        out["ticket_url"] = ""
    offers = raw.get("ticket_offers", [])
    if isinstance(offers, str) and offers.strip():
        try: offers = json.loads(offers)
        except json.JSONDecodeError: offers = []
    out["ticket_offers"] = offers if isinstance(offers, list) else []
    return out

def build_offer_schema(public: Dict[str, Any]) -> List[Dict[str, Any]]:
    status = _text(public.get("admission_status"))
    candidates = public.get("ticket_offers") or [public]
    result: List[Dict[str, Any]] = []
    for candidate in candidates:
        if not isinstance(candidate, dict): continue
        ticket_url = public_http_url(candidate.get("ticket_url"))
        price = numeric_price(candidate.get("price"))
        currency = _text(candidate.get("price_currency"))
        if status == "free":
            offer: Dict[str, Any] = {"@type": "Offer", "price": 0, "priceCurrency": "EUR"}
        elif status == "paid" and price is not None and re.fullmatch(r"[A-Z]{3}", currency) and ticket_url:
            offer = {"@type": "Offer", "price": price, "priceCurrency": currency, "url": ticket_url}
        else:
            continue
        availability = _text(candidate.get("availability"))
        if availability in {"InStock", "SoldOut", "PreOrder"}:
            offer["availability"] = f"https://schema.org/{availability}"
        valid_from = iso_datetime(candidate.get("valid_from"))
        if valid_from: offer["validFrom"] = valid_from
        result.append(offer)
    return result

def schema_eligible(public: Dict[str, Any]) -> bool:
    return bool(build_offer_schema(public))
