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

/* BEGIN PATCH DP-CATEGORY-ICONS-SVG-V1 (CATEGORY MAP)
   Zweck: Kategorie-Icons im Detailpanel als SVG-Typen (keine Emojis, keine Font-Metrik-Drifts).
   Umfang: Ersetzt nur getCategoryIcon().
*/
/* BEGIN PATCH ICONS-DETAILS-CATEGORYKEY-V1
   Zweck: Kategorie-Icons im Detailpanel NICHT als Emojis, sondern als IconKeys (SVG via window.Icons).
   Umfang: Ersetzt nur getCategoryIcon().
*/
/* === BEGIN BLOCK: CATEGORY ICON FALLBACK (calendar token, not emoji)
Zweck:
- Kategorie → IconKey (Single Source of Truth: window.Icons.categoryKey)
- Fallback: "calendar"
Umfang:
- Nur getCategoryIcon()
=== */
const getCategoryIcon = (categoryRaw) => {
  const c = trimOrEmpty(categoryRaw);
  if (!c) return "";

  // Single Source of Truth
  if (window.Icons && typeof window.Icons.categoryKey === "function") {
    return window.Icons.categoryKey(c) || "calendar";
  }

  return "calendar";
};
/* === END BLOCK: CATEGORY ICON FALLBACK (calendar token, not emoji) === */
/* END PATCH ICONS-DETAILS-CATEGORYKEY-V1 */
/* END PATCH DP-CATEGORY-ICONS-SVG-V1 (CATEGORY MAP) */

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

/* === BEGIN BLOCK: ENTERPRISE V2 LINKS + SHARE (source always visible, no route CTA)
Zweck:
- CTA-Disziplin: Actionbar zeigt NUR Kalender + Teilen
- Quelle wird immer aus der besten belastbaren Event-URL gebildet (nicht nur bocholt.de)
- Website bleibt die Venue-/Homepage aus locations.json, wenn sie sinnvoll von der Quelle verschieden ist
- Keine Route-Redundanz (Maps steckt im Ort-Link)
Umfang:
- VM Felder: websiteUrl, sourceUrl, sharePayload
- actions: calendar + share (max 2)
=== */

const rawUrl = normalizeHttpUrl(
  trimOrEmpty(
    // bevorzugt explizite Source-Felder, sonst generisches url/link
    e.source_url || e.sourceUrl || e.url || e.link || e.website || e.website_url || e.websiteUrl || ""
  )
);

const sourceCandidates = [
  normalizeHttpUrl(trimOrEmpty(e.source_url || e.sourceUrl || "")),
  normalizeHttpUrl(trimOrEmpty(e.url || e.link || "")),
  rawUrl,
].filter(Boolean);

const scoreSourceUrl = (u) => {
  try {
    const parsed = new URL(u);
    const host = parsed.hostname.toLowerCase();
    const path = parsed.pathname || "/";
    const segs = path.split("/").filter(Boolean).length;

    let score = 0;

    // konkrete Event-Detailseiten bevorzugen
    if (/\/veranstaltungskalender\//i.test(path)) score += 100;
    if (/\/programm\//i.test(path)) score += 90;

    // offizielle Bocholter Quellen leicht bevorzugen, aber nicht erzwingen
    if (/(^|\.)bocholt\.de$/i.test(host)) score += 20;
    if (/(^|\.)stadttheater-bocholt\.de$/i.test(host)) score += 15;

    // spezifischere Pfade bevorzugen
    score += segs;

    return score;
  } catch {
    return 0;
  }
};

const sourceUrl = sourceCandidates.length
  ? sourceCandidates.slice().sort((a, b) => scoreSourceUrl(b) - scoreSourceUrl(a))[0]
  : "";

// Website: externe Homepage aus locations.json, nur wenn sinnvoll von Quelle verschieden
const websiteUrl = (() => {
  const hp = normalizeHttpUrl(homepage) || "";
  if (!hp) return "";
  if (sourceUrl && hp === sourceUrl) return "";
  return hp;
})();

// Share: Text + beste URL (Website bevorzugt, sonst Quelle, sonst leer)
const shareParts = [
  title,
  date ? `📅 ${date}${timeRange ? ` · ${timeRange}` : ""}` : "",
  locationLabel ? `📍 ${locationLabel}` : "",
].filter(Boolean);

const shareText = shareParts.join("\n");
const shareUrl = websiteUrl || sourceUrl || "";
const sharePayload = { title, text: shareText, url: shareUrl };

// Actions (Actionbar: exakt 2 CTAs max)
const actions = [
  ...(canCalendar ? [{
    type: "calendar",
    label: "Kalender",
    priority: "primary",
    payload: calendarPayload,
  }] : []),

  ...(shareText ? [{
    type: "share",
    label: "Teilen",
    priority: "primary",
    payload: sharePayload,
  }] : []),
];

/* === END BLOCK: ENTERPRISE V2 LINKS + SHARE (source always visible, no route CTA) === */

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

      // enterprise v2 link model (render uses these)
      websiteUrl,
      sourceUrl,
      sharePayload,

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

           /* === BEGIN BLOCK: DETAILPANEL SLOT REFS (content + actionbar) ===
      Zweck: Content-Scrollbereich und Actionbar-Slot als getrennte Zonen referenzieren.
      Umfang: DOM-Refs in init()
      === */
      this.overlay = this.panel.querySelector(".detail-panel-overlay");
      this.sheet = this.panel.querySelector(".detail-panel-content");
      this.content = this.panel.querySelector("#detail-content");
      this.actionbarSlot = this.panel.querySelector("#detail-actionbar-slot");
      this.closeBtn = this.panel.querySelector(".detail-panel-close");
      /* === END BLOCK: DETAILPANEL SLOT REFS (content + actionbar) === */

      


      // A11y semantics
      this.panel.setAttribute("role", "dialog");
      this.panel.setAttribute("aria-modal", "true");
      this.panel.setAttribute("aria-hidden", "true");
            /* === BEGIN BLOCK: DETAILPANEL FOCUS FALLBACK TARGET ===
      Zweck:
      - Sheet als programmatisch fokussierbares Ziel bereitstellen (Fallback, falls Close fehlt/unsichtbar)
      - Hilft Screenreader/Keyboard-Navigation (Enterprise a11y)
      Umfang:
      - Setzt tabindex nur auf .detail-panel-content (sheet)
      === */
      if (this.sheet && !this.sheet.hasAttribute("tabindex")) {
        this.sheet.setAttribute("tabindex", "-1");
      }
      /* === END BLOCK: DETAILPANEL FOCUS FALLBACK TARGET === */
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

           /* === BEGIN BLOCK: DETAILPANEL KEYBOARD + FOCUS TRAP (enterprise a11y) ===
      Zweck:
      - ESC schließt zuverlässig
      - TAB bleibt IM Panel (vollständige Focusables inkl. Inputs)
      - Edge-Case: Fokus „rutscht“ außerhalb → zurück ins Panel
      Umfang:
      - Ersetzt ausschließlich this._onKeyDown Handler in init()
      === */
      this._onKeyDown = (e) => {
        if (!this.panel || !this.panel.classList.contains("active")) return;

        // ESC closes
        if (e.key === "Escape") {
          e.preventDefault();
          this.hide();
          return;
        }

        if (e.key !== "Tab") return;

        const selectors = [
          'a[href]',
          'area[href]',
          'button:not([disabled])',
          'input:not([disabled])',
          'select:not([disabled])',
          'textarea:not([disabled])',
          'summary',
          '[tabindex]:not([tabindex="-1"])',
          '[contenteditable="true"]'
        ].join(",");

        // Visible + enabled focusables only
        const focusables = Array.from(this.panel.querySelectorAll(selectors))
          .filter((el) => {
            if (!(el instanceof HTMLElement)) return false;
            if (el.hasAttribute("disabled")) return false;
            if (el.getAttribute("aria-hidden") === "true") return false;
            const style = window.getComputedStyle(el);
            if (style.display === "none" || style.visibility === "hidden") return false;
            // offsetParent null can be false-negative for position:fixed; use rect as fallback
            const r = el.getClientRects();
            return r && r.length > 0;
          });

        if (!focusables.length) return;

        const first = focusables[0];
        const last = focusables[focusables.length - 1];

        const active = document.activeElement;

        // If focus is outside panel (or body), force it back in
        if (!(active instanceof HTMLElement) || !this.panel.contains(active)) {
          e.preventDefault();
          (e.shiftKey ? last : first).focus({ preventScroll: true });
          return;
        }

        if (e.shiftKey) {
          if (active === first) {
            e.preventDefault();
            last.focus({ preventScroll: true });
          }
          return;
        }

        // forward tab
        if (active === last) {
          e.preventDefault();
          first.focus({ preventScroll: true });
        }
      };
      /* === END BLOCK: DETAILPANEL KEYBOARD + FOCUS TRAP (enterprise a11y) === */
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

       // === BEGIN BLOCK: SNAP HEIGHT HELPERS (DVH-SAFE) ===
    // Zweck: Verhindert zu tiefes Panel bei mobiler Browser/System-UI (vh-Bug). Nutzt dvh wenn verfügbar.
    // Umfang: getHalfSnap + setBase (nur Layout-Parameter, keine Side-Effects)
    getHalfSnap() {
      try {
        if (window.CSS && typeof window.CSS.supports === "function" && window.CSS.supports("height: 1dvh")) {
          return "40dvh";
        }
      } catch (_) {}
      return "40vh";
    },

    setBase(v) {
      if (!this.sheet) return;
      this.sheet.style.setProperty("--dp-base-y", v);
    },
    // === END BLOCK: SNAP HEIGHT HELPERS (DVH-SAFE) ===


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
          this.setBase(this.getHalfSnap());


      // open
      this.panel.classList.remove("hidden");
      this.panel.classList.add("active");
      this.panel.setAttribute("aria-hidden", "false");

          /* === BEGIN BLOCK: BODY SCROLL LOCK (iOS-safe, enterprise) ===
      Zweck:
      - Hintergrund darf NICHT scrollen/“rubberbanden”, auch auf iOS
      - Scrollposition wird korrekt wiederhergestellt
      Umfang:
      - Ersetzt ausschließlich den bisherigen overflow-only Lock in show()
      === */
      if (!this._scrollLockActive) {

        this._scrollLockActive = true;

        // Save scroll position
        this._savedScrollY = window.scrollY || 0;

        // Save body inline styles (defensive)
        if (this._savedBodyOverflow == null) this._savedBodyOverflow = document.body.style.overflow || "";
        this._savedBodyPosition = document.body.style.position || "";
        this._savedBodyTop = document.body.style.top || "";
        this._savedBodyLeft = document.body.style.left || "";
        this._savedBodyRight = document.body.style.right || "";
        this._savedBodyWidth = document.body.style.width || "";

      // Lock body (works reliably on iOS)
      document.documentElement.classList.add("is-panel-open");
      document.body.classList.add("is-panel-open");

      document.body.style.overflow = "hidden";
      document.body.style.position = "fixed";
      document.body.style.top = `-${this._savedScrollY}px`;
      document.body.style.left = "0";
      document.body.style.right = "0";
      document.body.style.width = "100%";

      }
      /* === END BLOCK: BODY SCROLL LOCK (iOS-safe, enterprise) === */

      // history state (avoid stacking)
      if (!history.state?.detailOpen) {
        history.pushState({ ...(history.state || {}), detailOpen: true }, "");
      }

           /* === BEGIN BLOCK: OPEN FOCUS (robust, transitionend + fallback) ===
      Zweck:
      - Close-Button wird zuverlässig fokussiert (auch wenn transitionend nicht feuert)
      - Verhindert „kein Fokus“ bei reduced motion / fehlender Transition
      Umfang:
      - Ersetzt ausschließlich den Focus-After-Open Block in show(event)
      === */
      const focusClose = () => {
        try { this.closeBtn?.focus({ preventScroll: true }); } catch (_) {}
      };

      // 1) Prefer transition end (smooth)
      this.panel.addEventListener("transitionend", focusClose, { once: true });

      // 2) Fallback: next frame + micro delay
      requestAnimationFrame(() => {
        setTimeout(() => {
          if (this.panel?.classList.contains("active")) focusClose();
        }, 0);
      });
      /* === END BLOCK: OPEN FOCUS (robust, transitionend + fallback) === */
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

      /* === BEGIN BLOCK: CLEAR ACTIONBAR SLOT ON HIDE ===
      Zweck: Keine „hängenden“ Actions nach Close; sauberer Zustand beim nächsten Öffnen.
      Umfang: _hideNow()
      === */
      if (this.actionbarSlot) {
        this.actionbarSlot.innerHTML = "";
        this.actionbarSlot.hidden = true;
      }
      /* === END BLOCK: CLEAR ACTIONBAR SLOT ON HIDE === */

      // reset snap vars

      this.setDragY(0);
      this.setBase("100%");

        /* === BEGIN BLOCK: BODY SCROLL UNLOCK (iOS-safe, enterprise) ===
      Zweck:
      - Stellt Body-Styles wieder her
      - Springt exakt zur vorherigen Scrollposition zurück
      Umfang:
      - Ersetzt ausschließlich den bisherigen overflow-only Restore in hide()
      === */
      if (this._scrollLockActive) {

        // Restore inline styles
        document.documentElement.classList.remove("is-panel-open");
        document.body.classList.remove("is-panel-open");

        if (this._savedBodyOverflow != null) {
          document.body.style.overflow = this._savedBodyOverflow;
          this._savedBodyOverflow = null;
        }
        document.body.style.position = this._savedBodyPosition || "";
        document.body.style.top = this._savedBodyTop || "";
        document.body.style.left = this._savedBodyLeft || "";
        document.body.style.right = this._savedBodyRight || "";
        document.body.style.width = this._savedBodyWidth || "";

        // Restore scroll position
        const y = Number(this._savedScrollY || 0);
        this._savedScrollY = null;

        this._scrollLockActive = false;

        // Must happen after styles are restored
        window.scrollTo(0, y);

      }
      /* === END BLOCK: BODY SCROLL UNLOCK (iOS-safe, enterprise) === */

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
               this.setBase(this.getHalfSnap());

      }
    },

           renderContent(event) {
         const vm = toEventDetailVM(event);

      // === BEGIN BLOCK: DATE LABEL (DE, user-friendly) ===
      // Zweck: ISO-Datum (YYYY-MM-DD) für Anzeige hübsch formatieren (de-DE), ohne Datenmodell zu verändern
      const formatDateLabelDE = (iso) => {
        const s = String(iso || "").trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;

        const [y, m, d] = s.split("-").map(n => parseInt(n, 10));
        // UTC, damit es nicht durch TZ/DST kippt
        const dt = new Date(Date.UTC(y, m - 1, d));

        try {
          const now = new Date();
          const currentYear = now.getFullYear();

          const base = new Intl.DateTimeFormat("de-DE", {
            weekday: "short",
            day: "2-digit",
            month: "short",
          }).format(dt);

          // Jahr nur zeigen, wenn nicht aktuelles Jahr (ruhiger)
          if (y !== currentYear) {
            const yr = new Intl.DateTimeFormat("de-DE", { year: "numeric" }).format(dt);
            return `${base} ${yr}`;
          }

          return base;
        } catch (_) {
          return s;
        }
      };
      const dateLabel = formatDateLabelDE(vm.date);
      // === END BLOCK: DATE LABEL (DE, user-friendly) ===


/* BEGIN PATCH ICONS-DETAILS-ICONS-WRAPPER-V1
   Zweck: Detailpanel-Icons zentral aus window.Icons.svg() beziehen (keine Inline-SVG-Definitionen in details.js).
   Umfang: Ersetzt iconSvg() Block komplett, API bleibt kompatibel (type, extraClass).
*/
/* === BEGIN FIX: CALENDAR ICON SINGLE SOURCE (actionbar token reused everywhere)
Zweck:
- Icons als Single Source of Truth über window.Icons.svg()
- Bestehende Call-Sites behalten iconSvg(type, extraClass)
Umfang:
- Ersetzt iconSvg()
=== */
const iconSvg = (type, extraClass = "") => {
  const cls = `detail-icon-svg${extraClass ? " " + extraClass : ""}`;

  if (window.Icons && typeof window.Icons.svg === "function") {
    // IMPORTANT: keep existing CSS hooks by passing "detail-icon-svg"
    return window.Icons.svg(type, { className: cls });
  }

  return "";
};
/* === END FIX: CALENDAR ICON SINGLE SOURCE (actionbar token reused everywhere) === */
/* END PATCH ICONS-DETAILS-ICONS-WRAPPER-V1 */
/* END PATCH DP-CATEGORY-ICONS-SVG-V1 (ICONSVG) */

            /* === BEGIN PATCH: ACTIONBAR LABELS (enterprise v2: calendar + share + website)
            Zweck:
            - Actionbar strikt: Kalender, Teilen, optional Website
            - Share als Button (navigator.share/clipboard) statt WhatsApp-Link
            - Kein Route-CTA
            Umfang:
            - renderAction() + icon mapping
            === */
      const renderAction = (a) => {
        if (!a || typeof a !== "object") return "";

        const raw = String(a.label || "").trim();
        const fallback = raw || "Aktion";

        const uiLabel =
          (a.type === "share") ? "Teilen" :
          fallback;

        const ariaLabel =
          (a.type === "share") ? "Event teilen" :
          fallback;

        const labelUiEsc = escapeHtml(uiLabel);
        const labelAriaEsc = escapeHtml(ariaLabel);

        const type =
          (a.type === "calendar") ? "calendar" :
          (a.type === "website") ? "website" :
          "share";

        const icon = iconSvg(type);

        if (a.type === "calendar") {
          return `
            <button
              class="detail-actionbar-btn is-icon"
              type="button"
              data-action="calendar"
              aria-label="${labelAriaEsc}"
              title="${labelUiEsc}"
            >
              ${icon}
              <span class="detail-sr-only">${labelAriaEsc}</span>
            </button>
          `;
        }

        if (a.type === "share") {
          return `
            <button
              class="detail-actionbar-btn is-icon"
              type="button"
              data-action="share"
              aria-label="${labelAriaEsc}"
              title="${labelUiEsc}"
            >
              ${icon}
              <span class="detail-sr-only">${labelAriaEsc}</span>
            </button>
          `;
        }

        if (a.href) {
          const href = escapeHtml(a.href);
          return `
            <a
              class="detail-actionbar-btn is-icon"
              href="${href}"
              target="_blank"
              rel="noopener"
              aria-label="${labelAriaEsc}"
              title="${labelUiEsc}"
            >
              ${icon}
              <span class="detail-sr-only">${labelAriaEsc}</span>
            </a>
          `;
        }

        return "";
      };
            /* === END PATCH: ACTIONBAR LABELS (enterprise v2: calendar + share + website) === */




           const actionsForBar = (Array.isArray(vm.actions) ? vm.actions : [])
        .filter(a => a && (a.type === "calendar" || a.type === "share"));

      const actionsHtml = actionsForBar
        .map(renderAction)
        .filter(Boolean)
        .join("");


      /* === BEGIN BLOCK: ENTERPRISE V2 DETAIL CONTENT (meta compaction + no redundancy)
      Zweck:
      - Meta direkt unter Titel: Row1 Ort (Maps), Row2 Datum·Zeit
      - Actionbar strikt 2 CTAs: Kalender + Teilen
      - Website + Quelle als ruhige Links im Content, dedupliziert (keine Doppelungen)
      Umfang:
      - detail-header markup + meta rows + optional links block
      === */

      const dateTimeLabel = (() => {
        const d = dateLabel || vm.date || "";
        if (!d) return "";
        if (vm.timeRange) return `${d} · ${vm.timeRange}`;
        return `${d} · ganztägig`;
      })();

      const normWebsite = normalizeHttpUrl(vm.websiteUrl || "");
      const normSource = normalizeHttpUrl(vm.sourceUrl || "");

      const canonicalLinkKey = (u) => {
        if (!u) return "";
        try {
          const url = new URL(u);
          const host = (url.hostname || "").toLowerCase().replace(/^www\./, "");
          const path = (url.pathname || "/")
            .replace(/\/+$/g, "")
            .toLowerCase() || "/";
          return `${host}${path}`;
        } catch {
          return u.trim().toLowerCase();
        }
      };

      const hostLabel = (u, fallbackLabel) => {
        if (!u) return "";
        try {
          const url = new URL(u);
          const host = (url.hostname || "").replace(/^www\./, "");
          if (/bocholt\.de$/i.test(host)) return "Stadt Bocholt · Veranstaltungskalender";
          return host || fallbackLabel;
        } catch {
          return fallbackLabel;
        }
      };

      const websiteKey = canonicalLinkKey(normWebsite);
      const sourceKey = canonicalLinkKey(normSource);

      const websiteHostLabel = hostLabel(normWebsite, "Website");
      const sourceHostLabel = hostLabel(normSource, "Quelle");

      const sameCanonicalTarget =
        Boolean(websiteKey) &&
        Boolean(sourceKey) &&
        websiteKey === sourceKey;

      const sameDisplayTarget =
        Boolean(websiteHostLabel) &&
        Boolean(sourceHostLabel) &&
        websiteHostLabel === sourceHostLabel;

      const showWebsite =
        Boolean(normWebsite) &&
        !sameCanonicalTarget &&
        !sameDisplayTarget;

      const showSource = Boolean(normSource);

      /* === BEGIN BLOCK: DETAIL_OUTBOUND_ANALYTICS_PAYLOADS_V1 | Zweck: erzeugt saubere Analytics-Payloads und data-Attribute für Event-Detaillinks (Maps / Website / Quelle), ohne sichtbare UI zu verändern | Umfang: nur Renderlogik im Detailpanel === */
      const baseOutboundPayload = {
        entityType: "event",
        entityId: String(event?.id || "").trim(),
        entityTitle: String(vm.title || "").trim()
      };

      const mapsOutboundPayload = vm.maps
        ? {
            ...baseOutboundPayload,
            outboundType: "maps",
            destinationUrl: vm.maps
          }
        : null;

      const websiteOutboundPayload = showWebsite
        ? {
            ...baseOutboundPayload,
            outboundType: "website",
            destinationUrl: normWebsite
          }
        : null;

      const sourceOutboundPayload = showSource
        ? {
            ...baseOutboundPayload,
            outboundType: "website",
            destinationUrl: normSource
          }
        : null;

      const buildOutboundDataAttrs = (payload) => {
        if (!payload) return "";
        return [
          `data-outbound-type="${escapeHtml(payload.outboundType || "")}"`,
          `data-entity-type="${escapeHtml(payload.entityType || "")}"`,
          `data-entity-id="${escapeHtml(payload.entityId || "")}"`,
          `data-entity-title="${escapeHtml(payload.entityTitle || "")}"`,
          `data-destination-url="${escapeHtml(payload.destinationUrl || "")}"`
        ].join(" ");
      };
      /* === END BLOCK: DETAIL_OUTBOUND_ANALYTICS_PAYLOADS_V1 === */

      const html = `
        <div class="detail-panel-inner">
          <div class="detail-header">
            <div class="detail-title-row">
              <h2 class="detail-title">${escapeHtml(vm.title)}</h2>
${vm.icon ? `<span class="detail-category-icon" aria-hidden="true">${iconSvg(vm.icon, "is-category")}</span>` : ""}
            </div>

            <div class="detail-meta-rows" aria-label="Event-Infos">
              ${vm.locationLabel ? `
                ${vm.maps ? `
                  <a
                    class="detail-meta-row is-location"
                    href="${escapeHtml(vm.maps)}"
                    target="_blank"
                    rel="noopener"
                    aria-label="Ort in Karten öffnen"
                    ${buildOutboundDataAttrs(mapsOutboundPayload)}
                  >
                    <span class="detail-meta-icon" aria-hidden="true">
                      ${iconSvg("pin", "is-chip")}
                    </span>
                    <span class="detail-meta-text">${escapeHtml(vm.locationLabel)}</span>
                    <span class="detail-meta-ext" aria-hidden="true">${iconSvg("external", "is-ext")}</span>
                  </a>
                ` : `
                  <div class="detail-meta-row is-location is-static" aria-label="Ort">
                    <span class="detail-meta-icon" aria-hidden="true">
                      ${iconSvg("pin", "is-chip")}
                    </span>
                    <span class="detail-meta-text">${escapeHtml(vm.locationLabel)}</span>
                  </div>
                `}
              ` : ""}

              ${dateTimeLabel ? `
                <div class="detail-meta-row is-datetime" aria-label="Datum und Uhrzeit">
                  <span class="detail-meta-icon" aria-hidden="true">
                    ${iconSvg("calendar", "is-chip")}
                  </span>
                  <span class="detail-meta-text">${escapeHtml(dateTimeLabel)}</span>
                </div>
              ` : ""}
            </div>

          ${vm.desc ? `<div class="detail-description">${escapeHtml(vm.desc)}</div>` : ""}

          ${(showWebsite || showSource) ? `
            <div class="detail-links" aria-label="Links">
              ${showWebsite ? `
                <a
                  class="detail-link"
                  href="${escapeHtml(normWebsite)}"
                  target="_blank"
                  rel="noopener"
                  ${buildOutboundDataAttrs(websiteOutboundPayload)}
                >
                  <span class="detail-link-label">Website</span>
                  <span class="detail-link-value">${escapeHtml(websiteHostLabel)}</span>
                  <span class="detail-link-ext" aria-hidden="true">${iconSvg("external", "is-ext")}</span>
                </a>
              ` : ""}

              ${showSource ? `
                <a
                  class="detail-link"
                  href="${escapeHtml(normSource)}"
                  target="_blank"
                  rel="noopener"
                  ${buildOutboundDataAttrs(sourceOutboundPayload)}
                >
                  <span class="detail-link-label">Quelle</span>
                  <span class="detail-link-value">${escapeHtml(sourceHostLabel)}</span>
                  <span class="detail-meta-ext" aria-hidden="true">${iconSvg("external", "is-ext")}</span>
                </a>
              ` : ""}
            </div>
          ` : ""}
        </div>
      `;

      this.content.innerHTML = html;

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

      /* === END BLOCK: ENTERPRISE V2 DETAIL CONTENT (meta compaction + no redundancy) === */

      /* === BEGIN BLOCK: ACTIONBAR RENDER INTO SLOT (outside scroll) ===
      Zweck: Actions immer sichtbar am Sheet-Boden (nicht im Scroll-Content).
      Umfang: Render-Output in #detail-actionbar-slot + show/hide handling.
      === */
      if (this.actionbarSlot) {
        if (actionsHtml) {
          this.actionbarSlot.innerHTML = actionsHtml;
          this.actionbarSlot.hidden = false;
        } else {
          this.actionbarSlot.innerHTML = "";
          this.actionbarSlot.hidden = true;
        }
      }
      /* === END BLOCK: ACTIONBAR RENDER INTO SLOT (outside scroll) === */


// === BEGIN BLOCK: CALENDAR ACTION CHOOSER (device vs google) ===
const calBtn = this.actionbarSlot?.querySelector('[data-action="calendar"]');

if (calBtn) {
  const getPayload = () =>
    (Array.isArray(vm.actions) ? vm.actions : [])
      .find(a => a && a.type === "calendar" && a.payload)?.payload || null;

  const openGoogle = (p) => {
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
    // Defensive cleanup: niemals mehrere Chooser gleichzeitig
    this.panel.querySelectorAll(".detail-cal-chooser").forEach(n => n.remove());

    const html = `
      <div class="detail-cal-chooser">
        <div class="detail-cal-backdrop" data-cal="close"></div>
        <div class="detail-cal-sheet">
          <button data-cal="device">Gerätekalender</button>
          <button data-cal="google">Google Kalender</button>
          <button data-cal="close">Abbrechen</button>
        </div>
      </div>
    `;

    this.panel.insertAdjacentHTML("beforeend", html);

    const chooser = this.panel.querySelector(".detail-cal-chooser");
    if (!chooser) return;

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

  if (calBtn.dataset.calChooserBound !== "1") {
    calBtn.dataset.calChooserBound = "1";
    calBtn.addEventListener("click", showChooser);
  }
}
// === END BLOCK: CALENDAR ACTION CHOOSER ===


/* === BEGIN BLOCK: SHARE ACTION (navigator.share + clipboard fallback)
Zweck:
- Enterprise: Share-Abbruch (Back/Cancel) darf KEIN Fallback-Popup triggern
- Kein window.prompt mehr
- Fallback nur Clipboard (still, optional kurzer Title-Feedback)
Umfang:
- bindet [data-action="share"] einmalig pro Render
=== */
const shareBtn = this.actionbarSlot?.querySelector('[data-action="share"]');

if (shareBtn) {
  const getPayload = () =>
    (Array.isArray(vm.actions) ? vm.actions : [])
      .find(a => a && a.type === "share" && a.payload)?.payload || null;

  const doShare = async () => {
    const p = getPayload();
    if (!p) return;

    const text = String(p.text || "").trim();
    const url = String(p.url || "").trim();
    const title = String(p.title || vm.title || "Event").trim();

    const combined = [text, url].filter(Boolean).join("\n");
    if (!combined) return;

    // 1) Native share
    try {
      if (navigator.share) {
        await navigator.share({
          title,
          text: combined,
          url: url || undefined,
        });
        return;
      }
    } catch (err) {
      // WICHTIG: User-Abbruch -> KEIN Fallback
      if (err && (err.name === "AbortError" || err.name === "NotAllowedError")) return;
      // sonst weiter zu Clipboard
    }

    // 2) Clipboard fallback (still, kein Prompt)
    try {
      if (navigator.clipboard) {
        await navigator.clipboard.writeText(combined);
        const prev = shareBtn.title;
        shareBtn.title = "Kopiert";
        setTimeout(() => { shareBtn.title = prev; }, 1200);
      }
    } catch (_) {
      // Absichtlich still: kein Popup/Prompt
    }
  };

  if (shareBtn.dataset.shareBound !== "1") {
    shareBtn.dataset.shareBound = "1";
    shareBtn.addEventListener("click", (e) => {
      e.preventDefault();
      doShare();
    });
  }
}
/* === END BLOCK: SHARE ACTION (navigator.share + clipboard fallback) === */



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











































