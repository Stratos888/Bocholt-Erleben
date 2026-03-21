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
    zeitraum: "all"   // all | today | week | weekend | nextweek | later
  },

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
      resetPill
    };

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

   /* === BEGIN BLOCK: DESKTOP POPOVER SWITCH (adaptive filter UI) ===
Zweck:
- Desktop: Popover statt Bottom-Sheet
- Mobile: unverändert Sheets
Umfang:
- ersetzt nur Öffnungslogik der Filter-Pills
=== */

const isDesktop = () => window.matchMedia("(min-width: 900px)").matches;

const getPopover = (type) => document.getElementById(`popover-${type}`);

const openPopover = (type, triggerEl) => {
  const pop = getPopover(type);
  if (!pop || !triggerEl) return;

  closeAllPopovers();

  const rect = triggerEl.getBoundingClientRect();

  const top = rect.bottom + window.scrollY + 8;
  const left = rect.left + window.scrollX;

  pop.style.top = `${top}px`;
  pop.style.left = `${left}px`;
  pop.hidden = false;

  triggerEl.setAttribute("aria-expanded", "true");

  this._openDesktopPopover = type;
};

const closePopover = (type) => {
  const pop = getPopover(type);
  if (!pop) return;

  pop.hidden = true;

  const trigger = type === "time" ? timePill : catPill;
  if (trigger) trigger.setAttribute("aria-expanded", "false");

  this._openDesktopPopover = null;
};

const closeAllPopovers = () => {
  closePopover("time");
  closePopover("category");
};

// CLICK HANDLER (ADAPTIV)
timePill.addEventListener("click", (e) => {
  if (!isDesktop()) return openSheet(timeSheet);
  openPopover("time", timePill);
});

catPill.addEventListener("click", (e) => {
  if (!isDesktop()) return openSheet(catSheet);
  openPopover("category", catPill);
});

// OUTSIDE CLICK (DESKTOP ONLY)
document.addEventListener("click", (e) => {
  if (!isDesktop()) return;

  const open = this._openDesktopPopover;
  if (!open) return;

  const pop = getPopover(open);
  const trigger = open === "time" ? timePill : catPill;

  if (
    pop &&
    !pop.contains(e.target) &&
    trigger &&
    !trigger.contains(e.target)
  ) {
    closePopover(open);
  }
});

// ESC erweitert (für Popover)
document.addEventListener("keydown", (e) => {
  if (e.key !== "Escape") return;

  if (this._openDesktopPopover) {
    closeAllPopovers();
  }
});

// RESIZE SAFETY
window.addEventListener("resize", () => {
  if (!isDesktop()) {
    closeAllPopovers();
  }
});

/* === END BLOCK: DESKTOP POPOVER SWITCH (adaptive filter UI) === */

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

    /* === BEGIN BLOCK: FILTER_RESET_PILL_HANDLER_V2 | Zweck: koppelt das globale X an einen reinen Facetten-Reset, während die Suche als lokales Search-Feld-Verhalten bestehen bleibt; Umfang: ersetzt ausschließlich den Click-Handler des globalen Reset-Pills === */
    resetPill.addEventListener("click", () => {
      this.resetFacetFilters();
    });
    /* === END BLOCK: FILTER_RESET_PILL_HANDLER_V2 === */

    // Initial render
    this.applyFilters();
    this.setActiveOption(timeSheet, timeSheet.querySelector('[data-time="all"]'));
    this.setActiveOption(catSheet, catSheet.querySelector('[data-category=""]'));
    this.updateFilterBarUI(timeValue, catValue, resetPill);

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

               /* === BEGIN BLOCK: CATEGORY FILTER (normalized, canonical) ===
      Zweck: Kategorie-Filter robust via Canonical-Mapping, damit keine Events “unsichtbar” werden,
            auch wenn Event-Kategorien granular/uneinheitlich sind.
      Umfang: Ersetzt nur die Single-Kategorie-Filterlogik in applyFilters().
      === */
      if (catNeedle) {
        const evCatRaw = (event?.kategorie || "").trim();
        const filterRaw = catNeedle;

        const evCat = this.normalizeCategory(evCatRaw);
        const filterCat = this.normalizeCategory(filterRaw) || filterRaw; // falls Filter bereits canonical ist

        // Match, wenn canonical übereinstimmt ODER (Fallback) raw exakt passt
        if (evCat !== filterCat && evCatRaw !== filterRaw) return false;
      }
      /* === END BLOCK: CATEGORY FILTER (normalized, canonical) === */



/* === BEGIN BLOCK: ZEITFILTER (feed-buckets, single source of truth) ===
Zweck:
- Zeitfilter muss exakt dieselben Buckets nutzen wie die Feed-Überschriften in js/events.js:
  Heute → Diese Woche → Dieses Wochenende → Nächste Woche → Später
- Range-aware: endDate wird berücksichtigt; laufende Events zählen als "Heute".
Umfang:
- Ersetzt ausschließlich Zeitraum-Filterlogik in applyFilters().
=== */
if (timeKey !== "all") {
  const isoStart = (event?.date || event?.datum || "").trim();
  const isoEnd = (event?.endDate || event?.endDatum || "").trim(); // optional

  // ISO YYYY-MM-DD robust lokal parsen (00:00 lokal)
  const toLocalDay = (s) => {
    const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!m) return null;
    const y = Number(m[1]), mo = Number(m[2]), d = Number(m[3]);
    if (!y || !mo || !d) return null;
    const dt = new Date(y, mo - 1, d);
    dt.setHours(0, 0, 0, 0);
    return dt;
  };

  const addDaysLocal = (base, n) => {
    const t = new Date(base);
    t.setDate(t.getDate() + n);
    t.setHours(0, 0, 0, 0);
    return t;
  };

  const endOfDay = (d) => {
    const t = new Date(d);
    t.setHours(23, 59, 59, 999);
    return t;
  };

  const endOfWeek = (fromDate) => {
    const d = new Date(fromDate);
    const day = d.getDay(); // 0 So .. 6 Sa
    const diff = (7 - day) % 7;
    d.setDate(d.getDate() + diff);
    d.setHours(23, 59, 59, 999);
    return d;
  };

  const nextWeekendRange = (base) => {
    const b = new Date(base);
    b.setHours(0, 0, 0, 0);
    const dow = b.getDay(); // 0 So ... 6 Sa

    let fri;
    if (dow === 5) fri = addDaysLocal(b, 0);       // Fr
    else if (dow === 6) fri = addDaysLocal(b, -1); // Sa -> Fr
    else if (dow === 0) fri = addDaysLocal(b, -2); // So -> Fr
    else {
      const daysUntilFri = (5 - dow + 7) % 7; // Fr=5
      fri = addDaysLocal(b, daysUntilFri);
    }

    const start = new Date(fri);
    start.setHours(0, 0, 0, 0);

    const sun = addDaysLocal(fri, 2);
    const end = new Date(sun);
    end.setHours(23, 59, 59, 999);

    return { start, end };
  };

  const startDay = toLocalDay(isoStart);
  if (!startDay) return false;

  const endBase = isoEnd ? toLocalDay(isoEnd) : new Date(startDay);
  if (!endBase) return false;

  const endDay = endOfDay(endBase);

  const today = new Date();
  today.setHours(0, 0, 0, 0);

  // "effective day" wie events.js: laufende Events gelten als heute
  const effectiveDay = (() => {
    if (today >= startDay && today <= endDay) return new Date(today);
    return new Date(startDay);
  })();
  effectiveDay.setHours(0, 0, 0, 0);

  // Bucket-Definition exakt wie events.js renderList()
  const dow = today.getDay(); // 0 So .. 6 Sa
  const hasThisWeek = (dow >= 1 && dow <= 4); // Mo–Do
  const thisWeekStart = addDaysLocal(today, 1); // morgen
  let thisWeekEnd = null;
  if (hasThisWeek) {
    const daysUntilThu = (4 - dow);
    const thu = addDaysLocal(today, daysUntilThu);
    thisWeekEnd = endOfDay(thu);
  }

  const weekend = nextWeekendRange(today);
  const weekendStart = new Date(weekend.start);
  const weekendEnd = new Date(weekend.end);

  const endThisWeek = endOfWeek(today); // Sonntag 23:59:59
  const nextWeekStart = addDaysLocal(endThisWeek, 1);
  const nextWeekEnd = addDaysLocal(nextWeekStart, 6);
  nextWeekEnd.setHours(23, 59, 59, 999);

  const pickBucket = (day) => {
    if (!day) return "later";
    if (day.getTime() === today.getTime()) return "today";
    if (hasThisWeek && thisWeekEnd && day >= thisWeekStart && day <= thisWeekEnd) return "week";
    if (day >= weekendStart && day <= weekendEnd) return "weekend";
    if (day >= nextWeekStart && day <= nextWeekEnd) return "nextweek";
    return "later";
  };

  const bucket = pickBucket(effectiveDay);
  if (bucket !== timeKey) return false;
}
/* === END BLOCK: ZEITFILTER (feed-buckets, single source of truth) === */




      return true;
    });

    // BEGIN: APPLYFILTERS_UI_SYNC (Reset-X nur bei B/C/D aktiv)
// Zweck: UI-Status (Labels + Reset-X) wird nach jedem applyFilters() aus dem State synchronisiert.
// Umfang: Ersetzt den UI-Update-Abschnitt am Ende von applyFilters().
// END: APPLYFILTERS_UI_SYNC (Reset-X nur bei B/C/D aktiv)
    // UI aktualisieren
    this.updateUI();

    // Reset-X/Labels immer aus State ableiten (nicht nur aus UI-Events),
    // damit Sichtbarkeit nur bei B/C/D aktiv ist – auch nach refresh()/initial apply.
       this.updateFilterBarUI(
      document.getElementById("filter-time-value"),
      document.getElementById("filter-category-value"),
      document.getElementById("filter-reset-pill")
    );

    /* === BEGIN BLOCK: FACETS UPDATE (counts + disabled, time + category) ===
    Zweck: Zeit- und Kategorieoptionen mit Counts versehen und 0-Treffer-Optionen disabled anzeigen.
           Disabled bleibt sichtbar (transparente UX), aber verhindert Sackgassen.
    Umfang: Wird nach jedem applyFilters() ausgeführt.
    === */
    this.updateFacetOptionStates();
    /* === END BLOCK: FACETS UPDATE (counts + disabled, time + category) === */

    debugLog(`Filtered: ${this.filteredEvents.length} of ${(this.allEvents || []).length} events`);

  },
// END: APPLYFILTERS_UI_SYNC (Reset-X nur bei B/C/D aktiv)



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

  /* === BEGIN BLOCK: FILTER_BAR_UI_STATE_V3 | Zweck: hält Pill-Labels, Reset-Sichtbarkeit und den Mobile-3-Spalten-State strikt am Facettenzustand; Umfang: ersetzt ausschließlich updateFilterBarUI() === */
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
    const cat = (this.filters.kategorie || "").trim();
    const hasActiveFacetFilters = timeKey !== "all" || cat.length > 0;

    const ui = this._ui || {};
    const rowEl = ui.searchRow || document.querySelector(".desktop-hero__search-row");

    if (timeValueEl) timeValueEl.textContent = timeMap[timeKey] || "Alle";
    if (catValueEl) catValueEl.textContent = cat ? cat : "Alle";

    if (rowEl) {
      rowEl.classList.toggle("has-active-filter-reset", hasActiveFacetFilters);
    }

    if (resetEl) {
      resetEl.hidden = !hasActiveFacetFilters;
    }
  },
  /* === END BLOCK: FILTER_BAR_UI_STATE_V3 === */

  updateFacetOptionStates() {
    const ui = this._ui;
    if (!ui?.timeSheet || !ui?.catSheet) return;

    const timeKey = (this.filters.zeitraum || "all").trim();
    const catNeedle = (this.filters.kategorie || "").trim();
    const searchNeedle = (this.filters.searchText || "").trim().toLowerCase();

    // --- Zeit-Helfer (identisch zur applyFilters Logik) ---
    const toLocalDay = (s) => {
      const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})$/);
      if (!m) return null;
      const y = Number(m[1]), mo = Number(m[2]), d = Number(m[3]);
      if (!y || !mo || !d) return null;
      const dt = new Date(y, mo - 1, d);
      dt.setHours(0, 0, 0, 0);
      return dt;
    };

    const addDaysLocal = (base, n) => {
      const t = new Date(base);
      t.setDate(t.getDate() + n);
      t.setHours(0, 0, 0, 0);
      return t;
    };

    const endOfDay = (d) => {
      const t = new Date(d);
      t.setHours(23, 59, 59, 999);
      return t;
    };

    const endOfWeek = (fromDate) => {
      const d = new Date(fromDate);
      const day = d.getDay(); // 0 So .. 6 Sa
      const diff = (7 - day) % 7;
      d.setDate(d.getDate() + diff);
      d.setHours(23, 59, 59, 999);
      return d;
    };

    const nextWeekendRange = (base) => {
      const b = new Date(base);
      b.setHours(0, 0, 0, 0);
      const dow = b.getDay(); // 0 So ... 6 Sa

      let fri;
      if (dow === 5) fri = addDaysLocal(b, 0);       // Fr
      else if (dow === 6) fri = addDaysLocal(b, -1); // Sa -> Fr
      else if (dow === 0) fri = addDaysLocal(b, -2); // So -> Fr
      else {
        const daysUntilFri = (5 - dow + 7) % 7; // Fr=5
        fri = addDaysLocal(b, daysUntilFri);
      }

      const start = new Date(fri);
      start.setHours(0, 0, 0, 0);

      const sun = addDaysLocal(fri, 2);
      const end = new Date(sun);
      end.setHours(23, 59, 59, 999);

      return { start, end };
    };

    // Bucket-Definition exakt wie Feed: Heute / Diese Woche / Dieses Wochenende / Nächste Woche / Später
    const getBucketForEvent = (event) => {
      const isoStart = (event?.date || event?.datum || "").trim();
      const isoEnd = (event?.endDate || event?.endDatum || "").trim();

      const startDay = toLocalDay(isoStart);
      if (!startDay) return "later";

      const endBase = isoEnd ? toLocalDay(isoEnd) : new Date(startDay);
      if (!endBase) return "later";

      const endDay = endOfDay(endBase);

      const today = new Date();
      today.setHours(0, 0, 0, 0);

      // laufende Events zählen als "Heute"
      const effectiveDay = (() => {
        const todayEnd = endOfDay(today);
        if (today >= startDay && todayEnd <= endDay) return new Date(today);
        // Fallback: klassischer Overlap (heute liegt zwischen Start/Ende)
        if (today >= startDay && today <= endDay) return new Date(today);
        return new Date(startDay);
      })();
      effectiveDay.setHours(0, 0, 0, 0);

      const dow = today.getDay(); // 0 So .. 6 Sa
      const hasThisWeek = (dow >= 1 && dow <= 4); // Mo–Do
      const thisWeekStart = addDaysLocal(today, 1); // morgen
      let thisWeekEnd = null;
      if (hasThisWeek) {
        const daysUntilThu = (4 - dow);
        const thu = addDaysLocal(today, daysUntilThu);
        thisWeekEnd = endOfDay(thu);
      }

      const weekend = nextWeekendRange(today);
      const weekendStart = new Date(weekend.start);
      const weekendEnd = new Date(weekend.end);

      const endThisWeek = endOfWeek(today); // Sonntag 23:59:59
      const nextWeekStart = addDaysLocal(endThisWeek, 1);
      const nextWeekEnd = addDaysLocal(nextWeekStart, 6);
      nextWeekEnd.setHours(23, 59, 59, 999);

      if (effectiveDay.getTime() === today.getTime()) return "today";
      if (hasThisWeek && thisWeekEnd && effectiveDay >= thisWeekStart && effectiveDay <= thisWeekEnd) return "week";
      if (effectiveDay >= weekendStart && effectiveDay <= weekendEnd) return "weekend";
      if (effectiveDay >= nextWeekStart && effectiveDay <= nextWeekEnd) return "nextweek";
      return "later";
    };

    const matchesTimeKey = (event, key) => {
      if (key === "all") return true;
      return getBucketForEvent(event) === key;
    };

    // --- Base-Listen: Für Zeit-Facets zählt Search + aktive Kategorie.
    const baseForTime = (this.allEvents || []).filter((event) => {
      if (searchNeedle) {
        const title = (event?.eventName || event?.title || "").toLowerCase();
        const desc = (event?.beschreibung || "").toLowerCase();
        const loc = (event?.location || "").toLowerCase();
        const searchable = `${title} ${desc} ${loc}`;
        if (!searchable.includes(searchNeedle)) return false;
      }
      if (catNeedle) {
        const evCatRaw = (event?.kategorie || "").trim();
        const evCat = this.normalizeCategory(evCatRaw);
        const filterCat = this.normalizeCategory(catNeedle) || catNeedle;
        if (evCat !== filterCat && evCatRaw !== catNeedle) return false;
      }
      return true;
    });

    // --- Base-Listen: Für Kategorie-Facets zählt Search + aktiver Zeitfilter.
    const baseForCat = (this.allEvents || []).filter((event) => {
      if (searchNeedle) {
        const title = (event?.eventName || event?.title || "").toLowerCase();
        const desc = (event?.beschreibung || "").toLowerCase();
        const loc = (event?.location || "").toLowerCase();
        const searchable = `${title} ${desc} ${loc}`;
        if (!searchable.includes(searchNeedle)) return false;
      }
      if (timeKey !== "all" && !matchesTimeKey(event, timeKey)) return false;
      return true;
    });

    // --- Counts berechnen ---
    const timeCounts = {
      all: baseForTime.length,
      today: 0,
      week: 0,
      weekend: 0,
      nextweek: 0,
      later: 0
    };

    for (const ev of baseForTime) {
      const k = getBucketForEvent(ev);
      if (k === "today") timeCounts.today++;
      else if (k === "week") timeCounts.week++;
      else if (k === "weekend") timeCounts.weekend++;
      else if (k === "nextweek") timeCounts.nextweek++;
      else if (k === "later") timeCounts.later++;
    }

    const catCounts = {};
    for (const c of this.canonicalCategories) catCounts[c] = 0;
    for (const ev of baseForCat) {
      const evCatRaw = (ev?.kategorie || "").trim();
      const c = this.normalizeCategory(evCatRaw);
      catCounts[c] = (catCounts[c] ?? 0) + 1;
    }

    // --- Auto-Healing: aktiver Filter darf nicht in 0 Ergebnissen „stecken bleiben“ ---
    const activeTimeOk = (timeKey === "all") ? true : ((timeCounts[timeKey] ?? 0) > 0);
    if (!activeTimeOk) {
      this.filters.zeitraum = "all";
      return this.applyFilters();
    }

    if (catNeedle) {
      const canon = this.normalizeCategory(catNeedle) || catNeedle;
      const activeCatOk = (catCounts[canon] ?? 0) > 0;
      if (!activeCatOk) {
        this.filters.kategorie = "";
        return this.applyFilters();
      }
    }

    const timeButtons = this.getFacetButtons("time");
    const catButtons = this.getFacetButtons("category");

    timeButtons.forEach((btn) => {
      const key = this.getFacetButtonValue(btn, "time");
      const cnt = timeCounts[key] ?? 0;
      const enabled = (key === "all") ? true : cnt > 0;
      const withCount = btn.classList.contains("filter-sheet-option");
      this.setFacetButtonState(btn, { enabled, count: cnt, withCount });
    });

    catButtons.forEach((btn) => {
      const raw = this.getFacetButtonValue(btn, "category");
      const withCount = btn.classList.contains("filter-sheet-option");

      if (!raw) {
        this.setFacetButtonState(btn, { enabled: true, count: baseForCat.length, withCount });
        return;
      }

      const canon = this.normalizeCategory(raw) || raw;
      const cnt = catCounts[canon] ?? 0;
      this.setFacetButtonState(btn, { enabled: cnt > 0, count: cnt, withCount });
    });

    const sortCategoryContainer = (container) => {
      if (!container) return;

      const allBtn = container.querySelector('[data-category=""]');
      const catBtns = Array.from(container.querySelectorAll('[data-category]'))
        .filter((btn) => this.getFacetButtonValue(btn, "category").length > 0);

      catBtns.sort((a, b) => {
        const ac = this.normalizeCategory(this.getFacetButtonValue(a, "category")) || this.getFacetButtonValue(a, "category");
        const bc = this.normalizeCategory(this.getFacetButtonValue(b, "category")) || this.getFacetButtonValue(b, "category");
        const ca = catCounts[ac] ?? 0;
        const cb = catCounts[bc] ?? 0;
        if (cb !== ca) return cb - ca;
        return this.canonicalCategories.indexOf(ac) - this.canonicalCategories.indexOf(bc);
      });

      if (allBtn) container.appendChild(allBtn);
      for (const btn of catBtns) container.appendChild(btn);
    };

    sortCategoryContainer(ui.catSheet.querySelector(".filter-sheet__body"));
    sortCategoryContainer(document.querySelector("#popover-category .filter-popover__panel"));

    const activeTimeBtn = timeButtons.find((btn) => this.getFacetButtonValue(btn, "time") === this.filters.zeitraum)
      || timeButtons.find((btn) => this.getFacetButtonValue(btn, "time") === "all");
    const activeCatBtn = catButtons.find((btn) => this.getFacetButtonValue(btn, "category") === this.filters.kategorie)
      || catButtons.find((btn) => this.getFacetButtonValue(btn, "category") === "");

    this.setActiveOption(ui.timeSheet, activeTimeBtn);
    this.setActiveOption(ui.catSheet, activeCatBtn);
  },

  /* === END BLOCK: FILTER UI HELPERS + FACETS (canonical + counts + disabled) === */

  /* === BEGIN BLOCK: FILTER_RESET_AND_REFRESH_TAIL_V5 | Zweck: stellt die fehlende resetFacetFilters()-Methode korrekt wieder her, setzt nur Zeit/Kategorie zurück, synchronisiert aktive Optionen in Sheet+Popover und belässt die Suche unangetastet; Umfang: ersetzt den kaputten Tail ab dem losen Reset-Code bis inkl. refresh() === */
  resetFacetFilters() {
    const ui = this._ui || {};

    this.filters.kategorie = "";
    this.filters.zeitraum = "all";

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

    this.applyFilters();

    this.updateFilterBarUI(
      ui.timeValue || document.getElementById("filter-time-value"),
      ui.catValue || document.getElementById("filter-category-value"),
      ui.resetPill || document.getElementById("filter-reset-pill")
    );

    debugLog("Facet filters reset");
  },

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


