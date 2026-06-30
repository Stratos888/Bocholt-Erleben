/* === BEGIN FILE: js/today-home.js | Zweck: Controller und Renderer fuer die neue Heute-/Recommendation-Home; Umfang: Datenladen, Wetter-/Profil-Kontext, gemischter Empfehlungsfeed ohne bestehende Events-/Activities-Suchseiten zu steuern === */
(function () {
  "use strict";

  const ROOT_ID = "today-root";
  const FEED_ID = "today-feed";
  const STATUS_ID = "today-status";
  const WEATHER_ID = "today-weather-note";
  const TODAY_IMPRESSIONS_STORAGE_KEY = "bocholt_erleben.today_home_impressions.v1";
  const TODAY_IMPRESSIONS_RETENTION_DAYS = 14;
  let state = {
    events: [],
    offers: [],
    items: [],
    eventVisualPools: Object.freeze({}),
    activityVisualPoolsByOfferId: Object.freeze({}),
    weatherContext: null,
    mode: "for_you"
  };

  const renderedVisualsByItem = new WeakMap();

  function qs(selector, root = document) {
    return root.querySelector(selector);
  }

  function qsa(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
  }

  function hasStorage() {
    try {
      return typeof window !== "undefined" && !!window.localStorage;
    } catch (_) {
      return false;
    }
  }

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function escapeHtml(value) {
    const text = asString(value);
    return text.replace(/[&<>"']/g, (char) => {
      switch (char) {
        case "&": return "&amp;";
        case "<": return "&lt;";
        case ">": return "&gt;";
        case '"': return "&quot;";
        case "'": return "&#39;";
        default: return char;
      }
    });
  }

  function normalizeUrl(raw) {
    const value = asString(raw);
    if (!value) return "";

    try {
      return new URL(value, window.location.origin).href;
    } catch (_) {
      return "";
    }
  }

  /* === BEGIN BLOCK: TODAY_HOME_DESKTOP_DIRECT_TARGETS_V1 | Zweck: setzt die geklaerte Detailpanel-Policy um: Mobile nutzt Detailpanels, Desktop oeffnet Cards direkt outbound bzw. zum primaeren Ziel; Umfang: nur Today-Home Click-Routing === */
  function isDesktopViewport() {
    return window.matchMedia("(min-width: 900px)").matches;
  }

  function normalizeHttpUrl(raw) {
    const value = asString(raw);
    if (!value) return "";

    try {
      return new URL(value).href;
    } catch (_) {
      try {
        return new URL(`https://${value}`).href;
      } catch (_) {
        return "";
      }
    }
  }

  function buildMapsUrl(raw) {
    const query = asString(raw);
    if (!query) return "";

    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
  }

  function desktopPrimaryUrl(item) {
    const source = item?.raw || item || {};

    if (item?.type === "event") {
      return normalizeHttpUrl(
        item?.url ||
        source.url ||
        source.link ||
        source.website ||
        source.sourceUrl ||
        source.source_url ||
        ""
      );
    }

    if (item?.type === "activity") {
      return normalizeHttpUrl(
        item?.url ||
        source.url ||
        source.website ||
        source.website_url ||
        source.booking_url ||
        ""
      );
    }

    return normalizeHttpUrl(item?.url || source.url || "");
  }

  function desktopFallbackTarget(item) {
    const source = item?.raw || item || {};

    if (item?.type === "activity") {
      return buildMapsUrl(
        item?.mapsTarget ||
        source.maps_query ||
        source.maps_label ||
        source.address ||
        source.location ||
        item?.location ||
        item?.title ||
        ""
      );
    }

    if (item?.type === "event") {
      return buildMapsUrl(
        item?.mapsTarget ||
        source.location ||
        source.ort ||
        source.locationName ||
        item?.location ||
        ""
      );
    }

    return "";
  }

  /* === BEGIN BLOCK: TODAY_HOME_REPORTING_TARGET_ANALYTICS_V1 | Zweck: gibt bei Today-Desktop-Direktklicks vorhandene Reporting-Ziele an das Nutzwerttracking weiter; Umfang: ersetzt nur openDesktopTarget(item) === */
  function openDesktopTarget(item) {
    const primaryUrl = desktopPrimaryUrl(item);
    const targetUrl = primaryUrl || desktopFallbackTarget(item);
    if (!targetUrl) return false;

    try {
      if (window.BEAnalytics?.trackOutboundClick) {
        const reportingTargetPayload = window.BEAnalytics?.buildReportingTargetPayload
          ? window.BEAnalytics.buildReportingTargetPayload(item?.raw || item || {})
          : {};

        window.BEAnalytics.trackOutboundClick({
          outboundType: primaryUrl ? "website" : "maps",
          entityType: item?.type || "today_card",
          entityId: asString(item?.id || item?.raw?.id),
          entityTitle: asString(item?.title || item?.raw?.title),
          destinationUrl: targetUrl,
          ...reportingTargetPayload
        });
      }

      window.open(targetUrl, "_blank", "noopener,noreferrer");
      return true;
    } catch (_) {
      return false;
    }
  }
  /* === END BLOCK: TODAY_HOME_REPORTING_TARGET_ANALYTICS_V1 === */
  /* === END BLOCK: TODAY_HOME_DESKTOP_DIRECT_TARGETS_V1 === */

  async function fetchJsonNoStore(url, required) {
    try {
      const response = await fetch(url, { cache: "no-store" });
      if (!response.ok) {
        throw new Error(`${url} failed: ${response.status}`);
      }
      return response.json();
    } catch (error) {
      if (required) throw error;
      console.warn("[TodayHome] Optional source failed:", url, error);
      return null;
    }
  }

  function extractEvents(payload) {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.events)) return payload.events;
    if (Array.isArray(payload?.data?.events)) return payload.data.events;
    return [];
  }

  function extractOffers(payload) {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.offers)) return payload.offers;
    if (Array.isArray(payload?.data?.offers)) return payload.data.offers;
    return [];
  }

  /* === BEGIN BLOCK: TODAY_EVENT_VISUAL_POOL_HELPERS_V1 | Zweck: bereitet ready Event-Visual-Pools fuer Today-Karten vor; Umfang: nur status=ready, stabile Auswahl, keine planned-Assets === */
  function buildReadyEventVisualPools(payload) {
    const pools = payload && typeof payload === "object" ? payload.pools : null;
    const out = Object.create(null);

    if (!pools || typeof pools !== "object") {
      return Object.freeze(out);
    }

    Object.entries(pools).forEach(([visualKey, pool]) => {
      const key = asString(visualKey);
      const images = Array.isArray(pool?.images) ? pool.images : [];

      const ready = images
        .filter((image) => image && image.status === "ready")
        .map((image) => {
          const src = normalizeUrl(image.src);
          if (!src) return null;

          return Object.freeze({
            id: asString(image.id),
            src,
            alt: asString(image.alt),
            status: asString(image.status),
            source: asString(image.source),
            sourceType: asString(image.source_type || image.sourceType),
            rightsStatus: asString(image.rights_status || image.rightsStatus),
            reviewStatus: asString(image.review_status || image.reviewStatus),
            author: asString(image.author),
            license: asString(image.license),
            licenseUrl: asString(image.license_url || image.licenseUrl),
            sourceTitle: asString(image.source_title || image.sourceTitle),
            sourcePage: asString(image.source_page || image.sourcePage || image.source_url || image.sourceUrl),
            downloadUrl: asString(image.download_url || image.downloadUrl),
            credit: asString(image.credit || image.attribution),
            modifications: asString(image.modifications),
            isSymbolic: Boolean(image.is_symbolic || image.isSymbolic),
            isDocumentary: Boolean(image.is_documentary || image.isDocumentary),
            publicNote: asString(image.public_note || image.publicNote),
            note: asString(image.note),
            visualMotif: asString(image.visual_motif || image.visualMotif),
            visualMotifRole: asString(image.visual_motif_role || image.visualMotifRole)
          });
        })
        .filter(Boolean);

      if (key && ready.length) {
        out[key] = Object.freeze(ready);
      }
    });

    return Object.freeze(out);
  }

  /* === BEGIN BLOCK: TODAY_ACTIVITY_VISUAL_POOL_HELPERS_V1 | Zweck: ordnet ready Activity-Premium-Visuals den Today-Home-Activity-Cards zu; Umfang: activity_visual_pool nach primary_offer_ids, offers.image bleibt nur Fallback === */
  function buildReadyActivityVisualPoolsByOfferId(payload) {
    const pools = payload && typeof payload === "object" ? payload.pools : null;
    const out = Object.create(null);

    if (!pools || typeof pools !== "object") {
      return Object.freeze(out);
    }

    Object.entries(pools).forEach(([poolKey, pool]) => {
      const offerIds = Array.isArray(pool?.primary_offer_ids)
        ? pool.primary_offer_ids.map(asString).filter(Boolean)
        : [];
      const images = Array.isArray(pool?.images) ? pool.images : [];

      const ready = images
        .filter((image) => image && image.status === "ready")
        .map((image) => {
          const src = normalizeUrl(image.src);
          if (!src) return null;

          return Object.freeze({
            id: asString(image.id),
            src,
            alt: asString(image.alt),
            poolKey: asString(poolKey),
            status: asString(image.status),
            source: asString(image.source),
            sourceType: asString(image.source_type || image.sourceType),
            rightsStatus: asString(image.rights_status || image.rightsStatus),
            reviewStatus: asString(image.review_status || image.reviewStatus),
            author: asString(image.author),
            license: asString(image.license),
            licenseUrl: asString(image.license_url || image.licenseUrl),
            sourceTitle: asString(image.source_title || image.sourceTitle),
            sourcePage: asString(image.source_page || image.sourcePage || image.source_url || image.sourceUrl),
            downloadUrl: asString(image.download_url || image.downloadUrl),
            credit: asString(image.credit || image.attribution),
            modifications: asString(image.modifications),
            isSymbolic: Boolean(image.is_symbolic || image.isSymbolic),
            isDocumentary: Boolean(image.is_documentary || image.isDocumentary),
            publicNote: asString(image.public_note || image.publicNote),
            note: asString(image.note)
          });
        })
        .filter(Boolean);

      if (!offerIds.length || !ready.length) return;

      offerIds.forEach((offerId) => {
        out[offerId] = Object.freeze(ready);
      });
    });

    return Object.freeze(out);
  }
  /* === END BLOCK: TODAY_ACTIVITY_VISUAL_POOL_HELPERS_V1 === */

  function stableHash(value) {
    const textValue = asString(value);
    let hash = 2166136261;

    for (let index = 0; index < textValue.length; index += 1) {
      hash ^= textValue.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }

    return hash >>> 0;
  }

  function selectVisualFromPool(pool, seed, usedImages) {
    if (!Array.isArray(pool) || !pool.length) {
      return null;
    }

    const start = stableHash(seed) % pool.length;

    for (let offset = 0; offset < pool.length; offset += 1) {
      const candidate = pool[(start + offset) % pool.length];
      if (!candidate?.src) continue;

      if (!usedImages || !usedImages.has(candidate.src) || offset === pool.length - 1) {
        if (usedImages) usedImages.add(candidate.src);
        return Object.freeze({
          ...candidate,
          src: candidate.src,
          alt: asString(candidate.alt)
        });
      }
    }

    return null;
  }

  const EVENT_VISUAL_FALLBACK_MOTIFS = new Set([
    "book_market",
    "classic_car_meet",
    "country_fair_rural",
    "cycling_event",
    "dance_workshop",
    "default_city",
    "evening_social_party",
    "film_screening",
    "kirmes_funfair",
    "neutral_active_tour",
    "neutral_art_exhibition",
    "neutral_city_festival",
    "neutral_classical_concert",
    "neutral_comedy_stage",
    "neutral_creative_workshop",
    "neutral_family_play_outdoor",
    "neutral_food_festival",
    "neutral_guided_city_tour",
    "neutral_indoor_sport",
    "neutral_info_fair",
    "neutral_kids_stage",
    "neutral_learning_workshop",
    "neutral_live_stage",
    "neutral_local_history",
    "neutral_market_stalls",
    "neutral_nature_learning",
    "neutral_open_air",
    "neutral_parade",
    "neutral_reading_talk",
    "neutral_textile_machines",
    "neutral_theater_stage",
    "running_event",
    "shooting_festival_tradition",
    "textile_exhibition_design"
  ]);

  function eventVisualKey(item) {
    return asString(item?.visualKey || item?.visual_key || item?.image_visual_key);
  }

  function eventVisualMotif(item) {
    return asString(item?.visualMotif || item?.visual_motif || item?.image_visual_motif);
  }

  function isFallbackEventVisual(visual) {
    const motif = asString(visual?.visualMotif || visual?.visual_motif);
    if (!motif) return true;

    const role = asString(visual?.visualMotifRole || visual?.visual_motif_role);
    return role === "fallback" || EVENT_VISUAL_FALLBACK_MOTIFS.has(motif);
  }

  function eventVisualCandidatePool(pool, visualMotif) {
    if (!Array.isArray(pool) || !pool.length) return [];

    const motif = asString(visualMotif);
    if (motif) {
      const exact = pool.filter((candidate) => asString(candidate?.visualMotif) === motif);
      if (exact.length) return exact;
    }

    const neutral = pool.filter(isFallbackEventVisual);
    return neutral.length ? neutral : pool;
  }

  function activityVisualPool(item) {
    if (item?.type !== "activity") return null;

    const offerIds = [
      item?.id,
      item?.raw?.id,
      item?.offer_id,
      item?.raw?.offer_id
    ].map(asString).filter(Boolean);

    for (const offerId of offerIds) {
      const pool = state.activityVisualPoolsByOfferId[offerId];
      if (Array.isArray(pool) && pool.length) {
        return pool;
      }
    }

    return null;
  }

  function resolveItemVisual(item, usedImages) {
    if (item?.type === "activity") {
      const pool = activityVisualPool(item);
      const visual = selectVisualFromPool(
        pool,
        [
          asString(item.id),
          asString(item.raw?.id),
          asString(item.title),
          "activity"
        ].join("|"),
        usedImages
      );

      if (visual) return visual;
    }

    const ownImage = normalizeUrl(item?.image);
    if (ownImage) {
      if (usedImages) usedImages.add(ownImage);
      return Object.freeze({
        src: ownImage,
        alt: asString(item?.imageAlt || item?.image_alt || item?.alt)
      });
    }

    if (item?.type !== "event") {
      return null;
    }

    const visualKey = eventVisualKey(item);
    const visualMotif = eventVisualMotif(item);
    const pool = visualKey ? state.eventVisualPools[visualKey] : null;
    const candidatePool = eventVisualCandidatePool(pool, visualMotif);

    return selectVisualFromPool(
      candidatePool,
      [
        asString(item.id),
        asString(item.date),
        asString(item.endDate),
        asString(item.title),
        visualKey,
        visualMotif
      ].join("|"),
      usedImages
    );
  }

  function resolveItemImage(item, usedImages) {
    const visual = resolveItemVisual(item, usedImages);
    return visual?.src || "";
  }
  /* === END BLOCK: TODAY_EVENT_VISUAL_POOL_HELPERS_V1 === */

  function dedupeEvents(events) {
    const seen = new Set();
    const out = [];

    (Array.isArray(events) ? events : []).forEach((event) => {
      const idKey = asString(event?.id);
      const fallbackKey = [
        asString(event?.title || event?.eventName).toLowerCase(),
        asString(event?.date || event?.datum),
        asString(event?.time || event?.uhrzeit).toLowerCase(),
        asString(event?.location || event?.ort).toLowerCase()
      ].join("|");

      const key = idKey || fallbackKey;
      if (!key || seen.has(key)) return;

      seen.add(key);
      out.push(event);
    });

    return out;
  }

  function getPreferences() {
    return window.BEUserPreferences || null;
  }

  function getRecommendations() {
    return window.BERecommendations || null;
  }

  function getWeather() {
    return window.BEWeatherContext || null;
  }

  function dateKey(date) {
    const value = date instanceof Date ? date : new Date();
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, "0")}-${String(value.getDate()).padStart(2, "0")}`;
  }

  function addDays(date, days) {
    const value = date instanceof Date ? date : new Date();
    return new Date(value.getFullYear(), value.getMonth(), value.getDate() + Number(days || 0));
  }

  function easterSunday(year) {
    const a = year % 19;
    const b = Math.floor(year / 100);
    const c = year % 100;
    const d = Math.floor(b / 4);
    const e = b % 4;
    const f = Math.floor((b + 8) / 25);
    const g = Math.floor((b - f + 1) / 3);
    const h = (19 * a + b - d - g + 15) % 30;
    const i = Math.floor(c / 4);
    const k = c % 4;
    const l = (32 + 2 * e + 2 * i - h - k) % 7;
    const m = Math.floor((a + 11 * h + 22 * l) / 451);
    const month = Math.floor((h + l - 7 * m + 114) / 31);
    const day = ((h + l - 7 * m + 114) % 31) + 1;

    return new Date(year, month - 1, day);
  }

  function nrwHolidayMap(year) {
    const easter = easterSunday(year);
    const entries = [
      [new Date(year, 0, 1), "Neujahr"],
      [addDays(easter, -2), "Karfreitag"],
      [addDays(easter, 1), "Ostermontag"],
      [new Date(year, 4, 1), "Tag der Arbeit"],
      [addDays(easter, 39), "Christi Himmelfahrt"],
      [addDays(easter, 50), "Pfingstmontag"],
      [addDays(easter, 60), "Fronleichnam"],
      [new Date(year, 9, 3), "Tag der Deutschen Einheit"],
      [new Date(year, 10, 1), "Allerheiligen"],
      [new Date(year, 11, 25), "1. Weihnachtstag"],
      [new Date(year, 11, 26), "2. Weihnachtstag"]
    ];

    return entries.reduce((map, [date, label]) => {
      map[dateKey(date)] = label;
      return map;
    }, Object.create(null));
  }

  function buildDayContext(now) {
    const date = now instanceof Date ? now : new Date();
    const holidayName = nrwHolidayMap(date.getFullYear())[dateKey(date)] || "";
    const isSunday = date.getDay() === 0;

    return {
      isHoliday: !!holidayName,
      holidayName,
      isSunday,
      isNonBusinessDay: !!holidayName || isSunday,
      dayType: holidayName ? "holiday" : isSunday ? "sunday" : "weekday"
    };
  }

  function getProfile() {
    const prefs = getPreferences();
    return prefs?.getProfile ? prefs.getProfile() : { interests: [], saved: [], dismissed: [], lastMode: "for_you" };
  }

  function createContext() {
    const now = new Date();
    const prefs = getPreferences();
    const weatherApi = getWeather();
    const profile = getProfile();
    const weatherContext = state.weatherContext || {};
    const weather = weatherApi?.toRecommendationWeather
      ? weatherApi.toRecommendationWeather(weatherContext)
      : "unknown";
    const dayContext = buildDayContext(now);
    const extraContext = {
      mode: state.mode || "for_you",
      weather,
      rainRisk: asString(weatherContext.rainRisk || "unknown"),
      outdoorFit: asString(weatherContext.outdoorFit || "unknown"),
      showersLikely: weatherContext.showersLikely === true,
      todayImpressions: readTodayImpressions(now),
      now
    };

    const baseContext = prefs?.toRecommendationContext
      ? prefs.toRecommendationContext(extraContext)
      : {
          mode: state.mode || "for_you",
          interests: profile.interests || [],
          saved: profile.saved || [],
          dismissed: profile.dismissed || [],
          weather,
          now
        };

    return {
      ...baseContext,
      ...extraContext,
      ...dayContext
    };
  }

  /* === BEGIN BLOCK: TODAY_RECOMMENDATION_CURATION_V1 | Zweck: macht die Today-Home zur echten Entscheidungsseite statt zur langen Vorfilterliste; Umfang: entfernt vergangene Events, begrenzt auf wenige Vorschläge, rotiert täglich stabil und erzwingt grobe Vielfalt === */
  function parseLocalDate(value) {
    const text = asString(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) return null;

    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
  }

  function startOfDay(value) {
    const date = value instanceof Date ? value : new Date();
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function stableHash(value) {
    const text = asString(value);
    let hash = 2166136261;

    for (let i = 0; i < text.length; i += 1) {
      hash ^= text.charCodeAt(i);
      hash = Math.imul(hash, 16777619);
    }

    return hash >>> 0;
  }

  function rotationKey(date) {
    const base = startOfDay(date || new Date());
    return `${base.getFullYear()}-${String(base.getMonth() + 1).padStart(2, "0")}-${String(base.getDate()).padStart(2, "0")}`;
  }

  /* === BEGIN BLOCK: TODAY_HOME_RECENT_ACTIVITY_ROTATION_V1 | Zweck: verhindert, dass dieselben Activity-Tipps ueber Tage hinweg immer wieder dominieren; Umfang: lokale, datenschutzarme Impression-Historie nur fuer Today-Home-Curation, keine UI-/Layout-Aenderung === */
  function daysSinceDateKey(value, now) {
    const date = parseLocalDate(value);
    if (!date) return null;

    return Math.round((startOfDay(now || new Date()).getTime() - startOfDay(date).getTime()) / 86400000);
  }

  function readTodayImpressions(now = new Date()) {
    if (!hasStorage()) return Object.freeze({});

    try {
      const raw = window.localStorage.getItem(TODAY_IMPRESSIONS_STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : null;
      const sourceItems = parsed && typeof parsed === "object" && parsed.items && typeof parsed.items === "object"
        ? parsed.items
        : {};
      const out = Object.create(null);

      Object.entries(sourceItems).forEach(([key, record]) => {
        const itemKeyValue = asString(key);
        const lastShown = asString(record?.lastShown);
        const ageDays = daysSinceDateKey(lastShown, now);

        if (!itemKeyValue || ageDays == null || ageDays < 0 || ageDays > TODAY_IMPRESSIONS_RETENTION_DAYS) return;

        out[itemKeyValue] = Object.freeze({
          lastShown,
          count: Number.isFinite(Number(record?.count)) ? Number(record.count) : 1,
          type: asString(record?.type),
          title: asString(record?.title)
        });
      });

      return Object.freeze(out);
    } catch (_) {
      return Object.freeze({});
    }
  }

  function activityRecentPenalty(item, context = {}) {
    if (item?.type !== "activity" || isSaved(item)) return 0;

    const record = context?.todayImpressions?.[itemKey(item)];
    const ageDays = daysSinceDateKey(record?.lastShown, context?.now || new Date());

    if (ageDays == null || ageDays <= 0) return 0;
    if (ageDays === 1) return -32;
    if (ageDays === 2) return -26;
    if (ageDays <= 4) return -18;
    if (ageDays <= 7) return -10;
    if (ageDays <= TODAY_IMPRESSIONS_RETENTION_DAYS) return -4;

    return 0;
  }

  function activitySelectionScore(item, context = {}) {
    return todayScore(item) + activityRecentPenalty(item, context);
  }

  function recordTodayImpressions(items, context = {}) {
    const visibleItems = Array.isArray(items) ? items.filter(Boolean) : [];
    if (!visibleItems.length || !hasStorage()) return;

    const now = context?.now instanceof Date ? context.now : new Date();
    const today = rotationKey(now);
    const currentItems = { ...readTodayImpressions(now) };

    visibleItems.forEach((item) => {
      const key = itemKey(item);
      if (!key) return;

      const previous = currentItems[key] || {};
      currentItems[key] = {
        lastShown: today,
        count: Number(previous.count || 0) + 1,
        type: asString(item.type),
        title: asString(item.title)
      };
    });

    try {
      window.localStorage.setItem(TODAY_IMPRESSIONS_STORAGE_KEY, JSON.stringify({
        schemaVersion: 1,
        updatedAt: new Date().toISOString(),
        items: currentItems
      }));
    } catch (_) {
      // Die Rotation bleibt auch ohne localStorage funktionsfaehig; dann greift nur die Tagesrotation.
    }
  }
  /* === END BLOCK: TODAY_HOME_RECENT_ACTIVITY_ROTATION_V1 === */

  function isPastEventItem(item, now) {
    if (item?.type !== "event") return false;

    const today = startOfDay(now || new Date());
    const end = parseLocalDate(item.endDate || item.date);
    const start = parseLocalDate(item.date);

    if (end) return end < today;
    if (start) return start < today;

    return false;
  }

  function addDailyRotation(items, context) {
    const key = rotationKey(context?.now || new Date());

    return (Array.isArray(items) ? items : [])
      .map((item) => {
        const baseScore = Number(item?.score);
        const score = Number.isFinite(baseScore) ? baseScore : 0;
        const jitter = stableHash(`${key}:${itemKey(item)}`) % 9;

        return {
          item,
          sortScore: score + jitter / 10
        };
      })
      .sort((a, b) => b.sortScore - a.sortScore)
      .map((entry) => entry.item);
  }

  /* === BEGIN BLOCK: TODAY_RECOMMENDATION_EVENT_MIX_V4 | Zweck: mischt nur tatsaechlich heute laufende Events ueber eine eigene Event-Spur in Today Home ein, damit zukuenftige Termine nicht als Heute-Tipp erscheinen; Umfang: ersetzt nur Event-Mix-Curation, keine DOM-Aenderung === */
  function isTodayEventCandidate(item, now) {
    if (item?.type !== "event") return false;

    const today = startOfDay(now || new Date());
    const start = parseLocalDate(item.date);
    const end = parseLocalDate(item.endDate || item.date) || start;

    if (!start && !end) return false;

    const todayTime = today.getTime();
    const startTime = start ? startOfDay(start).getTime() : null;
    const endTime = end ? startOfDay(end).getTime() : startTime;

    if (startTime === null || endTime === null) return false;

    return startTime <= todayTime && todayTime <= endTime;
  }

  function todayScore(item) {
    const score = Number(item?.score);
    return Number.isFinite(score) ? score : 0;
  }

  function hasReadyEventVisual(item) {
    const visualKey = eventVisualKey(item);
    const visualMotif = eventVisualMotif(item);
    const pool = visualKey ? state.eventVisualPools[visualKey] : null;
    const candidatePool = eventVisualCandidatePool(pool, visualMotif);

    return Array.isArray(candidatePool) && candidatePool.length > 0;
  }

  /* === BEGIN BLOCK: TODAY_ACTIVITY_VISUAL_POOL_QUALITY_GATE_V2 | Zweck: blockiert Activities mit altem needs_review-Bild nicht mehr, wenn fuer dieselbe Activity ein ready Premium-Visual im Pool existiert; Umfang: ersetzt nur hasAllowedActivityVisual() === */
  function hasAllowedActivityVisual(item) {
    if (item?.type !== "activity") return true;

    const pool = activityVisualPool(item);
    if (Array.isArray(pool) && pool.length) return true;

    const quality = asString(
      item?.imageQuality ||
      item?.image_quality ||
      item?.image_status ||
      item?.visual_status
    ).toLowerCase();

    return quality !== "needs_review" && quality !== "blocked";
  }
  /* === END BLOCK: TODAY_ACTIVITY_VISUAL_POOL_QUALITY_GATE_V2 === */

  function isTopTipEligible(item, context) {
    return window.OpeningStatus?.isTopTipEligible?.(item, context) !== false;
  }

  function compareTodayItems(a, b) {
    const scoreDiff = todayScore(b) - todayScore(a);
    if (scoreDiff) return scoreDiff;

    if (a.type !== b.type) return a.type === "event" ? -1 : 1;

    return String(a.title || "").localeCompare(String(b.title || ""), "de");
  }

  function compareEventCandidates(a, b) {
    const visualDiff = Number(hasReadyEventVisual(b)) - Number(hasReadyEventVisual(a));
    if (visualDiff) return visualDiff;

    return compareTodayItems(a, b);
  }

  function activityText(item) {
    const tags = Array.isArray(item?.tags) ? item.tags.join(" ") : "";
    return [
      item?.title,
      item?.description,
      item?.location,
      tags
    ].map(asString).join(" ").toLowerCase();
  }

  function activityRegionRank(item) {
    const location = asString(item?.location || item?.city || item?.ort).toLowerCase();

    if (location.includes("bocholt") || location.includes("aasee bocholt")) return 0;
    if (
      location.includes("rhede") ||
      location.includes("borken") ||
      location.includes("burlo") ||
      location.includes("isselburg") ||
      location.includes("anholt") ||
      location.includes("dinxperlo") ||
      location.includes("suderwick")
    ) return 1;
    if (
      location.includes("winterswijk") ||
      location.includes("aalten") ||
      location.includes("bredevoort") ||
      location.includes("vreden")
    ) return 2;

    return 3;
  }

  function activityRegionBucket(item) {
    const location = asString(item?.location || item?.city || item?.ort).toLowerCase();

    if (location.includes("bocholt") || location.includes("aasee bocholt")) return "bocholt";
    if (location.includes("dinxperlo") || location.includes("suderwick")) return "grenze";
    if (location.includes("winterswijk") || location.includes("aalten") || location.includes("bredevoort")) return "achterhoek";
    if (location.includes("borken") || location.includes("burlo") || location.includes("vreden") || location.includes("raesfeld") || location.includes("ahaus")) return "kreis-borken";
    if (location.includes("rhede") || location.includes("isselburg") || location.includes("anholt")) return "nahbereich";
    if (location.includes("wesel") || location.includes("hamminkeln")) return "niederrhein";

    return "sonstige";
  }

  function activityThemeBucket(item) {
    const textValue = activityText(item);

    if (/spielplatz|familie|märchen|tiergarten|zoo|anholter schweiz/.test(textValue)) return "familie";
    if (/museum|textil|handwerk|villa|mondriaan|schloss|wasserburg|bücherstadt|unterduik|geschichte/.test(textValue)) return "kultur";
    if (/radweg|wandern|mtb|route|spaziergang|tour|noaberpad|kerkpatt/.test(textValue)) return "route";
    if (/see|wasser|aasee|venn|veen|auesee|pröbstingsee|klostersee|quelle|flamingo/.test(textValue)) return "natur-wasser";
    if (/innenstadt|promenade|kubaai|park|vestingpark/.test(textValue)) return "stadt-park";

    return "sonstige";
  }

  /* === BEGIN BLOCK: TODAY_ACTIVITY_SEASONAL_HIGHLIGHT_CURATION_V2 | Zweck: haertet Home-Curation gegen monotone Seasonal-Highlight-Listen; Umfang: taegliche Rotation, maximal 2 Seasonal-Activities bzw. 1 bei Event-Mix, mindestens ein normaler Activity-Tipp wenn verfuegbar === */
  function hasActiveActivityHighlight(item, context = {}) {
    if (item?.type !== "activity") return false;

    const now = context?.now instanceof Date ? context.now : new Date();
    return !!window.BEActivityHighlights?.getPrimaryActiveHighlight?.(item.raw || item, {
      now,
      surface: "home"
    });
  }

  function dailyActivityRotationRank(item, context = {}) {
    const key = rotationKey(context?.now || new Date());
    return stableHash(`${key}:today-home-activity:${itemKey(item)}`) % 1000;
  }

  function compareActivityCandidates(a, b, context = {}) {
    const aHighlight = hasActiveActivityHighlight(a, context);
    const bHighlight = hasActiveActivityHighlight(b, context);
    const scoreDiff = activitySelectionScore(b, context) - activitySelectionScore(a, context);

    if (Math.abs(scoreDiff) >= 18) return scoreDiff;

    if (aHighlight === bHighlight) {
      const rotationDiff = dailyActivityRotationRank(a, context) - dailyActivityRotationRank(b, context);
      if (rotationDiff) return rotationDiff;
    }

    if (Math.abs(scoreDiff) >= 8) return scoreDiff;

    const regionDiff = activityRegionRank(a) - activityRegionRank(b);
    if (regionDiff) return regionDiff;

    return compareTodayItems(a, b);
  }

  function seasonalHomeLimit(limit, hasNonHighlightCandidate) {
    if (!hasNonHighlightCandidate) return limit;
    return limit <= 2 ? 1 : 2;
  }
  /* === END BLOCK: TODAY_ACTIVITY_SEASONAL_HIGHLIGHT_CURATION_V2 === */

  function selectDiverseActivities(items, limit, context = {}) {
    const candidates = Array.isArray(items) ? items : [];
    const selected = [];
    const selectedKeys = new Set();
    const usedRegions = new Set();
    const usedThemes = new Set();
    const hasNonHighlightCandidate = candidates.some((item) => !hasActiveActivityHighlight(item, context));
    const maxSeasonalHighlights = seasonalHomeLimit(limit, hasNonHighlightCandidate);
    let selectedSeasonalHighlights = 0;
    let selectedNonHighlights = 0;

    const tryAdd = (item) => {
      const key = itemKey(item);
      if (!item || selectedKeys.has(key) || selected.length >= limit) return false;

      const isSeasonalHighlight = hasActiveActivityHighlight(item, context);
      if (isSeasonalHighlight && selectedSeasonalHighlights >= maxSeasonalHighlights) return false;

      if (
        isSeasonalHighlight &&
        hasNonHighlightCandidate &&
        selectedNonHighlights === 0 &&
        selected.length >= limit - 1
      ) {
        return false;
      }

      selected.push(item);
      selectedKeys.add(key);
      usedRegions.add(activityRegionBucket(item));
      usedThemes.add(activityThemeBucket(item));

      if (isSeasonalHighlight) {
        selectedSeasonalHighlights += 1;
      } else {
        selectedNonHighlights += 1;
      }

      return true;
    };

    [
      (item) => !usedRegions.has(activityRegionBucket(item)) && !usedThemes.has(activityThemeBucket(item)),
      (item) => !usedThemes.has(activityThemeBucket(item)),
      (item) => !usedRegions.has(activityRegionBucket(item)),
      () => true
    ].forEach((predicate) => {
      candidates.forEach((item) => {
        if (selected.length < limit && predicate(item)) {
          tryAdd(item);
        }
      });
    });

    if (selected.length < limit) {
      candidates.forEach((item) => {
        if (selected.length < limit) {
          tryAdd(item);
        }
      });
    }

    return selected;
  }

  function curateTodayItems(items, context) {
    const now = context?.now || new Date();
    const cleaned = (Array.isArray(items) ? items : [])
      .filter((item) => item && !isPastEventItem(item, now));

    const activityLane = addDailyRotation(
      cleaned.filter((item) => item.type !== "event" && hasAllowedActivityVisual(item)).slice(0, 32),
      context
    ).sort((a, b) => compareActivityCandidates(a, b, context));

    const eventLane = addDailyRotation(
      cleaned.filter((item) => isTodayEventCandidate(item, now) && todayScore(item) > 0),
      context
    ).sort(compareEventCandidates);

    const selectedActivities = selectDiverseActivities(activityLane, eventLane.length ? 2 : 3, context);
    const selectedEvent = eventLane[0] || null;
    const selected = selectedEvent
      ? [...selectedActivities, selectedEvent]
      : selectedActivities;

    const sorted = selected
      .sort(compareTodayItems)
      .slice(0, 3);

    const topIndex = sorted.findIndex((item) => isTopTipEligible(item, context));
    if (topIndex > 0) {
      const [topItem] = sorted.splice(topIndex, 1);
      sorted.unshift(topItem);
    }

    return sorted;
  }
  /* === END BLOCK: TODAY_RECOMMENDATION_EVENT_MIX_V4 === */

  function buildRecommendations() {
    const api = getRecommendations();
    if (!api?.createRecommendations) return [];

    const context = createContext();
    const recommendations = api.createRecommendations(
      {
        events: state.events,
        offers: state.offers
      },
      context
    );

    return curateTodayItems(recommendations, context);
  }
  /* === END BLOCK: TODAY_RECOMMENDATION_CURATION_V1 === */

  function formatDate(value) {
    const text = asString(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) return "";

    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    return new Intl.DateTimeFormat("de-DE", {
      weekday: "short",
      day: "2-digit",
      month: "2-digit"
    }).format(date);
  }

  /* === BEGIN BLOCK: TODAY_EVENT_META_RUNNING_LABELS_V1 | Zweck: laufende Mehrtagesevents auf der Today-Home nicht wie vergangene Startdatum-Events wirken lassen; Umfang: ersetzt nur Event-Meta-Labeling im gemischten Today-Feed === */
  function formatEventDateLabel(item) {
    const start = parseLocalDate(item?.date);
    const end = parseLocalDate(item?.endDate);
    const today = startOfDay(new Date());
    const todayTime = today.getTime();

    if (start && end && start.getTime() !== end.getTime()) {
      const startTime = start.getTime();
      const endTime = end.getTime();

      if (startTime <= todayTime && todayTime <= endTime) {
        if (endTime > todayTime) return `Läuft heute · bis ${formatDate(item.endDate)}`;
        return "Läuft heute";
      }

      const startLabel = formatDate(item.date);
      const endLabel = formatDate(item.endDate);
      return [startLabel, endLabel].filter(Boolean).join(" – ");
    }

    if (start) {
      const startTime = start.getTime();
      const tomorrow = new Date(today);
      tomorrow.setDate(today.getDate() + 1);

      if (startTime === todayTime) return "Heute";
      if (startTime === tomorrow.getTime()) return "Morgen";
    }

    return formatDate(item?.date);
  }

  /* === BEGIN BLOCK: TODAY_HOME_CARD_META_CONTRACT_V1 | Zweck: vereinheitlicht Home-Card-Meta als Ort zuerst und knapper Zeit-/Statuskontext; Umfang: nur Event-Card-Meta, Detailpanel behaelt volle Adresse/Zeitdetails === */
  function normalizeCardPlace(value) {
    return asString(value)
      .replace(/\b\d{5}\b/g, "")
      .replace(/\s+/g, " ")
      .replace(/^[,·\s]+|[,·\s]+$/g, "")
      .trim();
  }

  function compactEventLocation(item) {
    const explicitPlace = [
      item?.locationArea,
      item?.area,
      item?.district,
      item?.ortsteil,
      item?.city,
      item?.stadt,
      item?.locationCity
    ].map(normalizeCardPlace).find(Boolean);

    if (explicitPlace) return explicitPlace;

    const rawLocation = normalizeCardPlace(item?.location || item?.ort || item?.locationName);
    if (!rawLocation) return "";

    const commaParts = rawLocation.split(",").map(normalizeCardPlace).filter(Boolean);
    if (commaParts.length > 1) return commaParts[commaParts.length - 1];

    return rawLocation;
  }

  function compactEventTimeLabel(item) {
    const date = formatEventDateLabel(item);
    const time = asString(item?.time);

    if (date && time && !date.includes("·")) return `${date} ${time}`;
    return [date, time].filter(Boolean).join(" · ");
  }

  function buildEventMeta(item) {
    const parts = [];
    const location = compactEventLocation(item);
    const timeLabel = compactEventTimeLabel(item);

    if (location) parts.push(location);
    if (timeLabel) parts.push(timeLabel);

    return parts.join(" · ");
  }
  /* === END BLOCK: TODAY_HOME_CARD_META_CONTRACT_V1 === */
  /* === END BLOCK: TODAY_EVENT_META_RUNNING_LABELS_V1 === */

  /* === BEGIN BLOCK: TODAY_HOME_COMPACT_ACTIVITY_META_V1 | Zweck: haelt Activity-Card-Meta auf Home kurz und scanbar; Umfang: nur Home-Card-Meta, Detailpanel behaelt vollstaendige Hinweise === */
  function compactActivityMetaLabel(label) {
    const text = asString(label)
      .replace(/\s+/g, " ")
      .trim();

    if (!text) return "";
    if (/jederzeit möglich/i.test(text)) return "jederzeit möglich";
    if (/ganzjährig/i.test(text) && /nutzbar/i.test(text)) return "ganzjährig nutzbar";
    if (text.includes(";")) return text.split(";").map((part) => part.trim()).find(Boolean) || "";

    return text;
  }

  function buildActivityMeta(item, context) {
    const parts = [];
    const dayContext = context || buildDayContext(new Date());

    if (item.location) parts.push(item.location);

    const availabilityLabels = window.OpeningStatus?.buildActivityMetaLabels?.(item, dayContext) || [];
    const compactLabel = availabilityLabels.map(compactActivityMetaLabel).find(Boolean);
    if (compactLabel) parts.push(compactLabel);

    return parts.join(" · ");
  }
  /* === END BLOCK: TODAY_HOME_COMPACT_ACTIVITY_META_V1 === */

  function weatherMessage(context) {
    const weather = asString(context?.weather || "unknown");
    const rainRisk = asString(context?.rainRisk || context?.forecast?.rainRisk || "unknown");
    const summaryLabel = asString(context?.summaryLabel);

    if (summaryLabel) {
      return `${summaryLabel} · Wetter mitgedacht`;
    }

    if (weather === "hot") return "Heute Schatten und Wasser einplanen · Wetter mitgedacht";
    if (weather === "rain" || rainRisk === "near_term") return "Heute wetterfest planen · Wetter mitgedacht";
    if (rainRisk === "later_today") return "Heute mit Plan B planen · Wetter mitgedacht";
    if (weather === "cold") return "Heute kurze Wege oder drinnen wählen · Wetter mitgedacht";
    if (weather === "windy") return "Heute geschützte Orte wählen · Wetter mitgedacht";
    if (weather === "dry") return "Heute gut für draußen · Wetter mitgedacht";

    return "Heute passend vorsortiert · Wetter mitgedacht";
  }

  function typeLabel(item) {
    return item.type === "activity" ? "Aktivität" : "Event";
  }

  function typeIcon(item) {
    return item.type === "activity" ? "compass" : "calendar-days";
  }

  function renderStatus(text) {
    const el = document.getElementById(STATUS_ID);
    if (!el) return;
    el.textContent = text || "";
  }

  function renderWeatherNote() {
    const el = document.getElementById(WEATHER_ID);
    if (!el) return;

    const message = weatherMessage(state.weatherContext);
    el.innerHTML = `
      <span class="today-weather-note__icon" data-ui-icon="sparkles" aria-hidden="true"></span>
      <span>${escapeHtml(message)}</span>
    `.trim();

    hydrateIcons(el);
  }

  function itemKey(item) {
    return `${item.type}:${item.id}`;
  }

  function isSaved(item) {
    const prefs = getPreferences();
    return !!(prefs?.isSaved && prefs.isSaved(item));
  }

  /* === BEGIN BLOCK: TODAY_HOME_ACTIVITY_REASON_PILLS_ONLY_V1 | Zweck: verhindert redundante Event-Pills wie "Heute" auf der Today-Home; Umfang: Reason-Pills nur fuer Activities, Event-Zeitkontext bleibt in der Meta-Zeile === */
  function renderReasonLabels(item) {
    if (item?.type === "event") return "";

    const labels = Array.isArray(item?.reasonLabels) ? item.reasonLabels : [];
    if (!labels.length) return "";

    return `
      <div class="today-card__reasons" aria-label="Warum das passt">
        ${labels.slice(0, 3).map((label) => `<span class="today-chip">${escapeHtml(label)}</span>`).join("")}
      </div>
    `.trim();
  }
  /* === END BLOCK: TODAY_HOME_ACTIVITY_REASON_PILLS_ONLY_V1 === */

  function renderDesktopCreditLink(item, resolvedVisual) {
    if (!resolvedVisual || !window.ImageAttribution?.renderCreditAccessLink) return "";

    return window.ImageAttribution.renderCreditAccessLink(resolvedVisual, {
      entityType: item?.type === "activity" ? "activity" : "event",
      entityId: asString(item?.id || item?.raw?.id),
      entityTitle: asString(item?.title || item?.raw?.title),
      imageId: asString(resolvedVisual?.id),
      label: "Bildnachweis"
    });
  }

  function renderCard(item, index, usedImages) {
    const context = createContext();
    const meta = item.type === "activity" ? buildActivityMeta(item, context) : buildEventMeta(item);
    const resolvedVisual = resolveItemVisual(item, usedImages);
    const image = resolvedVisual?.src || "";
    const creditLink = renderDesktopCreditLink(item, resolvedVisual);
    const showTopBadge = index === 0 && isTopTipEligible(item, context);

    if (item && typeof item === "object") {
      if (resolvedVisual) {
        renderedVisualsByItem.set(item, resolvedVisual);
      } else {
        renderedVisualsByItem.delete(item);
      }
    }
    const cardClass = [
      "today-card",
      `today-card--${item.type}`,
      index === 0 ? "today-card--primary" : "",
      image ? "today-card--with-image" : ""
    ].filter(Boolean).join(" ");

    return `
      <article class="${cardClass}" data-today-card="${escapeHtml(itemKey(item))}" tabindex="0" role="button" aria-label="${escapeHtml(typeLabel(item))} öffnen: ${escapeHtml(item.title)}">
        ${image ? `
          <div class="today-card__media">
            <img src="${escapeHtml(image)}" alt="" loading="${index < 1 ? "eager" : "lazy"}" decoding="async">
            ${creditLink}
          </div>
        ` : ""}
        <div class="today-card__body">
          <div class="today-card__topline">
            <span class="today-card__type">
              <span data-ui-icon="${escapeHtml(typeIcon(item))}" aria-hidden="true"></span>
              ${escapeHtml(typeLabel(item))}
            </span>
            ${showTopBadge ? `<span class="today-card__badge">Top-Tipp</span>` : ""}
          </div>
          <h2 class="today-card__title">${escapeHtml(item.title)}</h2>
          ${meta ? `<p class="today-card__meta">${escapeHtml(meta)}</p>` : ""}
          ${item.description ? `<p class="today-card__desc">${escapeHtml(item.description)}</p>` : ""}
          ${renderReasonLabels(item)}
        </div>
      </article>
    `.trim();
  }

  function renderFeed() {
    const feed = document.getElementById(FEED_ID);
    if (!feed) return;

    state.items = buildRecommendations();

    const visible = state.items;

    if (!visible.length) {
      feed.innerHTML = `
        <section class="today-empty" role="status">
          <h2>Gerade nichts Passendes gefunden</h2>
          <p>Events und Aktivitäten sind weiterhin direkt erreichbar.</p>
          <div class="today-empty__actions">
            <a href="/events/">Alle Events ansehen</a>
            <a href="/aktivitaeten/">Aktivitäten entdecken</a>
          </div>
        </section>
      `.trim();
      renderStatus("Keine passenden Vorschläge");
      return;
    }

    const usedImages = new Set();

    feed.innerHTML = `
      ${visible.map((item, index) => renderCard(item, index, usedImages)).join("")}
      <section class="today-more" aria-label="Mehr entdecken">
        <a class="today-more__link" href="/events/">
          <span class="today-more__icon" data-ui-icon="calendar-days" aria-hidden="true"></span>
          <span class="today-more__copy">
            <span class="today-more__label">Alle Events ansehen</span>
            <span class="today-more__hint">Termine, Märkte, Kultur und mehr</span>
          </span>
        </a>
        <a class="today-more__link" href="/aktivitaeten/">
          <span class="today-more__icon" data-ui-icon="compass" aria-hidden="true"></span>
          <span class="today-more__copy">
            <span class="today-more__label">Aktivitäten entdecken</span>
            <span class="today-more__hint">Orte, Natur, Familie und Ausflüge</span>
          </span>
        </a>
      </section>
    `.trim();

    renderStatus(`${visible.length} Tipps`);
    hydrateIcons(feed);
    recordTodayImpressions(visible, createContext());
  }

  function renderAll() {
    renderWeatherNote();
    renderFeed();
  }

  function findItemByKey(key) {
    return state.items.find((item) => itemKey(item) === key) || null;
  }

  function openItem(item) {
    if (!item) return;

    if (isDesktopViewport()) {
      openDesktopTarget(item);
      return;
    }

    if (item.type === "activity" && window.OfferDetailPanel?.show) {
      window.OfferDetailPanel.show(item.raw || item);
      return;
    }

    if (item.type === "event" && window.DetailPanel?.show) {
      const renderedVisual = item && typeof item === "object"
        ? renderedVisualsByItem.get(item)
        : null;
      const resolvedVisual = renderedVisual || resolveItemVisual(item, null);
      const detailEvent = {
        ...(item.raw || item),
        ...(resolvedVisual ? { resolvedVisual } : {})
      };

      window.DetailPanel.show(detailEvent);
      return;
    }

    if (item.url) {
      window.location.href = item.url;
    }
  }

  function bindEvents(root) {
    root.addEventListener("click", (event) => {
      if (event.target.closest("[data-image-credit-access]")) {
        event.stopPropagation();
        return;
      }

      const card = event.target.closest("[data-today-card]");
      if (card) {
        openItem(findItemByKey(card.getAttribute("data-today-card")));
      }
    });

    root.addEventListener("keydown", (event) => {
      if (event.target.closest("[data-image-credit-access]")) return;
      if (event.key !== "Enter" && event.key !== " ") return;

      const card = event.target.closest("[data-today-card]");
      if (!card) return;

      event.preventDefault();
      openItem(findItemByKey(card.getAttribute("data-today-card")));
    });
  }

  function hydrateIcons(root) {
    if (window.Icons?.hydrate && root) {
      window.Icons.hydrate(root);
    }
  }

  function renderSkeleton() {
    const feed = document.getElementById(FEED_ID);
    if (!feed) return;

    feed.innerHTML = Array.from({ length: 3 }).map(() => `
      <article class="today-card today-card--skeleton" aria-hidden="true">
        <div class="today-card__body">
          <div class="today-skeleton today-skeleton--small"></div>
          <div class="today-skeleton today-skeleton--title"></div>
          <div class="today-skeleton today-skeleton--line"></div>
          <div class="today-skeleton today-skeleton--chips"></div>
        </div>
      </article>
    `.trim()).join("");
  }

  async function loadData() {
    const [eventPayload, approvedPayload, offerPayload, visualPoolPayload, activityVisualPoolPayload, bathingStatusPayload] = await Promise.all([
      fetchJsonNoStore("/data/events.json", false),
      fetchJsonNoStore("/api/events/public.php", false),
      fetchJsonNoStore("/data/offers.json", true),
      fetchJsonNoStore("/data/event_visual_pool.json", false),
      fetchJsonNoStore("/data/activity_visual_pool.json", false),
      fetchJsonNoStore("/data/bathing_water_status.json", false)
    ]);

    state.events = dedupeEvents([
      ...extractEvents(eventPayload),
      ...extractEvents(approvedPayload)
    ]);

    if (typeof window.BEActivityHighlights?.setStatusOverrides === "function") {
      window.BEActivityHighlights.setStatusOverrides(bathingStatusPayload);
    }

    state.offers = extractOffers(offerPayload);
    state.eventVisualPools = buildReadyEventVisualPools(visualPoolPayload);
    state.activityVisualPoolsByOfferId = buildReadyActivityVisualPoolsByOfferId(activityVisualPoolPayload);
  }

  async function loadWeather() {
    const weatherApi = getWeather();

    if (!weatherApi?.getWeatherContext) {
      state.weatherContext = { weather: "unknown" };
      return;
    }

    state.weatherContext = await weatherApi.getWeatherContext();
  }

  async function init() {
    const root = document.getElementById(ROOT_ID);
    if (!root) return;

    state.mode = "for_you";

    bindEvents(root);
    renderSkeleton();
    renderStatus("Lade Ideen für heute …");

    try {
      await Promise.all([loadData(), loadWeather()]);
      renderAll();
    } catch (error) {
      console.error("[TodayHome] Init failed:", error);
      renderStatus("Fehler beim Laden");
      const feed = document.getElementById(FEED_ID);
      if (feed) {
        feed.innerHTML = `
          <section class="today-empty" role="alert">
            <h2>Heute lädt gerade nicht richtig</h2>
            <p>Events und Aktivitäten sind weiterhin direkt erreichbar.</p>
            <div class="today-empty__actions">
              <a href="/events/">Events ansehen</a>
              <a href="/aktivitaeten/">Aktivitäten ansehen</a>
            </div>
          </section>
        `.trim();
      }
    }
  }

  window.BETodayHome = { init };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
}());
/* === END FILE: js/today-home.js === */
