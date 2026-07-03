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


INTENT_RULES = [
    ("today-events", re.compile(r"\b(was|wo)\b.*\b(los|veranstaltung|veranstaltungen|event|events)\b|\bheute\b.*\b(los|veranstaltung|veranstaltungen|event|events)\b", re.I),
     "Today-/Was-ist-los-Suchintention", "Today-Bereich und SEO für tagesaktuelle Veranstaltungen verbessern"),
    ("weekend-events", re.compile(r"\bwochenende\b.*\b(los|veranstaltung|veranstaltungen|event|events)\b|\b(los|veranstaltung|veranstaltungen|event|events)\b.*\bwochenende\b", re.I),
     "Wochenend-Event-Suchintention", "Wochenend- und Heute-Logik für Veranstaltungen prüfen"),
    ("bad-weather-indoor", re.compile(r"\b(regen|schlechtwetter|indoor|drinnen)\b", re.I),
     "Indoor-/Schlechtwetter-Suchintention", "Indoor- und Regen-Inhalte als Arbeitspaket prüfen"),
    ("family-kids", re.compile(r"\b(kinder|familie|familien|kind|spielplatz|indoorspielplatz|kindergeburtstag)\b", re.I),
     "Familien-/Kinder-Suchintention", "Familien- und Kinder-Inhalte priorisiert prüfen"),
    ("swimming", re.compile(r"\b(hallenbad|schwimmbad|freibad|bahia|badesee|aasee)\b", re.I),
     "Schwimmen-/Wasser-Suchintention", "Schwimmen-/Wasser-Content prüfen"),
    ("food-after-activity", re.compile(r"\b(cafe|café|restaurant|essen|eiscafe|eiscafé)\b", re.I),
     "Gastro-/Anschluss-Suchintention", "Gastro-Verknüpfung nach Events/Aktivitäten prüfen"),
]

def infer_intent(text: str) -> dict[str, str]:
    normalized = text.lower().translate(str.maketrans({"ä": "ae", "ö": "oe", "ü": "ue", "ß": "ss"}))
    for key, pattern, label, recommendation in INTENT_RULES:
        if pattern.search(normalized):
            return {"key": key, "label": label, "recommendation": recommendation}
    topic = canonical_topic(text)
    return {"key": slug(topic) or "general", "label": topic.title() or "Allgemeine Suchintention", "recommendation": "Content-/SEO-Potenzial fachlich prüfen"}

def score_priority(impressions: float = 0, clicks: float = 0, sessions: float = 0, confidence: float = 0.7, effort: int = 2) -> tuple[str, dict[str, Any]]:
    impact = 0
    if impressions >= 1000 or sessions >= 250:
        impact = 5
    elif impressions >= 500 or sessions >= 150:
        impact = 4
    elif impressions >= 150 or sessions >= 50:
        impact = 3
    elif impressions >= 40 or sessions >= 15:
        impact = 2
    else:
        impact = 1
    value = impact * confidence / max(1, effort)
    priority = "hoch" if value >= 1.35 or impact >= 5 else "mittel" if value >= 0.75 or impact >= 3 else "niedrig"
    return priority, {"impact_score": impact, "effort_score": effort, "confidence": confidence, "value_score": round(value, 3)}

def recommendation_for_intent(intent_key: str, impressions: float, clicks: float, ctr: float, covered: bool) -> tuple[str, str, str, int]:
    if intent_key == "today-events":
        title = "Today-/Was-ist-los-Suchintention besser bedienen"
        action = "Today-Ansicht, Home-Platzierung, Seitentitel, Snippet-Texte und interne Verlinkung für tagesaktuelle Veranstaltungen prüfen."
        benefit = "Nutzer mit klarer Heute-Absicht schneller abholen und vorhandene Event-Inhalte organisch besser nutzbar machen."
        effort = 2
    elif intent_key == "weekend-events":
        title = "Wochenend-Suchintention für Veranstaltungen prüfen"
        action = "Prüfen, ob Wochenende/Heute als klare Einstiegslogik, Landingpage oder Filterzustand abbildbar ist."
        benefit = "Bessere Abdeckung typischer Freizeitplanung und stärkere Nutzung vorhandener Eventdaten."
        effort = 2
    elif intent_key == "bad-weather-indoor":
        title = "Indoor-/Schlechtwetter-Inhalte als Arbeitspaket prüfen"
        action = "Prüfen, ob vorhandene Indoor-Aktivitäten, Events und Landingpages bei Regen besser gebündelt werden sollten."
        benefit = "Höherer Nutzerwert bei schlechtem Wetter und klare saisonale Content-Chance."
        effort = 3
    elif intent_key == "family-kids":
        title = "Familien-/Kinder-Inhalte gezielt prüfen"
        action = "Prüfen, ob Kinder-/Familienangebote, Filter, Highlights oder Landingpages vollständiger und prominenter werden sollten."
        benefit = "Stärkeres Kern-Nutzersegment und spätere Akquisegrundlage für familienorientierte Anbieter."
        effort = 3
    elif intent_key == "swimming":
        title = "Schwimmen-/Wasser-Content prüfen"
        action = "Prüfen, ob bestehende Schwimmen-/Badesee-/Hallenbad-Inhalte vollständig, aktuell und sinnvoll verlinkt sind."
        benefit = "Suchnachfrage bedienen; Akquise nur nachrangig, weil öffentliche/bekannte Anbieter oft geringe Zahlungsbereitschaft haben."
        effort = 2
    elif intent_key == "food-after-activity":
        title = "Gastro-Verknüpfung nach Events/Aktivitäten prüfen"
        action = "Prüfen, ob Café/Restaurant-Suchen als Anschlussnutzen nach Events oder Aktivitäten besser verknüpft werden können."
        benefit = "Mehr Alltagstauglichkeit und mögliche spätere Partner-/Akquisegrundlage für buchungs- oder gastroorientierte Anbieter."
        effort = 3
    elif not covered:
        title = "Content-Lücke mit belegter Nachfrage prüfen"
        action = "Prüfen, ob eine Activity, Landingpage oder Content-Erweiterung sinnvoll ist. Keine automatische Übernahme."
        benefit = "Belegte Nachfrage-Lücke schließen und organische Sichtbarkeit verbessern."
        effort = 3
    elif ctr < 0.025:
        title = "SEO-Chance bei vorhandener Sichtbarkeit prüfen"
        action = "Title, Beschreibung, Snippet-Relevanz, interne Verlinkung und Zielinhalt prüfen."
        benefit = "Mehr Klicks aus vorhandener Google-Sichtbarkeit ohne neuen Suchlauf."
        effort = 2
    else:
        title = "Growth-Signal fachlich prüfen"
        action = "Als Arbeitspaket prüfen, ob Content, UX oder interne Verlinkung verbessert werden sollte."
        benefit = "Rohsignal in konkrete Projektentscheidung übersetzen."
        effort = 2
    return title, action, benefit, effort


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


def fetch_ga4(creds, property_id: str, start: str, end: str) -> Tuple[List[Dict[str, Any]], str]:
    if not property_id:
        return [], "GA4_PROPERTY_ID nicht gesetzt; GA4 übersprungen."
    analytics = service("analyticsdata", "v1beta", creds)
    name = f"properties/{property_id}"
    body = {
        "dateRanges": [{"startDate": start, "endDate": end}],
        "dimensions": [{"name": "landingPagePlusQueryString"}, {"name": "sessionDefaultChannelGroup"}],
        "metrics": [{"name": "sessions"}, {"name": "engagementRate"}, {"name": "averageSessionDuration"}],
        "limit": 10000,
    }
    try:
        res = analytics.properties().runReport(property=name, body=body).execute()
    except HttpError as exc:
        raw = ""
        try:
            raw = exc.content.decode("utf-8", errors="replace") if getattr(exc, "content", None) else str(exc)
        except Exception:
            raw = str(exc)
        lower = raw.lower()
        if exc.resp.status in (401, 403) or "permission" in lower or "access" in lower:
            return [], "GA4 Diagnose: Zugriff verweigert. Service Account in GA4 Property-Zugriffsverwaltung als Betrachter/Analyst hinzufügen und Analytics Data API prüfen."
        if "analyticsdata" in lower and ("disabled" in lower or "not been used" in lower or "enable" in lower):
            return [], "GA4 Diagnose: Google Analytics Data API ist im Google-Cloud-Projekt wahrscheinlich nicht aktiviert."
        if exc.resp.status == 404 or "not found" in lower:
            return [], "GA4 Diagnose: Property nicht gefunden. GA4_PROPERTY_ID prüfen; benötigt wird die numerische Property-ID, nicht die Measurement-ID G-..."
        return [], f"GA4 Diagnose: API-Fehler HTTP {exc.resp.status}: {raw[:500]}"
    except Exception as exc:
        return [], f"GA4 Diagnose: unerwarteter Fehler: {type(exc).__name__}: {exc}"
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
    if not rows:
        return rows, "GA4 Diagnose: API erreichbar und berechtigt, aber keine Zeilen im Zeitraum/Abfrageschema geliefert. Zeitraum, Traffic oder Datenerhebung prüfen."
    return rows, f"GA4 Diagnose: API erreichbar; {len(rows)} Zeilen gelesen."


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
        intent = infer_intent(q)
        topic = intent["key"]
        g = grouped.setdefault(topic, {"intent": intent, "queries": [], "impressions": 0.0, "clicks": 0.0, "positions": [], "pages": set(), "raw_topics": Counter()})
        g["queries"].append(q)
        g["impressions"] += float(r.get("impressions", 0))
        g["clicks"] += float(r.get("clicks", 0))
        g["positions"].append(float(r.get("position", 0) or 0))
        g["raw_topics"][canonical_topic(q)] += 1
        if r.get("page"):
            g["pages"].add(str(r.get("page")))

    for topic, g in grouped.items():
        intent = g["intent"]
        impressions = g["impressions"]
        clicks = g["clicks"]
        ctr = clicks / impressions if impressions else 0.0
        pos = sum(g["positions"]) / max(1, len(g["positions"]))
        covered = seems_covered(" ".join(g["raw_topics"].keys()) or topic, inventory)
        top_queries = sorted(set(g["queries"]), key=lambda x: (-g["queries"].count(x), x))[:8]
        title, action, benefit, effort = recommendation_for_intent(topic, impressions, clicks, ctr, covered)
        confidence = 0.9 if topic in {"today-events", "weekend-events", "bad-weather-indoor", "family-kids", "swimming"} else 0.7
        priority, score = score_priority(impressions=impressions, clicks=clicks, confidence=confidence, effort=effort)
        kind = "SEO-/Content-Arbeitspaket"
        if not covered and topic not in {"today-events", "weekend-events"}:
            kind = "Content-Lücke"
        elif ctr < 0.025 and impressions >= MIN_IMPRESSIONS:
            kind = "SEO-Optimierung"
        description = (
            f"Search-Console-Daten vom {start} bis {end} zeigen {int(impressions)} Impressionen und {int(clicks)} Klicks für die Suchintention „{intent['label']}“ "
            f"(Beispiele: {', '.join(top_queries[:5])}). Empfohlenes Arbeitspaket: {action} "
            f"Erwarteter Nutzen: {benefit} Interner Score: Nutzen {score['impact_score']}/5, Aufwand {score['effort_score']}/5, Konfidenz {score['confidence']:.0%}."
        )
        key = cluster_key(kind, topic)
        candidates[key] = Candidate(
            cluster_key=key,
            priority=priority,
            type=kind,
            title=title,
            short_reason=description,
            why_relevant=description,
            recommended_action=action,
            expected_benefit=benefit,
            acquisition_note=acquisition_note(" ".join(top_queries)),
            source="growth-intelligence:gsc-intent",
            signals={"period_start": start, "period_end": end, "intent": intent, "impressions": impressions, "clicks": clicks, "ctr": ctr, "avg_position": pos, "queries": top_queries, "covered": covered, **score},
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
        priority, score = score_priority(sessions=sessions, confidence=0.75, effort=2)
        key = cluster_key("UX-/Content-Prüfung", topic)
        desc = (
            f"GA4-Daten vom {start} bis {end} zeigen {int(sessions)} organische Sessions mit schwachen Engagement-Signalen auf „{topic}“ "
            f"(Engagement {engagement:.0%}, Ø Dauer {duration:.0f}s). Prüfen, ob Erwartung, Inhalt, Card-Auswahl, interne Verlinkung oder mobile Darstellung verbessert werden müssen. "
            f"Interner Score: Nutzen {score['impact_score']}/5, Aufwand {score['effort_score']}/5, Konfidenz {score['confidence']:.0%}."
        )
        candidates.setdefault(key, Candidate(
            cluster_key=key,
            priority=priority,
            type="UX-/Content-Prüfung",
            title=f"Landingpage-Nutzung prüfen: {topic}",
            short_reason=desc,
            why_relevant=desc,
            recommended_action="Erwartung, Inhalt, Card-Auswahl, interne Verlinkung und mobile Darstellung prüfen.",
            expected_benefit="Bessere Nutzerführung und höhere Chance, organische Besucher in echte Nutzung der Seite zu überführen.",
            acquisition_note="Keine direkte Akquise-Aktion. Erst Produkt-/Content-Qualität verbessern.",
            source="growth-intelligence:ga4",
            signals={"period_start": start, "period_end": end, "landing_page": page, "channel": channel, "sessions": sessions, "engagement_rate": engagement, "avg_duration": duration, **score},
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
        elif metric in {"website_click", "location_click", "event_share_click", "event_copy_link"}:
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
        intent = infer_intent(existing_text)
        if intent.get("key") == "today-events" or canonical_topic(existing_text) == "heute veranstaltungen events":
            known_cluster_keys.add(cluster_key("SEO-/Landingpage-Chance", "heute veranstaltungen events"))
            known_cluster_keys.add(cluster_key("SEO-/Content-Arbeitspaket", "today-events"))
            known_cluster_keys.add(cluster_key("SEO-Optimierung", "today-events"))
        if intent.get("key"):
            known_cluster_keys.add(cluster_key("SEO-/Content-Arbeitspaket", intent["key"]))
            known_cluster_keys.add(cluster_key("SEO-Optimierung", intent["key"]))
            known_cluster_keys.add(cluster_key("Content-Lücke", intent["key"]))
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
    ga4_diag = ""
    try:
        ga4_rows, ga4_diag = fetch_ga4(creds, ga4_property_id, start, end)
        if ga4_diag:
            messages.append(ga4_diag)
        if ga4_property_id and not ga4_rows:
            status = "partial"
    except Exception as exc:
        status = "partial"
        messages.append(f"GA4 Diagnose: unerwarteter Fehler außerhalb API-Call: {type(exc).__name__}: {exc}")

    value_rows = fetch_value_metrics(start, end)
    if not value_rows:
        messages.append("Interne Nutzungsdaten Diagnose: value_metric_daily lieferte 0 Zeilen oder DB-Zugriff/Tabellenstruktur ist nicht verfügbar.")
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
    if messages:
        log("Growth Intelligence diagnostics:")
        for m in messages:
            log(f"- {m}")


if __name__ == "__main__":
    main()
# === END FILE: scripts/growth-intelligence-backlog.py ===
