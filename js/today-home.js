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

  function getProfile() {
    const prefs = getPreferences();
    return prefs?.getProfile ? prefs.getProfile() : { interests: [], saved: [], dismissed: [], lastMode: "for_you" };
  }

  function createContext() {
    const prefs = getPreferences();
    const weatherApi = getWeather();
    const profile = getProfile();
    const weather = weatherApi?.toRecommendationWeather
      ? weatherApi.toRecommendationWeather(state.weatherContext)
      : "unknown";

    if (prefs?.toRecommendationContext) {
      return prefs.toRecommendationContext({
        mode: state.mode || profile.lastMode || "for_you",
        weather
      });
    }

    return {
      mode: state.mode || "for_you",
      interests: profile.interests || [],
      saved: profile.saved || [],
      dismissed: profile.dismissed || [],
      weather,
      now: new Date()
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

  function curateTodayItems(items, context) {
    const now = context?.now || new Date();
    const cleaned = (Array.isArray(items) ? items : [])
      .filter((item) => item && !isPastEventItem(item, now));

    const rotated = addDailyRotation(cleaned.slice(0, 16), context);
    const hasEventCandidate = rotated.some((item) => item.type === "event");
    const maxActivities = hasEventCandidate ? 2 : 3;
    const maxEvents = 2;
    const selected = [];
    const counts = {
      activity: 0,
      event: 0
    };

    for (const item of rotated) {
      const type = item.type === "event" ? "event" : "activity";

      if (type === "activity" && counts.activity >= maxActivities) continue;
      if (type === "event" && counts.event >= maxEvents) continue;

      selected.push(item);
      counts[type] += 1;

      if (selected.length >= 3) break;
    }

    return selected;
  }

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

  function buildActivityMeta(item) {
    const parts = [];
    if (item.location) parts.push(item.location);
    if (item.availability === "always") parts.push("frei planbar");
    if (item.availability === "opening_hours_check") parts.push("Öffnungszeiten prüfen");
    if (item.availability === "seasonal") parts.push("saisonal");
    return parts.join(" · ");
  }

  function weatherMessage(context) {
    const weather = asString(context?.weather || "unknown");
    const temperature = Number(context?.temperature);
    const tempLabel = Number.isFinite(temperature) ? `${Math.round(temperature)} °C` : "";

    if (weather === "hot") {
      return [tempLabel || "Warm heute", "Wasser & Schatten sind gute Ideen."].filter(Boolean).join(" · ");
    }

    if (weather === "rain") {
      return "Regen in Bocholt – drinnen-taugliche Ideen zuerst.";
    }

    if (weather === "cold") {
      return [tempLabel || "Kühl heute", "kurz oder drinnen passt besser."].filter(Boolean).join(" · ");
    }

    if (weather === "windy") {
      return "Windig heute – geschützte Orte passen besser.";
    }

    if (weather === "dry") {
      return [tempLabel, "Heute gut für draußen."].filter(Boolean).join(" · ");
    }

    return "Ideen für heute.";
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
    const meta = item.type === "activity" ? buildActivityMeta(item) : buildEventMeta(item);
    const image = resolveItemImage(item, usedImages);
    const cardClass = [
      "today-card",
      `today-card--${item.type}`,
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
            ${index === 0 ? `<span class="today-card__badge">Heute passend</span>` : ""}
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
          <span class="today-more__label">Alle Events ansehen</span>
          <span class="today-more__hint">Termine, Märkte, Kultur und mehr</span>
        </a>
        <a class="today-more__link" href="/aktivitaeten/">
          <span class="today-more__label">Aktivitäten entdecken</span>
          <span class="today-more__hint">Orte, Natur, Familie und Ausflüge</span>
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
      fetchJsonNoStore("/data/events.json", true),
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

    const profile = getProfile();
    state.mode = profile.lastMode || "for_you";

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