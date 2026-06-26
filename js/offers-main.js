// BEGIN: FILE_HEADER_OFFERS_MAIN
// Datei: js/offers-main.js
// Zweck:
// - Bootstrapping für Aktivitäten-Seite (/angebote/)
// - Lädt /data/offers.json, normalisiert Aktivitätsdaten und rendert den Feed
// - Steuert den semantischen Aktivitäten-Finder mit Suche, Schnellfiltern und kombinierbaren Filtergruppen
// END: FILE_HEADER_OFFERS_MAIN

/* === BEGIN BLOCK: ACTIVITIES_SEMANTIC_FINDER_OWNER_V1 | Zweck: ersetzt den alten Single-Filter-/Sheet-/Popover-Owner durch Suche, Schnellfilter, kombinierbare Filtergruppen, aktive Chips, Trefferzahl und Relevanzsortierung; Umfang: Datei-Anfang bis direkt vor showLoading(show) === */
const OffersApp = {
  offers: [],
  filteredOffers: [],
  searchTerm: "",
  activeFilters: {
    seasonal: new Set(),
    situation: new Set(),
    proximity: new Set(),
    activity_type: new Set(),
    features: new Set(),
    effort: new Set()
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_NARROWING_GROUP_CONTRACT_V1 | Zweck: definiert eine einheitliche Nutzerlogik: jeder weitere Filter verengt die Treffer; Ort/Nähe blockiert dabei parallele Alternativen, damit Schnellfilter nicht wie OR-Wechsel wirken; Umfang: Filterreihenfolge, Exklusivgruppen und Filtergruppen-Konfiguration === */
  filterGroupOrder: ["seasonal", "proximity", "situation", "activity_type", "features", "effort"],
  exclusiveFilterGroups: new Set(["proximity"]),

  filterGroups: Object.freeze({
    seasonal: Object.freeze({
      label: "Jetzt",
      mode: "all",
      options: Object.freeze(["Jetzt besonders"])
    }),
    proximity: Object.freeze({
      label: "Ort / Nähe",
      mode: "all",
      options: Object.freeze(["Direkt in Bocholt", "In der Umgebung", "Grenzregion / Niederlande"])
    }),
    situation: Object.freeze({
      label: "Situation",
      mode: "all",
      options: Object.freeze(["Mit Kindern", "Bei Regen", "Draußen"])
    }),
    activity_type: Object.freeze({
      label: "Aktivitätsart",
      mode: "all",
      options: Object.freeze(["Spazieren", "Radfahren", "Spielen", "Kultur", "Natur entdecken", "Sport & Bewegung"])
    }),
    features: Object.freeze({
      label: "Ausstattung & Erlebnis",
      mode: "all",
      options: Object.freeze(["Spielplatz", "Wasser", "Tiere", "Café", "Baden", "Rundweg", "Aussicht"])
    }),
    effort: Object.freeze({
      label: "Aufwand",
      mode: "all",
      options: Object.freeze(["Spontan", "Halber Tag", "Längerer Ausflug"])
    })
  }),
  /* === END BLOCK: ACTIVITIES_FINDER_NARROWING_GROUP_CONTRACT_V1 === */
  refs: {
    finder: null,
    searchInput: null,
    searchClear: null,
    searchRow: null,
    resetPill: null,
    advancedToggles: [],
    advancedToggle: null,
    advancedPanel: null,
    advancedCount: null,
    advancedSummary: null,
    advancedSummaryText: null,
    advancedReset: null,
    resultCount: null,
    activeFilters: null,
    activeFilterList: null
  },

  async init() {
    debugLog?.("=== ACTIVITIES - APP START ===");
    this.cacheRefs();

    /* === BEGIN BLOCK: ACTIVITIES_LOADING_START_PARITY_V2 | Zweck: gleicht Aktivitäten vollständig an die Event-Seite an – auf allen Viewports sofort Skeleton im Feed, Spinner nur noch als Fallback/Error/No-Data | Umfang: ersetzt nur den Loading-Start in init() === */
    if (typeof window.OfferCards?.renderSkeleton === "function") {
      window.OfferCards.renderSkeleton(8);
      this.showLoading(false);
    } else {
      this.showLoading(true);
    }
    /* === END BLOCK: ACTIVITIES_LOADING_START_PARITY_V2 === */

    try {
      const response = await fetch("/data/offers.json", { cache: "no-store" });
      if (!response.ok) {
        throw new Error(`offers.json load failed: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();

      try {
        const visualPoolResponse = await fetch("/data/activity_visual_pool.json", { cache: "no-store" });
        if (visualPoolResponse.ok) {
          const visualPoolData = await visualPoolResponse.json();
          if (typeof window.OfferVisuals?.setActivityVisualPool === "function") {
            window.OfferVisuals.setActivityVisualPool(visualPoolData);
          }
        } else {
          console.warn(`activity_visual_pool.json load failed: ${visualPoolResponse.status} ${visualPoolResponse.statusText}`);
        }
      } catch (visualPoolError) {
        console.warn("Activity visual pool unavailable; falling back to offer.image.", visualPoolError);
      }

      const rawOffers = Array.isArray(data) ? data : (Array.isArray(data?.offers) ? data.offers : []);

      this.offers = rawOffers
        .map((raw, index) => this.normalizeOffer(raw, index))
        .filter(Boolean);

      if (!this.offers.length) {
        this.showNoOffers();
        this.updateFinderUI();
        return;
      }

      this.bindControls();
      this.initFilterButtonLabels();
      this.applyFilterAndRender();
      this.showLoading(false);

      debugLog?.(`=== ACTIVITIES READY - ${this.offers.length} activities loaded ===`);
    } catch (error) {
      console.error("Activities initialization failed:", error);
      this.showError("Fehler beim Laden der Aktivitäten. Bitte Seite neu laden.");
    }
  },

  cacheRefs() {
    this.refs.finder = document.querySelector("[data-activity-finder]");
    this.refs.searchInput = document.getElementById("search-filter");
    this.refs.searchClear = document.getElementById("offer-search-clear");
    this.refs.searchRow = document.querySelector(".desktop-hero__search-row");
    this.refs.resetPill = document.getElementById("offer-reset-pill");
    this.refs.advancedToggles = Array.from(document.querySelectorAll(".activity-finder__advanced-toggle"));
    this.refs.advancedToggle = this.refs.advancedToggles[0] || null;
    this.refs.advancedPanel = document.getElementById("offer-advanced-filters");
    this.refs.advancedCount = document.getElementById("offer-advanced-filter-count");
    this.refs.advancedSummary = document.getElementById("offer-advanced-filter-summary");
    this.refs.advancedSummaryText = document.getElementById("offer-advanced-filter-summary-text");
    this.refs.advancedReset = document.getElementById("offer-advanced-filter-reset");
    this.refs.resultCount = document.getElementById("offer-result-count");
    this.refs.activeFilters = document.getElementById("offer-active-filters");
    this.refs.activeFilterList = document.getElementById("offer-active-filter-list");
  },

  normalizeArray(value) {
    return Array.isArray(value)
      ? value.map((item) => String(item || "").trim()).filter(Boolean)
      : [];
  },

  normalizeBoolean(value) {
    if (typeof value === "boolean") return value;
    const normalized = String(value || "").trim().toLowerCase();
    return normalized === "true" || normalized === "1" || normalized === "yes" || normalized === "ja";
  },

  normalizeFilterModel(rawFilter, legacyTags = []) {
    const source = rawFilter && typeof rawFilter === "object" ? rawFilter : {};
    const result = {};

    this.filterGroupOrder.forEach((group) => {
      const allowed = new Set(this.filterGroups[group].options);
      result[group] = this.normalizeArray(source[group]).filter((value) => allowed.has(value));
    });

    const hasStructuredValues = this.filterGroupOrder.some((group) => result[group].length > 0);
    if (hasStructuredValues) return result;

    const legacy = new Set(this.normalizeArray(legacyTags));
    const add = (group, value) => {
      if (!this.filterGroups[group]?.options.includes(value)) return;
      if (!result[group].includes(value)) result[group].push(value);
    };

    if (legacy.has("Mit Kindern")) add("situation", "Mit Kindern");
    if (legacy.has("Indoor")) add("situation", "Bei Regen");
    if (legacy.has("Wandern & Spazieren") || legacy.has("Radfahren") || legacy.has("Wasser") || legacy.has("Spielplatz") || legacy.has("Tiere")) {
      add("situation", "Draußen");
    }
    if (legacy.has("Wandern & Spazieren")) add("activity_type", "Spazieren");
    if (legacy.has("Radfahren")) add("activity_type", "Radfahren");
    if (legacy.has("Spielplatz")) add("activity_type", "Spielen");
    if (legacy.has("Spielplatz")) add("features", "Spielplatz");
    if (legacy.has("Wasser")) add("features", "Wasser");
    if (legacy.has("Tiere")) add("features", "Tiere");

    return result;
  },

  buildSearchText(parts) {
    const source = this.normalizeArray(parts)
      .join(" ")
      .toLowerCase();

    const aeVariant = source
      .replace(/[ä]/g, "ae")
      .replace(/[ö]/g, "oe")
      .replace(/[ü]/g, "ue")
      .replace(/[ß]/g, "ss");

    const plainVariant = source
      .replace(/[ä]/g, "a")
      .replace(/[ö]/g, "o")
      .replace(/[ü]/g, "u")
      .replace(/[ß]/g, "ss");

    return `${source} ${aeVariant} ${plainVariant}`
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  },

  normalizeOffer(raw, index = 0) {
    const obj = raw && typeof raw === "object" ? raw : {};
    const id = String(obj.id || "").trim();
    const title = String(obj.title || "").trim();
    const kategorie = String(obj.kategorie || obj.category || "").trim();
    const location = String(obj.location || obj.place_name || obj.ort || "").trim();
    const description = String(obj.description || "").trim();
    const url = String(obj.url || obj.link || "").trim();
    /* === BEGIN BLOCK: ACTIVITIES_NORMALIZE_REPORTING_TARGET_V1 | Zweck: bewahrt explizite Location-/Anbieter-Zuordnung aus data/offers.json für das Nutzwert-Tracking; Umfang: ergänzt nur Reporting-Ziel-Normalisierung in normalizeOffer() === */
    const rawReportingTarget =
      obj.reporting_target && typeof obj.reporting_target === "object"
        ? obj.reporting_target
        : obj.reportingTarget && typeof obj.reportingTarget === "object"
          ? obj.reportingTarget
          : {};

    const reportingTarget = {
      type: String(
        rawReportingTarget.type ||
        rawReportingTarget.reporting_target_type ||
        rawReportingTarget.reportingTargetType ||
        ""
      ).trim(),
      id: String(
        rawReportingTarget.id ||
        rawReportingTarget.reporting_target_id ||
        rawReportingTarget.reportingTargetId ||
        ""
      ).trim(),
      title: String(
        rawReportingTarget.title ||
        rawReportingTarget.name ||
        rawReportingTarget.reporting_target_title ||
        rawReportingTarget.reportingTargetTitle ||
        ""
      ).trim()
    };
    /* === END BLOCK: ACTIVITIES_NORMALIZE_REPORTING_TARGET_V1 === */

    if (!id || !title || !kategorie || !location || !description || !url) {
      return null;
    }

    const tags = this.normalizeArray(obj.tags);
    const filter_tags = this.normalizeArray(obj.filter_tags);
    const audience = this.normalizeArray(obj.audience);
    const cardFacts = this.normalizeArray(obj.cardFacts);
    const filter = this.normalizeFilterModel(obj.filter, filter_tags);
    const filterValues = this.filterGroupOrder.flatMap((group) => filter[group] || []);

    const offer = {
      id,
      title,
      kategorie,
      location,
      description,
      url,
      maps_query: String(obj.maps_query || "").trim(),
      maps_label: String(obj.maps_label || "").trim(),
      website_label: String(obj.website_label || "").trim(),
      tags,
      filter_tags,
      filter,
      audience,
      cardFacts,
      image: String(obj.image || "").trim(),
      image_position_x: String(obj.image_position_x || "").trim(),
      image_position_y: String(obj.image_position_y || "").trim(),
      image_fit: String(obj.image_fit || "").trim(),
      image_source_page: String(obj.image_source_page || "").trim(),
      image_author: String(obj.image_author || "").trim(),
      image_license: String(obj.image_license || "").trim(),
      image_credit: String(obj.image_credit || "").trim(),
      image_source_type: String(obj.image_source_type || "").trim(),
      visual_key: String(obj.visual_key || obj.image_visual_key || "").trim(),
      image_is_symbolic: this.normalizeBoolean(obj.image_is_symbolic),
      image_is_ai_generated: this.normalizeBoolean(obj.image_is_ai_generated),
      image_note: String(obj.image_note || "").trim(),
      duration: String(obj.duration || "").trim(),
      mode: String(obj.mode || "").trim(),
      price: String(obj.price || "").trim(),
      season: String(obj.season || "").trim(),
      seasonal_highlights: Array.isArray(obj.seasonal_highlights)
        ? obj.seasonal_highlights
        : [],
      opening_status: obj.opening_status && typeof obj.opening_status === "object"
        ? obj.opening_status
        : null,
      opening_hours: obj.opening_hours && typeof obj.opening_hours === "object"
        ? obj.opening_hours
        : null,
      ...(reportingTarget.type && reportingTarget.id && reportingTarget.title
        ? { reporting_target: reportingTarget }
        : {}),
      sortIndex: Number.isFinite(Number(index)) ? Number(index) : 0
    };

    offer.searchIndex = this.buildSearchText([
      offer.title,
      offer.location,
      offer.description,
      offer.kategorie,
      offer.duration,
      offer.mode,
      offer.price,
      offer.season,
      ...(window.BEActivityHighlights?.getSearchTerms?.(offer) || []),
      ...offer.tags,
      ...offer.filter_tags,
      ...offer.audience,
      ...offer.cardFacts,
      ...filterValues
    ]);

    return offer;
  },
  bindControls() {
    const {
      finder,
      searchInput,
      searchClear,
      resetPill,
      activeFilterList
    } = this.refs;

    /* === BEGIN BLOCK: ACTIVITIES_SEARCH_CLEAR_BINDING_V1 | Zweck: synchronisiert Freitextsuche und eigenen Clear-Button; Umfang: nur Search-Input- und Clear-Button-Events === */
    if (searchInput) {
      searchInput.addEventListener("input", () => {
        this.searchTerm = String(searchInput.value || "").trim();
        this.applyFilterAndRender();
      });
    }

    if (searchClear && searchInput) {
      searchClear.addEventListener("click", (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (!String(searchInput.value || "").trim()) return;

        searchInput.value = "";
        this.searchTerm = "";
        this.applyFilterAndRender();
        searchInput.focus({ preventScroll: true });
      });
    }
    /* === END BLOCK: ACTIVITIES_SEARCH_CLEAR_BINDING_V1 === */

    if (finder) {
      finder.addEventListener("click", (event) => {
        const filterButton = event.target.closest(".activity-filter-chip[data-filter-group][data-filter-value]");
        if (!filterButton || !finder.contains(filterButton)) return;

        event.preventDefault();
        this.toggleFilter(
          String(filterButton.getAttribute("data-filter-group") || "").trim(),
          String(filterButton.getAttribute("data-filter-value") || "").trim()
        );
      });
    }

    const toggles = Array.isArray(this.refs.advancedToggles) ? this.refs.advancedToggles : [];
    toggles.forEach((toggle) => {
      toggle.addEventListener("click", (event) => {
        event.preventDefault();
        this.toggleAdvancedFilters();
      });
    });

    if (resetPill) {
      resetPill.addEventListener("click", () => {
        this.resetFilters();
      });
    }

    if (this.refs.advancedReset) {
      this.refs.advancedReset.addEventListener("click", () => {
        this.resetFilters();
      });
    }

    if (activeFilterList) {
      activeFilterList.addEventListener("click", (event) => {
        const chip = event.target.closest("[data-active-filter-group][data-active-filter-value]");
        if (!chip) return;

        event.preventDefault();
        this.removeFilter(
          String(chip.getAttribute("data-active-filter-group") || "").trim(),
          String(chip.getAttribute("data-active-filter-value") || "").trim()
        );
      });
    }

    window.addEventListener("offers:reset-filters", () => {
      this.resetFilters();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      this.closeAdvancedFilters();
    });

    window.addEventListener("resize", () => {
      this.updateFinderUI();
    }, { passive: true });
  },

  initFilterButtonLabels() {
    this.getFilterButtons().forEach((button) => {
      const label = String(button.textContent || "").trim().replace(/\s*\(\d+\)\s*$/, "");
      button.dataset.baseLabel = label;
      button.setAttribute("aria-pressed", "false");
    });
  },

  getFilterButtons() {
    return Array.from(document.querySelectorAll(".activity-filter-chip[data-filter-group][data-filter-value]"));
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_EXCLUSIVE_GROUP_HELPERS_V1 | Zweck: erkennt exklusive Filtergruppen, deren inaktive Alternativen bei aktiver Auswahl ausgeblendet werden; Umfang: ersetzt nur isKnownFilter plus ergänzende Helper === */
  isKnownFilter(group, value) {
    return !!this.filterGroups[group]?.options.includes(value);
  },

  isExclusiveFilterGroup(group) {
    return this.exclusiveFilterGroups instanceof Set && this.exclusiveFilterGroups.has(group);
  },

  isBlockedByActiveExclusiveGroup(group, value) {
    if (!this.isExclusiveFilterGroup(group)) return false;

    const activeValues = this.getActiveValues(group);
    return activeValues.length > 0 && !activeValues.includes(value);
  },
  /* === END BLOCK: ACTIVITIES_FINDER_EXCLUSIVE_GROUP_HELPERS_V1 === */

  getActiveSet(group) {
    if (!this.activeFilters[group]) {
      this.activeFilters[group] = new Set();
    }
    return this.activeFilters[group];
  },

  getActiveValues(group) {
    const set = this.getActiveSet(group);
    const options = this.filterGroups[group]?.options || [];
    return options.filter((value) => set.has(value));
  },

  getActiveFilterCount() {
    return this.filterGroupOrder.reduce((sum, group) => sum + this.getActiveValues(group).length, 0);
  },

  hasActiveFilters() {
    return !!String(this.searchTerm || "").trim() || this.getActiveFilterCount() > 0;
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_NARROWING_TOGGLE_V1 | Zweck: behandelt Filterchips als echte Eingrenzungen: erneuter Klick entfernt, neuer Klick fügt hinzu; Umfang: ersetzt nur toggleFilter(group, value) === */
  toggleFilter(group, value) {
    if (!this.isKnownFilter(group, value)) return;

    const set = this.getActiveSet(group);
    if (set.has(value)) {
      set.delete(value);
    } else {
      set.add(value);
    }

    this.applyFilterAndRender();
  },
  /* === END BLOCK: ACTIVITIES_FINDER_NARROWING_TOGGLE_V1 === */

  removeFilter(group, value) {
    if (!this.isKnownFilter(group, value)) return;
    this.getActiveSet(group).delete(value);
    this.applyFilterAndRender();
  },

  resetFilters() {
    this.searchTerm = "";
    this.filterGroupOrder.forEach((group) => this.getActiveSet(group).clear());

    if (this.refs.searchInput) {
      this.refs.searchInput.value = "";
    }

    this.closeAdvancedFilters();
    this.applyFilterAndRender();
  },

  toggleAdvancedFilters() {
    const { advancedPanel } = this.refs;
    if (!advancedPanel) return;

    if (advancedPanel.hidden) {
      this.openAdvancedFilters();
    } else {
      this.closeAdvancedFilters();
    }
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_ADVANCED_OPEN_STATE_V3 | Zweck: synchronisiert geöffneten Zustand für Desktop- und Mobile-Filtertoggle gemeinsam; Umfang: ersetzt nur openAdvancedFilters() und closeAdvancedFilters() === */
  openAdvancedFilters() {
    const { advancedPanel, finder } = this.refs;
    if (!advancedPanel) return;

    advancedPanel.hidden = false;
    advancedPanel.classList.add("is-open");

    if (finder) {
      finder.classList.add("is-advanced-open");
    }

    this.updateFinderUI();
  },

  closeAdvancedFilters() {
    const { advancedPanel, finder } = this.refs;
    if (!advancedPanel) return;

    advancedPanel.hidden = true;
    advancedPanel.classList.remove("is-open");

    if (finder) {
      finder.classList.remove("is-advanced-open");
    }

    this.updateFinderUI();
  },
  /* === END BLOCK: ACTIVITIES_FINDER_ADVANCED_OPEN_STATE_V3 === */

  getOfferFilterValues(offer, group) {
    return this.normalizeArray(offer?.filter?.[group]);
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_NARROWING_MATCH_MODE_V1 | Zweck: wertet aktive Filter konsequent additiv aus; jeder weitere aktive Chip muss am Ergebnis vorhanden sein und kann Treffer nicht künstlich erweitern; Umfang: ersetzt nur matchesFilterGroup(offer, group, activeValues) === */
  matchesFilterGroup(offer, group, activeValues = this.getActiveValues(group)) {
    if (!activeValues.length) return true;

    if (group === "seasonal") {
      return activeValues.every((value) => {
        if (value !== "Jetzt besonders") return false;
        return window.BEActivityHighlights?.hasActiveHighlight?.(offer) === true;
      });
    }

    const available = new Set(this.getOfferFilterValues(offer, group));
    return activeValues.every((value) => available.has(value));
  },
  /* === END BLOCK: ACTIVITIES_FINDER_NARROWING_MATCH_MODE_V1 === */

  matchesFilterState(offer, filterState = this.activeFilters) {
    return this.filterGroupOrder.every((group) => {
      const values = this.filterGroups[group].options.filter((value) => filterState[group]?.has(value));
      return this.matchesFilterGroup(offer, group, values);
    });
  },

  matchesSearch(offer) {
    const query = String(this.searchTerm || "").trim();
    if (!query) return true;

    const needle = this.buildSearchText([query]);
    if (!needle) return true;

    const tokens = needle.split(" ").filter(Boolean);
    return tokens.every((token) => offer.searchIndex.includes(token));
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_PROJECTED_COUNT_NARROWING_V1 | Zweck: berechnet Trefferzahlen ausschließlich als zusätzliche Verengung; Umfang: ersetzt nur createProjectedState(group, value) === */
  createProjectedState(group, value) {
    const projected = {};
    this.filterGroupOrder.forEach((key) => {
      projected[key] = new Set(this.getActiveValues(key));
    });

    if (!projected[group].has(value)) {
      projected[group].add(value);
    }

    return projected;
  },
  /* === END BLOCK: ACTIVITIES_FINDER_PROJECTED_COUNT_NARROWING_V1 === */

  countProjectedMatches(group, value) {
    if (!this.isKnownFilter(group, value)) return 0;

    const isActive = this.getActiveSet(group).has(value);
    const state = isActive ? this.activeFilters : this.createProjectedState(group, value);

    return this.offers.filter((offer) => {
      if (!this.matchesSearch(offer)) return false;
      return this.matchesFilterState(offer, state);
    }).length;
  },

  rankOffer(offer) {
    let score = 0;

    score += window.BEActivityHighlights?.getScoreAdjustment?.(offer, { mode: "activity" }) || 0;

    this.filterGroupOrder.forEach((group) => {
      const activeValues = this.getActiveValues(group);
      if (!activeValues.length) return;

      if (group === "seasonal") {
        if (window.BEActivityHighlights?.hasActiveHighlight?.(offer)) {
          score += 18;
        }
        return;
      }

      const values = new Set(this.getOfferFilterValues(offer, group));
      activeValues.forEach((value) => {
        if (values.has(value)) {
          if (group === "proximity") score += 18;
          else if (group === "features") score += 14;
          else if (group === "situation") score += 12;
          else if (group === "activity_type") score += 10;
          else if (group === "effort") score += 8;
        }
      });
    });

    if (!this.getActiveValues("proximity").length) {
      const proximity = new Set(this.getOfferFilterValues(offer, "proximity"));
      if (proximity.has("Direkt in Bocholt")) score += 6;
      else if (proximity.has("In der Umgebung")) score += 3;
    }

    const visibleValueCount = [
      ...(offer.tags || []),
      ...(offer.seasonal_highlights || []),
      ...(offer.cardFacts || []),
      ...this.filterGroupOrder.flatMap((group) => this.getOfferFilterValues(offer, group))
    ].filter(Boolean).length;

    score += Math.min(visibleValueCount, 8) * 0.25;
    return score;
  },

  /* === BEGIN BLOCK: ACTIVITIES_CARD_MATCH_CONTEXT_V1 | Zweck: gibt den Activity-Cards die aktuell passenden aktiven Filter als sichtbare Passungsbegründung mit; Umfang: erweitert Relevanzsortierung um Render-Kontext, ohne Filterlogik zu verändern === */
  sortByRelevance(offers) {
    return [...offers].sort((a, b) => {
      const scoreDelta = this.rankOffer(b) - this.rankOffer(a);
      if (scoreDelta !== 0) return scoreDelta;
      return (a.sortIndex || 0) - (b.sortIndex || 0);
    });
  },

  /* === BEGIN BLOCK: ACTIVITIES_CARD_MATCH_LABELS_ENTERPRISE_V1 | Zweck: übersetzt aktive Filterwerte in verständliche Card-Merkmale, damit Ergebnis-Cards ihre Passung für Nutzer nachvollziehbar erklären; Umfang: ersetzt nur getActiveMatchLabels() und ergänzt lokalen Label-Mapper === */
  getCardMatchLabel(offer, group, value) {
    if (group === "seasonal" && value === "Jetzt besonders") {
      const highlight = window.BEActivityHighlights?.getPrimaryHighlight?.(offer);
      return highlight?.shortLabel ? `Jetzt: ${highlight.shortLabel}` : "Jetzt besonders";
    }

    const normalizedTags = new Set(this.normalizeArray(offer?.tags));
    const normalizedFacts = new Set(this.normalizeArray(offer?.cardFacts));

    if (group === "situation" && value === "Mit Kindern") {
      if (normalizedTags.has("Familienfreundlich")) return "Familienfreundlich";
      return "Mit Kindern";
    }

    if (group === "situation" && value === "Draußen") {
      if (normalizedFacts.has("Outdoor")) return "Draußen";
      return "Draußen";
    }

    if (group === "situation" && value === "Bei Regen") {
      if (normalizedFacts.has("Indoor")) return "Bei Regen";
      return "Bei Regen";
    }

    if (group === "proximity" && value === "In der Umgebung") {
      return "Umgebung";
    }

    if (group === "proximity" && value === "Grenzregion / Niederlande") {
      return "Grenzregion";
    }

    if (group === "proximity" && value === "Direkt in Bocholt") {
      return "Bocholt";
    }

    return value;
  },

  getActiveMatchLabels(offer) {
    const orderedGroups = ["seasonal", "situation", "features", "proximity", "activity_type", "effort"];
    const labels = [];
    const seen = new Set();

    orderedGroups.forEach((group) => {
      const available = new Set(this.getOfferFilterValues(offer, group));
      this.getActiveValues(group).forEach((value) => {
        if (!available.has(value)) return;

        const label = this.getCardMatchLabel(offer, group, value);
        const key = String(label || "").trim().toLowerCase();
        if (!label || seen.has(key)) return;

        seen.add(key);
        labels.push(label);
      });
    });

    return labels.slice(0, 3);
  },
  /* === END BLOCK: ACTIVITIES_CARD_MATCH_LABELS_ENTERPRISE_V1 === */

  withRenderedMatchContext(offer) {
    const activeHighlight = window.BEActivityHighlights?.getPrimaryHighlight?.(offer) || null;

    return {
      ...offer,
      activeHighlight,
      activeMatchLabels: this.getActiveMatchLabels(offer)
    };
  },
  /* === END BLOCK: ACTIVITIES_CARD_MATCH_CONTEXT_V1 === */

  renderActiveFilterChips() {
    const { activeFilters, activeFilterList } = this.refs;
    if (!activeFilters || !activeFilterList) return;

    activeFilterList.innerHTML = "";
    const activeEntries = [];

    this.filterGroupOrder.forEach((group) => {
      this.getActiveValues(group).forEach((value) => {
        activeEntries.push({ group, value });
      });
    });

    activeFilters.hidden = activeEntries.length === 0;

    activeEntries.forEach(({ group, value }) => {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "activity-active-chip";
      button.setAttribute("data-active-filter-group", group);
      button.setAttribute("data-active-filter-value", value);
      button.setAttribute("aria-label", `Filter ${value} entfernen`);
      button.textContent = `${value} ×`;
      activeFilterList.appendChild(button);
    });
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_BUTTON_STATE_ENTERPRISE_COUNT_CONTRACT | Zweck: trennt aktive Zustände und Auswahlprognosen eindeutig: aktive Chips zeigen nur Label + CSS-X, inaktive Chips zeigen Label + projizierte Trefferzahl; Umfang: ersetzt nur updateFilterButtonStates() === */
  updateFilterButtonStates() {
    this.getFilterButtons().forEach((button) => {
      const group = String(button.getAttribute("data-filter-group") || "").trim();
      const value = String(button.getAttribute("data-filter-value") || "").trim();
      if (!this.isKnownFilter(group, value)) return;

      const baseLabel = String(button.dataset.baseLabel || button.textContent || value)
        .trim()
        .replace(/\s*\(\d+\)\s*$/, "");
      button.dataset.baseLabel = baseLabel;

      const isActive = this.getActiveSet(group).has(value);
      const isExclusiveBlocked = this.isBlockedByActiveExclusiveGroup(group, value);
      const count = this.countProjectedMatches(group, value);
      const disabled = !isActive && (isExclusiveBlocked || count === 0);
      if (group === "seasonal") {
        button.hidden = !isActive && count === 0;
      } else {
        button.hidden = false;
      }
      const countLabel = count === 1 ? "1 Aktivität" : `${count} Aktivitäten`;

      button.textContent = isActive ? baseLabel : `${baseLabel} (${count})`;
      button.classList.toggle("is-active", isActive);
      button.classList.toggle("is-disabled", disabled);
      button.classList.toggle("is-exclusive-blocked", isExclusiveBlocked);
      button.disabled = disabled;
      button.setAttribute("aria-pressed", isActive ? "true" : "false");
      button.setAttribute("aria-disabled", disabled ? "true" : "false");
      button.setAttribute(
        "aria-label",
        isActive
          ? `Filter ${baseLabel} entfernen`
          : `Filter ${baseLabel} aktivieren, danach ${countLabel}`
      );
    });
  },
  /* === END BLOCK: ACTIVITIES_FINDER_BUTTON_STATE_ENTERPRISE_COUNT_CONTRACT === */

  updateResultCount() {
    const { resultCount } = this.refs;
    if (!resultCount) return;

    const count = this.filteredOffers.length;
    if (count === 0) {
      resultCount.textContent = "Keine Aktivitäten gefunden";
      return;
    }

    resultCount.textContent = count === 1
      ? "1 Aktivität gefunden"
      : `${count} Aktivitäten gefunden`;
  },

  /* === BEGIN BLOCK: ACTIVITIES_FINDER_SEARCH_CLEAR_AND_FILTER_COUNT_STATE_V5 | Zweck: synchronisiert Desktop- und Mobile-Filtertoggle gemeinsam; Mobile zeigt nur die Zahl, Desktop zeigt „x aktiv“, Summary bleibt ohne doppelte Trefferzeile; Umfang: ersetzt nur updateFinderUI() === */
  updateFinderUI() {
    const activeCount = this.getActiveFilterCount();
    const hasActiveFilters = this.hasActiveFilters();
    const hasActiveFilterValues = activeCount > 0;
    const hasSearchTerm = String(this.searchTerm || "").trim().length > 0;
    const isAdvancedOpen = !!(this.refs.advancedPanel && !this.refs.advancedPanel.hidden);

    if (this.refs.finder) {
      this.refs.finder.classList.toggle("has-active-filters", hasActiveFilterValues);
      this.refs.finder.classList.toggle("has-search-term", hasSearchTerm);
      this.refs.finder.classList.toggle("is-advanced-open", isAdvancedOpen);
    }

    if (this.refs.searchClear) {
      this.refs.searchClear.hidden = !hasSearchTerm;
    }

    if (this.refs.resetPill) {
      this.refs.resetPill.hidden = !hasActiveFilters;
    }

    if (this.refs.searchRow) {
      this.refs.searchRow.classList.toggle("has-active-filter-reset", hasActiveFilters);
    }

    const toggles = Array.isArray(this.refs.advancedToggles) ? this.refs.advancedToggles : [];
    toggles.forEach((toggle) => {
      const isMobileToggle = toggle.classList.contains("activity-finder__mobile-toggle");
      const countEl = toggle.querySelector(".filter-pill__value");

      toggle.classList.toggle("has-active-filter-count", hasActiveFilterValues);
      toggle.dataset.activeCount = hasActiveFilterValues ? String(activeCount) : "";
      toggle.setAttribute("aria-expanded", isAdvancedOpen ? "true" : "false");
      toggle.classList.toggle("is-active", isAdvancedOpen);
      toggle.setAttribute(
        "aria-label",
        hasActiveFilterValues
          ? `Weitere Filter, ${activeCount} Filter aktiv`
          : "Weitere Filter"
      );

      if (countEl) {
        countEl.textContent = hasActiveFilterValues
          ? (isMobileToggle ? String(activeCount) : `${activeCount} aktiv`)
          : "";
      }
    });

    if (this.refs.advancedSummary) {
      if (hasActiveFilters) {
        this.refs.advancedSummary.removeAttribute("hidden");
      } else {
        this.refs.advancedSummary.setAttribute("hidden", "");
      }
    }

    if (this.refs.advancedReset) {
      this.refs.advancedReset.hidden = !hasActiveFilters;
    }

    if (this.refs.advancedSummaryText) {
      if (hasActiveFilterValues) {
        this.refs.advancedSummaryText.textContent = activeCount === 1
          ? "1 Filter aktiv"
          : `${activeCount} Filter aktiv`;
      } else if (hasSearchTerm) {
        this.refs.advancedSummaryText.textContent = "Suche aktiv";
      } else {
        this.refs.advancedSummaryText.textContent = "";
      }
    }

    this.updateResultCount();
    this.updateFilterButtonStates();
    this.renderActiveFilterChips();
  },
  /* === END BLOCK: ACTIVITIES_FINDER_SEARCH_CLEAR_AND_FILTER_COUNT_STATE_V5 === */

  applyFilterAndRender() {
    this.filteredOffers = this.sortByRelevance(this.offers.filter((offer) => {
      if (!this.matchesSearch(offer)) return false;
      return this.matchesFilterState(offer);
    }));

    if (typeof window.OfferCards?.render !== "function") {
      console.error("OfferCards.render missing");
      this.showError("Aktivitäten konnten nicht angezeigt werden.");
      return;
    }

    this.updateFinderUI();
    this.showLoading(false);
    window.OfferCards.render(this.filteredOffers.map((offer) => this.withRenderedMatchContext(offer)));
  },
  /* === END BLOCK: ACTIVITIES_SEMANTIC_FINDER_OWNER_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_SHOWLOADING_A11Y_V1 | Zweck: Loading-Overlay analog zur Event-Seite mit aria-busy steuern | Umfang: ersetzt nur showLoading(show) === */
  showLoading(show) {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;

    loadingEl.setAttribute("aria-busy", show ? "true" : "false");
    loadingEl.style.display = show ? "flex" : "none";
  },
  /* === END BLOCK: ACTIVITIES_SHOWLOADING_A11Y_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_NO_OFFERS_A11Y_V1 | Zweck: angleicht den No-Data-Status an die Event-Seite an und beendet aria-busy sauber | Umfang: ersetzt nur showNoOffers() === */
  showNoOffers() {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;

    loadingEl.innerHTML = `
      <div class="info-message">
        <p>📭 Aktuell sind noch keine Aktivitäten hinterlegt.</p>
        <p><small>Bald findest du hier mehr Freizeitideen für Bocholt und Umgebung.</small></p>
      </div>
    `.trim();

    loadingEl.setAttribute("aria-busy", "false");
    loadingEl.style.display = "flex";
  },
  /* === END BLOCK: ACTIVITIES_NO_OFFERS_A11Y_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_ERROR_STATE_RETRY_V1 | Zweck: gleicht Error-State der Aktivitäten-Seite an Events an, inkl. Retry-CTA | Umfang: ersetzt nur showError(message) === */
  showError(message) {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;

    loadingEl.innerHTML = `
      <div class="error-message" role="alert">
        <p>⚠️ ${message}</p>
        <button type="button" class="empty-state__btn" id="offer-error-retry-btn">Erneut versuchen</button>
      </div>
    `.trim();

    loadingEl.setAttribute("aria-busy", "false");
    loadingEl.style.display = "flex";

    const btn = document.getElementById("offer-error-retry-btn");
    if (btn) {
      btn.addEventListener("click", () => location.reload());
    }
  },
  /* === END BLOCK: ACTIVITIES_ERROR_STATE_RETRY_V1 === */

  escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (ch) => {
      switch (ch) {
        case "&": return "&amp;";
        case "<": return "&lt;";
        case ">": return "&gt;";
        case '"': return "&quot;";
        case "'": return "&#39;";
        default: return ch;
      }
    });
  },

  escapeHtmlAttr(value) {
    return this.escapeHtml(value);
  }
};

document.addEventListener("DOMContentLoaded", () => {
  OffersApp.init();
});

debugLog?.("Activities main module loaded - waiting for DOM ready");
