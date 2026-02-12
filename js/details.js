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
  // Zweck: Kleine Hilfsfunktionen + Normalisierung in ein Detail-ViewModel (defensiv, keine Seiteneffekte)
  // Umfang: Nur Helper + VM Builder (keine DOM-Operationen)
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

  const isSafeHttpUrl = (url) => {
    const u = trimOrEmpty(url);
    if (!u) return false;
    try {
      const parsed = new URL(u, window.location.origin);
      return parsed.protocol === "http:" || parsed.protocol === "https:";
    } catch (_) {
      return false;
    }
  };

  const normalizeHttpUrl = (url) => (isSafeHttpUrl(url) ? trimOrEmpty(url) : "");

  const toMapsUrl = (q) => {
    const query = trimOrEmpty(q);
    if (!query) return "";
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
  };

  const normalizeCategory = (raw) => {
    // canonical keys for later use (UI can stay on display/raw for now)
    const r = trimOrEmpty(raw);
    if (!r) return { raw: "", canonical: "" };

    const map = {
      "Musik": "music",
      "Kultur": "culture",
      "Essen & Trinken": "food",
      "Sport": "sports",
      "Familie": "family",
      "Natur & Draußen": "nature",
      "Innenstadt & Leben": "citylife",
    };

    return { raw: r, canonical: map[r] || "other" };
  };

  const getCategoryIcon = (categoryRaw) => {
    const c = trimOrEmpty(categoryRaw);
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

  // === BEGIN BLOCK: LOCATION HOMEPAGE LOOKUP (robust matching) ===
  // Zweck: Homepage lookup robust gegen Klammerzusätze / Stadtteil-Suffixe, ohne Datenmodell zu ändern
  // Umfang: Nur getLocationHomepage + interne Normalisierung (keine DOM/Side-Effects)
  const normalizeLocationForLookup = (s) => {
    const raw = trimOrEmpty(s);
    if (!raw) return "";

    // remove any parenthetical suffix like " (Innenstadt)"
    let out = raw.replace(/\s*\([^)]*\)\s*/g, " ").replace(/\s+/g, " ").trim();

    // remove trailing separators
    out = out.replace(/\s*[-–,;:]\s*$/g, "").trim();

    return out;
  };

  const getLocationHomepage = (locationName) => {
    const name = trimOrEmpty(locationName);
    if (!name) return "";

    try {
      if (window.Locations && typeof window.Locations.getHomepage === "function") {
        // Try multiple keys, from most specific to more normalized
        const candidates = [
          name,
          normalizeLocationForLookup(name),
        ].filter(Boolean);

        for (const c of candidates) {
          const url = window.Locations.getHomepage(c);
          const norm = normalizeHttpUrl(url);
          if (norm) return norm;
        }
      }
    } catch (_) {}

    return "";
  };
  // === END BLOCK: LOCATION HOMEPAGE LOOKUP (robust matching) ===


  const splitTimeRange = (raw) => {
    const t = trimOrEmpty(raw);
    if (!t) return { start: "", end: "" };

    // Accept "08:00–13:00", "08:00-13:00", "08:00 – 13:00", etc.
    const m = t.match(/^(.+?)(?:\s*[–-]\s*)(.+)$/);
    if (!m) return { start: t, end: "" };

    const start = trimOrEmpty(m[1]);
    const end = trimOrEmpty(m[2]);
    return { start, end };
  };

  // === BEGIN BLOCK: toEventDetailVM (VIEWMODEL NORMALIZER) ===
  // Zweck: Event-Objekt defensiv in kanonisches Detail-ViewModel normalisieren (keine DOM/Side-Effects)
  // Umfang: Nur Datenaufbereitung inkl. Actions (Kalender/WhatsApp/Website/Route) – Rendering später
  const toEventDetailVM = (event) => {
    const e = (event && typeof event === "object") ? event : {};

    const title = trimOrEmpty(e.title || e.name || e.summary) || "Event";
    const city = trimOrEmpty(e.city || e.stadt || e.locationCity || "Bocholt");
    const date = trimOrEmpty(e.date || e.datum || e.startDate || "");

    const timeRaw = trimOrEmpty(e.time || e.uhrzeit || e.startTime || "");
    const endTimeRaw = trimOrEmpty(e.endTime || e.ende || "");

    const parsed = splitTimeRange(timeRaw);
    const startTime = parsed.start || "";
    const endTime = endTimeRaw || parsed.end || "";

    const timeRange = (() => {
      // Keep current behavior: if we have both start+end => "start–end"; else show start (which may already include a range if unparsable)
      if (startTime && endTime) return `${startTime}–${endTime}`;
      return startTime || "";
    })();

    const categoryRaw = trimOrEmpty(e.category || e.kategorie || "");
    const categoryDisplay = categoryRaw;
    const icon = getCategoryIcon(categoryDisplay);

    const locationName = trimOrEmpty(e.locationName || e.location || e.ort || "");
    const locationArea = trimOrEmpty(e.locationArea || e.area || e.stadtteil || "");
    const locationLabel = [locationName, locationArea].filter(Boolean).join(" ").trim();

    const desc = trimOrEmpty(e.description || e.beschreibung || "");

    const homepage = getLocationHomepage(locationName);

    // Route (Maps) only when we have a meaningful location; avoids city-only links
    const routeQuery = locationName ? [locationName, city].filter(Boolean).join(" ") : "";
    const maps = routeQuery ? toMapsUrl(routeQuery) : "";

    // Kalender-Payload (ICS/Export-Implementierung folgt später)
    const canCalendar = Boolean(title && date);
    const calendarPayload = canCalendar ? {
      title,
      date,         // erwartetes Format in unseren Daten: YYYY-MM-DD
      startTime,    // optional
      endTime,      // optional
      location: locationName || city,
      description: desc,
    } : null;

    // WhatsApp-Share-Text (Deep-Link kann später im Renderer ergänzt werden)
    const shareParts = [
      title,
      date ? `📅 ${date}${timeRange ? ` · ${timeRange}` : ""}` : "",
      locationLabel ? `📍 ${locationLabel}` : "",
    ].filter(Boolean);
    const shareText = shareParts.join("\n");
    const whatsappHref = shareText ? `https://wa.me/?text=${encodeURIComponent(shareText)}` : "";

    // Actions (noch nicht gerendert; canonical Liste für spätere UI)
    const actions = [
      ...(canCalendar ? [{
        type: "calendar",
        label: "Kalender",
        priority: "primary",
        payload: calendarPayload,
      }] : []),

      ...(whatsappHref ? [{
        type: "whatsapp",
        label: "Teilen",
        priority: "secondary",
        href: whatsappHref,
        payload: { text: shareText },
      }] : []),

      ...(homepage ? [{
        type: "website",
        label: "Website",
        priority: "secondary",
        href: homepage,
      }] : []),

      ...(maps ? [{
        type: "route",
        label: "Route",
        priority: "utility",
        href: maps,
      }] : []),
    ];

    return {
      // current render fields (keep stable to avoid UI regression)
      title,
      city,
      date,
      startTime,
      endTime,
      timeRange,
      categoryRaw,
      categoryDisplay,
      icon,
      locationName,
      locationArea,
      locationLabel,
      desc,
      homepage,
      maps,

      // future usage
      actions,
    };
  };
  // === END BLOCK: toEventDetailVM (VIEWMODEL NORMALIZER) ===

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

       /* === BEGIN PATCH: DOWNWARD DRAG ONLY (disable upward expand) === */
    _onDragMove(e) {
      if (!this._drag.dragging || !this.sheet) return;

      this._drag.dy = e.clientY - this._drag.startY;

      // only allow downward movement
      const clamped = clamp(this._drag.dy, 0, 360);
      this.setDragY(clamped);

      e.preventDefault();
    },
    /* === END PATCH: DOWNWARD DRAG ONLY (disable upward expand) === */


    _onDragEnd() {
      if (!this.sheet) return;

      const moved = this._drag.dy;

      this._drag.dragging = false;
      this._drag.dy = 0;

      this.sheet.classList.remove("is-dragging");

      // thresholds
           /* === BEGIN PATCH: REMOVE UPWARD EXPAND LOGIC === */
      const CLOSE_T = 90;   // down closes only

      if (moved > CLOSE_T) {
        this.setDragY(0);
        this.hide();
        return;
      }
      /* === END PATCH: REMOVE UPWARD EXPAND LOGIC === */


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
      const vm = toEventDetailVM(event);

      const renderAction = (a) => {
        if (!a || typeof a !== "object") return "";
        const label = escapeHtml(a.label || "");

        if (a.type === "calendar") {
          // Render as button; click handler is attached after render (ICS download)
          return `<button class="detail-actionbar-btn" type="button" data-action="calendar">${label}</button>`;
        }

        if (a.href) {
          const href = escapeHtml(a.href);
          return `<a class="detail-actionbar-btn" href="${href}" target="_blank" rel="noopener">${label}</a>`;
        }

        return "";
      };

      const actionsHtml = (Array.isArray(vm.actions) ? vm.actions : [])
        .map(renderAction)
        .filter(Boolean)
        .join("");

      // Minimal, stable markup (keeps current layout), plus action-bar below description
      const html = `
        <div class="detail-panel-inner">
          <div class="detail-header">
            <div class="detail-title-row">
              <h2 class="detail-title">${escapeHtml(vm.title)}</h2>
              ${vm.icon ? `<span class="detail-category-icon" aria-hidden="true">${escapeHtml(vm.icon)}</span>` : ""}
            </div>
            <div class="detail-meta">${escapeHtml([vm.city, vm.date, vm.timeRange].filter(Boolean).join(" · "))}</div>
          </div>

                             ${vm.locationLabel ? `
            ${vm.homepage ? `
              <a
                class="detail-location-action"
                href="${escapeHtml(vm.homepage)}"
                target="_blank"
                rel="noopener"
                aria-label="Website der Location öffnen"
              >
                <span class="detail-location-icon" aria-hidden="true">📍</span>
                <span class="detail-location-text">${escapeHtml(vm.locationLabel)}</span>
                <span class="detail-location-chev" aria-hidden="true">›</span>
              </a>
            ` : `
              <div class="detail-location-action" aria-label="Location">
                <span class="detail-location-icon" aria-hidden="true">📍</span>
                <span class="detail-location-text">${escapeHtml(vm.locationLabel)}</span>
              </div>
            `}
          ` : ""}



          ${vm.desc ? `<div class="detail-description">${escapeHtml(vm.desc)}</div>` : ""}

          ${actionsHtml ? `
            <div class="detail-actionbar" role="group" aria-label="Aktionen">
              ${actionsHtml}
            </div>
          ` : ""}
        </div>
      `;

      this.content.innerHTML = html;

                // === BEGIN BLOCK: CALENDAR ACTION CHOOSER (device vs google) ===
      const calBtn = this.content.querySelector('[data-action="calendar"]');

      if (calBtn) {
        const getPayload = () =>
          (Array.isArray(vm.actions) ? vm.actions : [])
            .find(a => a && a.type === "calendar" && a.payload)?.payload || null;

        const openGoogle = (p) => {
          const pad2 = (n) => String(n).padStart(2, "0");
          const ymd = String(p.date).replaceAll("-", "");

          const params = new URLSearchParams();
          params.set("action", "TEMPLATE");
          params.set("text", p.title);

          if (p.startTime) {
            const s = `${ymd}T${p.startTime.replace(":", "")}00`;
            const e = p.endTime
              ? `${ymd}T${p.endTime.replace(":", "")}00`
              : s;

            params.set("dates", `${s}/${e}`);
          } else {
            params.set("dates", `${ymd}/${ymd}`);
          }

          if (p.location) params.set("location", p.location);
          if (p.description) params.set("details", p.description);

          const url = `https://calendar.google.com/calendar/render?${params.toString()}`;

          try {
            const w = window.open(url, "_blank", "noopener");
            if (!w) window.location.href = url;
          } catch {
            window.location.href = url;
          }
        };

        const downloadICS = (p) => {
          const ymd = String(p.date).replaceAll("-", "");
          const start = `${ymd}T000000`;

          const ics =
`BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SUMMARY:${p.title}
DTSTART:${start}
LOCATION:${p.location || ""}
DESCRIPTION:${p.description || ""}
END:VEVENT
END:VCALENDAR`;

          const blob = new Blob([ics], { type: "text/calendar" });
          const url = URL.createObjectURL(blob);

          const a = document.createElement("a");
          a.href = url;
          a.download = "event.ics";
          a.click();

          setTimeout(() => URL.revokeObjectURL(url), 500);
        };

        const showChooser = () => {
          const html = `
            <div class="detail-cal-chooser">
              <div class="detail-cal-backdrop"></div>
              <div class="detail-cal-sheet">
                <button data-cal="device">Gerätekalender</button>
                <button data-cal="google">Google Kalender</button>
                <button data-cal="close">Abbrechen</button>
              </div>
            </div>
          `;

                  this.panel.insertAdjacentHTML("beforeend", html);


                   const chooser = this.panel.querySelector(".detail-cal-chooser");

          const payload = getPayload();

          chooser.addEventListener("click", (e) => {
            const t = e.target.closest("[data-cal]");
            if (!t) return;

                       if (t.dataset.cal === "close") {
              chooser.remove();
              return;
            }

            chooser.remove();


            if (!payload) return;

            if (t.dataset.cal === "device") downloadICS(payload);
            if (t.dataset.cal === "google") openGoogle(payload);
          });
        };

        calBtn.addEventListener("click", showChooser);
      }
      // === END BLOCK: CALENDAR ACTION CHOOSER ===

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













