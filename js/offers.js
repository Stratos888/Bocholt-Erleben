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
  const fallbackLabel = raw || "Aktivität";

  if (window.Icons && typeof window.Icons.categoryMeta === "function") {
    const meta = window.Icons.categoryMeta(raw);

    if (meta && meta.token) {
      const labelByFamily = {
        market: "Markt",
        kids: "Freizeit",
        music: "Musik",
        culture: "Kultur",
        sport: "Aktiv",
        nature: "Natur",
        city: "Lokal",
        business: "Wirtschaft",
        activity: "Aktivität",
        default: fallbackLabel
      };

      const modifierByFamily = {
        market: "markt",
        kids: "freizeit-familie",
        music: "musik",
        culture: "kultur",
        sport: "sport-bewegung",
        nature: "natur",
        city: "innenstadt-leben",
        business: "wirtschaft",
        activity: "aktivitaet",
        default: slugify(meta.canonical || raw || "aktivitaet") || "aktivitaet"
      };

      return {
        rawLabel: meta.canonical || fallbackLabel,
        label: labelByFamily[meta.family] || fallbackLabel,
        iconKey: meta.token,
        modifier: modifierByFamily[meta.family] || slugify(meta.canonical || raw || "aktivitaet") || "aktivitaet"
      };
    }
  }

  return {
    rawLabel: fallbackLabel,
    label: fallbackLabel,
    iconKey: "cat-activity",
    modifier: slugify(fallbackLabel) || "aktivitaet"
  };
}

  /* === BEGIN BLOCK: OFFERS_CARD_DISCOVERY_VALUE_V2 | Zweck: priorisiert auf Activity-Cards die nützlichsten Ortsmerkmale vor generischen Tags und hält Mobile/Desktop bei derselben Discovery-Logik; Umfang: ersetzt nur die Helper von buildMetaLine() bis renderSupportingLine() === */
  function buildMetaLine(offer) {
    return [offer?.duration, offer?.mode, offer?.price].filter(Boolean).join(" · ");
  }

  function toSingleLine(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  }

  function getTagPriority(tag) {
    const value = toSingleLine(tag);
    const priorityMap = {
      "Spielplatz": 10,
      "Badebucht": 12,
      "Badesee": 14,
      "Strand": 16,
      "Spaziergang": 18,
      "Snacks & Getränke": 20,
      "Café": 21,
      "Restaurant": 22,
      "Wassersport": 24,
      "Aussicht": 26,
      "Vogelbeobachtung": 28,
      "Spielgeräte": 30,
      "Moor": 32,
      "Grenze": 34,
      "Grenzort": 36,
      "Geschichte": 38,
      "Naturpfad": 40,
      "Rundweg": 42,
      "Park": 44,
      "Natur": 46
    };

    return priorityMap[value] ?? 500;
  }

  function getRankedTagItems(offer, limit = Infinity) {
    const tags = Array.isArray(offer?.tags)
      ? offer.tags.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    const uniqueTags = Array.from(new Set(tags));
    uniqueTags.sort((a, b) => {
      const delta = getTagPriority(a) - getTagPriority(b);
      if (delta !== 0) return delta;
      return a.localeCompare(b, "de");
    });

    return uniqueTags.slice(0, Math.max(0, Number.isFinite(limit) ? limit : uniqueTags.length));
  }

  function pickSupportingLabel(offer) {
    const primaryTags = getRankedTagItems(offer, 2);
    if (primaryTags.length) return primaryTags.join(" · ");

    const season = toSingleLine(offer?.season);
    if (season) return season;

    const hint = toSingleLine(offer?.hint);
    if (hint) return hint;

    return "";
  }

  function buildFactItems(offer) {
    const primaryTags = getRankedTagItems(offer, 3);
    if (primaryTags.length) {
      return primaryTags;
    }

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
    pickSupportingLabel,
    getRankedTagItems
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
      <div class="activity-card-facts" aria-label="Wichtige Merkmale">
        ${items.map((item) => `<span class="activity-card-fact">${OfferVisuals.escapeHtml(item)}</span>`).join("")}
      </div>
    `.trim();
  }

  function renderSupportingLine(offer) {
    const text = OfferVisuals.pickSupportingLabel(offer);
    if (!text) return "";
    return `<div class="activity-card-quiet">${OfferVisuals.escapeHtml(text)}</div>`;
  }
  /* === END BLOCK: OFFERS_CARD_DISCOVERY_VALUE_V2 === */
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
