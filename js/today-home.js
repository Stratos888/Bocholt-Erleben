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

  function buildRecommendations() {
    const api = getRecommendations();
    if (!api?.createRecommendations) return [];

    return api.createRecommendations(
      {
        events: state.events,
        offers: state.offers
      },
      createContext()
    );
  }

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

  function buildEventMeta(item) {
    const parts = [];
    const date = formatDate(item.date);
    if (date) parts.push(date);
    if (item.time) parts.push(item.time);
    if (item.location) parts.push(item.location);
    return parts.join(" · ");
  }

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

  function renderCard(item, index) {
    const meta = item.type === "activity" ? buildActivityMeta(item) : buildEventMeta(item);
    const saved = isSaved(item);
    const image = item.type === "activity" ? normalizeUrl(item.image) : "";
    const cardClass = [
      "today-card",
      `today-card--${item.type}`,
      image ? "today-card--with-image" : ""
    ].filter(Boolean).join(" ");

    return `
      <article class="${cardClass}" data-today-card="${escapeHtml(itemKey(item))}" tabindex="0" role="button" aria-label="${escapeHtml(typeLabel(item))} öffnen: ${escapeHtml(item.title)}">
        ${image ? `
          <div class="today-card__media">
            <img src="${escapeHtml(image)}" alt="" loading="${index < 2 ? "eager" : "lazy"}" decoding="async">
          </div>
        ` : ""}
        <div class="today-card__body">
          <div class="today-card__topline">
            <span class="today-card__type">
              <span data-ui-icon="${escapeHtml(typeIcon(item))}" aria-hidden="true"></span>
              ${escapeHtml(typeLabel(item))}
            </span>
            <div class="today-card__actions">
              <button type="button" class="today-card__icon-btn${saved ? " is-active" : ""}" data-today-save="${escapeHtml(itemKey(item))}" aria-label="${saved ? "Nicht mehr merken" : "Merken"}">
                <span data-ui-icon="bookmark" aria-hidden="true"></span>
              </button>
              <button type="button" class="today-card__icon-btn" data-today-dismiss="${escapeHtml(itemKey(item))}" aria-label="Ausblenden">
                <span data-ui-icon="x" aria-hidden="true"></span>
              </button>
            </div>
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

    const visible = state.items.slice(0, 18);

    if (!visible.length) {
      feed.innerHTML = `
        <section class="today-empty" role="status">
          <h2>Gerade nichts Passendes gefunden</h2>
          <p>Setze Interessen zurück oder schau direkt in Events und Aktivitäten.</p>
          <div class="today-empty__actions">
            <a href="/events/">Events ansehen</a>
            <a href="/aktivitaeten/">Aktivitäten ansehen</a>
          </div>
        </section>
      `.trim();
      renderStatus("Keine passenden Vorschläge");
      return;
    }

    feed.innerHTML = visible.map(renderCard).join("");

    renderStatus(`${visible.length} Ideen für heute`);
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

      const saveBtn = event.target.closest("[data-today-save]");
      if (saveBtn) {
        event.preventDefault();
        event.stopPropagation();

        const item = findItemByKey(saveBtn.getAttribute("data-today-save"));
        if (item) getPreferences()?.toggleSaved?.(item);
        renderAll();
        return;
      }

      const dismissBtn = event.target.closest("[data-today-dismiss]");
      if (dismissBtn) {
        event.preventDefault();
        event.stopPropagation();

        const item = findItemByKey(dismissBtn.getAttribute("data-today-dismiss"));
        if (item) getPreferences()?.dismissItem?.(item);
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
    const [eventPayload, approvedPayload, offerPayload] = await Promise.all([
      fetchJsonNoStore("/data/events.json", true),
      fetchJsonNoStore("/api/events/public.php", false),
      fetchJsonNoStore("/data/offers.json", true)
    ]);

    state.events = dedupeEvents([
      ...extractEvents(eventPayload),
      ...extractEvents(approvedPayload)
    ]);

    state.offers = extractOffers(offerPayload);
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