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
  if (!this.panel) return;

  this.overlay = this.panel.querySelector(".detail-panel-overlay");
  this.content = this.panel.querySelector("#detail-content");
  this.closeBtn = this.panel.querySelector(".detail-close-btn");

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
  this.overlay?.addEventListener("click", (e) => {
    if (e.target === this.overlay) this.hide();
  });

  this.closeBtn?.addEventListener("click", () => this.hide());

  /* ===============================
     ESC + Focus Trap
  =============================== */
  document.addEventListener("keydown", (e) => {
    if (!this.panel.classList.contains("active")) return;

    if (e.key === "Escape") {
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
    }
    if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  /* ===============================
     Back button closes panel
  =============================== */
  window.addEventListener("popstate", () => {
    if (this.panel.classList.contains("active")) {
      this._hideNow();
    }
  });

  /* ===============================
     Swipe to close (mobile)
  =============================== */
  const sheet = this.panel.querySelector(".detail-panel-content");
  const scroll = this.panel.querySelector("#detail-content");

  let startY = 0;
  let dy = 0;
  let dragging = false;

  sheet?.addEventListener("pointerdown", (e) => {
    if (scroll.scrollTop > 0) return;
    startY = e.clientY;
    dragging = true;
  });

  sheet?.addEventListener("pointermove", (e) => {
    if (!dragging) return;
    dy = e.clientY - startY;
    if (dy <= 0) return;
    sheet.style.transform = `translateY(${dy}px)`;
  });

  sheet?.addEventListener("pointerup", () => {
    dragging = false;
    sheet.style.transform = "";
    if (dy > 90) this.hide();
  });

  this._isInit = true;
},


show(event) {
  if (!this._isInit) this.init();

  this._lastFocusEl = document.activeElement;

  this.renderContent(event);

  this.panel.classList.remove("hidden");
  this.panel.classList.add("active");
  this.panel.setAttribute("aria-hidden", "false");

  document.body.classList.add("is-panel-open");

  history.pushState({ detailOpen: true }, "");

  this.closeBtn?.focus();
},


  hide() {
  if (history.state?.detailOpen) {
    history.back();
    return;
  }
  this._hideNow();
},

_hideNow() {
  this.panel.classList.remove("active");
  this.panel.classList.add("hidden");
  this.panel.setAttribute("aria-hidden", "true");

  document.body.classList.remove("is-panel-open");

  this._lastFocusEl?.focus();
},


  _hideNow() {
    if (!this.panel) return;

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





















