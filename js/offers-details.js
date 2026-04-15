// BEGIN: FILE_HEADER_OFFERS_DETAILS
// Datei: js/offers-details.js
// Zweck:
// - Activity-Detailpanel nur für Mobile
// - Desktop wird bewusst vom Panel entkoppelt und öffnet direkt die Ziel-URL
// - Nutzt dieselbe Kategorie-/Meta-Logik wie die Cards
// END: FILE_HEADER_OFFERS_DETAILS

const OfferDetailPanel = {
  panel: null,
  overlay: null,
  content: null,
  body: null,
  closeBtn: null,
  _isInit: false,
  _lastFocusEl: null,

  isDesktopViewport() {
    return window.matchMedia("(min-width: 900px)").matches;
  },

  /* === BEGIN BLOCK: OFFER_DETAIL_PANEL_INIT_ICON_HYDRATE_V1 | Zweck: hydriert dynamisch erzeugte data-ui-icon-Knoten im Activity-Detailpanel sofort nach dem Mount | Umfang: ersetzt nur init() Setup bis vor die Event-Listener === */
  init() {
    if (this._isInit) return;

    const root = document.getElementById("offer-detail-root");
    if (!root) return;

    root.innerHTML = `
      <div id="event-detail-panel" class="detail-panel hidden" hidden>
        <div class="detail-panel-overlay"></div>
        <div class="detail-panel-content">
          <div class="detail-panel-grabber" aria-hidden="true"></div>
          <button class="detail-panel-close" aria-label="Schließen"><span class="detail-panel-close__icon" data-ui-icon="x" aria-hidden="true"></span></button>
          <div class="detail-panel-body">
            <div id="detail-content"></div>
          </div>
        </div>
      </div>
    `.trim();

    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }

    this.panel = document.getElementById("event-detail-panel");
    this.overlay = this.panel?.querySelector(".detail-panel-overlay");
    this.body = this.panel?.querySelector(".detail-panel-body");
    this.content = document.getElementById("detail-content");
    this.closeBtn = this.panel?.querySelector(".detail-panel-close");

    if (!this.panel || !this.overlay || !this.body || !this.content || !this.closeBtn) return;
  }
  /* === END BLOCK: OFFER_DETAIL_PANEL_INIT_ICON_HYDRATE_V1 === */

    this.overlay.addEventListener("click", (event) => {
      if (event.target === this.overlay) this.hide();
    });

    this.closeBtn.addEventListener("click", (event) => {
      event.preventDefault();
      this.hide();
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && this.panel.classList.contains("active")) {
        this.hide();
      }
    });

    this._isInit = true;
  },

  show(offer) {
    const primaryUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer?.url)
      : String(offer?.url || "").trim();

    if (this.isDesktopViewport() && primaryUrl) {
      window.open(primaryUrl, "_blank", "noopener,noreferrer");
      return;
    }

    if (!this._isInit) this.init();
    if (!this.panel || !this.content || !this.body) return;

    this._lastFocusEl =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    this.panel.setAttribute("data-detail-type", "activity");
    this.renderContent(offer);

    this.panel.classList.remove("hidden");
    this.panel.removeAttribute("hidden");
    this.body.scrollTop = 0;

    requestAnimationFrame(() => {
      this.panel.classList.add("active");
      document.documentElement.classList.add("is-panel-open");
      document.body.classList.add("is-panel-open");
      this.closeBtn.focus();
    });
  },

  hide() {
    if (!this.panel) return;

    this.panel.classList.remove("active");
    document.documentElement.classList.remove("is-panel-open");
    document.body.classList.remove("is-panel-open");

    window.setTimeout(() => {
      this.panel.classList.add("hidden");
      this.panel.setAttribute("hidden", "");
      this.panel.removeAttribute("data-detail-type");
      if (this.body) this.body.scrollTop = 0;
      if (this._lastFocusEl && typeof this._lastFocusEl.focus === "function") {
        this._lastFocusEl.focus();
      }
    }, 280);
  },

  escapeHtml(value) {
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
  },

  getVisual(offer) {
    if (window.OfferVisuals?.getCategoryPresentation) {
      return window.OfferVisuals.getCategoryPresentation(offer?.kategorie);
    }

    return {
      rawLabel: String(offer?.kategorie || "Aktivität").trim() || "Aktivität",
      label: String(offer?.kategorie || "Aktivität").trim() || "Aktivität",
      iconKey: "pin",
      modifier: "aktivitaet"
    };
  },

  buildMapsUrl(location) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location || "")}`;
  },

  /* === BEGIN BLOCK: OFFERS_DETAIL_MEDIA_NORMALIZED_IMAGE_V1 | Zweck: normalisiert lokale/externe Bildpfade auch im Mobile-Detailpanel und übernimmt dieselben Fokalpunkt-Variablen wie die Cards | Umfang: ersetzt nur renderMedia(offer) === */
  renderMedia(offer) {
    const visual = this.getVisual(offer);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-detail__media-icon-svg" })
      : this.escapeHtml(visual.label.slice(0, 1));
    const imageUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer?.image)
      : String(offer?.image || "").trim();

    if (imageUrl) {
      return `
        <div class="activity-detail__media activity-detail__media--image activity-detail__media--${visual.modifier}">
          <img
            class="activity-detail__media-image"
            src="${this.escapeHtml(imageUrl)}"
            alt="${this.escapeHtml(offer.title)}"
            loading="eager"
            decoding="async"
            referrerpolicy="no-referrer"
            style="--activity-image-pos-x:${this.escapeHtml(offer?.image_position_x || "50%")}; --activity-image-pos-y:${this.escapeHtml(offer?.image_position_y || "50%")}; --activity-image-fit:${this.escapeHtml(offer?.image_fit || "cover")};"
          >
        </div>
      `.trim();
    }

    return `
      <div class="activity-detail__media activity-detail__media--fallback activity-detail__media--${visual.modifier}">
        <div class="activity-detail__media-icon" aria-hidden="true">${iconHtml}</div>
      </div>
    `.trim();
  },
  /* === END BLOCK: OFFERS_DETAIL_MEDIA_NORMALIZED_IMAGE_V1 === */

  renderCategoryBadge(offer) {
    const visual = this.getVisual(offer);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-detail__category-icon-svg" })
      : this.escapeHtml(visual.label.slice(0, 1));

    return `
      <div class="activity-detail__category activity-detail__category--${visual.modifier}">
        <span class="activity-detail__category-icon" aria-hidden="true">${iconHtml}</span>
        <span class="activity-detail__category-label">${this.escapeHtml(visual.label)}</span>
      </div>
    `.trim();
  },

  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_FACTS_EDITORIAL_V1 | Zweck: ersetzt die formularartige Faktenliste durch eine kompaktere, redaktionellere Facts-Struktur mit kurzem Grid + längeren Textsektionen | Umfang: ersetzt nur renderFacts(offer) === */
  renderFacts(offer) {
    const gridRows = [
      ["Saison", offer.season],
      ["Region", offer.area]
    ]
      .map(([label, value]) => {
        const text = String(value || "").trim();
        if (!text) return null;
        return { label, value: text };
      })
      .filter(Boolean);

    const sections = [
      ["Geeignet für", (offer.audience || []).join(" · ")],
      ["Hinweis", offer.hint]
    ]
      .map(([label, value]) => {
        const text = String(value || "").trim();
        if (!text) return null;
        return { label, value: text };
      })
      .filter(Boolean);

    if (!gridRows.length && !sections.length) return "";

    return `
      <div class="activity-detail__facts">
        ${gridRows.length ? `
          <div class="activity-detail__facts-grid">
            ${gridRows.map((row) => `
              <div class="activity-detail__fact-row">
                <div class="activity-detail__fact-label">${this.escapeHtml(row.label)}</div>
                <div class="activity-detail__fact-value">${this.escapeHtml(row.value)}</div>
              </div>
            `).join("")}
          </div>
        ` : ""}
        ${sections.map((row) => `
          <section class="activity-detail__fact-section">
            <div class="activity-detail__fact-label">${this.escapeHtml(row.label)}</div>
            <div class="activity-detail__fact-value">${this.escapeHtml(row.value)}</div>
          </section>
        `).join("")}
      </div>
    `.trim();
  },
  /* === END BLOCK: ACTIVITIES_DETAIL_FACTS_EDITORIAL_V1 === */

  renderContent(offer) {
    const mapsUrl = this.buildMapsUrl(offer.location);
    const websiteUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer.url)
      : String(offer.url || "").trim();
    const metaLine = window.OfferVisuals?.buildMetaLine
      ? window.OfferVisuals.buildMetaLine(offer)
      : [offer?.duration, offer?.mode, offer?.price].filter(Boolean).join(" · ");
    const description = String(offer?.description || "").trim();

    this.content.innerHTML = `
      <article class="activity-detail">
        ${this.renderMedia(offer)}

        <header class="activity-detail__header">
          ${this.renderCategoryBadge(offer)}
          <h2 class="activity-detail__title">${this.escapeHtml(offer.title)}</h2>
          <div class="activity-detail__place">${this.escapeHtml(offer.location)}</div>
          ${metaLine ? `<div class="activity-detail__meta">${this.escapeHtml(metaLine)}</div>` : ""}
        </header>

        <div class="activity-detail__body">
          ${description ? `<p class="activity-detail__description">${this.escapeHtml(description)}</p>` : ""}
          ${this.renderFacts(offer)}
          <div class="activity-detail__actions">
            <a class="activity-detail__action" href="${this.escapeHtml(mapsUrl)}" target="_blank" rel="noopener noreferrer">In Maps öffnen</a>
            ${websiteUrl ? `<a class="activity-detail__action activity-detail__action--secondary" href="${this.escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer">Website / Infos</a>` : ""}
          </div>
        </div>
      </article>
    `.trim();
  }
};

window.OfferDetailPanel = OfferDetailPanel;
