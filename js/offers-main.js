// BEGIN: FILE_HEADER_OFFERS_MAIN
// Datei: js/offers-main.js
// Zweck:
// - Bootstrapping für Angebote-Seite
// - Lädt Angebots-Daten (Fetch) und hält die vollständige Offer-Liste
// - Übergibt normalisierte Daten an OfferCards.render (Rendering liegt in js/offers.js)
//
// Verantwortlich für:
// - Daten laden + Fehlerbehandlung (Loading/Empty/Error UI)
// - Normalisierung auf kanonische Felder (title/category/location/hint/url)
//
// Nicht verantwortlich für:
// - Card-Layout (liegt in js/offers.js)
// - Filter/Details (derzeit nicht Teil von Angebote)
//
// Contract:
// - benutzt /data/offers.json als Datenquelle (no-store)
// - erwartet #loading im DOM (wie Startseite)
// END: FILE_HEADER_OFFERS_MAIN


const OffersApp = {
  offers: [],
  filteredOffers: [],
  activeCategory: "",
  activeTag: "",

  async init() {
    debugLog?.("=== OFFERS - APP START ===");

    this.showLoading(true);

    try {
      /* === BEGIN BLOCK: OFFERS FETCH + NORMALIZE (events-consistent, required fields) ===
Zweck:
- Lädt /data/offers.json (no-store)
- Unterstützt beide Root-Formate:
  (A) Array-Root: [ {...}, {...} ]
  (B) Objekt-Root: { offers: [ {...}, {...} ] }
- Normalisiert Felder konsistent zu Events:
  - kategorie
  - description Pflicht
  - url Pflicht
  - tags optional (Array)
Umfang: Gesamter fetch/parse/normalize/validation Block in OffersApp.init().
=== */
      const response = await fetch("/data/offers.json", { cache: "no-store" });

      if (!response.ok) {
        throw new Error(`offers.json load failed: ${response.status} ${response.statusText}`);
      }

      const data = await response.json();

      const rawOffers = Array.isArray(data)
        ? data
        : (Array.isArray(data?.offers) ? data.offers : []);

      const normalizeOffer = (o) => {
        const obj = o && typeof o === "object" ? o : {};

        const id = (obj.id ?? "").toString().trim();
        const title = (obj.title ?? obj.name ?? "").toString().trim();
        const kategorie = (obj.kategorie ?? obj.category ?? "").toString().trim();
        const location = (obj.location ?? obj.ort ?? "").toString().trim();
        const hint = (obj.hint ?? obj.zeit ?? obj.note ?? "").toString().trim();
        const description = (obj.description ?? "").toString().trim();
        const url = (obj.url ?? obj.link ?? "").toString().trim();

        const rawTags = Array.isArray(obj.tags) ? obj.tags : [];
        const tags = rawTags
          .map((t) => (t == null ? "" : String(t)).trim())
          .filter(Boolean);

        return {
          ...obj,
          id,
          title,
          kategorie,
          location,
          hint,
          description,
          url,
          tags
        };
      };

      this.offers = rawOffers.map(normalizeOffer);

      // Pflichtfelder prüfen (Fail-Fast)
      const invalid = this.offers.filter((o) => !o.title || !o.kategorie || !o.description || !o.url);
      if (invalid.length > 0) {
        console.error("Invalid offers (missing required fields):", invalid);
        this.showError("Angebote-Daten sind unvollständig (Pflichtfelder fehlen).");
        return;
      }
      /* === END BLOCK: OFFERS FETCH + NORMALIZE (events-consistent, required fields) === */

      if (this.offers.length === 0) {
        this.showNoOffers();
        return;
      }

      // Filter initialisieren
      this.bindFilters();
      this.populateCategoryOptions();
      this.populateTagOptions(); // initial (alle Tags)

      // Initial render (ohne Filter)
      this.applyFilterAndRender();

      this.showLoading(false);

      debugLog?.(`=== OFFERS READY - ${this.offers.length} offers loaded ===`);
    } catch (error) {
      console.error("Offers initialization failed:", error);
      this.showError("Fehler beim Laden der Angebote. Bitte Seite neu laden.");
    }
  },

  /* === BEGIN BLOCK: FILTER_BIND (category + tag) ===
Zweck:
- Kategorie/Tag Dropdowns binden
- Bei Kategorie-Wechsel: Tag-Optionen facettieren + ggf. ungültigen Tag resetten
- Re-render nach jeder Änderung
Umfang: Nur Filter-Logik.
=== */
  bindFilters() {
    const catSelect = document.getElementById("offer-category");
    const tagSelect = document.getElementById("offer-tag");

    if (catSelect) {
      catSelect.addEventListener("change", () => {
        this.activeCategory = (catSelect.value || "").toString();

        // Tag-Optionen abhängig von Kategorie aktualisieren
        this.populateTagOptions();

        // Falls aktiver Tag in dieser Kategorie nicht vorkommt → reset
        if (this.activeTag && !this.getAvailableTagsForCurrentCategory().includes(this.activeTag)) {
          this.activeTag = "";
          if (tagSelect) tagSelect.value = "";
        }

        this.applyFilterAndRender();
      });
    }

    if (tagSelect) {
      tagSelect.addEventListener("change", () => {
        this.activeTag = (tagSelect.value || "").toString();
        this.applyFilterAndRender();
      });
    }
  },
  /* === END BLOCK: FILTER_BIND (category + tag) === */

  /* === BEGIN BLOCK: FILTER_OPTIONS (category) ===
Zweck: Kategorie-Dropdown aus Angeboten befüllen (einmal beim Init).
Umfang: Sortierte Unique-Liste.
=== */
  populateCategoryOptions() {
    const select = document.getElementById("offer-category");
    if (!select) return;

    while (select.options.length > 1) select.remove(1);

    const categories = Array.from(
      new Set(this.offers.map((o) => o.kategorie).filter(Boolean))
    ).sort((a, b) => a.localeCompare(b, "de"));

    for (const cat of categories) {
      const opt = document.createElement("option");
      opt.value = cat;
      opt.textContent = cat;
      select.appendChild(opt);
    }
  },
  /* === END BLOCK: FILTER_OPTIONS (category) === */

  /* === BEGIN BLOCK: FILTER_OPTIONS (tag, faceted) ===
Zweck:
- Aktivitäts-Dropdown aus offers[].tags befüllen
- Facettierung: Wenn Kategorie aktiv ist, nur Tags zeigen, die in dieser Kategorie vorkommen
Umfang: Sortierte Unique-Liste, inkl. "Alle".
=== */
  populateTagOptions() {
    const select = document.getElementById("offer-tag");
    if (!select) return;

    while (select.options.length > 1) select.remove(1);

    const tags = this.getAvailableTagsForCurrentCategory()
      .slice()
      .sort((a, b) => a.localeCompare(b, "de"));

    for (const tag of tags) {
      const opt = document.createElement("option");
      opt.value = tag;
      opt.textContent = tag;
      select.appendChild(opt);
    }
  },
  /* === END BLOCK: FILTER_OPTIONS (tag, faceted) === */

  /* === BEGIN BLOCK: TAGS_AVAILABLE (helper) ===
Zweck: Liefert die verfügbaren Tags abhängig von der aktiven Kategorie.
Umfang: Helper für Facettierung + Validierung.
=== */
  getAvailableTagsForCurrentCategory() {
    const cat = (this.activeCategory || "").trim();

    const base = !cat
      ? this.offers
      : this.offers.filter((o) => o.kategorie === cat);

    const all = [];
    for (const o of base) {
      if (Array.isArray(o.tags)) {
        for (const t of o.tags) all.push(t);
      }
    }

    return Array.from(new Set(all.filter(Boolean)));
  },
  /* === END BLOCK: TAGS_AVAILABLE (helper) === */

  /* === BEGIN BLOCK: FILTER_APPLY + RENDER (AND) ===
Zweck:
- Filter anwenden und OfferCards rendern
- Logik: Kategorie AND Tag (wenn gesetzt)
Umfang: Nur Filter + Render.
=== */
  applyFilterAndRender() {
    const cat = (this.activeCategory || "").trim();
    const tag = (this.activeTag || "").trim();

    this.filteredOffers = this.offers.filter((o) => {
      if (cat && o.kategorie !== cat) return false;
      if (tag && !(Array.isArray(o.tags) && o.tags.includes(tag))) return false;
      return true;
    });

    if (typeof window.OfferCards?.render !== "function") {
      console.error("❌ OfferCards.render missing – js/offers.js not loaded or has an error.");
      this.showError("Angebote konnten nicht angezeigt werden (Render-Modul fehlt).");
      return;
    }

    OfferCards.render(this.filteredOffers);
  },
  /* === END BLOCK: FILTER_APPLY + RENDER (AND) === */

  showLoading(show) {
    const loadingEl = document.getElementById("loading");
    if (loadingEl) {
      loadingEl.style.display = show ? "flex" : "none";
    }
  },

  showNoOffers() {
    const loadingEl = document.getElementById("loading");
    if (loadingEl) {
      loadingEl.innerHTML = `
        <div class="info-message">
          <p>📭 Aktuell sind keine Angebote verfügbar.</p>
          <p><small>Bald findest du hier passende Angebote für Bocholt.</small></p>
        </div>
      `;
      loadingEl.style.display = "flex";
    }
  },

  showError(message) {
    const loadingEl = document.getElementById("loading");
    if (loadingEl) {
      loadingEl.innerHTML = `
        <div class="error-message">
          <p>⚠️ ${message}</p>
        </div>
      `;
      loadingEl.style.display = "flex";
    }
  }
};



// App starten sobald DOM ready
/* === BEGIN BLOCK: OFFERS APP BOOTSTRAP (DOM first, deterministic) ===
Zweck: Angebote erst nach DOMReady initialisieren.
Umfang: Start-Hook am Dateiende.
=== */
document.addEventListener("DOMContentLoaded", () => {
  OffersApp.init();
});
/* === END BLOCK: OFFERS APP BOOTSTRAP (DOM first, deterministic) === */


debugLog?.("Offers main module loaded - waiting for DOM ready");
