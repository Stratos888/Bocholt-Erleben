// BEGIN: FILE_HEADER_OFFERS_DETAILS
// Datei: js/offers-details.js
// Zweck:
// - Activity-Detailpanel für die Aktivitäten-Seite
// - Reused Overlay-/Panel-Familie des Event-Detailpanels
// - Nutzt den gleichen Scroll-Container wie das Event-Detailpanel
// END: FILE_HEADER_OFFERS_DETAILS

const OfferDetailPanel = {
  panel: null,
  overlay: null,
  content: null,
  body: null,
  closeBtn: null,
  _isInit: false,
  _lastFocusEl: null,

  init() {
    if (this._isInit) return;

    const root = document.getElementById("offer-detail-root");
    if (!root) return;

    root.innerHTML = `
      <div id="event-detail-panel" class="detail-panel hidden" hidden>
        <div class="detail-panel-overlay"></div>
        <div class="detail-panel-content">
          <div class="detail-panel-grabber" aria-hidden="true"></div>
          <button class="detail-panel-close" aria-label="Schließen">&times;</button>
          <div class="detail-panel-body">
            <div id="detail-content"></div>
          </div>
        </div>
      </div>
    `.trim();

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

  show(offer) {
    if (!this._isInit) this.init();
    if (!this.panel || !this.content || !this.body) return;

    this._lastFocusEl =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

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

  slugify(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9äöüß]+/gi, "-")
      .replace(/^-+|-+$/g, "");
  },

  buildMapsUrl(location) {
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location || "")}`;
  },

  renderMedia(offer) {
    if (offer.image) {
      return `
        <div class="activity-detail__media">
          <img src="${this.escapeHtml(offer.image)}" alt="${this.escapeHtml(offer.title)}" loading="lazy">
        </div>
      `.trim();
    }

    const modifier = this.slugify(offer.kategorie || "aktivitaet");
    const shortLabel = this.escapeHtml((offer.kategorie || "Aktivität").split("&")[0].trim());

    return `
      <div class="activity-detail__media activity-detail__media--fallback activity-detail__media--${modifier}">
        <span class="activity-detail__media-label">${shortLabel}</span>
      </div>
    `.trim();
  },

  renderTags(tags) {
    const list = Array.isArray(tags) ? tags.filter(Boolean) : [];
    if (!list.length) return "";
    return `
      <div class="activity-detail__chips">
        ${list.map((tag) => `<span class="discovery-chip">${this.escapeHtml(tag)}</span>`).join("")}
      </div>
    `.trim();
  },

  renderInfoGrid(offer) {
    const items = [
      ["Typische Dauer", offer.duration],
      ["Drinnen / Draußen", offer.mode],
      ["Kosten", offer.price],
      ["Geeignet für", (offer.audience || []).join(" · ")],
      ["Bereich", offer.kategorie],
      ["Lage", offer.area],
      ["Saison", offer.season],
      ["Hinweis", offer.hint]
    ].filter(([, value]) => String(value || "").trim());

    if (!items.length) return "";

    return `
      <div class="activity-detail__facts">
        ${items.map(([label, value]) => `
          <div class="activity-detail__fact">
            <div class="activity-detail__fact-label">${this.escapeHtml(label)}</div>
            <div class="activity-detail__fact-value">${this.escapeHtml(value)}</div>
          </div>
        `).join("")}
      </div>
    `.trim();
  },

  renderContent(offer) {
    const mapsUrl = this.buildMapsUrl(offer.location);
    const websiteUrl = offer.url;

    this.content.innerHTML = `
      <article class="activity-detail">
        ${this.renderMedia(offer)}

        <header class="activity-detail__header">
          <h2 class="activity-detail__title">${this.escapeHtml(offer.title)}</h2>
          <div class="activity-detail__place">${this.escapeHtml(offer.location)}</div>
          ${this.renderTags(offer.tags)}
        </header>

        <div class="activity-detail__body">
          <p class="activity-detail__description">${this.escapeHtml(offer.description)}</p>
          ${this.renderInfoGrid(offer)}

          <div class="activity-detail__actions">
            <a class="activity-detail__action" href="${this.escapeHtml(mapsUrl)}" target="_blank" rel="noopener noreferrer">In Maps öffnen</a>
            <a class="activity-detail__action activity-detail__action--secondary" href="${this.escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer">Website / Infos</a>
          </div>
        </div>
      </article>
    `.trim();
  }
};

window.OfferDetailPanel = OfferDetailPanel;
