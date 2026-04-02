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

  function getCategoryPresentation(category) {
    const raw = String(category || "").trim();
    const normalized = raw.toLowerCase();

    if (normalized.includes("sport")) {
      return { rawLabel: raw || "Sport & Bewegung", label: "Aktiv", iconKey: "cat-sport", modifier: "sport-bewegung" };
    }

    if (normalized.includes("natur")) {
      return { rawLabel: raw || "Natur", label: "Natur", iconKey: "cat-nature", modifier: "natur" };
    }

    if (normalized.includes("kultur")) {
      return { rawLabel: raw || "Kultur", label: "Kultur", iconKey: "cat-culture", modifier: "kultur" };
    }

    if (normalized.includes("freizeit")) {
      return { rawLabel: raw || "Freizeitorte", label: "Freizeit", iconKey: "pin", modifier: "freizeitorte" };
    }

    return {
      rawLabel: raw || "Aktivität",
      label: raw || "Aktivität",
      iconKey: "pin",
      modifier: slugify(raw || "aktivitaet") || "aktivitaet"
    };
  }

  function buildMetaLine(offer) {
    return [offer?.duration, offer?.mode, offer?.price].filter(Boolean).join(" · ");
  }

  return {
    escapeHtml,
    normalizeHttpUrl,
    slugify,
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

  function renderCategoryIcon(visual) {
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-category-icon-svg" })
      : `<span class="activity-card-category-icon-fallback">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    return `<span class="event-category-icon activity-card-category-icon" aria-hidden="true">${iconHtml}</span>`;
  }

  function renderDescription(offer) {
    const text = String(offer?.description || "").trim();
    if (!text) return "";
    return `<p class="event-card-desc">${OfferVisuals.escapeHtml(text)}</p>`;
  }

  function openPrimaryDesktopTarget(url) {
    if (!url) return false;
    window.open(url, "_blank", "noopener,noreferrer");
    return true;
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
      <div class="event-card-body">
        <div class="activity-card-kicker">${OfferVisuals.escapeHtml(visual.label)}</div>
        <h2 class="event-title">
          <span class="event-title__text">${OfferVisuals.escapeHtml(offer.title)}</span>
          ${renderCategoryIcon(visual)}
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

      if (isDesktopViewport() && openPrimaryDesktopTarget(primaryUrl)) {
        return;
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
