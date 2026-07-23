#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
HOME_CSS = (ROOT / "css" / "home.css").read_text(encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


single_column_contract = "@media (min-width: 900px) and (max-width: 1099.98px) {"
two_column_contract = (
    "@media (min-width: 1100px) {\n"
    "  .events-feed-group__grid{\n"
    "    display: grid;\n"
    "    grid-template-columns: repeat(2, minmax(0, 1fr));"
)
old_single_column_contract = "@media (min-width: 900px) and (max-width: 1279.98px) {"
old_two_column_contract = (
    "@media (min-width: 1280px) {\n"
    "  .events-feed-group__grid{"
)

require(
    single_column_contract in HOME_CSS,
    "Der Event-Grid-Vertrag muss von 900 bis 1099.98 CSS px einspaltig bleiben.",
)
require(
    two_column_contract in HOME_CSS,
    "Der Event-Grid-Vertrag muss ab 1100 CSS px zweispaltig sein.",
)
require(
    old_single_column_contract not in HOME_CSS,
    "Der veraltete einspaltige Bereich bis 1279.98 CSS px ist noch vorhanden.",
)
require(
    old_two_column_contract not in HOME_CSS,
    "Der veraltete Zweispalten-Breakpoint bei 1280 CSS px ist noch vorhanden.",
)
require(
    "zwischen 900px und 1099.98px" in HOME_CSS,
    "Die dokumentierte Einspalten-Grenze muss dem wirksamen CSS entsprechen.",
)
require(
    "ab 1100px" in HOME_CSS,
    "Die dokumentierte Zweispalten-Grenze muss dem wirksamen CSS entsprechen.",
)

print("Responsive grid contract: OK")
