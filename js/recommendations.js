/* === BEGIN FILE: js/recommendations.js | Zweck: isolierte Recommendation-Schicht fuer Punkt 9 "Fuer dich in Bocholt"; Umfang: Normalisierung, Scoring und Sortierung ohne DOM-Zugriff und ohne Auto-Start === */
(function () {
  "use strict";

  const DEFAULT_CONTEXT = Object.freeze({
    mode: "for_you",
    interests: [],
    weather: "unknown",
    saved: [],
    dismissed: [],
    now: null,
    rainRisk: "unknown",
    outdoorFit: "unknown",
    showersLikely: false,
    isHoliday: false,
    isNonBusinessDay: false,
    holidayName: ""
  });

  const MODE_ALIASES = Object.freeze({
    forYou: "for_you",
    for_you: "for_you",
    today: "today",
    evening: "evening",
    weekend: "weekend",
    family: "family",
    outdoor: "outdoor",
    rain: "rain"
  });

  const WEIGHT_SCORE = Object.freeze({
    core: 18,
    high: 14,
    normal: 6,
    fallback: -4,
    unknown: 0
  });

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function asArray(value) {
    if (Array.isArray(value)) {
      return unique(value.map(asString).filter(Boolean));
    }

    const text = asString(value);
    if (!text) return [];

    return unique(
      text
        .split(/[,;|]/)
        .map(asString)
        .filter(Boolean)
    );
  }

  function unique(values) {
    const out = [];

    values.forEach((value) => {
      if (value && !out.includes(value)) {
        out.push(value);
      }
    });

    return out;
  }

  function toSet(values) {
    return new Set(asArray(values));
  }

  function hasAny(values, candidates) {
    const set = toSet(values);
    return asArray(candidates).some((candidate) => set.has(candidate));
  }

  function readObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : {};
  }

  function readRecommendation(value) {
    const obj = readObject(value);
    return readObject(obj.recommendation);
  }

  function hasClosureRisk(item) {
    return window.OpeningStatus?.hasClosureRisk?.(item) === true;
  }

  function readFilterValues(item, group) {
    const filter = readObject(item?.filter);
    return asArray(filter[group]);
  }

  function categoryInterestTags(category) {
    const value = asString(category);
    const tags = [];

    if (value === "Kinder & Familie" || value === "Freizeit & Familie") {
      tags.push("Familie");
    }
    if (value === "Natur & Draußen") {
      tags.push("Draußen", "Natur");
    }
    if (value === "Sport & Bewegung") {
      tags.push("Sport & Bewegung");
    }
    if (value === "Kultur & Kunst" || value === "Kultur") {
      tags.push("Kultur");
    }
    if (value === "Musik & Bühne") {
      tags.push("Musik");
    }
    if (value === "Märkte & Feste") {
      tags.push("Wochenende");
    }

    return tags;
  }

  function parseDateLocal(value) {
    const text = asString(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const date = new Date(year, month, day);

    if (
      date.getFullYear() !== year ||
      date.getMonth() !== month ||
      date.getDate() !== day
    ) {
      return null;
    }

    return date;
  }

  function startOfDay(date) {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function diffDays(from, to) {
    const a = startOfDay(from).getTime();
    const b = startOfDay(to).getTime();
    return Math.round((b - a) / 86400000);
  }

  function isSameDay(a, b) {
    return diffDays(a, b) === 0;
  }

  function isWeekend(date) {
    const day = date.getDay();
    return day === 0 || day === 6;
  }

  function parseStartMinutes(time) {
    const match = /^(\d{1,2})[:.](\d{2})/.exec(asString(time));
    if (!match) return null;

    const hours = Number(match[1]);
    const minutes = Number(match[2]);

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) return null;

    return hours * 60 + minutes;
  }

  /* === BEGIN BLOCK: RECOMMENDATION_EVENT_HAS_PASSED_FIX_V1 | Zweck: korrigiert die Past-Logik fuer kuenftige Events; Umfang: nur Event-Scoring, keine UI-/DOM-Aenderung === */
  function eventHasPassed(item, now) {
    const startDate = parseDateLocal(item.date);
    const endDate = parseDateLocal(item.endDate) || startDate;

    if (!startDate) return false;

    const endOffset = diffDays(now, endDate);

    if (endOffset < 0) return true;
    if (endOffset > 0) return false;

    const startMinutes = parseStartMinutes(item.time);
    if (startMinutes == null) return false;

    const currentMinutes = now.getHours() * 60 + now.getMinutes();

    return currentMinutes > startMinutes + 180;
  }
  /* === END BLOCK: RECOMMENDATION_EVENT_HAS_PASSED_FIX_V1 === */

  function eventDateScore(item, context) {
    if (item.type !== "event") return 0;

    const now = context.now;
    const startDate = parseDateLocal(item.date);
    const endDate = parseDateLocal(item.endDate) || startDate;

    if (!startDate || !endDate) return -6;
    if (eventHasPassed(item, now)) return -10000;

    const days = diffDays(now, startDate);
    let score = 0;

    if (days === 0 || (diffDays(now, endDate) >= 0 && diffDays(startDate, now) >= 0)) {
      score += 42;
    } else if (days === 1) {
      score += 22;
    } else if (days >= 2 && days <= 7) {
      score += 12;
    } else if (days > 7 && days <= 21) {
      score += 4;
    } else if (days > 21) {
      score -= 6;
    }

    if (context.mode === "today" && !isSameDay(startDate, now) && !(diffDays(startDate, now) >= 0 && diffDays(now, endDate) >= 0)) {
      score -= 40;
    }

    if (context.mode === "weekend" && (isWeekend(startDate) || isWeekend(endDate))) {
      score += 28;
    }

    if (context.mode === "evening") {
      const startMinutes = parseStartMinutes(item.time);
      if (startMinutes != null && startMinutes >= 17 * 60) {
        score += 28;
      } else {
        score -= 10;
      }
    }

    return score;
  }

  function normalizeContext(context) {
    const input = readObject(context);
    const mode = MODE_ALIASES[input.mode] || DEFAULT_CONTEXT.mode;

    return {
      mode,
      interests: asArray(input.interests || DEFAULT_CONTEXT.interests),
      weather: asString(input.weather || DEFAULT_CONTEXT.weather) || "unknown",
      saved: asArray(input.saved || DEFAULT_CONTEXT.saved),
      dismissed: asArray(input.dismissed || DEFAULT_CONTEXT.dismissed),
      now: input.now instanceof Date ? input.now : new Date(),
      rainRisk: asString(input.rainRisk || DEFAULT_CONTEXT.rainRisk) || DEFAULT_CONTEXT.rainRisk,
      outdoorFit: asString(input.outdoorFit || DEFAULT_CONTEXT.outdoorFit) || DEFAULT_CONTEXT.outdoorFit,
      showersLikely: input.showersLikely === true,
      isHoliday: input.isHoliday === true,
      isNonBusinessDay: input.isNonBusinessDay === true,
      holidayName: asString(input.holidayName || DEFAULT_CONTEXT.holidayName)
    };
  }

  function itemKey(item) {
    return `${item.type}:${item.id}`;
  }

  function normalizeEvent(event, index) {
    const obj = readObject(event);
    const recommendation = readRecommendation(obj);

    const category = asString(obj.kategorie || obj.category);
    const situationTags = unique([
      ...asArray(recommendation.situation_tags),
      ...categoryInterestTags(category).filter((tag) => tag === "Draußen")
    ]);

    const audienceTags = unique([
      ...asArray(recommendation.audience_tags),
      ...categoryInterestTags(category).filter((tag) => tag !== "Draußen" && tag !== "Natur")
    ]);

    const interestTags = unique([
      ...situationTags,
      ...audienceTags,
      ...categoryInterestTags(category)
    ]);

    return {
      type: "event",
      id: asString(obj.id) || `event-${index}`,
      title: asString(obj.title || obj.eventName),
      description: asString(obj.description || obj.beschreibung),
      category,
      location: asString(obj.location || obj.ort),
      url: asString(obj.url || obj.link),
      mapsTarget: asString(obj.location || obj.ort),
      image: asString(obj.image),
      visualKey: asString(obj.visual_key || obj.image_visual_key),
      date: asString(obj.date || obj.datum),
      endDate: asString(obj.endDate),
      time: asString(obj.time || obj.uhrzeit || obj.startzeit),
      availability: "scheduled",
      situationTags,
      audienceTags,
      interestTags,
      weatherProfile: asArray(recommendation.weather_profile),
      timeProfile: [],
      planningLevel: asString(recommendation.planning_level) || "unknown",
      costLevel: asString(recommendation.cost_level) || "unknown",
      holidayPolicy: asString(recommendation.holiday_policy || recommendation.holidayPolicy),
      recommendationWeight: asString(recommendation.recommendation_weight) || "normal",
      reasonLabels: [],
      raw: obj,
      sortIndex: Number.isFinite(Number(index)) ? Number(index) : 0
    };
  }

  function normalizeActivity(offer, index) {
    const obj = readObject(offer);
    const recommendation = readRecommendation(obj);

    const situationTags = unique([
      ...readFilterValues(obj, "situation"),
      ...asArray(recommendation.situation_tags)
    ]);

    const interestTags = unique([
      ...asArray(recommendation.interest_tags),
      ...categoryInterestTags(obj.kategorie),
      ...situationTags
    ]);

    return {
      type: "activity",
      id: asString(obj.id) || `activity-${index}`,
      title: asString(obj.title),
      description: asString(obj.description),
      category: asString(obj.kategorie || obj.category),
      location: asString(obj.location),
      url: asString(obj.url),
      mapsTarget: asString(obj.maps_query || obj.maps_label || obj.location),
      image: asString(obj.image),
      visualKey: asString(obj.visual_key || obj.image_visual_key),
      imageQuality: asString(obj.image_quality || obj.image_status || obj.visual_status),
      date: "",
      endDate: "",
      time: "",
      availability: asString(recommendation.availability) || "unknown",
      situationTags,
      audienceTags: [],
      interestTags,
      weatherProfile: asArray(recommendation.weather_profile),
      timeProfile: asArray(recommendation.time_profile),
      planningLevel: asString(recommendation.planning_level) || "unknown",
      costLevel: asString(recommendation.cost_level) || "unknown",
      holidayPolicy: asString(recommendation.holiday_policy || recommendation.holidayPolicy),
      recommendationWeight: asString(recommendation.recommendation_weight) || "normal",
      reasonLabels: [],
      raw: obj,
      sortIndex: Number.isFinite(Number(index)) ? Number(index) : 0
    };
  }

  function normalizeItems(input) {
    const source = readObject(input);
    const events = Array.isArray(source.events) ? source.events : [];
    const offers = Array.isArray(source.offers) ? source.offers : [];

    return [
      ...events.map(normalizeEvent),
      ...offers.map(normalizeActivity)
    ].filter((item) => item.id && item.title);
  }

  /* === BEGIN BLOCK: RECOMMENDATION_REASON_LABELS_WEATHER_AWARE_V1 | Zweck: priorisiert nutzernahe Wetter- und Situationshinweise fuer die Premium-Home; Umfang: ersetzt nur buildReasonLabels(), keine Scoring-/DOM-Aenderung === */
  function buildReasonLabels(item, context) {
    const labels = [];

    if (context.isHoliday && hasClosureRisk(item)) {
      labels.push("Feiertag: prüfen");
    } else if (context.isNonBusinessDay && hasClosureRisk(item)) {
      labels.push("Öffnungszeiten prüfen");
    }

    if (item.type === "event") {
      const date = parseDateLocal(item.date);
      if (date && isSameDay(date, context.now)) labels.push("Heute");
      if (date && isWeekend(date)) labels.push("Wochenende");
    }

    if (context.weather === "rain" && (
      hasAny(item.weatherProfile, ["indoor", "weather_independent"]) ||
      (hasAny(item.weatherProfile, "rain_ok") && !hasAny(item.interestTags, "Draußen"))
    )) {
      labels.push("Gut bei Regen");
    }

    if (context.weather === "hot" && hasAny(item.interestTags, ["Baden", "Wasser"])) {
      labels.push("Gut bei Wärme");
    }

    if (context.weather === "cold" && hasAny(item.weatherProfile, ["indoor", "weather_independent", "rain_ok"])) {
      labels.push("Drinnen möglich");
    }

    if (context.weather === "windy" && hasAny(item.weatherProfile, ["indoor", "weather_independent", "rain_ok"])) {
      labels.push("Eher geschützt");
    }

    if (item.type === "activity") {
      if (item.availability === "always" && !(context.isNonBusinessDay && hasClosureRisk(item))) labels.push("Immer möglich");
      if (item.availability === "opening_hours_check") labels.push("Öffnungszeiten prüfen");
      if (item.availability === "seasonal") labels.push("Saisonal");
    }

    if (hasAny(item.interestTags, "Familie")) labels.push("Familie");
    if (hasAny(item.interestTags, "Draußen")) labels.push("Draußen");
    if (context.weather !== "rain" && hasAny(item.weatherProfile, ["rain_ok", "indoor", "weather_independent"])) {
      labels.push("Bei Regen möglich");
    }

    return unique(labels).slice(0, 3);
  }
  /* === END BLOCK: RECOMMENDATION_REASON_LABELS_WEATHER_AWARE_V1 === */

  function scoreItem(item, rawContext) {
    const context = normalizeContext(rawContext);
    const key = itemKey(item);

    if (context.dismissed.includes(key) || context.dismissed.includes(item.id)) {
      return -10000;
    }

    let score = 0;

    score += item.type === "event" ? 18 : 10;
    score += WEIGHT_SCORE[item.recommendationWeight] ?? WEIGHT_SCORE.unknown;
    score += eventDateScore(item, context);

    if (context.saved.includes(key) || context.saved.includes(item.id)) {
      score += 24;
    }

    const interests = toSet(context.interests);
    item.interestTags.forEach((tag) => {
      if (interests.has(tag)) score += 12;
    });

    if (context.mode === "family" && hasAny(item.interestTags, "Familie")) {
      score += 34;
    }

    if (context.mode === "outdoor" && hasAny(item.interestTags, "Draußen")) {
      score += 28;
    }

    if (context.mode === "rain") {
      if (hasAny(item.weatherProfile, ["indoor", "weather_independent"])) {
        score += 34;
      } else if (hasAny(item.weatherProfile, "rain_ok") && !hasAny(item.interestTags, "Draußen")) {
        score += 16;
      } else if (hasAny(item.weatherProfile, "rain_bad") || hasAny(item.interestTags, "Draußen")) {
        score -= 24;
      }
    }

    if (context.weather === "rain") {
      if (hasAny(item.weatherProfile, ["indoor", "weather_independent"])) {
        score += 16;
      } else if (hasAny(item.weatherProfile, "rain_ok") && !hasAny(item.interestTags, "Draußen")) {
        score += 8;
      } else if (hasAny(item.weatherProfile, "rain_bad") || hasAny(item.interestTags, "Draußen")) {
        score -= 18;
      }
    }

    if (context.weather === "dry" && hasAny(item.interestTags, "Draußen")) {
      score += 8;
    }

    /* === BEGIN BLOCK: RECOMMENDATION_WEATHER_SCORING_HOT_COLD_WINDY_V1 | Zweck: nutzt Wetterklassen hot/cold/windy als echten Rankingfaktor fuer Premium-Home; Umfang: erweitert nur scoreItem(), keine DOM-/UI-Aenderung === */
    if (context.weather === "hot") {
      if (hasAny(item.interestTags, ["Baden", "Wasser"])) {
        score += 30;
      } else if (hasAny(item.weatherProfile, ["indoor", "weather_independent", "rain_ok"])) {
        score += 10;
      } else if (hasAny(item.interestTags, "Draußen")) {
        score -= 6;
      }
    }

    if (context.weather === "cold") {
      if (hasAny(item.weatherProfile, ["indoor", "weather_independent", "rain_ok"])) {
        score += 22;
      } else if (hasAny(item.interestTags, "Draußen")) {
        score -= 14;
      }
    }

    if (context.weather === "windy") {
      if (hasAny(item.interestTags, ["Baden", "Wasser"])) {
        score -= 20;
      } else if (hasAny(item.interestTags, "Draußen")) {
        score -= 8;
      }

      if (hasAny(item.weatherProfile, ["indoor", "weather_independent", "rain_ok"])) {
        score += 10;
      }
    }
    /* === END BLOCK: RECOMMENDATION_WEATHER_SCORING_HOT_COLD_WINDY_V1 === */

    if (item.type === "activity") {
      if (item.availability === "always") score += 6;
      if (item.availability === "opening_hours_check") score -= 3;
      if (hasAny(item.timeProfile, "short_spontaneous")) score += 7;
      if (context.mode === "weekend" && hasAny(item.timeProfile, "weekend")) score += 14;

      if (context.isNonBusinessDay && hasClosureRisk(item)) {
        score -= 70;
      }
    }

    if (!item.location) score -= 8;
    if (!item.url) score -= 6;

    return score;
  }

  function withScore(item, context) {
    const normalizedContext = normalizeContext(context);

    return {
      ...item,
      score: scoreItem(item, normalizedContext),
      reasonLabels: buildReasonLabels(item, normalizedContext)
    };
  }

  function sortRecommendations(items, context) {
    return (Array.isArray(items) ? items : [])
      .map((item) => withScore(item, context))
      .filter((item) => item.score > -10000)
      .sort((a, b) => {
        if (b.score !== a.score) return b.score - a.score;
        if (a.type !== b.type) return a.type === "event" ? -1 : 1;
        return a.sortIndex - b.sortIndex;
      });
  }

  function createRecommendations(input, context) {
    return sortRecommendations(normalizeItems(input), context);
  }

  window.BERecommendations = {
    normalizeEvent,
    normalizeActivity,
    normalizeItems,
    scoreItem,
    sortRecommendations,
    createRecommendations
  };
}());
/* === END FILE: js/recommendations.js === */