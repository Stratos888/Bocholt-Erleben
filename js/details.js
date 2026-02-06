// === BEGIN FILE: js/details.js (DETAILPANEL MODULE – CONSOLIDATED, SINGLE SOURCE OF TRUTH) ===
// Zweck:
// - Anzeige eines einzelnen Events im Detail-Panel (Bottom-Sheet)
// - Öffnen/Schließen (Overlay, ESC, Close)
// - Swipe-Interaktion (Drag via CSS-Variablenmodell), Snap HALF/FULL, Close
// - A11y: aria-hidden korrekt + Fokus merken/restore
// Umfang:
// - Komplette Datei ersetzt/konsolidiert (entfernt Patch-Reste, Doppel-Logik, Inkonsistenzen)
// ===

(() => {
  "use strict";

  // === BEGIN BLOCK: DETAILPANEL HELPERS (pure utils) ===
  // Zweck: Kleine Hilfsfunktionen (defensiv, keine Seiteneffekte)
  // Umfang: Nur Helper
  const clamp = (v, min, max) => Math.max(min, Math.min(max, v));

  const safeText = (v) => (typeof v === "string" ? v : (v == null ? "" : String(v)));

  const trimOrEmpty = (v) => safeText(v).trim();

  const escapeHtml = (s) =>
    safeText(s)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  const toMapsUrl = (q) => {
    const query = trimOrEmpty(q);
    if (!query) return "";
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
  };

  const getCategoryIcon = (category) => {
    const c = trimOrEmpty(category);
    if (!c) return "";
    const iconMap = {
      "Musik": "🎵",
      "Kultur": "🎭",
      "Essen & Trinken": "🍽️",
      "Sport": "🏃",
      "Familie": "👨‍👩‍👧‍👦",
      "Natur & Draußen": "🌿",
      "Innenstadt & Leben": "🏙️",
    };
    return iconMap[c] || "🗓️";
  };

  const getLocationHomepage = (locationName) => {
    const name = trimOrEmpty(locationName);
    if (!name) return "";
    try {
      if (window.Locations && typeof window.Locations.getHomepage === "function") {
        const url = window.Locations.getHomepage(name);
        return trimOrEmpty(url);
      }
    } catch (_) {}
    return "";
  };
  // === END BLOCK: DETAILPANEL HELPERS (pure utils) ===

  // === BEGIN BLOCK: DETAILPANEL MODULE (single object) ===
  // Zweck: Ein einziges konsistentes API-Objekt: init/show/hide/renderContent
  // Umfang: Komplettes DetailPanel
  const DetailPanel = {
    // DOM refs
    panel: null,
    overlay: null,
    sheet: null,
    content: null,
    closeBtn: null,
    dragHit: null,

    // state
    _isInit: false,
    _lastFocusEl: null,
    _savedBodyOverflow: null,

    // handlers
    _onKeyDown: null,
    _onOverlayClick: null,
    _onCloseClick: null,
    _onPopState: null,

    // drag state
    _drag: {
      startY: 0,
      dy: 0,
      dragging: false,
    },

    init() {
      if (this._isInit) return;

      this.panel = document.getElementById("event-detail-panel");
      if (!this.panel) return;

      this.overlay = this.panel.querySelector(".detail-panel-overlay");
      this.sheet = this.panel.querySelector(".detail-panel-content");
      this.content = this.panel.querySelector("#detail-content");
      this.closeBtn = this.panel.querySelector(".detail-panel-close");

      // A11y semantics
      this.panel.setAttribute("role", "dialog");
      this.panel.setAttribute("aria-modal", "true");
      this.panel.setAttribute("aria-hidden", "true");
      if (this.closeBtn) this.closeBtn.setAttribute("aria-label", "Schließen");

      // Ensure drag hit area exists
      if (this.sheet) {
        this.dragHit = this.sheet.querySelector(".detail-panel-drag-hit");
        if (!this.dragHit) {
          this.dragHit = document.createElement("div");
          this.dragHit.className = "detail-panel-drag-hit";
          this.sheet.appendChild(this.dragHit);
        }
      }

      // Overlay click closes
      this._onOverlayClick = (e) => {
        if (e.target === this.overlay) this.hide();
      };
      this.overlay?.addEventListener("click", this._onOverlayClick);

      // Close button closes
      this._onCloseClick = () => this.hide();
      this.closeBtn?.addEventListener("click", this._onCloseClick);

      // ESC + Focus trap (only while active)
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

      // Back button closes if open
      this._onPopState = () => {
        if (this.panel && this.panel.classList.contains("active")) {
          this._hideNow();
        }
      };
      window.addEventListener("popstate", this._onPopState);

      // Drag handlers (VAR MODEL)
      if (this.dragHit && this.sheet) {
        this.dragHit.addEventListener("pointerdown", (e) => this._onDragStart(e));
        this.dragHit.addEventListener("pointermove", (e) => this._onDragMove(e), { passive: false });
        this.dragHit.addEventListener("pointerup", () => this._onDragEnd());
        this.dragHit.addEventListener("pointercancel", () => this._onDragEnd());
      }

      this._isInit = true;
    },

    // CSS var setters (single source of truth)
    setDragY(px) {
      if (!this.sheet) return;
      this.sheet.style.setProperty("--dp-drag-y", `${px}px`);
    },

    setBase(v) {
      if (!this.sheet) return;
      this.sheet.style.setProperty("--dp-base-y", v);
    },

    // show starts HALF
    show(event) {
      this.init();
      if (!this.panel || !this.sheet || !this.content) return;

      // remember focus to restore on close
      this._lastFocusEl = document.activeElement instanceof HTMLElement ? document.activeElement : null;

      // render
      this.renderContent(event);

      // set snap start HALF (60% visible => translateY 40vh)
      this.setDragY(0);
      this.setBase("40vh");

      // open
      this.panel.classList.remove("hidden");
      this.panel.classList.add("active");
      this.panel.setAttribute("aria-hidden", "false");

      // body scroll lock (defensive)
      if (this._savedBodyOverflow == null) {
        this._savedBodyOverflow = document.body.style.overflow || "";
      }
      document.body.style.overflow = "hidden";

      // history state (avoid stacking)
      if (!history.state?.detailOpen) {
        history.pushState({ ...(history.state || {}), detailOpen: true }, "");
      }

      // focus close after transition
      const focusClose = () => this.closeBtn?.focus({ preventScroll: true });
      this.panel.addEventListener("transitionend", focusClose, { once: true });
    },

    hide() {
      if (history.state?.detailOpen) {
        history.back();
        return;
      }
      this._hideNow();
    },

    _hideNow() {
      if (!this.panel) return;

      // remove focus from inside panel to avoid aria-hidden warnings
      if (this.panel.contains(document.activeElement)) {
        try { document.activeElement.blur(); } catch (_) {}
      }

      this.panel.setAttribute("aria-hidden", "true");
      this.panel.classList.remove("active");
      this.panel.classList.add("hidden");

      // reset snap vars
      this.setDragY(0);
      this.setBase("100%");

      // restore body scroll
      if (this._savedBodyOverflow != null) {
        document.body.style.overflow = this._savedBodyOverflow;
        this._savedBodyOverflow = null;
      }

      // restore focus
      if (this._lastFocusEl && typeof this._lastFocusEl.focus === "function") {
        try { this._lastFocusEl.focus({ preventScroll: true }); } catch (_) {}
      }
      this._lastFocusEl = null;
    },

    _onDragStart(e) {
      if (!this.sheet || !this.content) return;

      // only drag if scroll at top
      if (this.content.scrollTop > 0) return;

      this._drag.startY = e.clientY;
      this._drag.dy = 0;
      this._drag.dragging = true;

      this.sheet.classList.add("is-dragging");
      this.setDragY(0);

      try { this.dragHit?.setPointerCapture(e.pointerId); } catch (_) {}
    },

    _onDragMove(e) {
      if (!this._drag.dragging || !this.sheet) return;

      this._drag.dy = e.clientY - this._drag.startY;

      // allow both directions; clamp
      const clamped = clamp(this._drag.dy, -220, 360);
      this.setDragY(clamped);

      e.preventDefault();
    },

    _onDragEnd() {
      if (!this.sheet) return;

      const moved = this._drag.dy;

      this._drag.dragging = false;
      this._drag.dy = 0;

      this.sheet.classList.remove("is-dragging");

      // thresholds
      const CLOSE_T = 90;   // down closes
      const UP_T = -70;     // up expands

      if (moved > CLOSE_T) {
        this.setDragY(0);
        this.hide();
        return;
      }

      if (moved < UP_T) {
        // FULL
        this.setDragY(0);
        this.setBase("0px");
        return;
      }

      // snap back to current base (HALF or FULL)
      const base = getComputedStyle(this.sheet).getPropertyValue("--dp-base-y").trim();
      this.setDragY(0);
      if (base === "0px") {
        this.setBase("0px");
      } else {
        this.setBase("40vh");
      }
    },

    renderContent(event) {
      // Defensive normalize
      const e = (event && typeof event === "object") ? event : {};

      const title = trimOrEmpty(e.title || e.name || e.summary) || "Event";
      const city = trimOrEmpty(e.city || e.stadt || e.locationCity || "Bocholt");
      const date = trimOrEmpty(e.date || e.datum || e.startDate || "");
      const time = trimOrEmpty(e.time || e.uhrzeit || e.startTime || "");
      const endTime = trimOrEmpty(e.endTime || e.ende || "");

      const timeRange = (() => {
        const t = time;
        const et = endTime;
        if (t && et) return `${t}–${et}`;
        return t || "";
      })();

      const category = trimOrEmpty(e.category || e.kategorie || "");
      const icon = getCategoryIcon(category);

      const locationName = trimOrEmpty(e.locationName || e.location || e.ort || "");
      const locationArea = trimOrEmpty(e.locationArea || e.area || e.stadtteil || "");
      const locationLabel = [locationName, locationArea].filter(Boolean).join(" ").trim();

      const desc = trimOrEmpty(e.description || e.beschreibung || "");

      const homepage = getLocationHomepage(locationName);
      const maps = toMapsUrl([locationName, city].filter(Boolean).join(" "));

      // Minimal, stable markup (no extra features here)
      const html = `
        <div class="detail-panel-inner">
          <div class="detail-header">
            <div class="detail-title-row">
              <h2 class="detail-title">${escapeHtml(title)}</h2>
              ${icon ? `<span class="detail-category-icon" aria-hidden="true">${escapeHtml(icon)}</span>` : ""}
            </div>
            <div class="detail-meta">${escapeHtml([city, date, timeRange].filter(Boolean).join(" · "))}</div>
          </div>

          ${locationLabel ? `
            <div class="detail-location">
              <span class="detail-pin" aria-hidden="true">📍</span>
              <div class="detail-location-main">
                <div class="detail-location-name">${escapeHtml(locationLabel)}</div>
                <div class="detail-location-actions">
                  ${homepage ? `<a class="detail-action" href="${escapeHtml(homepage)}" target="_blank" rel="noopener">Website</a>` : ""}
                  ${maps ? `<a class="detail-action" href="${escapeHtml(maps)}" target="_blank" rel="noopener">Karte</a>` : ""}
                </div>
              </div>
            </div>
          ` : ""}

          ${desc ? `<div class="detail-description">${escapeHtml(desc)}</div>` : ""}
        </div>
      `;

      this.content.innerHTML = html;

      // reset scroll on open
      this.content.scrollTop = 0;
    },
  };
  // === END BLOCK: DETAILPANEL MODULE (single object) ===

  // === BEGIN BLOCK: DETAILPANEL LOAD + GLOBAL EXPORT (window.DetailPanel) ===
  // Zweck: Globales API für andere Module (events.js etc.)
  // Umfang: Export + DOMContentLoaded init
  window.DetailPanel = DetailPanel;

  document.addEventListener("DOMContentLoaded", () => {
    try { DetailPanel.init(); } catch (_) {}
  });
  // === END BLOCK: DETAILPANEL LOAD + GLOBAL EXPORT (window.DetailPanel) ===
})();

// === END FILE: js/details.js (DETAILPANEL MODULE – CONSOLIDATED, SINGLE SOURCE OF TRUTH) ===
