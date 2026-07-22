#!/usr/bin/env python3
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
class Page(HTMLParser):
    def __init__(self): super().__init__(); self.h1=0; self.links=[]; self.title=0; self.canonical=0; self.noindex=False
    def handle_starttag(self, tag, attrs):
        attrs=dict(attrs)
        if tag == "h1": self.h1 += 1
        if tag == "a" and attrs.get("href"): self.links.append(attrs["href"])
        if tag == "title": self.title += 1
        if tag == "link" and attrs.get("rel") == "canonical": self.canonical += 1
        if tag == "meta" and attrs.get("name") == "robots" and "noindex" in attrs.get("content", ""): self.noindex=True

for filename in ("index.html", "events/index.html", "aktivitaeten/index.html"):
    text=(ROOT/filename).read_text()
    page=Page(); page.feed(text)
    assert (page.h1, page.title, page.canonical, page.noindex) == (1, 1, 1, False), filename
    assert "STATIC:" in text and "static-content-card" in text, filename
home=(ROOT/"index.html").read_text(); events=(ROOT/"events/index.html").read_text(); activities=(ROOT/"aktivitaeten/index.html").read_text()
assert '/events/' in home and '/aktivitaeten/' in home and '/aktivitaeten/' in events and '/events/' in activities
assert '"@type": "Event"' not in events
print("SEO static/no-JS contract: OK")
