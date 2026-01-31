// BEGIN: FILE_HEADER_OFFERS_DETAILS
// Datei: js/offers-details.js
// Zweck:
// - Anzeige eines einzelnen Angebots im Detail-Panel (Offers)
// - Öffnen/Schließen des Panels (Overlay, ESC, Close)
// - Darstellung aller Offer-Details (Beschreibung, Links)
//
// Verantwortlich für:
// - Detail-Panel DOM (wird in #offer-detail-root erzeugt)
// - Panel-Interaktion (Open/Close)
// - Darstellung eines Offers
//
// Nicht verantwortlich für:
// - Offer-Listen oder Offer-Filter
// - Filter-State oder Filter-UI
// - Laden von Offer-Daten
//
// Contract:
// - erhält ein einzelnes Offer-Objekt (z. B. OfferDetailPanel.show(offer))
// END: FILE_HEADER_OFFERS_DETAILS

const OfferDetailPanel = {
  panel: null,
  overlay: null,
  content: null,
  closeBtn: null,

  _isInit: false,
  _lastFocusEl: null,
  _onKeyDown: null,
  _onOverlayClick: null,
  _onCloseClick: null,

  init() {
    if (this._isInit) return;

    /* === BEGIN BLOCK: OFFER DETAIL DOM (create + attach to #offer-detail-root) ===
    Zweck: Detailpanel auf der Angebotsseite als Overlay unter <body> einhängen (PROJECT.md).
    Umfang: Erzeugt Panel-HTML (id/class identisch zum Event-Panel für CSS-Reuse).
    === */
    const root = document.getElementById("offer-detail-root");
    if (!root) {
      console.warn("OfferDetailPanel: #offer-detail-root not found");
      return;
    }

    // Reuse der bestehenden DetailPanel-CSS (scoped auf #event-detail-panel)
    // -> auf Angebotsseite existiert kein Event-Panel, daher konfliktfrei.
    root.innerHTML = `
      <div id="event-detail-panel" class="detail-panel hidden" hidden>
        <div class="detail-panel-overlay"></div>
        <div class="detail-panel-content">
          <button class="detail-panel-close" aria-label="Schließen">&times;</button>
          <div id="detail-content"></div>
        </div>
      </div>
    `;
    /* === END BLOCK: OFFER DETAIL DOM (create + attach to #offer-detail-root) === */

    this.panel = document.getElementById("event-detail-panel");
    this.overlay = this.panel?.querySelector(".detail-panel-overlay");
    this.content = document.getElementById("detail-content");
    this.closeBtn = this.panel?.querySelector(".detail-panel-close");

    if (!this.panel || !this.overlay || !this.content) {
      console.warn("OfferDetailPanel: elements not found");
      return;
    }

    this._onCloseClick = (e) => {
      e.preventDefault();
      this.hide();
    };
    if (this.closeBtn) this.closeBtn.addEventListener("click", this._onCloseClick);

    this._onOverlayClick = (e) => {
      if (e.target === this.overlay) this.hide();
    };
    this.overlay.addEventListener("click", this._onOverlayClick);

    this._onKeyDown = (e) => {
      if (e.key !== "Escape") return;
      if (!this.panel.classList.contains("active")) return;
      this.hide();
    };
    document.addEventListener("keydown", this._onKeyDown);

    this._isInit = true;
  },

  show(offer) {
    /* === BEGIN BLOCK: OFFER DETAIL SHOW (ensure init + open) ===
    Zweck: Panel robust öffnen (init + hidden/[hidden] entfernen + Focus-Handling).
    Umfang: show(offer) komplett.
    === */
    if (!this._isInit) this.init();

    if (!this.panel || !this.content) {
      console.error("❌ OfferDetailPanel.show aborted: panel/content missing", {
        hasPanel: !!this.panel,
        hasContent: !!this.content,
        isInit: this._isInit
      });
      return;
    }

    this._lastFocusEl =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    this.renderContent(offer);

    this.panel.classList.remove("hidden");
    this.panel.removeAttribute("hidden");

    requestAnimationFrame(() => {
      this.panel.classList.add("active");
      document.body.classList.add("is-panel-open");
      if (this.closeBtn) this.closeBtn.focus();
    });
    /* === END BLOCK: OFFER DETAIL SHOW (ensure init + open) === */
  },

  hide() {
    if (!this.panel) return;

    this.panel.classList.remove("active");
    document.body.classList.remove("is-panel-open");

    const sheet = this.panel.querySelector(".detail-panel-content");
    let ms = 260;
    if (sheet) {
      const dur = getComputedStyle(sheet).transitionDuration || "";
      const first = dur.split(",")[0].trim();
      if (first.endsWith("ms")) ms = Math.max(0, parseFloat(first));
      if (first.endsWith("s")) ms = Math.max(0, parseFloat(first) * 1000);
      if (!Number.isFinite(ms) || ms <= 0) ms = 260;
      ms = Math.round(ms + 40);
    }

    window.setTimeout(() => {
      this.panel.classList.add("hidden");
      this.panel.setAttribute("hidden", "");

      if (this._lastFocusEl && typeof this._lastFocusEl.focus === "function") {
        this._lastFocusEl.focus();
      }
      this._lastFocusEl = null;
    }, ms);
  },

  renderContent(offer) {
    /* === BEGIN BLOCK: OFFER DETAIL RENDER (final fields, no pills) ===
    Zweck: Offer-Detailpanel nach PROJECT.md:
    - vollständige Beschreibung
    - Zur Location (url) + Maps-Fallback
    - Card bleibt minimal, Details nur hier
    Umfang: renderContent(offer) komplett.
    === */
    const o = offer && typeof offer === "object" ? offer : {};

    const title = (o.title || "").toString().trim() || "Ohne Titel";
    const category = (o.kategorie || o.category || "").toString().trim();
    const location = (o.location || "").toString().trim();
    const description = (o.description || "").toString().trim();
    const hint = (o.hint || "").toString().trim();
    const url = (o.url || "").toString().trim();

    const iconMap = {
      Baden: "🏊",
      Natur: "🌿",
      Familie: "👨‍👩‍👧‍👦",
      Freizeit: "🎯",
      Kultur: "🎭"
    };
    const categoryIcon = category ? (iconMap[category] || "") : "";

    const isHttpUrl = (u) => /^https?:\/\//i.test(u || "");
    const safeUrl = (u) => (isHttpUrl(u) ? u : "");

    const mapsHref = (() => {
      if (!location) return "";
      if (window.Locations?.getMapsFallback) return window.Locations.getMapsFallback(location);
      return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(location)}`;
    })();

    const subline = [category, location].filter(Boolean).join(" · ");

    this.content.innerHTML = `
      <div class="detail-header">
        <h2>${this.escape(title)}</h2>

        ${categoryIcon ? `
          <span class="detail-category-icon" role="img" aria-label="Kategorie: ${this.escape(category)}">
            ${categoryIcon}
          </span>
        ` : ""}
      </div>

      ${subline ? `<div class="detail-subline">${this.escape(subline)}</div>` : ""}

      ${description ? `<div class="detail-description">${this.escape(description)}</div>` : ""}

               ${hint ? `
        <div class="detail-meta">
          <div class="detail-hint">${this.escape(hint)}</div>
        </div>
      ` : ""}

      <div class="detail-actions">
        ${safeUrl(url) ? `
          <a class="detail-link-btn" href="${safeUrl(url)}" target="_blank" rel="noopener">
            Zur Location
          </a>
        ` : ""}


        ${mapsHref ? `
          <a class="detail-link-btn" href="${mapsHref}" target="_blank" rel="noopener">
            In Maps öffnen
          </a>
        ` : ""}
      </div>
    `;
    /* === END BLOCK: OFFER DETAIL RENDER (final fields, no pills) === */
  },

  escape(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }
};

// global (offers.js erwartet window.OfferDetailPanel?.show)
window.OfferDetailPanel = OfferDetailPanel;
