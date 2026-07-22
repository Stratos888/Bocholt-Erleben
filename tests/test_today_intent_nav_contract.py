#!/usr/bin/env python3
import re
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


class IntentNavParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.nav_depth = 0
        self.nav_classes = set()
        self.links = []

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        classes = set(attributes.get("class", "").split())
        if tag == "nav" and "today-intent-nav" in classes:
            self.nav_depth = 1
            self.nav_classes = classes
        elif self.nav_depth:
            self.nav_depth += 1

        if tag == "a" and self.nav_depth:
            self.links.append((attributes.get("href"), classes))

    def handle_endtag(self, tag):
        if self.nav_depth:
            self.nav_depth -= 1


html = (ROOT / "index.html").read_text(encoding="utf-8")
css = (ROOT / "css/today.css").read_text(encoding="utf-8")
parser = IntentNavParser()
parser.feed(html)

assert parser.nav_classes == {"today-intent-nav"}, "semantic intent nav missing"
assert parser.links == [
    ("/events/", {"today-intent-nav__link"}),
    ("/aktivitaeten/", {"today-intent-nav__link"}),
], "intent links or targets changed"

nav_rule = re.search(r"\.today-intent-nav\s*\{(?P<body>[^}]*)\}", css, re.S)
link_rule = re.search(r"\.today-intent-nav__link\s*\{(?P<body>[^}]*)\}", css, re.S)
focus_rule = re.search(r"\.today-intent-nav__link:focus-visible\s*\{(?P<body>[^}]*)\}", css, re.S)
assert nav_rule and link_rule and focus_rule, "targeted nav styles are incomplete"

nav_css = nav_rule.group("body")
link_css = link_rule.group("body")
assert "flex-direction: column" in nav_css and "width: 100%" in nav_css
assert "min-width: 0" in nav_css and "max-width:" in nav_css, "327px overflow guard missing"
assert "min-width: 0" in link_css and "overflow-wrap: anywhere" in link_css
assert "min-height: 44px" in link_css, "touch target is too small"
assert "color: var(" in link_css and "text-decoration: none" in link_css
assert "outline:" in focus_rule.group("body"), "visible keyboard focus missing"

hidden_patterns = ("display: none", "visibility: hidden", "opacity: 0", "position: absolute")
assert not any(pattern in nav_css or pattern in link_css for pattern in hidden_patterns), "intent links are hidden"

print("Today intent navigation contract: OK")
