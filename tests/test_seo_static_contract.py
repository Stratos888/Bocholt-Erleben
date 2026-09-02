#!/usr/bin/env python3
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class Page(HTMLParser):
    def __init__(self):
        super().__init__()
        self.h1 = 0
        self.links = []
        self.title = 0
        self.canonical = 0
        self.noindex = False
        self.descriptions = {}

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == "h1":
            self.h1 += 1
        if tag == "a" and attrs.get("href"):
            self.links.append(attrs["href"])
        if tag == "title":
            self.title += 1
        if tag == "link" and attrs.get("rel") == "canonical":
            self.canonical += 1
        if tag == "meta" and attrs.get("name") == "robots" and "noindex" in attrs.get("content", ""):
            self.noindex = True
        if tag == "meta":
            key = attrs.get("name") or attrs.get("property")
            if key in {"description", "og:description", "twitter:description"}:
                self.descriptions[key] = attrs.get("content", "")


def parse(filename):
    text = (ROOT / filename).read_text()
    page = Page()
    page.feed(text)
    return text, page


core_files = ("index.html", "events/index.html", "aktivitaeten/index.html")
for filename in core_files:
    text, page = parse(filename)
    assert (page.h1, page.title, page.canonical, page.noindex) == (1, 1, 1, False), filename
    assert "STATIC:" in text and "static-content-card" in text, filename

weekend_path = ROOT / "events/wochenende/index.html"
weekend, weekend_page = parse("events/wochenende/index.html")
assert weekend_path.exists()
assert (weekend_page.h1, weekend_page.title, weekend_page.canonical, weekend_page.noindex) == (1, 1, 1, False)
assert "STATIC:WEEKEND:START" in weekend and "STATIC:WEEKEND:END" in weekend
assert "Veranstaltungen am Wochenende in Bocholt" in weekend
assert 'href="https://bocholt-erleben.de/events/wochenende/"' in weekend

# Weekend route reuses the existing Event-Hub interaction owners instead of
# maintaining a second simple content-card experience.
for required in (
    'class="desktop-hero"',
    'id="search-filter"',
    'id="filter-time-pill"',
    'id="filter-category-pill"',
    'id="event-cards"',
    'id="event-detail-panel"',
    'src="/js/events.js',
    'src="/js/filter.js',
    'src="/js/main.js',
):
    assert required in weekend, f"weekend Event-UX contract missing {required}"
assert "content-hero content-hero--panel" not in weekend
assert 'data-time="weekend"' in weekend
assert "weekendRouteInit" in weekend
assert "weekendButton.click()" in weekend

home, home_page = parse("index.html")
events, events_page = parse("events/index.html")
activities, _ = parse("aktivitaeten/index.html")
assert '/events/' in home and '/aktivitaeten/' in home and '/aktivitaeten/' in events and '/events/' in activities
assert '"@type": "Event"' not in events
assert events.count('data-date-month-label>Monat</div>') == 2
assert 'März 2026' not in events

# SEO intent ownership: / owns Today, /events/ owns the general calendar,
# /events/wochenende/ owns Weekend. Interactive filters on /events/ may still
# offer Today/Weekend; only search-preview metadata must stay unambiguous.
assert "heute" in home_page.descriptions["description"].casefold()
assert "wochenende" in weekend_page.descriptions["description"].casefold()
for key in ("description", "og:description", "twitter:description"):
    value = events_page.descriptions[key].casefold()
    assert "heute" not in value, f"/events/ {key} must not claim the Today intent"
    assert "wochenende" not in value, f"/events/ {key} must not claim the Weekend intent"

sitemap = (ROOT / "deploy-templates/sitemap.live.xml").read_text()
assert sitemap.count("https://bocholt-erleben.de/events/wochenende/") == 1
assert "/events/heute/" not in sitemap

print("SEO static/no-JS contract: OK")
