// BEGIN: FILE_HEADER_OFFERS_MAIN
// Datei: js/offers-main.js
// Zweck:
// - Bootstrapping für Aktivitäten-Seite (/angebote/)
// - Lädt /data/offers.json, normalisiert Aktivitätsdaten und rendert den Feed
// - Bindet Suche + 2 Primärfilter im gleichen Mobile-Sheet-/Desktop-Popover-Modell wie die Event-Seite
// END: FILE_HEADER_OFFERS_MAIN

const OffersApp = {
  offers: [],
  filteredOffers: [],
  searchTerm: "",
  activeSituation: "",
  activeCategory: "",
  activeDesktopPopover: "",

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
    situationSheetOptions: null,
    categorySheetOptions: null,
    situationPopover: null,
    categoryPopover: null,
    situationPopoverOptions: null,
    categoryPopoverOptions: null
  },

  isDesktopViewport() {
    return window.matchMedia("(min-width: 900px)").matches;
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
    this.refs.situationSheetOptions = document.getElementById("sheet-situation-options");
    this.refs.categorySheetOptions = document.getElementById("sheet-category-options");
    this.refs.situationPopover = document.getElementById("popover-situation");
    this.refs.categoryPopover = document.getElementById("popover-offer-category");
    this.refs.situationPopoverOptions = document.getElementById("popover-situation-options");
    this.refs.categoryPopoverOptions = document.getElementById("popover-offer-category-options");
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
      situationSheetOptions,
      categorySheetOptions,
      situationPopoverOptions,
      categoryPopoverOptions
    } = this.refs;

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        this.searchTerm = String(searchInput.value || "").trim().toLowerCase();
        this.applyFilterAndRender();
      });
    }

    if (situationPill) {
      situationPill.addEventListener("click", (event) => {
        event.preventDefault();
        if (this.isDesktopViewport()) {
          this.toggleDesktopPopover("situation");
          return;
        }
        this.toggleSheet("situation");
      });
    }

    if (categoryPill) {
      categoryPill.addEventListener("click", (event) => {
        event.preventDefault();
        if (this.isDesktopViewport()) {
          this.toggleDesktopPopover("category");
          return;
        }
        this.toggleSheet("category");
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

    this.bindOptionContainer(situationSheetOptions, "data-situation", (value) => {
      this.activeSituation = value;
      this.syncSituationSelection();
      this.closeSheet(this.refs.situationSheet);
      this.closeAllDesktopPopovers();
      this.applyFilterAndRender();
    });

    this.bindOptionContainer(categorySheetOptions, "data-category", (value) => {
      this.activeCategory = value;
      this.syncCategorySelection();
      this.closeSheet(this.refs.categorySheet);
      this.closeAllDesktopPopovers();
      this.applyFilterAndRender();
    });

    this.bindOptionContainer(situationPopoverOptions, "data-situation", (value) => {
      this.activeSituation = value;
      this.syncSituationSelection();
      this.closeAllDesktopPopovers();
      this.applyFilterAndRender();
    });

    this.bindOptionContainer(categoryPopoverOptions, "data-category", (value) => {
      this.activeCategory = value;
      this.syncCategorySelection();
      this.closeAllDesktopPopovers();
      this.applyFilterAndRender();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key !== "Escape") return;
      this.closeAllTransientUI();
    });

    document.addEventListener("click", (event) => {
      if (!this.isDesktopViewport()) return;

      const insidePill = event.target.closest("#offer-situation-pill, #offer-category-pill");
      const insidePopover = event.target.closest("#popover-situation, #popover-offer-category");
      if (insidePill || insidePopover) return;

      this.closeAllDesktopPopovers();
    });

    window.addEventListener("resize", () => {
      if (!this.isDesktopViewport()) {
        this.closeAllDesktopPopovers();
      } else {
        this.repositionOpenDesktopPopover();
      }
      this.updateFilterBarUI();
    }, { passive: true });

    window.addEventListener("scroll", () => {
      this.repositionOpenDesktopPopover();
    }, { passive: true });
  },

  bindOptionContainer(container, attrName, onSelect) {
    if (!container) return;

    container.addEventListener("click", (event) => {
      const button = event.target.closest(`[${attrName}]`);
      if (!button) return;
      onSelect(String(button.getAttribute(attrName) || "").trim());
    });
  },

  getCategoryDisplayLabel(rawValue) {
    if (window.OfferVisuals?.getCategoryPresentation) {
      return window.OfferVisuals.getCategoryPresentation(rawValue).label;
    }
    return String(rawValue || "").trim();
  },

  populateButtons(targets, attrName, entries) {
    const markup = [
      `<button type="button" class="filter-sheet-option is-active" ${attrName}="">Alle</button>`,
      ...entries.map(({ value, label }) => (
        `<button type="button" class="filter-sheet-option" ${attrName}="${this.escapeHtmlAttr(value)}">${this.escapeHtml(label)}</button>`
      ))
    ].join("");

    targets.forEach((target) => {
      if (target) target.innerHTML = markup;
    });
  },

  populateSituationOptions() {
    const situations = Array.from(
      new Set(this.offers.flatMap((offer) => offer.tags || []).filter(Boolean))
    )
      .sort((a, b) => a.localeCompare(b, "de"))
      .map((value) => ({ value, label: value }));

    this.populateButtons(
      [this.refs.situationSheetOptions, this.refs.situationPopoverOptions],
      "data-situation",
      situations
    );
  },

  populateCategoryOptions() {
    const categories = Array.from(
      new Set(this.offers.map((offer) => offer.kategorie).filter(Boolean))
    )
      .map((value) => ({ value, label: this.getCategoryDisplayLabel(value) }))
      .sort((a, b) => a.label.localeCompare(b.label, "de"));

    this.populateButtons(
      [this.refs.categorySheetOptions, this.refs.categoryPopoverOptions],
      "data-category",
      categories
    );
  },

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

  toggleSheet(key) {
    const isSituation = key === "situation";
    const current = isSituation ? this.refs.situationSheet : this.refs.categorySheet;
    const other = isSituation ? this.refs.categorySheet : this.refs.situationSheet;
    if (!current) return;

    if (!current.hidden) {
      this.closeSheet(current);
      return;
    }

    this.closeSheet(other);
    this.openSheet(current);
  },

  getDesktopPopoverRefs(key) {
    if (key === "situation") {
      return {
        pill: this.refs.situationPill,
        popover: this.refs.situationPopover
      };
    }

    return {
      pill: this.refs.categoryPill,
      popover: this.refs.categoryPopover
    };
  },

  positionDesktopPopover(popover, pill) {
    if (!popover || !pill || popover.hidden) return;

    const panel = popover.querySelector(".filter-popover__panel");
    if (!panel) return;

    const previousVisibility = popover.style.visibility;
    popover.style.visibility = "hidden";
    popover.style.left = "0px";
    popover.style.top = "0px";

    const pillRect = pill.getBoundingClientRect();
    const panelRect = popover.getBoundingClientRect();
    const popoverWidth = Math.max(panelRect.width, popover.offsetWidth, 240);
    const popoverHeight = Math.max(panel.scrollHeight, panelRect.height, 120);

    const viewportLeft = window.scrollX + 16;
    const viewportRight = window.scrollX + window.innerWidth - 16;
    const viewportTop = window.scrollY + 16;
    const viewportBottom = window.scrollY + window.innerHeight - 16;

    let left = pillRect.left + window.scrollX;
    if (left + popoverWidth > viewportRight) {
      left = viewportRight - popoverWidth;
    }
    if (left < viewportLeft) {
      left = viewportLeft;
    }

    let top = pillRect.bottom + window.scrollY + 8;
    let side = "bottom";

    if (top + popoverHeight > viewportBottom) {
      const topCandidate = pillRect.top + window.scrollY - popoverHeight - 8;
      if (topCandidate >= viewportTop) {
        top = topCandidate;
        side = "top";
      } else {
        top = Math.max(viewportTop, viewportBottom - popoverHeight);
      }
    }

    popover.style.left = `${Math.round(left)}px`;
    popover.style.top = `${Math.round(top)}px`;
    popover.dataset.side = side;
    popover.style.visibility = previousVisibility;
  },

  setPillExpanded(pill, isExpanded) {
    if (!pill) return;
    pill.setAttribute("aria-expanded", isExpanded ? "true" : "false");
  },

  openDesktopPopover(key) {
    const { pill, popover } = this.getDesktopPopoverRefs(key);
    if (!pill || !popover) return;

    this.closeAllDesktopPopovers();
    popover.hidden = false;
    this.positionDesktopPopover(popover, pill);
    this.setPillExpanded(pill, true);
    this.activeDesktopPopover = key;
  },

  closeDesktopPopover(key) {
    const { pill, popover } = this.getDesktopPopoverRefs(key);
    if (popover) {
      popover.hidden = true;
      popover.style.left = "";
      popover.style.top = "";
      popover.style.visibility = "";
      popover.dataset.side = "bottom";
    }
    this.setPillExpanded(pill, false);
    if (this.activeDesktopPopover === key) {
      this.activeDesktopPopover = "";
    }
  },

  toggleDesktopPopover(key) {
    if (this.activeDesktopPopover === key) {
      this.closeDesktopPopover(key);
      return;
    }
    this.openDesktopPopover(key);
  },

  repositionOpenDesktopPopover() {
    if (!this.activeDesktopPopover || !this.isDesktopViewport()) return;
    const { pill, popover } = this.getDesktopPopoverRefs(this.activeDesktopPopover);
    if (!pill || !popover || popover.hidden) return;
    this.positionDesktopPopover(popover, pill);
  },

  closeAllDesktopPopovers() {
    this.closeDesktopPopover("situation");
    this.closeDesktopPopover("category");
  },

  closeAllTransientUI() {
    this.closeSheet(this.refs.situationSheet);
    this.closeSheet(this.refs.categorySheet);
    this.closeAllDesktopPopovers();
  },

  setActiveOption(container, attrName, activeValue) {
    if (!container) return;

    container.querySelectorAll(`.filter-sheet-option[${attrName}]`).forEach((button) => {
      const value = String(button.getAttribute(attrName) || "").trim();
      button.classList.toggle("is-active", value === activeValue);
    });
  },

  syncSituationSelection() {
    this.setActiveOption(this.refs.situationSheetOptions, "data-situation", this.activeSituation);
    this.setActiveOption(this.refs.situationPopoverOptions, "data-situation", this.activeSituation);
  },

  syncCategorySelection() {
    this.setActiveOption(this.refs.categorySheetOptions, "data-category", this.activeCategory);
    this.setActiveOption(this.refs.categoryPopoverOptions, "data-category", this.activeCategory);
  },

  updateFilterBarUI() {
    const {
      searchRow,
      situationValue,
      categoryValue,
      resetPill,
      situationPill,
      categoryPill
    } = this.refs;
    const hasActiveFilters = !!(this.searchTerm || this.activeSituation || this.activeCategory);

    if (situationValue) {
      situationValue.textContent = this.activeSituation || "Alle";
    }

    if (categoryValue) {
      categoryValue.textContent = this.activeCategory ? this.getCategoryDisplayLabel(this.activeCategory) : "Alle";
    }

    if (resetPill) {
      resetPill.hidden = !hasActiveFilters;
    }

    if (searchRow) {
      searchRow.classList.toggle("has-active-filter-reset", hasActiveFilters);
    }

    if (situationPill) {
      situationPill.classList.toggle("is-active", !!this.activeSituation);
    }

    if (categoryPill) {
      categoryPill.classList.toggle("is-active", !!this.activeCategory);
    }
  },

  resetFilters() {
    this.searchTerm = "";
    this.activeSituation = "";
    this.activeCategory = "";

    if (this.refs.searchInput) {
      this.refs.searchInput.value = "";
    }

    this.syncSituationSelection();
    this.syncCategorySelection();
    this.closeAllTransientUI();
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
