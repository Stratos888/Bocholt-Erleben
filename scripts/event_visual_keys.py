# === BEGIN FILE: scripts/event_visual_keys.py | Zweck: zentrale, kostenlose Visual-Key-Zuordnung fuer Event-Bilder; Umfang: deterministische Regelableitung aus Titel, Beschreibung, Kategorie und Ort ohne Web-/KI-Bildsuche ===
from __future__ import annotations

import re

ALLOWED_EVENT_VISUAL_KEYS = {
    "textile_machines_industry",
    "textile_exhibition_design",
    "art_exhibition_gallery",
    "local_history_heritage",
    "city_tour_history",
    "live_music_stage",
    "classical_music",
    "theater_stage",
    "comedy_cabaret",
    "film_screening",
    "literature_reading_talk",
    "kids_stage_story",
    "city_festival_street",
    "open_air_festival",
    "kirmes_funfair",
    "parade_festzug",
    "shooting_festival_tradition",
    "market_stalls",
    "book_market",
    "food_drink_festival",
    "country_fair_rural",
    "family_play_outdoor",
    "creative_making_workshop",
    "learning_science_workshop",
    "dance_music_workshop",
    "active_route_tour",
    "running_event",
    "cycling_event",
    "indoor_sport_competition",
    "nature_learning_wildlife",
    "evening_social_party",
    "business_messe_info",
    "vehicle_classic",
    "default_city",
}

LEGACY_VISUAL_KEY_MAP = {
    "market_food": "food_drink_festival",
    "city_festival": "city_festival_street",
    "music_stage": "live_music_stage",
    "culture_exhibition": "local_history_heritage",
    "theater_show": "theater_stage",
    "kids_family": "family_play_outdoor",
    "creative_workshop": "creative_making_workshop",
    "sport_active": "active_route_tour",
    "outdoor_nature": "nature_learning_wildlife",
    "city_walk": "city_tour_history",
    "evening_social": "evening_social_party",
}


def _norm(value: object) -> str:
    return str(value or "").strip().lower()


def _clean_key(value: object) -> str:
    raw = _norm(value).replace("-", "_").replace(" ", "_")
    raw = re.sub(r"[^a-z0-9_]+", "", raw)
    return raw


def normalize_event_visual_key(value: object) -> str:
    raw = _clean_key(value)
    if raw in ALLOWED_EVENT_VISUAL_KEYS:
        return raw
    return LEGACY_VISUAL_KEY_MAP.get(raw, "")


def infer_event_visual_key(title: object = "", description: object = "", category: object = "", location: object = "") -> str:
    """Return a stable V3.1 visual key for event image pool selection.

    Priority contract:
    - strong event type markers first
    - then format/topic markers
    - category fallback only after specific rules
    - location never overrides a clearly detected event type
    """

    title_text = _norm(title)
    description_text = _norm(description)
    category_text = _norm(category)
    location_text = _norm(location)
    haystack = " ".join([title_text, description_text, category_text, location_text]).strip()

    if not haystack:
        return "default_city"

    def match(*patterns: str) -> bool:
        return any(re.search(pattern, haystack, flags=re.IGNORECASE) for pattern in patterns)

    # 1) Sehr starke, eindeutige Eventtypen zuerst.
    if match(r"\b(oldtimer|classic cars?|veteranenfahrzeug|fahrzeugtreffen|autoschau)\b"):
        return "vehicle_classic"

    if match(r"\b(schützenfest|schuetzenfest|thronball|vogelschießen|vogelschiessen|schützenverein|schuetzenverein)\b"):
        return "shooting_festival_tradition"

    if match(r"\b(rosenmontag|karnevalszug|karnevalsumzug|festzug|umzug|parade|csd)\b"):
        return "parade_festzug"

    if match(r"\b(kirmes|jahrmarkt|funfair|fahrgeschäft|fahrgeschaeft|riesenrad|autoscooter)\b"):
        return "kirmes_funfair"

    if match(r"\b(farm\s*&\s*country\s*fair|country\s*fair|landpartie|landmesse)\b"):
        return "country_fair_rural"

    if match(r"\b(boekenmarkt|büchermarkt|buechermarkt|buchmarkt|antiquariat)\b"):
        return "book_market"

    if match(r"\b(weinfest|wijnfeest|wine tasting|street ?food|food ?festival|city food festival|genuss|kulinarik|kulinarisch)\b"):
        return "food_drink_festival"

    if match(r"\b(krammarkt|stoffmarkt|flohmarkt|trödelmarkt|troedelmarkt|wochenmarkt|grenzmarkt|marktstände|marktstaende)\b"):
        return "market_stalls"

    # 2) Kultur/Textil: Eventtyp vor Location.
    if match(r"\b(maschinen[- ]?mittwoch|drossel[- ]?donnerstag|spinnerei|weberei|industriekultur|textilmaschinen?)\b"):
        return "textile_machines_industry"

    if match(r"\b(ibena|textile leidenschaft|textilausstellung|textildesign|stoffdesign|behind beauty)\b"):
        return "textile_exhibition_design"

    if match(r"\b(kunstausstellung|kunstmarkt|vernissage|galerie|kreativausstellung|schloss ringenberg)\b"):
        return "art_exhibition_gallery"

    if match(r"\b(stadtführung|stadtfuehrung|rundgang|nachtwächter|nachtwaechter|kiepenkerl|promenadenführung|promenadenfuehrung)\b"):
        return "city_tour_history"

    # 3) Bühne, Sprache, Film.
    if match(r"\b(kindertheater|puppenspiel|kinderoper|vorlese(stunde|zeit)|märchenerzäh|maerchenerzaehl)\b"):
        return "kids_stage_story"

    if match(r"\b(comedy|kabarett|lachnacht|stand[- ]?up|kleinkunst)\b"):
        return "comedy_cabaret"

    if match(r"\b(film|kino|open[- ]?air[- ]?kino|kinoabend|filmabend|dokufilm)\b"):
        return "film_screening"

    if match(r"\b(lesung|literatur|autorengespräch|autorengespraech|gedichte|poetry|poesie)\b"):
        return "literature_reading_talk"

    if match(r"\b(orgel|oratorium|chor|chorkonzert|kammermusik|klassik|quartett|quartet|kirchenkonzert|sinfonie)\b"):
        return "classical_music"

    if match(r"\b(theater|schauspiel|bühnenstück|buehnenstueck|bühne|buehne|musical)\b"):
        return "theater_stage"

    if match(r"\b(konzert|live[- ]?musik|band|tribute|unplugged|dj|open[- ]?air[- ]?konzert)\b"):
        return "live_music_stage"

    # 4) Feste, Stadtleben, Familie.
    if match(r"\b(aasee[- ]?festival|open[- ]?air[- ]?festival|festivalgelände|festivalgelaende|kulturtage)\b"):
        return "open_air_festival"

    if match(r"\b(stadtfest|cityfest|verkaufsoffen|lichtersonntag|aktionstag|innenstadtfest|familienfest|tag der offenen tür|tag der offenen tuer)\b"):
        return "city_festival_street"

    if match(r"\b(kindertrödel|kinderflohmarkt|kinderfest|ostereiersuche|jugendfarm|familienprogramm|wasserspaß|wasserspass|spielfest)\b"):
        return "family_play_outdoor"

    # 5) Workshops, Lernen, Mitmachen.
    if match(r"\b(k[- ]?pop|sing ?& ?dance|dance workshop|tanzworkshop|musikworkshop|dance camp)\b"):
        return "dance_music_workshop"

    if match(r"\b(junge uni|wissenschaft|ökosystem|oekosystem|experiment|forsch(er|en)|lernwerkstatt)\b"):
        return "learning_science_workshop"

    if match(r"\b(workshop|escape game|pokemon|pokémon|bastel|kreativ|malen|zeichnen|handwerk|nähen|naehen|töpfer|toepfer|maker)\b"):
        return "creative_making_workshop"

    # 6) Sport, Tour, Natur.
    if match(r"\b(citylauf|abendlauf|lauf|marathon|halbmarathon|running)\b"):
        return "running_event"

    if match(r"\b(giro|radrennen|radsport|bike race|fahrradfrühling|fahrradfruehling)\b"):
        return "cycling_event"

    if match(r"\b(darts|fechten|turnier|meisterschaft|hallen(sport)?|wettkampf)\b"):
        return "indoor_sport_competition"

    if match(r"\b(fahrradtour|radtour|segway|wanderung|wandern|spaziergang|tour)\b"):
        return "active_route_tour"

    if match(r"\b(fledermaus|naturführung|naturfuehrung|wildlife|wildpark|wasser|pröbstingsee|proebstingsee|aasee|garten|natur|umwelt)\b"):
        return "nature_learning_wildlife"

    # 7) Sonstige klare Bildwelten.
    if match(r"\b(tanz in den mai|karibische nacht|party|afterwork|abendveranstaltung|abendformat|nacht)\b"):
        return "evening_social_party"

    if match(r"\b(messe|berufsmesse|unternehmermesse|infoabend|informationsabend|gesundheitsberufemesse|netzwerkabend|gründung|gruendung)\b"):
        return "business_messe_info"

    if match(r"\b(archiv|farb|stadtgeschichte|heimat|historisch|geschichte|museum)\b"):
        return "local_history_heritage"

    # 8) Kategorie-Fallbacks.
    cat = category_text

    if "musik" in cat or "bühne" in cat or "buehne" in cat:
        return "live_music_stage"

    if "kinder" in cat or "familie" in cat:
        return "family_play_outdoor"

    if "märkte" in cat or "maerkte" in cat:
        return "market_stalls"

    if "feste" in cat:
        return "city_festival_street"

    if "sport" in cat:
        return "indoor_sport_competition"

    if "natur" in cat or "draußen" in cat or "draussen" in cat:
        return "nature_learning_wildlife"

    if "kultur" in cat or "kunst" in cat:
        return "local_history_heritage"

    if "innenstadt" in cat or "leben" in cat:
        return "city_festival_street"

    return "default_city"


# === END FILE: scripts/event_visual_keys.py ===
