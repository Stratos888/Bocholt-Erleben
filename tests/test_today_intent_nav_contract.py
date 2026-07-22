#!/usr/bin/env python3
from html.parser import HTMLParser
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

class IntentNavParser(HTMLParser):
    def __init__(self):
        super().__init__()
        self.in_nav = False
        self.links = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        classes = set(attrs.get("class", "").split())
        if tag == "nav" and "today-intent-nav" in classes:
            self.in_nav = True
        elif self.in_nav and tag == "a":
            self.links.append((attrs.get("href"), classes))

    def handle_endtag(self, tag):
        if tag == "nav" and self.in_nav:
            self.in_nav = False

parser = IntentNavParser()
parser.feed((ROOT / "index.html").read_text(encoding="utf-8"))
assert [href for href, _ in parser.links] == ["/events/", "/aktivitaeten/"]
assert all("today-intent-nav__link" in classes for _, classes in parser.links)

css = (ROOT / "css/today.css").read_text(encoding="utf-8")
link_rule = re.search(r"body\.page-route-today \.today-intent-nav__link\s*\{([^}]+)\}", css)
assert link_rule, "Targeted intent-link rule is missing"
declarations = link_rule.group(1)
assert re.search(r"color:\s*var\(--color-text-primary\)", declarations)
assert re.search(r"text-decoration:\s*none", declarations)
assert re.search(r"max-width:\s*100%", declarations)
assert re.search(r"min-width:\s*0", declarations)
assert re.search(r"overflow-wrap:\s*anywhere", declarations)
assert re.search(r"@media\s*\(max-width:\s*359px\)[\s\S]+grid-template-columns:\s*minmax\(0,\s*1fr\)", css)
assert not re.search(r"(?:display:\s*none|visibility:\s*hidden|opacity:\s*0)", declarations)
print("Today intent navigation contract: OK")
