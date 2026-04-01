// BEGIN: FILE_HEADER_OFFERS_MAIN
// Datei: js/offers-main.js
// Zweck:
// - Bootstrapping für Aktivitäten-Seite (/angebote/)
// - Lädt /data/offers.json, normalisiert Aktivitätsdaten und rendert den Feed
// - Bindet Suche + 2 Primärfilter (Situation + Bereich) im gleichen Pill-/Sheet-Modell wie die Event-Seite
//
// Verantwortlich für:
// - Daten laden + Fehlerbehandlung
// - Normalisierung
// - Such-/Filter-State
// - Render-Aufruf an window.OfferCards
// - Activity Filter Pills + Sheets
//
// Nicht verantwortlich für:
// - Card-Markup (js/offers.js)
// - Detailpanel-Markup (js/offers-details.js)
// END: FILE_HEADER_OFFERS_MAIN

const OffersApp = {
  offers: [],
  filteredOffers: [],
  searchTerm: "",
  activeSituation: "",
  activeCategory: "",

  refs: {
    searchInput: null,
    searchRow: null,
    situationPill: null,
    categoryPill: null,
    resetPill: null,
    situationValue: null,
    categoryValue: null,
    situationSheet: null,
    categorySheet: null,
    situationOptions: null,
    categoryOptions: null
  },

  async init() {
    debugLog?.("=== ACTIVITIES - APP START ===");
    this.cacheRefs();
    this.showLoading(true);

    try {
      const response = await fetch("/data/offers.json", { cache: "no-store" });
      if (!response.ok) {
        throw new Error(`offers.json load failed: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();
      const rawOffers = Array.isArray(data) ? data : (Array.isArray(data?.offers) ? data.offers : []);

      this.offers = rawOffers
        .map(this.normalizeOffer)
        .filter(Boolean);

      if (!this.offers.length) {
        this.showNoOffers();
        return;
      }

      this.bindControls();
      this.populateSituationOptions();
      this.populateCategoryOptions();
      this.updateFilterBarUI();
      this.applyFilterAndRender();
      this.showLoading(false);

      debugLog?.(`=== ACTIVITIES READY - ${this.offers.length} activities loaded ===`);
    } catch (error) {
      console.error("Activities initialization failed:", error);
      this.showError("Fehler beim Laden der Aktivitäten. Bitte Seite neu laden.");
    }
  },

  cacheRefs() {
    this.refs.searchInput = document.getElementById("search-filter");
    this.refs.searchRow = document.querySelector(".desktop-hero__search-row");
    this.refs.situationPill = document.getElementById("offer-situation-pill");
    this.refs.categoryPill = document.getElementById("offer-category-pill");
    this.refs.resetPill = document.getElementById("offer-reset-pill");
    this.refs.situationValue = document.getElementById("offer-situation-value");
    this.refs.categoryValue = document.getElementById("offer-category-value");
    this.refs.situationSheet = document.getElementById("sheet-situation");
    this.refs.categorySheet = document.getElementById("sheet-category");
    this.refs.situationOptions = document.getElementById("sheet-situation-options");
    this.refs.categoryOptions = document.getElementById("sheet-category-options");
  },

  normalizeOffer(raw) {
    const obj = raw && typeof raw === "object" ? raw : {};
    const id = String(obj.id || "").trim();
    const title = String(obj.title || "").trim();
    const kategorie = String(obj.kategorie || obj.category || "").trim();
    const location = String(obj.location || obj.place_name || obj.ort || "").trim();
    const description = String(obj.description || "").trim();
    const url = String(obj.url || obj.link || "").trim();

    if (!id || !title || !kategorie || !location || !description || !url) {
      return null;
    }

    const normalizeArray = (value) =>
      Array.isArray(value)
        ? value.map((item) => String(item || "").trim()).filter(Boolean)
        : [];

    return {
      id,
      title,
      kategorie,
      location,
      description,
      url,
      tags: normalizeArray(obj.tags),
      audience: normalizeArray(obj.audience),
      image: String(obj.image || "").trim(),
      duration: String(obj.duration || "").trim(),
      mode: String(obj.mode || "").trim(),
      price: String(obj.price || "").trim(),
      area: String(obj.area || "").trim(),
      season: String(obj.season || "").trim(),
      hint: String(obj.hint || "").trim()
    };
  },

  bindControls() {
    const {
      searchInput,
      situationPill,
      categoryPill,
      resetPill,
      situationSheet,
      categorySheet,
      situationOptions,
      categoryOptions
    } = this.refs;

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        this.searchTerm = String(searchInput.value || "").trim().toLowerCase();
        this.applyFilterAndRender();
      });
    }

    if (situationPill && situationSheet) {
      situationPill.addEventListener("click", () => {
        if (!situationSheet.hidden) {
          this.closeSheet(situationSheet);
          return;
        }
        this.closeSheet(categorySheet);
        this.openSheet(situationSheet);
      });
    }

    if (categoryPill && categorySheet) {
      categoryPill.addEventListener("click", () => {
        if (!categorySheet.hidden) {
          this.closeSheet(categorySheet);
          return;
        }
        this.closeSheet(situationSheet);
        this.openSheet(categorySheet);
      });
    }

    if (resetPill) {
      resetPill.addEventListener("click", () => {
        this.resetFilters();
      });
    }

    [situationSheet, categorySheet].forEach((sheetEl) => {
      if (!sheetEl) return;

      sheetEl.addEventListener("click", (event) => {
        const closeTrigger = event.target.closest("[data-close-sheet]");
        if (closeTrigger) {
          this.closeSheet(sheetEl);
        }
      });
    });

    if (situationOptions) {
      situationOptions.addEventListener("click", (event) => {
        const button = event.target.closest("[data-situation]");
        if (!button) return;

        this.activeSituation = String(button.getAttribute("data-situation") || "").trim();
        this.setActiveOption(situationOptions, "data-situation", this.activeSituation);
        this.closeSheet(situationSheet);
        this.applyFilterAndRender();
      });
    }

    if (categoryOptions) {
      categoryOptions.addEventListener("click", (event) => {
        const button = event.target.closest("[data-category]");
        if (!button) return;

        this.activeCategory = String(button.getAttribute("data-category") || "").trim();
        this.setActiveOption(categoryOptions, "data-category", this.activeCategory);
        this.closeSheet(categorySheet);
        this.applyFilterAndRender();
      });
    }

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      this.closeSheet(situationSheet);
      this.closeSheet(categorySheet);
    });
  },

  populateSituationOptions() {
    const target = this.refs.situationOptions;
    if (!target) return;

    const situations = Array.from(
      new Set(this.offers.flatMap((offer) => offer.tags || []).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b, "de"));

    target.innerHTML = [
      `<button type="button" class="filter-sheet-option is-active" data-situation="">Alle</button>`,
      ...situations.map((situation) => (
        `<button type="button" class="filter-sheet-option" data-situation="${this.escapeHtmlAttr(situation)}">${this.escapeHtml(situation)}</button>`
      ))
    ].join("");
  },

  populateCategoryOptions() {
    const target = this.refs.categoryOptions;
    if (!target) return;

    const categories = Array.from(
      new Set(this.offers.map((offer) => offer.kategorie).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b, "de"));

    target.innerHTML = [
      `<button type="button" class="filter-sheet-option is-active" data-category="">Alle</button>`,
      ...categories.map((category) => (
        `<button type="button" class="filter-sheet-option" data-category="${this.escapeHtmlAttr(category)}">${this.escapeHtml(category)}</button>`
      ))
    ].join("");
  },

  // BEGIN: FILTER_SHEET_LOCK_HELPERS
  setSheetLock(isOpen) {
    document.documentElement.classList.toggle("is-sheet-open", isOpen);
    document.body.classList.toggle("is-sheet-open", isOpen);
  },

  openSheet(sheetEl) {
    if (!sheetEl) return;
    sheetEl.hidden = false;
    this.setSheetLock(true);
  },

  closeSheet(sheetEl) {
    if (!sheetEl) return;
    sheetEl.hidden = true;

    const { situationSheet, categorySheet } = this.refs;
    const allClosed =
      (!situationSheet || situationSheet.hidden) &&
      (!categorySheet || categorySheet.hidden);

    if (allClosed) {
      this.setSheetLock(false);
    }
  },
  // END: FILTER_SHEET_LOCK_HELPERS

  setActiveOption(container, attrName, activeValue) {
    if (!container) return;

    container.querySelectorAll(".filter-sheet-option").forEach((button) => {
      const value = String(button.getAttribute(attrName) || "").trim();
      button.classList.toggle("is-active", value === activeValue);
    });
  },

  // BEGIN: FILTER_BAR_STATE_SYNC
  updateFilterBarUI() {
    const { searchRow, situationValue, categoryValue, resetPill } = this.refs;
    const hasActiveFilters = !!(this.searchTerm || this.activeSituation || this.activeCategory);

    if (situationValue) {
      situationValue.textContent = this.activeSituation || "Alle";
    }

    if (categoryValue) {
      categoryValue.textContent = this.activeCategory || "Alle";
    }

    if (resetPill) {
      resetPill.hidden = !hasActiveFilters;
    }

    if (searchRow) {
      searchRow.classList.toggle("has-active-filter-reset", hasActiveFilters);
    }
  },
  // END: FILTER_BAR_STATE_SYNC

  resetFilters() {
    this.searchTerm = "";
    this.activeSituation = "";
    this.activeCategory = "";

    if (this.refs.searchInput) {
      this.refs.searchInput.value = "";
    }

    this.setActiveOption(this.refs.situationOptions, "data-situation", "");
    this.setActiveOption(this.refs.categoryOptions, "data-category", "");

    this.closeSheet(this.refs.situationSheet);
    this.closeSheet(this.refs.categorySheet);

    this.applyFilterAndRender();
  },

  matchesSearch(offer) {
    if (!this.searchTerm) return true;

    const haystack = [
      offer.title,
      offer.location,
      offer.description,
      offer.kategorie,
      offer.area,
      offer.duration,
      offer.mode,
      offer.price,
      offer.hint,
      ...(offer.tags || []),
      ...(offer.audience || [])
    ]
      .filter(Boolean)
      .join(" ")
      .toLowerCase();

    return haystack.includes(this.searchTerm);
  },

  applyFilterAndRender() {
    const situation = this.activeSituation;
    const category = this.activeCategory;

    this.filteredOffers = this.offers.filter((offer) => {
      if (situation && !(offer.tags || []).includes(situation)) return false;
      if (category && offer.kategorie !== category) return false;
      if (!this.matchesSearch(offer)) return false;
      return true;
    });

    if (typeof window.OfferCards?.render !== "function") {
      console.error("OfferCards.render missing");
      this.showError("Aktivitäten konnten nicht angezeigt werden.");
      return;
    }

    this.updateFilterBarUI();
    this.showLoading(false);
    window.OfferCards.render(this.filteredOffers);
  },

  showLoading(show) {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;
    loadingEl.style.display = show ? "flex" : "none";
  },

  showNoOffers() {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;

    loadingEl.innerHTML = `
      <div class="info-message">
        <p>📭 Aktuell sind noch keine Aktivitäten hinterlegt.</p>
        <p><small>Bald findest du hier mehr Freizeitideen für Bocholt und Umgebung.</small></p>
      </div>
    `.trim();
    loadingEl.style.display = "flex";
  },

  showError(message) {
    const loadingEl = document.getElementById("loading");
    if (!loadingEl) return;

    loadingEl.innerHTML = `
      <div class="error-message">
        <p>⚠️ ${message}</p>
      </div>
    `.trim();
    loadingEl.style.display = "flex";
  },

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
