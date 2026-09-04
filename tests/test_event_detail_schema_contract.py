#!/usr/bin/env python3
import importlib.util
import json
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

build_detail_event = module.build_detail_event
json_ld = module.json_ld
render_page = module.render_page


def event(extra=None, *, is_past=False, noindex=False, visual_index=None):
    raw = {
        "id": "fixture",
        "title": "Fixture",
        "date": "2026-09-04",
        "city": "Bocholt",
        "location": "Bocholt",
        **(extra or {}),
    }
    built = build_detail_event(raw, is_past, noindex, visual_index or {})
    assert built
    return built


def embedded_schema(html):
    match = re.search(
        r'<script type="application/ld\+json">\s*(\{.*?\})\s*</script>',
        html,
        flags=re.S,
    )
    return json.loads(match.group(1)) if match else None


# Core Event markup must not depend on ticket/Offer data.
plain = event()
plain_html = render_page(plain)
plain_schema = embedded_schema(plain_html)
assert plain_schema is not None
assert plain_schema["@type"] == "Event"
assert plain_schema["name"] == "Fixture"
assert "offers" not in plain_schema
assert '<meta name="robots" content="noindex' not in plain_html

# City-only / area fallback: PostalAddress without invented street or venue name.
plain_location = plain_schema["location"]
assert plain_location["@type"] == "Place"
assert "name" not in plain_location
assert plain_location["address"] == {
    "@type": "PostalAddress",
    "addressLocality": "Bocholt",
    "addressCountry": "DE",
}

# Exact source-backed address: split venue and street conservatively.
addressed = event({"location": "Bahia, Hemdener Weg 169", "time": "19:00–23:00"})
addressed_schema = json.loads(json_ld(addressed))
assert addressed_schema["location"]["name"] == "Bahia"
assert addressed_schema["location"]["address"] == {
    "@type": "PostalAddress",
    "streetAddress": "Hemdener Weg 169",
    "addressLocality": "Bocholt",
    "addressCountry": "DE",
}
assert addressed_schema["startDate"] == "2026-09-04T19:00:00+02:00"
assert addressed_schema["endDate"] == "2026-09-04T23:00:00+02:00"

# Correct CET/CEST offsets from the local event date.
winter = event({"date": "2026-12-13", "time": "17:00", "location": "Pfarrkirche St. Georg"})
winter_schema = json.loads(json_ld(winter))
assert winter_schema["startDate"] == "2026-12-13T17:00:00+01:00"
assert "endDate" not in winter_schema

# Known Dutch border places use their local IANA timezone and country.
dutch = event({"date": "2026-07-12", "time": "10:00", "city": "Bredevoort", "location": "Koppelkerk Buitenterrein"})
dutch_schema = json.loads(json_ld(dutch))
assert dutch_schema["startDate"] == "2026-07-12T10:00:00+02:00"
assert dutch_schema["location"]["address"]["addressCountry"] == "NL"

# Cross-midnight ranges advance the end date when there is no explicit endDate.
overnight = event({"date": "2026-09-04", "time": "22:00–01:00"})
overnight_schema = json.loads(json_ld(overnight))
assert overnight_schema["startDate"] == "2026-09-04T22:00:00+02:00"
assert overnight_schema["endDate"] == "2026-09-05T01:00:00+02:00"

# Unknown hour remains date-only; no midnight is invented.
date_only = event({"date": "2026-10-16", "time": ""})
date_only_schema = json.loads(json_ld(date_only))
assert date_only_schema["startDate"] == "2026-10-16"
assert date_only_schema["endDate"] == "2026-10-16"

# Multi-day event preserves the explicit end day without inventing a time.
multi_day = event({"date": "2026-10-16", "endDate": "2026-10-19", "time": ""})
multi_day_schema = json.loads(json_ld(multi_day))
assert multi_day_schema["startDate"] == "2026-10-16"
assert multi_day_schema["endDate"] == "2026-10-19"

# A known start time without a known end time must not fabricate endDate.
start_only = event({"date": "2026-10-03", "time": "17:00"})
start_only_schema = json.loads(json_ld(start_only))
assert start_only_schema["startDate"] == "2026-10-03T17:00:00+02:00"
assert "endDate" not in start_only_schema

# Offer contract remains truth-preserving and optional.
free = event({"admission_status": "free"})
assert json.loads(json_ld(free))["offers"] == {
    "@type": "Offer",
    "price": 0,
    "priceCurrency": "EUR",
}

paid_unverified = event({"admission_status": "paid", "price": "12.5", "price_currency": "EUR"})
paid_unverified_schema = json.loads(json_ld(paid_unverified))
assert "offers" not in paid_unverified_schema
assert "12.5 EUR; keine verifizierten Ticketdaten vorhanden" in render_page(paid_unverified)

paid = event({
    "admission_status": "paid",
    "price": "12.5",
    "price_currency": "EUR",
    "ticket_url": "https://tickets.example/a",
    "availability": "InStock",
    "valid_from": "2026-08-01T10:00:00+02:00",
})
paid_schema = json.loads(json_ld(paid))["offers"]
paid_html = render_page(paid)
for value in ("12.5", "EUR", "https://tickets.example/a", "InStock", "2026-08-01T10:00:00+02:00"):
    assert value in paid_html
assert paid_schema["availability"].endswith("/InStock")

multi_offer = event({"admission_status": "paid", "ticket_offers": [
    {"price": "10", "price_currency": "EUR", "ticket_url": "https://tickets.example/a", "availability": "SoldOut"},
    {"price": "20", "price_currency": "EUR", "ticket_url": "https://tickets.example/b", "valid_from": "2026-08-02"},
]})
multi_offer_schema = json.loads(json_ld(multi_offer))["offers"]
multi_offer_html = render_page(multi_offer)
assert len(multi_offer_schema) == 2
for offer in multi_offer_schema:
    for key in ("price", "priceCurrency", "url"):
        assert str(offer[key]) in multi_offer_html

# Existing curated examples now get core Event markup but never fake offers.
kinder = event({
    "id": "du-wunderst-mich-kinderzaubershow-endrik-thier-2026-09-04",
    "url": "https://yuki-magazin.de/veranstaltungen/du-wunderst-mich-die-kinderzaubershow-mit-endrik-thier/",
})
kinder_html = render_page(kinder)
kinder_schema = embedded_schema(kinder_html)
assert kinder_schema is not None
assert "offers" not in kinder_schema
assert "4 EUR" in kinder_html and "keine verifizierten Ticketdaten" in kinder_html
assert "Tickets</a>" not in kinder_html

messe = event({
    "id": "2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27",
    "url": "https://www.bocholt.de/veranstaltungskalender/2-bocholter-vereinsmesse-in-den-shopping-arkaden",
})
messe_html = render_page(messe)
messe_schema = embedded_schema(messe_html)
assert messe_schema is not None
assert "offers" not in messe_schema
assert "Tickets</a>" not in messe_html and "Eintritt:" not in messe_html

# Untyped organizer/performer claims stay out of structured data.
untyped = event({"admission_status": "free", "organizer_name": "Unbelegt", "performer_name": "Unbelegt"})
untyped_schema = json.loads(json_ld(untyped))
assert "organizer" not in untyped_schema and "performer" not in untyped_schema

# Generic city fallback image remains visible metadata/UI but is not claimed as Event image.
assert plain.image_src.endswith("/default-city-02-16x9.webp")
assert "image" not in plain_schema

# A concrete assigned visual may be included in Event schema.
visual = event(
    {"visual_key": "live_music", "visual_motif": "concert"},
    visual_index={
        "live_music": [{
            "src": "/assets/event-visuals/concert-01.webp",
            "alt": "Konzertmotiv",
            "visual_motif": "concert",
            "visual_motif_role": "primary",
        }]
    },
)
visual_schema = json.loads(json_ld(visual))
assert visual_schema["image"] == ["https://bocholt-erleben.de/assets/event-visuals/concert-01.webp"]

# noindex past pages must not carry rich-result markup.
past = event({"date": "2026-09-03"}, is_past=True, noindex=True)
past_html = render_page(past)
assert '<meta name="robots" content="noindex,follow">' in past_html
assert '<script type="application/ld+json">' not in past_html

print("event detail schema contract: OK")
