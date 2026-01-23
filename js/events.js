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
function getEventBucket(event) {
  const eventDate = parseISODateLocal(event?.date);
  if (!eventDate) return "invalid";

  const today = startOfToday();
  const day = new Date(eventDate);
  day.setHours(0, 0, 0, 0);

  if (day < today) return "past";
  if (day.getTime() === today.getTime()) return "today";
  if (day <= endOfWeek(today)) return "week";

  const limit = addDays(today, 30);
  limit.setHours(23, 59, 59, 999);
  if (day <= limit) return "upcoming";

  return "later";
}

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

function sortByDateAsc(a, b) {
  const da = parseISODateLocal(a?.date);
  const db = parseISODateLocal(b?.date);
  if (!da && !db) return 0;
  if (!da) return 1;
  if (!db) return -1;

  const diff = da - db;
  if (diff !== 0) return diff;

  // Same day: sort by time if present
  const ta = parseTimeToMinutes(a?.time);
  const tb = parseTimeToMinutes(b?.time);

  if (ta == null && tb == null) return 0;
  if (ta == null) return 1; // events without time go after timed ones
  if (tb == null) return -1;

  return ta - tb;
}
/* === END BLOCK: SORT (date + time) === */

/* ---------- Event Cards ---------- */
// BEGIN: EVENT_CARDS
const EventCards = {
  events: [],
  container: null,

  render(events) {
    if (!CONFIG?.features?.showEventCards) return;

    this.container = document.getElementById("event-cards");
    if (!this.container) return;

    // Renderer ist bewusst "dumm": keine eigene Filter-UI/State mehr.
    // Minimaler Schutz: keine Past/Invalid Events anzeigen (wie bisher).
    this.events = (events || [])
      .filter((e) => {
        const bucket = getEventBucket(e);
        return bucket !== "past" && bucket !== "invalid";
      })
      .sort(sortByDateAsc);

    this.renderList(this.events);
  },

  refresh(events) {
    this.render(events);
  },

  renderList(list) {
    if (!this.container) return;

    this.container.innerHTML = "";

    if (!list || list.length === 0) {
      const empty = document.createElement("div");
      empty.className = "events-empty";
      empty.textContent = "Keine passenden Events gefunden.";
      this.container.appendChild(empty);
      return;
    }

    const frag = document.createDocumentFragment();
    for (const ev of list) {
      frag.appendChild(this.createCard(ev));
    }
    this.container.appendChild(frag);
  },

  /* ---------- Card ---------- */
   createCard(event) {
    const card = document.createElement("div");
    card.className = "event-card";
    card.tabIndex = 0;

    // A11y/UX: Card ist interaktiv
    card.setAttribute("role", "button");
    card.setAttribute("aria-label", `Event anzeigen: ${event.title || ""}`);

    const dateLabel = event.date ? formatDate(event.date) : "";
    const timeLabel = event.time ? ` · ${this.escape(event.time)}` : "";

    // Title
    const h3 = document.createElement("h3");
    h3.className = "event-title";
    h3.innerHTML = this.escape(event.title || "Event");

    // Meta (date/time)
    const meta = document.createElement("div");
    meta.className = "event-meta";
    meta.innerHTML = `${dateLabel}${timeLabel}`;

    // Location (link -> Google Maps Suche)
    const location = document.createElement("div");
    location.className = "event-location";

    const loc = (event.location || "").trim();
    if (loc) {
      const a = document.createElement("a");
      a.className = "event-location-link";
      a.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(loc)}`;
      a.target = "_blank";
      a.rel = "noopener";
      a.innerHTML = this.escape(loc);
      location.appendChild(a);
    }

    card.appendChild(h3);
    card.appendChild(meta);
    card.appendChild(location);

    // Click: DetailPanel
    card.addEventListener("click", (e) => {
      if (e.target.closest(".event-location-link")) return;
      if (window.DetailPanel?.show) DetailPanel.show(event);
    });

    // Keyboard: Enter/Space
    card.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      if (e.target.closest(".event-location-link")) return;
      e.preventDefault();
      if (window.DetailPanel?.show) DetailPanel.show(event);
    });

    return card;
      }
};
// END: EVENT_CARDS






