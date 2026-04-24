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

  /* === BEGIN BLOCK: OFFERS_DETAIL_MEDIA_AND_ATTRIBUTION_ENTERPRISE_V1 | Zweck: trennt Symbolbild-Kennzeichnung auf dem Bild von einem separaten Bildnachweis im Footer des Activity-Detailpanels; Umfang: ersetzt renderMedia(offer) und ergänzt renderImageAttribution(offer) === */
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
      const symbolicLabel = imageData.isSymbolic
        ? (String(imageData.note || "").trim() || "Symbolbild")
        : "";

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
            ${symbolicLabel ? `
              <span class="activity-detail__media-badge">${this.escapeHtml(symbolicLabel)}</span>
            ` : ""}
          </div>
        </div>
      `.trim();
    }

    return `
      <div class="activity-detail__media activity-detail__media--fallback activity-detail__media--${visual.modifier}">
        <div class="activity-detail__media-icon" aria-hidden="true">${iconHtml}</div>
      </div>
    `.trim();
  },

  renderImageAttribution(offer) {
    const imageData = window.OfferVisuals?.resolveImageData
      ? window.OfferVisuals.resolveImageData(offer)
      : {
          sourcePage: String(offer?.image_source_page || "").trim(),
          author: String(offer?.image_author || "").trim(),
          license: String(offer?.image_license || "").trim(),
          credit: String(offer?.image_credit || "").trim(),
          isSymbolic: false,
          note: ""
        };

    const sourceUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(imageData.sourcePage)
      : String(imageData.sourcePage || "").trim();
    const author = String(imageData.author || "").trim();
    const license = String(imageData.license || "").trim();
    const credit = String(imageData.credit || "").trim();
    const note = imageData.isSymbolic ? (String(imageData.note || "").trim() || "Symbolbild") : "";
    const showCredit = credit && (!author || !license);
    const infoIcon = window.Icons?.svg
      ? window.Icons.svg("info", { className: "activity-detail__media-attribution-icon-svg" })
      : "";

    if (!note && !author && !license && !showCredit && !sourceUrl) return "";

    return `
      <details class="activity-detail__media-attribution">
        <summary class="activity-detail__media-attribution-toggle">
          <span class="activity-detail__media-attribution-toggle-main">
            <span class="activity-detail__media-attribution-icon" aria-hidden="true">${infoIcon}</span>
            <span>Bildnachweis</span>
          </span>
          <span class="activity-detail__media-attribution-toggle-secondary">Details</span>
        </summary>
        <div class="activity-detail__media-attribution-panel">
          ${note ? `
            <div class="activity-detail__media-attribution-row">
              <div class="activity-detail__media-attribution-key">Hinweis</div>
              <div class="activity-detail__media-attribution-value">${this.escapeHtml(note)}</div>
            </div>
          ` : ""}
          ${author ? `
            <div class="activity-detail__media-attribution-row">
              <div class="activity-detail__media-attribution-key">Urheber</div>
              <div class="activity-detail__media-attribution-value">${this.escapeHtml(author)}</div>
            </div>
          ` : ""}
          ${license ? `
            <div class="activity-detail__media-attribution-row">
              <div class="activity-detail__media-attribution-key">Lizenz</div>
              <div class="activity-detail__media-attribution-value">${this.escapeHtml(license)}</div>
            </div>
          ` : ""}
          ${showCredit ? `
            <div class="activity-detail__media-attribution-row">
              <div class="activity-detail__media-attribution-key">Nachweis</div>
              <div class="activity-detail__media-attribution-value">${this.escapeHtml(credit)}</div>
            </div>
          ` : ""}
          ${sourceUrl ? `
            <div class="activity-detail__media-attribution-row">
              <div class="activity-detail__media-attribution-key">Quelle</div>
              <div class="activity-detail__media-attribution-value">
                <a class="activity-detail__media-attribution-link" href="${this.escapeHtml(sourceUrl)}" target="_blank" rel="noopener noreferrer">Original / Quelle öffnen</a>
              </div>
            </div>
          ` : ""}
        </div>
      </details>
    `.trim();
  },
  /* === END BLOCK: OFFERS_DETAIL_MEDIA_AND_ATTRIBUTION_ENTERPRISE_V1 === */

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

  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_FACTS_TAGS_ONLY_V8 | Zweck: entfernt den künstlichen Kurzinfo-Block vollständig und zeigt im Activity-Detailpanel nur noch die semantisch bereinigten Detail-Merkmale | Umfang: ersetzt nur renderFacts(offer) in js/offers-details.js === */
  renderFacts(offer) {
    const primaryTags = window.OfferVisuals?.getRankedTagItems
      ? window.OfferVisuals.getRankedTagItems(offer, 4)
      : Array.from(
          new Set(
            Array.isArray(offer?.tags)
              ? offer.tags.map((entry) => String(entry || "").trim()).filter(Boolean)
              : []
          )
        ).slice(0, 4);

    if (!primaryTags.length) return "";

    return `
      <div class="activity-detail__facts">
        <section class="activity-detail__fact-section activity-detail__fact-section--tags">
          <div class="activity-detail__fact-label">Merkmale</div>
          <div class="activity-detail__tag-list" aria-label="Merkmale vor Ort">
            ${primaryTags.map((tag) => `<span class="activity-detail__tag">${this.escapeHtml(tag)}</span>`).join("")}
          </div>
        </section>
      </div>
    `.trim();
  },
  /* === END BLOCK: ACTIVITIES_DETAIL_FACTS_TAGS_ONLY_V8 === */

  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_CONTENT_WITH_OUTBOUND_ANALYTICS_V3 | Zweck: ergänzt im Activity-Detailpanel sauberes Outbound-Tracking für Maps- und Website-Links, ohne sichtbare UI oder Linkziele zu verändern | Umfang: ersetzt nur renderContent(offer) in js/offers-details.js === */
  renderContent(offer) {
    const mapsUrl = this.buildMapsUrl(offer);
    const websiteUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer.url)
      : String(offer.url || "").trim();
    const mapsLabel = String(offer?.maps_label || "Zum Startpunkt navigieren").trim();
    const websiteLabel = String(offer?.website_label || "Website / Infos").trim();

    const season = String(offer?.season || "").trim();
    const fallbackMetaParts = [offer?.duration, offer?.mode, offer?.price].filter(Boolean);
    if (season && season !== "Ganzjährig") {
      fallbackMetaParts.push(season);
    }

    const metaLine = window.OfferVisuals?.buildMetaLine
      ? window.OfferVisuals.buildMetaLine(offer, { includeSeasonalRestriction: true })
      : fallbackMetaParts.join(" · ");
    const description = String(offer?.description || "").trim();

    const mapsIcon = window.Icons?.svg
      ? window.Icons.svg("compass", { className: "activity-detail__action-icon-svg" })
      : "";
    const websiteIcon = window.Icons?.svg
      ? window.Icons.svg("external-link", { className: "activity-detail__action-icon-svg" })
      : "";

    const baseOutboundPayload = {
      entityType: "activity",
      entityId: String(offer?.id || "").trim(),
      entityTitle: String(offer?.title || "").trim()
    };

    const mapsOutboundPayload = mapsUrl
      ? {
          ...baseOutboundPayload,
          outboundType: "maps",
          destinationUrl: mapsUrl
        }
      : null;

    const websiteOutboundPayload = websiteUrl
      ? {
          ...baseOutboundPayload,
          outboundType: "website",
          destinationUrl: websiteUrl
        }
      : null;

    const buildOutboundDataAttrs = (payload) => {
      if (!payload) return "";
      return [
        `data-outbound-type="${this.escapeHtml(payload.outboundType || "")}"`,
        `data-entity-type="${this.escapeHtml(payload.entityType || "")}"`,
        `data-entity-id="${this.escapeHtml(payload.entityId || "")}"`,
        `data-entity-title="${this.escapeHtml(payload.entityTitle || "")}"`,
        `data-destination-url="${this.escapeHtml(payload.destinationUrl || "")}"`
      ].join(" ");
    };

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
            <a
              class="activity-detail__action"
              href="${this.escapeHtml(mapsUrl)}"
              target="_blank"
              rel="noopener noreferrer"
              ${buildOutboundDataAttrs(mapsOutboundPayload)}
            >
              <span class="activity-detail__action-icon" aria-hidden="true">${mapsIcon}</span>
              <span class="activity-detail__action-label">${this.escapeHtml(mapsLabel)}</span>
            </a>
            ${websiteUrl ? `
              <a
                class="activity-detail__action activity-detail__action--secondary"
                href="${this.escapeHtml(websiteUrl)}"
                target="_blank"
                rel="noopener noreferrer"
                ${buildOutboundDataAttrs(websiteOutboundPayload)}
              >
                <span class="activity-detail__action-icon" aria-hidden="true">${websiteIcon}</span>
                <span class="activity-detail__action-label">${this.escapeHtml(websiteLabel)}</span>
              </a>
            ` : ""}
          </div>
          ${this.renderImageAttribution(offer)}
        </div>
      </article>
    `.trim();

    this.content.querySelectorAll("a[data-outbound-type]").forEach((link) => {
      link.addEventListener("click", () => {
        if (!window.BEAnalytics || typeof window.BEAnalytics.trackOutboundClick !== "function") return;

        window.BEAnalytics.trackOutboundClick({
          outboundType: String(link.dataset.outboundType || "").trim(),
          entityType: String(link.dataset.entityType || "").trim(),
          entityId: String(link.dataset.entityId || "").trim(),
          entityTitle: String(link.dataset.entityTitle || "").trim(),
          destinationUrl: String(link.dataset.destinationUrl || link.href || "").trim()
        });
      });
    });
  }
  /* === END BLOCK: ACTIVITIES_DETAIL_CONTENT_WITH_OUTBOUND_ANALYTICS_V3 === */
  /* === END BLOCK: ACTIVITIES_DETAIL_CONTENT_WITH_SEASONAL_META_V2 === */
/* === BEGIN BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 | Zweck: registriert das Activity-Detailpanel wieder global, damit Card-Klicks auf Mobile das Panel öffnen statt in den URL-Fallback zu laufen; Umfang: ersetzt nur das Dateiende von js/offers-details.js === */
};

window.OfferDetailPanel = OfferDetailPanel;
/* === END BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 === */
