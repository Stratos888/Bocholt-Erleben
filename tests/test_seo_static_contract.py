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

# Weekend is an indexable route context of the same Event Hub, not a second
# independently maintained product implementation.
for required in (
    'class="desktop-hero"',
    'id="search-filter"',
    'id="filter-category-pill"',
    'id="event-cards"',
    'id="event-detail-panel"',
    'id="desktop-section-nav-root"',
    'id="bottom-tabbar-root"',
    'src="/js/events.js',
    'src="/js/filter.js',
    'src="/js/main.js',
):
    assert required in weekend, f"weekend Event-UX contract missing {required}"
assert "content-hero content-hero--panel" not in weekend
assert 'data-event-time-default="weekend"' in weekend
assert 'data-event-time-locked="true"' in weekend
assert "weekend-route-bridge" not in weekend

# No user-facing time selector remains on the Weekend landing page. The two
# hidden nodes are only the minimum compatibility host required by the shared
# FilterModule contract; the full time sheet/popover has been removed.
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
weekend_css_path = ROOT / "css/weekend.css"
assert weekend_css_path.exists()
weekend_css = weekend_css_path.read_text()
assert "body.page-route-events-weekend #filter-time-pill" in weekend_css
assert "display: none !important" in weekend_css
assert "body.page-route-events-weekend .events-section-title" in weekend_css
assert "weekend-route-bridge" not in weekend_css

home, home_page = parse("index.html")
events, events_page = parse("events/index.html")
activities, _ = parse("aktivitaeten/index.html")

# Home keeps crawlable section crosslinks. Cross-section app navigation on the
# section pages is owned centrally by the shared shell instead of duplicated
# inline links in every page template.
assert '/events/' in home and '/aktivitaeten/' in home
for section in (events, activities, weekend):
    assert 'id="desktop-section-nav-root"' in section
    assert 'id="bottom-tabbar-root"' in section
assert 'href: EVENTS_ROOT_PATH' in nav_js
assert 'href: "/aktivitaeten/"' in nav_js

assert '"@type": "Event"' not in events
assert events.count('data-date-month-label>Monat</div>') == 2
assert 'März 2026' not in events

# The selected SEO landing page is linked from both mobile sheet and desktop
# popover with real anchors. Other time presets remain in-page filters.
assert events.count('href="/events/wochenende/"') == 2
assert events.count('data-time-route="weekend"') == 2
assert 'data-time="weekend"' not in events

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
