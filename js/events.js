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

    // A11y/UX
    card.setAttribute("role", "button");
    card.setAttribute("aria-label", `Event anzeigen: ${event?.title || ""}`);

    /* === BEGIN BLOCK: EVENT CITY RESOLUTION ===
    Zweck: Stellt sicher, dass jedes Event eine Stadt hat.
    Reihenfolge:
    1) event.city (explizit)
    2) aus event.location ableiten
    3) Fallback: "Bocholt"
    Umfang: Lokal in createCard, kein globaler State.
    === */
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
    /* === END BLOCK: EVENT CITY RESOLUTION === */

   /* === BEGIN BLOCK: RANGE DATE LABEL ===
Zweck:
- Mehrtägige Events als Zeitraum anzeigen (20.11 – 10.01)
=== */
let dateLabel = "";

if (event?.date && event?.endDate && event.endDate !== event.date) {
  dateLabel = `${formatDate(event.date)} – ${formatDate(event.endDate)}`;
} else if (event?.date) {
  dateLabel = formatDate(event.date);
}
/* === END BLOCK: RANGE DATE LABEL === */

    const timeLabel = event?.time ? ` · ${escapeHtml(event.time)}` : "";

   /* === BEGIN BLOCK: EVENT META LINE (badge-ready, calm meta) ===
Zweck:
- Vorbereitung Date-Badge Layout (Top-App Pattern): Datum/Uhrzeit als linke Badge, Content rechts.
- Meta-Zeile wird ruhiger: kein permanentes "Bocholt", Stadt nur wenn != Bocholt.
Umfang:
- Ersetzt Meta-Erzeugung + Append-Reihenfolge (h3/meta/location) durch Badge + Body-Wrapper.
=== */

    /* --- Date Badge (left) --- */
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

    // === BEGIN BLOCK: DATE BADGE WITHOUT TIME (clean scan) ===
// Zweck: Badge zeigt nur Wochentag/Tag/Monat (Zeit kommt in Meta-Zeile).
// Umfang: Entfernt nur das Zeit-Element aus dem Badge.
// ===
badge.appendChild(bWday);
badge.appendChild(bDay);
badge.appendChild(bMonth);
// === END BLOCK: DATE BADGE WITHOUT TIME (clean scan) ===


    /* --- Card body (right) --- */
    const body = document.createElement("div");
    body.className = "event-card-body";

        /* === BEGIN BLOCK: CARD 2-LINE CONTENT (title+icon stable, meta = time+location) ===
    Zweck:
    - Zielzustand: 2 Zeilen rechts (Title + Meta), keine 3. Zeile mehr.
    - Icon darf nie “rausrutschen”: Titeltext und Icon werden getrennt gerendert.
    - Meta-Zeile: Zeit • Location (optional Stadt/Range davor), 1 Zeile.
    Umfang:
    - Ersetzt Meta/Title/Location-Erzeugung in createCard (nur Rendering, keine Logik außenrum).
    === */

    /* Meta (1 Zeile): Stadt (optional) • Range (optional) • Zeit (optional) • Location */
    const metaParts = [];

    if (city && city !== "Bocholt") metaParts.push(city);

    if (event?.date && event?.endDate && event.endDate !== event.date) {
      metaParts.push(dateLabel);
    }

    const timeText = event?.time ? String(event.time).trim() : "";
    if (timeText) metaParts.push(timeText);

    const locText = (event?.location || "").trim();
    if (locText) metaParts.push(locText);

    const meta = document.createElement("div");
    meta.className = "event-meta";
    meta.textContent = metaParts.join(" • ");

    /* Title: Text + Icon getrennt (Icon bleibt immer sichtbar) */
    const h3 = document.createElement("h3");
    h3.className = "event-title";

    const titleText = document.createElement("span");
    titleText.className = "event-title__text";
    titleText.textContent = event?.title ? String(event.title) : "Event";

    h3.appendChild(titleText);

    /* === END BLOCK: CARD 2-LINE CONTENT (title+icon stable, meta = time+location) === */


    /* === BEGIN BLOCK: EVENTCARD APPEND CONTENT ===
    Zweck:
    - Kategorie-Icon auf Event Cards wieder anzeigen (oben rechts).
    - Icon ist rein visuell, Card bleibt komplett klickbar.
    Umfang:
    - Erzeugt optional .event-category-icon und hängt sie an die Card.
    - Danach Badge + Body.
    === */

 // === BEGIN BLOCK: CATEGORY ICON INLINE WITH TITLE (canonical + consistent) ===
// Zweck: Icons konsistent zu Canonical Categories (Sheet/Filter/Builder) via FilterModule.normalizeCategory().
// Umfang: Ersetzt nur Icon-Ermittlung; DOM-Anhängung bleibt inline an <h3 class="event-title">.
// ===
const kategorieRaw = (event?.kategorie || "").toString().trim();

// Canonical-Kategorie (Single Source of Truth: FilterModule)
const canonicalCategory =
  (window.FilterModule?.normalizeCategory ? window.FilterModule.normalizeCategory(kategorieRaw) : "") ||
  kategorieRaw;

const iconMap = {
  "Märkte & Feste": "🧺",
  "Kultur & Kunst": "🎭",
  "Musik & Bühne": "🎵",
  "Kinder & Familie": "🧒",
  "Sport & Bewegung": "🏃",
  "Natur & Draußen": "🌿",
  "Innenstadt & Leben": "🏙️",
};

const categoryIcon = canonicalCategory ? (iconMap[canonicalCategory] || "🗓️") : "";

if (categoryIcon) {
  const icon = document.createElement("span");
  icon.className = "event-category-icon";
  icon.setAttribute("role", "img");
  icon.setAttribute("aria-label", `Kategorie: ${canonicalCategory}`);
  icon.textContent = categoryIcon;

  // Icon INLINE in die Titelzeile
  h3.appendChild(icon);
}
// === END BLOCK: CATEGORY ICON INLINE WITH TITLE (canonical + consistent) ===




        // === BEGIN BLOCK: BODY APPEND (2 lines only: title + meta) ===
    // Zweck: Zielzustand 2 Zeilen rechts (Titel + Meta), keine Location-Node mehr anhängen.
    // Umfang: Ersetzt nur das Anhängen der Body-Children.
    // ===
    body.appendChild(h3);
    if (meta.textContent) body.appendChild(meta);
    // === END BLOCK: BODY APPEND (2 lines only: title + meta) ===


    card.appendChild(badge);
    card.appendChild(body);

    /* === END BLOCK: EVENTCARD APPEND CONTENT === */

/* === END BLOCK: EVENT META LINE (badge-ready, calm meta) === */



    /* === BEGIN BLOCK: EVENTCARD CLICK HANDLER === */
    card.addEventListener("click", () => {
      if (window.DetailPanel?.show) window.DetailPanel.show(event);
    });
    /* === END BLOCK: EVENTCARD CLICK HANDLER === */

    // Keyboard: Enter/Space
    card.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      e.preventDefault();
      if (window.DetailPanel?.show) window.DetailPanel.show(event);
    });

    return card;
  }


   /* === BEGIN BLOCK: EVENT LIST SECTIONS (dynamic headers: today/weekend/soon/later) ===
Zweck:
- Saubere, app-typische Zeit-Sections ohne doppelte Header (Buckets werden vorab gesammelt).
- Reihenfolge: Heute → Diese Woche → Dieses Wochenende → Nächste Woche → Später
- Keine Lücken (z.B. Donnerstag verschwindet nicht), kein „Demnächst“ doppelt.
Umfang:
- Ersetzt ausschließlich die Section-Logik in renderList(list).
=== */
  function renderList(list) {
    const c = ensureContainer();
    c.innerHTML = "";

    if (!list || list.length === 0) {
      c.innerHTML = `<div class="empty-state">Keine Events gefunden.</div>`;
      return;
    }

    const today = startOfToday();

    const toDay = (ev) => {
      const effective = ev?.endDate ? getEffectiveDate(ev) : parseISODateLocal(ev?.date);
      if (!effective) return null;
      const d = new Date(effective);
      d.setHours(0, 0, 0, 0);
      return d;
    };

    const endOfDay = (d) => {
      const t = new Date(d);
      t.setHours(23, 59, 59, 999);
      return t;
    };

    // Diese Woche: nur Mo–Do sinnvoll (morgen..Do)
    const dow = today.getDay(); // 0 So ... 6 Sa
    const hasThisWeek = (dow >= 1 && dow <= 4); // Mo–Do
    const thisWeekStart = addDays(today, 1); // morgen
    thisWeekStart.setHours(0, 0, 0, 0);

    let thisWeekEnd = null;
    if (hasThisWeek) {
      const daysUntilThu = (4 - dow);
      const thu = addDays(today, daysUntilThu);
      thisWeekEnd = endOfDay(thu);
    }

    // Wochenende (Fr–So): aktuelles oder kommendes WE relativ zu heute
    const weekend = getNextWeekendRange(today);
    const weekendStart = new Date(weekend.start);
    const weekendEnd = new Date(weekend.end);

    // Nächste Woche: Kalenderwoche nach dieser Woche (Mo..So)
    const endThisWeek = endOfWeek(today); // Sonntag 23:59:59
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

      // Heute
      if (day.getTime() === today.getTime()) return "today";

      // Diese Woche (nur wenn Mo–Do)
      if (hasThisWeek && day >= thisWeekStart && day <= thisWeekEnd) return "week";

      // Wochenende (Fr–So)
      if (day >= weekendStart && day <= weekendEnd) return "weekend";

      // Nächste Woche (Mo–So der Folgewoche)
      if (day >= nextWeekStart && day <= nextWeekEnd) return "nextweek";

      return "later";
    };

    // 1) Events in Buckets sammeln (keine Doppel-Header möglich)
    for (const ev of list) {
      const day = toDay(ev);
      const key = pickBucket(day);
      buckets[key].push(ev);
    }

    // 2) Buckets in fester Reihenfolge rendern (nur wenn nicht leer)
    const order = ["today", "week", "weekend", "nextweek", "later"];
    const frag = document.createDocumentFragment();

    const createSectionHeader = (key) => {
      const h = document.createElement("div");
      h.className = "events-section-title";
      h.textContent = bucketLabel[key] || key;
      return h;
    };

    for (const key of order) {
      if (!buckets[key] || buckets[key].length === 0) continue;
      frag.appendChild(createSectionHeader(key));
      for (const ev of buckets[key]) {
        frag.appendChild(createCard(ev));
      }
    }

    c.appendChild(frag);
  }
/* === END BLOCK: EVENT LIST SECTIONS (dynamic headers: today/weekend/soon/later) === */



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

  return { render, refresh };
})();
/* === END BLOCK: EVENT_CARDS MODULE (render-only, no implicit this) === */
// END: EVENT_CARDS





























