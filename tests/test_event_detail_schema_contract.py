#!/usr/bin/env python3
import json
import importlib.util
import re
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
module_path = Path(__file__).resolve().parents[1] / "scripts" / "build-event-detail-pages.py"
spec = importlib.util.spec_from_file_location("build_event_detail_pages", module_path)
module = importlib.util.module_from_spec(spec)
assert spec and spec.loader
sys.modules[spec.name] = module
spec.loader.exec_module(module)
build_detail_event, json_ld, render_page = module.build_detail_event, module.json_ld, module.render_page

def event(extra):
    raw = {"id": "fixture", "title": "Fixture", "date": "2026-09-04", "location": "Bocholt", **extra}
    built = build_detail_event(raw, False, False, {})
    assert built
    return built

unknown = event({"id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04"})
html = render_page(unknown)
assert '<script type="application/ld+json">' not in html
assert "4 EUR" not in html
kinder = event({
    "id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04",
    "url": "https://yuki-magazin.de/veranstaltungen/du-wunderst-mich-die-kinderzaubershow-mit-endrik-thier/",
    "admission_status": "paid", "price": "4", "price_currency": "EUR",
    "organizer_name": "Förderverein der Kita St. Josef", "organizer_type": "Organization",
    "performer_name": "Endrik Thier", "performer_type": "Person",
})
kinder_html = render_page(kinder)
assert "4 EUR" in kinder_html and "keine verifizierten Ticketdaten" in kinder_html
assert '<script type="application/ld+json">' not in kinder_html
assert "offers" not in kinder_html and "Tickets</a>" not in kinder_html
messe = event({
    "id": "2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27",
    "url": "https://www.bocholt.de/veranstaltungskalender/2-bocholter-vereinsmesse-in-den-shopping-arkaden",
    "organizer_name": "Stadt Bocholt – Freiwilligen-Agentur", "organizer_type": "Organization",
})
messe_html = render_page(messe)
assert '<script type="application/ld+json">' not in messe_html
assert "Tickets</a>" not in messe_html and "Eintritt:" not in messe_html
free = event({"admission_status": "free"})
assert json.loads(json_ld(free))["offers"] == {"@type": "Offer", "price": 0, "priceCurrency": "EUR"}
paid = event({"admission_status": "paid", "price": "12.5", "price_currency": "EUR", "ticket_url": "https://tickets.example/a", "availability": "InStock", "valid_from": "2026-08-01T10:00:00+02:00"})
paid_schema = json.loads(json_ld(paid))["offers"]
paid_html = render_page(paid)
for value in ("12.5", "EUR", "https://tickets.example/a", "InStock", "2026-08-01T10:00:00+02:00"):
    assert value in paid_html
assert paid_schema["availability"].endswith("/InStock")
multi = event({"admission_status": "paid", "ticket_offers": [
    {"price": "10", "price_currency": "EUR", "ticket_url": "https://tickets.example/a", "availability": "SoldOut"},
    {"price": "20", "price_currency": "EUR", "ticket_url": "https://tickets.example/b", "valid_from": "2026-08-02"},
]})
multi_schema = json.loads(json_ld(multi))["offers"]
multi_html = render_page(multi)
assert len(multi_schema) == 2
for offer in multi_schema:
    for key in ("price", "priceCurrency", "url"):
        assert str(offer[key]) in multi_html
untyped = event({"admission_status": "free", "organizer_name": "Unbelegt", "performer_name": "Unbelegt"})
untyped_schema = json.loads(json_ld(untyped))
assert "organizer" not in untyped_schema and "performer" not in untyped_schema
print("event detail schema contract: OK")
