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


/* === BEGIN BLOCK: FILTER MODULE STATE + DATE PICKER HELPERS_V2 | Zweck: erweitert den Filterzustand um einen integrierten Monatskalender und kapselt alle Datumshilfen zentral; Umfang: ersetzt die ersten Properties + Date-Helper innerhalb des FilterModules === */
const FilterModule = {
  _isInit: false,
  _datePickerMonth: "",
  allEvents: [],
  filteredEvents: [],

  filters: {
    searchText: "",
    location: "",     // Ort-Filter später (aktuell immer leer)
    kategorie: "",    // Single
    zeitraum: "all",  // all | today | week | weekend | nextweek | later
    selectedDate: ""  // exact day YYYY-MM-DD
  },

  getTodayIso() {
    const today = new Date();
    const year = today.getFullYear();
    const month = String(today.getMonth() + 1).padStart(2, "0");
    const day = String(today.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
  },

  toLocalDay(value) {
    const match = String(value || "").trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    if (!year || !month || !day) return null;

    const date = new Date(year, month - 1, day);
    date.setHours(0, 0, 0, 0);
    return date;
  },

  toIsoLocal(value) {
    const day = value instanceof Date ? new Date(value) : this.toLocalDay(value);
    if (!day) return "";
    day.setHours(0, 0, 0, 0);
    const year = day.getFullYear();
    const month = String(day.getMonth() + 1).padStart(2, "0");
    const date = String(day.getDate()).padStart(2, "0");
    return `${year}-${month}-${date}`;
  },

  getMonthKey(value) {
    const day = value instanceof Date ? new Date(value) : this.toLocalDay(value);
    if (!day) return "";
    return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, "0")}`;
  },

  parseMonthKey(value) {
    const match = String(value || "").trim().match(/^(\d{4})-(\d{2})$/);
    if (!match) return null;
    const year = Number(match[1]);
    const month = Number(match[2]);
    if (!year || !month) return null;
    const date = new Date(year, month - 1, 1);
    date.setHours(0, 0, 0, 0);
    return date;
  },

  clampMonthKey(value) {
    const candidate = this.parseMonthKey(value);
    const min = this.parseMonthKey(this.getMonthKey(this.getTodayIso()));
    if (!candidate || !min) return this.getMonthKey(this.getTodayIso());
    return candidate < min ? this.getMonthKey(min) : this.getMonthKey(candidate);
  },

  shiftMonthKey(value, delta) {
    const base = this.parseMonthKey(value) || this.parseMonthKey(this.getMonthKey(this.getTodayIso()));
    if (!base) return this.getMonthKey(this.getTodayIso());
    base.setMonth(base.getMonth() + delta);
    return this.clampMonthKey(this.getMonthKey(base));
  },

  getActiveDatePickerMonth() {
    if (this._datePickerMonth) return this.clampMonthKey(this._datePickerMonth);
    if (this.filters.selectedDate) return this.clampMonthKey(this.getMonthKey(this.filters.selectedDate));
    return this.getMonthKey(this.getTodayIso());
  },

  setActiveDatePickerMonth(value) {
    this._datePickerMonth = this.clampMonthKey(value);
  },

  addDaysLocal(base, days) {
    const next = new Date(base);
    next.setDate(next.getDate() + days);
    next.setHours(0, 0, 0, 0);
    return next;
  },

  endOfDay(date) {
    const end = new Date(date);
    end.setHours(23, 59, 59, 999);
    return end;
  },

  endOfWeekLocal(fromDate) {
    const end = new Date(fromDate);
    const weekday = end.getDay();
    const delta = (7 - weekday) % 7;
    end.setDate(end.getDate() + delta);
    end.setHours(23, 59, 59, 999);
    return end;
  },

  getNextWeekendRange(base) {
    const day = new Date(base);
    day.setHours(0, 0, 0, 0);

    const weekday = day.getDay();
    let friday;

    if (weekday === 5) friday = this.addDaysLocal(day, 0);
    else if (weekday === 6) friday = this.addDaysLocal(day, -1);
    else if (weekday === 0) friday = this.addDaysLocal(day, -2);
    else friday = this.addDaysLocal(day, (5 - weekday + 7) % 7);

    const start = new Date(friday);
    start.setHours(0, 0, 0, 0);

    const end = this.addDaysLocal(friday, 2);
    end.setHours(23, 59, 59, 999);

    return { start, end };
  },

  getEventEffectiveDay(event) {
    const startDay = this.toLocalDay(event?.date || event?.datum || "");
    if (!startDay) return null;

    const rawEnd = event?.endDate || event?.endDatum || "";
    const endBase = rawEnd ? this.toLocalDay(rawEnd) : new Date(startDay);
    if (!endBase) return null;

    const endDay = this.endOfDay(endBase);
    const today = this.toLocalDay(this.getTodayIso());

    if (today && today >= startDay && today <= endDay) {
      return new Date(today);
    }

    return new Date(startDay);
  },

  getBucketForEvent(event) {
    const effectiveDay = this.getEventEffectiveDay(event);
    if (!effectiveDay) return "later";

    effectiveDay.setHours(0, 0, 0, 0);

    const today = this.toLocalDay(this.getTodayIso());
    if (!today) return "later";

    const weekday = today.getDay();
    const hasThisWeek = weekday >= 1 && weekday <= 4;
    const thisWeekStart = this.addDaysLocal(today, 1);
    let thisWeekEnd = null;

    if (hasThisWeek) {
      thisWeekEnd = this.endOfDay(this.addDaysLocal(today, 4 - weekday));
    }

    const weekend = this.getNextWeekendRange(today);
    const endThisWeek = this.endOfWeekLocal(today);
    const nextWeekStart = this.addDaysLocal(endThisWeek, 1);
    const nextWeekEnd = this.endOfDay(this.addDaysLocal(nextWeekStart, 6));

    if (effectiveDay.getTime() === today.getTime()) return "today";
    if (hasThisWeek && thisWeekEnd && effectiveDay >= thisWeekStart && effectiveDay <= thisWeekEnd) return "week";
    if (effectiveDay >= weekend.start && effectiveDay <= weekend.end) return "weekend";
    if (effectiveDay >= nextWeekStart && effectiveDay <= nextWeekEnd) return "nextweek";
    return "later";
  },

  eventMatchesSelectedDate(event, isoDate) {
    const selectedDay = this.toLocalDay(isoDate);
    const startDay = this.toLocalDay(event?.date || event?.datum || "");
    if (!selectedDay || !startDay) return false;

    const rawEnd = event?.endDate || event?.endDatum || "";
    const endBase = rawEnd ? this.toLocalDay(rawEnd) : new Date(startDay);
    if (!endBase) return false;

    return selectedDay >= startDay && selectedDay <= this.endOfDay(endBase);
  },

  formatSelectedDateLabel(isoDate, variant = "long") {
    const day = this.toLocalDay(isoDate);
    if (!day) return "";

    const formats = {
      short: { day: "2-digit", month: "long" },
      long: { weekday: "short", day: "2-digit", month: "long", year: "numeric" },
      aria: { weekday: "long", day: "2-digit", month: "long", year: "numeric" },
      month: { month: "long", year: "numeric" }
    };

    return new Intl.DateTimeFormat("de-DE", formats[variant] || formats.long).format(day).replace(/\s+/g, " ").trim();
  },

  getDateModuleParts(module) {
    if (!module) return {};
    return {
      trigger: module.querySelector("[data-date-trigger]"),
      text: module.querySelector("[data-date-trigger-text]"),
      panel: module.querySelector("[data-date-panel]"),
      monthLabel: module.querySelector("[data-date-month-label]"),
      grid: module.querySelector("[data-date-grid]"),
      prev: module.querySelector('[data-date-nav="prev"]'),
      next: module.querySelector('[data-date-nav="next"]'),
      today: module.querySelector("[data-date-today]")
    };
  },

  getDateScrollHost(module) {
    if (!module) return null;
    return module.closest(".filter-sheet__body") || module.closest(".filter-popover__panel") || null;
  },

  revealDateModule(module, behavior = "smooth") {
    if (!module) return;

    const scrollHost = this.getDateScrollHost(module);
    const parts = this.getDateModuleParts(module);
    const anchor = parts.trigger || module;
    if (!scrollHost || !anchor) return;

    window.requestAnimationFrame(() => {
      const hostRect = scrollHost.getBoundingClientRect();
      const anchorRect = anchor.getBoundingClientRect();
      const topGap = module.closest(".filter-sheet__body") ? 12 : 10;
      const nextTop = scrollHost.scrollTop + (anchorRect.top - hostRect.top) - topGap;

      scrollHost.scrollTo({
        top: Math.max(0, nextTop),
        behavior
      });
    });
  },

  repositionOpenDesktopPopover() {
    if (typeof this._repositionDesktopPopover === "function") {
      this._repositionDesktopPopover();
    }
  },

  closeDatePickers() {
    const ui = this._ui || {};
    (ui.dateModules || []).forEach((module) => {
      const parts = this.getDateModuleParts(module);
      module.classList.remove("is-open");
      if (parts.trigger) parts.trigger.setAttribute("aria-expanded", "false");
      if (parts.panel) parts.panel.hidden = true;
    });
  },

  openDatePicker(module) {
    if (!module) return;
    this.closeDatePickers();

    const parts = this.getDateModuleParts(module);
    this.setActiveDatePickerMonth(this.filters.selectedDate || this.getTodayIso());

    module.classList.add("is-open");
    if (parts.trigger) parts.trigger.setAttribute("aria-expanded", "true");
    if (parts.panel) parts.panel.hidden = false;

    this.renderDateCalendars();
    this.repositionOpenDesktopPopover();
    this.revealDateModule(module);
  },

  toggleDatePicker(module) {
    if (!module) return;
    if (module.classList.contains("is-open")) {
      this.closeDatePickers();
      this.repositionOpenDesktopPopover();
      return;
    }
    this.openDatePicker(module);
  },

  renderDateCalendars() {
    const ui = this._ui || {};
    const monthKey = this.getActiveDatePickerMonth();
    const monthStart = this.parseMonthKey(monthKey);
    if (!monthStart) return;

    const todayIso = this.getTodayIso();
    const selectedIso = String(this.filters.selectedDate || "").trim();
    const firstGridDay = new Date(monthStart);
    const weekdayOffset = (firstGridDay.getDay() + 6) % 7;
    firstGridDay.setDate(firstGridDay.getDate() - weekdayOffset);
    firstGridDay.setHours(0, 0, 0, 0);

    (ui.dateModules || []).forEach((module) => {
      const parts = this.getDateModuleParts(module);
      if (!parts.grid || !parts.monthLabel) return;

      parts.monthLabel.textContent = this.formatSelectedDateLabel(this.toIsoLocal(monthStart), "month");
      if (parts.prev) parts.prev.disabled = this.shiftMonthKey(monthKey, -1) === monthKey;

      parts.grid.innerHTML = "";

      for (let i = 0; i < 42; i += 1) {
        const date = this.addDaysLocal(firstGridDay, i);
        const iso = this.toIsoLocal(date);
        const dayButton = document.createElement("button");
        dayButton.type = "button";
        dayButton.className = "filter-date-day";
        dayButton.textContent = String(date.getDate());
        dayButton.dataset.dateValue = iso;
        dayButton.setAttribute("aria-label", this.formatSelectedDateLabel(iso, "aria"));

        if (date.getMonth() !== monthStart.getMonth()) dayButton.classList.add("is-outside");
        if (iso === todayIso) dayButton.classList.add("is-today");
        if (iso === selectedIso) dayButton.classList.add("is-selected");
        if (iso < todayIso) {
          dayButton.disabled = true;
          dayButton.classList.add("is-disabled");
        }

        parts.grid.appendChild(dayButton);
      }
    });
  },

  syncDateFilterUI() {
    const ui = this._ui || {};
    const selectedDate = (this.filters.selectedDate || "").trim();
    const hasSelectedDate = selectedDate.length > 0;
    const triggerLabel = hasSelectedDate
      ? (this.formatSelectedDateLabel(selectedDate, "long") || selectedDate)
      : "Datum auswählen";

    (ui.dateModules || []).forEach((module) => {
      const parts = this.getDateModuleParts(module);
      module.classList.toggle("is-active", hasSelectedDate);
      if (parts.text) parts.text.textContent = triggerLabel;
      if (parts.trigger) {
        const label = hasSelectedDate ? `Gewähltes Datum: ${triggerLabel}` : "Bestimmtes Datum auswählen";
        parts.trigger.setAttribute("aria-label", label);
        parts.trigger.setAttribute("title", hasSelectedDate ? triggerLabel : "Datum auswählen");
        parts.trigger.setAttribute("aria-expanded", module.classList.contains("is-open") ? "true" : "false");
      }
      if (parts.panel) parts.panel.hidden = !module.classList.contains("is-open");
    });

    this.renderDateCalendars();
  },
/* === END BLOCK: FILTER MODULE STATE + DATE PICKER HELPERS_V3 === */

  /**
   * Init: Event Listeners registrieren
   */
  /* === BEGIN BLOCK: FILTER INIT GUARD + PROOF LOGS (single init) ===
Zweck: Harter Beweis, ob init() wirklich läuft und wo es ggf. abbricht.
Umfang: Ersetzt den Start von init(events) bis inkl. allEvents/filteredEvents Zuweisung.
=== */
  init(events) {
    console.log("[PROOF][FilterModule] init() entered", {
      alreadyInit: this._isInit === true,
      showFilters: CONFIG?.features?.showFilters,
      hasDOM_search: !!document.getElementById("search-filter"),
      hasDOM_timePill: !!document.getElementById("filter-time-pill"),
      hasDOM_catPill: !!document.getElementById("filter-category-pill")
    });

    if (this._isInit) {
      console.log("[PROOF][FilterModule] init() SKIP (_isInit already true)");
      debugLog("FilterModule.init skipped (already initialized)");
      return;
    }

    this.allEvents = Array.isArray(events) ? events : [];
    this.filteredEvents = this.allEvents;

    console.log("[PROOF][FilterModule] init() after event assignment", {
      allEvents: this.allEvents.length
    });
  /* === END BLOCK: FILTER INIT GUARD + PROOF LOGS (single init) === */



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
Zweck: Schließt Fehlkonfigurationen aus, aber ohne Selector-Mismatch (alt/neu) zu killen.
Umfang: Guard direkt nach dem Einsammeln der UI-Elemente.
=== */
    const timeBody = timeSheet?.querySelector(".filter-sheet__body");
    const catBody  = catSheet?.querySelector(".filter-sheet__body");

    // Optionen: akzeptiere beide Klassennamen (alt/neu)
    const timeOptions = timeSheet?.querySelectorAll(
      '.filter-option[data-time], .filter-sheet-option[data-time], .filter-sheet-option[data-time], [data-time].filter-option, [data-time].filter-sheet-option'
    );
    const catOptions = catSheet?.querySelectorAll(
      '.filter-option[data-category], .filter-sheet-option[data-category], .filter-sheet-option[data-category], [data-category].filter-option, [data-category].filter-sheet-option'
    );

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
        "sheet-time options(data-time)": (timeOptions?.length ?? 0),
        "sheet-time [data-close-sheet]": hasTimeClose,

        "filter-category-pill": !!catPill,
        "filter-category-value": !!catValue,
        "sheet-category": !!catSheet,
        "sheet-category .filter-sheet__body": !!catBody,
        "sheet-category options(data-category)": (catOptions?.length ?? 0),
        "sheet-category [data-close-sheet]": hasCatClose,

        "filter-reset-pill": !!resetPill
      });

      return;
    }
  /* === END BLOCK: FILTER CONTRACT GUARD (hard-fail, no silent render) === */



    // BEGIN: FILTER_UI_REFS_STORE
    // Zweck: UI-Referenzen persistent im Modul speichern, damit Facet-Counts/Disabled-States
    //        und der Mobile-3-Spalten-State stabil gegen die richtige Row laufen.
    // Umfang: Ersetzt nur den UI-Refs-Store.
    // END: FILTER_UI_REFS_STORE
    this._ui = {
      searchRow: document.querySelector(".desktop-hero__search-row"),
      searchInput,
      timePill, timeValue, timeSheet,
      catPill,  catValue,  catSheet,
      resetPill,
      dateModules: Array.from(document.querySelectorAll("[data-date-module]"))
    };

    // Defaults (konsistent)
    this.filters.searchText = "";
    this.filters.location = "";
    this.filters.kategorie = "";
    this.filters.zeitraum = "all";
    this.filters.selectedDate = "";
    this._datePickerMonth = this.getMonthKey(this.getTodayIso());

    // Sheet helper
    const openSheet = (sheetEl) => {
      if (!sheetEl) return;
      sheetEl.hidden = false;
      document.body.classList.add("is-sheet-open");
    };

    const closeSheet = (sheetEl) => {
      if (!sheetEl) return;
      sheetEl.hidden = true;
      this.closeDatePickers();
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

    // Exaktes Datum (integrierter Kalender in Sheet + Popover)
    (this._ui.dateModules || []).forEach((module) => {
      const parts = this.getDateModuleParts(module);

      parts.trigger?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.toggleDatePicker(module);
      });

      parts.prev?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.setActiveDatePickerMonth(this.shiftMonthKey(this.getActiveDatePickerMonth(), -1));
        this.renderDateCalendars();
        this.repositionOpenDesktopPopover();
        this.revealDateModule(module, "auto");
      });

      parts.next?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        this.setActiveDatePickerMonth(this.shiftMonthKey(this.getActiveDatePickerMonth(), 1));
        this.renderDateCalendars();
        this.repositionOpenDesktopPopover();
        this.revealDateModule(module, "auto");
      });

      parts.today?.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        const todayIso = this.getTodayIso();
        this.filters.selectedDate = todayIso;
        this.filters.zeitraum = "all";
        this.setActiveDatePickerMonth(todayIso);
        this.closeDatePickers();
        this.syncDateFilterUI();
        this.applyFilters();
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        if (isDesktop()) closeAllPopovers();
        else closeSheet(timeSheet);
      });

      parts.grid?.addEventListener("click", (event) => {
        const dayButton = event.target.closest("[data-date-value]");
        if (!dayButton || dayButton.disabled) return;
        event.preventDefault();
        event.stopPropagation();

        const isoDate = (dayButton.getAttribute("data-date-value") || "").trim();
        if (!isoDate) return;

        this.filters.selectedDate = isoDate;
        this.filters.zeitraum = "all";
        this.setActiveDatePickerMonth(isoDate);
        this.closeDatePickers();
        this.syncDateFilterUI();
        this.applyFilters();
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        if (isDesktop()) closeAllPopovers();
        else closeSheet(timeSheet);
      });
    });

    /* === BEGIN BLOCK: FILTER_RESET_PILL_HANDLER_V2 | Zweck: koppelt das globale X an einen reinen Facetten-Reset, während die Suche als lokales Search-Feld-Verhalten bestehen bleibt; Umfang: ersetzt ausschließlich den Click-Handler des globalen Reset-Pills === */
    resetPill.addEventListener("click", () => {
      this.resetFacetFilters();
    });
    /* === END BLOCK: FILTER_RESET_PILL_HANDLER_V2 === */

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

   // Zeit Auswahl (Sheet + Popover)
[...timeSheet.querySelectorAll("[data-time]"),
 ...document.querySelectorAll("#popover-time [data-time]")]
.forEach((btn) => {
      btn.addEventListener("click", () => {
        const v = (btn.getAttribute("data-time") || "all").trim();
        this.filters.zeitraum = v;
        this.filters.selectedDate = "";
        this.closeDatePickers();
        this.syncDateFilterUI();
        this.applyFilters();
        this.setActiveOption(timeSheet, btn);
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        if (isDesktop()) closeAllPopovers();
        else closeSheet(timeSheet);
      });
    });

   // Kategorie Auswahl (Sheet + Popover)
[...catSheet.querySelectorAll("[data-category]"),
 ...document.querySelectorAll("#popover-category [data-category]")]
.forEach((btn) => {
      btn.addEventListener("click", () => {
        const v = btn.getAttribute("data-category"); // '' = Alle
        this.filters.kategorie = (v ?? "").trim();
        this.applyFilters();
        this.setActiveOption(catSheet, btn);
        this.updateFilterBarUI(timeValue, catValue, resetPill);
        if (isDesktop()) closeAllPopovers();
        else closeSheet(catSheet);
      });
    });

});

    // Initial render
    this.syncDateFilterUI();
    this.applyFilters();
    this.setActiveOption(timeSheet, timeSheet.querySelector('[data-time="all"]'));
    this.setActiveOption(catSheet, catSheet.querySelector('[data-category=""]'));

         /* === BEGIN BLOCK: FILTER INIT FINALIZE + PROOF LOGS ===
Zweck: Harter Beweis, dass init() wirklich bis zum Ende kommt und _isInit setzt.
Umfang: Ersetzt nur die letzten Zeilen von init() direkt vor dem return.
=== */
    this._isInit = true;
    console.log("[PROOF][FilterModule] init() COMPLETED -> _isInit=true");
    debugLog("Filter module initialized (Top-App pills + sheets)");
  },
  /* === END BLOCK: FILTER INIT FINALIZE + PROOF LOGS === */


/**
   * Filter anwenden
   */
  applyFilters() {
    debugLog("Applying filters:", this.filters);

    const timeKey = (this.filters.zeitraum || "all").trim();
    const selectedDate = (this.filters.selectedDate || "").trim();
    const searchNeedle = (this.filters.searchText || "").trim().toLowerCase();
    const catNeedle = (this.filters.kategorie || "").trim();

    this.filteredEvents = (this.allEvents || []).filter((event) => {
      const title = (event?.eventName || event?.title || "").toLowerCase();
      const desc = (event?.beschreibung || "").toLowerCase();
      const loc = (event?.location || "").toLowerCase();

      if (searchNeedle) {
        const searchable = `${title} ${desc} ${loc}`;
        if (!searchable.includes(searchNeedle)) return false;
      }

      if (catNeedle) {
        const evCatRaw = (event?.kategorie || "").trim();
        const filterRaw = catNeedle;
        const evCat = this.normalizeCategory(evCatRaw);
        const filterCat = this.normalizeCategory(filterRaw) || filterRaw;
        if (evCat !== filterCat && evCatRaw !== filterRaw) return false;
      }

      if (selectedDate) {
        if (!this.eventMatchesSelectedDate(event, selectedDate)) return false;
      } else if (timeKey !== "all") {
        if (this.getBucketForEvent(event) !== timeKey) return false;
      }

      return true;
    });

    this.updateUI();
    this.updateFilterBarUI(
      document.getElementById("filter-time-value"),
      document.getElementById("filter-category-value"),
      document.getElementById("filter-reset-pill")
    );

    this.updateFacetOptionStates();
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

  /* === BEGIN BLOCK: FILTER UI HELPERS + FACETS (canonical + counts + disabled) ===
  Zweck:
  - UI-State sauber halten: aktive Option markieren, Pill-Labels updaten, Reset nur bei aktiven Filtern zeigen.
  - Canonical-Kategorien zentral definieren (Single Source of Truth).
  - Facet-Counts anzeigen + 0-Treffer-Optionen disabled anzeigen (Zeit + Kategorie).
  - Kategorien nach Count sortieren (Stufe B), ohne „Alle“ zu verschieben.
  Umfang: Ersetzt setActiveOption() + updateFilterBarUI() und ergänzt normalizeCategory() + updateFacetOptionStates().
  === */

  canonicalCategories: [
    "Märkte & Feste",
    "Kultur & Kunst",
    "Musik & Bühne",
    "Kinder & Familie",
    "Sport & Bewegung",
    "Natur & Draußen",
    "Innenstadt & Leben",
  ],

  normalizeCategory(raw) {
    const s = String(raw || "").trim();
    if (!s) return "";
    const v = s.toLowerCase();

    // Märkte & Feste
    if (
      v.includes("markt") ||
      v.includes("festival") ||
      v.includes("parade") ||
      v.includes("stadtfest") ||
      v.includes("krammarkt") ||
      v.includes("karneval") ||
      v.includes("umzug")
    ) return "Märkte & Feste";

    // Kinder & Familie
    if (v.includes("kinder") || v.includes("familie")) return "Kinder & Familie";

    // Musik & Bühne
    if (
      v.includes("musik") ||
      v.includes("konzert") ||
      v.includes("theater") ||
      v.includes("bühne") ||
      v.includes("kabarett") ||
      v.includes("comedy")
    ) return "Musik & Bühne";

    // Kultur & Kunst
    if (
      v.includes("kultur") ||
      v.includes("kunst") ||
      v.includes("ausstellung") ||
      v.includes("führung") ||
      v.includes("vortrag") ||
      v.includes("film")
    ) return "Kultur & Kunst";

    // Sport & Bewegung
    if (v.includes("sport") || v.includes("bewegung") || v.includes("lauf") || v.includes("wandern")) {
      return "Sport & Bewegung";
    }

    // Natur & Draußen
    if (v.includes("outdoor") || v.includes("draußen") || v.includes("natur") || v.includes("freizeit")) {
      return "Natur & Draußen";
    }

    // Variante A: Highlights/Wirtschaft werden in bestehende kanonische Kategorien gemappt
    // Highlights: bewusst KEINE eigene Kategorie -> läuft unter "Innenstadt & Leben"
    if (v.includes("highlight") || v.includes("highlights") || v.includes("tipp") || v.includes("top")) {
      return "Innenstadt & Leben";
    }

    // Wirtschaft: bewusst KEINE eigene Kategorie -> läuft unter "Innenstadt & Leben"
    if (v.includes("wirtschaft") || v.includes("business") || v.includes("handel") || v.includes("unternehmen")) {
      return "Innenstadt & Leben";
    }

    // Innenstadt & Leben
    if (v.includes("innenstadt") || v.includes("urban") || v.includes("city")) {
      return "Innenstadt & Leben";
    }

    // Fallback: keine Canonicalisierung (damit Facets nicht "undefined" bekommen)
    return "";
  },

  /* === BEGIN BLOCK: FILTER_FACET_SYNC_HELPERS_V2 | Zweck: synchronisiert aktive/disabled States zwischen Mobile-Sheet und Desktop-Popover, damit Desktop keine "hängenden" grünen Optionen mehr behält und 0-Treffer-Facets nicht klickbar bleiben; Umfang: ersetzt setActiveOption() und erweitert die Facet-Helper-Infrastruktur === */
  getFacetButtons(group) {
    const ui = this._ui || {};
    const selectors = {
      time: {
        attr: "data-time",
        sheetRoot: ui.timeSheet,
        popoverSelector: "#popover-time [data-time]"
      },
      category: {
        attr: "data-category",
        sheetRoot: ui.catSheet,
        popoverSelector: "#popover-category [data-category]"
      }
    };

    const cfg = selectors[group];
    if (!cfg) return [];

    const result = [];
    const seen = new Set();
    const pushUnique = (btn) => {
      if (!btn || seen.has(btn)) return;
      seen.add(btn);
      result.push(btn);
    };

    if (cfg.sheetRoot) {
      cfg.sheetRoot.querySelectorAll(`[${cfg.attr}]`).forEach(pushUnique);
    }

    document.querySelectorAll(cfg.popoverSelector).forEach(pushUnique);
    return result;
  },

  getFacetGroup(sheetEl, activeBtn) {
    if (activeBtn?.hasAttribute?.("data-time")) return "time";
    if (activeBtn?.hasAttribute?.("data-category")) return "category";
    if (sheetEl?.id === "sheet-time") return "time";
    if (sheetEl?.id === "sheet-category") return "category";
    return "";
  },

  getFacetButtonValue(btn, group) {
    if (!btn) return "";
    if (group === "time") return (btn.getAttribute("data-time") || "all").trim();
    if (group === "category") return (btn.getAttribute("data-category") ?? "").trim();
    return "";
  },

  setFacetButtonState(btn, { enabled, count, withCount = false }) {
    if (!btn) return;

    if (!btn.hasAttribute("data-label")) {
      const raw = (btn.textContent || "").trim();
      btn.setAttribute("data-label", raw.replace(/\s*\(\d+\)\s*$/, ""));
    }

    const baseLabel = btn.getAttribute("data-label") || (btn.textContent || "").trim();
    btn.textContent = withCount ? `${baseLabel} (${count})` : baseLabel;

    btn.disabled = !enabled;
    btn.setAttribute("aria-disabled", (!enabled).toString());
    btn.classList.toggle("is-disabled", !enabled);

    if (!enabled) btn.classList.remove("is-active");
  },

  setActiveOption(sheetEl, activeBtn) {
    const group = this.getFacetGroup(sheetEl, activeBtn);
    if (!group) return;

    const allButtons = this.getFacetButtons(group);
    allButtons.forEach((btn) => btn.classList.remove("is-active"));

    if (!activeBtn || activeBtn.disabled) return;

    const activeValue = this.getFacetButtonValue(activeBtn, group);
    allButtons
      .filter((btn) => !btn.disabled && this.getFacetButtonValue(btn, group) === activeValue)
      .forEach((btn) => btn.classList.add("is-active"));

    if (typeof activeBtn.blur === "function") {
      activeBtn.blur();
    }
  },
  /* === END BLOCK: FILTER_FACET_SYNC_HELPERS_V2 === */

/* === BEGIN BLOCK: FILTER_BAR_UI_STATE_V4 | Zweck: hält Pill-Labels, Reset-Sichtbarkeit und Date-UI strikt synchron zum Facettenzustand; Umfang: ersetzt updateFilterBarUI() und updateFacetOptionStates() für Zeitraum + exaktes Datum === */
  updateFilterBarUI(timeValueEl, catValueEl, resetEl) {
    const timeMap = {
      all: "Alle",
      today: "Heute",
      week: "Diese Woche",
      weekend: "Dieses Wochenende",
      nextweek: "Nächste Woche",
      later: "Später"
    };

    const timeKey = (this.filters.zeitraum || "all").trim();
    const selectedDate = (this.filters.selectedDate || "").trim();
    const cat = (this.filters.kategorie || "").trim();
    const hasActiveFacetFilters = timeKey !== "all" || cat.length > 0 || selectedDate.length > 0;

    const ui = this._ui || {};
    const rowEl = ui.searchRow || document.querySelector(".desktop-hero__search-row");
    const timeLabel = selectedDate
      ? (this.formatSelectedDateLabel(selectedDate, "short") || selectedDate)
      : (timeMap[timeKey] || "Alle");

    if (timeValueEl) {
      timeValueEl.textContent = timeLabel;
      if (selectedDate) timeValueEl.title = this.formatSelectedDateLabel(selectedDate, "long") || timeLabel;
      else timeValueEl.removeAttribute("title");
    }

    if (catValueEl) catValueEl.textContent = cat ? cat : "Alle";

    if (rowEl) {
      rowEl.classList.toggle("has-active-filter-reset", hasActiveFacetFilters);
    }

    if (resetEl) {
      resetEl.hidden = !hasActiveFacetFilters;
    }

    this.syncDateFilterUI();
  },

  updateFacetOptionStates() {
    const ui = this._ui;
    if (!ui?.timeSheet || !ui?.catSheet) return;

    const timeKey = (this.filters.zeitraum || "all").trim();
    const selectedDate = (this.filters.selectedDate || "").trim();
    const catNeedle = (this.filters.kategorie || "").trim();
    const searchNeedle = (this.filters.searchText || "").trim().toLowerCase();

    const matchesSearch = (event) => {
      if (!searchNeedle) return true;
      const title = (event?.eventName || event?.title || "").toLowerCase();
      const desc = (event?.beschreibung || "").toLowerCase();
      const loc = (event?.location || "").toLowerCase();
      return `${title} ${desc} ${loc}`.includes(searchNeedle);
    };

    const matchesCategory = (event) => {
      if (!catNeedle) return true;
      const evCatRaw = (event?.kategorie || "").trim();
      const evCat = this.normalizeCategory(evCatRaw);
      const filterCat = this.normalizeCategory(catNeedle) || catNeedle;
      return evCat === filterCat || evCatRaw === catNeedle;
    };

    const matchesTimeKey = (event, key) => {
      if (key === "all") return true;
      return this.getBucketForEvent(event) === key;
    };

    const baseForTime = (this.allEvents || []).filter((event) => {
      return matchesSearch(event) && matchesCategory(event);
    });

    const baseForCat = (this.allEvents || []).filter((event) => {
      if (!matchesSearch(event)) return false;
      if (selectedDate) return this.eventMatchesSelectedDate(event, selectedDate);
      return matchesTimeKey(event, timeKey);
    });

    const timeCounts = {
      all: baseForTime.length,
      today: 0,
      week: 0,
      weekend: 0,
      nextweek: 0,
      later: 0
    };

    for (const event of baseForTime) {
      const bucket = this.getBucketForEvent(event);
      if (bucket in timeCounts) timeCounts[bucket] += 1;
    }

    const catCounts = {};
    for (const category of this.canonicalCategories) catCounts[category] = 0;
    for (const event of baseForCat) {
      const rawCategory = (event?.kategorie || "").trim();
      const canonical = this.normalizeCategory(rawCategory);
      if (canonical) catCounts[canonical] = (catCounts[canonical] ?? 0) + 1;
    }

    const activeTimeOk = timeKey === "all" ? true : ((timeCounts[timeKey] ?? 0) > 0);
    if (!selectedDate && !activeTimeOk) {
      this.filters.zeitraum = "all";
      return this.applyFilters();
    }

    if (catNeedle) {
      const canonical = this.normalizeCategory(catNeedle) || catNeedle;
      const activeCatOk = (catCounts[canonical] ?? 0) > 0;
      if (!activeCatOk) {
        this.filters.kategorie = "";
        return this.applyFilters();
      }
    }

    const timeButtons = this.getFacetButtons("time");
    const catButtons = this.getFacetButtons("category");

    timeButtons.forEach((button) => {
      const key = this.getFacetButtonValue(button, "time");
      const count = timeCounts[key] ?? 0;
      const enabled = key === "all" ? true : count > 0;
      const withCount = button.classList.contains("filter-sheet-option");
      this.setFacetButtonState(button, { enabled, count, withCount });
    });

    catButtons.forEach((button) => {
      const raw = this.getFacetButtonValue(button, "category");
      const withCount = button.classList.contains("filter-sheet-option");

      if (!raw) {
        this.setFacetButtonState(button, { enabled: true, count: baseForCat.length, withCount });
        return;
      }

      const canonical = this.normalizeCategory(raw) || raw;
      const count = catCounts[canonical] ?? 0;
      this.setFacetButtonState(button, { enabled: count > 0, count, withCount });
    });

    const sortCategoryContainer = (container) => {
      if (!container) return;

      const allButton = container.querySelector('[data-category=""]');
      const categoryButtons = Array.from(container.querySelectorAll('[data-category]'))
        .filter((button) => this.getFacetButtonValue(button, "category").length > 0);

      categoryButtons.sort((a, b) => {
        const aCategory = this.normalizeCategory(this.getFacetButtonValue(a, "category")) || this.getFacetButtonValue(a, "category");
        const bCategory = this.normalizeCategory(this.getFacetButtonValue(b, "category")) || this.getFacetButtonValue(b, "category");
        const aCount = catCounts[aCategory] ?? 0;
        const bCount = catCounts[bCategory] ?? 0;
        if (bCount !== aCount) return bCount - aCount;
        return this.canonicalCategories.indexOf(aCategory) - this.canonicalCategories.indexOf(bCategory);
      });

      if (allButton) container.appendChild(allButton);
      for (const button of categoryButtons) container.appendChild(button);
    };

    sortCategoryContainer(ui.catSheet.querySelector(".filter-sheet__body"));
    sortCategoryContainer(document.querySelector("#popover-category .filter-popover__panel"));

    const activeTimeBtn = selectedDate
      ? null
      : (
        timeButtons.find((button) => this.getFacetButtonValue(button, "time") === this.filters.zeitraum)
        || timeButtons.find((button) => this.getFacetButtonValue(button, "time") === "all")
      );

    const activeCatBtn = catButtons.find((button) => this.getFacetButtonValue(button, "category") === this.filters.kategorie)
      || catButtons.find((button) => this.getFacetButtonValue(button, "category") === "");

    this.setActiveOption(ui.timeSheet, activeTimeBtn);
    this.setActiveOption(ui.catSheet, activeCatBtn);
    this.syncDateFilterUI();
  },
  /* === END BLOCK: FILTER_BAR_UI_STATE_V4 === */

  /* === BEGIN BLOCK: FILTER_RESET_AND_REFRESH_TAIL_V6 | Zweck: setzt Zeit/Kategorie/exaktes Datum zurück und schließt den integrierten Kalenderzustand sauber mit; Umfang: ersetzt resetFacetFilters() === */
  resetFacetFilters() {
    const ui = this._ui || {};

    this.filters.kategorie = "";
    this.filters.zeitraum = "all";
    this.filters.selectedDate = "";
    this._datePickerMonth = this.getMonthKey(this.getTodayIso());

    if (ui.timeSheet) {
      this.setActiveOption(
        ui.timeSheet,
        ui.timeSheet.querySelector('[data-time="all"]')
      );
      ui.timeSheet.hidden = true;
    }

    if (ui.catSheet) {
      this.setActiveOption(
        ui.catSheet,
        ui.catSheet.querySelector('[data-category=""]')
      );
      ui.catSheet.hidden = true;
    }

    const timePopover = document.getElementById("popover-time");
    const catPopover = document.getElementById("popover-category");

    if (timePopover) timePopover.hidden = true;
    if (catPopover) catPopover.hidden = true;

    const timePill = ui.timePill || document.getElementById("filter-time-pill");
    const catPill = ui.catPill || document.getElementById("filter-category-pill");

    if (timePill) timePill.setAttribute("aria-expanded", "false");
    if (catPill) catPill.setAttribute("aria-expanded", "false");

    this._openDesktopPopover = null;
    document.body.classList.remove("is-sheet-open");
    this.closeDatePickers();
    this.syncDateFilterUI();

    this.applyFilters();

    this.updateFilterBarUI(
      ui.timeValue || document.getElementById("filter-time-value"),
      ui.catValue || document.getElementById("filter-category-value"),
      ui.resetPill || document.getElementById("filter-reset-pill")
    );

    debugLog("Facet filters reset");
  },
  /* === END BLOCK: FILTER_RESET_AND_REFRESH_TAIL_V6 === */

  /**
   * Events neu laden (z. B. nach Airtable-Update)
   */
  refresh(events) {
    this.allEvents = Array.isArray(events) ? events : [];
    this.applyFilters();
  }
  /* === END BLOCK: FILTER_RESET_AND_REFRESH_TAIL_V5 === */
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


