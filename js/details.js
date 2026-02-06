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

// === BEGIN BLOCK: DETAILPANEL INIT (syntax-fix + stable close + esc + focus-trap) ===
// Zweck: Syntaxfehler beheben; Close/Overlay/ESC zuverlässig; Focus-Trap nur wenn Panel aktiv.
// Umfang: Ersetzt init() komplett.
// ===
init() {
  if (this._isInit) return;

  // === BEGIN BLOCK: DETAILPANEL DOM HOOKS (selectors aligned) ===
  // Zweck: Panel/Overlay/Content/Close-Button selektieren (Close = .detail-panel-close).
  // Umfang: DOM-Queries.
  this.panel = document.getElementById("event-detail-panel");
  if (!this.panel) return;

  this.overlay = this.panel.querySelector(".detail-panel-overlay");
  this.content = this.panel.querySelector("#detail-content");
  this.closeBtn = this.panel.querySelector(".detail-panel-close");
  // === END BLOCK: DETAILPANEL DOM HOOKS (selectors aligned) ===

  /* ===============================
     A11y / Dialog semantics
  =============================== */
  this.panel.setAttribute("role", "dialog");
  this.panel.setAttribute("aria-modal", "true");
  this.panel.setAttribute("aria-hidden", "true");
  if (this.closeBtn) this.closeBtn.setAttribute("aria-label", "Schließen");

  /* ===============================
     Close interactions
  =============================== */
  this._onOverlayClick = (e) => {
    if (e.target === this.overlay) this.hide();
  };
  this.overlay?.addEventListener("click", this._onOverlayClick);

  this._onCloseClick = () => this.hide();
  this.closeBtn?.addEventListener("click", this._onCloseClick);

  /* ===============================
     ESC + Focus Trap
  =============================== */
  this._onKeyDown = (e) => {
    if (!this.panel || !this.panel.classList.contains("active")) return;

    if (e.key === "Escape") {
      e.preventDefault();
      this.hide();
      return;
    }

    if (e.key !== "Tab") return;

    const focusables = [...this.panel.querySelectorAll(
      'a[href],button:not([disabled]),[tabindex]:not([tabindex="-1"])'
    )];

    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
      return;
    }
    if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  };
  document.addEventListener("keydown", this._onKeyDown);

  /* ===============================
     Back button closes panel
  =============================== */
  window.addEventListener("popstate", () => {
    if (this.panel && this.panel.classList.contains("active")) {
      this._hideNow();
    }
  });

    /* ===============================
     Swipe to close (mobile) – VAR MODEL (no inline transform)
  =============================== */
  const sheet = this.panel.querySelector(".detail-panel-content");
  const scroll = this.panel.querySelector("#detail-content");

  // Ensure a dedicated drag hit-area exists (CSS already styles .detail-panel-drag-hit)
  let dragHit = sheet?.querySelector(".detail-panel-drag-hit");
  if (sheet && !dragHit) {
    dragHit = document.createElement("div");
    dragHit.className = "detail-panel-drag-hit";
    sheet.appendChild(dragHit);
  }

  let startY = 0;
  let dy = 0;
  let dragging = false;

  const setDragY = (px) => {
    if (!sheet) return;
    sheet.style.setProperty("--dp-drag-y", `${px}px`);
  };

  dragHit?.addEventListener("pointerdown", (e) => {
    if (!sheet || !scroll) return;
    if (scroll.scrollTop > 0) return;

    startY = e.clientY;
    dy = 0;
    dragging = true;

    sheet.classList.add("is-dragging");
    setDragY(0);

    try { dragHit.setPointerCapture(e.pointerId); } catch (_) {}
  });

   dragHit?.addEventListener("pointermove", (e) => {
    if (!dragging || !sheet) return;

    dy = e.clientY - startY;

    // allow both directions; clamp both ways
    const clamped = Math.max(Math.min(dy, 360), -220);
    setDragY(clamped);

    e.preventDefault();
  }, { passive: false });


   const endDrag = () => {
    if (!sheet) return;
    dragging = false;

    sheet.classList.remove("is-dragging");

    const moved = dy;
    dy = 0;

    // thresholds
    const CLOSE_T = 90;     // down
    const UP_T = -70;       // up

    // current base state
    const base = getComputedStyle(sheet).getPropertyValue("--dp-base-y").trim();

    const setBase = (v) => sheet.style.setProperty("--dp-base-y", v);

    // snap decision
    if (moved > CLOSE_T) {
      setDragY(0);
      this.hide();
      return;
    }

    if (moved < UP_T) {
      // swipe up -> FULL
      setDragY(0);
      setBase("0px");
      return;
    }

    // otherwise snap back to current state (HALF or FULL)
    setDragY(0);
    if (base === "0px") {
      setBase("0px");
    } else {
      setBase("40vh"); // HALF
    }
  };

  dragHit?.addEventListener("pointerup", endDrag);
  dragHit?.addEventListener("pointercancel", endDrag);


  this._isInit = true;
},

// === END BLOCK: DETAILPANEL INIT (syntax-fix + stable close + esc + focus-trap) ===



// === BEGIN BLOCK: DETAILPANEL SHOW (snap default HALF) ===
// Zweck: Panel öffnet im HALF-Snap (60% sichtbar), History-State bleibt wie gehabt.
// Umfang: Ersetzt show() komplett.
// ===
show(event) {
  this.init();
  if (!this.panel) return;

  this.renderContent(event);

  // start at HALF (60% visible)
  const sheet = this.panel.querySelector(".detail-panel-content");
  sheet?.style.setProperty("--dp-drag-y", "0px");
  sheet?.style.setProperty("--dp-base-y", "40vh"); // 60% sichtbar

  this.panel.classList.remove("hidden");
  this.panel.classList.add("active");
  this.panel.setAttribute("aria-hidden", "false");

  if (!history.state?.detailOpen) {
    history.pushState({ ...(history.state || {}), detailOpen: true }, "");
  }

  // focus close after open transition
  const focusClose = () => this.closeBtn?.focus({ preventScroll: true });
  this.panel.addEventListener("transitionend", focusClose, { once: true });
},
// === END BLOCK: DETAILPANEL SHOW (snap default HALF) ===



   // === BEGIN BLOCK: DETAILPANEL HIDE (syntax-fix + history-safe) ===
  // Zweck: Syntaxfehler beheben; Schließen via History sauber; kein "hängender" return-Block.
  // Umfang: Ersetzt hide() komplett (endet direkt vor _hideNow()).
 // === BEGIN BLOCK: DETAILPANEL HIDE (syntax-fix + history-safe) ===
// Zweck: Syntax korrekt im Object-Literal (Komma zwischen Methoden); Schließen via History sauber.
// Umfang: Ersetzt hide() + die folgende _hideNow()-Signaturzeile.
// ===
hide() {
  if (history.state?.detailOpen) {
    history.back();
    return;
  }
  this._hideNow();
},
// === BEGIN BLOCK: DETAILPANEL A11Y CLOSE (aria-hidden + defocus) ===
// Zweck: Beim Schließen Fokus aus dem Panel nehmen und aria-hidden auf true setzen.
// Umfang: Ersetzt _hideNow()-Header + Panel-Guard.
_hideNow() {
  if (!this.panel) return;

  // move focus out of the panel to avoid aria-hidden focused descendant warnings
  if (this.panel.contains(document.activeElement)) {
    document.activeElement.blur();
  }

  this.panel.setAttribute("aria-hidden", "true");
// === END BLOCK: DETAILPANEL A11Y CLOSE (aria-hidden + defocus) ===


    this.panel.classList.remove("active");
    document.body.classList.remove("is-panel-open");
    this.panel.setAttribute("aria-hidden", "true");

    // Use transition duration from CSS if possible; fallback 260ms
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
    - Stadt + Datum/Uhrzeit als Meta-Zeile (wie Card): Stadt · Datum · Uhrzeit
    - Location als klare Action (klickbar): Homepage primär, Maps fallback.
    - Kategorie-Icon (ohne Text) im Header rechts oben.
    Umfang: Ersetzt renderContent(event) komplett.
    === */
    const e = event && typeof event === "object" ? event : {};

      /* === BEGIN BLOCK: DETAIL DATE/TIME (support endDate range in subline) ===
    Zweck:
    - Range-Events im DetailPanel korrekt anzeigen: Start–Ende (endDate) in der Meta-Zeile.
    - Bestehendes Verhalten unverändert lassen, wenn kein endDate vorhanden ist.
    Umfang:
    - Ersetzt die lokale Datum/Uhrzeit-Aufbereitung in renderContent(event) (title/date/time/meta line).
    === */
    const title = e.title || e.eventName || "";

    const time = e.time || "";
            /* === BEGIN BLOCK: detail meta inputs (use event consistently) === */
    const locationRaw = (event.location || "").trim();
    const city = (event.city || event.ort || "").trim();
    const description = event.beschreibung || event.description || "";
    const kategorieRaw = (event.kategorie || "").trim();

    // Event-URL robust normalisieren (inkl. "www." ohne Scheme)
    const rawUrl = String(event.url || "").trim();

    const normalizeExternalUrl = (u) => {
      if (!u) return "";
      // already absolute
      if (/^https?:\/\//i.test(u)) return u;
      // protocol-relative
      if (/^\/\//.test(u)) return `https:${u}`;
      // common "www." case
      if (/^www\./i.test(u)) return `https://${u}`;
      return "";
    };

    const eventUrl = normalizeExternalUrl(rawUrl);
    const hasEventUrl = !!eventUrl;

    const startIso = event.date || "";
    const endIso = event.endDate || event.end_date || event.enddate || "";
    /* === END BLOCK: detail meta inputs (use event consistently) === */




    const formatShortDate = (iso) => {
      if (!iso) return "";
      const d = new Date(iso);
      if (Number.isNaN(d.getTime())) return "";
      return d.toLocaleDateString("de-DE", { day: "2-digit", month: "2-digit" });
    };

    const dateLabel = (() => {
      if (!startIso) return "";
      const start = formatShortDate(startIso);
      const end = formatShortDate(endIso);

      if (end && endIso !== startIso) {
        // Produktregel: Zeitraum anzeigen (z. B. 20.11 – 10.01)
        return `${start} – ${end}`.trim();
      }

      // Fallback: bestehendes Verhalten (inkl. Jahr)
      return formatDate(startIso);
    })();

    // Meta line (text-only): city · date · time
    const dateTimeText = [dateLabel ? this.escape(dateLabel) : "", time ? this.escape(time) : ""]
      .filter(Boolean)
      .join(" · ");
    const sublineText = [city ? this.escape(city) : "", dateTimeText].filter(Boolean).join(" · ");
    /* === END BLOCK: DETAIL DATE/TIME (support endDate range in subline) === */


    /* === BEGIN BLOCK: CATEGORY ICON (canonical + consistent with filter/sheet) ===
Zweck:
- Icons nutzen dieselben Canonical Categories wie Filter & Google Sheet
- Single Source of Truth: FilterModule.normalizeCategory()
- kein Legacy-Mapping mehr
=== */

const canonicalCategory =
  (window.FilterModule?.normalizeCategory ? window.FilterModule.normalizeCategory(kategorieRaw) : "") ||
  kategorieRaw;

const iconMap = {
  "Märkte & Feste": "🧺",
  "Kultur & Kunst": "🎭",
  "Musik & Bühne": "🎵",
  "Kinder & Familie": "🧒",
  "Sport & Bewegung": "🏃",
  "Natur & Draußen": "🌿",
  "Innenstadt & Leben": "🏙️",
};

const categoryIcon = canonicalCategory ? (iconMap[canonicalCategory] || "🗓️") : "";

/* === END BLOCK: CATEGORY ICON (canonical + consistent with filter/sheet) === */


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

                 ${(() => {
        if (!eventUrl) return "";

        // bocholt.de "Veranstaltung*" Pfade sind oft instabil/404 → nicht anbieten
        let isUnstableBocholt = false;
        try {
          const u = new URL(eventUrl);
          const host = (u.hostname || "").toLowerCase();
          const path = (u.pathname || "").toLowerCase();

          if (host.endsWith("bocholt.de")) {
            isUnstableBocholt =
              path.startsWith("/veranstaltungskalender") ||
              path.startsWith("/veranstaltung") ||
              path.startsWith("/veranstaltungen");
          }
        } catch (_) {
          return "";
        }
        if (isUnstableBocholt) return "";

        // Redundanz: Event-URL == Location-URL → nicht doppelt anbieten
        const normalizeForCompare = (href) => {
          try {
            const u = new URL(href);
            u.hash = "";
            u.search = "";
            const s = u.toString();
            return s.endsWith("/") ? s.slice(0, -1) : s;
          } catch (_) {
            return "";
          }
        };

        const a = normalizeForCompare(eventUrl);
        const b = normalizeForCompare(locationHref);
        if (a && b && a === b) return "";

        return `
          <div class="detail-actions">
            <a href="${this.escape(eventUrl)}"
               target="_blank"
               rel="noopener noreferrer"
               class="detail-secondary-link"
               aria-label="Website öffnen">
              Website
            </a>
          </div>
        `;
      })()}


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
































