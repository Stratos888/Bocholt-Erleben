// BEGIN: FILE_HEADER_OFFERS
// Datei: js/offers.js
// Zweck:
// - Rendert Activity Cards im Feed der Aktivitäten-Seite
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

  function renderMedia(offer) {
    if (offer.image) {
      return `
        <div class="discovery-card__media">
          <img src="${escapeHtml(offer.image)}" alt="${escapeHtml(offer.title)}" loading="lazy">
        </div>
      `.trim();
    }

    const modifier = slugify(offer.kategorie || "aktivitaet");
    const shortLabel = escapeHtml((offer.kategorie || "Aktivität").split("&")[0].trim());

    return `
      <div class="discovery-card__media discovery-card__media--fallback discovery-card__media--${modifier}">
        <span class="discovery-card__media-label">${shortLabel}</span>
      </div>
    `.trim();
  }

  function renderTags(tags) {
    const visibleTags = (Array.isArray(tags) ? tags : []).slice(0, 4);
    if (!visibleTags.length) return "";
    return `
      <div class="discovery-card__chips">
        ${visibleTags.map((tag) => `<span class="discovery-chip">${escapeHtml(tag)}</span>`).join("")}
      </div>
    `.trim();
  }

  function renderMeta(offer) {
    const parts = [offer.duration, offer.mode, offer.price].filter(Boolean).slice(0, 3);
    if (!parts.length) return "";
    return `<div class="discovery-card__meta">${parts.map(escapeHtml).join(" · ")}</div>`;
  }

  function createCard(offer) {
    const article = document.createElement("article");
    article.className = "event-card discovery-card";
    article.tabIndex = 0;
    article.setAttribute("role", "button");
    article.setAttribute("aria-label", `Aktivität anzeigen: ${offer.title}`);

    article.innerHTML = `
      ${renderMedia(offer)}
      <div class="discovery-card__body">
        <h2 class="discovery-card__title">${escapeHtml(offer.title)}</h2>
        <div class="discovery-card__place">${escapeHtml(offer.location)}</div>
        <p class="discovery-card__description">${escapeHtml(offer.description)}</p>
        ${renderTags(offer.tags)}
        ${renderMeta(offer)}
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
