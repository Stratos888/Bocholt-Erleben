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

  function ensureContainer() {
    if (!container) container = document.getElementById("event-cards");
    return container;
  }

function createCard(event) {
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

     /* === BEGIN BLOCK: SAFE_DESKTOP_EXTERNAL_OPEN_V2 | Purpose: remove the synthetic hidden-anchor click path and open desktop event targets only via window.open in a new tab; Scope: replaces only the desktop external-open helper inside createCard === */
  const openPrimaryDesktopTarget = (url) => {
    if (!url) return false;

    try {
      window.open(url, "_blank", "noopener,noreferrer");
      return true;
    } catch (_) {
      return false;
    }
  };
  /* === END BLOCK: SAFE_DESKTOP_EXTERNAL_OPEN_V2 === */
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

  let dateLabel = "";

  if (event?.date && event?.endDate && event.endDate !== event.date) {
    dateLabel = `${formatDate(event.date)} – ${formatDate(event.endDate)}`;
  } else if (event?.date) {
    dateLabel = formatDate(event.date);
  }

  const startDateObj = parseISODateLocal(event?.date);
  const weekdayShort = startDateObj
    ? new Intl.DateTimeFormat("de-DE", { weekday: "short" })
        .format(startDateObj)
        .replace(".", "")
        .toUpperCase()
    : "";
  const dayNum = startDateObj ? String(startDateObj.getDate()).padStart(2, "0") : "";
  const monthShort = startDateObj
    ? new Intl.DateTimeFormat("de-DE", { month: "short" })
        .format(startDateObj)
        .replace(".", "")
        .toUpperCase()
    : "";

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
  const timeLine = timeText || (isRange ? String(dateLabel).trim() : "");

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
    primaryLink.addEventListener("click", (e) => e.stopPropagation());
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
  card.appendChild(body);

   /* === BEGIN BLOCK: DESKTOP_NO_DETAILPANEL_FALLBACK_V2 | Purpose: handle the original card interaction explicitly so desktop opens only a new tab while the current homepage stays untouched; Mobile detail-panel behavior remains unchanged | Scope: replaces openCard plus card click/keyboard wiring inside createCard === */
  const openCard = (e) => {
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }

    if (isDesktopViewport()) {
      if (primaryUrl) {
        openPrimaryDesktopTarget(primaryUrl);
      }
      return;
    }

    if (window.DetailPanel?.show) {
      window.DetailPanel.show(event);
      return;
    }

    if (primaryUrl) {
      openPrimaryDesktopTarget(primaryUrl);
    }
  };
  /* === END BLOCK: DESKTOP_NO_DETAILPANEL_FALLBACK_V2 === */

  card.addEventListener("click", (e) => {
    openCard(e);
  });

  card.addEventListener("keydown", (e) => {
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

  const createFeedPublishEntry = () => {
    const link = document.createElement("a");
    link.className = "feed-publish-entry";
    link.href = "/events-veroeffentlichen/";
    link.setAttribute("aria-label", "Für Veranstalter – Event veröffentlichen");

    const label = document.createElement("span");
    label.className = "feed-publish-entry__label";
    label.textContent = "Für Veranstalter";

    const main = document.createElement("span");
    main.className = "feed-publish-entry__main";

    const title = document.createElement("span");
    title.className = "feed-publish-entry__title";
    title.textContent = "Event veröffentlichen";

    const chevron = document.createElement("span");
    chevron.className = "feed-publish-entry__chevron";
    chevron.setAttribute("aria-hidden", "true");
    chevron.textContent = "›";

    main.append(title, chevron);
    link.append(label, main);

    return link;
  };

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
      grid.appendChild(createCard(ev));
    }
    section.appendChild(grid);

    const frag = document.createDocumentFragment();
    frag.appendChild(section);
    frag.appendChild(createFeedPublishEntry());
    c.appendChild(frag);
    return;
  }

  const toDay = (ev) => {
    const d = parseISODateLocal(ev?.date);
    if (!d) return null;
    d.setHours(0, 0, 0, 0);
    return d;
  };

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

  const getNextWeekendRange = (base) => {
    const day = base.getDay();
    const daysUntilFriday = ((5 - day) + 7) % 7;
    const start = addDays(base, daysUntilFriday);
    start.setHours(0, 0, 0, 0);

    const end = addDays(start, 2);
    end.setHours(23, 59, 59, 999);

    return { start, end };
  };

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

  const pickBucket = (day) => {
    if (!day) return "later";
    if (day.getTime() === today.getTime()) return "today";
    if (hasThisWeek && day >= thisWeekStart && day <= thisWeekEnd) return "week";
    if (day >= weekendStart && day <= weekendEnd) return "weekend";
    if (day >= nextWeekStart && day <= nextWeekEnd) return "nextweek";
    return "later";
  };

  for (const ev of list) {
    const day = toDay(ev);
    const key = pickBucket(day);
    buckets[key].push(ev);
  }

  const order = ["today", "week", "weekend", "nextweek", "later"];
  const frag = document.createDocumentFragment();
  let hasInsertedFeedPublishEntry = false;

  for (const key of order) {
    if (!buckets[key] || buckets[key].length === 0) continue;

    const section = document.createElement("section");
    section.className = "events-feed-group";

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

  return { render, refresh, renderSkeleton };
})();
/* === END BLOCK: EVENT_CARDS MODULE (render-only, no implicit this) === */
// END: EVENT_CARDS




































