// BEGIN: FILE_HEADER_OFFERS
// Datei: js/offers.js
// Zweck:
// - Rendert kompakte Activity Cards im Feed der Aktivitäten-Seite
// - Nutzt bewusst die Event-Card-DNA statt der großen Discovery-Card-Fläche
// - Öffnet das Activity-Detailpanel bei Klick/Enter/Space
// END: FILE_HEADER_OFFERS

const OfferCards = (() => {
  let container = null;

  function ensureContainer() {
    if (!container) container = document.getElementById("offer-cards");
    return container;
  }

  function escapeHtml(value) {
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
  }

  function slugify(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9äöüß]+/gi, "-")
      .replace(/^-+|-+$/g, "");
  }

  // BEGIN: ACTIVITY_CARD_THUMB_LABEL_MAPPING
  function getThumbLabel(category) {
    const normalized = String(category || "").trim().toLowerCase();

    if (!normalized) return "Aktiv";
    if (normalized.includes("sport")) return "Sport";
    if (normalized.includes("natur")) return "Natur";
    if (normalized.includes("kultur")) return "Kultur";
    if (normalized.includes("freizeit")) return "Freizeit";

    return String(category || "Aktiv")
      .split("&")[0]
      .split("/")[0]
      .trim();
  }
  // END: ACTIVITY_CARD_THUMB_LABEL_MAPPING

  function renderThumb(offer) {
    if (offer.image) {
      return `
        <div class="activity-card-thumb">
          <img src="${escapeHtml(offer.image)}" alt="${escapeHtml(offer.title)}" loading="lazy">
        </div>
      `.trim();
    }

    const modifier = slugify(offer.kategorie || "aktivitaet");
    const shortLabel = escapeHtml(getThumbLabel(offer.kategorie));

    return `
      <div class="activity-card-thumb activity-card-thumb--fallback activity-card-thumb--${modifier}">
        <span class="activity-card-thumb__label">${shortLabel}</span>
      </div>
    `.trim();
  }

  function renderTags(tags) {
    const visibleTags = (Array.isArray(tags) ? tags : []).filter(Boolean).slice(0, 3);
    if (!visibleTags.length) return "";
    return `
      <div class="activity-card-tags">
        ${visibleTags.map((tag) => `<span class="discovery-chip">${escapeHtml(tag)}</span>`).join("")}
      </div>
    `.trim();
  }

  function renderQuietMeta(offer) {
    const parts = [offer.duration, offer.mode, offer.price].filter(Boolean).slice(0, 3);
    if (!parts.length) return "";
    return `<div class="activity-card-quiet">${parts.map(escapeHtml).join(" · ")}</div>`;
  }

  function createCard(offer) {
    const article = document.createElement("article");
    article.className = "event-card discovery-card--compact";
    article.tabIndex = 0;
    article.setAttribute("role", "button");
    article.setAttribute("aria-label", `Aktivität anzeigen: ${offer.title}`);

    article.innerHTML = `
      ${renderThumb(offer)}
      <div class="event-card-body">
        <h2 class="event-title">
          <span class="event-title__text">${escapeHtml(offer.title)}</span>
        </h2>
        <div class="event-meta">
          <span class="event-meta__place">${escapeHtml(offer.location)}</span>
        </div>
        <p class="event-card-desc">${escapeHtml(offer.description)}</p>
        ${renderTags(offer.tags)}
        ${renderQuietMeta(offer)}
      </div>
    `.trim();

    const open = () => {
      if (window.OfferDetailPanel?.show) {
        window.OfferDetailPanel.show(offer);
      }
    };

    article.addEventListener("click", open);
    article.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      event.preventDefault();
      open();
    });

    return article;
  }

  function render(offers) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const list = Array.isArray(offers) ? offers : [];
    if (!list.length) {
      target.innerHTML = `
        <section class="empty-state">
          <div class="empty-state__card">
            <h2 class="empty-state__title">Keine Aktivitäten gefunden</h2>
            <p class="empty-state__text">Passe Suche oder Filter an, um weitere Freizeitideen für Bocholt und Umgebung zu sehen.</p>
          </div>
        </section>
      `.trim();
      return;
    }

    for (const offer of list) {
      target.appendChild(createCard(offer));
    }
  }

  return { render };
})();

window.OfferCards = OfferCards;
