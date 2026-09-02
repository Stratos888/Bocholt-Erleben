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


core_files = ("index.html", "events/index.html", "aktivitaeten/index.html")
for filename in core_files:
    text = (ROOT / filename).read_text()
    page = Page()
    page.feed(text)
    assert (page.h1, page.title, page.canonical, page.noindex) == (1, 1, 1, False), filename
    assert "STATIC:" in text and "static-content-card" in text, filename

weekend_path = ROOT / "events/wochenende/index.html"
weekend = weekend_path.read_text()
weekend_page = Page()
weekend_page.feed(weekend)
assert (weekend_page.h1, weekend_page.title, weekend_page.canonical, weekend_page.noindex) == (1, 1, 1, False)
assert "STATIC:WEEKEND:START" in weekend and "STATIC:WEEKEND:END" in weekend
assert "Veranstaltungen am Wochenende in Bocholt" in weekend
assert 'href="https://bocholt-erleben.de/events/wochenende/"' in weekend
assert '/events/' in weekend_page.links

home = (ROOT / "index.html").read_text()
events = (ROOT / "events/index.html").read_text()
activities = (ROOT / "aktivitaeten/index.html").read_text()
assert '/events/' in home and '/aktivitaeten/' in home and '/aktivitaeten/' in events and '/events/' in activities
assert '"@type": "Event"' not in events
assert events.count('data-date-month-label>Monat</div>') == 2
assert 'März 2026' not in events

sitemap = (ROOT / "deploy-templates/sitemap.live.xml").read_text()
assert sitemap.count("https://bocholt-erleben.de/events/wochenende/") == 1
assert "/events/heute/" not in sitemap

print("SEO static/no-JS contract: OK")
