#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
HOME_CSS = (ROOT / "css" / "home.css").read_text(encoding="utf-8")
SERVICE_WORKER = (ROOT / "service-worker.js").read_text(encoding="utf-8")


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

private_helper_start = SERVICE_WORKER.find("async function networkOnly(request)")
private_helper_end = SERVICE_WORKER.find(
    "/* === END BLOCK: PRIVATE_API_NETWORK_ONLY_V1 === */",
    private_helper_start,
)
private_helper = (
    SERVICE_WORKER[private_helper_start:private_helper_end]
    if private_helper_start >= 0 and private_helper_end > private_helper_start
    else ""
)
public_route_index = SERVICE_WORKER.find('url.pathname === "/api/events/public.php"')
private_route_index = SERVICE_WORKER.find('if (url.pathname.startsWith("/api/"))')
stale_route_index = SERVICE_WORKER.find("event.respondWith(staleWhileRevalidate(req));")

require(
    private_helper_start >= 0,
    "Der Service Worker braucht einen expliziten Network-only-Owner fuer private/dynamische API-GETs.",
)
require(
    'fetch(request, { cache: "no-store" })' in private_helper,
    "Private/dynamische API-GETs muessen den Browser-HTTP-Cache umgehen.",
)
require(
    "caches.open(" not in private_helper and ".put(" not in private_helper and ".match(" not in private_helper,
    "Private/dynamische API-GETs duerfen nicht in CacheStorage gelesen oder geschrieben werden.",
)
require(
    public_route_index >= 0,
    "Der oeffentliche Event-Feed muss seinen expliziten Network-first-Vertrag behalten.",
)
require(
    private_route_index > public_route_index,
    "Die allgemeine /api/-Network-only-Regel darf den expliziten oeffentlichen Event-Feed nicht uebersteuern.",
)
require(
    'event.respondWith(networkOnly(req));' in SERVICE_WORKER[private_route_index:private_route_index + 240],
    "Alle uebrigen same-origin /api/* GETs muessen ueber networkOnly laufen.",
)
require(
    stale_route_index > private_route_index,
    "Die private API-Route muss vor dem generischen stale-while-revalidate-Fallback liegen.",
)

print("Responsive grid and service-worker cache contract: OK")
