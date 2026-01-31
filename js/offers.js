// BEGIN: FILE_HEADER_OFFERS
// Datei: js/offers.js
// Zweck: Angebots-Cards anzeigen (Rendering = Anzeigen)
// Verantwortlich für:
// - Aus einer Angebots-Liste DOM-Karten bauen und im Container anzeigen
// - Interaktion: Klick/Enter/Space → (optional) Link öffnen
//
// Nicht verantwortlich für:
// - Daten laden (liegt in js/offers-main.js)
// - Filter-State / Filter-UI (derzeit nicht Teil von Angebote)
//
// Contract:
// - bekommt bereits normalisierte Offers von js/offers-main.js
// - öffentliche API: OfferCards.render(offers)
// END: FILE_HEADER_OFFERS


const OfferCards = (() => {
  let container = null;

  /* === BEGIN BLOCK: HTML_ESCAPE (pure helper) ===
  Zweck: XSS-sicheres Escaping für Text, der via innerHTML gesetzt wird.
  Umfang: Lokaler Helper (kein this-Binding-Risiko).
  === */
  function escapeHtml(value) {
    const s = value == null ? "" : String(value);
    return s.replace(/[&<>"']/g, (ch) => {
      switch (ch) {
        case "&":
          return "&amp;";
        case "<":
          return "&lt;";
        case ">":
          return "&gt;";
        case '"':
          return "&quot;";
        case "'":
          return "&#39;";
        default:
          return ch;
      }
    });
  }
  /* === END BLOCK: HTML_ESCAPE (pure helper) === */

  /* === BEGIN BLOCK: CONTAINER_LOOKUP (offers) ===
  Zweck: Einheitliche Container-Auflösung für Angebotsseite.
  Umfang: Nutzt #offer-cards als Ziel.
  === */
  function ensureContainer() {
    if (!container) container = document.getElementById("offer-cards");
    return container;
  }
  /* === END BLOCK: CONTAINER_LOOKUP (offers) === */

    /* === BEGIN BLOCK: CATEGORY_ICON (optional, neutral) ===
  Zweck: Kategorie-Icon-Zuordnung für die finalen Hauptkategorien (rein dekorativ).
  Umfang: Feste Map; unbekannt => leer.
  === */
  function getCategoryIcon(category) {
    const c = (category || "").toString().trim().toLowerCase();
    if (!c) return "";

    const map = {
      baden: "🏊",
      natur: "🌿",
      familie: "👨‍👩‍👧‍👦",
      freizeit: "🎯",
      kultur: "🎭"
    };

    return map[c] || "";
  }
  /* === END BLOCK: CATEGORY_ICON (optional, neutral) === */


      /* === BEGIN BLOCK: CREATE_CARD (offers → event-card DNA) ===
  Zweck: Offer-Cards minimal nach PROJECT.md:
  - Titel
  - Hauptkategorie
  - Location
  - Kategorie-Icon
  Interaktion:
  - Klick/Enter/Space → Offer-Detailpanel (Hook wie Events; Panel folgt separat)
  Umfang: Erzeugt .event-card mit .event-title + .event-meta + .event-location.
  === */
  function createCard(offer) {
    const card = document.createElement("div");
    card.className = "event-card";
    card.tabIndex = 0;

    // A11y/UX
    const title = (offer?.title || "").toString().trim();
    card.setAttribute("role", "button");
    card.setAttribute("aria-label", `Angebot anzeigen: ${title || ""}`);

    const category = (offer?.kategorie || offer?.category || "").toString().trim();
    const location = (offer?.location || "").toString().trim();

    const icon = getCategoryIcon(category);
    if (icon) {
      const iconEl = document.createElement("span");
      iconEl.className = "event-category-icon";
      iconEl.setAttribute("role", "img");
      iconEl.setAttribute("aria-label", `Kategorie: ${category}`);
      iconEl.textContent = icon;
      card.appendChild(iconEl);
    }

    const titleEl = document.createElement("div");
    titleEl.className = "event-title";
    titleEl.textContent = title || "Ohne Titel";
    card.appendChild(titleEl);

    const metaEl = document.createElement("div");
    metaEl.className = "event-meta";
    metaEl.textContent = category || "";
    card.appendChild(metaEl);

    if (location) {
      const locWrap = document.createElement("div");
      locWrap.className = "event-location";

      const locText = document.createElement("div");
      locText.className = "event-location-text";
      locText.textContent = location;
      locWrap.appendChild(locText);

      card.appendChild(locWrap);
    }

    /* === BEGIN BLOCK: OFFERCARD CLICK HANDLER === */
    card.addEventListener("click", () => {
      if (window.OfferDetailPanel?.show) window.OfferDetailPanel.show(offer);
    });
    /* === END BLOCK: OFFERCARD CLICK HANDLER === */

    // Keyboard: Enter/Space
    card.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      e.preventDefault();
      if (window.OfferDetailPanel?.show) window.OfferDetailPanel.show(offer);
    });

    return card;
  }
  /* === END BLOCK: CREATE_CARD (offers → event-card DNA) === */



  /* === BEGIN BLOCK: RENDER (offers) ===
  Zweck: Rendert komplette Angebotsliste in #offer-cards inkl. Empty-State.
  Umfang: Clears container + fügt Cards hinzu.
  === */
  function render(offers) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const list = Array.isArray(offers) ? offers : [];
    if (list.length === 0) {
      const empty = document.createElement("div");
      empty.className = "content-card";
      empty.innerHTML = `
        <h2>Keine Angebote gefunden</h2>
        <p>Aktuell sind noch keine Angebote hinterlegt.</p>
      `.trim();
      target.appendChild(empty);
      return;
    }

    for (const offer of list) {
      target.appendChild(createCard(offer));
    }
  }
  /* === END BLOCK: RENDER (offers) === */

  return {
    render
  };
})();

// Export (browser global)
window.OfferCards = OfferCards;
