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
known_id_only = normalize_public_event({"id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04"})
assert known_id_only["organizer_name"] == "" and known_id_only["performer_name"] == "" and known_id_only["price"] == ""
kinder = normalize_public_event({
    "id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04",
    "url": "https://yuki-magazin.de/veranstaltungen/du-wunderst-mich-die-kinderzaubershow-mit-endrik-thier/",
    "admission_status": "paid", "price": "4", "price_currency": "EUR",
    "organizer_name": "Förderverein der Kita St. Josef", "organizer_type": "Organization",
    "performer_name": "Endrik Thier", "performer_type": "Person",
})
assert (kinder["performer_name"], kinder["performer_type"]) == ("Endrik Thier", "Person")
assert (kinder["organizer_name"], kinder["organizer_type"]) == ("Förderverein der Kita St. Josef", "Organization")
assert (kinder["price"], kinder["price_currency"]) == ("4", "EUR")
assert kinder["ticket_url"] == "" and build_offer_schema(kinder) == []
messe = normalize_public_event({
    "id": "2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27",
    "url": "https://www.bocholt.de/veranstaltungskalender/2-bocholter-vereinsmesse-in-den-shopping-arkaden",
    "organizer_name": "Stadt Bocholt – Freiwilligen-Agentur", "organizer_type": "Organization",
})
assert (messe["organizer_name"], messe["organizer_type"]) == ("Stadt Bocholt – Freiwilligen-Agentur", "Organization")
assert messe["performer_name"] == "" and messe["price"] == "" and build_offer_schema(messe) == []
print("event offer contract: OK")
