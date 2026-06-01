# === BEGIN FILE: scripts/event_visual_keys.py | Zweck: zentrale, kostenlose Visual-Key-Zuordnung fuer Event-Bilder; Umfang: deterministische Regelableitung aus Titel, Beschreibung, Kategorie und Ort ohne Web-/KI-Bildsuche ===
from __future__ import annotations

import re

ALLOWED_EVENT_VISUAL_KEYS = {
    "market_food",
    "city_festival",
    "music_stage",
    "culture_exhibition",
    "theater_show",
    "kids_family",
    "creative_workshop",
    "sport_active",
    "outdoor_nature",
    "city_walk",
    "evening_social",
    "default_city",
}


def _norm(value: object) -> str:
    return str(value or "").strip().lower()


def normalize_event_visual_key(value: object) -> str:
    raw = _norm(value).replace("-", "_").replace(" ", "_")
    raw = re.sub(r"[^a-z0-9_]+", "", raw)
    return raw if raw in ALLOWED_EVENT_VISUAL_KEYS else ""


def infer_event_visual_key(title: object = "", description: object = "", category: object = "", location: object = "") -> str:
    """Return a stable visual key for event image pool selection.

    This intentionally does not search the web and does not assign a concrete image.
    It maps event metadata to a controlled local/prefetched visual pool.
    """

    haystack = " ".join([_norm(title), _norm(description), _norm(category), _norm(location)])

    checks = [
        ("market_food", r"\b(food|streetfood|street food|imbiss|essen|wein|bier|trödel|troedel|flohmarkt|wochenmarkt|markt|kirmes|messe)\b|festival"),
        ("city_festival", r"\b(stadtfest|cityfest|innenstadtfest|sommerfest|familienfest|fest|feier|aktionstag|tag der offenen tür|verkaufsoffen)\b"),
        ("music_stage", r"\b(konzert|live[ -]?musik|band|chor|orchester|dj|k[- ]?pop|tanzen|dance|musikschule|bühne|buehne)\b"),
        ("theater_show", r"\b(theater|show|kabarett|comedy|zauber|zirkus|variet[eé]|poetry|lesung|bühnenprogramm|buehnenprogramm)\b"),
        ("culture_exhibition", r"\b(ausstellung|museum|kunst|galerie|textilwerk|kultur|geschichte|führung|fuehrung|vernissage|vortrag|textil)\b"),
        ("creative_workshop", r"\b(workshop|kurs|bastel|zeichnen|malen|kreativ|handwerk|nähen|naehen|töpfer|toepfer|maker|escape game|rätsel|raetsel|bauen|bau eines)\b"),
        ("sport_active", r"\b(sport|lauf|rennen|turnier|rad|radtour|bike|fitness|bewegung|yoga|schwimmen|fußball|fussball)\b"),
        ("outdoor_nature", r"\b(natur|wald|venn|park|aasee|see|draußen|draussen|outdoor|ökosystem|oekosystem|insektenhotel|nistkasten|wander|spazier|garten)\b"),
        ("city_walk", r"\b(stadtrundgang|stadtführung|stadtfuehrung|innenstadt|kubaai|promenade|tour|route|rundgang|türme|tuerme|kirche)\b"),
        ("evening_social", r"\b(kneipe|quiz|pub|bar|party|afterwork|abend|nacht|tanzabend|treff)\b"),
        ("kids_family", r"\b(kinder|familie|familien|jugend|schüler|schueler|junge uni|kulturrucksack|pokemon|pokémon|jugendfarm|ferienspaß|ferienspass)\b"),
    ]

    for key, pattern in checks:
        if re.search(pattern, haystack, flags=re.IGNORECASE):
            return key

    cat = _norm(category)
    if "märkte" in cat or "maerkte" in cat or "feste" in cat:
        return "city_festival"
    if "musik" in cat:
        return "music_stage"
    if "kinder" in cat or "familie" in cat:
        return "kids_family"
    if "kultur" in cat or "kunst" in cat:
        return "culture_exhibition"
    if "sport" in cat:
        return "sport_active"
    if "natur" in cat or "draußen" in cat or "draussen" in cat:
        return "outdoor_nature"
    if "innenstadt" in cat or "leben" in cat:
        return "city_walk"

    return "default_city"
# === END FILE: scripts/event_visual_keys.py ===
