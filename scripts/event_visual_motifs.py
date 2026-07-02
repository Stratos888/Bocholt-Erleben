# === BEGIN FILE: scripts/event_visual_motifs.py | Zweck: motivgenaue Event-Visual-Zuordnung und Asset-Luecken-Erkennung; Umfang: deterministische Regeln ohne Web-/KI-Zugriff ===
from __future__ import annotations

import json
import re
import unicodedata
from pathlib import Path
from typing import Any, Dict, Iterable, List, Mapping, Optional, Tuple

from event_visual_keys import infer_event_visual_key, normalize_event_visual_key, resolve_event_visual_key

EVENT_VISUAL_POOL_PATH = Path(__file__).resolve().parents[1] / "data" / "event_visual_pool.json"

# Motive sind bewusst aus vorhandenen und vergangenen Events abgeleitet.
# Neue Motive werden nur ergaenzt, wenn echte Events oder klare Wiederholungsfaelle sie brauchen.
# role=fallback bedeutet: fachlich neutral genug fuer den groben visual_key.
EVENT_VISUAL_MOTIF_RULES: Dict[str, Dict[str, Dict[str, str]]] = {
    "textile_machines_industry": {
        "neutral_textile_machines": {"role": "fallback", "label": "Textilmaschinen allgemein"},
        "spinning_mill": {"role": "specific", "label": "Spinnerei"},
        "weaving_mill": {"role": "specific", "label": "Weberei"},
    },
    "textile_exhibition_design": {
        "textile_exhibition_design": {"role": "fallback", "label": "Textil-/Designausstellung"},
    },
    "art_exhibition_gallery": {
        "neutral_art_exhibition": {"role": "fallback", "label": "Kunstausstellung allgemein"},
        "art_market": {"role": "specific", "label": "Kunstmarkt"},
        "creative_exhibition": {"role": "specific", "label": "Kreativausstellung"},
    },
    "local_history_heritage": {
        "neutral_local_history": {"role": "fallback", "label": "Stadtgeschichte / Kulturerbe allgemein"},
        "living_history": {"role": "specific", "label": "Living History"},
        "museum_history_exhibition": {"role": "specific", "label": "historische Ausstellung"},
    },
    "city_tour_history": {
        "neutral_guided_city_tour": {"role": "fallback", "label": "Stadtfuehrung allgemein"},
        "costumed_history_tour": {"role": "specific", "label": "kostuemierte historische Fuehrung"},
        "literary_walk": {"role": "specific", "label": "literarischer Rundgang"},
    },
    "live_music_stage": {
        "neutral_live_stage": {"role": "fallback", "label": "Live-Musik allgemein"},
        "tribute_band": {"role": "specific", "label": "Tribute-/Coverband"},
        "open_air_concert": {"role": "specific", "label": "Open-Air-Konzert"},
        "local_band_concert": {"role": "specific", "label": "Bandkonzert"},
        "music_school_fest": {"role": "specific", "label": "Musikschulfest"},
    },
    "classical_music": {
        "neutral_classical_concert": {"role": "fallback", "label": "Klassikkonzert allgemein"},
        "choir": {"role": "specific", "label": "Chor"},
        "organ_concert": {"role": "specific", "label": "Orgelkonzert"},
        "chamber_music": {"role": "specific", "label": "Kammermusik"},
        "oratorio": {"role": "specific", "label": "Oratorium"},
    },
    "theater_stage": {
        "neutral_theater_stage": {"role": "fallback", "label": "Theaterbuehne allgemein"},
        "theater_play": {"role": "specific", "label": "Theaterstueck"},
        "musical_show": {"role": "specific", "label": "Musical / Show"},
    },
    "comedy_cabaret": {
        "neutral_comedy_stage": {"role": "fallback", "label": "Comedy-/Kabarettbuehne allgemein"},
        "standup_comedy": {"role": "specific", "label": "Stand-up / Comedy"},
        "cabaret_stage": {"role": "specific", "label": "Kabarett"},
    },
    "film_screening": {
        "film_screening": {"role": "fallback", "label": "Filmvorfuehrung"},
    },
    "literature_reading_talk": {
        "neutral_reading_talk": {"role": "fallback", "label": "Lesung / Talk allgemein"},
        "poetry_performance": {"role": "specific", "label": "Poetry / Lyrik-Performance"},
        "author_reading": {"role": "specific", "label": "Autorenlesung"},
        "lecture_talk": {"role": "specific", "label": "Vortrag / Gespraech"},
    },
    "kids_stage_story": {
        "neutral_kids_stage": {"role": "fallback", "label": "Kinderbuehne allgemein"},
        "puppet_theater": {"role": "specific", "label": "Puppentheater"},
        "children_story_reading": {"role": "specific", "label": "Kindergeschichte / Vorlesen"},
    },
    "city_festival_street": {
        "neutral_city_festival": {"role": "fallback", "label": "Stadtfest / Innenstadtaktion allgemein"},
        "shopping_sunday": {"role": "specific", "label": "verkaufsoffener Sonntag"},
        "district_festival": {"role": "specific", "label": "Quartiers-/Stadtteilfest"},
        "children_intercultural_festival": {"role": "specific", "label": "Kinder-/interkulturelles Fest"},
        "open_house_city_services": {"role": "specific", "label": "Tag der offenen Tuer / Stadtservices"},
    },
    "open_air_festival": {
        "neutral_open_air": {"role": "fallback", "label": "Open-Air-Festival allgemein"},
        "lake_festival": {"role": "specific", "label": "See-/Aasee-Festival"},
        "market_square_open_air": {"role": "specific", "label": "Marktplatz-Open-Air"},
    },
    "kirmes_funfair": {
        "kirmes_funfair": {"role": "fallback", "label": "Kirmes / Volksfest"},
    },
    "parade_festzug": {
        "neutral_parade": {"role": "fallback", "label": "Festzug / Parade allgemein"},
        "carnival_parade": {"role": "specific", "label": "Rosenmontagszug / Karneval"},
        "csd_pride_parade": {"role": "specific", "label": "CSD / Pride-Parade"},
        "marching_band_procession": {"role": "specific", "label": "Musikzug / Blaskapellen-Festzug"},
    },
    "shooting_festival_tradition": {
        "shooting_festival_tradition": {"role": "fallback", "label": "Schuetzenfest / Tradition"},
    },
    "market_stalls": {
        "neutral_market_stalls": {"role": "fallback", "label": "Marktstaende allgemein"},
        "flea_market": {"role": "specific", "label": "Troedel-/Flohmarkt"},
        "fabric_market": {"role": "specific", "label": "Stoffmarkt"},
        "krammarkt_general": {"role": "specific", "label": "Krammarkt"},
        "seasonal_martinsmarkt": {"role": "specific", "label": "Martinsmarkt / saisonaler Markt"},
    },
    "book_market": {
        "book_market": {"role": "fallback", "label": "Buecher-/Boekenmarkt"},
    },
    "food_drink_festival": {
        "neutral_food_festival": {"role": "fallback", "label": "Food-/Genussfestival allgemein"},
        "wine_festival": {"role": "specific", "label": "Weinfest"},
        "street_food_festival": {"role": "specific", "label": "Street-Food-Festival"},
        "tasting_event": {"role": "specific", "label": "Verkostung"},
    },
    "country_fair_rural": {
        "country_fair_rural": {"role": "fallback", "label": "Country Fair / Landpartie"},
    },
    "family_play_outdoor": {
        "neutral_family_play_outdoor": {"role": "fallback", "label": "Familie / Spiel draussen allgemein"},
        "playfountain_water_splash": {"role": "specific", "label": "PlayFountain / Wasserspielflaeche"},
        "children_flea_market": {"role": "specific", "label": "Kinderflohmarkt"},
        "egg_hunt_family": {"role": "specific", "label": "Ostern / Eiersuche"},
    },
    "creative_making_workshop": {
        "neutral_creative_workshop": {"role": "fallback", "label": "Kreativ-/Mitmachworkshop allgemein"},
        "craft_workshop": {"role": "specific", "label": "Handwerk / Craft"},
        "escape_game": {"role": "specific", "label": "Escape Game"},
        "game_day": {"role": "specific", "label": "Spieletag"},
    },
    "learning_science_workshop": {
        "neutral_learning_workshop": {"role": "fallback", "label": "Lernen / Wissenschaft allgemein"},
        "science_school": {"role": "specific", "label": "Junge Uni / Wissenschaftsschule"},
        "ecology_workshop": {"role": "specific", "label": "Oekologie-Workshop"},
    },
    "dance_music_workshop": {
        "dance_workshop": {"role": "fallback", "label": "Tanz-/Musikworkshop"},
    },
    "active_route_tour": {
        "neutral_active_tour": {"role": "fallback", "label": "aktive Tour allgemein"},
        "guided_walk": {"role": "specific", "label": "geführter Spaziergang / Wanderung"},
        "guided_bike_tour": {"role": "specific", "label": "geführte Radtour"},
        "segway_tour": {"role": "specific", "label": "Segwaytour"},
    },
    "running_event": {
        "running_event": {"role": "fallback", "label": "Laufveranstaltung"},
    },
    "cycling_event": {
        "cycling_event": {"role": "fallback", "label": "Radveranstaltung / Radsport"},
        "cycling_race": {"role": "specific", "label": "Radrennen"},
        "bike_fair": {"role": "review", "label": "Fahrradmesse / Fahrradaktion"},
    },
    "indoor_sport_competition": {
        "neutral_indoor_sport": {"role": "fallback", "label": "Indoor-Sport allgemein"},
        "badminton": {"role": "specific", "label": "Badminton"},
        "handball": {"role": "specific", "label": "Handball"},
        "volleyball": {"role": "specific", "label": "Volleyball"},
        "table_tennis": {"role": "specific", "label": "Tischtennis"},
        "darts": {"role": "specific", "label": "Darts"},
        "fencing": {"role": "specific", "label": "Fechten"},
    },
    "nature_learning_wildlife": {
        "neutral_nature_learning": {"role": "fallback", "label": "Natur / Umweltbildung allgemein"},
        "wildlife_bats": {"role": "specific", "label": "Fledermausfuehrung"},
        "orchid_plant_tour": {"role": "specific", "label": "Orchideen / Pflanzen"},
        "animal_park_family": {"role": "specific", "label": "Tierpark / Familiennatur"},
        "walking_nature_tour": {"role": "specific", "label": "Naturspaziergang"},
    },
    "evening_social_party": {
        "evening_social_party": {"role": "fallback", "label": "Abend / Tanz / Party"},
    },
    "business_messe_info": {
        "neutral_info_fair": {"role": "fallback", "label": "Info-/Messeveranstaltung allgemein"},
        "business_fair": {"role": "specific", "label": "Unternehmermesse"},
        "health_career_fair": {"role": "specific", "label": "Gesundheits-/Berufemesse"},
        "association_fair": {"role": "specific", "label": "Vereinsmesse"},
        "info_evening": {"role": "specific", "label": "Infoabend"},
    },
    "vehicle_classic": {
        "classic_car_meet": {"role": "fallback", "label": "Oldtimer- / Fahrzeugtreffen"},
    },
    "default_city": {
        "default_city": {"role": "fallback", "label": "allgemeines Stadtmotiv"},
    },
}


def _norm(value: object) -> str:
    text = str(value or "").replace("\u00a0", " ")
    text = unicodedata.normalize("NFC", text).strip().lower()
    return text


def _clean_motif(value: object) -> str:
    raw = _norm(value).replace("-", "_").replace(" ", "_")
    raw = re.sub(r"[^a-z0-9_]+", "", raw)
    return raw


def _haystack(title: object = "", description: object = "", category: object = "", location: object = "") -> str:
    return " ".join([_norm(title), _norm(description), _norm(category), _norm(location)]).strip()


def _match(haystack: str, *patterns: str) -> bool:
    return any(re.search(pattern, haystack, flags=re.IGNORECASE) for pattern in patterns)


def allowed_event_visual_motifs(visual_key: object) -> Dict[str, Dict[str, str]]:
    key = normalize_event_visual_key(visual_key)
    return EVENT_VISUAL_MOTIF_RULES.get(key, {})


def normalize_event_visual_motif(value: object, visual_key: object = "") -> str:
    motif = _clean_motif(value)
    if not motif:
        return ""

    key = normalize_event_visual_key(visual_key)
    if key:
        return motif if motif in EVENT_VISUAL_MOTIF_RULES.get(key, {}) else ""

    matches = [motif for rules in EVENT_VISUAL_MOTIF_RULES.values() if motif in rules]
    return motif if matches else ""


def fallback_event_visual_motif(visual_key: object) -> str:
    key = normalize_event_visual_key(visual_key)
    for motif, meta in EVENT_VISUAL_MOTIF_RULES.get(key, {}).items():
        if meta.get("role") == "fallback":
            return motif
    return ""


def event_visual_motif_role(visual_key: object, motif: object) -> str:
    key = normalize_event_visual_key(visual_key)
    normalized_motif = normalize_event_visual_motif(motif, key)
    return EVENT_VISUAL_MOTIF_RULES.get(key, {}).get(normalized_motif, {}).get("role", "")


def infer_event_visual_motif(
    title: object = "",
    description: object = "",
    category: object = "",
    location: object = "",
    visual_key: object = "",
) -> str:
    key = normalize_event_visual_key(visual_key) or infer_event_visual_key(title, description, category, location)
    hay = _haystack(title, description, category, location)
    if not hay:
        return fallback_event_visual_motif(key)

    if key == "textile_machines_industry":
        if _match(hay, r"\b(spinnerei|spinnen|spinnmaschine)\b"):
            return "spinning_mill"
        if _match(hay, r"\b(weberei|weben|webstuhl|webmaschine|maschinen[- ]?mittwoch|drossel[- ]?donnerstag)\b"):
            return "weaving_mill"

    if key == "art_exhibition_gallery":
        if _match(hay, r"\b(kunstmarkt|cityart)\b"):
            return "art_market"
        if _match(hay, r"\b(kreativausstellung|kreativ[- ]?schmiede)\b"):
            return "creative_exhibition"

    if key == "local_history_heritage":
        if _match(hay, r"\b(living history|in szene gesetzt)\b"):
            return "museum_history_exhibition"
        if _match(hay, r"\b(ausstellung|museum|farb|archiv|historisch|geschichte)\b"):
            return "museum_history_exhibition"

    if key == "city_tour_history":
        if _match(hay, r"\b(anno 1900|kiepenkerl|klumpenf(ü|ue)hrung|nachtw(ä|ae)chter|kost(ü|ue)m)\b"):
            return "costumed_history_tour"
        if _match(hay, r"\b(dichter|literarisch|poesie|literatur)\b"):
            return "literary_walk"
        if _match(hay, r"\b(sagensafari|sagenf(ü|ue)hrung|sagenhafte|themenf(ü|ue)hrung|szenische stadtf(ü|ue)hrung)\b"):
            return "neutral_guided_city_tour"

    if key == "live_music_stage":
        if _match(hay, r"\b(tribute|coverband|floyd|floydbox|coltplay|abba|rocking stones)\b"):
            return "tribute_band"
        if _match(hay, r"\b(open[- ]?air|stadtturm|bahia|borken open air)\b"):
            return "open_air_concert"
        if _match(hay, r"\b(musikschulfest|musikschule)\b"):
            return "music_school_fest"
        if _match(hay, r"\b(bands?|konzert|live|rock|pop|jazz|song[- ]?slam|songslam|unplugged)\b"):
            return "local_band_concert"

    if key == "classical_music":
        if _match(hay, r"\b(oratorium|elias)\b"):
            return "oratorio"
        if _match(hay, r"\b(orgel|organ|pipes)\b"):
            return "organ_concert"
        if _match(hay, r"\b(chor|chorkonzert|madrigalchor|domsingknaben|nacht der ch(ö|oe)re)\b"):
            return "choir"
        if _match(hay, r"\b(quartett|quartet|kammermusik|ensemble)\b"):
            return "chamber_music"

    if key == "theater_stage":
        if _match(hay, r"\b(musical|show)\b"):
            return "musical_show"
        if _match(hay, r"\b(theater|schauspiel|b(ü|ue)hnenst(ü|ue)ck|frankenstein|dritte mann|kanaren|lerche)\b"):
            return "theater_play"

    if key == "comedy_cabaret":
        if _match(hay, r"\b(kabarett|kleinkunst|marlies blume)\b"):
            return "cabaret_stage"
        if _match(hay, r"\b(comedy|lachnacht|stand[- ]?up|tobi freudenthal)\b"):
            return "standup_comedy"

    if key == "literature_reading_talk":
        if _match(hay, r"\b(poetry|lyrik|poesie|gedichte)\b"):
            return "poetry_performance"
        if _match(hay, r"\b(lesung|autor|buchvorstellung|gedichte)\b"):
            return "author_reading"
        if _match(hay, r"\b(kanaren|sieben auf einen streich)\b"):
            return "neutral_reading_talk"
        if _match(hay, r"\b(vortrag|talk|gespr(ä|ae)ch|info)\b"):
            return "neutral_reading_talk"

    if key == "kids_stage_story":
        if _match(hay, r"\b(puppenspieltage|puppenspiel|puppentheater|pettersson|figurentheater|ei der welt)\b"):
            return "puppet_theater"
        if _match(hay, r"\b(vorlese|geschichte|geschichten|m(ä|ae)rchen|b(ä|ae)ren|bären[- ]?geschichten|baeren[- ]?geschichten)\b"):
            return "neutral_kids_stage"

    if key == "city_festival_street":
        if _match(hay, r"\b(verkaufsoffener sonntag|maiensonntag|lichtersonntag|sonntagsshopping)\b"):
            return "shopping_sunday"
        if _match(hay, r"\b(rosenberg|quartierfest|stadtteilfest)\b"):
            return "district_festival"
        if _match(hay, r"\b(weltkindertag|interkulturell|kinderfest)\b"):
            return "children_intercultural_festival"
        if _match(hay, r"\b(tag der offenen t(ü|ue)r|tourist[- ]?info|stadtb(ü|ue)cherei|archiv)\b"):
            return "open_house_city_services"

    if key == "open_air_festival":
        if _match(hay, r"\b(aasee[- ]?festival|see[- ]?festival)\b"):
            return "lake_festival"
        if _match(hay, r"\b(marktplatz|markt platz|kulturtage)\b"):
            return "market_square_open_air"

    if key == "parade_festzug":
        if _match(hay, r"\b(csd|pride)\b"):
            return "csd_pride_parade"
        if _match(hay, r"\b(blaskapelle|musikzug|spielmannszug|fanfarenzug|tambourcorps|marschkapelle)\b"):
            return "marching_band_procession"
        if _match(hay, r"\b(rosenmontagszug|rosenmontag|karneval|karnevalszug)\b"):
            return "neutral_parade"

    if key == "market_stalls":
        if _match(hay, r"\b(stoffmarkt|stoff market|stoffe)\b"):
            return "fabric_market"
        if _match(hay, r"\b(flohmarkt|tr(ö|oe)delmarkt|hobbytr(ö|oe)del|interkultureller tr(ö|oe)del)\b"):
            return "flea_market"
        if _match(hay, r"\b(krammarkt|grensmarkt|grenzmarkt)\b"):
            return "krammarkt_general"
        if _match(hay, r"\b(martinsmarkt)\b"):
            return "seasonal_martinsmarkt"

    if key == "food_drink_festival":
        if _match(hay, r"\b(weinfest|wijnfeest|wein|wine)\b"):
            return "wine_festival"
        if _match(hay, r"\b(street[- ]?food|city food festival|food festival)\b"):
            return "street_food_festival"
        if _match(hay, r"\b(tasting|verkostung|probe)\b"):
            return "tasting_event"

    if key == "family_play_outdoor":
        if _match(hay, r"\b(playfountain|play fountain|wasserspa(ß|ss)|wasserspiel|wasserspielfl(ä|ae)che|wasserfont(ä|ae)ne|spritzbereich|spritzfl(ä|ae)che)\b"):
            return "playfountain_water_splash"
        if _match(hay, r"\b(kinderflohmarkt|kindertr(ö|oe)del)\b"):
            return "children_flea_market"
        if _match(hay, r"\b(ostern|osterei|eiersuche)\b"):
            return "egg_hunt_family"

    if key == "creative_making_workshop":
        if _match(hay, r"\b(escape|locked)\b"):
            return "escape_game"
        if _match(hay, r"\b(pokemon|pok(é|e)mon|spieltag|game day)\b"):
            return "game_day"
        if _match(hay, r"\b(handwerk|craft|bastel|kreativ|n(ä|ae)hen|t(ö|oe)pfer|malen|zeichnen)\b"):
            return "craft_workshop"

    if key == "learning_science_workshop":
        if _match(hay, r"\b(junge uni|summerschool|summer school|wissenschaft|forsch)\b"):
            return "science_school"
        if _match(hay, r"\b(ökosystem|oekosystem|umwelt|natur)\b"):
            return "ecology_workshop"

    if key == "active_route_tour":
        if _match(hay, r"\b(segway(?:tour(?:en)?)?)\b"):
            return "neutral_active_tour"
        if _match(hay, r"\b(fahrradtour|radtour|rad[- ]?tour|bike tour)\b"):
            return "guided_bike_tour"
        if _match(hay, r"\b(wanderung|wandern|spaziergang|walk)\b"):
            return "guided_walk"

    if key == "cycling_event":
        if _match(hay, r"\b(fahrradfr(ü|ue)hling|fahrradmesse|fahrradaktion)\b"):
            return "bike_fair"
        if _match(hay, r"\b(m(ü|ue)nsterlandgiro|radrennen|profistart|radsport)\b"):
            return "cycling_race"

    if key == "indoor_sport_competition":
        if _match(hay, r"\b(fecht|fencing|degen|florett|s(ä|ae)bel)\b"):
            return "fencing"
        if _match(hay, r"\b(darts?|dartturnier|dart[- ]?trophy)\b"):
            return "darts"
        if _match(hay, r"\b(tischtennis|table tennis|pingpong|ping pong)\b"):
            return "table_tennis"
        if _match(hay, r"\b(badminton|federball)\b"):
            return "badminton"
        if _match(hay, r"\b(handball)\b"):
            return "handball"
        if _match(hay, r"\b(volleyball)\b"):
            return "volleyball"

    if key == "nature_learning_wildlife":
        if _match(hay, r"\b(fledermaus|bat night|bats?)\b"):
            return "wildlife_bats"
        if _match(hay, r"\b(orchidee|orchideen|pflanzen|plant)\b"):
            return "orchid_plant_tour"
        if _match(hay, r"\b(anholter schweiz|tierpark|wildpark)\b"):
            return "animal_park_family"
        if _match(hay, r"\b(naturf(ü|ue)hrung|naturtour|sagensafari|wandert|wanderung|wasser|pr(ö|oe)bstingsee)\b"):
            return "walking_nature_tour"

    if key == "business_messe_info":
        if _match(hay, r"\b(gesundheitsberufemesse|gesundheitsberufe|pflegeberufe)\b"):
            return "health_career_fair"
        if _match(hay, r"\b(gesundheitstage|gesundheitsprogramm|gesundheitsmesse|gesundheitsaktion|gesundheitsforum)\b"):
            return "neutral_info_fair"
        if _match(hay, r"\b(markterschließung|markterschliessung|unternehmermesse|business|unternehmen|gr(ü|ue)ndung|netzwerk)\b"):
            return "business_fair"
        if _match(hay, r"\b(vereinsmesse|verein|vereine)\b"):
            return "association_fair"
        if _match(hay, r"\b(infoabend|informationsabend|kindertagespflege|beratung)\b"):
            return "info_evening"

    return fallback_event_visual_motif(key)


def load_event_visual_pool(path: Optional[Path] = None) -> Dict[str, Any]:
    pool_path = path or EVENT_VISUAL_POOL_PATH
    try:
        payload = json.loads(pool_path.read_text(encoding="utf-8"))
    except FileNotFoundError:
        return {"pools": {}}
    if not isinstance(payload, dict):
        return {"pools": {}}
    return payload


def ready_motifs_for_key(pool_payload: Mapping[str, Any], visual_key: object) -> Dict[str, int]:
    key = normalize_event_visual_key(visual_key)
    pools = pool_payload.get("pools", {}) if isinstance(pool_payload, Mapping) else {}
    pool = pools.get(key, {}) if isinstance(pools, Mapping) else {}
    images = pool.get("images", []) if isinstance(pool, Mapping) else []
    counts: Dict[str, int] = {}
    for image in images if isinstance(images, list) else []:
        if not isinstance(image, Mapping):
            continue
        if str(image.get("status") or "").strip() != "ready":
            continue
        motif = normalize_event_visual_motif(image.get("visual_motif", ""), key)
        if motif:
            counts[motif] = counts.get(motif, 0) + 1
    return counts


def resolve_event_visual_asset_status(
    visual_key: object,
    visual_motif: object,
    pool_payload: Mapping[str, Any],
) -> str:
    key = normalize_event_visual_key(visual_key)
    motif = normalize_event_visual_motif(visual_motif, key)
    if not key or not motif:
        return "review"

    role = event_visual_motif_role(key, motif)
    if role == "review":
        return "review"

    ready_counts = ready_motifs_for_key(pool_payload, key)
    if ready_counts.get(motif, 0) > 0:
        return "ok"

    return "needs_asset"


def infer_event_visual_fit(
    title: object = "",
    description: object = "",
    category: object = "",
    location: object = "",
    visual_key: object = "",
    visual_motif: object = "",
    pool_payload: Optional[Mapping[str, Any]] = None,
) -> Dict[str, str]:
    key = resolve_event_visual_key(title, description, category, location, visual_key, visual_motif)
    motif = normalize_event_visual_motif(visual_motif, key) or infer_event_visual_motif(
        title=title,
        description=description,
        category=category,
        location=location,
        visual_key=key,
    )
    pool = pool_payload if pool_payload is not None else load_event_visual_pool()
    return {
        "visual_key": key,
        "visual_motif": motif,
        "visual_asset_status": resolve_event_visual_asset_status(key, motif, pool),
    }


# === END FILE: scripts/event_visual_motifs.py ===
