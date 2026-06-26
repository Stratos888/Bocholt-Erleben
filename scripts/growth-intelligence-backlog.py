#!/usr/bin/env python3
# === BEGIN FILE: scripts/growth-intelligence-backlog.py | Zweck: erzeugt deduplizierte Growth-/Acquisition-Backlog-Punkte aus GSC, GA4 und Content-Bestand; Umfang: API-basierter Workflow ohne KI-Suchlauf-Kopplung ===
from __future__ import annotations

import datetime as dt
import hashlib
import json
import os
import re
import sys
import time
from collections import Counter, defaultdict
from pathlib import Path
from dataclasses import dataclass
from typing import Any, Dict, Iterable, List, Tuple

from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

BACKLOG_TAB = os.environ.get("GROWTH_BACKLOG_TAB", "Growth_Backlog").strip() or "Growth_Backlog"
REPORT_TAB = os.environ.get("GROWTH_REPORT_TAB", "Growth_Intelligence_Report").strip() or "Growth_Intelligence_Report"
DAYS = int(os.environ.get("GROWTH_LOOKBACK_DAYS", "30") or "30")
MIN_IMPRESSIONS = int(os.environ.get("GROWTH_MIN_IMPRESSIONS", "40") or "40")
MIN_SESSIONS = int(os.environ.get("GROWTH_MIN_SESSIONS", "15") or "15")
ROOT = Path(__file__).resolve().parents[1]

BACKLOG_HEADER = [
    "id", "cluster_key", "status", "priority", "type", "title", "short_reason",
    "why_relevant", "recommended_action", "expected_benefit", "acquisition_note",
    "source", "signals_json", "created_at", "updated_at", "closed_at", "decision_note",
]
REPORT_HEADER = [
    "generated_at", "period_start", "period_end", "status", "source", "items_created",
    "items_suppressed", "gsc_rows", "ga4_rows", "message",
]

ACTIVITY_TERMS = [
    "hallenbad", "schwimmbad", "freibad", "bahia", "minigolf", "spielplatz", "indoorspielplatz",
    "indoor", "kindergeburtstag", "klettern", "escape", "bowling", "museum", "kino", "theater",
    "ferienprogramm", "ausflug", "aktivität", "aktivitaet", "freizeit", "radfahren", "wandern",
    "badesee", "aasee", "sup", "kanu", "cafe", "café", "restaurant", "workshop", "kurs",
]
PUBLIC_OR_LOW_ACQ = ["hallenbad", "schwimmbad", "bahia", "freibad", "stadt", "bibliothek"]
PRIVATE_OR_ACQ = ["kindergeburtstag", "indoor", "indoorspielplatz", "escape", "bowling", "minigolf", "cafe", "café", "kurs", "workshop"]
STOPWORDS = {
    "bocholt", "in", "bei", "nähe", "naehe", "umgebung", "nrw", "deutschland", "öffnungszeiten",
    "oeffnungszeiten", "öffnungszeit", "adresse", "preise", "preis", "tickets", "ticket", "heute", "morgen",
    "wochenende", "mit", "und", "für", "fuer", "der", "die", "das", "am", "im", "zur", "zum",
}

@dataclass
class Candidate:
    cluster_key: str
    priority: str
    type: str
    title: str
    short_reason: str
    why_relevant: str
    recommended_action: str
    expected_benefit: str
    acquisition_note: str
    source: str
    signals: Dict[str, Any]


def log(msg: str) -> None:
    print(msg, flush=True)


def fail(msg: str) -> None:
    print(f"❌ {msg}", file=sys.stderr)
    sys.exit(1)


def now_iso() -> str:
    return dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def slug(value: str) -> str:
    value = value.strip().lower()
    value = value.translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    value = re.sub(r"[^a-z0-9]+", "-", value)
    return value.strip("-")


def canonical_topic(text: str) -> str:
    s = text.lower().translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    if re.search(r"\b(was|wo)\b.*\b(los|veranstaltung|veranstaltungen|event|events)\b", s) or re.search(r"\bheute\b.*\b(los|veranstaltung|veranstaltungen|event|events)\b", s):
        return "heute veranstaltungen events"
    s = re.sub(r"[^a-z0-9\s-]+", " ", s)
    words = [w for w in re.split(r"\s+", s) if w and w not in STOPWORDS]
    if not words:
        words = [w for w in re.split(r"\s+", s) if w]
    topic = " ".join(words[:5]).strip()
    aliases = {
        "bahia": "schwimmen hallenbad bahia",
        "hallenbad": "schwimmen hallenbad bahia",
        "schwimmbad": "schwimmen hallenbad bahia",
        "freizeitbad": "schwimmen hallenbad bahia",
        "indoorspielplatz": "indoor familien kinder",
        "indoor spielplatz": "indoor familien kinder",
        "kindergeburtstag": "kindergeburtstag angebote",
    }
    for key, replacement in aliases.items():
        if key in topic:
            return replacement
    return topic or slug(text)


def cluster_key(kind: str, topic: str) -> str:
    return f"{slug(kind)}-{slug(canonical_topic(topic))}"


def priority_from(impressions: float = 0, clicks: float = 0, sessions: float = 0, ctr: float | None = None) -> str:
    if impressions >= 500 or sessions >= 150:
        return "hoch"
    if impressions >= 150 or sessions >= 50:
        return "mittel"
    return "niedrig"


def parse_sa() -> service_account.Credentials:
    raw = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON", "").strip()
    if not raw:
        fail("GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")
    try:
        info = json.loads(raw)
    except Exception as exc:
        fail(f"GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON: {exc}")
    scopes = [
        "https://www.googleapis.com/auth/spreadsheets",
        "https://www.googleapis.com/auth/webmasters.readonly",
        "https://www.googleapis.com/auth/analytics.readonly",
    ]
    return service_account.Credentials.from_service_account_info(info, scopes=scopes)


def service(name: str, version: str, creds):
    return build(name, version, credentials=creds, cache_discovery=False)


def ensure_sheet(sheets, spreadsheet_id: str, title: str, header: List[str]) -> None:
    meta = sheets.spreadsheets().get(spreadsheetId=spreadsheet_id, fields="sheets.properties.title").execute()
    titles = {s.get("properties", {}).get("title") for s in meta.get("sheets", [])}
    if title not in titles:
        sheets.spreadsheets().batchUpdate(spreadsheetId=spreadsheet_id, body={"requests": [{"addSheet": {"properties": {"title": title}}}]}).execute()
    sheets.spreadsheets().values().update(
        spreadsheetId=spreadsheet_id,
        range=f"{title}!A1:{chr(64+len(header))}1",
        valueInputOption="USER_ENTERED",
        body={"values": [header]},
    ).execute()


def values_get(sheets, spreadsheet_id: str, tab: str, rng: str = "A:ZZ") -> List[List[str]]:
    try:
        res = sheets.spreadsheets().values().get(spreadsheetId=spreadsheet_id, range=f"{tab}!{rng}").execute()
        return res.get("values", []) or []
    except HttpError as exc:
        if exc.resp.status == 400:
            return []
        raise


def read_records(sheets, spreadsheet_id: str, tab: str) -> List[Dict[str, str]]:
    rows = values_get(sheets, spreadsheet_id, tab)
    if not rows:
        return []
    header = [str(x).strip() for x in rows[0]]
    out = []
    for raw in rows[1:]:
        rec = {h: str(raw[i]).strip() if i < len(raw) else "" for i, h in enumerate(header) if h}
        if any(rec.values()):
            out.append(rec)
    return out


def append_records(sheets, spreadsheet_id: str, tab: str, header: List[str], records: List[Dict[str, Any]]) -> None:
    if not records:
        return
    values = [[str(rec.get(h, "")) for h in header] for rec in records]
    sheets.spreadsheets().values().append(
        spreadsheetId=spreadsheet_id,
        range=f"{tab}!A:ZZ",
        valueInputOption="USER_ENTERED",
        insertDataOption="INSERT_ROWS",
        body={"values": values},
    ).execute()


def inventory_text(sheets, spreadsheet_id: str) -> str:
    chunks: List[str] = []
    for tab in ["Events", "Activities", "Aktivitaeten", "Locations"]:
        rows = values_get(sheets, spreadsheet_id, tab)
        for row in rows[:1000]:
            chunks.append(" ".join(str(x) for x in row[:12]))
    return "\n".join(chunks).lower()


def seems_covered(topic: str, inventory: str) -> bool:
    can = canonical_topic(topic)
    words = [w for w in can.split() if len(w) >= 4]
    if not words:
        return False
    return any(w in inventory for w in words)


def fetch_gsc(creds, site_url: str, start: str, end: str) -> List[Dict[str, Any]]:
    if not site_url:
        return []
    webmasters = service("searchconsole", "v1", creds)
    body = {
        "startDate": start,
        "endDate": end,
        "dimensions": ["query", "page"],
        "rowLimit": 25000,
        "startRow": 0,
    }
    res = webmasters.searchanalytics().query(siteUrl=site_url, body=body).execute()
    rows = []
    for r in res.get("rows", []) or []:
        keys = r.get("keys", [])
        rows.append({
            "query": keys[0] if len(keys) > 0 else "",
            "page": keys[1] if len(keys) > 1 else "",
            "clicks": float(r.get("clicks", 0) or 0),
            "impressions": float(r.get("impressions", 0) or 0),
            "ctr": float(r.get("ctr", 0) or 0),
            "position": float(r.get("position", 0) or 0),
        })
    return rows


def fetch_ga4(creds, property_id: str, start: str, end: str) -> List[Dict[str, Any]]:
    if not property_id:
        return []
    analytics = service("analyticsdata", "v1beta", creds)
    name = f"properties/{property_id}"
    body = {
        "dateRanges": [{"startDate": start, "endDate": end}],
        "dimensions": [{"name": "landingPagePlusQueryString"}, {"name": "sessionDefaultChannelGroup"}],
        "metrics": [{"name": "sessions"}, {"name": "engagementRate"}, {"name": "averageSessionDuration"}],
        "limit": 10000,
    }
    res = analytics.properties().runReport(property=name, body=body).execute()
    rows = []
    for r in res.get("rows", []) or []:
        dims = [d.get("value", "") for d in r.get("dimensionValues", [])]
        mets = [m.get("value", "0") for m in r.get("metricValues", [])]
        rows.append({
            "landing_page": dims[0] if len(dims) > 0 else "",
            "channel": dims[1] if len(dims) > 1 else "",
            "sessions": float(mets[0] if len(mets) > 0 else 0),
            "engagement_rate": float(mets[1] if len(mets) > 1 else 0),
            "avg_duration": float(mets[2] if len(mets) > 2 else 0),
        })
    return rows


def acquisition_note(topic: str) -> str:
    s = topic.lower()
    if any(k in s for k in PUBLIC_OR_LOW_ACQ):
        return "Akquise aktuell niedrig priorisieren: hohe Nachfrage kann Content-relevant sein, aber Anbieter wirkt vermutlich bekannt, öffentlich oder bereits stark ausgelastet. Erst eigene Reichweite/Nutzersignale aufbauen."
    if any(k in s for k in PRIVATE_OR_ACQ):
        return "Potenzielle spätere Akquise: Thema passt zu privaten/freizeitorientierten Angeboten. Erst Content-Potenzial und eigene Sichtbarkeit prüfen, keine automatische Ansprache."
    return "Akquise nur als Nebenbewertung: zuerst Content-/Nutzermehrwert prüfen."


def build_candidates(gsc_rows: List[Dict[str, Any]], ga4_rows: List[Dict[str, Any]], inventory: str, start: str, end: str) -> List[Candidate]:
    candidates: Dict[str, Candidate] = {}
    grouped: Dict[str, Dict[str, Any]] = {}
    for r in gsc_rows:
        q = str(r.get("query", "")).strip()
        if not q or float(r.get("impressions", 0)) < MIN_IMPRESSIONS:
            continue
        ql = q.lower()
        if "bocholt" not in ql and not any(t in ql for t in ACTIVITY_TERMS):
            continue
        topic = canonical_topic(q)
        g = grouped.setdefault(topic, {"queries": [], "impressions": 0.0, "clicks": 0.0, "positions": [], "pages": set()})
        g["queries"].append(q)
        g["impressions"] += float(r.get("impressions", 0))
        g["clicks"] += float(r.get("clicks", 0))
        g["positions"].append(float(r.get("position", 0) or 0))
        if r.get("page"):
            g["pages"].add(str(r.get("page")))

    for topic, g in grouped.items():
        impressions = g["impressions"]
        clicks = g["clicks"]
        ctr = clicks / impressions if impressions else 0.0
        pos = sum(g["positions"]) / max(1, len(g["positions"]))
        covered = seems_covered(topic, inventory)
        top_queries = sorted(set(g["queries"]), key=lambda x: (-g["queries"].count(x), x))[:6]
        if topic == "heute veranstaltungen events":
            kind = "SEO-/Landingpage-Chance"
            title = "Heute-in-Bocholt / Was-ist-los-Suchen prüfen"
            short = f"{int(impressions)} Impressionen für tagesaktuelle Event-Suchintentionen."
            action = "Prüfen, ob die Today-Ansicht, interne Verlinkung, Seitentitel und Snippet-Texte für 'heute in Bocholt' bzw. 'was ist los' klar genug sind."
            benefit = "Bündelt Nachfrage nach tagesaktuellen Veranstaltungen in ein konkretes Optimierungs-Arbeitspaket statt einzelne Suchbegriffe als Backlog-Punkte zu sammeln."
        elif not covered:
            kind = "Content-Lücke"
            title = f"{topic.title()} prüfen"
            short = f"{int(impressions)} Impressionen, aber kein klar erkennbarer Zielinhalt."
            action = "Prüfen, ob eine Activity, Landingpage oder Content-Erweiterung sinnvoll ist. Nicht automatisch live übernehmen."
            benefit = "Schließt eine belegte Nachfrage-Lücke und kann organische Sichtbarkeit sowie spätere Akquisegrundlagen verbessern."
        elif impressions >= MIN_IMPRESSIONS and ctr < 0.025 and pos <= 25:
            kind = "SEO-Optimierung"
            title = f"SEO-Chance: {topic.title()}"
            short = f"{int(impressions)} Impressionen, niedrige CTR ({ctr:.1%})."
            action = "Title, Beschreibung, Snippet-Relevanz, interne Verlinkung und passenden Zielinhalt prüfen."
            benefit = "Mehr Klicks aus bereits vorhandener Google-Sichtbarkeit ohne neue Inhalte erzwingen zu müssen."
        else:
            continue
        key = cluster_key(kind, topic)
        candidates[key] = Candidate(
            cluster_key=key,
            priority=priority_from(impressions=impressions, clicks=clicks, ctr=ctr),
            type=kind,
            title=title,
            short_reason=short,
            why_relevant=f"Search-Console-Daten vom {start} bis {end} zeigen Nachfrage für: {', '.join(top_queries)}.",
            recommended_action=action,
            expected_benefit=benefit,
            acquisition_note=acquisition_note(topic),
            source="growth-intelligence:gsc",
            signals={"period_start": start, "period_end": end, "impressions": impressions, "clicks": clicks, "ctr": ctr, "avg_position": pos, "queries": top_queries, "covered": covered},
        )

    for r in ga4_rows:
        channel = str(r.get("channel", ""))
        page = str(r.get("landing_page", ""))
        sessions = float(r.get("sessions", 0) or 0)
        engagement = float(r.get("engagement_rate", 0) or 0)
        duration = float(r.get("avg_duration", 0) or 0)
        if sessions < MIN_SESSIONS:
            continue
        if "Organic" not in channel and channel not in ["Organic Search", "Unassigned", ""]:
            continue
        if engagement >= 0.35 and duration >= 20:
            continue
        topic = page.strip("/") or "Startseite"
        key = cluster_key("UX-/Content-Prüfung", topic)
        candidates.setdefault(key, Candidate(
            cluster_key=key,
            priority=priority_from(sessions=sessions),
            type="UX-/Content-Prüfung",
            title=f"Landingpage prüfen: {topic}",
            short_reason=f"{int(sessions)} organische Sessions, schwache Engagement-Signale.",
            why_relevant=f"GA4-Daten vom {start} bis {end} zeigen niedrige Interaktion auf dieser Einstiegsseite.",
            recommended_action="Prüfen, ob Erwartung, Inhalt, Card-Auswahl, interne Verlinkung oder mobile Darstellung verbessert werden müssen.",
            expected_benefit="Bessere Nutzerführung und höhere Chance, organische Besucher in echte Nutzung der Seite zu überführen.",
            acquisition_note="Keine direkte Akquise-Aktion. Erst Produkt-/Content-Qualität verbessern.",
            source="growth-intelligence:ga4",
            signals={"period_start": start, "period_end": end, "landing_page": page, "channel": channel, "sessions": sessions, "engagement_rate": engagement, "avg_duration": duration},
        ))
    return list(candidates.values())



def load_repo_visual_signals() -> list[Candidate]:
    """Create coarse backlog candidates from repo-owned visual backlog files.

    This is intentionally conservative: it does not create one item per image, only one
    aggregated workpackage if unresolved visual work is visible in repo data.
    """
    candidates: list[Candidate] = []
    now = now_iso()
    asset_path = ROOT / "data" / "event_visual_asset_backlog.tsv"
    remaster_path = ROOT / "data" / "event_visual_remaster_backlog.tsv"
    planned_count = 0
    high_count = 0
    remaster_count = 0
    try:
        if asset_path.exists():
            lines = asset_path.read_text(encoding="utf-8").splitlines()
            header = lines[0].split("\t") if lines else []
            idx_status = header.index("asset_status") if "asset_status" in header else -1
            idx_prio = header.index("priority") if "priority" in header else -1
            for line in lines[1:]:
                cols = line.split("\t")
                status = cols[idx_status].strip().lower() if idx_status >= 0 and idx_status < len(cols) else ""
                prio = cols[idx_prio].strip().lower() if idx_prio >= 0 and idx_prio < len(cols) else ""
                if status in {"planned", "candidate", "gap", "missing"}:
                    planned_count += 1
                    if prio == "high":
                        high_count += 1
        if remaster_path.exists():
            lines = remaster_path.read_text(encoding="utf-8").splitlines()
            remaster_count = max(0, len(lines) - 1)
    except Exception:
        return []

    if planned_count + remaster_count >= 8:
        title = "Event-Bildbestand gezielt weiter härten"
        desc = (
            f"Repo-Daten zeigen {planned_count} geplante/fehlende Event-Visuals und {remaster_count} Remaster-Kandidaten. "
            "Als Arbeitspaket bündeln, statt einzelne Bildthemen im Backlog zu sammeln. Ziel: bessere Kartenwirkung, weniger falsche Motive und stabilere visuelle Qualität."
        )
        candidates.append(Candidate(
            cluster_key="visual-event-bildbestand-haerten",
            priority="mittel" if high_count < 5 else "hoch",
            type="Visual-/Content-Qualität",
            title=title,
            short_reason=desc,
            why_relevant=desc,
            recommended_action="Visual-Backlog als eigenes Arbeitspaket priorisieren.",
            expected_benefit="Höhere wahrgenommene Qualität und weniger manuelle Bildkorrekturen.",
            acquisition_note="Keine direkte Akquise-Aktion.",
            source="growth-intelligence:repo-visual",
            signals={"planned_visuals": planned_count, "high_priority_visuals": high_count, "remaster_candidates": remaster_count, "generated_at": now},
        ))
    return candidates


def fetch_value_metrics(start: str, end: str) -> list[dict[str, Any]]:
    """Read anonymous value_metric_daily aggregates if DB secrets are available.

    Uses staging DB on staging, live DB on main. Missing DB config is a clean skip.
    """
    env_name = os.environ.get("BE_ENVIRONMENT", "").strip().lower()
    prefix = "LIVE" if env_name == "main" else "STAGING"
    host = os.environ.get(f"{prefix}_DB_HOST", "").strip()
    name = os.environ.get(f"{prefix}_DB_NAME", "").strip()
    user = os.environ.get(f"{prefix}_DB_USER", "").strip()
    password = os.environ.get(f"{prefix}_DB_PASSWORD", "").strip()
    port = int(os.environ.get(f"{prefix}_DB_PORT", "3306") or "3306")
    if not all([host, name, user, password]):
        return []
    try:
        import pymysql  # type: ignore
    except Exception:
        return []
    sql = """
        SELECT metric_key, entity_type, entity_id, COALESCE(entity_title, '') AS entity_title,
               reporting_target_type, reporting_target_id, COALESCE(reporting_target_title, '') AS reporting_target_title,
               COALESCE(page_path, '') AS page_path, SUM(count_value) AS total
        FROM value_metric_daily
        WHERE metric_date BETWEEN %s AND %s
        GROUP BY metric_key, entity_type, entity_id, entity_title, reporting_target_type, reporting_target_id, reporting_target_title, page_path
        ORDER BY total DESC
        LIMIT 200
    """
    try:
        conn = pymysql.connect(host=host, user=user, password=password, database=name, port=port, charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor, connect_timeout=8)
        with conn:
            with conn.cursor() as cur:
                cur.execute(sql, (start, end))
                return list(cur.fetchall() or [])
    except Exception:
        return []


def build_internal_metric_candidates(rows: list[dict[str, Any]], start: str, end: str) -> list[Candidate]:
    candidates: dict[str, Candidate] = {}
    by_entity: dict[str, dict[str, Any]] = defaultdict(lambda: {"views": 0, "outbound": 0, "maps": 0, "title": "", "type": "", "page": ""})
    organizer_clicks = 0
    for r in rows:
        metric = str(r.get("metric_key", ""))
        total = int(float(r.get("total", 0) or 0))
        if metric == "organizer_cta_click":
            organizer_clicks += total
            continue
        entity_id = str(r.get("reporting_target_id") or r.get("entity_id") or r.get("page_path") or "").strip()
        if not entity_id:
            continue
        b = by_entity[entity_id]
        b["title"] = str(r.get("reporting_target_title") or r.get("entity_title") or entity_id)
        b["type"] = str(r.get("reporting_target_type") or r.get("entity_type") or "content")
        b["page"] = str(r.get("page_path") or "")
        if metric in {"event_detail_view", "activity_detail_view"}:
            b["views"] += total
        elif metric in {"website_click", "location_click"}:
            b["outbound"] += total
        elif metric == "maps_click":
            b["maps"] += total

    for entity_id, b in by_entity.items():
        views = int(b["views"])
        outbound = int(b["outbound"])
        maps = int(b["maps"])
        if views < 25 and outbound + maps < 10:
            continue
        title = str(b["title"] or entity_id)
        key = cluster_key("Interne-Nutzung", title)
        desc = (
            f"Interne Nutzungsdaten vom {start} bis {end} zeigen messbares Interesse an „{title}“ "
            f"({views} Detailaufrufe, {outbound} Website-/Location-Klicks, {maps} Maps-Klicks). Prüfen, ob dieser Inhalt prominenter verlinkt, als Highlight genutzt oder als Muster für ähnliche Inhalte verwendet werden sollte."
        )
        candidates[key] = Candidate(
            cluster_key=key,
            priority="hoch" if views >= 100 or outbound + maps >= 40 else "mittel",
            type="Nutzungs-/Content-Chance",
            title=f"Stark genutzten Inhalt prüfen: {title[:80]}",
            short_reason=desc,
            why_relevant=desc,
            recommended_action="Prominenz, interne Verlinkung, ähnliche Inhalte und ggf. spätere Akquise-Eignung prüfen.",
            expected_benefit="Mehr Nutzen aus bereits beobachtetem Nutzerinteresse ziehen.",
            acquisition_note="Akquise nur prüfen, wenn Anbieter privat/buchungsorientiert ist und eigene Nutzersignale stark genug sind.",
            source="growth-intelligence:value-metrics",
            signals={"period_start": start, "period_end": end, "entity_id": entity_id, "views": views, "outbound_clicks": outbound, "maps_clicks": maps},
        )

    if organizer_clicks >= 5:
        desc = (
            f"Interne Nutzungsdaten zeigen {organizer_clicks} Klicks auf Veranstalter-/Anbieter-CTAs im Zeitraum {start} bis {end}. "
            "Prüfen, ob die Angebots-/Veranstalterstrecke verständlich genug ist und ob spätere Akquise-Unterlagen daraus abgeleitet werden sollten."
        )
        candidates["akquise-veranstalter-funnel-pruefen"] = Candidate(
            cluster_key="akquise-veranstalter-funnel-pruefen",
            priority="mittel" if organizer_clicks < 20 else "hoch",
            type="Akquise-/Funnel-Chance",
            title="Veranstalter-/Anbieter-Funnel prüfen",
            short_reason=desc,
            why_relevant=desc,
            recommended_action="CTA-Texte, Preise/Angebote, Vertrauen und nächste Schritte prüfen.",
            expected_benefit="Bessere Grundlage für spätere zahlende Anbieter, ohne direkte automatische Ansprache.",
            acquisition_note="Akquise-relevant, aber erst nach inhaltlicher Prüfung und belastbaren Nutzersignalen.",
            source="growth-intelligence:value-metrics",
            signals={"period_start": start, "period_end": end, "organizer_cta_clicks": organizer_clicks},
        )
    return list(candidates.values())


def build_sheet_history_candidates(sheets, spreadsheet_id: str, start: str, end: str) -> list[Candidate]:
    """Use existing review/audit history as coarse signals, if tabs exist."""
    candidates: list[Candidate] = []
    archive = read_records(sheets, spreadsheet_id, "Inbox_Archive")
    rejected_notes: Counter[str] = Counter()
    for r in archive:
        status = " ".join(str(r.get(k, "")).lower() for k in ["status", "decision", "review_status"])
        if not any(x in status for x in ["verworfen", "abgelehnt", "rejected"]):
            continue
        text = " ".join(str(r.get(k, "")) for k in ["notes", "title", "source_name", "kategorie_suggestion"])
        topic = canonical_topic(text)
        if topic and len(topic) >= 5:
            rejected_notes[topic] += 1
    for topic, count in rejected_notes.most_common(5):
        if count < 3:
            continue
        desc = f"Inbox-Archiv enthält {count} abgelehnte/verworfen markierte Einträge mit ähnlichem Muster („{topic}“). Prüfen, ob Quelle, Regelverständnis oder manuelle Bewertung dokumentiert werden sollte, damit gleiche Fälle weniger Arbeit verursachen."
        candidates.append(Candidate(
            cluster_key=cluster_key("Inbox-Ablehnungsmuster", topic),
            priority="mittel" if count < 8 else "hoch",
            type="Prozess-/Qualitätssignal",
            title=f"Wiederholtes Ablehnungsmuster prüfen: {topic.title()}",
            short_reason=desc,
            why_relevant=desc,
            recommended_action="Muster einmal fachlich bewerten und ggf. Regel-/Doku-/Quellenhinweis ableiten.",
            expected_benefit="Weniger wiederkehrende Review-Arbeit und sauberere Kandidatenqualität.",
            acquisition_note="Keine Akquise-Aktion.",
            source="growth-intelligence:inbox-history",
            signals={"period_start": start, "period_end": end, "topic": topic, "rejected_count": count},
        ))
    return candidates


def main() -> None:
    sheet_id = os.environ.get("BE_SHEET_ID", "").strip() or os.environ.get("SHEET_ID", "").strip()
    if not sheet_id:
        fail("BE_SHEET_ID oder SHEET_ID fehlt.")
    site_url = (os.environ.get("GSC_SITE_URL", "").strip() or os.environ.get("SEARCH_METRICS_GOOGLE_SITE_URL", "").strip())
    ga4_property_id = os.environ.get("GA4_PROPERTY_ID", "").strip()
    creds = parse_sa()
    sheets = service("sheets", "v4", creds)
    ensure_sheet(sheets, sheet_id, BACKLOG_TAB, BACKLOG_HEADER)
    ensure_sheet(sheets, sheet_id, REPORT_TAB, REPORT_HEADER)

    end_date = dt.date.today() - dt.timedelta(days=1)
    start_date = end_date - dt.timedelta(days=DAYS - 1)
    start, end = start_date.isoformat(), end_date.isoformat()

    existing = read_records(sheets, sheet_id, BACKLOG_TAB)
    known_cluster_keys = {r.get("cluster_key", "").strip() for r in existing if r.get("cluster_key", "").strip()}
    for r in existing:
        existing_text = " ".join([
            str(r.get("title", "")),
            str(r.get("short_reason", "")),
            str(r.get("why_relevant", "")),
            str(r.get("recommended_action", "")),
        ])
        if canonical_topic(existing_text) == "heute veranstaltungen events":
            known_cluster_keys.add(cluster_key("SEO-/Landingpage-Chance", "heute veranstaltungen events"))
    inv = inventory_text(sheets, sheet_id)

    gsc_rows: List[Dict[str, Any]] = []
    ga4_rows: List[Dict[str, Any]] = []
    messages = []
    status = "ok"
    try:
        gsc_rows = fetch_gsc(creds, site_url, start, end) if site_url else []
        if not site_url:
            messages.append("GSC_SITE_URL nicht gesetzt; GSC übersprungen.")
    except Exception as exc:
        status = "partial"
        messages.append(f"GSC Fehler: {exc}")
    try:
        ga4_rows = fetch_ga4(creds, ga4_property_id, start, end) if ga4_property_id else []
        if not ga4_property_id:
            messages.append("GA4_PROPERTY_ID nicht gesetzt; GA4 übersprungen.")
    except Exception as exc:
        status = "partial"
        messages.append(f"GA4 Fehler: {exc}")

    value_rows = fetch_value_metrics(start, end)
    candidates = build_candidates(gsc_rows, ga4_rows, inv, start, end)
    candidates.extend(build_internal_metric_candidates(value_rows, start, end))
    candidates.extend(build_sheet_history_candidates(sheets, sheet_id, start, end))
    candidates.extend(load_repo_visual_signals())
    created: List[Dict[str, Any]] = []
    suppressed = 0
    ts = now_iso()
    for c in sorted(candidates, key=lambda x: {"hoch": 0, "mittel": 1, "niedrig": 2}.get(x.priority, 3)):
        if c.cluster_key in known_cluster_keys:
            suppressed += 1
            continue
        row = {
            "id": f"growth-{dt.datetime.now(dt.timezone.utc).strftime('%Y%m%d%H%M%S')}-{hashlib.sha256(c.cluster_key.encode()).hexdigest()[:10]}",
            "cluster_key": c.cluster_key,
            "status": "open",
            "priority": c.priority,
            "type": c.type,
            "title": c.title,
            "short_reason": c.short_reason,
            "why_relevant": c.why_relevant,
            "recommended_action": c.recommended_action,
            "expected_benefit": c.expected_benefit,
            "acquisition_note": c.acquisition_note,
            "source": c.source,
            "signals_json": json.dumps(c.signals, ensure_ascii=False, separators=(",", ":")),
            "created_at": ts,
            "updated_at": ts,
            "closed_at": "",
            "decision_note": "",
        }
        created.append(row)
        known_cluster_keys.add(c.cluster_key)
        time.sleep(0.01)

    append_records(sheets, sheet_id, BACKLOG_TAB, BACKLOG_HEADER, created)
    append_records(sheets, sheet_id, REPORT_TAB, REPORT_HEADER, [{
        "generated_at": ts,
        "period_start": start,
        "period_end": end,
        "status": status,
        "source": "gsc+ga4+value_metrics+sheet_history+repo_visual+content_inventory",
        "items_created": len(created),
        "items_suppressed": suppressed,
        "gsc_rows": len(gsc_rows),
        "ga4_rows": len(ga4_rows),
        "message": " | ".join(messages) if messages else f"Growth-Backlog erfolgreich aktualisiert. Interne Metrik-Zeilen: {len(value_rows)}.",
    }])
    log(f"Growth Intelligence: created={len(created)} suppressed={suppressed} gsc_rows={len(gsc_rows)} ga4_rows={len(ga4_rows)} value_rows={len(value_rows)} status={status}")


if __name__ == "__main__":
    main()
# === END FILE: scripts/growth-intelligence-backlog.py ===
