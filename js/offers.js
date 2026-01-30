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
  Zweck: Kleine, neutrale Kategorie-Icon-Zuordnung (rein dekorativ).
  Umfang: Minimaler Mapper; unbekannt => leer.
  === */
  function getCategoryIcon(category) {
    const c = (category || "").toString().trim().toLowerCase();
    if (!c) return "";
    if (c.includes("museum") || c.includes("ausstellung")) return "🏛️";
    if (c.includes("kinder") || c.includes("familie")) return "👨‍👩‍👧‍👦";
    if (c.includes("indoor") || c.includes("regen")) return "☔";
    if (c.includes("sport") || c.includes("freizeit")) return "🏃";
    if (c.includes("natur") || c.includes("ausflug")) return "🌿";
    return "";
  }
  /* === END BLOCK: CATEGORY_ICON (optional, neutral) === */

    /* === BEGIN BLOCK: CREATE_CARD (offers → event-card DNA) ===
  Zweck: Cards nutzen bestehende Event-Card CSS-Klassen für konsistentes Design.
  Zusätzlich:
  - description ist Pflicht (kurz & prägnant)
  - url ist Pflicht → Button "Zur Location"
  - Fallback: Google Maps (oder Locations.getMapsFallback), falls url fehlt/leer ist
  Umfang: Erzeugt .event-card mit .event-title + .event-meta + Action-Button.
  === */
  function createCard(offer) {
    const card = document.createElement("div");
    card.className = "event-card";

    const title = (offer?.title || "").toString().trim();
    const category = (offer?.kategorie || offer?.category || "").toString().trim();
    const location = (offer?.location || "").toString().trim();
    const hint = (offer?.hint || "").toString().trim();
    const description = (offer?.description || "").toString().trim();
    const url = (offer?.url || "").toString().trim();

    const icon = getCategoryIcon(category);
    if (icon) {
      const iconEl = document.createElement("span");
      iconEl.className = "event-category-icon";
      iconEl.setAttribute("aria-hidden", "true");
      iconEl.textContent = icon;
      card.appendChild(iconEl);
    }

    const titleEl = document.createElement("div");
    titleEl.className = "event-title";
    titleEl.textContent = title || "Ohne Titel";
    card.appendChild(titleEl);

    const metaEl = document.createElement("div");
    metaEl.className = "event-meta";
    const metaParts = [];
    if (category) metaParts.push(category);
    if (hint) metaParts.push(hint);
    metaEl.textContent = metaParts.join(" · ");
    card.appendChild(metaEl);

    // Kurzbeschreibung (Pflicht) – bewusst kompakt
    if (description) {
      const descEl = document.createElement("div");
      descEl.className = "event-meta";
      descEl.textContent = description;
      card.appendChild(descEl);
    }

    if (location) {
      const locWrap = document.createElement("div");
      locWrap.className = "event-location";

      const locText = document.createElement("div");
      locText.className = "event-location-text";
      locText.textContent = location;
      locWrap.appendChild(locText);

      card.appendChild(locWrap);
    }

    // Action: "Zur Location" – url primär, Maps als Fallback (wie in js/details.js)
    let locationHref = "";
    if (location) {
      const homepage =
        (window.Locations?.getHomepage && window.Locations.getHomepage(location)) || "";
      locationHref =
        homepage ||
        (window.Locations?.getMapsFallback
          ? window.Locations.getMapsFallback(location)
          : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location)}`);
    }

    const primaryHref = url || locationHref;

    if (primaryHref) {
      const actions = document.createElement("div");
      actions.className = "detail-actions";

      const a = document.createElement("a");
      a.className = "detail-link-btn";
      a.href = primaryHref;
      a.target = "_blank";
      a.rel = "noopener noreferrer";
      a.setAttribute("aria-label", `Zur Location: ${title || location || "öffnen"}`);

      // Inhalt (kein innerHTML nötig)
      const label = document.createElement("span");
      label.textContent = "Zur Location";
      a.appendChild(label);

      actions.appendChild(a);
      card.appendChild(actions);
    }

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
