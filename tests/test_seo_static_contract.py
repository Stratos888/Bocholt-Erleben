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


def parse(filename):
    text = (ROOT / filename).read_text()
    page = Page()
    page.feed(text)
    return text, page


for filename in ("index.html", "events/index.html", "aktivitaeten/index.html"):
    text, page = parse(filename)
    assert (page.h1, page.title, page.canonical, page.noindex) == (1, 1, 1, False), filename
    assert "STATIC:" in text and "static-content-card" in text, filename

home = (ROOT / "index.html").read_text()
events = (ROOT / "events/index.html").read_text()
activities = (ROOT / "aktivitaeten/index.html").read_text()
assert '/events/' in home and '/aktivitaeten/' in home and '/aktivitaeten/' in events and '/events/' in activities
assert '"@type": "Event"' not in events
assert events.count('data-date-month-label>Monat</div>') == 2
assert 'März 2026' not in events

# Weekend is an indexable route context of the existing Event Hub, not a
# second independently maintained event implementation.
weekend_path = ROOT / "events/wochenende/index.html"
weekend, weekend_page = parse("events/wochenende/index.html")
assert weekend_path.exists()
assert (weekend_page.h1, weekend_page.title, weekend_page.canonical, weekend_page.noindex) == (1, 1, 1, False)
assert "STATIC:WEEKEND:START" in weekend and "STATIC:WEEKEND:END" in weekend
assert "Veranstaltungen am Wochenende in Bocholt" in weekend
assert 'href="https://bocholt-erleben.de/events/wochenende/"' in weekend
for required in (
    'class="desktop-hero"',
    'id="search-filter"',
    'id="filter-category-pill"',
    'id="event-cards"',
    'id="event-detail-panel"',
    'src="/js/events.js',
    'src="/js/filter.js',
    'src="/js/main.js',
):
    assert required in weekend, f"weekend Event-UX contract missing {required}"
assert "content-hero content-hero--panel" not in weekend
assert 'data-event-time-default="weekend"' in weekend
assert 'data-event-time-locked="true"' in weekend
assert "weekend-route-bridge" not in weekend
assert "Alle Veranstaltungen" not in weekend

# No user-facing time selector remains on the Weekend landing page. The two
# hidden nodes are only the minimum compatibility host required by FilterModule.
assert '<button type="button" id="filter-time-pill" hidden aria-hidden="true" tabindex="-1">' in weekend
assert '<div id="sheet-time" hidden aria-hidden="true">' in weekend
assert 'id="popover-time"' not in weekend
assert weekend.count('data-time="') == 1
assert 'data-time="weekend" hidden' in weekend
assert "Datum auswählen" not in weekend
assert "data-date-module" not in weekend
assert "weekendRouteInit" not in weekend
assert "weekendButton.click()" not in weekend

filter_js = (ROOT / "js/filter.js").read_text()
assert "getRouteDefaultTimeKey" in filter_js
assert "getDefaultTimeKey" in filter_js
assert 'this.filters.zeitraum = this.getDefaultTimeKey()' in filter_js
assert "timeKey !== defaultTimeKey" in filter_js
assert "this.filters.zeitraum = defaultTimeKey" in filter_js

nav_js = (ROOT / "js/bottom-tabbar.js").read_text()
assert "isExactActiveTarget" in nav_js
assert "currentPath === normalizePath(item.href)" in nav_js
for forbidden in (
    "EVENTS_WEEKEND_PATH",
    "bindWeekendRouteSelection",
    "applyWeekendRouteContext",
    "window.location.assign(EVENTS_WEEKEND_PATH)",
):
    assert forbidden not in nav_js, f"shell nav must not own Weekend filter routing: {forbidden}"

style = (ROOT / "css/style.css").read_text()
assert '@import url("./weekend.css?' in style
weekend_css = (ROOT / "css/weekend.css").read_text()
assert "body.page-route-events-weekend #filter-time-pill" in weekend_css
assert "display: none !important" in weekend_css
assert "body.page-route-events-weekend .events-section-title" in weekend_css

# The selected SEO landing page is linked from both mobile sheet and desktop
# popover with real anchors. Other time presets remain in-page filters.
assert events.count('href="/events/wochenende/"') == 2
assert events.count('data-time-route="weekend"') == 2
assert 'data-time="weekend"' not in events

sitemap = (ROOT / "deploy-templates/sitemap.live.xml").read_text()
assert sitemap.count("https://bocholt-erleben.de/events/wochenende/") == 1
assert "/events/heute/" not in sitemap

print("SEO static/no-JS contract: OK")
