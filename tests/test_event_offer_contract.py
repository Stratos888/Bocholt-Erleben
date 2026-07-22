#!/usr/bin/env python3
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from event_public_contract import build_offer_schema, normalize_public_event

source = normalize_public_event({"url": "https://example.test/info"})
assert source["source_url"] == "https://example.test/info" and not source["ticket_url"]
assert build_offer_schema(source) == []
assert build_offer_schema(normalize_public_event({"admission_status": "free"})) == [{"@type": "Offer", "price": 0, "priceCurrency": "EUR"}]
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "price": "12.50", "price_currency": "EUR", "ticket_url": "https://tickets.test/buy"}))[0]["url"].endswith("/buy")
assert build_offer_schema(normalize_public_event({"admission_status": "paid", "ticket_url": "https://tickets.test/buy"})) == []
assert "availability" not in build_offer_schema(normalize_public_event({"admission_status": "free"}))[0]
multi = normalize_public_event({"admission_status": "paid", "ticket_offers": [{"price": "10", "price_currency": "EUR", "ticket_url": "https://tickets.test/a"}, {"price": "20", "price_currency": "EUR", "ticket_url": "https://tickets.test/b"}]})
assert len(build_offer_schema(multi)) == 2
print("event offer contract: OK")
