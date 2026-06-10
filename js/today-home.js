/* === BEGIN FILE: js/today-home.js | Zweck: Controller und Renderer fuer die neue Heute-/Recommendation-Home; Umfang: Datenladen, Wetter-/Profil-Kontext, gemischter Empfehlungsfeed ohne bestehende Events-/Activities-Suchseiten zu steuern === */
(function () {
  "use strict";

  const ROOT_ID = "today-root";
  const FEED_ID = "today-feed";
  const STATUS_ID = "today-status";
  const WEATHER_ID = "today-weather-note";
  const MODE_ATTR = "data-today-mode";
  const INTEREST_ATTR = "data-today-interest";

  const MODE_LABELS = Object.freeze({
    for_you: "Heute",
    today: "Heute",
    weekend: "Wochenende",
    family: "Mit Kindern",
    outdoor: "Draußen",
    rain: "Regenplan"
  });

  const HOME_MODES = Object.freeze(["for_you", "weekend", "family", "outdoor", "rain"]);

  const INTERESTS_VISIBLE = Object.freeze([
    "Familie",
    "Draußen",
    "Bei Regen",
    "Kultur",
    "Natur",
    "Kurz & spontan",
    "Wasser",
    "Baden",
    "Spielplatz"
  ]);

  let state = {
    events: [],
    offers: [],
    items: [],
    eventVisualPools: Object.freeze({}),
    weatherContext: null,
    mode: "for_you"
  };

  function qs(selector, root = document) {
    return root.querySelector(selector);
  }

  function qsa(selector, root = document) {
    return Array.from(root.querySelectorAll(selector));
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
            alt: asString(image.alt)
          });
        })
        .filter(Boolean);

      if (key && ready.length) {
        out[key] = Object.freeze(ready);
      }
    });

    return Object.freeze(out);
  }

  function stableHash(value) {
    const textValue = asString(value);
    let hash = 2166136261;

    for (let index = 0; index < textValue.length; index += 1) {
      hash ^= textValue.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }

    return hash >>> 0;
  }

  function eventVisualKey(item) {
    return asString(item?.visualKey || item?.visual_key || item?.image_visual_key);
  }

  function resolveItemImage(item, usedImages) {
    const ownImage = normalizeUrl(item?.image);
    if (ownImage) {
      if (usedImages) usedImages.add(ownImage);
      return ownImage;
    }

    if (item?.type !== "event") {
      return "";
    }

    const visualKey = eventVisualKey(item);
    const pool = visualKey ? state.eventVisualPools[visualKey] : null;

    if (!Array.isArray(pool) || !pool.length) {
      return "";
    }

    const seed = [
      asString(item.id),
      asString(item.date),
      asString(item.endDate),
      asString(item.title),
      visualKey
    ].join("|");

    const start = stableHash(seed) % pool.length;

    for (let offset = 0; offset < pool.length; offset += 1) {
      const candidate = pool[(start + offset) % pool.length];
      if (!candidate?.src) continue;

      if (!usedImages || !usedImages.has(candidate.src) || offset === pool.length - 1) {
        if (usedImages) usedImages.add(candidate.src);
        return candidate.src;
      }
    }

    return "";
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
    const pool = visualKey ? state.eventVisualPools[visualKey] : null;

    return Array.isArray(pool) && pool.length > 0;
  }

  function hasAllowedActivityVisual(item) {
    if (item?.type !== "activity") return true;

    const quality = asString(
      item?.imageQuality ||
      item?.image_quality ||
      item?.image_status ||
      item?.visual_status
    ).toLowerCase();

    return quality !== "needs_review" && quality !== "blocked";
  }

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

  function curateTodayItems(items, context) {
    const now = context?.now || new Date();
    const cleaned = (Array.isArray(items) ? items : [])
      .filter((item) => item && !isPastEventItem(item, now));

    const activityLane = addDailyRotation(
      cleaned.filter((item) => item.type !== "event" && hasAllowedActivityVisual(item)).slice(0, 32),
      context
    );

    const eventLane = addDailyRotation(
      cleaned.filter((item) => isTodayEventCandidate(item, now) && todayScore(item) > 0),
      context
    ).sort(compareEventCandidates);

    const selectedActivities = activityLane.slice(0, eventLane.length ? 2 : 3);
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

  function buildEventMeta(item) {
    const parts = [];
    const date = formatEventDateLabel(item);
    if (date) parts.push(date);
    if (item.time) parts.push(item.time);
    if (item.location) parts.push(item.location);
    return parts.join(" · ");
  }
  /* === END BLOCK: TODAY_EVENT_META_RUNNING_LABELS_V1 === */

  function buildActivityMeta(item, context) {
    const parts = [];
    const dayContext = context || buildDayContext(new Date());

    if (item.location) parts.push(item.location);

    const availabilityLabels = window.OpeningStatus?.buildActivityMetaLabels?.(item, dayContext) || [];
    availabilityLabels.forEach((label) => {
      parts.push(label);
    });
    return parts.join(" · ");
  }

  function weatherMessage(context) {
    const weather = asString(context?.weather || "unknown");
    const temperature = Number(context?.temperature);
    const tempLabel = Number.isFinite(temperature) ? `${Math.round(temperature)} °C` : "";
    const summaryLabel = asString(context?.summaryLabel);

    if (summaryLabel) {
      return [tempLabel, summaryLabel].filter(Boolean).join(" · ");
    }

    if (weather === "hot") {
      return [tempLabel || "Warm heute", "Wasser & Schatten sind gute Ideen."].filter(Boolean).join(" · ");
    }

    if (weather === "rain") {
      return [tempLabel, "wechselhaft mit Schauern – wetterfest planen."].filter(Boolean).join(" · ");
    }

    if (weather === "cold") {
      return [tempLabel || "Kühl heute", "kurz oder drinnen passt besser."].filter(Boolean).join(" · ");
    }

    if (weather === "windy") {
      return [tempLabel, "windig – geschützte Orte passen besser."].filter(Boolean).join(" · ");
    }

    if (weather === "dry") {
      return [tempLabel, "heute gut für draußen."].filter(Boolean).join(" · ");
    }

    return "Drei Ideen für Bocholt – ruhig vorsortiert für heute.";
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

  function renderReasonLabels(item) {
    const labels = Array.isArray(item.reasonLabels) ? item.reasonLabels : [];
    if (!labels.length) return "";

    return `
      <div class="today-card__reasons" aria-label="Warum das passt">
        ${labels.slice(0, 3).map((label) => `<span class="today-chip">${escapeHtml(label)}</span>`).join("")}
      </div>
    `.trim();
  }

  function renderCard(item, index, usedImages) {
    const context = createContext();
    const meta = item.type === "activity" ? buildActivityMeta(item, context) : buildEventMeta(item);
    const image = resolveItemImage(item, usedImages);
    const showTopBadge = index === 0 && isTopTipEligible(item, context);
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

  function renderModeControls() {
    return HOME_MODES.map((mode) => {
      const active = mode === state.mode;
      return `
        <button type="button" class="today-mode${active ? " is-active" : ""}" ${MODE_ATTR}="${escapeHtml(mode)}" aria-pressed="${active ? "true" : "false"}">
          ${escapeHtml(MODE_LABELS[mode] || mode)}
        </button>
      `.trim();
    }).join("");
  }

  function renderInterestControls() {
    const profile = getProfile();
    const active = new Set(Array.isArray(profile.interests) ? profile.interests : []);

    return INTERESTS_VISIBLE.map((interest) => {
      const isActive = active.has(interest);
      return `
        <button type="button" class="today-interest${isActive ? " is-active" : ""}" ${INTEREST_ATTR}="${escapeHtml(interest)}" aria-pressed="${isActive ? "true" : "false"}">
          ${escapeHtml(interest)}
        </button>
      `.trim();
    }).join("");
  }

  function renderControls() {
    const modeEl = qs("[data-today-modes]");
    const interestEl = qs("[data-today-interests]");

    if (modeEl) modeEl.innerHTML = renderModeControls();
    if (interestEl) interestEl.innerHTML = renderInterestControls();

    hydrateIcons(document.getElementById(ROOT_ID));
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
  }

  function renderAll() {
    renderWeatherNote();
    renderControls();
    renderFeed();
  }

  function findItemByKey(key) {
    return state.items.find((item) => itemKey(item) === key) || null;
  }

  function openItem(item) {
    if (!item) return;

    if (item.type === "activity" && window.OfferDetailPanel?.show) {
      window.OfferDetailPanel.show(item.raw || item);
      return;
    }

    if (item.type === "event" && window.DetailPanel?.show) {
      window.DetailPanel.show(item.raw || item);
      return;
    }

    if (item.url) {
      window.location.href = item.url;
    }
  }

  function bindEvents(root) {
    root.addEventListener("click", (event) => {
      const modeBtn = event.target.closest(`[${MODE_ATTR}]`);
      if (modeBtn) {
        const mode = asString(modeBtn.getAttribute(MODE_ATTR)) || "for_you";
        state.mode = mode;
        getPreferences()?.setLastMode?.(mode);
        renderAll();
        return;
      }

      const interestBtn = event.target.closest(`[${INTEREST_ATTR}]`);
      if (interestBtn) {
        const interest = asString(interestBtn.getAttribute(INTEREST_ATTR));
        getPreferences()?.toggleInterest?.(interest);
        renderAll();
        return;
      }

      const card = event.target.closest("[data-today-card]");
      if (card) {
        openItem(findItemByKey(card.getAttribute("data-today-card")));
      }
    });

    root.addEventListener("keydown", (event) => {
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

    feed.innerHTML = Array.from({ length: 6 }).map(() => `
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
    const [eventPayload, approvedPayload, offerPayload, visualPoolPayload] = await Promise.all([
      fetchJsonNoStore("/data/events.json", false),
      fetchJsonNoStore("/api/events/public.php", false),
      fetchJsonNoStore("/data/offers.json", true),
      fetchJsonNoStore("/data/event_visual_pool.json", false)
    ]);

    state.events = dedupeEvents([
      ...extractEvents(eventPayload),
      ...extractEvents(approvedPayload)
    ]);

    state.offers = extractOffers(offerPayload);
    state.eventVisualPools = buildReadyEventVisualPools(visualPoolPayload);
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