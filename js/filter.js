/* === BEGIN BLOCK: FILTER.JS (Top-App Pills + Sheets, Single Category) ===
Zweck: Zentrale Filter-Logik für Suche + strukturierte Filter-Pills (Zeit + Kategorie als Single).
Umfang: Gesamte Datei js/filter.js vollständig (konsolidiert, ohne Legacy-Dropdowns/Chip-Teppich).
Hinweis: Erwartet HTML-IDs: search-filter, filter-time-pill, filter-time-value, sheet-time,
         filter-category-pill, filter-category-value, sheet-category, filter-reset-pill.
=== */

/**
 * FILTER.JS – Such- und Filter-Modul (konsolidiert)
 *
 * Unterstützt:
 * - Freitextsuche (#search-filter)
 * - Zeitfilter via Bottom-Sheet (all/today/weekend/soon)
 * - Kategoriefilter (Single) via Bottom-Sheet
 * - Reset nur bei aktiven Filtern
 */

const FilterModule = {
  allEvents: [],
  filteredEvents: [],

  filters: {
    searchText: "",
    location: "",     // Ort-Filter später (aktuell immer leer)
    kategorie: "",    // Single
    zeitraum: "all"   // all | today | weekend | soon
  },

  /**
   * Init: Event Listeners registrieren
   */
  init(events) {
    this.allEvents = Array.isArray(events) ? events : [];
    this.filteredEvents = this.allEvents;

    // UI-Elemente
    const searchInput = document.getElementById("search-filter");

    const timePill = document.getElementById("filter-time-pill");
    const timeValue = document.getElementById("filter-time-value");
    const timeSheet = document.getElementById("sheet-time");

    const catPill = document.getElementById("filter-category-pill");
    const catValue = document.getElementById("filter-category-value");
    const catSheet = document.getElementById("sheet-category");

    const resetPill = document.getElementById("filter-reset-pill");

    // Guard
    if (
  !searchInput ||
  !timePill || !timeValue || !timeSheet ||
  !catPill || !catValue || !catSheet ||
  !resetPill
) {
  alert("Filter init failed: missing UI elements. Check console for details.");

  console.error("Filter init failed – missing UI elements:", {
    "search-filter": !!searchInput,
    "filter-time-pill": !!timePill,
    "filter-time-value": !!timeValue,
    "sheet-time": !!timeSheet,
    "filter-category-pill": !!catPill,
    "filter-category-value": !!catValue,
    "sheet-category": !!catSheet,
    "filter-reset-pill": !!resetPill
  });

  return;
}


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

    debugLog("Filter module initialized (Top-App pills + sheets)");
  },

  /**
   * Filter anwenden
   */
  applyFilters() {
    debugLog("Applying filters:", this.filters);

    this.filteredEvents = this.allEvents.filter((event) => {
      // Textsuche
      if (this.filters.searchText) {
        const searchableText = `${event.eventName || ""} ${event.beschreibung || ""} ${event.location || ""}`.toLowerCase();
        if (!searchableText.includes(this.filters.searchText)) {
          return false;
        }
      }

      // Kategorie Filter (Single)
      if (this.filters.kategorie && event.kategorie !== this.filters.kategorie) {
        return false;
      }

      /* === BEGIN BLOCK: ZEITFILTER MAPPING (all/today/weekend/soon) ===
      Zweck: Neue Zeitwerte aus UI-Sheet sauber filtern.
      Umfang: Zeitraum-Filter-Logik in applyFilters().
      === */
      if ((this.filters.zeitraum || "all") !== "all") {
        const eventDate = new Date(event.datum);
        const now = new Date();
        now.setHours(0, 0, 0, 0);

        // Ungültige Daten raus
        if (Number.isNaN(eventDate.getTime())) return false;

        const endOfToday = new Date(now);
        endOfToday.setDate(endOfToday.getDate() + 1);

        switch (this.filters.zeitraum) {
          case "today": {
            // heute: [now, morgen 00:00)
            if (eventDate < now || eventDate >= endOfToday) return false;
            break;
          }

          case "weekend": {
            // nächstes Wochenende: Fr 00:00 bis Mo 00:00
            const day = now.getDay(); // 0=So ... 6=Sa
            const daysUntilFriday = (5 - day + 7) % 7;

            const friday = new Date(now);
            friday.setDate(now.getDate() + daysUntilFriday);

            const monday = new Date(friday);
            monday.setDate(friday.getDate() + 3);

            if (eventDate < friday || eventDate >= monday) return false;
            break;
          }

          case "soon": {
            // demnächst: nächste 14 Tage (inkl. heute)
            const soonEnd = new Date(now);
            soonEnd.setDate(now.getDate() + 14);
            if (eventDate < now || eventDate >= soonEnd) return false;
            break;
          }
        }
      }
      /* === END BLOCK: ZEITFILTER MAPPING (all/today/weekend/soon) === */

      return true;
    });

    // UI aktualisieren
    this.updateUI();

    debugLog(`Filtered: ${this.filteredEvents.length} of ${this.allEvents.length} events`);
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
    sheetEl.querySelectorAll(".filter-option").forEach((b) => b.classList.remove("is-active"));
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

debugLog("Filter module loaded");

/* === END BLOCK: FILTER.JS (Top-App Pills + Sheets, Single Category) === */
