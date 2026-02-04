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
Zweck: Wochenende-Chip zeigt das nächste anstehende Wochenende (Sa+So).
Umfang: Ersetzt getNextWeekendRange().
=== */
function getNextWeekendRange(today) {
  const d = new Date(today);
  d.setHours(0, 0, 0, 0);

  const day = d.getDay(); // 0 So ... 6 Sa
  const daysUntilSat = (6 - day + 7) % 7;

  const saturday = addDays(d, daysUntilSat);
  const sunday = addDays(saturday, 1);

  const start = new Date(saturday);
  start.setHours(0, 0, 0, 0);

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

    // === BEGIN BLOCK: CATEGORY ICON INLINE WITH TITLE (scan-friendly, calm) ===
// Zweck: Kategorie-Icon nicht mehr als schwebendes Element, sondern inline in der Titelzeile.
// Umfang: Icon wird an <h3 class="event-title"> angehängt (nicht an die Card).
// ===
const kategorieRaw = (event?.kategorie || "").trim();
const iconMap = {
  Party: "🎉",
  Kneipe: "🍺",
  Kinder: "🧒",
  Quiz: "❓",
  Musik: "🎵",
  Kultur: "🎭"
};
const categoryIcon = kategorieRaw ? (iconMap[kategorieRaw] || "🗓️") : "";

if (categoryIcon) {
  const icon = document.createElement("span");
  icon.className = "event-category-icon";
  icon.setAttribute("role", "img");
  icon.setAttribute("aria-label", `Kategorie: ${kategorieRaw}`);
  icon.textContent = categoryIcon;

  // Icon INLINE in die Titelzeile
  h3.appendChild(icon);
}
// === END BLOCK: CATEGORY ICON INLINE WITH TITLE (scan-friendly, calm) ===


    body.appendChild(h3);
    if (metaParts.length) body.appendChild(meta);
    body.appendChild(location);

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
  Zweck: Lange Listen ohne aktive Filter durch dynamische Zeit-Sektionen gliedern.
         Keine leeren Trenner: Überschrift nur, wenn es Events in der Sektion gibt.
         Sektionen konsistent zu den Filter-Begriffen: Heute / Dieses Wochenende / Demnächst / Später.
  Umfang: Ersetzt ausschließlich renderList(list).
  === */
  function renderList(list) {
    const c = ensureContainer();
    if (!c) return;

    c.innerHTML = "";

    if (!list || list.length === 0) {
      const empty = document.createElement("div");
      empty.className = "events-empty";
      empty.textContent = "Keine passenden Events gefunden.";
      c.appendChild(empty);
      return;
    }

    // --- helpers (lokal, self-contained) ---
    const toLocalDay = (iso) => {
      const dt = parseISODateLocal(iso);
      if (!dt) return null;
      const d = new Date(dt);
      d.setHours(0, 0, 0, 0);
      return d;
    };

    const addDaysLocal = (base, n) => {
      const t = new Date(base);
      t.setDate(t.getDate() + n);
      t.setHours(0, 0, 0, 0);
      return t;
    };

    const sectionLabel = {
      today: "Heute",
      weekend: "Dieses Wochenende",
      soon: "Demnächst",
      later: "Später"
    };

       const getSectionKey = (event) => {
      // Wichtig: KEIN toISOString() verwenden, sonst kippt "heute" je nach Zeitzone auf gestern.
      const effective = event?.endDate ? getEffectiveDate(event) : parseISODateLocal(event?.date);

      if (!effective) return "later";

      const day = new Date(effective);
      day.setHours(0, 0, 0, 0);

      const today = startOfToday();

      // Heute
      if (day.getTime() === today.getTime()) return "today";

      // Dieses Wochenende (nächstes Sa+So)
      const { start, end } = getNextWeekendRange(today);
      if (day >= start && day <= end) return "weekend";

      // Demnächst = nächste 14 Tage (inkl.)
      const soonEnd = addDaysLocal(today, 14);
      soonEnd.setHours(23, 59, 59, 999);
      if (day >= today && day <= soonEnd) return "soon";

      return "later";
    };


    const createSectionHeader = (key) => {
      const h = document.createElement("div");
      h.className = "events-section-title";
      h.textContent = sectionLabel[key] || "Demnächst";
      return h;
    };

    // --- render with dynamic headers (no empty sections) ---
    const frag = document.createDocumentFragment();
    let lastSection = null;

    for (const ev of list) {
      const key = getSectionKey(ev);
      if (key !== lastSection) {
        frag.appendChild(createSectionHeader(key));
        lastSection = key;
      }
      frag.appendChild(createCard(ev));
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


























