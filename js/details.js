// BEGIN: FILE_HEADER_DETAILS
// Datei: js/details.js
// Zweck:
// - Anzeige eines einzelnen Events im Detail-Panel
// - Öffnen/Schließen des Panels (Overlay, ESC, Close)
// - Darstellung aller Event-Details (Text, Meta, Links)
//
// Verantwortlich für:
// - Detail-Panel DOM
// - Panel-Interaktion (Open/Close)
// - Darstellung eines Events
//
// Nicht verantwortlich für:
// - Event-Listen oder Event-Filter
// - Filter-State oder Filter-UI
// - Laden von Event-Daten
//
// Contract:
// - erhält ein einzelnes Event-Objekt (z. B. DetailPanel.show(event))
// END: FILE_HEADER_DETAILS

 // (removed stray comment terminator)

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
  /* === BEGIN BLOCK: DETAILPANEL SHOW (ensure init + handle hidden attribute) ===
  Zweck: Systematisch öffnen: init() sicherstellen + sowohl class "hidden" als auch HTML-Attribut [hidden] entfernen.
  Umfang: Ersetzt show(event) komplett.
  === */
  if (!this._isInit) this.init();

  // Wenn init fehlgeschlagen ist, hart abbrechen (kein stilles Fail)
  if (!this.panel || !this.content) {
    console.error("❌ DetailPanel.show aborted: panel/content missing", {
      hasPanel: !!this.panel,
      hasContent: !!this.content,
      isInit: this._isInit
    });
    return;
  }

  // remember focus for restore
  this._lastFocusEl = document.activeElement instanceof HTMLElement ? document.activeElement : null;

  this.renderContent(event);

  // make visible (beide Mechaniken unterstützen: class + [hidden])
  this.panel.classList.remove("hidden");
  this.panel.removeAttribute("hidden");

  requestAnimationFrame(() => {
    this.panel.classList.add("active");
    document.body.classList.add("is-panel-open");

    if (this.closeBtn) this.closeBtn.focus();
  });
  /* === END BLOCK: DETAILPANEL SHOW (ensure init + handle hidden attribute) === */
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
       /* === BEGIN BLOCK: DETAIL RENDER (canonical fields, defensive) ===
    Zweck:
    - Top-App Zielzustand: keine Info-Pills im Panel.
    - Datum/Uhrzeit als ruhige Textzeile (nicht klickbar, keine Box).
    - Stadt + Datum/Uhrzeit als Meta-Zeile (wie Card): Stadt · Datum · Uhrzeit
    - Location als klare Action (klickbar): Homepage primär, Maps fallback.
    - Kategorie-Icon (ohne Text) im Header rechts oben.
    Umfang: Ersetzt renderContent(event) komplett.
    === */
    const e = event && typeof event === "object" ? event : {};

    const title = e.title || e.eventName || "";
    const cityRaw = (e.city || "").trim();
    const date = e.date ? formatDate(e.date) : "";
    const time = e.time || "";
    const locationRaw = (e.location || "").trim();
    const description = e.beschreibung || e.description || "";
    const kategorieRaw = (e.kategorie || "").trim();
    const url = e.url || "";

    const safeUrl = url ? String(url) : "";
    const isHttpUrl = /^https?:\/\//i.test(safeUrl);

    // Meta line (text-only): city · date · time
    const dateTimeText = [date, time ? this.escape(time) : ""].filter(Boolean).join(" · ");
    const sublineText = [cityRaw ? this.escape(cityRaw) : "", dateTimeText].filter(Boolean).join(" · ");

    // Kategorie-Icon (ohne Text)
    const iconMap = {
      Party: "🎉",
      Kneipe: "🍺",
      Kinder: "🧒",
      Quiz: "❓",
      Musik: "🎵",
      Kultur: "🎭"
    };
    const categoryIcon = kategorieRaw ? (iconMap[kategorieRaw] || "🗓️") : "";

    // Location action (homepage primary, maps fallback)
    let locationHref = "";
    if (locationRaw) {
      const homepage =
        (window.Locations?.getHomepage && window.Locations.getHomepage(locationRaw)) || "";
      locationHref =
        homepage ||
        (window.Locations?.getMapsFallback
          ? window.Locations.getMapsFallback(locationRaw)
          : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(locationRaw)}`);
    }

    this.content.innerHTML = `
      <div class="detail-header">
        <h2>${this.escape(title)}</h2>

        ${categoryIcon ? `
          <span class="detail-category-icon" role="img" aria-label="Kategorie: ${this.escape(kategorieRaw)}">
            ${categoryIcon}
          </span>
        ` : ""}

        ${sublineText ? `
          <div class="detail-subline">
            ${sublineText}
          </div>
        ` : ""}

        ${locationRaw && locationHref ? `
          <a class="detail-location-action detail-location-link"
             href="${this.escape(locationHref)}"
             target="_blank"
             rel="noopener noreferrer"
             aria-label="Ort öffnen: ${this.escape(locationRaw)}">
            <span class="detail-location-icon" aria-hidden="true">📍</span>
            <span class="detail-location-text">${this.escape(locationRaw)}</span>
            <span class="detail-location-chev" aria-hidden="true">›</span>
          </a>
        ` : ""}
      </div>

      ${description ? `
        <div class="detail-description">
          <p>${this.escape(description)}</p>
        </div>
      ` : ""}

      ${isHttpUrl ? `
        <div class="detail-actions">
          <a href="${this.escape(safeUrl)}"
             target="_blank"
             rel="noopener noreferrer"
             class="detail-link-btn">
            Zum Event
          </a>
        </div>
      ` : ""}
    `;
    /* === END BLOCK: DETAIL RENDER (canonical fields, defensive) === */


  },



  escape(text) {
    const div = document.createElement("div");
    div.textContent = String(text);
    return div.innerHTML;
  }
};

/* === BEGIN BLOCK: DETAILPANEL LOAD + GLOBAL EXPORT (window.DetailPanel) ===
Zweck: DetailPanel für EventCards global verfügbar machen (window.DetailPanel), sonst bleibt Card-Klick wirkungslos.
Umfang: Ersetzt nur den finalen Load-Log durch Export + Proof-Log.
=== */
window.DetailPanel = DetailPanel;
debugLog("DetailPanel loaded (global export OK)", {
  hasDetailPanel: typeof window.DetailPanel !== "undefined",
  hasShow: typeof window.DetailPanel?.show === "function"
});
/* === END BLOCK: DETAILPANEL LOAD + GLOBAL EXPORT (window.DetailPanel) === */

/* === END BLOCK: DETAILPANEL MODULE (UX hardened, single-init, focus restore) === */








