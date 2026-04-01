// BEGIN: FILE_HEADER_OFFERS_MAIN
// Datei: js/offers-main.js
// Zweck:
// - Bootstrapping für Aktivitäten-Seite (/angebote/)
// - Lädt /data/offers.json, normalisiert Aktivitätsdaten und rendert den Feed
// - Bindet Suche + 2 Primärfilter (Situation + Bereich)
//
// Verantwortlich für:
// - Daten laden + Fehlerbehandlung
// - Normalisierung
// - Such-/Filter-State
// - Render-Aufruf an window.OfferCards
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

  async init() {
    debugLog?.("=== ACTIVITIES - APP START ===");
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
      this.applyFilterAndRender();
      this.showLoading(false);

      debugLog?.(`=== ACTIVITIES READY - ${this.offers.length} activities loaded ===`);
    } catch (error) {
      console.error("Activities initialization failed:", error);
      this.showError("Fehler beim Laden der Aktivitäten. Bitte Seite neu laden.");
    }
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
    const searchInput = document.getElementById("activity-search-filter");
    const situationSelect = document.getElementById("offer-situation");
    const categorySelect = document.getElementById("offer-category");

    if (searchInput) {
      searchInput.addEventListener("input", () => {
        this.searchTerm = String(searchInput.value || "").trim().toLowerCase();
        this.applyFilterAndRender();
      });
    }

    if (situationSelect) {
      situationSelect.addEventListener("change", () => {
        this.activeSituation = String(situationSelect.value || "").trim();
        this.applyFilterAndRender();
      });
    }

    if (categorySelect) {
      categorySelect.addEventListener("change", () => {
        this.activeCategory = String(categorySelect.value || "").trim();
        this.applyFilterAndRender();
      });
    }
  },

  populateSituationOptions() {
    const select = document.getElementById("offer-situation");
    if (!select) return;

    while (select.options.length > 1) select.remove(1);

    const situations = Array.from(
      new Set(this.offers.flatMap((offer) => offer.tags || []).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b, "de"));

    for (const situation of situations) {
      const option = document.createElement("option");
      option.value = situation;
      option.textContent = situation;
      select.appendChild(option);
    }
  },

  populateCategoryOptions() {
    const select = document.getElementById("offer-category");
    if (!select) return;

    while (select.options.length > 1) select.remove(1);

    const categories = Array.from(
      new Set(this.offers.map((offer) => offer.kategorie).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b, "de"));

    for (const category of categories) {
      const option = document.createElement("option");
      option.value = category;
      option.textContent = category;
      select.appendChild(option);
    }
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
  }
};

document.addEventListener("DOMContentLoaded", () => {
  OffersApp.init();
});

debugLog?.("Activities main module loaded - waiting for DOM ready");
