/**
 * EVENTS.JS – Event Cards + Suche + Chips (Zeit / Quick)
 * Stabil, schema-korrekt, DetailPanel-kompatibel
 *
 * - Location-Dropdown entfernt
 * - Location-Klick führt zur Homepage (via Locations mapping), Fallback Maps
 * - Zeit-Chips: all / today / weekend / soon
 * - Quick-Interests: setzen/ersetzen Suche (kein Append)
 */

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
const EventCards = {
  events: [],
  filteredEvents: [],
  container: null,

  // Filter-State
  searchQuery: "",
  activeTime: "all", // all | today | weekend | soon

  // DOM refs
  searchInput: null,
  timeChips: [],
  quickChips: [],
  filterReady: false,

  render(events) {
    if (!CONFIG?.features?.showEventCards) return;

    this.container = document.getElementById("event-cards");
    if (!this.container) return;

    this.events = (events || [])
      .filter((e) => {
        const bucket = getEventBucket(e);
        return bucket !== "past" && bucket !== "invalid";
      })
      .sort(sortByDateAsc);

    this.ensureFilters();
    this.applyFiltersAndRender();
  },

  /* ---------- Filters wiring ---------- */
  ensureFilters() {
    if (this.filterReady) return;

    // Search
    this.searchInput = document.getElementById("search-filter");
    if (this.searchInput) {
      this.searchInput.addEventListener("input", () => {
        this.searchQuery = this.searchInput.value.trim();

        // If user types manually, clear quick-chip pressed state
        if (this.quickChips?.length) {
          this.quickChips.forEach((b) => b.setAttribute("aria-pressed", "false"));
        }

        this.applyFiltersAndRender();
      });
    }

    // Time chips
    this.timeChips = Array.from(document.querySelectorAll(".chip--time"));
    this.timeChips.forEach((btn) => {
      btn.addEventListener("click", () => {
        const t = btn.getAttribute("data-time") || "all";
        this.activeTime = t;
        this.updateTimeChipUI();
        this.applyFiltersAndRender();
      });
    });

    /* === BEGIN BLOCK: QUICK-CHIPS REPLACE SEARCH (no append) + PRESSED STATE ===
Zweck: Quick-Interest-Chip ersetzt die Suche (kein Append) und setzt pressed-state (A11y/UX).
Umfang: Ersetzt den kompletten Quick-Interests-Click-Handler in ensureFilters().
=== */
    this.quickChips = Array.from(document.querySelectorAll(".quick-interests .chip"));
    this.quickChips.forEach((btn) => {
      btn.setAttribute("aria-pressed", "false");

      btn.addEventListener("click", () => {
        const q = (btn.getAttribute("data-q") || "").trim();
        if (!q) return;

        // pressed-state (nur einer aktiv)
        this.quickChips.forEach((b) => b.setAttribute("aria-pressed", "false"));
        btn.setAttribute("aria-pressed", "true");

        // Immer ersetzen (nicht anhängen)
        this.searchQuery = q;
        if (this.searchInput) this.searchInput.value = q;

        this.applyFiltersAndRender();
      });
    });
/* === END BLOCK: QUICK-CHIPS REPLACE SEARCH (no append) + PRESSED STATE === */

    // Initial UI state
    this.updateTimeChipUI();

    this.filterReady = true;
  },

  updateTimeChipUI() {
    this.timeChips.forEach((btn) => {
      const t = btn.getAttribute("data-time") || "all";
      btn.classList.toggle("is-active", t === this.activeTime);
    });
  },

  matchesSearch(event) {
    if (!this.searchQuery) return true;
    const q = this.searchQuery.toLowerCase();
    return [event.title, event.location, event.description]
      .join(" ")
      .toLowerCase()
      .includes(q);
  },

  matchesTime(event) {
    if (this.activeTime === "all") return true;

    const eventDate = parseISODateLocal(event?.date);
    if (!eventDate) return false;

    const today = startOfToday();

    if (this.activeTime === "today") {
      return isSameDay(eventDate, today);
    }

    if (this.activeTime === "weekend") {
      const { start, end } = getNextWeekendRange(today);
      const d = new Date(eventDate);
      d.setHours(12, 0, 0, 0); // robust
      return d >= start && d <= end;
    }

    if (this.activeTime === "soon") {
      // "Demnächst" = nächste 7 Tage inkl. heute
      const end = addDays(today, 7);
      end.setHours(23, 59, 59, 999);

      const d = new Date(eventDate);
      d.setHours(12, 0, 0, 0);
      return d >= today && d <= end;
    }

    return true;
  },

  applyFiltersAndRender() {
    this.filteredEvents = this.events
      .filter((e) => this.matchesTime(e))
      .filter((e) => this.matchesSearch(e));

    this.renderGroups(this.filteredEvents);
  },

  /* ---------- Groups ---------- */

  /* === BEGIN BLOCK: GROUP RENDERING (filter-aware, cleaner UX) ===
Zweck: Gruppentitel passen zum aktiven Zeitfilter; weniger visuelles Rauschen.
Umfang: Ersetzt renderGroups(events).
=== */
  renderGroups(events) {
    this.container.innerHTML = "";

    // Wenn ein Zeitfilter aktiv ist (nicht "all"), rendere eine einzige Gruppe
    if (this.activeTime && this.activeTime !== "all") {
      const titleMap = {
        today: "Heute",
        weekend: "Wochenende",
        soon: "Demnächst"
      };
      const title = titleMap[this.activeTime] || "Events";

      if (events.length) {
        this.renderGroup(title, events);
        return;
      }

      this.renderEmptyState();
      return;
    }

    // Default: All -> Bucket groups
    const groups = { today: [], week: [], upcoming: [], later: [] };
    events.forEach((e) => groups[getEventBucket(e)]?.push(e));

    this.renderGroup("Heute", groups.today);
    this.renderGroup("Diese Woche", groups.week);
    this.renderGroup("Kommende Events", groups.upcoming);
    this.renderGroup("Später", groups.later);

    if (!this.container.children.length) {
      this.renderEmptyState();
    }
  },
  /* === END BLOCK: GROUP RENDERING (filter-aware, cleaner UX) === */

  renderGroup(title, list) {
    if (!list.length) return;

    const h = document.createElement("h3");
    h.className = "events-group-title";
    h.textContent = title;
    this.container.appendChild(h);

    list.forEach((event) => {
      this.container.appendChild(this.createCard(event));
    });
  },

  /* === BEGIN BLOCK: EMPTY STATE (reusable) ===
Zweck: Empty State zentral, damit renderGroups sauber bleibt.
Umfang: Neue Methode renderEmptyState() im EventCards-Objekt.
=== */
  renderEmptyState() {
    const wrap = document.createElement("div");
    wrap.className = "empty-state";

    wrap.innerHTML = `
      <div class="empty-state__card">
        <h3 class="empty-state__title">Keine Treffer</h3>
        <p class="empty-state__text">
          Probier einen anderen Suchbegriff oder setz die Filter zurück.
        </p>
        <button type="button" class="empty-state__btn" id="reset-filters-btn">
          Filter zurücksetzen
        </button>
      </div>
    `;

    this.container.appendChild(wrap);

    const btn = wrap.querySelector("#reset-filters-btn");
    if (btn) {
      btn.addEventListener("click", () => this.resetFilters());
    }
  },
  /* === END BLOCK: EMPTY STATE (reusable) === */

  resetFilters() {
    this.searchQuery = "";
    this.activeTime = "all";

    if (this.searchInput) this.searchInput.value = "";
    this.updateTimeChipUI();

    if (this.quickChips?.length) {
      this.quickChips.forEach((b) => b.setAttribute("aria-pressed", "false"));
    }

    this.applyFiltersAndRender();

    // UX: nach Reset wieder nach oben (fühlt sich "fertig" an)
    try {
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch {
      window.scrollTo(0, 0);
    }
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

    const loc = (event.location || "").trim();
    const homepage =
      (window.Locations && Locations.getHomepage && Locations.getHomepage(loc)) || "";
    const href =
      homepage ||
      ((window.Locations && Locations.getMapsFallback && Locations.getMapsFallback(loc)) ||
        `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(
          (loc ? loc + " " : "") + "Bocholt"
        )}`);

    card.innerHTML = `
      <h3 class="event-title">${this.escape(event.title || "")}</h3>
      <div class="event-meta">${dateLabel}${timeLabel}</div>

      ${
        loc
          ? `<a class="event-location event-location-link"
                href="${this.escape(href)}"
                target="_blank"
                rel="noopener noreferrer">
               ${this.escape(loc)}
             </a>`
          : ""
      }
    `;

    // Card → DetailPanel (außer beim Klick auf den Location-Link)
    card.addEventListener("click", (e) => {
      if (e.target.closest(".event-location-link")) return;
      if (typeof DetailPanel !== "undefined" && DetailPanel.show) {
        DetailPanel.show(event);
      }
    });

    card.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      if (e.target.closest(".event-location-link")) return;
      e.preventDefault();
      if (window.DetailPanel?.show) {
        DetailPanel.show(event);
      }
    });

    return card;
  },

  escape(text) {
    const div = document.createElement("div");
    div.textContent = String(text ?? "");
    return div.innerHTML;
  },

  refresh(events) {
    this.filterReady = false;
    this.render(events);
  }
};

debugLog("EventCards loaded successfully");
