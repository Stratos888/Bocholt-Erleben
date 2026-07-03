#!/usr/bin/env python3
"""Generate canonical, shareable event detail pages from generated runtime data.

The Events sheet remains the source of truth. This script only turns generated
runtime artifacts into deploy-only HTML pages so events can be shared, indexed
and later measured without changing the in-app detail panel flow.
"""

from __future__ import annotations

import csv
import html
import json
import os
import re
import shutil
import unicodedata
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional

ROOT = Path(__file__).resolve().parents[1]
EVENTS_JSON = ROOT / "data" / "events.json"
EVENTS_TSV = ROOT / "data" / "events.tsv"
VISUAL_POOL_JSON = ROOT / "data" / "event_visual_pool.json"
EVENTS_DIR = ROOT / "events"
MANIFEST_PATH = ROOT / "data" / "event_detail_pages.json"
SITE_ORIGIN = os.environ.get("SITE_ORIGIN", "https://bocholt-erleben.de").rstrip("/")
RETENTION_DAYS = 60
STYLE_VERSION = "2026-06-22-css-governance-v1"

RE_DATE = re.compile(r"^\d{4}-\d{2}-\d{2}$")
RE_TIME = re.compile(r"\b(\d{1,2})[:.](\d{2})\b")


@dataclass(frozen=True)
class DetailEvent:
    id: str
    slug: str
    detail_path: str
    detail_url: str
    title: str
    date: str
    end_date: str
    time: str
    city: str
    location: str
    category: str
    description: str
    external_url: str
    visual_key: str
    visual_motif: str
    image_src: str
    image_alt: str
    is_past: bool
    noindex: bool


def normalize_text(value: Any) -> str:
    text = "" if value is None else str(value)
    text = text.replace("\u00a0", " ")
    return unicodedata.normalize("NFC", text).strip()


def escape(value: Any) -> str:
    return html.escape(normalize_text(value), quote=True)


def normalize_slug_part(value: Any) -> str:
    text = normalize_text(value).lower()
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    replacements = {
        "ä": "ae",
        "ö": "oe",
        "ü": "ue",
        "ß": "ss",
        "æ": "ae",
        "ø": "oe",
    }
    for src, target in replacements.items():
        text = text.replace(src, target)
    text = re.sub(r"[^a-z0-9]+", "-", text)
    text = re.sub(r"-+", "-", text).strip("-")
    return text[:96].strip("-") or "event"


def normalize_lookup_key(value: Any) -> str:
    return re.sub(
        r"_+",
        "_",
        re.sub(r"[^a-z0-9_]", "", re.sub(r"[\s-]+", "_", normalize_text(value).lower())),
    ).strip("_")


def parse_iso_date(value: Any) -> Optional[date]:
    raw = normalize_text(value)
    if not RE_DATE.match(raw):
        return None
    try:
        return datetime.strptime(raw, "%Y-%m-%d").date()
    except ValueError:
        return None


def format_date_de(value: str) -> str:
    parsed = parse_iso_date(value)
    if not parsed:
        return normalize_text(value)
    return parsed.strftime("%d.%m.%Y")


def format_date_long(value: str) -> str:
    parsed = parse_iso_date(value)
    if not parsed:
        return normalize_text(value)
    weekdays = ["Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag", "Sonntag"]
    months = [
        "Januar", "Februar", "März", "April", "Mai", "Juni",
        "Juli", "August", "September", "Oktober", "November", "Dezember",
    ]
    return f"{weekdays[parsed.weekday()]}, {parsed.day}. {months[parsed.month - 1]} {parsed.year}"


def extract_time(value: str) -> str:
    match = RE_TIME.search(normalize_text(value))
    if not match:
        return ""
    return f"{int(match.group(1)):02d}:{match.group(2)}"


def build_start_date(event: DetailEvent) -> str:
    start_time = extract_time(event.time)
    if start_time:
        return f"{event.date}T{start_time}:00+02:00"
    return event.date


def build_end_date(event: DetailEvent) -> str:
    return event.end_date or event.date


def truncate(value: str, limit: int) -> str:
    text = re.sub(r"\s+", " ", normalize_text(value))
    if len(text) <= limit:
        return text
    return text[: max(0, limit - 1)].rstrip(" .,;:-") + "…"


def normalize_http_url(value: Any) -> str:
    raw = normalize_text(value)
    if not raw:
        return ""
    if raw.startswith("http://") or raw.startswith("https://"):
        return raw
    if raw.startswith("www."):
        return "https://" + raw
    return ""



# === BEGIN BLOCK: EVENT_DETAIL_PUBLIC_SOURCE_URL_GUARD_V1 | Zweck: verhindert direkte Download-/PDF-Links als CTA auf generierten Event-Detailseiten; Umfang: nur URL-Normalisierung fuer Detailseiten, keine Sheet-Schreiboperation ===
DOCUMENT_URL_RE = re.compile(r"(?:\.pdf|\.docx?|\.xlsx?|\.pptx?)(?:$|[?#])", re.I)
DOWNLOAD_QUERY_RE = re.compile(r"(?:^|[?&])download(?:=1|=true|&|$)", re.I)
CURATED_SAFE_SOURCE_URLS_BY_ID = {
    "rosenbergfestival-2026-09-26": "https://www.bocholt.de/Interkulturellewoche",
}


def is_download_document_url(value: Any) -> bool:
    url = normalize_text(value).lower()
    if not url:
        return False
    if DOCUMENT_URL_RE.search(url):
        return True
    if DOWNLOAD_QUERY_RE.search(url):
        return True
    if "/bocholt_media/" in url and any(ext in url for ext in (".pdf", ".doc", ".docx", ".xls", ".xlsx", ".ppt", ".pptx")):
        return True
    return False


def curated_safe_source_url(raw: Dict[str, Any]) -> str:
    event_id = normalize_text(raw.get("id"))
    if event_id in CURATED_SAFE_SOURCE_URLS_BY_ID:
        return CURATED_SAFE_SOURCE_URLS_BY_ID[event_id]
    haystack = " ".join(
        normalize_text(raw.get(key)).lower()
        for key in ("title", "description", "beschreibung", "location", "ort", "kategorie", "category")
    )
    if "rosenbergfestival" in haystack or ("rosenberg" in haystack and "interkulturelle woche" in haystack):
        return "https://www.bocholt.de/Interkulturellewoche"
    return ""


def normalize_public_external_url(raw: Dict[str, Any]) -> str:
    candidate = normalize_http_url(raw.get("url") or raw.get("website") or raw.get("source_url") or raw.get("sourceUrl"))
    if not candidate:
        return ""
    if not is_download_document_url(candidate):
        return candidate
    return normalize_http_url(curated_safe_source_url(raw))
# === END BLOCK: EVENT_DETAIL_PUBLIC_SOURCE_URL_GUARD_V1 ===


def absolute_url(path_or_url: str) -> str:
    raw = normalize_text(path_or_url)
    if not raw:
        return ""
    if raw.startswith("http://") or raw.startswith("https://"):
        return raw
    if raw.startswith("/"):
        return SITE_ORIGIN + raw
    return SITE_ORIGIN + "/" + raw


def detail_path_for_event(raw: Dict[str, Any]) -> str:
    existing = normalize_text(raw.get("detail_path") or raw.get("detailPath"))
    if existing.startswith("/events/") and existing.endswith("/"):
        return existing
    ev_id = normalize_slug_part(raw.get("id"))
    title = normalize_slug_part(raw.get("title") or raw.get("eventName") or "event")
    date_value = normalize_text(raw.get("date") or raw.get("datum"))
    slug = ev_id if ev_id else "-".join(part for part in (title, date_value) if part)
    slug = normalize_slug_part(slug)
    return f"/events/{slug}/"


def read_json_array(path: Path) -> List[Dict[str, Any]]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if isinstance(data, list):
        return [item for item in data if isinstance(item, dict)]
    if isinstance(data, dict) and isinstance(data.get("events"), list):
        return [item for item in data["events"] if isinstance(item, dict)]
    return []


def read_tsv(path: Path) -> List[Dict[str, str]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8", newline="") as handle:
        return [
            {str(k): normalize_text(v) for k, v in row.items() if k is not None}
            for row in csv.DictReader(handle, delimiter="\t")
            if any(normalize_text(v) for v in row.values())
        ]


def clean_generated_event_dirs() -> None:
    if not EVENTS_DIR.exists():
        return
    for child in EVENTS_DIR.iterdir():
        if child.is_dir() and (child / ".generated-event-detail").exists():
            shutil.rmtree(child)


def build_visual_index() -> Dict[str, List[Dict[str, str]]]:
    if not VISUAL_POOL_JSON.exists():
        return {}
    try:
        payload = json.loads(VISUAL_POOL_JSON.read_text(encoding="utf-8"))
    except Exception:
        return {}

    pools = payload.get("pools") if isinstance(payload, dict) else None
    if not isinstance(pools, dict):
        return {}

    index: Dict[str, List[Dict[str, str]]] = {}
    for visual_key, pool in pools.items():
        key = normalize_lookup_key(visual_key)
        images = pool.get("images") if isinstance(pool, dict) else None
        if not key or not isinstance(images, list):
            continue
        ready: List[Dict[str, str]] = []
        for image in images:
            if not isinstance(image, dict):
                continue
            if normalize_text(image.get("status")) != "ready":
                continue
            src = normalize_text(image.get("src"))
            if not src.startswith("/assets/event-visuals/"):
                continue
            ready.append({
                "src": src,
                "alt": normalize_text(image.get("alt")),
                "visual_motif": normalize_lookup_key(image.get("visual_motif") or image.get("visualMotif")),
                "visual_motif_role": normalize_lookup_key(image.get("visual_motif_role") or image.get("visualMotifRole")),
            })
        if ready:
            index[key] = ready
    return index


def choose_visual(raw: Dict[str, Any], visual_index: Dict[str, List[Dict[str, str]]]) -> tuple[str, str]:
    visual_key = normalize_lookup_key(raw.get("visual_key") or raw.get("visualKey") or raw.get("image_visual_key"))
    visual_motif = normalize_lookup_key(raw.get("visual_motif") or raw.get("visualMotif") or raw.get("image_visual_motif"))
    pool = visual_index.get(visual_key) or []

    if pool and visual_motif:
        exact = [image for image in pool if image.get("visual_motif") == visual_motif]
        if exact:
            return exact[0]["src"], exact[0].get("alt") or ""

    if pool:
        fallback = [image for image in pool if image.get("visual_motif_role") == "fallback"]
        selected = (fallback or pool)[0]
        return selected["src"], selected.get("alt") or ""

    return "/assets/event-visuals/default-city-02-16x9.webp", "Symbolisches Stadtmotiv für Bocholt erleben"


def build_detail_event(raw: Dict[str, Any], is_past: bool, noindex: bool, visual_index: Dict[str, List[Dict[str, str]]]) -> Optional[DetailEvent]:
    title = normalize_text(raw.get("title") or raw.get("eventName"))
    event_id = normalize_text(raw.get("id"))
    event_date = normalize_text(raw.get("date") or raw.get("datum"))
    parsed_date = parse_iso_date(event_date)
    if not title or not event_id or not parsed_date:
        return None

    end_date = normalize_text(raw.get("endDate") or raw.get("end_date"))
    if end_date and not parse_iso_date(end_date):
        end_date = ""

    detail_path = detail_path_for_event(raw)
    slug = detail_path.strip("/").split("/")[-1]
    image_src, image_alt = choose_visual(raw, visual_index)
    if not image_alt:
        image_alt = f"Symbolisches Eventbild zu {title}"

    return DetailEvent(
        id=event_id,
        slug=slug,
        detail_path=detail_path,
        detail_url=absolute_url(detail_path),
        title=title,
        date=event_date,
        end_date=end_date,
        time=normalize_text(raw.get("time") or raw.get("uhrzeit") or raw.get("startzeit")),
        city=normalize_text(raw.get("city") or "Bocholt") or "Bocholt",
        location=normalize_text(raw.get("location") or raw.get("ort")),
        category=normalize_text(raw.get("kategorie") or raw.get("category")),
        description=normalize_text(raw.get("description") or raw.get("beschreibung")),
        external_url=normalize_public_external_url(raw),
        visual_key=normalize_lookup_key(raw.get("visual_key") or raw.get("visualKey")),
        visual_motif=normalize_lookup_key(raw.get("visual_motif") or raw.get("visualMotif")),
        image_src=image_src,
        image_alt=image_alt,
        is_past=is_past,
        noindex=noindex,
    )


def build_recent_past_events(active_ids: set[str], visual_index: Dict[str, List[Dict[str, str]]]) -> List[DetailEvent]:
    rows = read_tsv(EVENTS_TSV)
    if not rows:
        return []
    today = date.today()
    cutoff = today - timedelta(days=RETENTION_DAYS)
    out: List[DetailEvent] = []

    for row in rows:
        event_id = normalize_text(row.get("id"))
        if not event_id or event_id in active_ids:
            continue
        start = parse_iso_date(row.get("date"))
        if not start:
            continue
        end = parse_iso_date(row.get("endDate")) or start
        if cutoff <= end < today:
            event = build_detail_event(row, is_past=True, noindex=True, visual_index=visual_index)
            if event:
                out.append(event)
    return out


def event_date_line(event: DetailEvent) -> str:
    if event.end_date and event.end_date != event.date:
        base = f"{format_date_long(event.date)} bis {format_date_long(event.end_date)}"
    else:
        base = format_date_long(event.date)
    if event.time:
        return f"{base} · {event.time}"
    return base


def build_maps_url(event: DetailEvent) -> str:
    query = " ".join(part for part in [event.location, event.city] if part).strip()
    if not query:
        return ""
    from urllib.parse import quote_plus
    return f"https://www.google.com/maps/search/?api=1&query={quote_plus(query)}"


def json_ld(event: DetailEvent) -> str:
    payload: Dict[str, Any] = {
        "@context": "https://schema.org",
        "@type": "Event",
        "name": event.title,
        "startDate": build_start_date(event),
        "endDate": build_end_date(event),
        "eventStatus": "https://schema.org/EventScheduled",
        "eventAttendanceMode": "https://schema.org/OfflineEventAttendanceMode",
        "url": event.detail_url,
        "image": [absolute_url(event.image_src)] if event.image_src else [],
        "location": {
            "@type": "Place",
            "name": event.location or event.city,
            "address": " · ".join(part for part in [event.location, event.city] if part),
        },
    }
    if event.description:
        payload["description"] = truncate(event.description, 280)
    if event.external_url:
        payload["offers"] = {
            "@type": "Offer",
            "url": event.external_url,
            "availability": "https://schema.org/InStock",
        }
    return json.dumps(payload, ensure_ascii=False, indent=2)


def render_page(event: DetailEvent) -> str:
    title = f"{event.title} – Bocholt erleben"
    default_desc = f"{event.title}: Termin, Ort und weiterführende Informationen auf Bocholt erleben."
    desc = truncate(event.description or default_desc, 155)
    image_url = absolute_url(event.image_src)
    maps_url = build_maps_url(event)
    noindex = '<meta name="robots" content="noindex,follow">\n' if event.noindex else ""
    expired_notice = ""
    if event.is_past:
        expired_notice = """
        <section class="event-detail-notice" aria-label="Vergangene Veranstaltung">
          <strong>Diese Veranstaltung ist bereits vorbei.</strong>
          <span>Aktuelle Termine findest du in der Eventübersicht.</span>
        </section>
        """

    description_html = ""
    if event.description:
        description_html = f"""
        <section class="event-detail-section">
          <h2>Worum geht es?</h2>
          <p>{escape(event.description)}</p>
        </section>
        """

    source_action = ""
    if event.external_url and not event.is_past:
        source_action = f"""
        <a class="event-detail-action event-detail-action--primary" href="{escape(event.external_url)}" target="_blank" rel="noopener">Zur Veranstaltungsseite</a>
        """

    route_action = ""
    if maps_url and not event.is_past:
        route_action = f"""
        <a class="event-detail-action" href="{escape(maps_url)}" target="_blank" rel="noopener">Route öffnen</a>
        """

    share_text = f"{event.title}\n{event_date_line(event)}"

    return f"""<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="canonical" href="{escape(event.detail_url)}">
{noindex}<title>{escape(title)}</title>
<meta name="description" content="{escape(desc)}">
<meta property="og:locale" content="de_DE">
<meta property="og:site_name" content="Bocholt erleben">
<meta property="og:type" content="article">
<meta property="og:url" content="{escape(event.detail_url)}">
<meta property="og:title" content="{escape(title)}">
<meta property="og:description" content="{escape(desc)}">
<meta property="og:image" content="{escape(image_url)}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{escape(title)}">
<meta name="twitter:description" content="{escape(desc)}">
<meta name="twitter:image" content="{escape(image_url)}">
<link rel="manifest" href="/manifest.json">
<meta name="theme-color" content="#F3F2E6">
<link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon/icon-32.png">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="stylesheet" href="/css/style.css?v={STYLE_VERSION}">
<script type="application/ld+json">
{json_ld(event)}
</script>
</head>
<body class="event-detail-page">
<header class="app-header">
  <a class="header-left" href="/" aria-label="Bocholt erleben Startseite">
    <img src="/icons/app/icon-192.png" alt="Bocholt erleben Logo" class="app-logo">
    <span class="app-title">Bocholt erleben</span>
  </a>
  <div class="header-right">
    <a class="pwa-install-button header-info-button" href="/events/">Events</a>
  </div>
</header>
<main class="event-detail-main">
  <article class="event-detail-shell" data-event-id="{escape(event.id)}">
    <a class="event-detail-back" href="/events/">← Alle Events</a>
    {expired_notice}
    <figure class="event-detail-hero">
      <img src="{escape(event.image_src)}" alt="{escape(event.image_alt)}" loading="eager" decoding="async" width="1200" height="675">
    </figure>
    <div class="event-detail-content">
      <p class="event-detail-kicker">{escape(event.category or 'Event')}</p>
      <h1>{escape(event.title)}</h1>
      <dl class="event-detail-facts">
        <div><dt>Wann</dt><dd>{escape(event_date_line(event))}</dd></div>
        <div><dt>Wo</dt><dd>{escape(event.location or event.city)}</dd></div>
        <div><dt>Ort</dt><dd>{escape(event.city)}</dd></div>
      </dl>
      {description_html}
      <section class="event-detail-actions" aria-label="Aktionen">
        {source_action}
        {route_action}
        <button class="event-detail-action" type="button" data-share-title="{escape(event.title)}" data-share-text="{escape(share_text)}" data-share-url="{escape(event.detail_url)}">Teilen</button>
      </section>
    </div>
  </article>
</main>
<footer class="event-detail-footer">
  <a href="/events/">Events</a>
  <a href="/aktivitaeten/">Aktivitäten</a>
  <a href="/ueber/">Über Bocholt erleben</a>
</footer>
<script>
(function () {{
  var button = document.querySelector('[data-share-url]');
  if (!button) return;
  button.addEventListener('click', async function () {{
    var title = button.getAttribute('data-share-title') || document.title;
    var text = button.getAttribute('data-share-text') || '';
    var url = button.getAttribute('data-share-url') || location.href;
    try {{
      if (navigator.share) {{
        await navigator.share({{ title: title, text: text, url: url }});
        return;
      }}
    }} catch (error) {{
      if (error && (error.name === 'AbortError' || error.name === 'NotAllowedError')) return;
    }}
    try {{
      if (navigator.clipboard) await navigator.clipboard.writeText([text, url].filter(Boolean).join('\n'));
    }} catch (_) {{}}
  }});
}})();
</script>
</body>
</html>
"""


def write_page(event: DetailEvent) -> None:
    target_dir = EVENTS_DIR / event.slug
    target_dir.mkdir(parents=True, exist_ok=True)
    (target_dir / ".generated-event-detail").write_text("generated by scripts/build-event-detail-pages.py\n", encoding="utf-8")
    (target_dir / "index.html").write_text(render_page(event), encoding="utf-8")


def unique_events(events: Iterable[DetailEvent]) -> List[DetailEvent]:
    seen: set[str] = set()
    out: List[DetailEvent] = []
    for event in events:
        if event.slug in seen:
            continue
        seen.add(event.slug)
        out.append(event)
    return out


def main() -> int:
    if not EVENTS_JSON.exists():
        raise SystemExit(f"Missing {EVENTS_JSON}")

    visual_index = build_visual_index()
    active_raw = read_json_array(EVENTS_JSON)
    active: List[DetailEvent] = []
    for item in active_raw:
        # Ensure active event detail fields exist in the generated feed as source of truth.
        detail_path = detail_path_for_event(item)
        item["detail_path"] = detail_path
        item["detail_url"] = absolute_url(detail_path)
        event = build_detail_event(item, is_past=False, noindex=False, visual_index=visual_index)
        if event:
            active.append(event)

    EVENTS_JSON.write_text(json.dumps(active_raw, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    active_ids = {event.id for event in active}
    recent_past = build_recent_past_events(active_ids, visual_index)
    all_pages = unique_events([*active, *recent_past])

    clean_generated_event_dirs()
    for event in all_pages:
        write_page(event)

    manifest = {
        "generated_at": datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z"),
        "site_origin": SITE_ORIGIN,
        "retention_days": RETENTION_DAYS,
        "active_count": len(active),
        "recent_past_count": len(recent_past),
        "pages": [
            {
                "id": event.id,
                "title": event.title,
                "date": event.date,
                "endDate": event.end_date,
                "slug": event.slug,
                "path": event.detail_path,
                "url": event.detail_url,
                "active": not event.is_past,
                "noindex": event.noindex,
            }
            for event in all_pages
        ],
    }
    MANIFEST_PATH.write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print(f"✅ OK: {len(all_pages)} Event-Detailseiten erzeugt ({len(active)} aktiv, {len(recent_past)} kuerzlich abgelaufen).")
    print(f"✅ Manifest: {MANIFEST_PATH}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
