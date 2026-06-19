// BEGIN: FILE_HEADER_EVENTS
// Datei: js/events.js
// Zweck: Event Cards anzeigen (Rendering = Anzeigen)
// Verantwortlich für:
// - Aus einer Event-Liste DOM-Karten bauen und im Container anzeigen
// - Interaktion (Klick/Enter/Space) → DetailPanel öffnen
//
// Nicht verantwortlich für:
// - Filter-State (Zeit/Kategorie/Suche)
// - Filter-UI (Pills/Sheets/Reset)
// - Filterlogik (anwenden/zusammenführen)
//
// Contract:
// - bekommt bereits gefilterte Events von js/filter.js
// - öffentliche API: EventCards.render(events) / EventCards.refresh(events)
// END: FILE_HEADER_EVENTS


/* ---------- Date Helpers ---------- */
function parseISODateLocal(isoDate) {
  if (!isoDate) return null;
  const [y, m, d] = String(isoDate).split("-").map(Number);
  if (!y || !m || !d) return null;
  return new Date(y, m - 1, d);
}

function startOfToday() {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d;
}

function endOfWeek(fromDate) {
  const d = new Date(fromDate);
  const day = d.getDay();
  const diff = (7 - day) % 7;
  d.setDate(d.getDate() + diff);
  d.setHours(23, 59, 59, 999);
  return d;
}

function addDays(date, days) {
  const d = new Date(date);
  d.setDate(d.getDate() + days);
  return d;
}

function isSameDay(a, b) {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

/* === BEGIN BLOCK: WEEKEND RANGE (next weekend, robust) ===
Zweck: Wochenende-Bereich als Fr+Sa+So (aktuelles oder kommendes Wochenende, relativ zu heute).
Umfang: Ersetzt getNextWeekendRange().
=== */
function getNextWeekendRange(today) {
  const d = new Date(today);
  d.setHours(0, 0, 0, 0);

  const dow = d.getDay(); // 0 So ... 6 Sa
  let friday;

  // Wenn wir schon im Wochenende sind: aktuelles Wochenende (Fr..So)
  if (dow === 5) friday = addDays(d, 0);      // Fr
  else if (dow === 6) friday = addDays(d, -1); // Sa -> Fr
  else if (dow === 0) friday = addDays(d, -2); // So -> Fr
  else {
    // Mo–Do: kommendes Wochenende (nächster Freitag)
    const daysUntilFri = (5 - dow + 7) % 7; // Fr = 5
    friday = addDays(d, daysUntilFri);
  }

  const start = new Date(friday);
  start.setHours(0, 0, 0, 0);

  const sunday = addDays(friday, 2);
  const end = new Date(sunday);
  end.setHours(23, 59, 59, 999);

  return { start, end };
}
/* === END BLOCK: WEEKEND RANGE (next weekend, robust) === */


/* ---------- Bucketing (für Gruppenüberschriften) ---------- */
/* === BEGIN BLOCK: EVENT BUCKET (range-aware via endDate) ===
Zweck:
- Bucketing (today/week/upcoming/...) muss bei Events mit endDate die Laufzeit berücksichtigen.
- Laufende Events (start <= heute <= endDate) dürfen nicht als "past" rausfallen.
Umfang:
- Ersetzt ausschließlich getEventBucket(event).
=== */
function getEventBucket(event) {
  const effective = getEffectiveDate(event); // nutzt endDate: laufende Events -> today
  if (!effective) return "invalid";

  const today = startOfToday();
  const day = new Date(effective);
  day.setHours(0, 0, 0, 0);

  if (day < today) return "past";
  if (day.getTime() === today.getTime()) return "today";
  if (day <= endOfWeek(today)) return "week";

  const limit = addDays(today, 30);
  limit.setHours(23, 59, 59, 999);
  if (day <= limit) return "upcoming";

  return "later";
}
/* === END BLOCK: EVENT BUCKET (range-aware via endDate) === */


/* === BEGIN BLOCK: SORT (date + time) ===
Zweck: Stabile Sortierung nach Datum und optional Uhrzeit (besseres UX).
Umfang: Ersetzt sortByDateAsc().
=== */
function parseTimeToMinutes(timeStr) {
  if (!timeStr) return null;
  const m = String(timeStr).trim().match(/^(\d{1,2})[:.](\d{2})/);
  if (!m) return null;
  const hh = Number(m[1]);
  const mm = Number(m[2]);
  if (!Number.isFinite(hh) || !Number.isFinite(mm)) return null;
  return hh * 60 + mm;
}

/* === BEGIN BLOCK: RANGE-AWARE SORT (laufende Events oben halten) ===
Zweck:
- Events mit endDate gelten während der Laufzeit als "heute"
- dadurch bleiben sie sichtbar/oben
Umfang:
- ersetzt alte sortByDateAsc komplett
=== */

function getEffectiveDate(event) {
  const start = parseISODateLocal(event?.date);
  const end = parseISODateLocal(event?.endDate);

  if (!start) return null;

  if (!end) return start;

  const today = startOfToday();

  if (today >= start && today <= end) {
    return today; // läuft gerade → wie heute behandeln
  }

  return start;
}

function sortByDateAsc(a, b) {
  const da = getEffectiveDate(a);
  const db = getEffectiveDate(b);

  if (!da && !db) return 0;
  if (!da) return 1;
  if (!db) return -1;

  const diff = da - db;
  if (diff !== 0) return diff;

  const ta = parseTimeToMinutes(a?.time);
  const tb = parseTimeToMinutes(b?.time);

  if (ta == null && tb == null) return 0;
  if (ta == null) return 1;
  if (tb == null) return -1;

  return ta - tb;
}
/* === END BLOCK: RANGE-AWARE SORT (laufende Events oben halten) === */

/* === END BLOCK: SORT (date + time) === */

/* ---------- Event Cards ---------- */
// BEGIN: EVENT_CARDS
/* === BEGIN BLOCK: EVENT_CARDS MODULE (render-only, no implicit this) ===
Zweck: DOM-Rendering der Event Cards + Interaktion (öffnet DetailPanel).
Umfang: Reines Anzeige-Modul. Erwartet bereits gefilterte Events von js/filter.js.
API: EventCards.render(events), EventCards.refresh(events)
=== */
const EventCards = (() => {
  let container = null;

  /* === BEGIN BLOCK: HTML_ESCAPE (pure helper) ===
  Zweck: XSS-sicheres Escaping für Text, der via innerHTML gesetzt wird.
  Umfang: Lokaler Helper (kein this-Binding-Risiko).
  === */
  function escapeHtml(value) {
    const s = value == null ? "" : String(value);
    return s.replace(/[&<>"']/g, (ch) => {
      switch (ch) {
        case "&":
          return "&amp;";
        case "<":
          return "&lt;";
        case ">":
          return "&gt;";
        case '"':
          return "&quot;";
        case "'":
          return "&#39;";
        default:
          return ch;
      }
    });
  }
  /* === END BLOCK: HTML_ESCAPE (pure helper) === */

  /* === BEGIN BLOCK: EVENT_CARD_READY_VISUAL_POOL_V1 | Zweck: normalisiert ready Event-Visuals fuer den normalen /events/ Feed; Umfang: lokaler Pool-State, stabile Auswahl, kein usable/planned Rendering === */
  let readyVisualPools = Object.freeze(Object.create(null));

  function normalizeLookupKey(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[\s-]+/g, "_")
      .replace(/[^a-z0-9_]/g, "")
      .replace(/_+/g, "_")
      .replace(/^_+|_+$/g, "");
  }

  function normalizeLocalAssetUrl(value) {
    const raw = String(value || "").trim();
    if (!raw) return "";

    try {
      const url = new URL(raw, window.location.href);
      if (url.origin !== window.location.origin) return "";
      return `${url.pathname}${url.search}${url.hash}`;
    } catch (_) {
      return "";
    }
  }

  function stableHash(value) {
    const textValue = String(value || "");
    let hash = 2166136261;

    for (let index = 0; index < textValue.length; index += 1) {
      hash ^= textValue.charCodeAt(index);
      hash = Math.imul(hash, 16777619);
    }

    return hash >>> 0;
  }

  function buildReadyVisualPools(payload) {
    const pools = payload && typeof payload === "object" ? payload.pools : null;
    const out = Object.create(null);

    if (!pools || typeof pools !== "object") {
      return Object.freeze(out);
    }

    Object.entries(pools).forEach(([visualKey, pool]) => {
      const key = normalizeLookupKey(visualKey);
      const images = Array.isArray(pool?.images) ? pool.images : [];

      const readyImages = images
        .filter((image) => image && String(image.status || "").trim() === "ready")
        .map((image) => {
          const src = normalizeLocalAssetUrl(image.src);
          if (!src) return null;

          return Object.freeze({
            id: String(image.id || "").trim(),
            src,
            alt: String(image.alt || "").trim(),
            status: String(image.status || "").trim(),
            source: String(image.source || "").trim(),
            sourceType: String(image.source_type || image.sourceType || "").trim(),
            rightsStatus: String(image.rights_status || image.rightsStatus || "").trim(),
            reviewStatus: String(image.review_status || image.reviewStatus || "").trim(),
            author: String(image.author || "").trim(),
            license: String(image.license || "").trim(),
            licenseUrl: String(image.license_url || image.licenseUrl || "").trim(),
            sourceTitle: String(image.source_title || image.sourceTitle || "").trim(),
            sourcePage: String(image.source_page || image.sourcePage || image.source_url || image.sourceUrl || "").trim(),
            downloadUrl: String(image.download_url || image.downloadUrl || "").trim(),
            credit: String(image.credit || image.attribution || "").trim(),
            modifications: String(image.modifications || "").trim(),
            isSymbolic: Boolean(image.is_symbolic || image.isSymbolic),
            isDocumentary: Boolean(image.is_documentary || image.isDocumentary),
            publicNote: String(image.public_note || image.publicNote || "").trim(),
            note: String(image.note || "").trim(),
            visualMotif: normalizeLookupKey(image.visual_motif || image.visualMotif || ""),
            visualMotifRole: normalizeLookupKey(image.visual_motif_role || image.visualMotifRole || "")
          });
        })
        .filter(Boolean);

      if (key && readyImages.length) {
        out[key] = Object.freeze(readyImages);
      }
    });

    return Object.freeze(out);
  }

  function setVisualPools(payload) {
    readyVisualPools = buildReadyVisualPools(payload);
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

  function getEventVisualKey(event) {
    return normalizeLookupKey(event?.visual_key || event?.image_visual_key || event?.visualKey || "");
  }

  function getEventVisualMotif(event) {
    return normalizeLookupKey(event?.visual_motif || event?.image_visual_motif || event?.visualMotif || "");
  }

  function isFallbackVisualCandidate(visual) {
    const motif = normalizeLookupKey(visual?.visualMotif || visual?.visual_motif || "");
    if (!motif) return true;

    const role = normalizeLookupKey(visual?.visualMotifRole || visual?.visual_motif_role || "");
    return role === "fallback" || EVENT_VISUAL_FALLBACK_MOTIFS.has(motif);
  }

  function resolveEventVisualCandidatePool(pool, visualMotif) {
    if (!Array.isArray(pool) || !pool.length) return [];

    const motif = normalizeLookupKey(visualMotif);
    if (motif) {
      const exact = pool.filter((candidate) => normalizeLookupKey(candidate?.visualMotif) === motif);
      if (exact.length) return exact;
    }

    const neutral = pool.filter(isFallbackVisualCandidate);
    return neutral.length ? neutral : pool;
  }

  const EVENT_CARD_RECENT_VISUAL_WINDOW = 5;

  function getVisualUsageKey(visual) {
    const id = String(visual?.id || "").trim();
    return id || String(visual?.src || "").trim();
  }

  function getVisualKeyUsageSet(visualUsage, visualKey) {
    if (!visualUsage || !visualKey) {
      return new Set();
    }

    if (!(visualUsage[visualKey] instanceof Set)) {
      visualUsage[visualKey] = new Set();
    }

    return visualUsage[visualKey];
  }

  function getRecentVisualUsageKeys(visualUsage) {
    if (!visualUsage) {
      return [];
    }

    if (!Array.isArray(visualUsage.__recentVisuals)) {
      visualUsage.__recentVisuals = [];
    }

    return visualUsage.__recentVisuals;
  }

  function wasVisualRecentlyUsed(visualUsage, visual) {
    const usageKey = getVisualUsageKey(visual);
    if (!usageKey) return false;

    return getRecentVisualUsageKeys(visualUsage).includes(usageKey);
  }

  function markVisualUsage(visualUsage, visualKey, visual) {
    if (!visualUsage || !visualKey || !visual) return;

    const usageKey = getVisualUsageKey(visual);
    if (!usageKey) return;

    getVisualKeyUsageSet(visualUsage, visualKey).add(usageKey);

    const recentVisuals = getRecentVisualUsageKeys(visualUsage);
    recentVisuals.push(usageKey);

    while (recentVisuals.length > EVENT_CARD_RECENT_VISUAL_WINDOW) {
      recentVisuals.shift();
    }
  }

  function pickEventVisualCandidate(pool, startIndex, predicate) {
    for (let offset = 0; offset < pool.length; offset += 1) {
      const candidate = pool[(startIndex + offset) % pool.length];
      if (!candidate?.src) continue;

      if (predicate(candidate)) {
        return candidate;
      }
    }

    return null;
  }

  function resolveEventVisual(event, visualUsage = null) {
    const visualKey = getEventVisualKey(event);
    const visualMotif = getEventVisualMotif(event);
    const pool = visualKey ? readyVisualPools[visualKey] : null;
    const candidatePool = resolveEventVisualCandidatePool(pool, visualMotif);

    if (!Array.isArray(candidatePool) || candidatePool.length === 0) {
      return null;
    }

    const usageScope = visualMotif ? `${visualKey}:${visualMotif}` : visualKey;
    const seed = [
      event?.id,
      event?.date || event?.datum,
      event?.endDate,
      event?.title || event?.eventName,
      visualKey,
      visualMotif
    ]
      .map((part) => String(part || "").trim())
      .join("|");

    const startIndex = stableHash(seed) % candidatePool.length;
    const fallback = candidatePool[startIndex];

    if (!visualUsage || candidatePool.length <= 1) {
      if (!fallback?.src) return null;
      markVisualUsage(visualUsage, usageScope, fallback);
      return fallback;
    }

    const usedForKey = getVisualKeyUsageSet(visualUsage, usageScope);

    const unusedAndNotRecent = pickEventVisualCandidate(candidatePool, startIndex, (candidate) => {
      const usageKey = getVisualUsageKey(candidate);
      return usageKey && !usedForKey.has(usageKey) && !wasVisualRecentlyUsed(visualUsage, candidate);
    });

    if (unusedAndNotRecent) {
      markVisualUsage(visualUsage, usageScope, unusedAndNotRecent);
      return unusedAndNotRecent;
    }

    const notRecent = pickEventVisualCandidate(candidatePool, startIndex, (candidate) =>
      !wasVisualRecentlyUsed(visualUsage, candidate)
    );

    if (notRecent) {
      markVisualUsage(visualUsage, usageScope, notRecent);
      return notRecent;
    }

    const unusedForKey = pickEventVisualCandidate(candidatePool, startIndex, (candidate) => {
      const usageKey = getVisualUsageKey(candidate);
      return usageKey && !usedForKey.has(usageKey);
    });

    if (unusedForKey) {
      markVisualUsage(visualUsage, usageScope, unusedForKey);
      return unusedForKey;
    }

    if (!fallback?.src) return null;
    markVisualUsage(visualUsage, usageScope, fallback);
    return fallback;
  }

  /* === END BLOCK: EVENT_CARD_READY_VISUAL_POOL_V1 === */

  function ensureContainer() {
    if (!container) container = document.getElementById("event-cards");
    return container;
  }

function createCard(event, visualUsage = null) {
  const card = document.createElement("div");
  card.className = "event-card";
  card.tabIndex = 0;

  /* === BEGIN BLOCK: DESKTOP_CARD_BEHAVIOR_AND_CONTENT_V2 | Purpose: Desktop-Card anreichern und Desktop vom Detailpanel entkoppeln, Mobile unverändert lassen | Scope: createCard komplett konsolidiert inkl. Desktop-Aktionen, direkter Desktop-Navigation und ruhigen Zusatzinfos === */

  const isDesktopViewport = () => window.matchMedia("(min-width: 900px)").matches;

  const normalizeHttpUrl = (raw) => {
    const value = String(raw || "").trim();
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
  };

  const parseTimeParts = (raw) => {
    const matches = String(raw || "").match(/\b(\d{1,2}:\d{2})\b/g) || [];
    return {
      start: matches[0] || "",
      end: matches[1] || ""
    };
  };

  const buildGoogleCalendarUrl = (ev) => {
    const date = String(ev?.date || "").trim();
    if (!date) return "";

    const ymd = date.replaceAll("-", "");
    const { start, end } = parseTimeParts(ev?.time);

    const params = new URLSearchParams();
    params.set("action", "TEMPLATE");
    params.set("text", String(ev?.title || "Event").trim());

    if (start) {
      const startStamp = `${ymd}T${start.replace(":", "")}00`;
      const endStamp = `${ymd}T${(end || start).replace(":", "")}00`;
      params.set("dates", `${startStamp}/${endStamp}`);
    } else {
      params.set("dates", `${ymd}/${ymd}`);
    }

    const locationValue = String(ev?.location || "").trim();
    const descriptionValue = String(ev?.description || "").trim();

    if (locationValue) params.set("location", locationValue);
    if (descriptionValue) params.set("details", descriptionValue);

    return `https://calendar.google.com/calendar/render?${params.toString()}`;
  };

  const buildSharePayload = (ev, fallbackUrl) => {
    const title = String(ev?.title || "Event").trim();
    const datePart = ev?.date ? formatDate(ev.date) : "";
    const timePart = String(ev?.time || "").trim();
    const locationPart = String(ev?.location || "").trim();

    const lines = [
      title,
      [datePart, timePart].filter(Boolean).join(" · "),
      locationPart,
      fallbackUrl
    ].filter(Boolean);

    return {
      title,
      text: lines.join("\n"),
      url: fallbackUrl
    };
  };

     /* === BEGIN BLOCK: SAFE_DESKTOP_EXTERNAL_OPEN_WITH_ANALYTICS_V3 | Purpose: keeps the desktop direct-open behavior for event cards, but sends a clean outbound analytics event before opening the external target; Scope: replaces only the desktop external-open helper inside createCard === */
  const openPrimaryDesktopTarget = (url, analyticsPayload = null) => {
    if (!url) return false;

    try {
      if (
        analyticsPayload &&
        window.BEAnalytics &&
        typeof window.BEAnalytics.trackOutboundClick === "function"
      ) {
        window.BEAnalytics.trackOutboundClick(analyticsPayload);
      }

      window.open(url, "_blank", "noopener,noreferrer");
      return true;
    } catch (_) {
      return false;
    }
  };
  /* === END BLOCK: SAFE_DESKTOP_EXTERNAL_OPEN_WITH_ANALYTICS_V3 === */
  const resolveCity = (ev) => {
    if (ev?.city && String(ev.city).trim()) return String(ev.city).trim();

    const loc = String(ev?.location || "").toLowerCase();

    if (loc.includes("rhede")) return "Rhede";
    if (loc.includes("isselburg")) return "Isselburg";
    if (loc.includes("borken")) return "Borken";
    if (loc.includes("bocholt")) return "Bocholt";

    return "Bocholt";
  };
  const city = resolveCity(event);
  const cardVisual = resolveEventVisual(event, visualUsage);

  if (cardVisual) {
    card.classList.add("event-card--with-media");
  }

  let dateLabel = "";

  if (event?.date && event?.endDate && event.endDate !== event.date) {
    dateLabel = `${formatDate(event.date)} – ${formatDate(event.endDate)}`;
  } else if (event?.date) {
    dateLabel = formatDate(event.date);
  }

  /* === BEGIN BLOCK: EVENT_CARD_BADGE_EFFECTIVE_DATE_V1 | Purpose: Badge-Datum an die range-aware Feed-Logik angleichen, damit laufende Mehrtages-Events unter "Heute" auch das heutige Datum im Badge zeigen | Scope: ersetzt nur die Badge-Datumsbasis innerhalb von createCard === */
  const badgeDateObj = getEffectiveDate(event) || parseISODateLocal(event?.date);
  const weekdayShort = badgeDateObj
    ? new Intl.DateTimeFormat("de-DE", { weekday: "short" })
        .format(badgeDateObj)
        .replace(".", "")
        .toUpperCase()
    : "";
  const dayNum = badgeDateObj ? String(badgeDateObj.getDate()).padStart(2, "0") : "";
  const monthShort = badgeDateObj
    ? new Intl.DateTimeFormat("de-DE", { month: "short" })
        .format(badgeDateObj)
        .replace(".", "")
        .toUpperCase()
    : "";
  /* === END BLOCK: EVENT_CARD_BADGE_EFFECTIVE_DATE_V1 === */

  const badge = document.createElement("div");
  badge.className = "event-date-badge";
  badge.setAttribute("aria-hidden", "true");

  const bWday = document.createElement("div");
  bWday.className = "event-date-badge__wday";
  bWday.textContent = weekdayShort;

  const bDay = document.createElement("div");
  bDay.className = "event-date-badge__day";
  bDay.textContent = dayNum;

  const bMonth = document.createElement("div");
  bMonth.className = "event-date-badge__month";
  bMonth.textContent = monthShort;

  badge.appendChild(bWday);
  badge.appendChild(bDay);
  badge.appendChild(bMonth);

  const body = document.createElement("div");
  body.className = "event-card-body";

  const timeText = event?.time ? String(event.time).trim() : "";
  const locTextRaw = String(event?.location || "").trim();

  const isRange = !!(event?.date && event?.endDate && event.endDate !== event.date);
  const timeLine = (() => {
    if (isRange) {
      if (dateLabel && timeText) return `${dateLabel} · ${timeText}`;
      return String(dateLabel).trim();
    }
    if (timeText) return timeText;
    return String(dateLabel).trim();
  })();

  const cityRaw = String(city || "").trim();
  const cityIsUseful = !!cityRaw && cityRaw !== "Bocholt";
  const placeLine = locTextRaw || (cityIsUseful ? cityRaw : "");

  const meta = document.createElement("div");
  meta.className = "event-meta";

  if (timeLine) {
    const timeEl = document.createElement("span");
    timeEl.className = "event-meta__time";
    timeEl.textContent = timeLine;
    meta.appendChild(timeEl);
  }

  if (placeLine) {
    const placeEl = document.createElement("span");
    placeEl.className = "event-meta__place";
    placeEl.textContent = placeLine;
    meta.appendChild(placeEl);
  }
  const h3 = document.createElement("h3");
  h3.className = "event-title";

  const titleText = document.createElement("span");
  titleText.className = "event-title__text";
  titleText.textContent = event?.title ? String(event.title) : "Event";
  h3.appendChild(titleText);

  const kategorieRaw = String(event?.kategorie || "").trim();
  const canonicalCategory =
    (window.FilterModule?.normalizeCategory ? window.FilterModule.normalizeCategory(kategorieRaw) : "") ||
    kategorieRaw;

  const iconKey =
    canonicalCategory && window.Icons?.categoryKey
      ? (window.Icons.categoryKey(canonicalCategory) || "calendar")
      : "";

  if (iconKey && window.Icons?.svg) {
    const icon = document.createElement("span");
    icon.className = "event-category-icon";
    icon.setAttribute("role", "img");
    icon.setAttribute("aria-label", `Kategorie: ${canonicalCategory || "Kalender"}`);
    icon.innerHTML = window.Icons.svg(iconKey, { className: "event-icon-svg is-category" });
    h3.appendChild(icon);
  }

  const descText = String(event?.description || "").trim();
  const desc = document.createElement("p");
  desc.className = "event-card-desc";
  desc.textContent = descText;

  const quietMetaItems = [];
  if (canonicalCategory) quietMetaItems.push(canonicalCategory);
  if (cityIsUseful) quietMetaItems.push(cityRaw);
  else if (!cityIsUseful && cityRaw) quietMetaItems.push(cityRaw);

  const quietMeta = document.createElement("div");
  quietMeta.className = "event-card-meta-quiet";
  quietMeta.textContent = quietMetaItems.join(" · ");

  const primaryUrl = normalizeHttpUrl(
    event?.url || event?.website || event?.sourceUrl || event?.source_url || ""
  );
  const calendarUrl = buildGoogleCalendarUrl(event);
  const sharePayload = buildSharePayload(event, primaryUrl);
  const primaryOutboundPayload = primaryUrl
    ? {
        outboundType: "website",
        entityType: "event",
        entityId: String(event?.id || "").trim(),
        entityTitle: String(event?.title || "").trim(),
        destinationUrl: primaryUrl,
        ...(window.BEAnalytics?.buildReportingTargetPayload
          ? window.BEAnalytics.buildReportingTargetPayload(event)
          : {})
      }
    : null;

  card.setAttribute("role", "button");
  card.setAttribute(
    "aria-label",
    primaryUrl ? `Event öffnen: ${event?.title || ""}` : `Event anzeigen: ${event?.title || ""}`
  );
  if (primaryUrl) card.dataset.desktopBehavior = "direct-link";

  const actions = document.createElement("div");
  actions.className = "event-card-actions";

  if (primaryUrl) {
    const primaryLink = document.createElement("a");
    primaryLink.className = "event-card-action event-card-action--primary";
    primaryLink.href = primaryUrl;
    primaryLink.target = "_blank";
    primaryLink.rel = "noopener";
    primaryLink.textContent = "Zur Veranstaltung";
    primaryLink.setAttribute("aria-label", `Zur Veranstaltung: ${event?.title || "Event"}`);
    primaryLink.addEventListener("click", (e) => {
      if (
        primaryOutboundPayload &&
        window.BEAnalytics &&
        typeof window.BEAnalytics.trackOutboundClick === "function"
      ) {
        window.BEAnalytics.trackOutboundClick(primaryOutboundPayload);
      }
      e.stopPropagation();
    });
    actions.appendChild(primaryLink);
  }

  if (calendarUrl) {
    const calendarLink = document.createElement("a");
    calendarLink.className = "event-card-action";
    calendarLink.href = calendarUrl;
    calendarLink.target = "_blank";
    calendarLink.rel = "noopener";
    calendarLink.textContent = "In Kalender";
    calendarLink.setAttribute("aria-label", `In Kalender: ${event?.title || "Event"}`);
    calendarLink.addEventListener("click", (e) => e.stopPropagation());
    actions.appendChild(calendarLink);
  }

  const shareButton = document.createElement("button");
  shareButton.type = "button";
  shareButton.className = "event-card-action";
  shareButton.textContent = "Teilen";
  shareButton.setAttribute("aria-label", `Event teilen: ${event?.title || "Event"}`);
  shareButton.addEventListener("click", async (e) => {
    e.preventDefault();
    e.stopPropagation();

    try {
      if (navigator.share) {
        await navigator.share({
          title: sharePayload.title,
          text: sharePayload.text,
          url: sharePayload.url || undefined
        });
        return;
      }
    } catch (_) {}

    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(
          [sharePayload.text, sharePayload.url].filter(Boolean).join("\n")
        );
      }
    } catch (_) {}
  });
  actions.appendChild(shareButton);

  body.appendChild(h3);
  if (meta.textContent) body.appendChild(meta);
  if (descText) body.appendChild(desc);
  if (quietMeta.textContent) body.appendChild(quietMeta);
  if (actions.childNodes.length) body.appendChild(actions);

  card.appendChild(badge);

  if (cardVisual) {
    const media = document.createElement("figure");
    media.className = "event-card-media";

    const image = document.createElement("img");
    image.className = "event-card-media__img";
    image.src = cardVisual.src;
    image.alt = cardVisual.alt || "";
    image.loading = "lazy";
    image.decoding = "async";
    image.width = 1200;
    image.height = 675;

    media.appendChild(image);

    if (window.ImageAttribution?.renderCreditAccessLink) {
      const wrapper = document.createElement("div");
      wrapper.innerHTML = window.ImageAttribution.renderCreditAccessLink(cardVisual, {
        entityType: "event",
        entityId: String(event?.id || "").trim(),
        entityTitle: titleText,
        imageId: String(cardVisual.id || "").trim(),
        label: "Bildnachweis"
      });
      const creditLink = wrapper.firstElementChild;
      if (creditLink) {
        creditLink.addEventListener("click", (clickEvent) => {
          clickEvent.stopPropagation();
        });
        media.appendChild(creditLink);
      }
    }

    card.appendChild(media);
  }

  card.appendChild(body);

  /* === BEGIN BLOCK: DESKTOP_NO_DETAILPANEL_FALLBACK_V3 | Purpose: behebt den Syntaxfehler in openCard und erhält das gewünschte Verhalten bei Event-Cards: Desktop öffnet extern, Mobile nutzt das Detailpanel | Scope: ersetzt nur openCard innerhalb createCard === */
  const openCard = (e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }

    if (isDesktopViewport()) {
      if (primaryUrl) {
        openPrimaryDesktopTarget(primaryUrl, primaryOutboundPayload);
      }
      return;
    }

    if (window.DetailPanel?.show) {
      window.DetailPanel.show({
        ...event,
        resolvedVisual: cardVisual
      });
      return;
    }

    if (primaryUrl) {
      openPrimaryDesktopTarget(primaryUrl, primaryOutboundPayload);
    }
  };
  /* === END BLOCK: DESKTOP_NO_DETAILPANEL_FALLBACK_V3 === */

  card.addEventListener("click", (e) => {
    if (e.target.closest("[data-image-credit-access]")) {
      e.stopPropagation();
      return;
    }

    openCard(e);
  });

  card.addEventListener("keydown", (e) => {
    if (e.target.closest("[data-image-credit-access]")) return;
    if (e.key !== "Enter" && e.key !== " ") return;
    openCard(e);
  });

  return card;
  /* === END BLOCK: DESKTOP_CARD_BEHAVIOR_AND_CONTENT_V2 === */
}

/* === BEGIN BLOCK: EVENT LIST SECTIONS (dynamic headers: today/weekend/soon/later + exact date) ===
Zweck:
- Saubere, app-typische Zeit-Sections ohne doppelte Header (Buckets werden vorab gesammelt).
- Reihenfolge: Heute → Diese Woche → Dieses Wochenende → Nächste Woche → Später.
- Bei exakter Datumsauswahl genau eine Datums-Section statt relativer Buckets.
Umfang:
- Ersetzt ausschließlich die Section-Logik in renderList(list).
=== */
function renderList(list) {
  const c = ensureContainer();
  c.innerHTML = "";

  if (!list || list.length === 0) {
    c.innerHTML = `
      <div class="empty-state">
        <div class="empty-state__card" role="status" aria-live="polite">
          <div class="empty-state__title">Keine Events gefunden</div>
          <div class="empty-state__text">Filter anpassen oder zurücksetzen.</div>
          <button type="button" class="empty-state__btn" id="empty-reset-btn">Filter zurücksetzen</button>
        </div>
      </div>
    `;

    const btn = document.getElementById("empty-reset-btn");
    if (btn) {
      btn.addEventListener("click", () => {
        if (window.FilterModule?.resetFacetFilters) {
          window.FilterModule.resetFacetFilters();
          return;
        }
        const resetPill = document.getElementById("filter-reset-pill");
        if (resetPill) resetPill.click();
      });
    }
    return;
  }

  const visualUsage = Object.create(null);
/* === BEGIN BLOCK: FEED_PUBLISH_ENTRY_LUCIDE_CHEVRON_V1 | Zweck: ersetzt den textbasierten Chevron im mobilen Veranstalter-Entry durch ein globales Lucide-SVG; Umfang: ersetzt nur createFeedPublishEntry() === */
const createFeedPublishEntry = () => {
  const link = document.createElement("a");
  link.className = "feed-publish-entry";
  link.href = "/events-veroeffentlichen/";
  link.setAttribute("aria-label", "Für Veranstalter – Event einreichen");

  const label = document.createElement("span");
  label.className = "feed-publish-entry__label";
  label.textContent = "Für Veranstalter";

  const main = document.createElement("span");
  main.className = "feed-publish-entry__main";

  const title = document.createElement("span");
  title.className = "feed-publish-entry__title";
  title.textContent = "Event einreichen";

  const chevron = document.createElement("span");
  chevron.className = "feed-publish-entry__chevron";
  chevron.setAttribute("aria-hidden", "true");
  chevron.innerHTML = window.Icons?.svg
    ? window.Icons.svg("chevron-right", { className: "feed-publish-entry__chevron-svg" })
    : "";

  main.append(title, chevron);
  link.append(label, main);

  return link;
};
/* === END BLOCK: FEED_PUBLISH_ENTRY_LUCIDE_CHEVRON_V1 === */

  const createSectionHeader = (label) => {
    const h = document.createElement("div");
    h.className = "events-section-title";
    h.textContent = label;
    return h;
  };

  const selectedDate = String(window.FilterModule?.filters?.selectedDate || "").trim();
  if (selectedDate) {
    const selectedDateObj = parseISODateLocal(selectedDate);
    const selectedLabel = selectedDateObj
      ? `Am ${new Intl.DateTimeFormat("de-DE", { weekday: "long", day: "2-digit", month: "long" }).format(selectedDateObj)}`
      : "Am gewählten Datum";

    const section = document.createElement("section");
    section.className = "events-feed-group";
    section.setAttribute("aria-label", selectedLabel);

    const grid = document.createElement("div");
    grid.className = "events-feed-group__grid";

    section.appendChild(createSectionHeader(selectedLabel));
    for (const ev of list) {
      grid.appendChild(createCard(ev, visualUsage));
    }
    section.appendChild(grid);

    const frag = document.createDocumentFragment();
    frag.appendChild(section);
    frag.appendChild(createFeedPublishEntry());
    c.appendChild(frag);
    return;
  }

  /* === BEGIN BLOCK: RENDER_LIST_EFFECTIVE_DAY_V1 ===
  Zweck: Section-Bucketing nutzt dieselbe range-aware Datumsbasis wie Sortierung/Filterung.
  Umfang: Laufende Mehrtages-Events werden im Feed als "heute" gruppiert statt nur nach Startdatum.
  === */
  const toDay = (ev) => {
    const d = getEffectiveDate(ev);
    if (!d) return null;

    const day = new Date(d);
    day.setHours(0, 0, 0, 0);
    return day;
  };
  /* === END BLOCK: RENDER_LIST_EFFECTIVE_DAY_V1 === */

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const addDays = (d, n) => {
    const t = new Date(d);
    t.setDate(t.getDate() + n);
    return t;
  };

  const endOfWeek = (d) => {
    const t = new Date(d);
    const delta = (7 - t.getDay()) % 7;
    t.setDate(t.getDate() + delta);
    t.setHours(23, 59, 59, 999);
    return t;
  };

  /* === BEGIN BLOCK: RENDER_LIST_WEEKEND_RANGE_REUSE_V1 ===
  Zweck: renderList nutzt die bereits global definierte, robuste Weekend-Range-Logik.
  Umfang: entfernt die lokale Doppelimplementierung, damit "Dieses Wochenende" am Sa/So das aktuelle Wochenende bleibt.
  === */
  /* renderList verwendet bewusst die globale Funktion getNextWeekendRange(today). */
  /* === END BLOCK: RENDER_LIST_WEEKEND_RANGE_REUSE_V1 === */

  const endOfDay = (d) => {
    const t = new Date(d);
    t.setHours(23, 59, 59, 999);
    return t;
  };

  const dow = today.getDay();
  const hasThisWeek = dow >= 1 && dow <= 4;
  const thisWeekStart = addDays(today, 1);
  thisWeekStart.setHours(0, 0, 0, 0);

  let thisWeekEnd = null;
  if (hasThisWeek) {
    const daysUntilThu = 4 - dow;
    const thu = addDays(today, daysUntilThu);
    thisWeekEnd = endOfDay(thu);
  }

  const weekend = getNextWeekendRange(today);
  const weekendStart = new Date(weekend.start);
  const weekendEnd = new Date(weekend.end);

  const endThisWeek = endOfWeek(today);
  const nextWeekStart = addDays(endThisWeek, 1);
  nextWeekStart.setHours(0, 0, 0, 0);

  const nextWeekEnd = addDays(nextWeekStart, 6);
  nextWeekEnd.setHours(23, 59, 59, 999);

  const buckets = {
    today: [],
    week: [],
    weekend: [],
    nextweek: [],
    later: []
  };

  const bucketLabel = {
    today: "Heute",
    week: "Diese Woche",
    weekend: "Dieses Wochenende",
    nextweek: "Nächste Woche",
    later: "Später"
  };

  /* === BEGIN BLOCK: EVENT_FEED_BUCKET_RANGE_OVERLAP_V2 | Zweck: Feed-Gruppierung für Mehrtagesevents laufzeitbasiert machen, damit aktive Tage in passende Zeitgruppen fallen | Umfang: ersetzt pickBucket(day) und die Bucket-Zuweisung in renderList() === */
  const getEventRange = (ev) => {
    const start = parseISODateLocal(ev?.date);
    if (!start) return null;

    const endBase = ev?.endDate ? parseISODateLocal(ev.endDate) : new Date(start);
    if (!endBase) return null;

    const end = endOfDay(endBase);
    if (end < start) return null;

    return { start, end };
  };

  const overlaps = (range, start, end) => {
    if (!range || !start || !end) return false;
    return range.end >= start && range.start <= end;
  };

  const pickBucket = (ev) => {
    const range = getEventRange(ev);
    if (!range) return "later";

    const todayEnd = endOfDay(today);
    if (overlaps(range, today, todayEnd)) return "today";
    if (hasThisWeek && overlaps(range, thisWeekStart, thisWeekEnd)) return "week";
    if (overlaps(range, weekendStart, weekendEnd)) return "weekend";
    if (overlaps(range, nextWeekStart, nextWeekEnd)) return "nextweek";
    return "later";
  };

  for (const ev of list) {
    const key = pickBucket(ev);
    buckets[key].push(ev);
  }
  /* === END BLOCK: EVENT_FEED_BUCKET_RANGE_OVERLAP_V2 === */

  const order = ["today", "week", "weekend", "nextweek", "later"];
  const frag = document.createDocumentFragment();
  let hasInsertedFeedPublishEntry = false;

  for (const key of order) {
    if (!buckets[key] || buckets[key].length === 0) continue;

    const section = document.createElement("section");
    section.className = "events-feed-group";
    section.setAttribute("aria-label", bucketLabel[key] || key);

    const grid = document.createElement("div");
    grid.className = "events-feed-group__grid";

    section.appendChild(createSectionHeader(bucketLabel[key] || key));

    for (const ev of buckets[key]) {
      grid.appendChild(createCard(ev, visualUsage));
    }

    section.appendChild(grid);
    frag.appendChild(section);

    if (!hasInsertedFeedPublishEntry) {
      frag.appendChild(createFeedPublishEntry());
      hasInsertedFeedPublishEntry = true;
    }
  }

  c.appendChild(frag);
}
/* === END BLOCK: EVENT LIST SECTIONS (dynamic headers: today/weekend/soon/later + exact date) === */

/* === BEGIN BLOCK: GS-01 SKELETON RENDER (stable feed while loading) ===
Zweck: Während Fetch laufen Skeleton-Cards in #event-cards rendern (kein Layout-Jump).
Umfang: Fügt renderSkeleton(count) hinzu (Rendering-only).
=== */
  function createSkeletonCard() {
    const card = document.createElement("div");
    card.className = "event-card is-skeleton";
    card.tabIndex = -1;
    card.setAttribute("aria-hidden", "true");

    const badge = document.createElement("div");
    badge.className = "event-date-badge";

    const b1 = document.createElement("div");
    b1.className = "event-date-badge__wday skel-line skel-line--sm";
    b1.style.width = "34px";

    const b2 = document.createElement("div");
    b2.className = "event-date-badge__day skel-line";
    b2.style.width = "28px";
    b2.style.marginTop = "6px";

    const b3 = document.createElement("div");
    b3.className = "event-date-badge__month skel-line skel-line--sm";
    b3.style.width = "36px";
    b3.style.marginTop = "6px";

    badge.appendChild(b1);
    badge.appendChild(b2);
    badge.appendChild(b3);

    const body = document.createElement("div");
    body.className = "event-card-body";

    const title = document.createElement("div");
    title.className = "event-title";
    const t1 = document.createElement("span");
    t1.className = "skel-line";
    t1.style.width = "72%";
    title.appendChild(t1);

    const meta = document.createElement("div");
    meta.className = "event-meta";
    const m1 = document.createElement("span");
    m1.className = "skel-line skel-line--sm";
    m1.style.width = "44%";
    meta.appendChild(m1);

    body.appendChild(title);
    body.appendChild(meta);

    card.appendChild(badge);
    card.appendChild(body);

    return card;
  }

  function renderSkeleton(count = 8) {
    const c = ensureContainer();
    c.innerHTML = "";

    const n = Math.max(1, Math.min(12, Number(count) || 8));
    for (let i = 0; i < n; i++) c.appendChild(createSkeletonCard());
  }
/* === END BLOCK: GS-01 SKELETON RENDER (stable feed while loading) === */

  function normalizeEvents(events) {
    return (events || [])
      .filter((e) => {
        const bucket = getEventBucket(e);
        return bucket !== "past" && bucket !== "invalid";
      })
      .sort(sortByDateAsc);
  }

  function render(events) {
    if (!CONFIG?.features?.showEventCards) return;
    const list = normalizeEvents(events);
    renderList(list);
  }

  function refresh(events) {
    render(events);
  }

  return { render, refresh, renderSkeleton, setVisualPools };
})();
/* === END BLOCK: EVENT_CARDS MODULE (render-only, no implicit this) === */
// END: EVENT_CARDS




































