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


def rule_body(css, selector):
    match = re.search(rf"{re.escape(selector)}\s*\{{(?P<body>[^}}]*)\}}", css, re.S)
    assert match, f"missing CSS rule for {selector}"
    return match.group("body")


def declarations(rule):
    return {
        name.strip(): value.strip()
        for name, value in re.findall(r"([\w-]+)\s*:\s*([^;]+);", rule)
    }


html = (ROOT / "index.html").read_text(encoding="utf-8")
css = (ROOT / "css/today.css").read_text(encoding="utf-8")
parser = IntentNavParser()
parser.feed(html)

assert parser.nav_classes == {"today-intent-nav"}, "semantic intent nav missing"
assert parser.links == [
    ("/events/", {"today-intent-nav__link"}),
    ("/aktivitaeten/", {"today-intent-nav__link"}),
], "intent links or targets changed"

nav = declarations(rule_body(css, ".today-intent-nav"))
link_rule = rule_body(css, ".today-intent-nav__link")
link = declarations(link_rule)
focus = declarations(rule_body(css, ".today-intent-nav__link:focus-visible"))

assert nav.get("display") == "flex" and "wrap" in nav.get("flex-flow", ""), "nav must wrap compactly"
assert nav.get("max-width") == "100%" and nav.get("min-width") == "0", "327px overflow guard missing"
assert link.get("min-width") == "0" and link.get("overflow-wrap") == "anywhere", "long links can overflow"
assert link.get("color", "").startswith("var("), "link must use the project link color"
assert link.get("text-decoration") == "none", "browser-default underline must be removed"
assert focus.get("outline", "none") != "none", "visible keyboard focus missing"

cta_properties = ("width", "min-height", "padding", "border", "background", "box-shadow")
assert not any(prop in link for prop in cta_properties), "links must not become full-width CTA surfaces"
assert not any(prop in nav for prop in ("background", "border", "box-shadow")), "nav must remain visually quiet"

hidden_values = {
    "display": "none",
    "visibility": "hidden",
    "opacity": "0",
    "position": "absolute",
}
assert not any(nav.get(prop) == value or link.get(prop) == value for prop, value in hidden_values.items()), (
    "intent links are hidden"
)

print("Today intent navigation contract: OK")
