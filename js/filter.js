// BEGIN: FILE_HEADER_FILTER
// Datei: js/filter.js
// Zweck:
// - Zentrale Filter-Steuerung (Single Source of Truth)
// - Verwaltet Filter-State (Zeit, Kategorie, Suche)
// - Steuert Filter-UI (Pills + Sheets öffnen/schließen)
// - Wendet Filter auf Event-Daten an
//
// Verantwortlich für:
// - Filter-State halten und ändern
// - Sheet-Logik (Zeit/Kategorie)
// - Reset-Logik
// - Übergabe gefilterter Events an EventCards
//
// Nicht verantwortlich für:
// - Darstellung der Event Cards
// - Aufbau von Event-DOM
// - DetailPanel oder Locations-Modal
//
// Contract:
// - erhält vollständige Event-Liste von main.js
// - ruft EventCards.render(...) / refresh(...) mit gefilterten Events auf
// END: FILE_HEADER_FILTER


/* === BEGIN BLOCK: FILTER MODULE STATE (init flag) ===
Zweck: Stabiler Zustand für Auto-Bootstrap + Schutz gegen doppelte Init.
Umfang: Ersetzt nur die ersten Properties im FilterModule-Objekt.
=== */
const FilterModule = {
  _isInit: false,
  allEvents: [],
  filteredEvents: [],
/* === END BLOCK: FILTER MODULE STATE (init flag) === */


  filters: {
    searchText: "",
    location: "",     // Ort-Filter später (aktuell immer leer)
    kategorie: "",    // Single
    zeitraum: "all"   // all | today | weekend | soon
  },

  /**
   * Init: Event Listeners registrieren
   */
   /* === BEGIN BLOCK: FILTER INIT GUARD (single init) ===
  Zweck: Verhindert doppelte Initialisierung (Listener-Stacking) und macht init idempotent.
  Umfang: Ersetzt den Start von init(events) bis inkl. allEvents/filteredEvents Zuweisung.
  === */
  init(events) {
    if (this._isInit) {
      debugLog("FilterModule.init skipped (already initialized)");
      return;
    }

    this.allEvents = Array.isArray(events) ? events : [];
    this.filteredEvents = this.allEvents;
  /* === END BLOCK: FILTER INIT GUARD (single init) === */


    // UI-Elemente
    const searchInput = document.getElementById("search-filter");

    const timePill = document.getElementById("filter-time-pill");
    const timeValue = document.getElementById("filter-time-value");
    const timeSheet = document.getElementById("sheet-time");

    const catPill = document.getElementById("filter-category-pill");
    const catValue = document.getElementById("filter-category-value");
    const catSheet = document.getElementById("sheet-category");

    const resetPill = document.getElementById("filter-reset-pill");

  /* === BEGIN BLOCK: FILTER CONTRACT GUARD (hard-fail, no silent render) ===
Zweck: Schließt Fehlkonfigurationen aus (falscher DOM-Stand, falsche Klassen, fehlende Sheet-Bodies/Optionen).
Umfang: Ersetzt den kompletten Guard-Abschnitt direkt nach dem Einsammeln der UI-Elemente.
=== */
    const timeBody = timeSheet?.querySelector(".filter-sheet__body");
    const catBody  = catSheet?.querySelector(".filter-sheet__body");

    const timeOptions = timeSheet?.querySelectorAll(".filter-sheet-option[data-time]");
    const catOptions  = catSheet?.querySelectorAll(".filter-sheet-option[data-category]");

    const hasTimeClose = !!timeSheet?.querySelector("[data-close-sheet]");
    const hasCatClose  = !!catSheet?.querySelector("[data-close-sheet]");

    if (
      !searchInput ||
      !timePill || !timeValue || !timeSheet ||
      !catPill  || !catValue  || !catSheet  ||
      !resetPill ||
      !timeBody || !catBody ||
      !timeOptions || timeOptions.length === 0 ||
      !catOptions  || catOptions.length === 0 ||
      !hasTimeClose || !hasCatClose
    ) {
      console.error("❌ [FilterContract] init failed – missing/invalid UI:", {
        "search-filter": !!searchInput,

        "filter-time-pill": !!timePill,
        "filter-time-value": !!timeValue,
        "sheet-time": !!timeSheet,
        "sheet-time .filter-sheet__body": !!timeBody,
        "sheet-time .filter-sheet-option[data-time]": (timeOptions?.length ?? 0),
        "sheet-time [data-close-sheet]": hasTimeClose,

        "filter-category-pill": !!catPill,
        "filter-category-value": !!catValue,
        "sheet-category": !!catSheet,
        "sheet-category .filter-sheet__body": !!catBody,
        "sheet-category .filter-sheet-option[data-category]": (catOptions?.length ?? 0),
        "sheet-category [data-close-sheet]": hasCatClose,

        "filter-reset-pill": !!resetPill
      });

      return;
    }
/* === END BLOCK: FILTER CONTRACT GUARD (hard-fail, no silent render) === */

    // Defaults (konsistent)

    this.filters.searchText = "";
    this.filters.location = "";
    this.filters.kategorie = "";
    this.filters.zeitraum = "all";

    // Sheet helper
    const openSheet = (sheetEl) => {
      sheetEl.hidden = false;
      document.body.classList.add("is-sheet-open");
    };

    const closeSheet = (sheetEl) => {
      sheetEl.hidden = true;
      // Scroll lock nur entfernen, wenn beide Sheets zu sind
      if (timeSheet.hidden && catSheet.hidden) {
        document.body.classList.remove("is-sheet-open");
      }
    };

    // Search
    searchInput.addEventListener("input", (e) => {
      this.filters.searchText = (e.target.value || "").toLowerCase();
      this.applyFilters();
      this.updateFilterBarUI(timeValue, catValue, resetPill);
    });

    // Open sheets
    timePill.addEventListener("click", () => openSheet(timeSheet));
    catPill.addEventListener("click", () => openSheet(catSheet));

    // Close on overlay + close button (data-close-sheet)
    const wireSheetClose = (sheetEl) => {
      sheetEl.addEventListener("click", (e) => {
        const t = e.target;
        if (t && t.hasAttribute && t.hasAttribute("data-close-sheet")) {
          closeSheet(sheetEl);
        }
      });
    };
    wireSheetClose(timeSheet);
    wireSheetClose(catSheet);

    // ESC close
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      if (!timeSheet.hidden) closeSheet(timeSheet);
      if (!catSheet.hidden) closeSheet(catSheet);
    });

    // Zeit Auswahl
    timeSheet.querySelectorAll("[data-time]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const v = (btn.getAttribute("data-time") || "all").trim();
        this.filters.zeitraum = v;
        this.applyFilters();
        this.setActiveOption(timeSheet, btn);
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        closeSheet(timeSheet);
      });
    });

    // Kategorie Auswahl (Single)
    catSheet.querySelectorAll("[data-category]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const v = btn.getAttribute("data-category"); // '' = Alle
        this.filters.kategorie = (v ?? "").trim();
        this.applyFilters();
        this.setActiveOption(catSheet, btn);
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        closeSheet(catSheet);
      });
    });

    // Reset
    resetPill.addEventListener("click", () => {
      this.resetFilters();
      // UI reset
      searchInput.value = "";
      this.setActiveOption(timeSheet, timeSheet.querySelector('[data-time="all"]'));
      this.setActiveOption(catSheet, catSheet.querySelector('[data-category=""]'));
      this.updateFilterBarUI(timeValue, catValue, resetPill);
    });

    // Initial render
    this.applyFilters();
    this.setActiveOption(timeSheet, timeSheet.querySelector('[data-time="all"]'));
    this.setActiveOption(catSheet, catSheet.querySelector('[data-category=""]'));
    this.updateFilterBarUI(timeValue, catValue, resetPill);

        /* === BEGIN BLOCK: FILTER INIT FINALIZE (set init flag) ===
    Zweck: Markiert erfolgreiche Initialisierung, damit Auto-Bootstrap nicht erneut init() ausführt.
    Umfang: Ersetzt nur die letzten Zeilen von init() direkt vor dem return.
    === */
    this._isInit = true;
    debugLog("Filter module initialized (Top-App pills + sheets)");
  },
  /* === END BLOCK: FILTER INIT FINALIZE (set init flag) === */


  /**
   * Filter anwenden
   */
    applyFilters() {
    debugLog("Applying filters:", this.filters);

    const timeKey = (this.filters.zeitraum || "all").trim();
    const searchNeedle = (this.filters.searchText || "").trim().toLowerCase();
    const catNeedle = (this.filters.kategorie || "").trim();

    this.filteredEvents = (this.allEvents || []).filter((event) => {
      const title = (event?.eventName || event?.title || "").toLowerCase();
      const desc = (event?.beschreibung || "").toLowerCase();
      const loc = (event?.location || "").toLowerCase();

      // Textsuche
      if (searchNeedle) {
        const searchable = `${title} ${desc} ${loc}`;
        if (!searchable.includes(searchNeedle)) return false;
      }

      // Kategorie Filter (Single)
      if (catNeedle) {
        const evCat = (event?.kategorie || "").trim();
        if (evCat !== catNeedle) return false;
      }

      /* === BEGIN BLOCK: ZEITFILTER (uses events.js date helpers + robust weekend) ===
      Zweck: Zeitfilter nutzt gemeinsame Helper/Formats, vermeidet event.datum vs event.date Mix.
      Umfang: Zeitraum-Filter-Logik in applyFilters(), ohne Duplikate.
      === */
      if (timeKey !== "all") {
        // Erwartetes Feld: event.date (ISO YYYY-MM-DD). Fallback: event.datum.
        const iso = (event?.date || event?.datum || "").trim();
        const day = parseISODateLocal(iso);
        if (!day) return false;

        const today = startOfToday();
        const endToday = addDays(today, 1);

        switch (timeKey) {
          case "today": {
            if (day < today || day >= endToday) return false;
            break;
          }

          case "weekend": {
            const { start, end } = getNextWeekendRange(today);
            if (day < start || day > end) return false;
            break;
          }

          case "soon": {
            // Demnächst: nächste 14 Tage inkl. heute
            const soonEnd = addDays(today, 14);
            soonEnd.setHours(23, 59, 59, 999);
            if (day < today || day > soonEnd) return false;
            break;
          }

          default:
            break;
        }
      }
      /* === END BLOCK: ZEITFILTER (uses events.js date helpers + robust weekend) === */

      return true;
    });

    // UI aktualisieren
    this.updateUI();

    debugLog(`Filtered: ${this.filteredEvents.length} of ${(this.allEvents || []).length} events`);
  },


  /**
   * UI aktualisieren
   */
  updateUI() {
    // Counter aktualisieren
    const counter = document.getElementById("filter-counter");
    if (counter) {
      counter.textContent = `${this.filteredEvents.length} von ${this.allEvents.length} Events`;
    }

    // Module aktualisieren
    if (CONFIG.features.showCalendar) {
      CalendarModule.refresh(this.filteredEvents);
    }

    if (CONFIG.features.showEventCards) {
      EventCards.refresh(this.filteredEvents);
    }
  },

  /* === BEGIN BLOCK: FILTER UI HELPERS (active state + pill labels) ===
  Zweck: UI-State sauber halten: aktive Option markieren, Pill-Labels updaten, Reset nur bei aktiven Filtern zeigen.
  Umfang: setActiveOption() + updateFilterBarUI() als Methoden.
  === */
  setActiveOption(sheetEl, activeBtn) {
    if (!sheetEl) return;
    sheetEl.querySelectorAll(".filter-sheet-option").forEach((b) => b.classList.remove("is-active"));
    if (activeBtn) activeBtn.classList.add("is-active");
  },


  updateFilterBarUI(timeValueEl, catValueEl, resetEl) {
    const timeMap = {
      all: "Alle",
      today: "Heute",
      weekend: "Wochenende",
      soon: "Demnächst"
    };

    const timeKey = this.filters.zeitraum || "all";
    const cat = (this.filters.kategorie || "").trim();

    if (timeValueEl) timeValueEl.textContent = timeMap[timeKey] || "Alle";
    if (catValueEl) catValueEl.textContent = cat ? cat : "Alle";

    const hasActive =
      (this.filters.searchText && this.filters.searchText.trim().length > 0) ||
      timeKey !== "all" ||
      cat.length > 0;

    if (resetEl) resetEl.hidden = !hasActive;
  },
  /* === END BLOCK: FILTER UI HELPERS (active state + pill labels) === */

  /**
   * Extern: Search Text setzen (optional nutzbar)
   */
  setSearchText(text) {
    const searchInput = document.getElementById("search-filter");
    const normalized = (text || "").trim();

    this.filters.searchText = normalized.toLowerCase();

    if (searchInput) {
      searchInput.value = normalized; // ersetzt komplett
    }

    this.applyFilters();
  },

  /**
   * Filter zurücksetzen
   */
  resetFilters() {
    this.filters = {
      searchText: "",
      location: "",
      kategorie: "",
      zeitraum: "all"
    };

    this.filteredEvents = this.allEvents;
    this.updateUI();

    debugLog("Filters reset");
  },

  /**
   * Events neu laden (z.B. nach Airtable-Update)
   */
  refresh(events) {
    this.allEvents = Array.isArray(events) ? events : [];
    this.applyFilters();
  }
};

/* === BEGIN BLOCK: FILTER AUTO-BOOTSTRAP (removed - main.js is source of truth) ===
Zweck: Entfernt doppeltes Initialisieren (Race-Conditions vermeiden).
Umfang: Kein Auto-Init mehr in filter.js. Initialisierung erfolgt ausschließlich über main.js.
=== */
/* === BEGIN BLOCK: FILTER GLOBAL EXPORT (explicit) ===
Zweck: FilterModule muss global verfügbar sein (für main.js + Console + Debug).
Umfang: Setzt window.FilterModule explizit und loggt den Status.
=== */
window.FilterModule = FilterModule;
debugLog("Filter module loaded (global export OK; main.js controls initialization)", {
  hasFilterModule: typeof window.FilterModule !== "undefined",
  isInit: window.FilterModule?._isInit === true
});
/* === END BLOCK: FILTER GLOBAL EXPORT (explicit) === */


