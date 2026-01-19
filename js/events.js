/**
 * EVENTS.JS – Event Cards + Suche + Chips (Zeit / Quick)
 * Stabil, schema-korrekt, DetailPanel-kompatibel
 *
 * - Location-Dropdown entfernt
 * - Location-Klick führt zur Homepage (via Locations mapping), Fallback Maps
 * - Zeit-Chips: all / today / weekend / soon
 * - Quick-Interests: setzen/ergänzen Suche
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

function getNextWeekendRange(today) {
  // Weekend = Samstag+Sonntag der aktuellen Woche (oder nächstes, falls vorbei)
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

  // Wenn heute Sonntag und schon vorbei? (eigentlich nicht relevant bei 00:00)
  return { start, end };
}

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

function sortByDateAsc(a, b) {
  return parseISODateLocal(a.date) - parseISODateLocal(b.date);
}

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

    /* === BEGIN BLOCK: QUICK-CHIPS REPLACE SEARCH (no append) ===
   Zweck: Bei Klick auf Quick-Interest-Chip wird die Suche geleert und NUR der Chip-Begriff gesetzt.
   Umfang: Ersetzt den kompletten Quick-Interests-Click-Handler in ensureFilters().
=== */
this.quickChips = Array.from(document.querySelectorAll(".quick-interests .chip"));
this.quickChips.forEach((btn) => {
  btn.addEventListener("click", () => {
    const q = (btn.getAttribute("data-q") || "").trim();
    if (!q) return;

    // Immer ersetzen (nicht anhängen)
    this.searchQuery = q;
    if (this.searchInput) this.searchInput.value = q;

    this.applyFiltersAndRender();
  });
});
/* === END BLOCK: QUICK-CHIPS REPLACE SEARCH (no append) === */


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
  renderGroups(events) {
    const groups = { today: [], week: [], upcoming: [], later: [] };
    events.forEach((e) => groups[getEventBucket(e)]?.push(e));

    this.container.innerHTML = "";

    this.renderGroup("Heute", groups.today);
    this.renderGroup("Diese Woche", groups.week);
    this.renderGroup("Kommende Events", groups.upcoming);
    this.renderGroup("Später", groups.later);

    if (!this.container.children.length) {
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
    }
  },

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

  resetFilters() {
    this.searchQuery = "";
    this.activeTime = "all";

    if (this.searchInput) this.searchInput.value = "";
    this.updateTimeChipUI();
    this.applyFiltersAndRender();
  },

  /* ---------- Card ---------- */
  createCard(event) {
    const card = document.createElement("div");
    card.className = "event-card";
    card.tabIndex = 0;

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


