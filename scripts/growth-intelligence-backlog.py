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


def main() -> None:
    sheet_id = os.environ.get("BE_SHEET_ID", "").strip() or os.environ.get("SHEET_ID", "").strip()
    if not sheet_id:
        fail("BE_SHEET_ID oder SHEET_ID fehlt.")
    site_url = os.environ.get("GSC_SITE_URL", "").strip()
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

    candidates = build_candidates(gsc_rows, ga4_rows, inv, start, end)
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
        "source": "gsc+ga4+content_inventory",
        "items_created": len(created),
        "items_suppressed": suppressed,
        "gsc_rows": len(gsc_rows),
        "ga4_rows": len(ga4_rows),
        "message": " | ".join(messages) if messages else "Growth-Backlog erfolgreich aktualisiert.",
    }])
    log(f"Growth Intelligence: created={len(created)} suppressed={suppressed} gsc_rows={len(gsc_rows)} ga4_rows={len(ga4_rows)} status={status}")


if __name__ == "__main__":
    main()
# === END FILE: scripts/growth-intelligence-backlog.py ===
