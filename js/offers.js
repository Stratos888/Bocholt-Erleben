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

  /* === BEGIN BLOCK: OFFERS_LOCAL_IMAGE_URL_SUPPORT_V1 | Zweck: erlaubt neben http/https auch lokale Asset-Pfade für Activity-Bilder auf Live + Staging | Umfang: ersetzt nur die URL-Normalisierung in js/offers.js === */
  function normalizeHttpUrl(raw) {
    const value = String(raw || "").trim();
    if (!value) return "";

    try {
      return new URL(value).href;
    } catch (_) {
      try {
        return new URL(value, window.location.href).href;
      } catch (_) {
        try {
          return new URL(`https://${value}`).href;
        } catch (_) {
          return "";
        }
      }
    }
  }
  /* === END BLOCK: OFFERS_LOCAL_IMAGE_URL_SUPPORT_V1 === */

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

    /* === BEGIN BLOCK: OFFERS_CATEGORY_ICON_FAMILY_V1 | Zweck: verwendet für Freizeit & Familie global ein eigenes Kategorie-Icon statt des generischen Pins | Umfang: ersetzt nur den Freizeit/Familie-Branch in getCategoryPresentation() === */
    if (normalized.includes("freizeit") || normalized.includes("famil")) {
      return { rawLabel: raw || "Freizeit & Familie", label: "Freizeit", iconKey: "cat-kids", modifier: "freizeit-familie" };
    }
    /* === END BLOCK: OFFERS_CATEGORY_ICON_FAMILY_V1 === */

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

  /* === BEGIN BLOCK: ACTIVITIES_IMAGE_LOADING_STRATEGY_V1 | Zweck: priorisiert erste sichtbare Bilder, setzt für externe Bildquellen Connection Hints und lässt den Rest bewusst lazy | Umfang: ersetzt nur Helper + renderMedia() vor openPrimaryDesktopTarget() === */
  const preconnectedImageOrigins = new Set();

  function ensureImageOriginHints(imageUrl) {
    try {
      const url = new URL(imageUrl, window.location.href);
      if (!url.origin || url.origin === window.location.origin) return;
      if (preconnectedImageOrigins.has(url.origin)) return;

      const head = document.head;
      if (!head) return;

      if (!head.querySelector(`link[rel="dns-prefetch"][href="${url.origin}"]`)) {
        const dnsPrefetch = document.createElement("link");
        dnsPrefetch.rel = "dns-prefetch";
        dnsPrefetch.href = url.origin;
        head.appendChild(dnsPrefetch);
      }

      if (!head.querySelector(`link[rel="preconnect"][href="${url.origin}"]`)) {
        const preconnect = document.createElement("link");
        preconnect.rel = "preconnect";
        preconnect.href = url.origin;
        preconnect.crossOrigin = "anonymous";
        head.appendChild(preconnect);
      }

      preconnectedImageOrigins.add(url.origin);
    } catch (_) {}
  }

  function getImageLoadingProfile(index) {
    const numericIndex = Number(index);
    const cardIndex = Number.isFinite(numericIndex) ? numericIndex : Number.MAX_SAFE_INTEGER;
    const desktop = isDesktopViewport();

    const eagerCount = desktop ? 4 : 2;
    const highPriorityCount = desktop ? 4 : 1;

    return {
      loading: cardIndex < eagerCount ? "eager" : "lazy",
      fetchPriority: cardIndex < highPriorityCount ? "high" : "auto"
    };
  }

  function renderMedia(offer, visual, index) {
    const imageUrl = OfferVisuals.normalizeHttpUrl(offer?.image);
    const modifier = OfferVisuals.escapeHtml(visual.modifier);
    const label = OfferVisuals.escapeHtml(visual.label);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-media-icon-svg" })
      : `<span class="activity-card-media-icon-fallback">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    if (imageUrl) {
      ensureImageOriginHints(imageUrl);
      const { loading, fetchPriority } = getImageLoadingProfile(index);

      return `
        <div class="activity-card-media activity-card-media--image activity-card-media--${modifier}" aria-hidden="true">
<img
  class="activity-card-media__image"
  src="${OfferVisuals.escapeHtml(imageUrl)}"
  alt=""
  loading="${loading}"
  fetchpriority="${fetchPriority}"
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
  /* === END BLOCK: ACTIVITIES_IMAGE_LOADING_STRATEGY_V1 === */

  function openPrimaryDesktopTarget(url) {
    if (!url) return false;
    window.open(url, "_blank", "noopener,noreferrer");
    return true;
  }

  /* === BEGIN BLOCK: ACTIVITIES_CREATECARD_IMAGE_PRIORITY_PLUMBING_V1 | Zweck: reicht den Render-Index bis in renderMedia() durch, ohne Card-Verhalten zu verändern | Umfang: ersetzt nur createCard() === */
  function createCard(offer, index) {
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
      ${renderMedia(offer, visual, index)}
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
  /* === END BLOCK: ACTIVITIES_CREATECARD_IMAGE_PRIORITY_PLUMBING_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_SKELETON_AND_EMPTYSTATE_PARITY_V1 | Zweck: ergänzt Desktop-Skeletons und gleicht Empty-State mit Reset-CTA an die Event-Seite an | Umfang: ersetzt nur Render-/Export-Block des OfferCards-Moduls === */
  function createSkeletonCard() {
    const article = document.createElement("article");
    article.className = "event-card discovery-card--compact activity-card--rich is-skeleton";
    article.tabIndex = -1;
    article.setAttribute("aria-hidden", "true");

    article.innerHTML = `
      <div class="activity-card-media activity-card-media--image" aria-hidden="true">
        <div class="activity-card-skeleton-media skel-block"></div>
      </div>
      <div class="event-card-body">
        <div class="activity-card-kicker">
          <span class="skel-line skel-line--sm" style="width:64px"></span>
        </div>
        <h2 class="event-title">
          <span class="event-title__text">
            <span class="skel-line" style="width:72%"></span>
          </span>
        </h2>
        <div class="event-card-desc">
          <span class="skel-line" style="width:96%"></span>
          <span class="skel-line" style="width:78%; margin-top:8px;"></span>
        </div>
        <div class="event-meta">
          <span class="event-meta__place">
            <span class="skel-line skel-line--sm" style="width:38%"></span>
          </span>
        </div>
        <div class="activity-card-facts" aria-hidden="true">
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:56px"></span></span>
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:62px"></span></span>
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:68px"></span></span>
        </div>
      </div>
    `.trim();

    return article;
  }

  function renderSkeleton(count = 8) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const n = Math.max(1, Math.min(12, Number(count) || 8));
    for (let i = 0; i < n; i++) {
      target.appendChild(createSkeletonCard());
    }
  }

  /* === BEGIN BLOCK: ACTIVITIES_RENDER_IMAGE_PRIORITY_PLUMBING_V1 | Zweck: reicht Card-Indizes an die Bildlogik weiter, damit erste sichtbare Bilder priorisiert laden | Umfang: ersetzt nur render(offers) + Return-Zeile === */
  function render(offers) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const list = Array.isArray(offers) ? offers : [];
    if (!list.length) {
      target.innerHTML = `
        <section class="empty-state">
          <div class="empty-state__card" role="status" aria-live="polite">
            <div class="empty-state__title">Keine Aktivitäten gefunden</div>
            <div class="empty-state__text">Filter anpassen oder zurücksetzen.</div>
            <button type="button" class="empty-state__btn" id="empty-reset-btn">Filter zurücksetzen</button>
          </div>
        </section>
      `.trim();

      const btn = document.getElementById("empty-reset-btn");
      if (btn) {
        btn.addEventListener("click", () => {
          window.dispatchEvent(new CustomEvent("offers:reset-filters"));
        });
      }
      return;
    }

    list.forEach((offer, index) => {
      target.appendChild(createCard(offer, index));
    });
  }

  return { render, renderSkeleton };
  /* === END BLOCK: ACTIVITIES_RENDER_IMAGE_PRIORITY_PLUMBING_V1 === */
  /* === END BLOCK: ACTIVITIES_SKELETON_AND_EMPTYSTATE_PARITY_V1 === */
})();

window.OfferCards = OfferCards;
