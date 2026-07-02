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
  actionbarSlot: null,
  _isInit: false,
  _lastFocusEl: null,
  _savedScrollY: 0,
  _savedBodyOverflow: null,
  _savedBodyPosition: null,
  _savedBodyTop: null,
  _savedBodyLeft: null,
  _savedBodyRight: null,
  _savedBodyWidth: null,
  _scrollLockActive: false,
  _onKeyDown: null,
  _onPopState: null,
  _onOverlayClick: null,
  _onCloseClick: null,
  dragHit: null,
  sheet: null,
  _drag: {
    startY: 0,
    dy: 0,
    dragging: false
  },

  isDesktopViewport() {
    return window.matchMedia("(min-width: 900px)").matches;
  },

  /* === BEGIN BLOCK: OFFER_DETAIL_PANEL_MOBILE_SHELL_PARITY_V1 | Zweck: haertet das Activity-Detailpanel als mobiles System-Overlay mit A11y-, Scroll-, Back- und Drag-Paritaet zum Event-Detailpanel; Umfang: ersetzt die gesamte init()-Methode === */
  init() {
    if (this._isInit) return;

    const existingPanel = document.getElementById("event-detail-panel");

    if (!existingPanel) {
      const root = document.getElementById("offer-detail-root");
      if (!root) return;

      root.innerHTML = `
        <div id="event-detail-panel" class="detail-panel hidden" hidden>
          <div class="detail-panel-overlay"></div>
          <div class="detail-panel-content">
            <div class="detail-panel-header">
              <div class="detail-panel-handle" aria-hidden="true"></div>
              <button class="detail-panel-close" type="button" aria-label="Schließen"><span class="detail-panel-close__icon" data-ui-icon="x" aria-hidden="true"></span></button>
            </div>
            <div class="detail-panel-body">
              <div id="detail-content"></div>
            </div>
          </div>
          <div id="detail-actionbar-slot" class="detail-actionbar" aria-label="Aktionen" hidden></div>
        </div>
      `.trim();

      if (window.Icons && typeof window.Icons.hydrate === "function") {
        window.Icons.hydrate(root);
      }
    } else if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(existingPanel);
    }

    this.panel = document.getElementById("event-detail-panel");
    this.overlay = this.panel?.querySelector(".detail-panel-overlay");
    this.sheet = this.panel?.querySelector(".detail-panel-content");
    this.body = this.panel?.querySelector(".detail-panel-body");
    this.content = document.getElementById("detail-content");
    this.closeBtn = this.panel?.querySelector(".detail-panel-close");
    this.actionbarSlot = this.panel?.querySelector("#detail-actionbar-slot");

    if (this.panel && !this.actionbarSlot) {
      const slot = document.createElement("div");
      slot.id = "detail-actionbar-slot";
      slot.className = "detail-actionbar";
      slot.setAttribute("aria-label", "Aktionen");
      slot.hidden = true;
      this.panel.appendChild(slot);
      this.actionbarSlot = slot;
    }

    if (!this.panel || !this.overlay || !this.sheet || !this.body || !this.content || !this.closeBtn || !this.actionbarSlot) return;

    this.panel.setAttribute("role", "dialog");
    this.panel.setAttribute("aria-modal", "true");
    this.panel.setAttribute("aria-hidden", "true");
    if (!this.sheet.hasAttribute("tabindex")) this.sheet.setAttribute("tabindex", "-1");
    this.closeBtn.setAttribute("aria-label", "Schließen");

    this.dragHit = this.sheet.querySelector(".detail-panel-drag-hit");
    if (!this.dragHit) {
      this.dragHit = document.createElement("div");
      this.dragHit.className = "detail-panel-drag-hit";
      this.sheet.appendChild(this.dragHit);
    }

    this._onOverlayClick = (event) => {
      if (event.target !== this.overlay || !this.isActivityPanelOpen()) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      this.hide();
    };
    this.overlay.addEventListener("click", this._onOverlayClick, true);

    this._onCloseClick = (event) => {
      if (!this.isActivityPanelOpen()) return;
      event.preventDefault();
      event.stopImmediatePropagation();
      this.hide();
    };
    this.closeBtn.addEventListener("click", this._onCloseClick, true);

    this._onKeyDown = (event) => {
      if (!this.isActivityPanelOpen()) return;

      if (event.key === "Escape") {
        event.preventDefault();
        event.stopImmediatePropagation();
        this.hide();
        return;
      }

      if (event.key !== "Tab") return;

      event.stopImmediatePropagation();
      this.trapFocus(event);
    };
    document.addEventListener("keydown", this._onKeyDown, true);

    this._onPopState = (event) => {
      if (!this.isActivityPanelOpen()) return;
      event.stopImmediatePropagation();
      this._hideNow();
    };
    window.addEventListener("popstate", this._onPopState, true);

    if (this.dragHit) {
      this.dragHit.addEventListener("pointerdown", (event) => this._onDragStart(event), true);
      this.dragHit.addEventListener("pointermove", (event) => this._onDragMove(event), { passive: false, capture: true });
      this.dragHit.addEventListener("pointerup", (event) => this._onDragEnd(event), true);
      this.dragHit.addEventListener("pointercancel", (event) => this._onDragEnd(event), true);
    }

    this._isInit = true;
  },
  /* === END BLOCK: OFFER_DETAIL_PANEL_MOBILE_SHELL_PARITY_V1 === */


  isActivityPanelOpen() {
    return Boolean(
      this.panel &&
      this.panel.classList.contains("active") &&
      this.panel.getAttribute("data-detail-type") === "activity"
    );
  },

  setDragY(px) {
    if (!this.sheet) return;
    this.sheet.style.setProperty("--dp-drag-y", `${px}px`);
  },

  getHalfSnap() {
    try {
      if (window.CSS && typeof window.CSS.supports === "function" && window.CSS.supports("height: 1dvh")) {
        return "40dvh";
      }
    } catch (_) {}
    return "40vh";
  },

  setBase(value) {
    if (!this.sheet) return;
    this.sheet.style.setProperty("--dp-base-y", value);
  },

  getFocusableElements() {
    if (!this.panel) return [];

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

    return Array.from(this.panel.querySelectorAll(selectors)).filter((element) => {
      if (!(element instanceof HTMLElement)) return false;
      if (element.hasAttribute("disabled")) return false;
      if (element.getAttribute("aria-hidden") === "true") return false;

      const style = window.getComputedStyle(element);
      if (style.display === "none" || style.visibility === "hidden") return false;

      const rects = element.getClientRects();
      return rects && rects.length > 0;
    });
  },

  trapFocus(event) {
    const focusables = this.getFocusableElements();
    if (!focusables.length) return;

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (!(active instanceof HTMLElement) || !this.panel.contains(active)) {
      event.preventDefault();
      (event.shiftKey ? last : first).focus({ preventScroll: true });
      return;
    }

    if (event.shiftKey && active === first) {
      event.preventDefault();
      last.focus({ preventScroll: true });
      return;
    }

    if (!event.shiftKey && active === last) {
      event.preventDefault();
      first.focus({ preventScroll: true });
    }
  },

  lockScroll() {
    if (this._scrollLockActive) return;

    this._scrollLockActive = true;
    this._savedScrollY = window.scrollY || 0;
    this._savedBodyOverflow = document.body.style.overflow || "";
    this._savedBodyPosition = document.body.style.position || "";
    this._savedBodyTop = document.body.style.top || "";
    this._savedBodyLeft = document.body.style.left || "";
    this._savedBodyRight = document.body.style.right || "";
    this._savedBodyWidth = document.body.style.width || "";

    document.documentElement.classList.add("is-panel-open");
    document.body.classList.add("is-panel-open");

    document.body.style.overflow = "hidden";
    document.body.style.position = "fixed";
    document.body.style.top = `-${this._savedScrollY}px`;
    document.body.style.left = "0";
    document.body.style.right = "0";
    document.body.style.width = "100%";
  },

  unlockScroll() {
    if (!this._scrollLockActive) return;

    document.documentElement.classList.remove("is-panel-open");
    document.body.classList.remove("is-panel-open");

    document.body.style.overflow = this._savedBodyOverflow || "";
    document.body.style.position = this._savedBodyPosition || "";
    document.body.style.top = this._savedBodyTop || "";
    document.body.style.left = this._savedBodyLeft || "";
    document.body.style.right = this._savedBodyRight || "";
    document.body.style.width = this._savedBodyWidth || "";

    const y = Number(this._savedScrollY || 0);
    this._savedScrollY = 0;
    this._savedBodyOverflow = null;
    this._savedBodyPosition = null;
    this._savedBodyTop = null;
    this._savedBodyLeft = null;
    this._savedBodyRight = null;
    this._savedBodyWidth = null;
    this._scrollLockActive = false;

    window.scrollTo(0, y);
  },

  focusCloseButton() {
    const focusClose = () => {
      try { this.closeBtn?.focus({ preventScroll: true }); } catch (_) {}
    };

    this.panel?.addEventListener("transitionend", focusClose, { once: true });
    requestAnimationFrame(() => {
      setTimeout(() => {
        if (this.isActivityPanelOpen()) focusClose();
      }, 0);
    });
  },

  /* === BEGIN BLOCK: ACTIVITY_DETAIL_VIEW_VALUE_METRIC_V2 | Zweck: oeffnet Activity-Detailpanels nur mobil als robuste Bottom-Sheet-Shell und laesst Desktop bewusst outbound; Umfang: ersetzt show(offer), hide() und ergaenzt Lifecycle-Helper === */
  show(offer) {
    const primaryUrl = window.OfferVisuals?.normalizeHttpUrl
      ? window.OfferVisuals.normalizeHttpUrl(offer?.url)
      : String(offer?.url || "").trim();

    if (this.isDesktopViewport() && primaryUrl) {
      window.open(primaryUrl, "_blank", "noopener,noreferrer");
      return;
    }

    if (!this._isInit) this.init();
    if (!this.panel || !this.content || !this.body || !this.sheet) return;

    this._lastFocusEl =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;

    this.panel.setAttribute("data-detail-type", "activity");
    this.renderContent(offer);

    if (window.BEAnalytics && typeof window.BEAnalytics.trackValueMetric === "function") {
      window.BEAnalytics.trackValueMetric("activity_detail_view", {
        entityType: "activity",
        entityId: String(offer?.id || "").trim(),
        entityTitle: String(offer?.title || "").trim(),
        ...(window.BEAnalytics.buildReportingTargetPayload
          ? window.BEAnalytics.buildReportingTargetPayload(offer)
          : {})
      });
    }

    this.setDragY(0);
    this.setBase(this.getHalfSnap());

    this.panel.classList.remove("hidden");
    this.panel.removeAttribute("hidden");
    this.panel.classList.add("active");
    this.panel.setAttribute("aria-hidden", "false");
    this.body.scrollTop = 0;

    this.lockScroll();

    if (!history.state?.detailOpen) {
      history.pushState({ ...(history.state || {}), detailOpen: true, detailType: "activity" }, "");
    }

    this.focusCloseButton();
  },

  hide() {
    if (history.state?.detailOpen && history.state?.detailType === "activity") {
      history.back();
      return;
    }

    this._hideNow();
  },

  _hideNow() {
    if (!this.panel) return;

    if (this.panel.contains(document.activeElement)) {
      try { document.activeElement.blur(); } catch (_) {}
    }

    this.panel.setAttribute("aria-hidden", "true");
    this.panel.classList.remove("active");
    this.panel.classList.add("hidden");
    this.panel.setAttribute("hidden", "");
    this.panel.removeAttribute("data-detail-type");

    if (this.actionbarSlot) {
      this.actionbarSlot.innerHTML = "";
      this.actionbarSlot.hidden = true;
    }

    if (this.body) this.body.scrollTop = 0;
    this.setDragY(0);
    this.setBase("100%");
    this.unlockScroll();

    if (this._lastFocusEl && typeof this._lastFocusEl.focus === "function") {
      try { this._lastFocusEl.focus({ preventScroll: true }); } catch (_) {}
    }
    this._lastFocusEl = null;
  },

  _onDragStart(event) {
    if (!this.isActivityPanelOpen() || !this.sheet || !this.body) return;
    if (this.body.scrollTop > 0) return;

    event.stopImmediatePropagation();

    this._drag.startY = event.clientY;
    this._drag.dy = 0;
    this._drag.dragging = true;

    this.sheet.classList.add("is-dragging");
    this.setDragY(0);

    try { this.dragHit?.setPointerCapture(event.pointerId); } catch (_) {}
  },

  _onDragMove(event) {
    if (!this._drag.dragging || !this.sheet) return;

    event.stopImmediatePropagation();

    this._drag.dy = event.clientY - this._drag.startY;
    const clamped = Math.max(0, Math.min(this._drag.dy, 360));
    this.setDragY(clamped);

    event.preventDefault();
  },

  _onDragEnd(event) {
    if (!this._drag.dragging || !this.sheet) return;

    event.stopImmediatePropagation();

    const moved = this._drag.dy;
    this._drag.dragging = false;
    this._drag.dy = 0;

    this.sheet.classList.remove("is-dragging");

    if (moved > 90) {
      this.setDragY(0);
      this.hide();
      return;
    }

    this.setDragY(0);
    this.setBase(this.getHalfSnap());
  },
  /* === END BLOCK: ACTIVITY_DETAIL_VIEW_VALUE_METRIC_V2 === */

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
          sourceType: "",
          isSymbolic: false,
          isAiGenerated: false,
          note: ""
        };

    if (imageData.url) {
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
          sourceType: "",
          isSymbolic: false,
          isAiGenerated: false,
          note: ""
        };

    if (!window.ImageAttribution?.renderDetailAttribution) return "";

    const attributionHtml = window.ImageAttribution.renderDetailAttribution(imageData, {
      entityType: "activity",
      entityId: String(offer?.id || "").trim(),
      entityTitle: String(offer?.title || "").trim(),
      imageId: String(imageData?.id || "").trim()
    });

    return attributionHtml
      ? `<div class="detail-links detail-links--trust" aria-label="Quellen und Nachweise">${attributionHtml}</div>`
      : "";
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

  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_OPENING_STATUS_CALLOUT_V1 | Zweck: zeigt geprüfte Activity-Öffnungsstatus-Hinweise im Detailpanel an; Umfang: ergänzt nur renderOpeningStatus(offer), keine Datenlogik oder CTA-Änderung === */
  renderOpeningStatus(offer) {
    const status = offer?.opening_status && typeof offer.opening_status === "object"
      ? offer.opening_status
      : {};

    const publicLabel = String(status.public_label || "").trim();
    const detailNote = String(status.detail_note || "").trim();

    if (!publicLabel && !detailNote) return "";

    const noteHtml = detailNote && detailNote !== publicLabel
      ? `
        <details class="activity-detail__opening-details">
          <summary class="activity-detail__opening-summary">Hinweise anzeigen</summary>
          <div class="activity-detail__opening-note">${this.escapeHtml(detailNote)}</div>
        </details>
      `
      : "";

    return `
      <section class="activity-detail__fact-callout activity-detail__opening-status" aria-label="Öffnungsstatus">
        <div class="activity-detail__fact-label">Öffnungsstatus</div>
        ${publicLabel ? `<div class="activity-detail__fact-value">${this.escapeHtml(publicLabel)}</div>` : ""}
        ${noteHtml}
      </section>
    `.trim();
  },
  /* === END BLOCK: ACTIVITIES_DETAIL_OPENING_STATUS_CALLOUT_V1 === */


  /* === BEGIN BLOCK: ACTIVITIES_DETAIL_SEASONAL_HIGHLIGHT_V1 | Zweck: zeigt nur aktiv erlaubte Seasonal-Highlights und blockierte zustandsabhaengige Hinweise im Activity-Detailpanel; Umfang: reine Detailpanel-UI ohne Rankinglogik === */
  renderSeasonalHighlight(offer) {
    const highlight = window.BEActivityHighlights?.getDetailBlock?.(offer, { now: new Date(), surface: "activity" });
    const statusNote = window.BEActivityHighlights?.getConditionStatusNote?.(offer, { now: new Date(), surface: "activity" });

    if (!highlight && !statusNote) return "";

    if (highlight) {
      const note = String(highlight.note || "").trim();
      return `
        <section class="activity-detail__fact-callout activity-detail__seasonal-highlight" aria-label="Jetzt besonders">
          <div class="activity-detail__fact-label">Jetzt besonders</div>
          <div class="activity-detail__fact-value">${this.escapeHtml(highlight.title || highlight.label || "Saisonales Highlight")}</div>
          ${note ? `<div class="activity-detail__opening-note">${this.escapeHtml(note)}</div>` : ""}
        </section>
      `.trim();
    }

    const note = String(statusNote?.note || "").trim();
    return `
      <section class="activity-detail__fact-callout activity-detail__seasonal-highlight activity-detail__seasonal-highlight--status" aria-label="Statushinweis">
        <div class="activity-detail__fact-label">${this.escapeHtml(statusNote?.title || "Statushinweis")}</div>
        <div class="activity-detail__fact-value">${this.escapeHtml(statusNote?.label || "Aktuellen Status prüfen")}</div>
        ${note ? `<div class="activity-detail__opening-note">${this.escapeHtml(note)}</div>` : ""}
      </section>
    `.trim();
  },
  /* === END BLOCK: ACTIVITIES_DETAIL_SEASONAL_HIGHLIGHT_V1 === */

  /* === BEGIN BLOCK: ACTIVITY_DETAIL_FAVORITE_ACTION_V1 | Zweck: ergaenzt lokale Activity-Favoriten als Herz-Aktion im mobilen Detailpanel ohne Cookies, Login oder Backend === */
  isFavorite(offer) {
    try {
      return !!window.BEUserPreferences?.isSaved?.("activity", offer?.id);
    } catch (_) {
      return false;
    }
  },

  favoriteIconHtml() {
    return window.Icons?.svg
      ? window.Icons.svg("heart", { className: "activity-detail__actionbar-icon-svg" })
      : '<span aria-hidden="true">♥</span>';
  },

  updateFavoriteAction(button, offer, active) {
    if (!button) return;
    const title = String(offer?.title || "Aktivität").trim() || "Aktivität";
    button.classList.toggle("is-active", active);
    button.setAttribute("aria-pressed", active ? "true" : "false");
    button.setAttribute("aria-label", active ? `${title} aus Favoriten entfernen` : `${title} als Favorit speichern`);
    button.setAttribute("title", active ? "Favorit entfernen" : "Favorit");
  },

  toggleFavorite(offer, button = null) {
    const id = String(offer?.id || "").trim();
    if (!id || !window.BEUserPreferences?.toggleSaved) return false;

    let profile = null;
    try {
      profile = window.BEUserPreferences.toggleSaved("activity", id);
    } catch (_) {
      return false;
    }

    const active = Array.isArray(profile?.saved) && profile.saved.includes(`activity:${id}`);
    this.updateFavoriteAction(button, offer, active);
    window.dispatchEvent(new CustomEvent("activity:favorites-changed", { detail: { id, favorite: active } }));
    return active;
  },
  /* === END BLOCK: ACTIVITY_DETAIL_FAVORITE_ACTION_V1 === */

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
      ? window.Icons.svg("compass", { className: "activity-detail__actionbar-icon-svg" })
      : "";
    const websiteIcon = window.Icons?.svg
      ? window.Icons.svg("external-link", { className: "activity-detail__actionbar-icon-svg" })
      : "";
    const favoriteActive = this.isFavorite(offer);
    const favoriteIcon = this.favoriteIconHtml();

    const baseOutboundPayload = {
      entityType: "activity",
      entityId: String(offer?.id || "").trim(),
      entityTitle: String(offer?.title || "").trim(),
      ...(window.BEAnalytics?.buildReportingTargetPayload
        ? window.BEAnalytics.buildReportingTargetPayload(offer)
        : {})
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
        `data-reporting-target-type="${this.escapeHtml(payload.reportingTargetType || "")}"`,
        `data-reporting-target-id="${this.escapeHtml(payload.reportingTargetId || "")}"`,
        `data-reporting-target-title="${this.escapeHtml(payload.reportingTargetTitle || "")}"`,
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
          ${this.renderOpeningStatus(offer)}
          ${this.renderSeasonalHighlight(offer)}
          ${this.renderFacts(offer)}
          ${this.renderImageAttribution(offer)}
        </div>
      </article>
    `.trim();

    const actionbarHtml = [
      `
        <button
          type="button"
          class="detail-actionbar-btn is-icon activity-detail__actionbar-btn activity-detail__favorite-btn${favoriteActive ? " is-active" : ""}"
          aria-label="${this.escapeHtml(favoriteActive ? `${offer.title} aus Favoriten entfernen` : `${offer.title} als Favorit speichern`)}"
          aria-pressed="${favoriteActive ? "true" : "false"}"
          title="${favoriteActive ? "Favorit entfernen" : "Favorit"}"
          data-activity-detail-favorite
        >
          ${favoriteIcon}
          <span class="detail-sr-only">Favorit</span>
        </button>
      `,
      mapsUrl ? `
        <a
          class="detail-actionbar-btn is-icon activity-detail__actionbar-btn"
          href="${this.escapeHtml(mapsUrl)}"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="${this.escapeHtml(mapsLabel)}"
          title="Route"
          data-action="maps"
          ${buildOutboundDataAttrs(mapsOutboundPayload)}
        >
          ${mapsIcon}
          <span class="detail-sr-only">${this.escapeHtml(mapsLabel)}</span>
        </a>
      ` : "",
      websiteUrl ? `
        <a
          class="detail-actionbar-btn is-icon activity-detail__actionbar-btn"
          href="${this.escapeHtml(websiteUrl)}"
          target="_blank"
          rel="noopener noreferrer"
          aria-label="${this.escapeHtml(websiteLabel)}"
          title="Infos"
          data-action="website"
          ${buildOutboundDataAttrs(websiteOutboundPayload)}
        >
          ${websiteIcon}
          <span class="detail-sr-only">${this.escapeHtml(websiteLabel)}</span>
        </a>
      ` : ""
    ].filter(Boolean).join("");

    if (this.actionbarSlot) {
      if (actionbarHtml) {
        this.actionbarSlot.innerHTML = actionbarHtml;
        this.actionbarSlot.hidden = false;
      } else {
        this.actionbarSlot.innerHTML = "";
        this.actionbarSlot.hidden = true;
      }
    }

    const favoriteButton = this.actionbarSlot?.querySelector("[data-activity-detail-favorite]");
    favoriteButton?.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      this.toggleFavorite(offer, favoriteButton);
    });

    const bindOutboundTracking = (root) => {
      root?.querySelectorAll("a[data-outbound-type]").forEach((link) => {
        link.addEventListener("click", () => {
          if (!window.BEAnalytics || typeof window.BEAnalytics.trackOutboundClick !== "function") return;

          window.BEAnalytics.trackOutboundClick({
            outboundType: String(link.dataset.outboundType || "").trim(),
            entityType: String(link.dataset.entityType || "").trim(),
            entityId: String(link.dataset.entityId || "").trim(),
            entityTitle: String(link.dataset.entityTitle || "").trim(),
            reportingTargetType: String(link.dataset.reportingTargetType || "").trim(),
            reportingTargetId: String(link.dataset.reportingTargetId || "").trim(),
            reportingTargetTitle: String(link.dataset.reportingTargetTitle || "").trim(),
            destinationUrl: String(link.dataset.destinationUrl || link.href || "").trim()
          });
        });
      });
    };

    bindOutboundTracking(this.content);
    bindOutboundTracking(this.actionbarSlot);
  }
  /* === END BLOCK: ACTIVITIES_DETAIL_CONTENT_WITH_OUTBOUND_ANALYTICS_V3 === */
  /* === END BLOCK: ACTIVITIES_DETAIL_CONTENT_WITH_SEASONAL_META_V2 === */
/* === BEGIN BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 | Zweck: registriert das Activity-Detailpanel wieder global, damit Card-Klicks auf Mobile das Panel öffnen statt in den URL-Fallback zu laufen; Umfang: ersetzt nur das Dateiende von js/offers-details.js === */
};

window.OfferDetailPanel = OfferDetailPanel;
/* === END BLOCK: OFFERS_DETAIL_GLOBAL_EXPORT_V1 === */
