"""Truth-preserving public event and Offer normalization."""
from __future__ import annotations

import json
from typing import Any, Dict, List

PUBLIC_FIELDS = (
    "source_url", "admission_status", "price", "price_currency", "ticket_url",
    "availability", "valid_from", "organizer_name", "organizer_url",
    "performer_name", "performer_url", "offer_verified_at", "offer_source_url",
)

def _text(value: Any) -> str:
    return "" if value is None else str(value).strip()

def normalize_public_event(raw: Dict[str, Any]) -> Dict[str, Any]:
    out = {key: _text(raw.get(key)) for key in PUBLIC_FIELDS}
    out["source_url"] = out["source_url"] or _text(raw.get("url"))
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
        ticket_url = _text(candidate.get("ticket_url"))
        price = _text(candidate.get("price"))
        currency = _text(candidate.get("price_currency"))
        if status == "free":
            offer: Dict[str, Any] = {"@type": "Offer", "price": 0, "priceCurrency": "EUR"}
        elif status == "paid" and price and currency and ticket_url:
            try: numeric_price: Any = float(price.replace(",", "."))
            except ValueError: continue
            offer = {"@type": "Offer", "price": numeric_price, "priceCurrency": currency, "url": ticket_url}
        else:
            continue
        availability = _text(candidate.get("availability"))
        if availability:
            offer["availability"] = availability if availability.startswith("http") else f"https://schema.org/{availability}"
        valid_from = _text(candidate.get("valid_from"))
        if valid_from: offer["validFrom"] = valid_from
        result.append(offer)
    return result
