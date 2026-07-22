#!/usr/bin/env python3
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from event_public_contract import build_offer_schema, normalize_public_event, schema_eligible

source = normalize_public_event({"url": "https://example.test/info"})
assert source["source_url"] == "https://example.test/info" and not source["ticket_url"]
assert build_offer_schema(source) == []
assert build_offer_schema(normalize_public_event({"admission_status": "free"})) == [{"@type": "Offer", "price": 0, "priceCurrency": "EUR"}]
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "price": "12.50", "price_currency": "EUR", "ticket_url": "https://tickets.test/buy"}))[0]["url"].endswith("/buy")
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "ticket_url": "https://tickets.test/buy"})) == []
assert "availability" not in build_offer_schema(normalize_public_event({"admission_status": "free"}))[0]
multi = normalize_public_event({"admission_status": "paid", "ticket_offers": [{"price": "10", "price_currency": "EUR", "ticket_url": "https://tickets.test/a"}, {"price": "20", "price_currency": "EUR", "ticket_url": "https://tickets.test/b"}]})
assert len(build_offer_schema(multi)) == 2
for invalid in ("-1", "NaN", "Infinity", "-Infinity", "not-a-price"):
    assert build_offer_schema(normalize_public_event({"admission_status": "paid", "price": invalid, "price_currency": "EUR", "ticket_url": "https://tickets.test/buy"})) == []
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "price": "10", "price_currency": "eur", "ticket_url": "https://tickets.test/buy"})) == []
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "price": "10", "price_currency": "EUR", "ticket_url": "javascript:alert(1)"})) == []
invalid_availability = build_offer_schema(normalize_public_event({"admission_status": "paid", "price": "10", "price_currency": "EUR", "ticket_url": "https://tickets.test/buy", "availability": "BackOrder"}))[0]
assert "availability" not in invalid_availability
assert "validFrom" not in build_offer_schema(normalize_public_event({"admission_status": "free", "valid_from": "tomorrow"}))[0]
assert not schema_eligible(normalize_public_event({"id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04"}))
print("event offer contract: OK")
