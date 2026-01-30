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

  async init() {
    debugLog?.("=== OFFERS - APP START ===");

    this.showLoading(true);

    try {
      /* === BEGIN BLOCK: OFFERS FETCH + NORMALIZE (robust, canonical fields) ===
Zweck: Sauberes Error-Handling bei offers.json + Normalisierung auf kanonische Felder,
       damit OfferCards stabil mit { title, category, location, hint, url } arbeiten.
       Unterstützt beide JSON-Formate:
       (A) Array-Root: [ {...}, {...} ]
       (B) Objekt-Root: { offers: [ {...}, {...} ] }
Umfang: Gesamter fetch/parse/normalize Block in OffersApp.init().
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

        const title = (obj.title ?? obj.name ?? "").toString().trim();
        const category = (obj.category ?? obj.kategorie ?? "").toString().trim();
        const location = (obj.location ?? obj.ort ?? "").toString().trim();
        const hint = (obj.hint ?? obj.zeit ?? obj.note ?? "").toString().trim();
        const url = (obj.url ?? obj.link ?? "").toString().trim();

        return {
          ...obj,
          title,
          category,
          location,
          hint,
          url
        };
      };

      this.offers = rawOffers.map(normalizeOffer);
      /* === END BLOCK: OFFERS FETCH + NORMALIZE (robust, canonical fields) === */

      if (this.offers.length === 0) {
        this.showNoOffers();
        return;
      }

      // Rendering
      if (typeof window.OfferCards?.render !== "function") {
        console.error("❌ OfferCards.render missing – js/offers.js not loaded or has an error.");
        this.showError("Angebote konnten nicht angezeigt werden (Render-Modul fehlt).");
        return;
      }

      OfferCards.render(this.offers);

      this.showLoading(false);

      debugLog?.(`=== OFFERS READY - ${this.offers.length} offers loaded ===`);
    } catch (error) {
      console.error("Offers initialization failed:", error);
      this.showError("Fehler beim Laden der Angebote. Bitte Seite neu laden.");
    }
  },

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
