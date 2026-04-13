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

    if (normalized.includes("natur") || normalized.includes("drau")) {
      return { rawLabel: raw || "Natur & Draußen", label: "Natur", iconKey: "cat-nature", modifier: "natur" };
    }

    if (normalized.includes("kultur")) {
      return { rawLabel: raw || "Kultur", label: "Kultur", iconKey: "cat-culture", modifier: "kultur" };
    }

    if (normalized.includes("freizeit") || normalized.includes("famil")) {
      return { rawLabel: raw || "Freizeit & Familie", label: "Freizeit", iconKey: "pin", modifier: "freizeit-familie" };
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

  function toSingleLine(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  }

  function pickSupportingLabel(offer) {
    const audience = Array.isArray(offer?.audience)
      ? offer.audience.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    if (audience.length) {
      if (audience.some((entry) => entry.toLowerCase() === "familien")) return "Für Familien";
      return `Geeignet für ${audience[0]}`;
    }

    const season = toSingleLine(offer?.season);
    if (season) return season;

    const hint = toSingleLine(offer?.hint);
    if (hint) return hint;

    return "";
  }

  function buildFactItems(offer) {
    const curatedFacts = Array.isArray(offer?.cardFacts)
      ? offer.cardFacts.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    if (curatedFacts.length) {
      return curatedFacts.slice(0, 3);
    }

    return [offer?.duration, offer?.mode, offer?.price]
      .map((entry) => toSingleLine(entry))
      .filter(Boolean)
      .slice(0, 3);
  }

  return {
    escapeHtml,
    normalizeHttpUrl,
    slugify,
    getCategoryPresentation,
    buildMetaLine,
    buildFactItems,
    pickSupportingLabel
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

  function renderFactChips(offer) {
    const items = OfferVisuals.buildFactItems(offer);
    if (!items.length) return "";

    return `
      <div class="activity-card-facts" aria-label="Wichtige Informationen">
        ${items.map((item) => `<span class="activity-card-fact">${OfferVisuals.escapeHtml(item)}</span>`).join("")}
      </div>
    `.trim();
  }

function renderSupportingLine(offer) {
  return "";
}

  function renderMedia(offer, visual) {
    const imageUrl = OfferVisuals.normalizeHttpUrl(offer?.image);
    const modifier = OfferVisuals.escapeHtml(visual.modifier);
    const label = OfferVisuals.escapeHtml(visual.label);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-media-icon-svg" })
      : `<span class="activity-card-media-icon-fallback">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    if (imageUrl) {
      return `
        <div class="activity-card-media activity-card-media--image activity-card-media--${modifier}" aria-hidden="true">
<img
  class="activity-card-media__image"
  src="${OfferVisuals.escapeHtml(imageUrl)}"
  alt=""
  loading="lazy"
  decoding="async"
  referrerpolicy="no-referrer"
  style="--activity-image-pos-x:${OfferVisuals.escapeHtml(offer?.image_position_x || "50%")}; --activity-image-pos-y:${OfferVisuals.escapeHtml(offer?.image_position_y || "50%")}; --activity-image-fit:${OfferVisuals.escapeHtml(offer?.image_fit || "cover")};"
>
        </div>
      `.trim();
    }

    return `
      <div class="activity-card-media activity-card-media--fallback activity-card-media--${modifier}" aria-hidden="true">
        <div class="activity-card-media__scrim"></div>
        <div class="activity-card-media__content">
          <span class="activity-card-media__icon">${iconHtml}</span>
          <span class="activity-card-media__label">${label}</span>
        </div>
      </div>
    `.trim();
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

    article.className = "event-card discovery-card--compact activity-card--rich";
    article.tabIndex = 0;
    article.setAttribute("role", "button");
    article.setAttribute(
      "aria-label",
      primaryUrl ? `Aktivität öffnen: ${offer.title}` : `Aktivität anzeigen: ${offer.title}`
    );

    article.innerHTML = `
      ${renderMedia(offer, visual)}
      <div class="event-card-body">
        <div class="activity-card-kicker">${OfferVisuals.escapeHtml(visual.label)}</div>
        <h2 class="event-title">
          <span class="event-title__text">${OfferVisuals.escapeHtml(offer.title)}</span>
          ${renderCategoryIcon(visual)}
        </h2>
        ${renderDescription(offer)}
        <div class="event-meta">
          <span class="event-meta__place">${OfferVisuals.escapeHtml(offer.location)}</span>
        </div>
        ${renderFactChips(offer)}
        ${renderSupportingLine(offer)}
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
