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
# - Source-Health Tab ergänzen (Monitoring, kein Einfluss auf Events/Inbox).
# Umfang:
# - Ersetzt nur die TAB_* Konstanten.
# === END BLOCK: SHEET TAB CONFIG (ENV override, test-safe) ===
TAB_EVENTS = os.environ.get("TAB_EVENTS", "Events")
TAB_INBOX = os.environ.get("TAB_INBOX", "Inbox")
TAB_SOURCES = os.environ.get("TAB_SOURCES", "Sources")
TAB_SOURCE_HEALTH = os.environ.get("TAB_SOURCE_HEALTH", "Source_Health")



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
    r"\btrinkwasser\b",
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
EVENT_SIGNAL_PATTERNS = [
    r"\bkonzert\b",
    r"\bjazz\b",
    r"\bklassik\b",
    r"\bopen\s*air\b",
    r"\bfestival\b",
    r"\bshow\b",
    r"\btheater\b",
    r"\bmusical\b",
    r"\bkabarett\b",
    r"\bcomedy\b",
    r"\blesung\b",
    r"\bpoetry\s*slam\b",
    r"\bslam\b",
    r"\bvortrag\b",
    r"\bseminar\b",
    r"\bworkshop\b",
    r"\bf\u00fchrung\b",
    r"\bstadtf\u00fchrung\b",
    r"\brundgang\b",
    r"\btour\b",
    r"\bausstellung\b",
    r"\bvernissage\b",
    r"\bmarkt\b",
    r"\bflohmarkt\b",
    r"\bkirmes\b",
    r"\bfest\b",
    r"\bparty\b",
    r"\bsport\b",
    r"\blauf\b",
    r"\bturnier\b",
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
from urllib.parse import urlparse, urlunparse, parse_qsl, urlencode


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

_TIME_IN_TITLE_RE = re.compile(r"\bum\s*(\d{1,2})(?:[:\.](\d{2}))?\s*uhr\b", re.IGNORECASE)
_SLASH_SEG_RE = re.compile(r"\s*//\s*")

def _infer_time_from_text(text: str) -> str:
    m = _TIME_IN_TITLE_RE.search(text or "")
    if not m:
        return ""
    hh = int(m.group(1))
    mm = int(m.group(2)) if m.group(2) else 0
    return f"{hh:02d}:{mm:02d}"

def _infer_location_from_title(title: str) -> str:
    # presse-service Titel hat oft: "<Titel> // <Ort/Adresse> // ..."
    parts = [p.strip() for p in _SLASH_SEG_RE.split(title or "") if p.strip()]
    if len(parts) >= 2:
        # Teil 2 ist meist Location/Adresse
        loc = parts[1]
        # Zu generisch? dann leer lassen
        if len(loc) >= 3 and loc.lower() not in ("eintritt frei",):
            return loc
    return ""

def _infer_location_from_description(desc: str) -> str:
    d = (desc or "").strip()
    if not d:
        return ""
    # einfache, robuste Muster: "im LernWerk", "im Schloss Raesfeld", "in Preen’s Hoff"
    m = re.search(r"\b(im|in)\s+([A-ZÄÖÜ][^.,;]{2,80})", d)
    if m:
        loc = m.group(2).strip()
        # abschneiden, wenn Satz weiterläuft
        loc = re.split(r"\s+(am|um|ab|von|für|mit)\b", loc)[0].strip()
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

# === END BLOCK: DISCOVERY FILTER HELPERS (junk skip + date window) ===


_NON_EVENT_RE = re.compile("|".join(f"(?:{p})" for p in NON_EVENT_PATTERNS), re.IGNORECASE)
_EVENT_SIGNAL_RE = re.compile("|".join(f"(?:{p})" for p in EVENT_SIGNAL_PATTERNS), re.IGNORECASE)

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
def classify_candidate(
    *,
    stype: str,
    source_name: str,
    source_url: str,
    title: str,
    description: str,
    event_date: str,
) -> Tuple[str, str]:
    """
    Returns (status, reason):
      - review   = Kandidat zur manuellen Prüfung (gewollt!)
      - rejected = sicher kein Event (Müll) -> wird NICHT in Inbox geschrieben
    """

    text = f"{title}\n{description}".strip()
    is_non_event = _is_non_event_text(text)
    has_event = _has_event_signal(text)
    is_pressish = (stype == "rss") or _is_press_like_source(source_name, source_url)

    # 1) Harte Müllsignale UND kein Event-Signal -> raus
    if is_non_event and not has_event:
        return "rejected", "reject:non_event_keywords"

    # 2) Presse/RSS ohne Event-Signal -> raus
    if is_pressish and not has_event:
        return "rejected", "reject:press_without_event_signal"

    # 3) Datum außerhalb Fenster -> raus (wenn Datum vorhanden)
    if norm(event_date) and (not _in_date_window(event_date)):
        return "rejected", "reject:date_out_of_window"

    # 4) Alles, was irgendwie eventartig ist ODER unklar ist -> REVIEW (nie blocked)
    if not norm(event_date):
        return "review", "review:missing_event_date"
    if has_event:
        return "review", "review:event_signal"

    return "review", "review:uncertain"

# === END BLOCK: DISCOVERY FILTER HELPERS (junk skip + date window) ===



def safe_fetch(url: str, timeout: int = 20) -> str:

    req = urllib.request.Request(url, headers={"User-Agent": "BocholtErlebenDiscovery/1.0"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        charset = resp.headers.get_content_charset() or "utf-8"
        return resp.read().decode(charset, errors="replace")


# -------------------------
# ICS (iCal) minimal parser
# -------------------------

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

    def extract_event_date(text: str, fallback_year: int) -> Tuple[str, str]:
        """
        Returns (event_date_iso_or_empty, note)
        Note contains extraction method / warnings.
        """
        t = norm(text)
        if not t:
            return ("", "event_date:missing (no text)")

        # 1) dd.mm.yyyy or dd.mm.yy
        m = re.search(r"(?<!\d)(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})(?!\d)", t)
        if m:
            dd = int(m.group(1))
            mm = int(m.group(2))
            yy = m.group(3)
            yyyy = int(yy) if len(yy) == 4 else (2000 + int(yy))
            try:
                d = date(yyyy, mm, dd).strftime("%Y-%m-%d")
                return (d, "event_date:regex(dd.mm.yyyy)")
            except Exception:
                return ("", "event_date:invalid(dd.mm.yyyy)")

        # 2) dd.mm. (year inferred)
        m = re.search(r"(?<!\d)(\d{1,2})\.(\d{1,2})\.(?!\d)", t)
        if m:
            dd = int(m.group(1))
            mm = int(m.group(2))
            try:
                d = date(fallback_year, mm, dd).strftime("%Y-%m-%d")
                return (d, "event_date:regex(dd.mm.) year_inferred")
            except Exception:
                return ("", "event_date:invalid(dd.mm.)")

        # 3) dd. Monat yyyy / dd. Monat (year inferred)
        m = re.search(r"(?<!\d)(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s*(\d{4})?(?!\d)", t)
        if m:
            dd = int(m.group(1))
            mon = norm_key(m.group(2))
            yyyy = int(m.group(3)) if m.group(3) else fallback_year
            mm = month_map.get(mon, 0)
            if mm:
                try:
                    d = date(yyyy, mm, dd).strftime("%Y-%m-%d")
                    note = "event_date:regex(dd.monat" + (")" if m.group(3) else ") year_inferred")
                    return (d, note)
                except Exception:
                    return ("", "event_date:invalid(dd.monat)")

        return ("", "event_date:missing (article date only)")

    def pack_candidate(title: str, link: str, article_date: str, description: str) -> Dict[str, str]:
        combined = f"{title}\n{description}"
        fallback_year = int(article_date[:4]) if article_date and len(article_date) >= 4 else datetime.now().year
        ev_date, ev_note = extract_event_date(combined, fallback_year)

        notes = f"article_date={article_date or ''}; {ev_note}"
        return {
            "title": clean_text(title),
            "date": ev_date,          # Event-Datum (kann leer sein)
            "endDate": "",
            "time": "",
            "location": "",
            "url": canonical_url(link),
            "description": clean_text(description),
            "notes": notes,
        }

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

        for obj in _jsonld_iter_objs(data):
            if not isinstance(obj, dict):
                continue
            if not _jsonld_is_event(obj):
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
            loc = _jsonld_location(obj)
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

def parse_html_generic_events(html_text: str, base_url: str) -> List[Dict[str, str]]:
    """
    Generic HTML -> candidates:
    1) JSON-LD Events
    2) Feed Autodiscovery -> fetch feed -> parse_rss/parse_ics_events
    """
    # 1) JSON-LD
    candidates = parse_html_jsonld_events(html_text, base_url)
    if candidates:
        return candidates

    # 2) Autodiscovered feeds (RSS/Atom/ICS)
    feeds = discover_feeds_from_html(html_text, base_url)
    for fu in feeds:
        try:
            fc = safe_fetch(fu)
            if norm_key(fu).endswith(".ics") or ("ical" in norm_key(fu)) or ("text/calendar" in norm_key(fu)):
                cand = parse_ics_events(fc)
            else:
                cand = parse_rss(fc)
            if cand:
                # markiere Quelle
                for x in cand:
                    x["notes"] = (clean_text(x.get("notes", "")) + (" | " if x.get("notes") else "") + f"source=html_feed:{canonical_url(fu)}")
                return cand
        except Exception:
            continue

    return []
# === END BLOCK: GENERIC HTML EVENT PARSER (jsonld + feed autodiscovery, v1) ===

    
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

    enabled_sources = [s for s in sources_rows if norm(s.get("enabled", "")).upper() in ("TRUE", "1", "YES", "JA")]
    info(f"Sources enabled: {len(enabled_sources)}")

    new_inbox_rows: List[List[str]] = []
    health_rows: List[List[str]] = []
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

        # === BEGIN BLOCK: FETCH + PARSE DISPATCH + HEALTH LOGGING (v4 html-generic) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - candidates ist IMMER definiert
        # - Health pro Quelle loggen (ok / fetch_error / parse_error / unsupported)
        # - HTML: generischer Parser (JSON-LD Events + Feed-Autodiscovery), bocholt-spezial bleibt als Fallback
        # Umfang:
        # - ersetzt nur den Dispatch-Block; Events/Inbox Logik bleibt danach unverändert
        # === END BLOCK: FETCH + PARSE DISPATCH + HEALTH LOGGING (v4 html-generic) ===

        candidates: List[Dict[str, str]] = []
        health_status = "ok"
        http_status = ""
        err_msg = ""
        checked_at = now_iso()

        try:
            content = safe_fetch(url)

            if stype in ("ical", "ics"):
                candidates = parse_ics_events(content)

            elif stype == "rss":
                candidates = parse_rss(content)

            elif stype == "json":
                candidates = parse_json(content, url)

            elif stype == "html":
                # 1) Generic: JSON-LD (@type=Event) direkt aus HTML
                # 2) Generic: Feed/ICS Autodiscovery aus HTML und dann RSS/ICS nachladen
                # 3) Fallback: bocholt-spezifischer Kalenderparser (wenn URL passt)
                candidates = parse_html_generic_events(content, url)

                if (not candidates) and ("bocholt.de/veranstaltungskalender" in norm_key(url)):
                    candidates = parse_bocholt_calendar_html(content, url)

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


        total_candidates += len(candidates)
        # === BEGIN BLOCK: SOURCE HEALTH ROW (per source, per run) ===
        # Datei: scripts/discovery-to-inbox.py
        # Zweck:
        # - Pro Quelle Status + Kandidatenanzahl + neu geschriebene Zeilen protokollieren
        # Umfang:
        # - Schreibt NUR in Source_Health (separater Tab)
        # === END BLOCK: SOURCE HEALTH ROW (per source, per run) ===
        written_before_source = total_written



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
                        # === BEGIN BLOCK: ENRICH LOCATION/TIME (infer missing fields) ===
            # Datei: scripts/discovery-to-inbox.py
            # Zweck:
            # - Falls RSS/ICS kein Location/Time liefert: aus Titel/Description ableiten
            # Umfang:
            # - Setzt nur lokale Variablen inferred_location / inferred_time
            # === END BLOCK: ENRICH LOCATION/TIME (infer missing fields) ===
            raw_location = clean_text(c.get("location", ""))
            raw_time = norm(c.get("time", ""))

            inferred_location, inferred_time = infer_location_time(title, desc_text)

            final_location = raw_location or inferred_location
            final_time = raw_time or inferred_time


            status, reason = classify_candidate(
                stype=stype,
                source_name=source_name,
                source_url=url,
                title=title,
                description=desc_text,
                event_date=d,
            )
            if status == "rejected":
                continue

            # === BEGIN BLOCK: URL NORMALIZATION (indent fix) ===
            # Zweck: Korrigiert Einrückung von c_url_raw, damit der Block syntaktisch stabil ist.
            # Umfang: Ersetzt nur die beiden Zeilen c_url_raw/c_url.
            c_url_raw = norm(c.get("url", ""))
            c_url = canonical_url(c_url_raw)
            # === END BLOCK: URL NORMALIZATION (indent fix) ===



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


            # Already live -> ignore
            if matched_id:
                continue

            # Already in inbox -> ignore
            fp = make_candidate_fingerprint(c, url)
            if fp in existing_inbox_fps:
                continue

            # Write to inbox as suggestion
            row = {
                "status": status,
                "id_suggestion": make_id_suggestion(title, d, norm(c.get("time", "")), url),
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
                                "notes": (
                    clean_text(c.get("notes", ""))
                    + ("" if not reason else ((" | " if clean_text(c.get("notes", "")) else "") + reason))
                    + ("" if d else ((" | " if (clean_text(c.get("notes", "")) or reason) else "") + "⚠️ event_date_missing"))
                ),

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
