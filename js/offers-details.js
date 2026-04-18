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

  /* === BEGIN BLOCK: OFFER_DETAIL_PANEL_INIT_ICON_HYDRATE_V2 | Zweck: mountet das Activity-Detailpanel sauber, hydriert das Close-Icon direkt nach dem Insert und lässt die Listener innerhalb von init() | Umfang: ersetzt die gesamte init()-Methode === */
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
  /* === END BLOCK: OFFER_DETAIL_PANEL_INIT_ICON_HYDRATE_V2 === */


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

  /* === BEGIN BLOCK: OFFERS_DETAIL_MAPS_URL_V2 | Zweck: erzwingt für Activities konkrete Navigation per directions-URL und erlaubt optional einen exakt hinterlegten maps_url-Override; Umfang: ersetzt nur buildMapsUrl(...) === */
  buildMapsUrl(offer) {
    const explicitUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer?.maps_url)
      : String(offer?.maps_url || "").trim();

    if (explicitUrl) return explicitUrl;

    const destination = String(offer?.maps_query || offer?.location || "").trim();
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(destination)}`;
  },
  /* === END BLOCK: OFFERS_DETAIL_MAPS_URL_V2 === */

  /* === BEGIN BLOCK: OFFERS_DETAIL_MEDIA_WITH_SYMBOLIC_LABEL_AND_CREDIT_V2 | Zweck: nutzt im Activity-Detailpanel dieselbe zentrale Bildauflösung wie die Cards und kennzeichnet generische Motive dezent nur hier als Symbolbild inkl. Credit/Bildquelle | Umfang: ersetzt nur renderMedia(offer) === */
  renderMedia(offer) {
    const visual = this.getVisual(offer);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-detail__media-icon-svg" })
      : this.escapeHtml(visual.label.slice(0, 1));
    const imageData = window.OfferVisuals?.resolveImageData
      ? window.OfferVisuals.resolveImageData(offer)
      : {
          url: String(offer?.image || "").trim(),
          positionX: String(offer?.image_position_x || "50%").trim() || "50%",
          positionY: String(offer?.image_position_y || "50%").trim() || "50%",
          fit: String(offer?.image_fit || "cover").trim() || "cover",
          sourcePage: String(offer?.image_source_page || "").trim(),
          author: String(offer?.image_author || "").trim(),
          license: String(offer?.image_license || "").trim(),
          credit: String(offer?.image_credit || "").trim(),
          isSymbolic: false,
          note: ""
        };

    if (imageData.url) {
      const sourceUrl = window.OfferVisuals?.normalizeHttpUrl
        ? window.OfferVisuals.normalizeHttpUrl(imageData.sourcePage)
        : String(imageData.sourcePage || "").trim();

      const metaLine = [
        imageData.isSymbolic ? (String(imageData.note || "").trim() || "Symbolbild") : "",
        String(imageData.credit || "").trim()
      ].filter(Boolean).join(" · ");

      return `
        <div class="activity-detail__media-shell">
          <div class="activity-detail__media activity-detail__media--image activity-detail__media--${visual.modifier}">
            <img
              class="activity-detail__media-image"
              src="${this.escapeHtml(imageData.url)}"
              alt="${this.escapeHtml(imageData.isSymbolic ? `${offer.title} – Symbolbild` : offer.title)}"
              loading="eager"
              decoding="async"
              referrerpolicy="no-referrer"
              style="--activity-image-pos-x:${this.escapeHtml(imageData.positionX || "50%")}; --activity-image-pos-y:${this.escapeHtml(imageData.positionY || "50%")}; --activity-image-fit:${this.escapeHtml(imageData.fit || "cover")};"
            >
          </div>
          ${(metaLine || sourceUrl) ? `
            <div class="activity-detail__media-meta" aria-label="Bildhinweis">
              ${metaLine ? `<span class="activity-detail__media-note">${this.escapeHtml(metaLine)}</span>` : ""}
              ${sourceUrl ? `
                <a
                  class="activity-detail__media-source"
                  href="${this.escapeHtml(sourceUrl)}"
                  target="_blank"
                  rel="noopener noreferrer"
                >Bildquelle</a>
              ` : ""}
            </div>
          ` : ""}
        </div>
      `.trim();
    }

    return `
      <div class="activity-detail__media activity-detail__media--fallback activity-detail__media--${visual.modifier}">
        <div class="activity-detail__media-icon" aria-hidden="true">${iconHtml}</div>
      </div>
    `.trim();
  },
  /* === END BLOCK: OFFERS_DETAIL_MEDIA_WITH_SYMBOLIC_LABEL_AND_CREDIT_V2 === */

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

  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_FACTS_AND_ACTION_ICONS_V6 | Zweck: behebt den defekten sections-Referenzfehler, integriert Standard-Icons in die CTAs und liefert syntaktisch sauberen Abschluss des Detailpanel-Objekts; Umfang: ersetzt renderFacts(offer) und renderContent(offer) in js/offers-details.js === */
  renderFacts(offer) {
    const primaryTags = window.OfferVisuals?.getRankedTagItems
      ? window.OfferVisuals.getRankedTagItems(offer)
      : (Array.isArray(offer?.tags) ? offer.tags.map((entry) => String(entry || "").trim()).filter(Boolean) : []);

    const metaItems = [
      ["Saison", offer.season],
      ["Region", offer.area]
    ]
      .map(([label, value]) => {
        const text = String(value || "").trim();
        if (!text) return null;
        return { label, value: text };
      })
      .filter(Boolean);

    const note = String(offer?.hint || "").trim();

    if (!primaryTags.length && !metaItems.length && !note) return "";

    return `
      <div class="activity-detail__facts">
        ${primaryTags.length ? `
          <section class="activity-detail__fact-section activity-detail__fact-section--tags">
            <div class="activity-detail__fact-label">Merkmale</div>
            <div class="activity-detail__tag-list" aria-label="Merkmale vor Ort">
              ${primaryTags.map((tag) => `<span class="activity-detail__tag">${this.escapeHtml(tag)}</span>`).join("")}
            </div>
          </section>
        ` : ""}
        ${metaItems.length ? `
          <section class="activity-detail__fact-section activity-detail__fact-section--meta">
            <div class="activity-detail__fact-label">Kurzinfo</div>
            <div class="activity-detail__meta-list">
              ${metaItems.map((item) => `
                <div class="activity-detail__meta-chip">
                  <span class="activity-detail__meta-key">${this.escapeHtml(item.label)}</span>
                  <span class="activity-detail__meta-value">${this.escapeHtml(item.value)}</span>
                </div>
              `).join("")}
            </div>
          </section>
        ` : ""}
        ${note ? `
          <section class="activity-detail__fact-callout" aria-label="Gut zu wissen">
            <div class="activity-detail__fact-label">Gut zu wissen</div>
            <div class="activity-detail__fact-value">${this.escapeHtml(note)}</div>
          </section>
        ` : ""}
      </div>
    `.trim();
  },

  renderContent(offer) {
    const mapsUrl = this.buildMapsUrl(offer);
    const websiteUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer.url)
      : String(offer.url || "").trim();
    const mapsLabel = String(offer?.maps_label || "Zum Startpunkt navigieren").trim();
    const websiteLabel = String(offer?.website_label || "Website / Infos").trim();
    const metaLine = window.OfferVisuals?.buildMetaLine
      ? window.OfferVisuals.buildMetaLine(offer)
      : [offer?.duration, offer?.mode, offer?.price].filter(Boolean).join(" · ");
    const description = String(offer?.description || "").trim();

    const mapsIcon = window.Icons?.svg
      ? window.Icons.svg("compass", { className: "activity-detail__action-icon-svg" })
      : "";
    const websiteIcon = window.Icons?.svg
      ? window.Icons.svg("external-link", { className: "activity-detail__action-icon-svg" })
      : "";

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
            <a class="activity-detail__action" href="${this.escapeHtml(mapsUrl)}" target="_blank" rel="noopener noreferrer">
              <span class="activity-detail__action-icon" aria-hidden="true">${mapsIcon}</span>
              <span class="activity-detail__action-label">${this.escapeHtml(mapsLabel)}</span>
            </a>
            ${websiteUrl ? `
              <a class="activity-detail__action activity-detail__action--secondary" href="${this.escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer">
                <span class="activity-detail__action-icon" aria-hidden="true">${websiteIcon}</span>
                <span class="activity-detail__action-label">${this.escapeHtml(websiteLabel)}</span>
              </a>
            ` : ""}
          </div>
        </div>
      </article>
    `.trim();
  }
/* === BEGIN BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 | Zweck: registriert das Activity-Detailpanel wieder global, damit Card-Klicks auf Mobile das Panel öffnen statt in den URL-Fallback zu laufen; Umfang: ersetzt nur das Dateiende von js/offers-details.js === */
};

window.OfferDetailPanel = OfferDetailPanel;
/* === END BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 === */
