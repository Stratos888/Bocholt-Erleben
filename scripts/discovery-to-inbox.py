# === BEGIN BLOCK: DISCOVERY TO INBOX (sheet-driven, safe, v0) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Liest Google Sheet Tabs: "Events" (LIVE), "Inbox" (Vorschläge), "Sources" (Quellen)
# - Discovery v0: Holt neue Events aus RSS und iCal(ICS), dedupliziert grob, schreibt NUR in "Inbox"
# - Niemals automatische Veröffentlichung (Events-Tab wird nicht beschrieben)
#
# Eingaben (ENV):
# - SHEET_ID: Google Sheet ID (der lange String in der URL)
# - GOOGLE_SERVICE_ACCOUNT_JSON: Service-Account JSON (als Secret), im ENV als String
#
# Verhalten:
# - Für jede enabled Source: fetch + parse -> Kandidaten
# - Dedupe gegen LIVE Events (url oder (title+date)) und gegen Inbox (source_url+date+title)
# - Schreibt neue Vorschläge als "status=neu" in Inbox, inkl. match_score/notes/created_at
# === END BLOCK: DISCOVERY TO INBOX (sheet-driven, safe, v0) ===

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.request
import urllib.error

import xml.etree.ElementTree as ET
from datetime import datetime, date
from typing import Dict, List, Optional, Tuple

# Google API (wird im Workflow per pip installiert)
from google.oauth2 import service_account
from googleapiclient.discovery import build


# === BEGIN BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Tab-Namen per ENV überschreibbar machen (z.B. Events_Prod vs. Events_Test), ohne Code-Änderung pro Umgebung.
# - Source_Health + Discovery_Candidates Tabs ergänzen (Monitoring/Analyse, kein Einfluss auf Events selbst).
# Umfang:
# - Definiert nur TAB_* Konstanten.
# === END BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
TAB_SOURCE_HEALTH = os.environ.get("TAB_SOURCE_HEALTH", "Source_Health")
TAB_DISCOVERY_CANDIDATES = os.environ.get("TAB_DISCOVERY_CANDIDATES", "Discovery_Candidates")




INBOX_COLUMNS = [
    "status",
    "id_suggestion",
    "title",
    "date",
    "endDate",
    "time",
    "city",
    "location",
    "kategorie_suggestion",
    "url",
    "description",
    "source_name",
    "source_url",
    "match_score",
    "matched_event_id",
    "notes",
    "created_at",
]

# === BEGIN BLOCK: SOURCE HEALTH COLUMNS (sheet logging) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Pro Discovery-Run pro Quelle Status protokollieren (ok/fetch_error/parse_error/unsupported)
# - Damit kaputte Quellen sofort sichtbar sind (PWA kann später daraus Banner bauen)
# Umfang:
# - Definiert nur Spaltenreihenfolge für Tab "Source_Health"
# === END BLOCK: SOURCE HEALTH COLUMNS (sheet logging) ===
SOURCE_HEALTH_COLUMNS = [
    "source_name",
    "type",
    "url",
    "status",
    "http_status",
    "error",
    "last_checked_at",
    "candidates_count",
    "new_rows_written",
]

# === BEGIN BLOCK: DISCOVERY CANDIDATES COLUMNS (sheet logging) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Alle geparsten Kandidaten pro Run in einen Analyse-Tab loggen (auch rejected / deduped),
#   um Parser/Heuristiken datenbasiert zu verbessern.
# Umfang:
# - Definiert nur Spaltenreihenfolge für Tab "Discovery_Candidates"
# === END BLOCK: DISCOVERY CANDIDATES COLUMNS (sheet logging) ===
DISCOVERY_CANDIDATES_COLUMNS = [
    "run_ts",
    "source_name",
    "source_type",
    "source_url",
    "status",
    "reason",
    "is_written_to_inbox",
    "is_already_live",
    "is_already_inbox",
    "matched_event_id",
    "match_score",
    "fingerprint",
    "id_suggestion",
    "title",
    "event_date",
    "endDate",
    "time",
    "city",
    "location",
    "kategorie_suggestion",
    "url",
    "notes",
    "created_at",
]



# === BEGIN BLOCK: DISCOVERY FILTER CONFIG (hard skip junk + date window) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Presse-/Nicht-Event-Müll gar nicht erst in die Inbox schreiben.
# - Nur Events im sinnvollen Zeitraum übernehmen.
# Umfang:
# - Nur Konfiguration (Regex/Window). Logik folgt in Helper-Funktionen.
# === END BLOCK: DISCOVERY FILTER CONFIG (hard skip junk + date window) ===

# Zeitfenster: nur Events von heute bis +365 Tage
DATE_WINDOW_DAYS_FUTURE = int(os.environ.get("DATE_WINDOW_DAYS_FUTURE", "365"))
DATE_WINDOW_ALLOW_PAST_DAYS = int(os.environ.get("DATE_WINDOW_ALLOW_PAST_DAYS", "0"))

# Quellen, die typischerweise Presse-/Info-Müll liefern (hard skip, wenn keine klaren Event-Signale)
PRESS_SOURCE_HINTS = (
    "presse",
    "presse-service",
    "pressemitteilung",
)

# Worte/Patterns, die sehr wahrscheinlich KEIN Event sind (hard skip)
NON_EVENT_PATTERNS = [
    # Presse / Infrastruktur
    r"\bstau\b",
    r"\bverkehr\b",
    r"\bumleitung\b",
    r"\bsperr",
    r"\bvollsperr",
    r"\bbaustell",
    r"\bumbau",
    r"\bsanier",
    r"\bglasfaser",
    r"\bkanal\b",
   # === BEGIN BLOCK: NON EVENT KEYWORDS (press cleanup add, v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Source-spezifischen Presse/Info-Müll härter erkennen
# Umfang: Ersetzt nur den Teilblock um trinkwasser/hausbrunnen
# === END BLOCK: NON EVENT KEYWORDS (press cleanup add, v1) ===
    r"\btrinkwasser\b",
    r"\bwasserverband\b",
    r"\bbekanntmachung\b",
    r"\bgrußwort\b",
    r"\bgrusswort\b",
    r"\bhausbrunnen\b",

    r"\buntersuch",
    r"\bkontroll",
    r"\bmüllabfuhr\b",
    r"\babfuhr\b",
    r"\babfall\b",
    r"\bsitzung\b",
    r"\bausschuss\b",
    r"\bratssitzung\b",
    r"\bpressemitteilung\b",
    r"\bmitteilung\b",
    r"\bhinweis\b",
    r"\bwarn",

    # Schule / interne Termine (NEU)
    r"\belternabend\b",
    r"\bberufsberatung\b",
    r"\bberatung\b",
    r"\bklassen?\b",
    r"\bklasse\s*\d",
    r"\b9a\b",
    r"\b8b\b",
    r"\bunterricht\b",
    r"\bsprechstunde\b",
    r"\bprävention\b",
    r"\balkohol\b",
    r"\bdrogen\b",
    r"\bverkehrserziehung\b",
    r"\bschul\b",
    r"\bgymnasium\b",
    r"\brealschule\b",
    r"\bgesamtschule\b",
]


# Worte/Patterns, die stark auf ein Event hindeuten (Event-Signale)
# === BEGIN BLOCK: EVENT SIGNAL PATTERNS (expanded, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Mehr echte Eventformen erkennen (Bocholt-tauglich)
# - Vorträge/Workshops bleiben drin (landen in Review)
# Umfang:
# - Ersetzt nur EVENT_SIGNAL_PATTERNS
# === END BLOCK: EVENT SIGNAL PATTERNS (expanded, v2) ===
# === BEGIN BLOCK: EVENT SIGNAL PATTERNS (quality fix, v3) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - False-Positives durch generisches \bfest\b verhindern (z.B. "Fest- und Feiertagen")
# - Nur klare Event-Signale
# Umfang:
# - Ersetzt nur EVENT_SIGNAL_PATTERNS
# === END BLOCK: EVENT SIGNAL PATTERNS (quality fix, v3) ===
EVENT_SIGNAL_PATTERNS = [
    r"\bkonzert\b",
    r"\blesung\b",
    r"\bfestival\b",
    r"\btheater\b",
    r"\bausstellung\b",
    r"\bf\u00fchrung\b",
    r"\bworkshop\b",
    r"\bvortrag\b",
    r"\bmarkt\b",
    r"\bturnier\b",
    # spezifische Feste (statt generischem \bfest\b)
    r"\bstadtfest\b",
    r"\bweinfest\b",
    r"\bsommerfest\b",
    r"\boktoberfest\b",
]





def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def info(msg: str) -> None:
    print(f"ℹ️  {msg}")


def now_iso() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


# === BEGIN BLOCK: TEXT NORMALIZATION HELPERS (clean v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Saubere, stabile Textwerte in Inbox schreiben (Whitespace, HTML-Tags, Entities).
# Umfang: Nur Helper-Funktionen, keine Verhaltensänderung bei Status/Import-Flow.
# === END BLOCK: TEXT NORMALIZATION HELPERS (clean v1) ===

def norm(s: str) -> str:
    return (s or "").strip()


def norm_key(s: str) -> str:
    return re.sub(r"\s+", " ", norm(s)).lower()


# === BEGIN BLOCK: CLEAN TEXT (strip html + normalize spaces) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Entfernt HTML-Tags, decodiert Entities, normalisiert Whitespace.
# Umfang: Reine Helferfunktionen (facts-only), keine Side-Effects.
# === END BLOCK: CLEAN TEXT (strip html + normalize spaces) ===
import html as _html


def strip_html(s: str) -> str:
    s = norm(s)
    if not s:
        return ""
    # Tags raus
    s = re.sub(r"<[^>]+>", " ", s)
    return s


def normalize_ws(s: str) -> str:
    s = norm(s)
    if not s:
        return ""
    s = re.sub(r"\s+", " ", s)
    return s.strip()


def clean_text(s: str) -> str:
    s = strip_html(s)
    s = _html.unescape(s)
    s = normalize_ws(s)
    return s



# === BEGIN BLOCK: SLUG + CANONICAL URL + ID SUGGESTION (dedupe v1) ===
# Zweck: robustes Dedupe (slug) + stabile id_suggestion + URL-Normalisierung (utm/v/fbclid etc.)
# Umfang: reine Hilfsfunktionen, keine Side-Effects

import hashlib
from urllib.parse import urlparse, urlunparse, parse_qsl, urlencode, unquote


def slugify(s: str) -> str:
    s = norm_key(s)

    # de-umlaut (konservativ)
    s = (
        s.replace("ä", "ae")
        .replace("ö", "oe")
        .replace("ü", "ue")
        .replace("ß", "ss")
    )

    # nur [a-z0-9-]
    s = re.sub(r"[^a-z0-9]+", "-", s)
    s = re.sub(r"-{2,}", "-", s).strip("-")
    return s


def canonical_url(u: str) -> str:
    u = norm(u)
    if not u:
        return ""

    try:
        p = urlparse(u)
    except Exception:
        return u

    # drop fragment
    fragment = ""

    # keep only non-tracking query params; also drop build/cache params
    drop_prefixes = ("utm_",)
    drop_keys = {"fbclid", "gclid", "yclid", "mc_cid", "mc_eid", "ref", "source", "v"}

    q = []
    for k, v in parse_qsl(p.query, keep_blank_values=True):
        lk = (k or "").lower()
        if any(lk.startswith(pref) for pref in drop_prefixes):
            continue
        if lk in drop_keys:
            continue
        q.append((k, v))

    query = urlencode(q, doseq=True)

    # normalize scheme/host casing
    netloc = (p.netloc or "").lower()
    scheme = (p.scheme or "").lower()

    return urlunparse((scheme, netloc, p.path, p.params, query, fragment))


def short_hash(s: str, n: int = 4) -> str:
    return hashlib.md5(s.encode("utf-8")).hexdigest()[:n]


def make_id_suggestion(title: str, d: str, t: str, source_url: str) -> str:
    """
    Stable, collision-resistant ID:
      <slug(title)>-<yyyymmdd>-<hash4>
    """
    st = slugify(title)
    if not st:
        st = "event"

    ymd = re.sub(r"[^0-9]", "", norm(d))  # "2026-03-15" -> "20260315"
    if len(ymd) != 8:
        ymd = "00000000"

    key = f"{norm_key(canonical_url(source_url))}|{st}|{norm(d)}|{norm(t)}"
    return f"{st[:60]}-{ymd}-{short_hash(key, 4)}"
# === END BLOCK: SLUG + CANONICAL URL + ID SUGGESTION (dedupe v1) ===

# === BEGIN BLOCK: DISCOVERY FILTER HELPERS (junk skip + date window) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Hard-skip für Nicht-Events (Presse/Verkehr/Baustelle/etc.)
# - Date-Window Filter (keine alten Termine, keine extrem weit entfernten Termine)
# Umfang:
# - Nur Helper-Funktionen + Regex-Compile.
# === BEGIN BLOCK: LOCATION/TIME INFERENCE (rss/ics enrichment, v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - RSS liefert oft keine Location/Time-Felder -> aus Title/Description ableiten
# - "Online-Seminar" -> Location="Online"
# - "// ... //" Segmente im Titel (presse-service) als Ort/Adresse nutzen
# Umfang:
# - Nur Heuristiken, keine externen Requests
# === END BLOCK: LOCATION/TIME INFERENCE (rss/ics enrichment, v1) ===

# === BEGIN BLOCK: LOCATION/TIME INFERENCE (rss/ics enrichment, v2 robust) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - RSS liefert oft keine Location/Time-Felder -> aus Title/Description ableiten
# - Robustere Extraktion aus "Ort:", "Veranstaltungsort:", "Treffpunkt:", Adresszeilen
# - Online/Webinar bleibt "Online"
# Umfang:
# - Ersetzt nur die infer_location_time-Helper inkl. Regexes

# === BEGIN BLOCK: TIME INFERENCE REGEX (broader, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Mehr Zeiten finden (auch "ab 19 Uhr", "Beginn 20:00 Uhr", "Einlass 18.30 Uhr", "19:00 Uhr")
# Umfang:
# - Ersetzt nur Regex + _infer_time_from_text (keine weitere Logik)
# === END BLOCK: TIME INFERENCE REGEX (broader, v2) ===
_TIME_IN_TITLE_RE = re.compile(
    r"\b(?:um|ab|von|beginn|start|einlass)?\s*(\d{1,2})(?:[:\.](\d{2}))?\s*uhr\b",
    re.IGNORECASE,
)
_SLASH_SEG_RE = re.compile(r"\s*//\s*")

def _infer_time_from_text(text: str) -> str:
    t = text or ""
    m = _TIME_IN_TITLE_RE.search(t)
    if not m:
        # fallback: "19:00" ohne "Uhr" (vorsichtig)
        m2 = re.search(r"\b(\d{1,2})(?:[:\.](\d{2}))\b", t)
        if not m2:
            return ""
        hh = int(m2.group(1))
        mm = int(m2.group(2))
        if hh > 23 or mm > 59:
            return ""
        return f"{hh:02d}:{mm:02d}"

    hh = int(m.group(1))
    mm = int(m.group(2)) if m.group(2) else 0
    if hh > 23 or mm > 59:
        return ""
    return f"{hh:02d}:{mm:02d}"

    # fallback: erste HH:MM im Text
    m = _TIME_PLAIN_RE.search(txt)
    if m:
        hh = int(m.group(1))
        mm = int(m.group(2))
        if 0 <= hh <= 23 and 0 <= mm <= 59:
            return f"{hh:02d}:{mm:02d}"
    return ""

def _infer_location_from_title(title: str) -> str:
    # presse-service Titel hat oft: "<Titel> // <Ort/Adresse> // ..."
    parts = [p.strip() for p in _SLASH_SEG_RE.split(title or "") if p.strip()]
    if len(parts) >= 2:
        loc = parts[1]
        if len(loc) >= 3 and loc.lower() not in ("eintritt frei",):
            return loc
    return ""

def _clean_loc(loc: str) -> str:
    loc = clean_text(loc)
    if not loc:
        return ""
    # häufige Abschneider
    loc = re.split(r"\s+(?:am|um|ab|von|für|mit)\b", loc, maxsplit=1)[0].strip()
    # sehr generische Werte verwerfen
    if loc.lower() in ("bocholt", "kreis borken", "nrw"):
        return ""
    return loc

# === BEGIN BLOCK: LOCATION+TIME INFERENCE (better heuristics, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Location häufiger/sauberer füllen (Labels wie "Ort:", "Veranstaltungsort:", "Treffpunkt:", "Wo:", "Adresse:")
# - Weiterhin konservativ bleiben (keine Fantasie-Orte)
# Umfang:
# - Ersetzt nur _infer_location_from_description + infer_location_time
# === END BLOCK: LOCATION+TIME INFERENCE (better heuristics, v2) ===
def _infer_location_from_description(desc: str) -> str:
    d = (desc or "").strip()
    if not d:
        return ""

    # 1) Explizite Labels (häufig auf Eventseiten)
    for lab in ("Veranstaltungsort", "Ort", "Treffpunkt", "Wo", "Adresse"):
        m = re.search(rf"(?:^|\n)\s*{lab}\s*:\s*([^\n\r]{{3,160}})", d, flags=re.IGNORECASE)
        if m:
            loc = clean_text(m.group(1))
            loc = re.split(r"\s+(am|um|ab|von|für|mit)\b", loc, flags=re.IGNORECASE)[0].strip()
            if len(loc) >= 3:
                return loc

    # 2) Muster: "im <Ort>" / "in <Ort>" (aber harte Ausschlüsse gegen Quatsch)
    m = re.search(r"\b(im|in)\s+([A-ZÄÖÜ][^.,;\n]{2,100})", d)
    if m:
        loc = m.group(2).strip()
        loc = re.split(r"\s+(am|um|ab|von|für|mit)\b", loc, flags=re.IGNORECASE)[0].strip()

        bad_starts = ("rahmen", "kurs", "workshop", "seminar", "programm", "projekt", "gruppe")
        if loc and not any(loc.lower().startswith(b) for b in bad_starts):
            if len(loc) >= 3:
                return loc

    return ""

def infer_location_time(title: str, description: str) -> Tuple[str, str]:
    t = norm(title)
    d = norm(description)
    low = f"{t} {d}".lower()

    # Online zuerst
    if "online" in low or "webinar" in low:
        return ("Online", _infer_time_from_text(t) or _infer_time_from_text(d))

    loc = _infer_location_from_title(t)
    if not loc:
        loc = _infer_location_from_description(d)

    tm = _infer_time_from_text(t) or _infer_time_from_text(d)
    return (loc, tm)

# === END BLOCK: LOCATION/TIME INFERENCE (rss/ics enrichment, v2 robust) ===

# === END BLOCK: DISCOVERY FILTER HELPERS (junk skip + date window) ===


# === BEGIN BLOCK: DETAIL HTML TIME/LOCATION EXTRACTION (global helper, v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Fix: _extract_time_loc_from_html muss global verfügbar sein (wird auch im Main-Loop genutzt)
# - Stabil: nie Exceptions nach außen (liefert ("","") bei Problemen)
# - Qualität: bevorzugt JSON-LD (Event/Course), fällt optional auf Text-Inference zurück
# Umfang:
# - Neue globale Helper-Funktion (keine Änderung am Parsing-Flow außerhalb der Call-Sites)
# === END BLOCK: DETAIL HTML TIME/LOCATION EXTRACTION (global helper, v1) ===
def _extract_time_loc_from_html(
    html_text: str,
    *,
    title: str = "",
    allow_text_fallback: bool = False,
) -> Tuple[str, str]:
    """Returns (time_str, location_str). Never raises."""
    try:
        html = html_text or ""

        scripts = re.findall(
            r"<script[^>]+type=['\"]application/ld\+json['\"][^>]*>(.*?)</script>",
            html,
            flags=re.IGNORECASE | re.DOTALL,
        )

        def _iter_ld_nodes(obj):
            if isinstance(obj, list):
                for it in obj:
                    yield from _iter_ld_nodes(it)
            elif isinstance(obj, dict):
                yield obj
                if "@graph" in obj:
                    yield from _iter_ld_nodes(obj.get("@graph"))

        def _is_relevant_type(tval) -> bool:
            if isinstance(tval, str):
                k = norm_key(tval)
                return (
                    k.endswith("event")
                    or k.endswith(":event")
                    or k.endswith("course")
                    or k.endswith(":course")
                    or "educationevent" in k
                    or "courseinstance" in k
                )
            if isinstance(tval, list):
                return any(_is_relevant_type(x) for x in tval)
            return False

        def _time_from_iso(sd_raw: str, ed_raw: str) -> str:
            t1 = _infer_time_from_text(sd_raw or "")
            t2 = _infer_time_from_text(ed_raw or "")
            if t1 and t2 and t2 != t1:
                return f"{t1}–{t2}"
            return t1 or ""

        # 1) JSON-LD bevorzugt
        for raw in scripts:
            raw = (raw or "").strip()
            if not raw:
                continue
            try:
                data = json.loads(raw)
            except Exception:
                continue

            for node in _iter_ld_nodes(data):
                if not isinstance(node, dict):
                    continue
                if not _is_relevant_type(node.get("@type")):
                    continue

                # time
                time_str = _time_from_iso(str(node.get("startDate") or ""), str(node.get("endDate") or ""))

                # location
                loc_str = ""
                loc_obj = node.get("location") or node.get("place")
                if isinstance(loc_obj, dict):
                    loc_name = clean_text(str(loc_obj.get("name", "") or ""))
                    addr = loc_obj.get("address")
                    addr_str = ""
                    if isinstance(addr, dict):
                        addr_parts = [
                            clean_text(str(addr.get("streetAddress", "") or "")),
                            clean_text(str(addr.get("postalCode", "") or "")),
                            clean_text(str(addr.get("addressLocality", "") or "")),
                        ]
                        addr_str = " ".join([p for p in addr_parts if p]).strip()
                    elif isinstance(addr, str):
                        addr_str = clean_text(addr)
                    loc_str = " - ".join([p for p in [loc_name, addr_str] if p]).strip()
                elif isinstance(loc_obj, str):
                    loc_str = clean_text(loc_obj)

                if time_str or loc_str:
                    return (time_str, loc_str)

                # Kurs-Instanzen enthalten oft die echten Werte
                for key in ("hasCourseInstance", "courseInstance", "subEvent", "event"):
                    inst = node.get(key)
                    if not inst:
                        continue
                    inst_list = [inst] if isinstance(inst, dict) else (inst if isinstance(inst, list) else [])
                    for it in inst_list:
                        if not isinstance(it, dict):
                            continue
                        t2 = _time_from_iso(str(it.get("startDate") or ""), str(it.get("endDate") or ""))
                        loc2 = ""
                        loc_obj2 = it.get("location") or it.get("place")
                        if isinstance(loc_obj2, dict):
                            loc2 = clean_text(str(loc_obj2.get("name", "") or ""))
                        elif isinstance(loc_obj2, str):
                            loc2 = clean_text(loc_obj2)
                        if t2 or loc2:
                            return (t2, loc2)

        # 2) Fallback: Text-Inference
        if allow_text_fallback:
            detail_text = clean_text(_strip_html_tags(html))
            loc_guess, time_guess = infer_location_time(title or "", detail_text)
            return (time_guess or "", loc_guess or "")

        return ("", "")
    except Exception:
        return ("", "")
_NON_EVENT_RE = re.compile("|".join(f"(?:{p})" for p in NON_EVENT_PATTERNS), re.IGNORECASE)
_EVENT_SIGNAL_RE = re.compile(
    r"\b("
    r"konzert|live|festival|party|show|theater|kabarett|lesung|"
    r"ausstellung|vernissage|führung|vortrag|seminar|kurs|workshop|"
    r"treffen|treff|stammtisch|repair\s*cafe|repaircafe|"
    r"markt|flohmarkt|basar|börse|messe|"
    r"event|veranstaltung|programm|abend|nacht|"
    r"kino|film|premiere|aufführung|performance|"
    r"tag\s*der\s*offenen\s*tür|familientag|"
    r"aktion|angebot"
    r")\b",
    re.IGNORECASE,
)

def _is_press_like_source(source_name: str, source_url: str) -> bool:
    key = f"{norm_key(source_name)} {norm_key(source_url)}"
    return any(h in key for h in PRESS_SOURCE_HINTS)

def _has_event_signal(text: str) -> bool:
    return bool(_EVENT_SIGNAL_RE.search(text or ""))

def _is_non_event_text(text: str) -> bool:
    return bool(_NON_EVENT_RE.search(text or ""))

def _parse_iso_date(d: str) -> Optional[date]:
    d = norm(d)
    if not d:
        return None
    try:
        return datetime.strptime(d, "%Y-%m-%d").date()
    except Exception:
        return None

def _in_date_window(d_iso: str) -> bool:
    d = _parse_iso_date(d_iso)
    if not d:
        return False
    today = date.today()
    min_d = today.fromordinal(today.toordinal() - max(DATE_WINDOW_ALLOW_PAST_DAYS, 0))
    max_d = today.fromordinal(today.toordinal() + max(DATE_WINDOW_DAYS_FUTURE, 0))
    return (d >= min_d) and (d <= max_d)


def _format_date_de(d_iso: str) -> str:
    d = _parse_iso_date(d_iso)
    if not d:
        return d_iso
    months = [
        "Januar", "Februar", "März", "April", "Mai", "Juni",
        "Juli", "August", "September", "Oktober", "November", "Dezember"
    ]
    return f"{d.day}. {months[d.month - 1]} {d.year}"


def _format_time_de(time_str: str) -> str:
    t = norm(time_str)
    if not t:
        return ""
    # "19:00–23:00" -> "von 19:00 bis 23:00 Uhr"
    if "–" in t:
        a, b = [x.strip() for x in t.split("–", 1)]
        if a and b:
            return f"von {a} bis {b} Uhr"
    # "19:00" -> "ab 19:00 Uhr"
    return f"ab {t} Uhr"


# === BEGIN BLOCK: DISCOVERY DESCRIPTION STYLE (bocholt voice, fact-only, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Fallback-Description im "Bocholt erleben"-Stil erzeugen, wenn Quellen keine Beschreibung liefern.
# - Neutral, faktenbasiert, rechtlich sauber (keine erfundenen Inhalte), max. 3 kurze Sätze.
# Umfang:
# - Ersetzt ausschließlich ensure_description() (Zeilen 456–511 im ZIP-Stand).
# === END BLOCK: DISCOVERY DESCRIPTION STYLE (bocholt voice, fact-only, v2) ===
def ensure_description(
    *,
    title: str,
    d_iso: str,
    time_str: str,
    city: str,
    location: str,
    category: str,
    url: str,
    description_raw: str,
) -> str:
    """
    Rechtlich sauber, faktisch, ohne Bewertung:
    - Nutzt nur bekannte Felder (Titel/Datum/Zeit/Ort/Kategorie/URL)
    - Keine erfundenen Inhalte
    - Kurz und im "Bocholt erleben"-Ton (ruhig, direkt, ohne Marketing-Floskeln)
    """
    raw = clean_text(description_raw)
    if raw:
        return raw

    t = clean_text(title) or "Veranstaltung"
    city_clean = clean_text(city)
    loc = clean_text(location)
    cat = clean_text(category)

    date_de = _format_date_de(d_iso) if d_iso else ""
    time_de = _format_time_de(time_str)

    # Titelzeile im App-Stil: "Titel: ..."
    lead = t if t.endswith(":") else f"{t}:"

    # Ort natürlich formulieren, ohne "in X in Y"
    place = ""
    if loc and city_clean:
        if norm_key(city_clean) in norm_key(loc):
            place = f"in {loc}"
        else:
            place = f"in {loc}, {city_clean}"
    elif city_clean:
        place = f"in {city_clean}"
    elif loc:
        place = f"in {loc}"

    # Satz 1: harte Fakten, kurz
    s1_parts: List[str] = []
    if date_de:
        s1_parts.append(f"Am {date_de}")
    else:
        s1_parts.append("Termin")

    if time_de:
        s1_parts.append(time_de)

    if place:
        s1_parts.append(place)

    s1 = f"{lead} " + " ".join([p for p in s1_parts if p]).strip() + "."
    parts: List[str] = [s1]

    # Satz 2: Kategorie ohne Datenexport-Ton
    if cat:
        parts.append(f"Ein Termin aus dem Bereich {cat}.")

    # Satz 3: Quelle/Details (neutral, hilfreich)
    if url:
        parts.append("Details und mögliche Änderungen: offizielle Veranstaltungsseite.")
    else:
        parts.append("Details und mögliche Änderungen: offizielle Quelle.")

    return " ".join([p for p in parts if p]).strip()
# === BEGIN BLOCK: DISCOVERY DESCRIPTION STYLE (bocholt voice, fact-only, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck: Ende des ensure_description()-Blocks (siehe Header).
# Umfang: Keine weiteren Änderungen.
# === END BLOCK: DISCOVERY DESCRIPTION STYLE (bocholt voice, fact-only, v2) ===



# === BEGIN BLOCK: EVENT QUALITY GATE (review-first, v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Echte Events sollen als review landen (nicht blocked)
# - Müll (Presse/Verkehr/Sitzungen/Schule etc.) soll rejected werden (gar nicht in Inbox)
# - Grenzfälle (Vortrag/Workshop) bleiben als review für manuelle Prüfung
# Umfang:
# - Ersetzt nur classify_candidate()
# === END BLOCK: EVENT QUALITY GATE (review-first, v1) ===
# === BEGIN BLOCK: EVENT QUALITY GATE (final rules, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Nur noch Status: review / rejected
# - RSS ohne Event-Datum -> rejected (Option A)
# - Kein Event-Signal -> rejected
# - Event-Signal + Datum -> review
# - Event-Signal + kein Datum -> review (außer RSS)
# Umfang:
# - Ersetzt nur classify_candidate()
# === END BLOCK: EVENT QUALITY GATE (final rules, v2) ===
# === BEGIN BLOCK: EVENT QUALITY GATE (inclusive public events, freeze v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Umsetzung eurer Definition (1C/2C/3B/4C/5B): "viel rein", inkl. Kurse & Wiederkehrendes
# - Rejected nur für: klar kein Event / klar nicht öffentlich / außerhalb Date-Window
# - Missing date bleibt review (für Analyse), aber Inbox-Gate verhindert ohnehin unvollständige Events
# Umfang:
# - Ersetzt nur classify_candidate()
# === END BLOCK: EVENT QUALITY GATE (inclusive public events, freeze v1) ===
# === BEGIN BLOCK: EVENT QUALITY GATE (inclusive public events + regular service filter, freeze v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Weiterhin: "viel rein" (Kurse/Workshops/Wiederkehrendes erlaubt), rejected nur für klar ungeeignet
# - Neu (Decision 2026-02-27): Normale Gottesdienste / regulärer Kirchenbetrieb zählen NICHT als Event
#   -> rejected, AUSSER es gibt klare Event-Signale (z.B. Konzert/Lesung/Workshop/Sonderformat)
# Umfang:
# - Ersetzt nur classify_candidate()
# === END BLOCK: EVENT QUALITY GATE (inclusive public events + regular service filter, freeze v2) ===
def classify_candidate(
    *,
    stype: str,
    source_name: str,
    source_url: str,
    title: str,
    description: str,
    event_date: str,
) -> Tuple[str, str]:

    text = f"{title}\n{description}".strip()
    text_l = text.lower()

    # 1) Klar nicht öffentlich (5B: Community ja, aber keine internen Vereinsinterna)
    not_public_keywords = [
        "mitgliederversammlung",
        "jahreshauptversammlung",
        "nur für mitglieder",
        "nur fuer mitglieder",
        "vereinsintern",
        "intern",
        "geschlossene gesellschaft",
        "einladung",
        "kassenprüfung",
        "kassenpruefung",
        "vorstandssitzung",
    ]
    if any(k in text_l for k in not_public_keywords):
        return "rejected", "reject:not_public"

    # 2) Normale Gottesdienste / regulärer Kirchenbetrieb sind KEINE Events (Decision 2026-02-27)
    #    Ausnahme: es gibt klare Event-Signale (Konzert/Lesung/Workshop/...).
    regular_service_keywords = [
        "gottesdienst",
        "abendmahl",
        "kirchkaffee",
        "messe",
        "eucharistie",
        "andacht",
        "vesper",
        "rosenkranz",
        "tauffeier",
        "trauung",
        "beichte",
        "bibelstunde",
        "kindergottesdienst",
    ]
    if any(k in text_l for k in regular_service_keywords) and (not _has_event_signal(text)):
        return "rejected", "reject:regular_service"

    # 3) Klar kein Event (Presse/Infra/Utility) -> rejected,
    #    aber nur wenn KEIN Event-Signal vorhanden ist
    if _is_non_event_text(text) and (not _has_event_signal(text)):
        return "rejected", "reject:non_event_pattern"

    # 4) Datum fehlt -> review (für Analyse/Fehlerfälle); Inbox-Gate schreibt eh nicht ohne date+loc+url
    if not event_date:
        return "review", "review:missing_date"

    # 5) Date-Window (today..+365) erzwingen, damit Inbox nicht mit alten Sachen zugelaufen wird
    if not _in_date_window(event_date):
        return "rejected", "reject:outside_window"

    # 6) Default: öffentliches Event -> review
    # (Kurse/Workshops/Vorlesungen sind ausdrücklich erlaubt in Freeze v1)
    return "review", "review:public_event"
# === END BLOCK: EVENT QUALITY GATE (inclusive public events, freeze v1) ===

# === END BLOCK: DISCOVERY FILTER HELPERS (junk skip + date window) ===



# === BEGIN BLOCK: SAFE FETCH (retry + backoff for 503/429/5xx, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Stabiler Fetch gegen sporadische 503/429/5xx (z.B. Münsterland)
# - Retries mit Backoff + Retry-After Support
# - Nach Ausschöpfen der Retries: "soft fail" für diese Codes (return ""), damit kein "Parse failed" geloggt wird
# Umfang:
# - Ersetzt nur safe_fetch()
# === END BLOCK: SAFE FETCH (retry + backoff for 503/429/5xx, v2) ===
# === BEGIN BLOCK: SAFE FETCH (GET) + SAFE FETCH (POST FORM) (v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Ergänzt safe_fetch_post() für POST-Form-Pagination (Bocholt Kalender)
# Umfang:
# - Ersetzt den bestehenden safe_fetch()-Block 1:1 und fügt safe_fetch_post() direkt danach hinzu
# === END BLOCK: SAFE FETCH (GET) + SAFE FETCH (POST FORM) (v1) ===

def safe_fetch(url: str, timeout: int = 20) -> str:
    backoffs = [10, 30, 90]
    last_err: Exception | None = None

    for attempt in range(len(backoffs) + 1):
        req = urllib.request.Request(
            url,
            headers={
                "User-Agent": "BocholtErlebenDiscovery/1.0",
                "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
                "Accept-Language": "de-DE,de;q=0.9,en;q=0.6",
            },
        )

        try:
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                raw = resp.read()
                try:
                    return raw.decode("utf-8", errors="replace")
                except Exception:
                    return raw.decode("latin-1", errors="replace")
        except Exception as e:
            last_err = e
            if attempt < len(backoffs):
                time.sleep(backoffs[attempt])
                continue
            return ""

    return ""


def safe_fetch_post(url: str, form: Dict[str, str], timeout: int = 20) -> str:
    """
    POST x-www-form-urlencoded (simple form submit).
    Wichtig: URL-Fragmente (#results) werden entfernt.
    """
    url = (url or "").split("#", 1)[0].strip()

    body = urllib.parse.urlencode(form or {}).encode("utf-8")
    req = urllib.request.Request(
        url,
        data=body,
        method="POST",
        headers={
            "User-Agent": "BocholtErlebenDiscovery/1.0",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "de-DE,de;q=0.9,en;q=0.6",
            "Content-Type": "application/x-www-form-urlencoded",
        },
    )

    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            raw = resp.read()
            try:
                return raw.decode("utf-8", errors="replace")
            except Exception:
                return raw.decode("latin-1", errors="replace")
    except Exception:
        return ""
# === END BLOCK: SAFE FETCH (GET) + SAFE FETCH (POST FORM) (v1) ===

def ics_unfold_lines(text: str) -> List[str]:
    # RFC5545 line folding: lines starting with space/tab are continuations
    lines = text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    out: List[str] = []
    for line in lines:
        if not line:
            continue
        if line.startswith((" ", "\t")) and out:
            out[-1] += line[1:]
        else:
            out.append(line)
    return out


def parse_ics_date(val: str) -> Tuple[Optional[str], Optional[str]]:
    """
    Returns (date_iso, time_str_optional)
    Handles:
      - DTSTART:20260204T193000
      - DTSTART;VALUE=DATE:20260204
      - DTSTART:20260204T193000Z  (treated as local date/time string)
    """
    v = val.strip()
    v = v.replace("Z", "")
    if re.fullmatch(r"\d{8}", v):
        d = datetime.strptime(v, "%Y%m%d").date()
        return (d.strftime("%Y-%m-%d"), "")
    m = re.fullmatch(r"(\d{8})T(\d{2})(\d{2})(\d{2})?", v)
    if not m:
        return (None, None)
    d = datetime.strptime(m.group(1), "%Y%m%d").date()
    hh = m.group(2)
    mm = m.group(3)
    return (d.strftime("%Y-%m-%d"), f"{int(hh):02d}:{int(mm):02d}")


def parse_ics_events(ics_text: str) -> List[Dict[str, str]]:
    lines = ics_unfold_lines(ics_text)
    events: List[Dict[str, str]] = []
    cur: Dict[str, str] = {}
    in_event = False

    for line in lines:
        if line == "BEGIN:VEVENT":
            in_event = True
            cur = {}
            continue
        if line == "END:VEVENT":
            if cur:
                events.append(cur)
            in_event = False
            cur = {}
            continue
        if not in_event:
            continue

        if ":" not in line:
            continue
        k, v = line.split(":", 1)
        k = k.split(";", 1)[0].strip().upper()
        v = v.strip()

        # store first occurrence only (v0)
        if k not in cur:
            cur[k] = v

    out: List[Dict[str, str]] = []
    for e in events:
        title = norm(e.get("SUMMARY", ""))
        url = norm(e.get("URL", "")) or norm(e.get("LOCATION-URL", ""))
        location = norm(e.get("LOCATION", ""))
        desc = norm(e.get("DESCRIPTION", ""))

        dtstart = e.get("DTSTART", "")
        dtend = e.get("DTEND", "")

        d1, t1 = parse_ics_date(dtstart) if dtstart else (None, None)
        d2, t2 = parse_ics_date(dtend) if dtend else (None, None)

        if not d1:
            continue

        time_str = ""
        if t1 and t2:
            time_str = f"{t1}–{t2}"
        elif t1:
            time_str = t1

        out.append(
            {
                "title": title,
                "date": d1 or "",
                "endDate": d2 or "",
                "time": time_str,
                "location": location,
                "url": url,
                "description": desc,
            }
        )
    return out


# -------------------------
# RSS minimal parser
# -------------------------

def parse_rss(xml_text: str) -> List[Dict[str, str]]:
    # Works for RSS/Atom basic fields.
    # WICHTIG: pubDate/updated/published ist i.d.R. Artikel-Datum, NICHT Event-Datum.
    # Event-Datum wird heuristisch aus Titel/Description extrahiert (wenn möglich).
    xml_text = xml_text.strip()
    root = ET.fromstring(xml_text)

    # Atom
    ns = {"atom": "http://www.w3.org/2005/Atom"}
    entries = root.findall(".//atom:entry", ns)
    items = root.findall(".//item")

    out: List[Dict[str, str]] = []

    def parse_date_any(s: str) -> str:
        s = (s or "").strip()
        if not s:
            return ""
        # common: RFC2822 pubDate
        for fmt in ("%a, %d %b %Y %H:%M:%S %z", "%a, %d %b %Y %H:%M:%S %Z"):
            try:
                return datetime.strptime(s, fmt).date().strftime("%Y-%m-%d")
            except Exception:
                pass
        # ISO
        try:
            return datetime.fromisoformat(s.replace("Z", "+00:00")).date().strftime("%Y-%m-%d")
        except Exception:
            return ""

    month_map = {
        "januar": 1, "jan": 1,
        "februar": 2, "feb": 2,
        "maerz": 3, "märz": 3, "mrz": 3,
        "april": 4, "apr": 4,
        "mai": 5,
        "juni": 6, "jun": 6,
        "juli": 7, "jul": 7,
        "august": 8, "aug": 8,
        "september": 9, "sep": 9,
        "oktober": 10, "okt": 10,
        "november": 11, "nov": 11,
        "dezember": 12, "dez": 12,
    }

# === BEGIN BLOCK: RSS EVENT DATE+LOCATION ENRICHMENT (v2, uses global date-range + loc/time inference) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - RSS-Feeds liefern oft nur "Artikel-Datum" + Text -> wir extrahieren Event-Datum aus Title+Desc
# - Zusätzlich Location/Time aus Text inferieren (Pflichtfeld Location!)
# - Nutzt _extract_date_range_de (unterstützt Ranges wie 15.–16. März 2026)
# Umfang:
# - Ersetzt nur extract_event_date + pack_candidate innerhalb parse_rss()

def extract_event_date(text: str, fallback_year: int) -> Tuple[str, str]:
    """
    Returns (event_date_iso_or_empty, note)
    """
    t = clean_text(text or "")
    if not t:
        return ("", "event_date:missing (no text)")

    d1, d2 = _extract_date_range_de(t, fallback_year=fallback_year)
    if d1:
        if d2 and d2 != d1:
            return (d1, "event_date:range(_extract_date_range_de)")
        return (d1, "event_date:single(_extract_date_range_de)")

    return ("", "event_date:missing (article date only)")

def pack_candidate(title: str, link: str, article_date: str, description: str) -> Dict[str, str]:
    combined = f"{title}\n{description}"
    fallback_year = int(article_date[:4]) if article_date and len(article_date) >= 4 else datetime.now().year
    ev_date, ev_note = extract_event_date(combined, fallback_year)

    # Location/Time aus Text ableiten (wichtig für Pflichtfeld Location)
    loc_guess, time_guess = infer_location_time(clean_text(title), clean_text(description))

    notes = f"article_date={article_date or ''}; {ev_note}"
    return {
        "title": clean_text(title),
        "date": ev_date,                 # Event-Datum (kann leer sein)
        "endDate": "",
        "time": time_guess or "",
        "location": loc_guess or "",
        "url": canonical_url(link),
        "description": clean_text(description),
        "notes": notes,
    }

# === END BLOCK: RSS EVENT DATE+LOCATION ENRICHMENT (v2, uses global date-range + loc/time inference) ===

    if entries:
        for e in entries:
            title = norm(e.findtext("atom:title", default="", namespaces=ns))

            link = ""
            for link_el in e.findall("atom:link", ns):
                rel = (link_el.attrib.get("rel", "") or "").lower()
                href = link_el.attrib.get("href", "") or ""
                if href and (rel in ("", "alternate")):
                    link = href
                    break

            updated = norm(e.findtext("atom:updated", default="", namespaces=ns))
            published = norm(e.findtext("atom:published", default="", namespaces=ns))
            article_date = parse_date_any(published) or parse_date_any(updated)

            summary = norm(e.findtext("atom:summary", default="", namespaces=ns))
            content = norm(e.findtext("atom:content", default="", namespaces=ns))
            desc = summary or content or ""

            out.append(pack_candidate(title, link, article_date, desc))

    if items:
        # handle content namespace sometimes used by RSS feeds
        content_ns = {"content": "http://purl.org/rss/1.0/modules/content/"}

        for it in items:
            title = norm(it.findtext("title", default=""))
            link = norm(it.findtext("link", default=""))
            pub = norm(it.findtext("pubDate", default=""))
            article_date = parse_date_any(pub)

            desc = norm(it.findtext("description", default=""))
            encoded = ""
            try:
                encoded = norm(it.findtext("content:encoded", default="", namespaces=content_ns))
            except Exception:
                encoded = ""

            full_desc = encoded or desc or ""
            out.append(pack_candidate(title, link, article_date, full_desc))

    # WICHTIG: Nicht mehr nach "date" filtern – RSS/Artikel können ohne Event-Datum in die Inbox,
    # damit du sie in der PWA manuell datieren kannst.
    return [x for x in out if x.get("title")]
# === BEGIN BLOCK: BOCHOLT HTML CALENDAR PARSER (veranstaltungskalender) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Liest https://www.bocholt.de/veranstaltungskalender (HTML)
# - Extrahiert aus der Ergebnisliste: Titel, Datum, Zeit, (optionale) Location, Detail-URL
# Umfang:
# - Nur Listenseite (keine Detailseiten-Fetches), bewusst robust/konservativ
# === END BLOCK: BOCHOLT HTML CALENDAR PARSER (veranstaltungskalender) ===
# === BEGIN BLOCK: GENERIC HTML EVENT PARSER (jsonld + feed autodiscovery, v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - HTML-Quellen generisch nutzbar machen, ohne Source-spezifische Parser:
#   1) JSON-LD / schema.org Events aus <script type="application/ld+json">
#   2) Feed/ICS Autodiscovery aus <link rel="alternate"> + .ics Links im HTML
#   3) Nachgeladene Feeds werden mit bestehendem RSS/ICS Parser geparst
# Umfang:
# - Nur Parse-Helfer; keine Änderung an Dedupe/Inbox-Write-Logik
# === END BLOCK: GENERIC HTML EVENT PARSER (jsonld + feed autodiscovery, v1) ===

_JSONLD_SCRIPT_RE = re.compile(
    r"<script[^>]+type=['\"]application/ld\+json['\"][^>]*>(.*?)</script>",
    re.IGNORECASE | re.DOTALL,
)

_LINK_ALT_RE = re.compile(r"<link\s+[^>]*>", re.IGNORECASE)
_HREF_RE = re.compile(r"href=['\"]([^'\"]+)['\"]", re.IGNORECASE)
_REL_RE = re.compile(r"rel=['\"]([^'\"]+)['\"]", re.IGNORECASE)
_TYPE_RE = re.compile(r"type=['\"]([^'\"]+)['\"]", re.IGNORECASE)

def _as_list(x):
    if x is None:
        return []
    if isinstance(x, list):
        return x
    return [x]

def _jsonld_iter_objs(obj):
    """
    Flacht JSON-LD-Strukturen ab (dict/list, @graph, etc.) und liefert dict-Objekte.
    """
    if isinstance(obj, dict):
        yield obj
        if isinstance(obj.get("@graph"), list):
            for it in obj["@graph"]:
                yield from _jsonld_iter_objs(it)
    elif isinstance(obj, list):
        for it in obj:
            yield from _jsonld_iter_objs(it)

def _jsonld_is_event(d: dict) -> bool:
    t = d.get("@type") or d.get("type")
    types = []
    if isinstance(t, str):
        types = [t]
    elif isinstance(t, list):
        types = [str(x) for x in t if x]
    types = [norm_key(x) for x in types]
    return any("event" == x or x.endswith(":event") or x.endswith("/event") for x in types)

def _jsonld_pick_str(d: dict, *keys: str) -> str:
    for k in keys:
        if k in d and d.get(k) is not None:
            v = d.get(k)
            if isinstance(v, str):
                return v
            if isinstance(v, (int, float)):
                return str(v)
    return ""

def _jsonld_location(d: dict) -> str:
    loc = d.get("location")
    if isinstance(loc, str):
        return clean_text(loc)
    if isinstance(loc, dict):
        name = clean_text(_jsonld_pick_str(loc, "name"))
        addr = loc.get("address")
        addr_s = ""
        if isinstance(addr, str):
            addr_s = clean_text(addr)
        elif isinstance(addr, dict):
            street = clean_text(_jsonld_pick_str(addr, "streetAddress"))
            plz = clean_text(_jsonld_pick_str(addr, "postalCode"))
            city = clean_text(_jsonld_pick_str(addr, "addressLocality"))
            parts = [p for p in [street, (plz + " " + city).strip()] if p]
            addr_s = ", ".join(parts).strip()
        if name and addr_s and norm_key(addr_s) not in norm_key(name):
            return f"{name}, {addr_s}"
        return name or addr_s
    return ""

def parse_html_jsonld_events(html_text: str, base_url: str) -> List[Dict[str, str]]:
    """
    Extrahiert schema.org Event aus JSON-LD.
    """
    # === BEGIN BLOCK: JSONLD PARSER HARDEN (no-throw, v1) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck:
    # - Stabilität: JSON-LD Parsing darf keine Exceptions nach außen werfen
    # - Verhindert Parse-failed durch Edge-Cases in einzelnen Quellen
    # Umfang:
    # - Umhüllt die komplette Extraktion in try/except und liefert immer List[Dict]
    # === END BLOCK: JSONLD PARSER HARDEN (no-throw, v1) ===
    try:
        html_text = html_text or ""
        out: List[Dict[str, str]] = []

        for m in _JSONLD_SCRIPT_RE.finditer(html_text):
            raw = (m.group(1) or "").strip()
            if not raw:
                continue
            try:
                data = json.loads(raw)
            except Exception:
                continue

            try:
                objs = list(_jsonld_iter_objs(data))
            except Exception:
                objs = []

            for obj in (objs or []):
                if not isinstance(obj, dict):
                    continue
                try:
                    if not _jsonld_is_event(obj):
                        continue
                except Exception:
                    continue

                title = clean_text(_jsonld_pick_str(obj, "name", "headline"))
                start = _jsonld_pick_str(obj, "startDate", "startDateTime", "start")
                end = _jsonld_pick_str(obj, "endDate", "endDateTime", "end")

                d1 = _iso_date_part(start)
                d2 = _iso_date_part(end)

                t1 = _iso_time_part(start)
                t2 = _iso_time_part(end)

                time_str = ""
                if t1 and t2 and (d2 == d1 or not d2):
                    time_str = f"{t1}–{t2}"
                elif t1:
                    time_str = t1

                url = _normalize_event_url(_jsonld_pick_str(obj, "url"), base_url)

                try:
                    loc = _jsonld_location(obj)
                except Exception:
                    loc = ""

                desc = clean_text(_jsonld_pick_str(obj, "description"))

                if not title:
                    continue

                out.append(
                    {
                        "title": title,
                        "date": d1 or "",
                        "endDate": d2 or "",
                        "time": time_str,
                        "location": loc,
                        "url": url,
                        "description": desc,
                        "notes": "source=html_jsonld",
                    }
                )

                if len(out) >= int(os.environ.get("MAX_HTML_EVENTS", "80")):
                    return out

        return out
    except Exception:
        return []

    return out

def discover_feeds_from_html(html_text: str, base_url: str) -> List[str]:
    """
    Findet RSS/Atom/ICS Kandidaten in HTML:
    - <link rel="alternate" type="application/rss+xml|application/atom+xml|text/calendar" href="...">
    - direkte .ics / webcal / ?ical=1 Links
    """
    html_text = html_text or ""
    found: List[str] = []

    # 1) <link ...>
    for tag in _LINK_ALT_RE.findall(html_text):
        rel_m = _REL_RE.search(tag)
        type_m = _TYPE_RE.search(tag)
        href_m = _HREF_RE.search(tag)

        rel = norm_key(rel_m.group(1)) if rel_m else ""
        typ = norm_key(type_m.group(1)) if type_m else ""
        href = norm(href_m.group(1)) if href_m else ""

        if not href:
            continue
        if "alternate" not in rel:
            continue

        if ("rss+xml" in typ) or ("atom+xml" in typ) or ("text/calendar" in typ) or ("ics" in typ):
            u = _normalize_event_url(href, base_url)
            if u and u not in found:
                found.append(u)

    # 2) direkte Links (.ics / webcal / ?ical=1)
    for href in re.findall(r"href=['\"]([^'\"]+)['\"]", html_text, flags=re.IGNORECASE):
        h = norm(href)
        if not h:
            continue
        hk = norm_key(h)
        if hk.startswith("mailto:") or hk.startswith("tel:") or hk.startswith("javascript:"):
            continue

        if hk.startswith("webcal://"):
            h = "https://" + h[len("webcal://"):]
        if hk.endswith(".ics") or ("?ical=1" in hk) or ("&ical=1" in hk) or ("format=ical" in hk):
            u = _normalize_event_url(h, base_url)
            if u and u not in found:
                found.append(u)

    # Begrenzen: wir testen maximal 3 Feeds pro HTML-Quelle
    return found[:3]

# === BEGIN BLOCK: GENERIC HTML EVENT PARSER (jsonld + feeds + fallback date scan + KuKuG, v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - HTML-Quellen besser nutzbar machen:
#   1) JSON-LD / schema.org Events
#   2) Feed/ICS Autodiscovery
#   3) Fallback: Date-Scan (dd.mm.yyyy / dd.mm. / dd. Monat) + Link/Titel
#   4) KuKuG Spezial: /themen-tipps/ -> /portfolio-items/ Detailseiten
# Umfang:
# - Ersetzt nur die generische HTML-Parsing-Strecke (keine Dedupe/Inbox-Write Änderungen)
# === END BLOCK: GENERIC HTML EVENT PARSER (jsonld + feeds + fallback date scan + KuKuG, v2) ===

_HTML_A_RE = re.compile(r"<a\b[^>]*href=['\"]([^'\"]+)['\"][^>]*>(.*?)</a>", re.IGNORECASE | re.DOTALL)

# === BEGIN BLOCK: DATE EXTRACTION (DE, start+end, ranges + iso + slash, v3) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Liefert (startDate, endDate) als ISO (YYYY-MM-DD)
# - Unterstützt Multi-Day-Formate (Ranges) + ISO + Slash + klassische DE-Formate
# Umfang:
# - Ersetzt ausschließlich die Funktion _extract_event_date_de
# === END BLOCK: DATE EXTRACTION (DE, start+end, ranges + iso + slash, v3) ===
def _extract_event_date_de(text: str, fallback_year: int) -> Tuple[str, str]:
    # === BEGIN BLOCK: _extract_event_date_de IMPLEMENTATION (v3) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck: Siehe Header-Block "DATE EXTRACTION (DE, start+end, ranges + iso + slash, v3)"
    # Umfang: Vollständige Implementierung der Funktion (keine externen Seiteneffekte)
    # === END BLOCK: _extract_event_date_de IMPLEMENTATION (v3) ===
    t = norm(text)
    if not t:
        return ("", "")

    month_map = {
        "januar": 1, "jan": 1,
        "februar": 2, "feb": 2,
        "maerz": 3, "märz": 3, "mrz": 3,
        "april": 4, "apr": 4,
        "mai": 5,
        "juni": 6, "jun": 6,
        "juli": 7, "jul": 7,
        "august": 8, "aug": 8,
        "september": 9, "sep": 9,
        "oktober": 10, "okt": 10,
        "november": 11, "nov": 11,
        "dezember": 12, "dez": 12,
    }

    def _iso(yyyy: int, mm: int, dd: int) -> str:
        return date(yyyy, mm, dd).strftime("%Y-%m-%d")

    # 0) ISO: yyyy-mm-dd
    m = re.search(r"(?<!\d)(\d{4})-(\d{2})-(\d{2})(?!\d)", t)
    if m:
        yyyy = int(m.group(1))
        mm = int(m.group(2))
        dd = int(m.group(3))
        try:
            d = _iso(yyyy, mm, dd)
            return (d, d)
        except Exception:
            return ("", "")

    # 0b) Slash: dd/mm/yyyy or dd/mm/yy
    m = re.search(r"(?<!\d)(\d{1,2})/(\d{1,2})/(\d{2}|\d{4})(?!\d)", t)
    if m:
        dd = int(m.group(1))
        mm = int(m.group(2))
        yy = m.group(3)
        yyyy = int(yy) if len(yy) == 4 else (2000 + int(yy))
        try:
            d = _iso(yyyy, mm, dd)
            return (d, d)
        except Exception:
            return ("", "")

    # 1) Range: "15.-16.03.2026" / "15.–16.03.2026" / "15. bis 16.03.2026"
    m = re.search(
        r"(?<!\d)(\d{1,2})\.\s*(?:-|–|bis)\s*(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})(?!\d)",
        t,
        flags=re.IGNORECASE,
    )
    if m:
        d1 = int(m.group(1))
        d2 = int(m.group(2))
        mm = int(m.group(3))
        yy = m.group(4)
        yyyy = int(yy) if len(yy) == 4 else (2000 + int(yy))
        try:
            s = _iso(yyyy, mm, d1)
            e = _iso(yyyy, mm, d2)
            return (s, e)
        except Exception:
            return ("", "")

    # 2) Range: "15.–16. März 2026" / "15. bis 16. März 2026"
    m = re.search(
        r"(?<!\d)(\d{1,2})\.\s*(?:-|–|bis)\s*(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*(\d{4})(?!\d)",
        t,
        flags=re.IGNORECASE,
    )
    if m:
        d1 = int(m.group(1))
        d2 = int(m.group(2))
        mon = norm_key(m.group(3))
        yyyy = int(m.group(4))
        mm = month_map.get(mon, 0)
        if mm:
            try:
                s = _iso(yyyy, mm, d1)
                e = _iso(yyyy, mm, d2)
                return (s, e)
            except Exception:
                return ("", "")

    # 3) dd.mm.yyyy or dd.mm.yy
    m = re.search(r"(?<!\d)(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})(?!\d)", t)
    if m:
        dd = int(m.group(1))
        mm = int(m.group(2))
        yy = m.group(3)
        yyyy = int(yy) if len(yy) == 4 else (2000 + int(yy))
        try:
            d = _iso(yyyy, mm, dd)
            return (d, d)
        except Exception:
            return ("", "")

    # 4) dd.mm. (year inferred)
    m = re.search(r"(?<!\d)(\d{1,2})\.(\d{1,2})\.(?!\d)", t)
    if m:
        dd = int(m.group(1))
        mm = int(m.group(2))
        try:
            d = _iso(fallback_year, mm, dd)
            return (d, d)
        except Exception:
            return ("", "")

    # 5) dd. Monat yyyy / dd. Monat (year inferred)
    m = re.search(r"(?<!\d)(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*(\d{4})?(?!\d)", t)
    if m:
        dd = int(m.group(1))
        mon = norm_key(m.group(2))
        yyyy = int(m.group(3)) if m.group(3) else fallback_year
        mm = month_map.get(mon, 0)
        if mm:
            try:
                d = _iso(yyyy, mm, dd)
                return (d, d)
            except Exception:
                return ("", "")

    return ("", "")

def _html_link_candidates_date_scan(html_text: str, base_url: str) -> List[Dict[str, str]]:
    """
    Sehr konservativer Fallback:
    - sammelt <a href>text</a>
    - versucht Datum aus naher Umgebung (Anchor-Text + kleiner Kontext) zu ziehen
    - lässt Klassifizierung später entscheiden (classify_candidate)
    """
    html_text = html_text or ""
    fallback_year = datetime.now().year
    out: List[Dict[str, str]] = []

    # === BEGIN BLOCK: JUBOH PAGINATION PREFETCH (programm pages) ===
    # Ziel: /programm hat mehrere Seiten (1..5). Wir holen sie vorab und scannen alle.
    try:
        bu = urlparse(base_url)
        if (bu.netloc or "").lower().endswith("juboh.de") and (bu.path or "").lower().startswith("/programm"):
            max_pages = int(os.environ.get("MAX_JUBOH_PAGES", "5"))
            seen = {base_url}
            extra_html_parts: List[str] = []

            # Links auf Folgeseiten sind auf /programm typischerweise die Ziffern "2","3","4","5"
            for mm in _HTML_A_RE.finditer(html_text):
                href2 = norm(mm.group(1))
                txt2 = clean_text(mm.group(2))
                if not href2 or not txt2 or not txt2.isdigit():
                    continue

                u2 = _normalize_event_url(href2, base_url)
                if not u2 or u2 in seen:
                    continue

                pu2 = urlparse(u2)
                if (pu2.netloc or "").lower().endswith("juboh.de") and (pu2.path or "").lower().startswith("/programm"):
                    seen.add(u2)

            # deterministisch und begrenzt
            for u2 in list(sorted(seen))[:max_pages]:
                if u2 == base_url:
                    continue
                try:
                    extra_html_parts.append(safe_fetch(u2, timeout=20))
                except Exception:
                    continue

            if extra_html_parts:
                html_text = html_text + "\n" + "\n".join(extra_html_parts)
    except Exception:
        pass
    # === END BLOCK: JUBOH PAGINATION PREFETCH (programm pages) ===
    
    # === BEGIN BLOCK: DETAIL FETCH BUDGET (missing_date enrichment + per-host cap override, v2) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck:
    # - Begrenzter, sicherer Detailseiten-Fetch nur zur Datums-Anreicherung (JSON-LD/Text auf Detailseiten)
    # - Budget + Cache, damit kein Noise/Load-Exploit entsteht
    # - Per-host Cap Override für High-Confidence Quellen (aktuell: juboh.de), damit TASK 4 Backfill sichtbar wirkt
    # Umfang:
    # - Nur lokale Variablen + Helper für _html_link_candidates_date_scan
    detail_fetch_budget = int(os.environ.get("MAX_DETAIL_FETCH", "60"))
    detail_fetch_timeout = int(os.environ.get("DETAIL_FETCH_TIMEOUT", "15"))

    # Default cap
    detail_fetch_host_cap = int(os.environ.get("MAX_DETAIL_FETCH_PER_HOST", "8"))

    # Override caps (nur wenn gesetzt, sonst Default)
    detail_fetch_host_cap_juboh = int(os.environ.get("MAX_DETAIL_FETCH_PER_HOST_JUBOH", str(detail_fetch_host_cap)))

    def cap_for_host(h: str) -> int:
        h = (h or "").lower()
        if h.endswith("juboh.de"):
            return detail_fetch_host_cap_juboh
        return detail_fetch_host_cap

    detail_fetch_count = 0
    detail_fetch_by_host: Dict[str, int] = {}
    detail_html_cache: Dict[str, str] = {}
    # === END BLOCK: DETAIL FETCH BUDGET (missing_date enrichment + per-host cap override, v2) ===

    # Kontext: wir nutzen kurze Fenster um den Link (um Datum/Ort/Uhrzeit mitzunehmen)
    for m in _HTML_A_RE.finditer(html_text):
        href = norm(m.group(1))
        inner = clean_text(m.group(2))
        if not href:
            continue

        hk = norm_key(href)
        if hk.startswith("mailto:") or hk.startswith("tel:") or hk.startswith("javascript:"):
            continue
        if hk.startswith("#"):
            continue

        # === BEGIN BLOCK: JUBOH URL CLASSIFICATION (ONLY /programm/kurs/, no query knr) ===
        # Zweck:
        # - JUBOH: In die Inbox dürfen nur echte Kurs-Detailseiten
        # - Kategorie-/Info-/Navi-Links (Alter, Filter, Pagination etc.) werden verworfen
        # Umfang:
        # - Ersetzt die doppelte/inkonsistente JUBOH-Filterstrecke vollständig
        url = _normalize_event_url(href, base_url)
        if not url:
            continue

        p0 = urlparse(url)
        host0 = (p0.netloc or "").lower()
        path0 = (p0.path or "").lower()

        if host0.endswith("juboh.de"):
            # harte Ausschlüsse
            if "/programm/kategorie/" in path0 or "/info/" in path0:
                continue

            # nur Kurs-Detailseiten sind Events
            if "/programm/kurs/" not in path0:
                continue
        # === END BLOCK: JUBOH URL CLASSIFICATION (ONLY /programm/kurs/, no query knr) ===

               # === BEGIN BLOCK: HTML DATE-SCAN (detail fetch + json-ld Event + gating, v6) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Reduziert review:event_signal_missing_date durch:
        #   1) <time datetime="YYYY-MM-DD..."> im Link-Umfeld
        #   2) (NEU) Detailseiten-Fetch (budgetiert) + JSON-LD Event startDate/endDate
        # - Noise-Kontrolle bleibt: (Datum ODER Event-Signal), Datum-only nur bei event-typischer URL
        # Umfang:
        # - Ersetzt nur den Date-Scan-Teil innerhalb _html_link_candidates_date_scan (Kontext, Extraktion, Gate, out.append)
        # === END BLOCK: HTML DATE-SCAN (detail fetch + json-ld Event + gating, v6) ===

        # === BEGIN BLOCK: HTML LINK CONTEXT WINDOW (dynamic, multi-host boost, v2) ===
        # Ziel: Auf vielen Event-Listen-Seiten stehen Datum/Zeit/Ort nicht direkt am <a>, sondern im Karten-Container.
        # Wir erhöhen daher den Kontext-Puffer hostbasiert, damit Date/Time/Location zuverlässig gefunden werden.
        p_ctx = urlparse(url)
        host_ctx = (p_ctx.netloc or "").lower()

        # Default höher (war 180) – reduziert "kein Datum im Kontext" massiv auf typischen Listen-Layouts.
        pad = 650

        # Sehr “container-lastige” Seiten: noch größer
        if host_ctx.endswith("juboh.de"):
            pad = 1600
        elif host_ctx.endswith("textilwerk-bocholt.lwl.org"):
            pad = 1200
        elif "stadtbibliothek" in host_ctx:
            pad = 1200
        elif "stadtmuseum" in host_ctx:
            pad = 1200
        elif "fabi" in host_ctx:
            pad = 1200
        elif "nabu" in host_ctx:
            pad = 1200
        elif "euregio" in host_ctx:
            pad = 1200
        elif "muensterland" in host_ctx:
            pad = 1200
        elif "isselburg" in host_ctx:
            pad = 1200
        elif "kukug" in host_ctx:
            pad = 1200
        elif "lernwerkstatt" in host_ctx:
            pad = 1200

        start = max(0, m.start() - pad)
        end = min(len(html_text), m.end() + pad)
        ctx_html = html_text[start:end]
        ctx = clean_text(_strip_html_tags(ctx_html))
        # === END BLOCK: HTML LINK CONTEXT WINDOW (dynamic, multi-host boost, v2) ===

# === BEGIN BLOCK: HTML LINK TITLE QUALITY (generic titles + hard skips) ===
        # Zweck:
        # - Titel robust aus Link-Text/Attributen ableiten (inner, title, aria-label, img alt)
        # - Filter/Kategorie/Pagination Links (z.B. "16 Jahre", "2", "Datum", "/kategorie/", "browse=") sind KEINE Events
        # - Host-spezifische Hard-Skips, um Nicht-Events aus bekannten Hosts konsequent rauszufiltern
        # Umfang:
        # - Nur Titel/Combined-Erzeugung + frühe hard-skips in _html_link_candidates_date_scan

        # Titel robust bestimmen (inner -> title/aria-label -> img alt)
        title = clean_text(inner or "")
        if not title:
            try:
                title = clean_text(a.get("title", "") or a.get("aria-label", "") or "")
            except Exception:
                title = ""

        if not title:
            try:
                img = a.find("img")
                if img:
                    title = clean_text(img.get("alt", "") or "")
            except Exception:
                title = ""

        if not title:
            continue

        title_norm = norm_key(title)

        # Hard skip: reine Ziffern / Sort-/Pager-Links
        if title_norm.isdigit():
            continue

        # Hard skip: Alters-/Zielgruppenfilter (z.B. "6 - 7 Jahre", "16 Jahre")
        if re.fullmatch(r"\d+\s*(?:-|–)?\s*\d*\s*jahre", title_norm):
            continue

        # Hard skip: offensichtliche Filterkategorien/Meta
        if title_norm in ("plus", "+ (plus)", "+", "event", "seminar", "vorlesung", "datum"):
            continue

        url_lk = norm_key(url)

        # Hard skip: Kategorien + Listing/Sortier-/Pager-URLs
        if ("/kategorie/" in url_lk) or ("browse=" in url_lk) or ("orderby=" in url_lk) or ("&cHash=" in url_lk.lower()):
            continue

        # === BEGIN BLOCK: HOST HARD SKIPS (reduce non-events, v1) ===
        host_lk = (urlparse(url).netloc or "").lower()
        path_lk = (urlparse(url).path or "").lower()

        # LWL Textilwerk: Utility-Seiten wie "Leichte Sprache" sind keine Events
        if host_lk.endswith("textilwerk-bocholt.lwl.org"):
            if ("/leichte-sprache" in path_lk) or ("leichte sprache" in title_norm):
                continue

        # Münsterland e.V.: nur echte /veranstaltungen/ akzeptieren
        if host_lk.endswith("www.muensterland.com"):
            if "/veranstaltungen/" not in path_lk:
                continue

        # Isselburg: nur Veranstaltungsbereich akzeptieren
        if host_lk.endswith("www.isselburg.de"):
            if "/veranstaltungen" not in path_lk:
                continue

        # KuKuG Programmübersicht: "Programm" Portfolio-Items sind keine Einzeltermine
        if host_lk.endswith("www.kukug-bocholt.de"):
            if ("/portfolio-items/" in path_lk) and ("programm" in (path_lk + " " + title_norm)):
                continue

        # EUREGIO: Listing-Root /agenda/ ist kein Event-Detail
        if host_lk.endswith("www.euregio.eu"):
            if path_lk.rstrip("/") == "/de/agenda":
                continue
        # === END BLOCK: HOST HARD SKIPS (reduce non-events, v1) ===

        # Generische CTA-Titel -> später aus Detailseite reparieren
        needs_title_repair = title_norm in ("mehr erfahren", "details", "weiterlesen")

        combined = f"{title}\n{ctx}"
        # === END BLOCK: HTML LINK TITLE QUALITY (generic titles + hard skips) ===

        # 1) Datum aus Kontext (DE/ISO/Slash + Ranges -> (start,end))
        ev_date, ev_end = _extract_event_date_de(combined, fallback_year)

        # 2) Datum aus HTML-Attributen (sehr sicher): datetime="YYYY-MM-DD..."
        if not ev_date:
            iso_dates = re.findall(
                r"datetime\s*=\s*['\"](\d{4}-\d{2}-\d{2})(?:[T\s][^'\"]*)?['\"]",
                ctx_html,
                flags=re.IGNORECASE,
            )
            iso_dates = [d for d in iso_dates if d]
            if iso_dates:
                iso_dates = sorted(set(iso_dates))
                ev_date = iso_dates[0]
                ev_end = iso_dates[-1] if len(iso_dates) > 1 else iso_dates[0]

        event_signal = bool(_EVENT_SIGNAL_RE.search(combined))
        url_key = norm_key(url)
        url_seems_event = bool(
            re.search(r"/(event|events|veranstalt|veranstaltungen|termin|termine|kalender|programm|agenda)\b", url_key)
        )

        # === BEGIN BLOCK: DETAIL HTML DATE ENRICHMENT (budgeted fetch + json-ld + text + listing->detail, v3-correctness) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Korrektheit-first: Auf listing-like Seiten KEIN Text-Fallback für Datum auf der Listing-URL selbst.
        #   (Text-Fallback nur auf Detailseiten oder wenn JSON-LD eindeutig ist.)
        # - Listing->Detail bleibt aktiv: wenn Listing kein Datum liefert, wird EIN Detail-Link nachgezogen und dort Datum extrahiert.
        # - Noise/Load-Kontrolle: budgetiert + per-host cap + cache; maximal 1 zusätzlicher Detail-Fetch pro Kandidat.
        # Umfang:
        # - Ersetzt ausschließlich diesen Block innerhalb _html_link_candidates_date_scan.

        host = (urlparse(url).netloc or "").lower()
        host_fetches = detail_fetch_by_host.get(host, 0)

        # === BEGIN BLOCK: DETAIL HTML INIT (fix unbound detail_html, v1) ===
        detail_html = ""
        # === END BLOCK: DETAIL HTML INIT (fix unbound detail_html, v1) ===

        path = urlparse(url).path.lower()
        is_listing_like = (
            "/kategorie/" in path
            or "/category/" in path
            or "/kalender/" in path
            or "/monat/" in path
            or "/wochen" in path
            or "/tage" in path
            or "/programm/kategorie" in path
        )

        def _extract_date_from_html(_html_text: str, *, allow_text_fallback: bool) -> Tuple[str, str]:
            # 1) JSON-LD (Event/Course/EducationEvent/CourseInstance) startDate/endDate
            scripts = re.findall(
                r"<script[^>]+type=['\"]application/ld\+json['\"][^>]*>(.*?)</script>",
                _html_text,
                flags=re.IGNORECASE | re.DOTALL,
            )

            def _iter_ld_nodes(obj):
                if isinstance(obj, list):
                    for it in obj:
                        yield from _iter_ld_nodes(it)
                elif isinstance(obj, dict):
                    yield obj
                    if "@graph" in obj:
                        yield from _iter_ld_nodes(obj.get("@graph"))

            def _is_relevant_type(tval) -> bool:
                # accept Event / Course / EducationEvent / CourseInstance (and prefixed variants)
                if isinstance(tval, str):
                    k = norm_key(tval)
                    return (
                        k.endswith("event")
                        or k.endswith(":event")
                        or k.endswith("course")
                        or k.endswith(":course")
                        or "educationevent" in k
                        or "courseinstance" in k
                    )
                if isinstance(tval, list):
                    return any(_is_relevant_type(x) for x in tval)
                return False

            def _pick_dates(sd_raw: str, ed_raw: str) -> Tuple[str, str]:
                sd = norm(sd_raw)
                ed = norm(ed_raw)
                if sd:
                    sd = sd.split("T")[0].split(" ")[0]
                if ed:
                    ed = ed.split("T")[0].split(" ")[0]
                if re.fullmatch(r"\d{4}-\d{2}-\d{2}", sd or ""):
                    if re.fullmatch(r"\d{4}-\d{2}-\d{2}", ed or ""):
                        return (sd, ed)
                    return (sd, sd)
                return ("", "")

            for raw in scripts:
                raw = (raw or "").strip()
                if not raw:
                    continue
                try:
                    data = json.loads(raw)
                except Exception:
                    continue

                for node in _iter_ld_nodes(data):
                    if not isinstance(node, dict):
                        continue
                    if not _is_relevant_type(node.get("@type")):
                        continue

                    # direct startDate/endDate
                    d1, d2 = _pick_dates(str(node.get("startDate") or ""), str(node.get("endDate") or ""))
                    if d1:
                        return (d1, d2)

                    # Course instances (common pattern for Kursseiten)
                    for key in ("hasCourseInstance", "courseInstance", "subEvent", "event"):
                        inst = node.get(key)
                        if not inst:
                            continue
                        if isinstance(inst, dict):
                            d1, d2 = _pick_dates(str(inst.get("startDate") or ""), str(inst.get("endDate") or ""))
                            if d1:
                                return (d1, d2)
                        elif isinstance(inst, list):
                            for it in inst:
                                if not isinstance(it, dict):
                                    continue
                                d1, d2 = _pick_dates(str(it.get("startDate") or ""), str(it.get("endDate") or ""))
                                if d1:
                                    return (d1, d2)

            # 1b) <time datetime="..."> (often present even without JSON-LD)
            for m_time in re.finditer(r"<time[^>]+datetime=['\"]([^'\"]+)['\"]", _html_text or "", flags=re.IGNORECASE):
                d1, d2 = _pick_dates(m_time.group(1), "")
                if d1:
                    return (d1, d2)

            # 2) Fallback: Datum aus sichtbarem Text via bestehender Funktion (nur wenn erlaubt)
            if allow_text_fallback:
                detail_text = clean_text(_strip_html_tags(_html_text))
                detail_combined = f"{title}\n{detail_text[:20000]}"
                d_sd, d_ed = _extract_event_date_de(detail_combined, fallback_year)
                if d_sd:
                    return (d_sd, d_ed or d_sd)

            return ("", "")

        # === BEGIN BLOCK: REMOVE LOCAL _extract_time_loc_from_html SHADOW (v1) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Fix: verhindert Shadowing der globalen Helper-Funktion _extract_time_loc_from_html(...)
        # - Bugfix: vermeidet 'unexpected keyword argument title' aus lokalen nested Funktionen
        # Umfang:
        # - Lokale (nested) Definition entfernt; Aufrufe nutzen ab jetzt die globale Funktion
        # === END BLOCK: REMOVE LOCAL _extract_time_loc_from_html SHADOW (v1) ===

    return out

def _kukug_expand_portfolio_links(html_text: str, base_url: str) -> List[str]:
    """
    KuKuG: Programmübersicht listet oft nur Teaser.
    Wir sammeln /portfolio-items/ Links und geben sie zum Nachladen zurück.
    """
    html_text = html_text or ""
    links: List[str] = []
    for href in re.findall(r"href=['\"]([^'\"]+)['\"]", html_text, flags=re.IGNORECASE):
        h = norm(href)
        if not h:
            continue
        hk = norm_key(h)
        if "/portfolio-items/" not in hk:
            continue
        u = _normalize_event_url(h, base_url)
        if u and u not in links:
            links.append(u)
    return links[:10]

def parse_html_generic_events(html_text: str, base_url: str) -> List[Dict[str, str]]:
    # === BEGIN BLOCK: HTML GENERIC PARSER HARDEN (no-throw, v1) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck:
    # - Stabilität: generisches HTML-Parsing darf keine Exceptions nach außen werfen
    # - Verhindert Parse-failed durch "'NoneType' object is not iterable" in Edge-Cases
    # Umfang:
    # - Defensive Guards (or []) und try/except an kritischen Stellen
    # === END BLOCK: HTML GENERIC PARSER HARDEN (no-throw, v1) ===
    try:
        html_text = html_text or ""
        base_url = norm(base_url)

        # 1) JSON-LD Events
        out = parse_html_jsonld_events(html_text, base_url) or []
        if out:
            return out

        # 2) KuKuG Spezial: Detailseiten nachladen und dort JSON-LD/DateScan anwenden
        if ("/themen-tipps" in norm_key(base_url)) or ("kukug" in norm_key(base_url)):
            detail_urls = _kukug_expand_portfolio_links(html_text, base_url) or []
            collected: List[Dict[str, str]] = []
            for du in detail_urls:
                try:
                    detail_html = safe_fetch(du)
                except Exception:
                    continue

                d = []
                try:
                    d = parse_html_jsonld_events(detail_html, du) or []
                except Exception:
                    d = []
                if not d:
                    try:
                        d = _html_link_candidates_date_scan(detail_html, du) or []
                    except Exception:
                        d = []

                for c in (d or []):
                    c["notes"] = clean_text((c.get("notes", "") + f"; via=portfolio:{du}")[:180])
                    collected.append(c)

                if len(collected) >= int(os.environ.get("MAX_HTML_EVENTS", "80")):
                    break

            if collected:
                return collected

        # 3) Feed/ICS Autodiscovery → nachladen und als RSS/ICS parsen
        try:
            feed_urls = discover_feeds_from_html(html_text, base_url) or []
        except Exception:
            feed_urls = []

        collected2: List[Dict[str, str]] = []
        for fu in feed_urls:
            try:
                feed_text = safe_fetch(fu)
            except Exception:
                continue

            fk = norm_key(fu)
            parsed: List[Dict[str, str]] = []
            try:
                if fk.endswith(".ics") or ("text/calendar" in fk) or ("format=ical" in fk) or ("?ical=1" in fk) or ("&ical=1" in fk):
                    parsed = parse_ics_events(feed_text) or []
                else:
                    parsed = parse_rss(feed_text) or []
            except Exception:
                parsed = []

            for c in (parsed or []):
                c["notes"] = clean_text((c.get("notes", "") + f"; source=html_feed:{fu}")[:180])
                collected2.append(c)

            if len(collected2) >= int(os.environ.get("MAX_HTML_EVENTS", "80")):
                break

        if collected2:
            return collected2

        # 4) Fallback: Date-Scan
        try:
            return _html_link_candidates_date_scan(html_text, base_url) or []
        except Exception:
            return []
    except Exception:
        return []

    # 4) Fallback: Date-Scan
    return _html_link_candidates_date_scan(html_text, base_url)
# === BEGIN BLOCK: BOCHOLT VERANSTALTUNGSKALENDER HTML PARSER (v2) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Parst https://www.bocholt.de/veranstaltungskalender (Listenansicht) in Kandidaten
# - Unterstützt:
#   a) Einzeltermine mit Wochentag: "Freitag, 27. Februar, 18:00 Uhr - 20:30 Uhr, Ort"
#   b) Serien/Ranges: "Mehrere Termine vom 03. - 31. März ... | Dienstags zwischen ..."
# - Extrahiert: title, date (start ISO), endDate (optional ISO), time (optional), location (optional), url
# Umfang:
# - Nur Listenseite (keine Detailseiten-Fetches)
# - Konservativ: keine "date" => kein Candidate (für diese Quelle)
# === END BLOCK: BOCHOLT VERANSTALTUNGSKALENDER HTML PARSER (v2) ===

_BOCHOLT_WEEKDAYS = (
    "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag"
)

_BOCHOLT_MONTHS = {
    # DE
    "januar": 1,
    "februar": 2,
    "märz": 3,
    "maerz": 3,
    "april": 4,
    "mai": 5,
    "juni": 6,
    "juli": 7,
    "august": 8,
    "september": 9,
    "oktober": 10,
    "november": 11,
    "dezember": 12,
    # EN (falls mal EN-Ansicht/Fragmente auftauchen)
    "january": 1,
    "february": 2,
    "march": 3,
    "april": 4,
    "may": 5,
    "june": 6,
    "july": 7,
    "august": 8,
    "september": 9,
    "october": 10,
    "november": 11,
    "december": 12,
}

def _strip_html_tags(s: str) -> str:
    s = s or ""
    s = re.sub(r"<\s*br\s*/?\s*>", " ", s, flags=re.IGNORECASE)
    s = re.sub(r"<[^>]+>", " ", s)
    return clean_text(s)

# === BEGIN BLOCK: BOCHOLT INFER YEAR + PARSE SINGLE DATE (hotfix v1) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Fix: _bocholt_infer_year fehlte -> NameError im Bocholt-Kalender-Parser
# - Liefert ein plausibles Jahr für Monatsangaben (kalendernah, über Jahreswechsel robust)
# Umfang:
# - Ersetzt nur _bocholt_parse_date_from_text() und fügt _bocholt_infer_year() direkt davor ein
# === END BLOCK: BOCHOLT INFER YEAR + PARSE SINGLE DATE (hotfix v1) ===

def _bocholt_infer_year(month: int) -> int:
    """
    Inferiert das Jahr für einen Monatsnamen ohne Jahrangabe.
    Heuristik:
    - Wenn der Monat bereits "hinter" dem aktuellen Monat liegt (z.B. Januar bei aktuellem Monat Dezember),
      dann ist es sehr wahrscheinlich nächstes Jahr.
    - Sonst aktuelles Jahr.
    """
    try:
        today = date.today()
        if month < (today.month - 1):  # kleiner Puffer für Runs am Monatsanfang
            return today.year + 1
        return today.year
    except Exception:
        return date.today().year

def _bocholt_parse_date_from_text(t: str) -> str:
    # Beispiel: "Freitag, 20. Februar,"
    m = re.search(
        r"(?:%s)\s*,\s*(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)" % "|".join(_BOCHOLT_WEEKDAYS),
        t,
        flags=re.IGNORECASE,
    )
    if not m:
        return ""
    day = int(m.group(1))
    mon_name = norm(m.group(2)).lower()
    mon_name = mon_name.replace("ä", "ae").replace("ö", "oe").replace("ü", "ue")
    month = _BOCHOLT_MONTHS.get(mon_name, 0)
    if not month:
        return ""
    year = _bocholt_infer_year(month)
    try:
        return date(year, month, day).isoformat()
    except Exception:
        return ""

def _bocholt_parse_date_range_from_text(t: str) -> Tuple[str, str]:
    """
    Unterstützt u.a.:
    - "Mehrere Termine vom 03. - 31. März"
    - "Mehrere Termine vom 27. - 27. Februar"
    - "Mehrere Termine from 04. - 25. March"
    """
    t = clean_text(t)

    # Range mit Monat nur einmal (am Ende)
    m = re.search(
        r"(?:Mehrere\s+Termine\s+(?:vom|from))\s*(\d{1,2})\.\s*-\s*(\d{1,2})\.?\s*([A-Za-zÄÖÜäöüß]+)",
        t,
        flags=re.IGNORECASE,
    )
    if m:
        d1 = int(m.group(1))
        d2 = int(m.group(2))
        mon_name = norm(m.group(3)).lower()
        mon_name = mon_name.replace("ä", "ae").replace("ö", "oe").replace("ü", "ue")
        month = _BOCHOLT_MONTHS.get(mon_name, 0)
        if month:
            year = _bocholt_infer_year(month)
            try:
                start = date(year, month, d1).isoformat()
                end = date(year, month, d2).isoformat()
                return start, end
            except Exception:
                return "", ""

    # Range mit Monat auf beiden Seiten (seltener)
    m = re.search(
        r"(?:Mehrere\s+Termine\s+(?:vom|from))\s*(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*-\s*(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)",
        t,
        flags=re.IGNORECASE,
    )
    if m:
        d1 = int(m.group(1))
        m1n = norm(m.group(2)).lower().replace("ä", "ae").replace("ö", "oe").replace("ü", "ue")
        d2 = int(m.group(3))
        m2n = norm(m.group(4)).lower().replace("ä", "ae").replace("ö", "oe").replace("ü", "ue")
        m1 = _BOCHOLT_MONTHS.get(m1n, 0)
        m2 = _BOCHOLT_MONTHS.get(m2n, 0)
        if m1 and m2:
            y1 = _bocholt_infer_year(m1)
            y2 = _bocholt_infer_year(m2)
            try:
                start = date(y1, m1, d1).isoformat()
                end = date(y2, m2, d2).isoformat()
                return start, end
            except Exception:
                return "", ""

    return "", ""

def _bocholt_parse_time_from_text(t: str) -> str:
    # nimmt die erste Uhrzeit (HH:MM) als time
    m = re.search(r"\b(\d{1,2}:\d{2})\s*Uhr\b", t)
    if m:
        hhmm = m.group(1)
        if len(hhmm) == 4:  # "9:00"
            hhmm = "0" + hhmm
        return hhmm
    # "ab 20:00 Uhr"
    m = re.search(r"\bab\s*(\d{1,2}:\d{2})\s*Uhr\b", t, flags=re.IGNORECASE)
    if m:
        hhmm = m.group(1)
        if len(hhmm) == 4:
            hhmm = "0" + hhmm
        return hhmm
    return ""

def _bocholt_parse_location_from_text(t: str) -> str:
    # häufig:
    # - "... 20:00 Uhr - 22:30 Uhr, Kulturort Alte Molkerei ..."
    # - "... ab 20:00 Uhr Innenstadt"
    # - "... | Treffpunkt: Weberei"
    m = re.search(r"Uhr\s*(?:-\s*\d{1,2}:\d{2}\s*Uhr\s*)?,\s*([^,]{3,120})", t)
    if m:
        return clean_text(m.group(1))

    m = re.search(r"\bab\s*\d{1,2}:\d{2}\s*Uhr\s+([^,|]{3,120})", t, flags=re.IGNORECASE)
    if m:
        return clean_text(m.group(1))

    m = re.search(r"\bTreffpunkt\s*:\s*([^|,]{3,120})", t, flags=re.IGNORECASE)
    if m:
        return clean_text(m.group(1))

    m = re.search(r"\bMeeting\s+point\s*:\s*([^|,]{3,120})", t, flags=re.IGNORECASE)
    if m:
        return clean_text(m.group(1))

    # sehr konservativer Fallback: "... 09:00 Uhr - 18:30 Uhr Innenstadt"
    m = re.search(r"Uhr\s*(?:-\s*\d{1,2}:\d{2}\s*Uhr\s*)?\s+(Innenstadt)\b", t)
    if m:
        return clean_text(m.group(1))

    return ""

def _bocholt_cleanup_title(t: str) -> str:
    # Entfernt doppeltes Genre-Präfix: "Wirtschaft Wirtschaft <Title>"
    t = clean_text(t)
    m = re.match(r"^(.{3,40}?)\s+\1\s+(.*)$", t)
    if m:
        return clean_text(m.group(2))
    return t

def _bocholt_extract_title(inner_txt: str) -> str:
    """
    Titel sitzt in der Listenansicht meistens vor:
    - "Wochentag, ..." oder
    - "Mehrere Termine ..."
    """
    t = clean_text(inner_txt)

    # vor "Mehrere Termine ..."
    m = re.search(r"\bMehrere\s+Termine\b", t, flags=re.IGNORECASE)
    if m and m.start() > 2:
        return _bocholt_cleanup_title(t[: m.start()])

    # vor "Freitag, ..." etc.
    m = re.search(r"(?:%s)\s*," % "|".join(_BOCHOLT_WEEKDAYS), t)
    if m and m.start() > 2:
        return _bocholt_cleanup_title(t[: m.start()])

    return _bocholt_cleanup_title(t)

def parse_bocholt_calendar_html(html_text: str, base_url: str) -> List[Dict[str, str]]:
    html_text = html_text or ""

    anchors = re.findall(
        r"<a\b([^>]*?)>(.*?)</a>",
        html_text,
        flags=re.IGNORECASE | re.DOTALL,
    )

    out: List[Dict[str, str]] = []
    seen: set = set()

    for attrs, inner in anchors:
        inner_txt = _strip_html_tags(inner)
        if not inner_txt:
            continue

        # href extrahieren
        href = ""
        mh = re.search(r'href\s*=\s*["\']([^"\']+)["\']', attrs, flags=re.IGNORECASE)
        if mh:
            href = norm(mh.group(1))
        url = _normalize_event_url(href, base_url) if href else canonical_url(base_url)

        # 1) Datum: entweder Einzeltermin (Wochentag) oder Range ("Mehrere Termine ...")
        d_iso = _bocholt_parse_date_from_text(inner_txt)
        end_iso = ""

        if not d_iso:
            d_iso, end_iso = _bocholt_parse_date_range_from_text(inner_txt)

        if not d_iso:
            continue  # diese Quelle bleibt konservativ: ohne Datum kein Candidate

        # === BEGIN BLOCK: BOCHOLT CALENDAR LIST PARSE (indent fix + sanitizer, v1) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Fix: Indentation/Syntax im Bocholt-HTML-Listenparser
        # - Fix: fp/seen Scope bleibt innerhalb des anchor-loops
        # - Beibehalt: Location-Sanitizer (force detail enrichment)
        # Umfang:
        # - Ersetzt nur den Abschnitt ab time_str..out.append(...)
        time_str = _bocholt_parse_time_from_text(inner_txt)
        loc = _bocholt_parse_location_from_text(inner_txt)
        title = _bocholt_extract_title(inner_txt)

        if not title:
            continue

        # === BEGIN BLOCK: BOCHOLT LOCATION SANITIZER (force detail enrichment, v1) ===
        # Zweck:
        # - Listen-Location ist häufig Kategorie/Title-Mix -> führt zu falschen Pflichtfeldern
        # - Bei "noisy" Locations: bewusst leeren, damit später Detail-Enrichment Location/Time sauber füllt
        # Umfang:
        # - Nur für Bocholt-Kalender-Parser; keine globalen Heuristiken
        if loc:
            loc = clean_text(str(loc)).strip(' "\'')
            loc_l = loc.lower()
            title_l = title.lower()

            # Kategorie-/Teaser-Wörter und typische Noise-Signale
            if "ausflugstipps" in loc_l:
                loc = ""

            # Wenn Location sehr lang ist, ist es meist ein zusammengesetzter Teaser
            if loc and len(loc) > 55:
                loc = ""

            # Wenn Location den Titel (oder große Teile) enthält -> sehr wahrscheinlich vermischt
            if loc and (title_l[:24] in loc_l or loc_l[:24] in title_l):
                loc = ""

            # Wenn Location zu viele Satzzeichen/Trenner enthält -> oft "Ort + Programmtitel"
            if loc and (loc.count(" - ") >= 1 or loc.count(" & ") >= 2 or loc.count(":") >= 2):
                loc = ""
        # === END BLOCK: BOCHOLT LOCATION SANITIZER (force detail enrichment, v1) ===

        fp = (slugify(title), d_iso, end_iso, norm_key(url))
        if fp in seen:
            continue
        seen.add(fp)

        out.append(
            {
                "title": title,
                "date": d_iso,
                "endDate": end_iso,
                "time": time_str,
                "location": loc,
                "url": url,
                "description": "",
                "notes": "source=bocholt_veranstaltungskalender_html",
            }
        )
        # === END BLOCK: BOCHOLT CALENDAR LIST PARSE (indent fix + sanitizer, v1) ===

        if len(out) >= int(os.environ.get("MAX_HTML_EVENTS", "80")):
            break

    return out 
# === BEGIN BLOCK: JSON PARSER (events API -> candidates) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Unterstützt JSON-Eventlisten (z.B. bocholt.de/api/events)
# - Mappt auf Candidate-Felder: title/date/endDate/time/location/url/description/notes
# Umfang:
# - Neue Funktion parse_json + kleine Helpers
# === END BLOCK: JSON PARSER (events API -> candidates) ===

def _pick(obj: dict, *keys: str) -> str:
    for k in keys:
        if k in obj and obj.get(k) is not None:
            return str(obj.get(k))
    return ""


def _normalize_event_url(u: str, base_url: str) -> str:
    # === BEGIN BLOCK: URL NORMALIZATION (unquote + html-unescape) ===
    # Zweck:
    # - Repariert doppelt encodierte hrefs wie "&amp%3B..." (JUBOH)
    # - Erst danach canonical_url anwenden, damit Query korrekt wird
    # Umfang:
    # - Ersetzt vollständig _normalize_event_url
    u = norm(u)
    if not u:
        return ""

    # 1) %-Decoding (z.B. %3B -> ;) und danach HTML-Unescape (&amp; -> &)
    try:
        u = _html.unescape(unquote(u))
    except Exception:
        # wenn unquote/unescape scheitern, weiter mit Rohwert
        pass

    u = norm(u)
    if not u:
        return ""

    if u.startswith("http://") or u.startswith("https://"):
        return canonical_url(u)

    # relative -> host aus base_url
    try:
        p = urlparse(base_url)
        return canonical_url(f"{p.scheme}://{p.netloc}{u if u.startswith('/') else '/' + u}")
    except Exception:
        return canonical_url(u)
    # === END BLOCK: URL NORMALIZATION (unquote + html-unescape) ===


def _iso_date_part(iso_dt: str) -> str:
    s = norm(iso_dt)
    if not s:
        return ""
    # "2026-03-10T19:30:00+01:00" -> "2026-03-10"
    if len(s) >= 10 and s[4] == "-" and s[7] == "-":
        return s[:10]
    return ""


def _iso_time_part(iso_dt: str) -> str:
    s = norm(iso_dt)
    if "T" not in s:
        return ""
    t = s.split("T", 1)[1]
    # "19:30:00+01:00" -> "19:30"
    if len(t) >= 5 and t[2] == ":":
        return t[:5]
    return ""


def parse_json(text: str, base_url: str) -> List[Dict[str, str]]:
    try:
        data = json.loads(text)
    except Exception:
        return []

    # Akzeptiere: list, oder dict mit events/items/results
    items: List[dict] = []
    if isinstance(data, list):
        items = [x for x in data if isinstance(x, dict)]
    elif isinstance(data, dict):
        for k in ("events", "items", "results", "data"):
            v = data.get(k)
            if isinstance(v, list):
                items = [x for x in v if isinstance(x, dict)]
                break

    out: List[Dict[str, str]] = []

    for ev in items:
        title = clean_text(_pick(ev, "title", "name", "summary"))
        start = _pick(ev, "startDate", "start", "from", "dateStart", "dtstart")
        end = _pick(ev, "endDate", "end", "to", "dateEnd", "dtend")

        d1 = _iso_date_part(start) or norm(_pick(ev, "date"))
        d2 = _iso_date_part(end)

        t1 = _iso_time_part(start)
        t2 = _iso_time_part(end)

        time_str = ""
        if t1 and t2 and d2 == d1:
            time_str = f"{t1}–{t2}"
        elif t1:
            time_str = t1

        url = _normalize_event_url(_pick(ev, "url", "link", "href", "eventUrl"), base_url)

        # location kann string oder dict sein
        loc = ""
        loc_obj = ev.get("location")
        if isinstance(loc_obj, dict):
            loc = clean_text(_pick(loc_obj, "name", "title", "label"))
            # optional: adresse anhängen, falls vorhanden und nicht redundant
            addr = clean_text(_pick(loc_obj, "address", "street", "fullAddress"))
            if addr and addr.lower() not in (loc.lower(),):
                loc = f"{loc}, {addr}" if loc else addr
        elif isinstance(loc_obj, str):
            loc = clean_text(loc_obj)

        desc = clean_text(_pick(ev, "description", "details", "text", "content"))
        notes = clean_text(_pick(ev, "notes", "source", "sourceNote"))

        if not title:
            continue

        out.append(
            {
                "title": title,
                "date": d1 or "",
                "endDate": d2 or "",
                "time": time_str,
                "location": loc,
                "url": url,
                "description": desc,
                "notes": notes,
            }
        )

    return out

# === END BLOCK: JSON PARSER (events API -> candidates) ===



# -------------------------
# Google Sheets helpers
# -------------------------

def get_sheet_service() -> object:
    sheet_id = os.environ.get("SHEET_ID")
    sa_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON")
    if not sheet_id:
        fail("ENV SHEET_ID fehlt.")
    if not sa_json:
        fail("ENV GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")

    try:
        sa_info = json.loads(sa_json)
    except Exception:
        fail("GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON.")

    scopes = ["https://www.googleapis.com/auth/spreadsheets"]
    creds = service_account.Credentials.from_service_account_info(sa_info, scopes=scopes)
    return build("sheets", "v4", credentials=creds, cache_discovery=False)


def read_tab(service: object, sheet_id: str, tab_name: str) -> List[List[str]]:
    rng = f"{tab_name}!A:ZZ"
    res = service.spreadsheets().values().get(spreadsheetId=sheet_id, range=rng).execute()
    return res.get("values", []) or []


def append_rows(service: object, sheet_id: str, tab_name: str, rows: List[List[str]]) -> None:
    body = {"values": rows}
    service.spreadsheets().values().append(
        spreadsheetId=sheet_id,
        range=f"{tab_name}!A1",
        valueInputOption="RAW",
        insertDataOption="INSERT_ROWS",
        body=body,
    ).execute()


def batch_update_rows(service: object, sheet_id: str, updates: List[Dict[str, object]]) -> None:
    """
    updates = [{"range": "Inbox!A2:Q2", "values": [[...]]}, ...]
    """
    if not updates:
        return
    body = {
        "valueInputOption": "RAW",
        "data": updates,
    }
    service.spreadsheets().values().batchUpdate(
        spreadsheetId=sheet_id,
        body=body,
    ).execute()


# (duplicate batch_update_rows removed)

def sheet_rows_to_dicts(values: List[List[str]]) -> Tuple[List[str], List[Dict[str, str]]]:
    if not values:
        return ([], [])
    header = [norm(h) for h in values[0]]
    dicts: List[Dict[str, str]] = []
    for row in values[1:]:
        d: Dict[str, str] = {}
        for i, h in enumerate(header):
            d[h] = norm(row[i]) if i < len(row) else ""
        # ignore empty rows
        if any(v for v in d.values()):
            dicts.append(d)
    return (header, dicts)


# -------------------------
# Dedupe / matching (v0)
# -------------------------

# === BEGIN BLOCK: DEDUPE INDEXES (canonical url + slug+date with url-fallback) ===
# Datei: scripts/discovery-to-inbox.py
# Zweck:
# - Dedupe bleibt stabil auch wenn RSS/Artikel-Kandidaten kein Event-Datum haben.
# - Fallback: wenn date leer ist, wird canonical candidate-url als dritter Fingerprint-Teil genutzt.
# Umfang:
# - Ersetzt nur die Fingerprint/Index-Helfer.
# === END BLOCK: DEDUPE INDEXES (canonical url + slug+date with url-fallback) ===
def build_live_index(live_events: List[Dict[str, str]]) -> Tuple[Dict[str, str], Dict[Tuple[str, str], str]]:
    by_url: Dict[str, str] = {}
    by_slug_date: Dict[Tuple[str, str], str] = {}

    for ev in live_events:
        ev_id = ev.get("id", "")
        url = canonical_url(ev.get("url", ""))
        title = norm(ev.get("title", ""))
        d = norm(ev.get("date", ""))

        if url:
            by_url[norm_key(url)] = ev_id
        if title and d:
            by_slug_date[(slugify(title), d)] = ev_id

    return by_url, by_slug_date


def _fp_third(date_str: str, candidate_url: str) -> str:
    d = norm(date_str)
    if d:
        return d
    u = canonical_url(candidate_url)
    return norm_key(u) if u else ""


def inbox_fingerprint(row: Dict[str, str]) -> Tuple[str, str, str]:
    return (
        norm_key(canonical_url(row.get("source_url", ""))),
        slugify(row.get("title", "")),
        _fp_third(row.get("date", ""), row.get("url", "")),
    )


def make_candidate_fingerprint(c: Dict[str, str], source_url: str) -> Tuple[str, str, str]:
    return (
        norm_key(canonical_url(source_url)),
        slugify(c.get("title", "")),
        _fp_third(c.get("date", ""), c.get("url", "")),
    )
# === END BLOCK: DEDUPE INDEXES (canonical url + slug+date with url-fallback) ===




# -------------------------
# Main discovery flow (v0)
# -------------------------

def main() -> None:
    sheet_id = os.environ.get("SHEET_ID", "")
    service = get_sheet_service()

    info("Lese Tabs aus Google Sheet …")
    events_values = read_tab(service, sheet_id, TAB_EVENTS)
    inbox_values = read_tab(service, sheet_id, TAB_INBOX)
    sources_values = read_tab(service, sheet_id, TAB_SOURCES)

    _, live_events = sheet_rows_to_dicts(events_values)
    _, inbox_rows = sheet_rows_to_dicts(inbox_values)
    sources_header, sources_rows = sheet_rows_to_dicts(sources_values)

    if not sources_header:
        fail("Tab 'Sources' ist leer oder hat keine Headerzeile.")

    # Inbox header sanity (optional but helpful)
    inbox_header = [norm(h) for h in (inbox_values[0] if inbox_values else [])]
    if inbox_header and inbox_header[: len(INBOX_COLUMNS)] != INBOX_COLUMNS:
        info("Hinweis: Inbox-Header weicht ab. Script schreibt trotzdem spaltenweise in definierter Reihenfolge.")

    live_by_url, live_by_title_date = build_live_index(live_events)

    existing_inbox_fps = set(inbox_fingerprint(r) for r in inbox_rows)

         # === BEGIN BLOCK: INBOX BACKFILL PASS (missing fields via detail fetch, JUBOH kurs, v1) ===
    # Zweck:
    # - Systematisches Nachpflegen bereits vorhandener Inbox-Zeilen (v.a. fehlendes Datum),
    #   unabhängig davon, ob die URL im aktuellen Source-Fetch erneut als Candidate auftaucht.
    # - Start: nur High-Confidence JUBOH Kursdetailseiten.
    # - Erweiterung: Zeit + Location bevorzugt aus JSON-LD (falls vorhanden), sonst Text-Inference.
    # Umfang:
    # - Ersetzt nur diesen Backfill-Pass (keine Änderungen an Budgets/Host-Caps für Source-Fetch)
    inbox_backfill_updates: List[Dict[str, object]] = []
    inbox_backfill_count = 0

    backfill_budget = int(os.environ.get("MAX_DETAIL_FETCH", "60"))
    backfill_timeout = int(os.environ.get("DETAIL_FETCH_TIMEOUT", "15"))
    backfill_host_cap = int(os.environ.get("MAX_DETAIL_FETCH_PER_HOST", "8"))

    backfill_count = 0
    backfill_by_host: Dict[str, int] = {}
    backfill_html_cache: Dict[str, str] = {}

    def _backfill_extract_dates_from_html(_html_text: str) -> Tuple[str, str]:
        """
        Returns (start_date_iso, end_date_iso) or ("","") if nothing found.
        Strategy:
        1) JSON-LD startDate/endDate (Event/Course-like nodes)
        2) fallback: German date parsing on visible text (best-effort)
        """
        html = _html_text or ""

        # 1) JSON-LD
        scripts = re.findall(
            r"<script[^>]+type=['\"]application/ld\+json['\"][^>]*>(.*?)</script>",
            html,
            flags=re.IGNORECASE | re.DOTALL,
        )

        def _iter_ld_nodes(obj):
            if isinstance(obj, list):
                for it in obj:
                    yield from _iter_ld_nodes(it)
            elif isinstance(obj, dict):
                yield obj
                if "@graph" in obj:
                    yield from _iter_ld_nodes(obj.get("@graph"))

        def _is_relevant_type(tval) -> bool:
            if isinstance(tval, str):
                k = norm_key(tval)
                return (k.endswith("event") or k.endswith("course") or "educationevent" in k)
            if isinstance(tval, list):
                return any(_is_relevant_type(x) for x in tval)
            return False

        best_start = ""
        best_end = ""

        for s in scripts:
            try:
                obj = json.loads(s)
            except Exception:
                continue
            for node in _iter_ld_nodes(obj):
                if not isinstance(node, dict):
                    continue
                if not _is_relevant_type(node.get("@type")):
                    continue

                sd = norm(str(node.get("startDate", "") or ""))
                ed = norm(str(node.get("endDate", "") or ""))

                # normalize ISO-ish to yyyy-mm-dd (ignore time)
                if sd:
                    sd = _iso_date_part(sd)
                if ed:
                    ed = _iso_date_part(ed)

                if sd and not best_start:
                    best_start = sd
                    best_end = ed or sd
                if best_start:
                    break
            if best_start:
                break

        if best_start:
            return (best_start, best_end)

        # 2) Fallback: sichtbarer Text (konservativ: nur Anfang der Seite)
        plain = clean_text(_strip_html_tags(html[:20000]))
        fy = datetime.now().year
        d1, d2 = _extract_event_date_de(plain, fy)
        return (d1, d2)

    def _backfill_extract_time_loc_from_html(_html_text: str) -> Tuple[str, str]:
        """
        Returns (time_str, location_str)
        - time_str as "HH:MM" or "HH:MM–HH:MM"
        - location_str as best-effort name/address
        """
        html = _html_text or ""
        scripts = re.findall(
            r"<script[^>]+type=['\"]application/ld\+json['\"][^>]*>(.*?)</script>",
            html,
            flags=re.IGNORECASE | re.DOTALL,
        )

        def _iter_ld_nodes(obj):
            if isinstance(obj, list):
                for it in obj:
                    yield from _iter_ld_nodes(it)
            elif isinstance(obj, dict):
                yield obj
                if "@graph" in obj:
                    yield from _iter_ld_nodes(obj.get("@graph"))

        def _is_relevant_type(tval) -> bool:
            # accept Event/Course-like nodes
            if isinstance(tval, str):
                k = norm_key(tval)
                return (k.endswith("event") or k.endswith("course") or "educationevent" in k)
            if isinstance(tval, list):
                return any(_is_relevant_type(x) for x in tval)
            return False

        # try JSON-LD first
        for s in scripts:
            try:
                obj = json.loads(s)
            except Exception:
                continue

            for node in _iter_ld_nodes(obj):
                if not isinstance(node, dict):
                    continue
                if not _is_relevant_type(node.get("@type")):
                    continue

                # time: from startDate/endDate if they include time
                sd = norm(str(node.get("startDate", "") or ""))
                ed = norm(str(node.get("endDate", "") or ""))
                t1 = _infer_time_from_text(sd)
                t2 = _infer_time_from_text(ed)
                time_str = t1
                if t1 and t2 and t2 != t1:
                    time_str = f"{t1}–{t2}"

                # location: name + address best-effort
                loc_obj = node.get("location")
                loc_str = ""
                if isinstance(loc_obj, dict):
                    loc_name = clean_text(str(loc_obj.get("name", "") or ""))
                    addr = loc_obj.get("address")
                    addr_str = ""
                    if isinstance(addr, dict):
                        addr_parts = [
                            clean_text(str(addr.get("streetAddress", "") or "")),
                            clean_text(str(addr.get("postalCode", "") or "")),
                            clean_text(str(addr.get("addressLocality", "") or "")),
                        ]
                        addr_str = " ".join([p for p in addr_parts if p]).strip()
                    elif isinstance(addr, str):
                        addr_str = clean_text(addr)

                    loc_str = " - ".join([p for p in [loc_name, addr_str] if p]).strip()
                elif isinstance(loc_obj, str):
                    loc_str = clean_text(loc_obj)

                if time_str or loc_str:
                    return (time_str, loc_str)

        # fallback: infer from text
        plain = clean_text(_strip_html_tags(html[:8000]))
        loc2, time2 = infer_location_time("", plain)
        return (time2, loc2)

    for row_idx, r in enumerate(inbox_rows, start=2):
        cur_url = norm(r.get("url", ""))
        if not cur_url:
            continue

        cur_date = norm(r.get("date", ""))
        if cur_date:
            continue

        try:
            pu = urlparse(norm(cur_url))
            host = (pu.netloc or "").lower()
            path = (pu.path or "").lower()
        except Exception:
            continue

        is_juboh_kurs = host.endswith("juboh.de") and ("/programm/kurs/" in path)
        if not is_juboh_kurs:
            continue

        if backfill_count >= backfill_budget:
            break

        host_fetches = backfill_by_host.get(host, 0)
        if host_fetches >= backfill_host_cap:
            continue

        if cur_url not in backfill_html_cache:
            try:
                backfill_html_cache[cur_url] = safe_fetch(cur_url, timeout=backfill_timeout)
            except Exception:
                backfill_html_cache[cur_url] = ""
            backfill_count += 1
            backfill_by_host[host] = host_fetches + 1

        html = backfill_html_cache.get(cur_url, "") or ""
        if not html:
            continue

        d1, d2 = _backfill_extract_dates_from_html(html)
        if not d1:
            continue

        # nur leere Felder füllen
        updated_row: Dict[str, str] = {col: norm(r.get(col, "")) for col in INBOX_COLUMNS}
        changed = False

        def _fill(col: str, val: str) -> None:
            nonlocal changed
            if (not norm(updated_row.get(col, ""))) and norm(val):
                updated_row[col] = norm(val)
                changed = True

        _fill("date", d1)
        _fill("endDate", d2)

        # optional: time/location nur wenn leer
        time2, loc2 = _backfill_extract_time_loc_from_html(html)
        _fill("time", time2)
        _fill("location", loc2)

        if changed:
            inbox_backfill_updates.append(
                {
                    "range": f"{TAB_INBOX}!A{row_idx}:Q{row_idx}",
                    "values": [[updated_row.get(col, "") for col in INBOX_COLUMNS]],
                }
            )
            inbox_backfill_count += 1
    # === END BLOCK: INBOX BACKFILL PASS (missing fields via detail fetch, JUBOH kurs, v1) ===
    existing_inbox_fp_to_row: Dict[Tuple[str, str, str], int] = {}
    existing_inbox_fp_to_rowdata: Dict[Tuple[str, str, str], Dict[str, str]] = {}

    # Hinweis: wir nehmen an, dass inbox_rows die Sheet-Reihenfolge ohne Lücken abbildet.
    # Header ist Zeile 1, erste Datenzeile ist Zeile 2.
    for i, r in enumerate(inbox_rows, start=2):
        fp = inbox_fingerprint(r)
        if fp not in existing_inbox_fp_to_row:
            existing_inbox_fp_to_row[fp] = i
            existing_inbox_fp_to_rowdata[fp] = r

    enabled_sources = [s for s in sources_rows if norm(s.get("enabled", "")).upper() in ("TRUE", "1", "YES", "JA")]
    info(f"Sources enabled: {len(enabled_sources)}")

    run_ts = now_iso()

    new_inbox_rows: List[List[str]] = []
    health_rows: List[List[str]] = []
    candidate_log_rows: List[List[str]] = []

    total_candidates = 0
    total_written = 0



    for s in enabled_sources:
        source_name = norm(s.get("source_name", s.get("name", ""))) or "Source"
        stype = norm(s.get("type", "")).lower()
        url = norm(s.get("url", ""))
        default_city = norm(s.get("default_city", "")) or "Bocholt"
        default_cat = norm(s.get("default_category", ""))

        if not url or not stype:
            info(f"Skip source (missing type/url): {source_name}")
            continue

        info(f"Fetch: {source_name} ({stype})")

             # === BEGIN BLOCK: FETCH + PARSE DISPATCH + HEALTH LOGGING (v5 CONSOLIDATED) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - EINMALIGER Dispatch (kein Duplikat)
        # - Bocholt Kalender: erst Spezialparser, dann Generic-HTML
        # - Health pro Quelle loggen (ok / fetch_error / parse_error / unsupported)
        # Umfang:
        # - Ersetzt den aktuell doppelt vorhandenen Dispatch-Block vollständig
        # === END BLOCK: FETCH + PARSE DISPATCH + HEALTH LOGGING (v5 CONSOLIDATED) ===

        candidates: List[Dict[str, str]] = []
        health_status = "ok"
        http_status = ""
        err_msg = ""
        checked_at = now_iso()

        try:
            content = safe_fetch(url)

            # === BEGIN BLOCK: EMPTY FETCH SURFACES IN SOURCE HEALTH (freeze v1) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - safe_fetch kann bei 503/429/5xx soft "" zurückgeben
            # - Das MUSS in Source_Health sichtbar werden (statt "ok" mit 0 candidates)
            # Umfang:
            # - Nur Guard direkt nach safe_fetch()
            # === END BLOCK: EMPTY FETCH SURFACES IN SOURCE HEALTH (freeze v1) ===
            if not norm(content):
                health_status = "fetch_error"
                http_status = ""
                err_msg = "empty_response_or_soft_fail"
                candidates = []
                raise RuntimeError("soft_fetch_empty")
            # === END BLOCK: EMPTY FETCH SURFACES IN SOURCE HEALTH (freeze v1) ===

            if stype in ("ical", "ics"):
                candidates = parse_ics_events(content)

            elif stype == "rss":
                candidates = parse_rss(content)

            elif stype == "json":
                candidates = parse_json(content, url)

            elif stype == "html":
                # Bocholt-first (deterministisch) + Pagination per POST (pos=1..N)
                if "bocholt.de/veranstaltungskalender" in norm_key(url):
                    pages_cap = int(os.environ.get("MAX_BOCHOLT_PAGES", "23"))
                    per_page = os.environ.get("BOCHOLT_ENTRIES_PER_PAGE", "10")

                    # Page 1 (GET wie bisher)
                    all_candidates: List[Dict[str, str]] = []
                    all_candidates.extend(parse_bocholt_calendar_html(content, url))

                    # max page aus Pagination (name="pos" value="N")
                    pos_vals = [int(x) for x in re.findall(r'name="pos"\s+value="(\d+)"', content)]
                    max_pos = min(max(pos_vals) if pos_vals else 1, pages_cap)

                    # Pages 2..max_pos per POST
                    for pos in range(2, max_pos + 1):
                        page_html = safe_fetch_post(
                            url,
                            {
                                "search": "",
                                "date_from": "",
                                "date_until": "",
                                "entries_per_page": str(per_page),
                                "pos": str(pos),
                            },
                            timeout=20,
                        )
                        if not norm(page_html):
                            break
                        all_candidates.extend(parse_bocholt_calendar_html(page_html, url))

                    # Cross-page dedupe
                    dedup: Dict[Tuple[str, str, str, str], Dict[str, str]] = {}
                    for c in all_candidates:
                        key = (
                            slugify(c.get("title", "")),
                            norm(c.get("date", "")),
                            norm(c.get("endDate", "")),
                            norm_key(c.get("url", "")),
                        )
                        if key not in dedup:
                            dedup[key] = c
                    candidates = list(dedup.values())

                # Fallback: generic HTML (JSON-LD, Feed-Discovery, Date-Scan, KuKuG)
                if not candidates:
                    candidates = parse_html_generic_events(content, url)

            else:
                health_status = "unsupported"
                err_msg = f"unsupported type: {stype}"
                candidates = []

        except urllib.error.HTTPError as e:
            health_status = "fetch_error"
            http_status = str(getattr(e, "code", "") or "")
            err_msg = f"HTTP Error {http_status}: {getattr(e, 'reason', '')}".strip()
            info(f"Parse failed: {source_name}: {e}")
            candidates = []

        except Exception as e:
            health_status = "parse_error"
            err_msg = str(e)
            info(f"Parse failed: {source_name}: {e}")
            candidates = []

        # kurz halten (Sheet + PWA)
        err_msg = clean_text(err_msg)[:180]

        # === BEGIN BLOCK: CANDIDATES NONE GUARD (v1) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Verhindert Pipeline-Crash, falls ein Parser versehentlich None zurückgibt
        # Umfang:
        # - Normalisiert candidates auf Liste direkt vor Stats/Write
        # === END BLOCK: CANDIDATES NONE GUARD (v1) ===
        candidates = candidates or []

        total_candidates += len(candidates)

        # === BEGIN BLOCK: MAIN LOOP SAFETY + DETAIL ENRICH INIT (indent+none-guard, v1) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Fix: korrekte Indentation im Main-Loop (Scope bleibt innerhalb der Source-Schleife)
        # - Fix: candidates darf nie None sein (sonst 'NoneType is not iterable')
        # - Init: Detail-Enrichment Budget/Cache pro Source
        # Umfang:
        # - Nur Main-Loop direkt vor der Kandidaten-Schleife
        # === END BLOCK: MAIN LOOP SAFETY + DETAIL ENRICH INIT (indent+none-guard, v1) ===

        if candidates is None:
            candidates = []

        written_before_source = total_written

        detail_enrich_budget = int(os.environ.get("MAX_DETAIL_ENRICH_MAIN", "30"))
        detail_enrich_timeout = int(os.environ.get("DETAIL_ENRICH_TIMEOUT", "12"))
        detail_enrich_count = 0
        detail_enrich_cache: Dict[str, str] = {}

        for c in candidates:
                       # === BEGIN BLOCK: RSS ARTICLE DATE SAFETY (allow missing event date) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - RSS/Artikel-Kandidaten dürfen ohne Event-Datum in die Inbox (für manuelle Prüfung in der PWA),
            #   statt fälschlich das Artikel-Datum als Event-Datum zu übernehmen.
            # - Fehlendes Event-Datum wird als Hinweis in notes markiert.
            # Umfang:
            # - Ersetzt nur die title/date Guard-Logik.
            # === END BLOCK: RSS ARTICLE DATE SAFETY (allow missing event date) ===
            title = clean_text(c.get("title", ""))
            d = norm(c.get("date", ""))
            if not title:
                continue

                       # === BEGIN BLOCK: CLASSIFY + DESCRIPTION (review/blocked/rejected) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - blocked nur für potentielle Events (unsicher/unvollständig)
            # - rejected ist Müll und wird NICHT in Inbox geschrieben
            # - description immer gefüllt (original oder generiert, rechtlich sauber)
            # Umfang:
            # - ersetzt nur die alte Hard-Skip-Logik + setzt description/status sauber
            # === END BLOCK: CLASSIFY + DESCRIPTION (review/blocked/rejected) ===
            desc_text = clean_text(c.get("description", ""))
            # === BEGIN BLOCK: ENRICH REQUIRED FIELDS (date+location+time via infer + detail fetch fallback, v3) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - Pflichtfelder-Durchsatz erhöhen: missing date/location/time zuerst inferieren, danach budgetierter Detail-Fetch.
            # - Reuse desselben Detail-Fetches für date + location + time (kein zusätzlicher Load).
            # - date/endDate bevorzugt aus JSON-LD/<time datetime>, sonst konservativer Text-Fallback.
            # Umfang:
            # - Setzt nur lokale Variablen d/final_location/final_time (+ ggf. c["endDate"]) und erweitert notes.
            combined_notes = clean_text(c.get("notes", ""))
            raw_location = clean_text(c.get("location", ""))
            raw_time = norm(c.get("time", ""))

            inferred_location, inferred_time = infer_location_time(title, desc_text)

            final_location = raw_location or inferred_location
            final_time = raw_time or inferred_time

            # Detail-Enrichment nur wenn nach allem noch Pflichtfelder fehlen
            c_url = canonical_url(c.get("url", ""))
            need_date = not norm(d)
            need_loc = not norm(final_location)
            need_time = not norm(final_time)

            if (need_date or need_loc or need_time) and c_url and (detail_enrich_count < detail_enrich_budget):
                detail_html = detail_enrich_cache.get(c_url, "")
                if detail_html == "" and c_url not in detail_enrich_cache:
                    try:
                        detail_html = safe_fetch(c_url, timeout=detail_enrich_timeout)
                    except Exception:
                        detail_html = ""
                    detail_enrich_cache[c_url] = detail_html
                    detail_enrich_count += 1

                if detail_html:
                    # 1) date/endDate aus Detailseite (reuse vorhandene Backfill-Extractor)
                    if need_date:
                        try:
                            d1, d2 = _backfill_extract_dates_from_html(detail_html)
                        except Exception:
                            d1, d2 = ("", "")
                        if d1:
                            d = d1
                            # endDate nur setzen, wenn Kandidat bisher keins hatte
                            if not norm(c.get("endDate", "")) and d2:
                                c["endDate"] = d2

                            need_date = False

                    # 2) time/location strukturiert (JSON-LD), dann Textfallback
                    t2, l2 = _extract_time_loc_from_html(detail_html, title=title, allow_text_fallback=True)

                    if need_time and t2:
                        final_time = t2
                        need_time = False

                    if need_loc and l2:
                        final_location = l2
                        need_loc = False

                    if need_loc or need_time:
                        detail_text = clean_text(_strip_html_tags(detail_html))
                        loc_guess, time_guess = infer_location_time(title, detail_text)

                        if need_loc and loc_guess:
                            final_location = loc_guess
                            need_loc = False
                        if need_time and time_guess:
                            final_time = time_guess
                            need_time = False

                    # notes kurz halten
                    combined_notes = clean_text((combined_notes + "; enrich=detail_date_loc_time")[:180])
            # === END BLOCK: ENRICH REQUIRED FIELDS (date+location+time via infer + detail fetch fallback, v3) ===


                        # === BEGIN BLOCK: CANDIDATE CLASSIFY + LOGGING + INBOX WRITE (append Discovery_Candidates, v1) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - Candidate klassifizieren (review/rejected) UND immer in Discovery_Candidates loggen
            # - Auch rejected / already-live / already-inbox werden geloggt (Analyse)
            # - Inbox wird weiterhin NUR für "neue" Kandidaten geschrieben (wie bisher)
            # Umfang:
            # - Ersetzt nur den Bereich classify_candidate -> dedupe -> inbox write
            # === END BLOCK: CANDIDATE CLASSIFY + LOGGING + INBOX WRITE (append Discovery_Candidates, v1) ===
            # === BEGIN BLOCK: CLASSIFY OVERRIDE (JUBOH kurs no_event_signal -> review, v1) ===
            # Zweck:
            # - JUBOH Kursdetailseiten nicht als reject:no_event_signal verlieren (Enrichment/Review ermöglichen)
            # Umfang:
            # - Ersetzt nur den classify_candidate()-Call inkl. minimalem Override direkt danach
            status, reason = classify_candidate(
                stype=stype,
                source_name=source_name,
                source_url=url,
                title=title,
                description=desc_text,
                event_date=d,
            )

            try:
                _pu = urlparse(norm(c.get("url", "")))
                _host = (_pu.netloc or "").lower()
                _path = (_pu.path or "").lower()
                _is_juboh_kurs = _host.endswith("juboh.de") and ("/programm/kurs/" in _path)
            except Exception:
                _is_juboh_kurs = False

            if _is_juboh_kurs and reason == "reject:no_event_signal":
                status, reason = "review", "review:juboh_kurs_missing_event_signal"
            # === END BLOCK: CLASSIFY OVERRIDE (JUBOH kurs no_event_signal -> review, v1) ===)

            # URL normalisieren (für Matching/Dedupe/Log)
            c_url_raw = norm(c.get("url", ""))
            c_url = canonical_url(c_url_raw)

            matched_id = ""
            score = 0.0

            if c_url and norm_key(c_url) in live_by_url:
                matched_id = live_by_url[norm_key(c_url)]
                score = 1.0
            else:
                key = (slugify(title), d)
                if key in live_by_title_date:
                    matched_id = live_by_title_date[key]
                    score = 0.95

            fp = make_candidate_fingerprint(c, url)
            already_live = bool(matched_id)
            already_inbox = fp in existing_inbox_fps

            # Would be written to inbox?
            will_write = (status != "rejected") and (not already_live) and (not already_inbox)

            # Notes (inkl. reason + missing-date Hinweis)
            _base_notes = clean_text(combined_notes)
            combined_notes = (
                _base_notes
                + ("" if not reason else ((" | " if _base_notes else "") + reason))
                + ("" if d else ((" | " if (_base_notes or reason) else "") + "⚠️ event_date_missing"))
            )

            # Log every candidate row (analyse tab)
            candidate_log = {
                "run_ts": run_ts,
                "source_name": source_name,
                "source_type": stype,
                "source_url": url,
                "status": status,
                "reason": reason,
                "is_written_to_inbox": "1" if will_write else "0",
                "is_already_live": "1" if already_live else "0",
                "is_already_inbox": "1" if already_inbox else "0",
                "matched_event_id": matched_id,
                "match_score": f"{score:.2f}",
                "fingerprint": fp,
                "id_suggestion": make_id_suggestion(title, d, norm(c.get("time", "")), url),
                "title": title,
                "event_date": d,
                "endDate": norm(c.get("endDate", "")),
                "time": final_time,
                "city": default_city,
                "location": final_location,
                "kategorie_suggestion": default_cat,
                "url": c_url,
                "notes": combined_notes,
                "created_at": now_iso(),
            }
            # === BEGIN BLOCK: DISCOVERY CANDIDATES SAFE STRING CONVERSION (fix list_value error) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - Google Sheets API akzeptiert keine Python-Listen/Objekte
            # - daher alle Werte hart zu String konvertieren
            # Umfang:
            # - ersetzt nur die candidate_log_rows.append Zeile
            # === END BLOCK: DISCOVERY CANDIDATES SAFE STRING CONVERSION ===

            row_values = []

            for col in DISCOVERY_CANDIDATES_COLUMNS:

                v = candidate_log.get(col, "")

                if v is None:
                    v = ""

                elif isinstance(v, (list, dict, tuple)):
                    v = str(v)

                else:
                    v = str(v)

                row_values.append(v)

            candidate_log_rows.append(row_values)



            # Existing behavior: skip non-new candidates for inbox
            if status == "rejected":
                continue
            if already_live:
                continue
            if already_inbox:
                continue

            # Existing behavior: skip non-new candidates for inbox
            if status == "rejected":
                continue
            if already_live:
                continue

            # === BEGIN BLOCK: INBOX UPDATE POLICY (fill missing fields for existing rows, v1) ===
            # Zweck:
            # - Wenn Kandidat bereits in Inbox ist, aber Felder fehlen (v.a. date), dann in-place updaten statt skippen.
            # - Keine Duplikate erzeugen; Append bleibt nur für echte "neue" Kandidaten.
            # Umfang:
            # - Ersetzt nur den already_inbox-Branch + den Append-Branch in diesem Abschnitt.
            if "inbox_updates" not in locals():
                inbox_updates: List[Dict[str, object]] = []
                inbox_updates_count = 0

            if already_inbox:
                row_idx = existing_inbox_fp_to_row.get(fp, 0)
                old = existing_inbox_fp_to_rowdata.get(fp, {}) or {}

                # nur fehlende Felder füllen (niemals überschreiben, wenn schon gesetzt)
                new_date = d
                new_end = norm(c.get("endDate", ""))
                new_time = final_time
                new_loc = final_location
                new_title = title
                new_url = c_url

                new_desc = ensure_description(
                    title=title,
                    d_iso=d,
                    time_str=final_time,
                    city=default_city,
                    location=final_location,
                    category=default_cat,
                    url=c_url,
                    description_raw=c.get("description", ""),
                )

                changed = False

                def _pick_fill(col: str, val: str) -> str:
                    nonlocal changed
                    cur = norm(old.get(col, ""))
                    v = norm(val)
                    if (not cur) and v:
                        changed = True
                        return v
                    return cur

                if row_idx >= 2:
                    updated_row = {}
                    # bestehende Werte behalten / nur leere füllen
                    for col in INBOX_COLUMNS:
                        updated_row[col] = norm(old.get(col, ""))

                    updated_row["date"] = _pick_fill("date", new_date)
                    updated_row["endDate"] = _pick_fill("endDate", new_end)
                    updated_row["time"] = _pick_fill("time", new_time)
                    updated_row["location"] = _pick_fill("location", new_loc)
                    updated_row["title"] = _pick_fill("title", new_title)
                    updated_row["url"] = _pick_fill("url", new_url)
                    updated_row["description"] = _pick_fill("description", new_desc)

                    if changed:
                        # A..Q entspricht 17 Spalten (INBOX_COLUMNS)
                        inbox_updates.append(
                            {
                                "range": f"{TAB_INBOX}!A{row_idx}:Q{row_idx}",
                                "values": [[updated_row.get(col, "") for col in INBOX_COLUMNS]],
                            }
                        )
                        inbox_updates_count += 1
                        # cache aktualisieren, damit weitere Kandidaten im selben Run konsistent bleiben
                        existing_inbox_fp_to_rowdata[fp] = updated_row

                continue
            # === END BLOCK: INBOX UPDATE POLICY (fill missing fields for existing rows, v1) ===

            # === BEGIN BLOCK: INBOX REQUIRED FIELDS GATE (title+date+url+location, v1) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - Inbox zeigt nur Events, die in der PWA wirklich als Event taugen
            # - Pflichtfelder: title + date + url + location
            # - time bleibt optional
            # Umfang:
            # - Ersetzt nur den "Write to inbox as suggestion"-Append-Block in diesem Abschnitt
            if not (norm(title) and norm(d) and norm(c_url) and norm(final_location)):
                continue
            # === END BLOCK: INBOX REQUIRED FIELDS GATE (title+date+url+location, v1) ===

            # Write to inbox as suggestion (nur neue Kandidaten)
            row = {
                "status": status,
                "id_suggestion": candidate_log.get("id_suggestion", ""),
                "title": title,
                "date": d,
                "endDate": norm(c.get("endDate", "")),
                "time": final_time,
                "city": default_city,
                "location": final_location,
                "kategorie_suggestion": default_cat,
                "url": c_url,
                "description": ensure_description(
                    title=title,
                    d_iso=d,
                    time_str=final_time,
                    city=default_city,
                    location=final_location,
                    category=default_cat,
                    url=c_url,
                    description_raw=c.get("description", ""),
                ),
                "source_name": source_name,
                "source_url": url,
                "match_score": f"{score:.2f}",
                "matched_event_id": "",
                "notes": combined_notes,
                "created_at": now_iso(),
            }

            new_inbox_rows.append([row.get(col, "") for col in INBOX_COLUMNS])
            existing_inbox_fps.add(fp)
            total_written += 1


        # === BEGIN BLOCK: SOURCE HEALTH APPEND (after processing source) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Health-Zeile finalisieren (inkl. new_rows_written je Quelle)
        # Umfang:
        # - Append in health_rows (Sheet-Write passiert am Ende)
        # === END BLOCK: SOURCE HEALTH APPEND (after processing source) ===
        per_source_new = total_written - written_before_source

        health_row = {
            "source_name": source_name,
            "type": stype,
            "url": url,
            "status": health_status,
            "http_status": http_status,
            "error": err_msg,
            "last_checked_at": checked_at,
            "candidates_count": str(len(candidates)),
            "new_rows_written": str(per_source_new),
        }
        health_rows.append([health_row.get(col, "") for col in SOURCE_HEALTH_COLUMNS])

        
        # Gentle rate-limit to be nice to sources
        time.sleep(1)

    info(f"Candidates parsed: {total_candidates}")
    info(f"New inbox rows: {total_written}")

    if new_inbox_rows:
        append_rows(service, sheet_id, TAB_INBOX, new_inbox_rows)
        info("✅ Inbox updated.")
    else:
        info("✅ Nothing new to add.")

    if inbox_backfill_updates:
        batch_update_rows(service, sheet_id, inbox_backfill_updates)
        info(f"✅ Inbox backfilled (updated rows): {inbox_backfill_count}")

    if "inbox_updates" in locals() and inbox_updates:
        batch_update_rows(service, sheet_id, inbox_updates)
        info(f"✅ Inbox backfilled (updated rows): {locals().get('inbox_updates_count', 0)}")

    # === BEGIN BLOCK: WRITE DISCOVERY CANDIDATES (append, non-blocking) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck:
    # - Alle Kandidaten (review/rejected/deduped) in Tab "Discovery_Candidates" appenden,
    #   um Analyse/Debugging ohne "blocked" Status zu ermöglichen.
    # Umfang:
    # - Non-blocking: Inbox/Events sollen nicht scheitern, falls Tab fehlt
    # === END BLOCK: WRITE DISCOVERY CANDIDATES (append, non-blocking) ===
    if candidate_log_rows:
        try:
            append_rows(service, sheet_id, TAB_DISCOVERY_CANDIDATES, candidate_log_rows)
            info("✅ Discovery_Candidates updated.")
        except Exception as e:
            info(f"⚠️ Discovery_Candidates update failed: {e}")

    # === BEGIN BLOCK: WRITE SOURCE HEALTH (append) ===
    # Datei: scripts/discovery-to-inbox.py
    # Zweck:
    # - Health-Status pro Quelle in Tab "Source_Health" appenden
    # Umfang:
    # - Non-blocking: Inbox/Events sollen nicht scheitern, falls Health-Tab fehlt
    # === END BLOCK: WRITE SOURCE HEALTH (append) ===
    if health_rows:
        try:
            append_rows(service, sheet_id, TAB_SOURCE_HEALTH, health_rows)
            info("✅ Source_Health updated.")
        except Exception as e:
            info(f"⚠️ Source_Health update failed: {e}")

    return


if __name__ == "__main__":
    main()
