// BEGIN: FILE_HEADER_OFFERS
// Datei: js/offers.js
// Zweck:
// - Rendert Activity Cards für Mobile + Desktop
// - Nutzt auf Mobile das Detailpanel, auf Desktop direkten Ziel-Open wie bei Events
// - Stellt die visuelle Kategorie-Logik zentral über window.OfferVisuals bereit
// END: FILE_HEADER_OFFERS

const OfferVisuals = (() => {
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

  function normalizeHttpUrl(raw) {
    const value = String(raw || "").trim();
    if (!value) return "";

    try {
      return new URL(value).href;
    } catch (_) {
      try {
        return new URL(`https://${value}`).href;
      } catch (_) {
        return "";
      }
    }
  }

  function slugify(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9äöüß]+/gi, "-")
      .replace(/^-+|-+$/g, "");
  }

  // BEGIN: ACTIVITY_CATEGORY_PRESENTATION
  function getCategoryPresentation(category) {
    const normalized = String(category || "").trim().toLowerCase();

    if (normalized.includes("sport")) {
      return { label: "Aktiv", iconKey: "cat-sport", modifier: "sport-bewegung" };
    }

    if (normalized.includes("natur")) {
      return { label: "Natur", iconKey: "cat-nature", modifier: "natur" };
    }

    if (normalized.includes("kultur")) {
      return { label: "Kultur", iconKey: "cat-culture", modifier: "kultur" };
    }

    if (normalized.includes("freizeit")) {
      return { label: "Freizeit", iconKey: "pin", modifier: "freizeitorte" };
    }

    return {
      label: String(category || "Aktivität").trim() || "Aktivität",
      iconKey: "pin",
      modifier: slugify(category || "aktivitaet") || "aktivitaet"
    };
  }
  // END: ACTIVITY_CATEGORY_PRESENTATION

  function buildMetaLine(offer) {
    return [offer?.duration, offer?.mode, offer?.price].filter(Boolean).join(" · ");
  }

  return {
    escapeHtml,
    normalizeHttpUrl,
    getCategoryPresentation,
    buildMetaLine
  };
})();

window.OfferVisuals = OfferVisuals;

const OfferCards = (() => {
  let container = null;

  const isDesktopViewport = () => window.matchMedia("(min-width: 900px)").matches;

  function ensureContainer() {
    if (!container) container = document.getElementById("offer-cards");
    return container;
  }

  function renderThumb(offer) {
    const visual = OfferVisuals.getCategoryPresentation(offer.kategorie);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-thumb__icon-svg" })
      : `<span class="activity-card-thumb__fallback-letter">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    if (offer.image) {
      return `
        <div class="activity-card-thumb activity-card-thumb--image">
          <img src="${OfferVisuals.escapeHtml(offer.image)}" alt="${OfferVisuals.escapeHtml(offer.title)}" loading="lazy">
        </div>
      `.trim();
    }

    return `
      <div class="activity-card-thumb activity-card-thumb--fallback activity-card-thumb--${visual.modifier}" aria-hidden="true">
        <span class="activity-card-thumb__icon">${iconHtml}</span>
      </div>
    `.trim();
  }

  function renderDescription(offer) {
    const text = String(offer?.description || "").trim();
    if (!text) return "";
    return `<p class="event-card-desc">${OfferVisuals.escapeHtml(text)}</p>`;
  }

  function openPrimaryDesktopTarget(url) {
    if (!url) return false;

    try {
      window.open(url, "_blank", "noopener,noreferrer");
      return true;
    } catch (_) {
      return false;
    }
  }

  function createCard(offer) {
    const article = document.createElement("article");
    const visual = OfferVisuals.getCategoryPresentation(offer.kategorie);
    const primaryUrl = OfferVisuals.normalizeHttpUrl(offer.url);
    const metaLine = OfferVisuals.buildMetaLine(offer);

    article.className = "event-card discovery-card--compact";
    article.tabIndex = 0;
    article.setAttribute("role", "button");
    article.setAttribute(
      "aria-label",
      primaryUrl ? `Aktivität öffnen: ${offer.title}` : `Aktivität anzeigen: ${offer.title}`
    );

    article.innerHTML = `
      ${renderThumb(offer)}
      <div class="event-card-body">
        <div class="activity-card-kicker">${OfferVisuals.escapeHtml(visual.label)}</div>
        <h2 class="event-title">
          <span class="event-title__text">${OfferVisuals.escapeHtml(offer.title)}</span>
        </h2>
        <div class="event-meta">
          <span class="event-meta__place">${OfferVisuals.escapeHtml(offer.location)}</span>
        </div>
        ${renderDescription(offer)}
        ${metaLine ? `<div class="activity-card-quiet">${OfferVisuals.escapeHtml(metaLine)}</div>` : ""}
      </div>
    `.trim();

    const open = (event) => {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }

      if (isDesktopViewport()) {
        if (openPrimaryDesktopTarget(primaryUrl)) return;
      }

      if (window.OfferDetailPanel?.show) {
        window.OfferDetailPanel.show(offer);
        return;
      }

      openPrimaryDesktopTarget(primaryUrl);
    };

    article.addEventListener("click", open);
    article.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      open(event);
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
