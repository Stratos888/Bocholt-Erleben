/* === BEGIN BLOCK: DETAILPANEL MODULE (UX hardened, single-init, focus restore) ===
Zweck: Detailpanel robust & appig: kein Listener-Stacking, ESC/Overlay close, Focus-Restore,
       sauberes Timing zur CSS-Transition.
Umfang: Ersetzt den kompletten bisherigen Inhalt von js/details.js.
=== */

/**
 * DETAILS.JS – Detail-Panel Modul
 * Zeigt Event-Details in einem Slide-in Panel
 */
const DetailPanel = {
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

    this.panel = document.getElementById("event-detail-panel");
    this.overlay = this.panel?.querySelector(".detail-panel-overlay");
    this.content = document.getElementById("detail-content");
    this.closeBtn = this.panel?.querySelector(".detail-panel-close");

    if (!this.panel || !this.overlay || !this.content) {
      console.warn("DetailPanel: elements not found");
      return;
    }

    // Close button
    this._onCloseClick = (e) => {
      e.preventDefault();
      this.hide();
    };
    if (this.closeBtn) this.closeBtn.addEventListener("click", this._onCloseClick);

    // Overlay closes (but clicks inside the sheet do not bubble to overlay anyway)
    this._onOverlayClick = (e) => {
      // only close when actually clicking the overlay element
      if (e.target === this.overlay) this.hide();
    };
    this.overlay.addEventListener("click", this._onOverlayClick);

    // ESC closes only when open
    this._onKeyDown = (e) => {
      if (e.key !== "Escape") return;
      if (!this.panel.classList.contains("active")) return;
      this.hide();
    };
    document.addEventListener("keydown", this._onKeyDown);

    this._isInit = true;
    debugLog("DetailPanel initialized");
  },

  show(event) {
    if (!this.panel || !this.content) return;

    // remember focus for restore
    this._lastFocusEl = document.activeElement instanceof HTMLElement ? document.activeElement : null;

    this.renderContent(event);

    // make visible
    this.panel.classList.remove("hidden");

    // ensure button can be focused (optional, but helps keyboard users)
    requestAnimationFrame(() => {
      this.panel.classList.add("active");
      document.body.classList.add("is-panel-open");

      // focus close button if available
      if (this.closeBtn) this.closeBtn.focus();
    });
  },

  hide() {
    if (!this.panel) return;

    this.panel.classList.remove("active");
    document.body.classList.remove("is-panel-open");

    // Use transition duration from CSS if possible; fallback 260ms
    const sheet = this.panel.querySelector(".detail-panel-content");
    let ms = 260;
    if (sheet) {
      const dur = getComputedStyle(sheet).transitionDuration || "";
      // handle "0.22s, 0s" -> take first
      const first = dur.split(",")[0].trim();
      if (first.endsWith("ms")) ms = Math.max(0, parseFloat(first));
      if (first.endsWith("s")) ms = Math.max(0, parseFloat(first) * 1000);
      if (!Number.isFinite(ms) || ms <= 0) ms = 260;
      ms = Math.round(ms + 40); // small buffer for rAF/layout
    }

    window.setTimeout(() => {
      this.panel.classList.add("hidden");

      // restore focus (best effort)
      if (this._lastFocusEl && typeof this._lastFocusEl.focus === "function") {
        this._lastFocusEl.focus();
      }
      this._lastFocusEl = null;
    }, ms);
  },

  renderContent(event) {
    const title = event.title || "";
    const date = event.date ? formatDate(event.date) : "";
    const time = event.time || "";
    const location = event.location || "";
    const description = event.description || "";
    const url = event.url || "";

    this.content.innerHTML = `
      <div class="detail-header">
        <h2>${this.escape(title)}</h2>
        ${location ? `<div class="detail-location">${this.escape(location)}</div>` : ""}
      </div>

      <div class="detail-meta">
        ${date ? `<div><strong>Datum:</strong> ${date}</div>` : ""}
        ${time ? `<div><strong>Uhrzeit:</strong> ${this.escape(time)}</div>` : ""}
      </div>

      ${description ? `
        <div class="detail-description">
          <p>${this.escape(description)}</p>
        </div>
      ` : ""}

      ${url ? `
        <div class="detail-actions">
          <a href="${this.escape(url)}"
             target="_blank"
             rel="noopener noreferrer"
             class="detail-link-btn">
            Zum Event
          </a>
        </div>
      ` : ""}
    `;
  },

  escape(text) {
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  }
};

debugLog("DetailPanel loaded");
/* === END BLOCK: DETAILPANEL MODULE (UX hardened, single-init, focus restore) === */
