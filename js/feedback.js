/* === BEGIN FILE: js/feedback.js | Zweck: globales Feedback-System mit Formspree-Free-Direct-Submit, kontextbezogenen Meldewegen und robusten Fehlerzuständen; Umfang: komplette neue Datei === */
(() => {
  "use strict";

  const runtimeFeedbackConfig =
    (typeof CONFIG !== "undefined" && CONFIG && CONFIG.feedback)
      ? CONFIG.feedback
      : ((window.CONFIG && window.CONFIG.feedback) ? window.CONFIG.feedback : {});

  const cfg = {
    formspreeEndpoint: "",
    fallbackEmail: "",
    minMessageLength: 12,
    successAutoCloseMs: 1400,
    sourceLabel: "bocholt-erleben-web",
    privacyUrl: "/datenschutz/",
    ...runtimeFeedbackConfig
  };

  const endpoint = String(cfg.formspreeEndpoint || "").trim();
  if (!endpoint) return;

const TYPE_META = {
  bug: {
    label: "Bug melden",
    icon: "feedback-bug",
    prompt: "Was funktioniert nicht richtig?",
    placeholder: "Beschreibe kurz den Fehler und was eigentlich hätte passieren sollen."
  },
  data_issue: {
    label: "Falsche Information",
    icon: "feedback-data",
    prompt: "Welche Information ist falsch oder veraltet?",
    placeholder: "Zum Beispiel falsche Uhrzeit, falscher Ort, kaputter Link oder nicht mehr aktueller Eintrag."
  },
  idea: {
    label: "Idee oder Wunsch",
    icon: "feedback-idea",
    prompt: "Was wünschst du dir oder was sollte verbessert werden?",
    placeholder: "Zum Beispiel bessere Sortierung, fehlender Filter oder eine nützliche Zusatzinfo."
  },
  missing: {
    label: "Etwas fehlt",
    icon: "plus",
    prompt: "Was fehlt dir aktuell?",
    placeholder: "Zum Beispiel ein fehlendes Event, eine Aktivität, ein Ort oder eine Funktion."
  }
};

  const state = {
    type: "",
    shell: null,
    form: null,
    refs: {},
    opener: null,
    submitting: false,
    observer: null,
    closeTimer: null,
    inlineMounted: false,
    launcherMounted: false,
    initialized: false
  };

  const safeText = (value) => String(value ?? "").trim();
  const isDesktop = () => window.matchMedia("(min-width: 900px)").matches;

  const icon = (name, className = "") => {
    if (window.Icons && typeof window.Icons.svg === "function") {
      return window.Icons.svg(name, { className });
    }
    return "";
  };

  const escapeHtml = (value) => String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");

  function pageType() {
    const path = window.location.pathname || "/";
    if (path === "/" || path === "/index.html") return "events";
    if (path.startsWith("/angebote")) return "activities";
    if (path.startsWith("/ueber")) return "about";
    if (path.startsWith("/events-veroeffentlichen")) return "publish";
    if (path.startsWith("/impressum")) return "imprint";
    if (path.startsWith("/datenschutz")) return "privacy";
    return "page";
  }

  function createButton({ className, label, iconKey, attrs = {} }) {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = className;
    btn.innerHTML = `${icon(iconKey, "feedback-btn__icon")}<span class="feedback-btn__label">${escapeHtml(label)}</span>`;
    Object.entries(attrs).forEach(([key, value]) => btn.setAttribute(key, value));
    return btn;
  }

  function ensureLauncher() {
    if (state.launcherMounted || document.querySelector(".feedback-launcher")) return;
    const launcher = createButton({
      className: "feedback-launcher",
      label: "Feedback",
      iconKey: "feedback",
      attrs: {
        "aria-label": "Feedback geben",
        "data-feedback-open": "global"
      }
    });
    document.body.appendChild(launcher);
    state.launcherMounted = true;
  }

function ensureInlineEntry() {
  if (state.inlineMounted || isDesktop()) return;
  const main = document.querySelector("main");
  if (!main) return;

  const entry = document.createElement("section");
  entry.className = "feedback-inline-entry";
  entry.setAttribute("aria-label", "Feedback geben");
  entry.innerHTML = `
    <div class="feedback-inline-entry__copy">
      <strong>Fehler entdeckt oder Idee?</strong>
      <p>Nimm dir bitte ein paar Sekunden.</p>
    </div>
  `;

  const trigger = createButton({
    className: "feedback-inline-entry__button",
    label: "Feedback geben",
    iconKey: "feedback",
    attrs: {
      "aria-label": "Feedback geben",
      "data-feedback-open": "global"
    }
  });

  entry.appendChild(trigger);
  main.insertAdjacentElement("afterend", entry);
  state.inlineMounted = true;
}

  function ensureModalShell() {
    if (state.shell) return;

    const overlayRoot =
      document.getElementById("overlay-root") ||
      document.body;

    const shell = document.createElement("div");
    shell.className = "feedback-modal-shell";
    shell.hidden = true;
    shell.setAttribute("aria-hidden", "true");
    shell.innerHTML = `
      <div class="feedback-modal-shell__overlay" data-feedback-close></div>
      <div class="feedback-modal" role="dialog" aria-modal="true" aria-labelledby="feedback-modal-title">
        <div class="feedback-modal__scroll">
          <div class="feedback-modal__header">
            <div class="feedback-modal__header-copy">
              <h2 id="feedback-modal-title" class="feedback-modal__title">Feedback geben</h2>
              <p class="feedback-modal__subtitle">Direkt senden – ohne Mailprogramm oder Seitenwechsel.</p>
            </div>
            <button type="button" class="feedback-modal__close" aria-label="Feedback schließen" data-feedback-close>
              ${icon("x", "feedback-modal__close-icon")}
            </button>
          </div>

          <form class="feedback-form" action="${escapeHtml(endpoint)}" method="POST" novalidate>
          <div class="feedback-type-grid" role="radiogroup" aria-label="Feedback-Art"></div>
          <span class="feedback-field__error" data-feedback-error="type"></span>

          <label class="feedback-field">
            <span class="feedback-field__label" data-feedback-prompt></span>
            <textarea
              class="feedback-field__control feedback-field__control--textarea"
              name="message"
              rows="5"
              minlength="8"
              required
              data-feedback-field="message"
            ></textarea>
            <span class="feedback-field__error" data-feedback-error="message"></span>
          </label>

          <details class="feedback-optional">
            <summary>Optional: E-Mail für Rückfragen</summary>
            <label class="feedback-field feedback-field--optional">
              <span class="feedback-field__label">E-Mail</span>
              <input class="feedback-field__control" type="email" name="email" inputmode="email" autocomplete="email" placeholder="name@beispiel.de" data-feedback-field="email">
              <span class="feedback-field__error" data-feedback-error="email"></span>
            </label>
          </details>

          <div class="feedback-honeypot" aria-hidden="true">
            <label for="feedback-company">Bitte frei lassen</label>
            <input id="feedback-company" type="text" name="_gotcha" tabindex="-1" autocomplete="off">
          </div>

          <input type="hidden" name="subject" value="">
          <input type="hidden" name="feedback_type" value="">
          <input type="hidden" name="feedback_type_label" value="">
          <input type="hidden" name="source_label" value="">
          <input type="hidden" name="page_type" value="">
          <input type="hidden" name="page_url" value="">
          <input type="hidden" name="route" value="">
          <input type="hidden" name="context_title" value="">
          <input type="hidden" name="context_subtitle" value="">
          <input type="hidden" name="search_query" value="">
          <input type="hidden" name="filters" value="">
          <input type="hidden" name="viewport" value="">
          <input type="hidden" name="submitted_at" value="">

          <div class="feedback-form__meta">
            <p class="feedback-form__hint">Seite, Filter, Bezug und Bildschirmbreite werden automatisch mitgesendet.</p>
            <p class="feedback-form__privacy">Mit dem Absenden werden deine Angaben zur Bearbeitung über Formspree an Bocholt erleben übermittelt. Details in der <a href="${escapeHtml(cfg.privacyUrl || "/datenschutz/")}">Datenschutzerklärung</a>.</p>
          </div>

          <div class="feedback-form__status" aria-live="polite"></div>

          <div class="feedback-form__actions">
            <button type="button" class="feedback-secondary-btn" data-feedback-close>Abbrechen</button>
            <button type="submit" class="feedback-primary-btn">
              <span class="feedback-primary-btn__icon" aria-hidden="true">${icon("feedback-submit", "feedback-primary-btn__icon-svg")}</span>
              <span class="feedback-primary-btn__label">Absenden</span>
            </button>
          </div>
        </form>
        </div>
      </div>
    `;

    overlayRoot.appendChild(shell);
    state.shell = shell;
    state.form = shell.querySelector(".feedback-form");
    state.refs.typeGrid = shell.querySelector(".feedback-type-grid");
    state.refs.prompt = shell.querySelector("[data-feedback-prompt]");
    state.refs.status = shell.querySelector(".feedback-form__status");
    state.refs.message = shell.querySelector('[name="message"]');
    state.refs.email = shell.querySelector('[name="email"]');
    state.refs.subject = shell.querySelector('[name="subject"]');
    state.refs.feedbackType = shell.querySelector('[name="feedback_type"]');
    state.refs.feedbackTypeLabel = shell.querySelector('[name="feedback_type_label"]');
    state.refs.sourceLabel = shell.querySelector('[name="source_label"]');
    state.refs.pageType = shell.querySelector('[name="page_type"]');
    state.refs.pageUrl = shell.querySelector('[name="page_url"]');
    state.refs.route = shell.querySelector('[name="route"]');
    state.refs.contextTitle = shell.querySelector('[name="context_title"]');
    state.refs.contextSubtitle = shell.querySelector('[name="context_subtitle"]');
    state.refs.searchQuery = shell.querySelector('[name="search_query"]');
    state.refs.filters = shell.querySelector('[name="filters"]');
    state.refs.viewport = shell.querySelector('[name="viewport"]');
    state.refs.submittedAt = shell.querySelector('[name="submitted_at"]');
    state.refs.submit = shell.querySelector(".feedback-primary-btn");
    renderTypeOptions();
    syncTypeUi();
  }

  function renderTypeOptions() {
    if (!state.refs.typeGrid) return;
    state.refs.typeGrid.innerHTML = Object.entries(TYPE_META).map(([key, meta]) => `
      <button
        type="button"
        class="feedback-type-chip${key === state.type ? " is-active" : ""}"
        data-feedback-type="${key}"
        role="radio"
        aria-checked="${key === state.type ? "true" : "false"}"
      >
        <span class="feedback-type-chip__icon" aria-hidden="true">${icon(meta.icon, "feedback-type-chip__icon-svg")}</span>
        <span class="feedback-type-chip__label">${escapeHtml(meta.label)}</span>
      </button>
    `).join("");
  }

  function getEventPanelContext() {
    const panel = document.querySelector("#overlay-root #event-detail-panel:not(.hidden)");
    if (!panel) return null;
    const title = safeText(panel.querySelector(".detail-title")?.textContent);
    if (!title) return null;
    const location = safeText(panel.querySelector(".detail-meta-row.is-location .detail-meta-text")?.textContent);
    const datetime = safeText(panel.querySelector(".detail-meta-row.is-datetime .detail-meta-text")?.textContent);
    return {
      entityType: "event",
      entityTitle: title,
      entitySubtitle: [location, datetime].filter(Boolean).join(" · ")
    };
  }

  function getOfferPanelContext() {
    const panel = document.querySelector("#offer-detail-root #event-detail-panel:not(.hidden)");
    if (!panel) return null;
    const title = safeText(panel.querySelector(".activity-detail__title")?.textContent);
    if (!title) return null;
    const location = safeText(panel.querySelector(".activity-detail__place")?.textContent);
    const meta = safeText(panel.querySelector(".activity-detail__meta")?.textContent);
    return {
      entityType: "activity",
      entityTitle: title,
      entitySubtitle: [location, meta].filter(Boolean).join(" · ")
    };
  }

  function getActiveContext() {
    return getOfferPanelContext() || getEventPanelContext() || null;
  }

  function getSearchValue() {
    return safeText(document.querySelector("#search-filter")?.value);
  }

  function getFilters() {
    const items = [];
    const add = (label, value) => {
      const clean = safeText(value);
      if (!clean || clean === "Alle") return;
      items.push(`${label}: ${clean}`);
    };

    add("Wann", document.getElementById("filter-time-value")?.textContent);
    add("Kategorie", document.getElementById("filter-category-value")?.textContent);
    add("Merkmal", document.getElementById("offer-situation-value")?.textContent);
    add("Kategorie", document.getElementById("offer-category-value")?.textContent);

    return items.join(" | ");
  }

  /* === BEGIN BLOCK: FEEDBACK_CONTEXT_SUMMARY_HIDE_REDUNDANT_PAGE_V1 | Zweck: zeigt den Bezug nur noch dann an, wenn wirklich ein konkreter Event- oder Activity-Kontext vorliegt, statt pauschal „Aktuelle Seite“ auszuspielen; Umfang: ersetzt nur contextSummary() in js/feedback.js === */
  function contextSummary() {
    const context = getActiveContext();
    if (!context) return "";
    return [context.entityTitle, context.entitySubtitle].filter(Boolean).join(" · ");
  }
  /* === END BLOCK: FEEDBACK_CONTEXT_SUMMARY_HIDE_REDUNDANT_PAGE_V1 === */

  /* === BEGIN BLOCK: FEEDBACK_TYPE_UI_OPTIONAL_DEFAULT_V1 | Zweck: unterstützt einen leeren Startzustand ohne Vorauswahl und stellt trotzdem eine klare generische Führung für Textfeld und Chips bereit | Umfang: ersetzt nur syncTypeUi() === */
  function syncTypeUi() {
    if (!state.refs.prompt || !state.refs.typeGrid) return;

    const meta = TYPE_META[state.type] || null;
    state.refs.prompt.textContent = meta ? meta.prompt : "Worum geht es?";
    state.refs.message.placeholder = meta
      ? meta.placeholder
      : "Beschreibe kurz dein Feedback und wähle oben die passende Art aus.";

    state.refs.typeGrid.querySelectorAll("[data-feedback-type]").forEach((node) => {
      const active = node.getAttribute("data-feedback-type") === state.type;
      node.classList.toggle("is-active", active);
      node.setAttribute("aria-checked", active ? "true" : "false");
    });

    if (meta) {
      const typeError = state.form?.querySelector('[data-feedback-error="type"]');
      if (typeError) {
        typeError.textContent = "";
        typeError.removeAttribute("data-active");
      }
    }

  }
  /* === END BLOCK: FEEDBACK_TYPE_UI_OPTIONAL_DEFAULT_V1 === */

  function clearErrors() {
    state.form?.querySelectorAll("[data-feedback-error]").forEach((node) => {
      node.textContent = "";
      node.removeAttribute("data-active");
    });
    state.form?.querySelectorAll("[data-feedback-field]").forEach((node) => {
      node.removeAttribute("aria-invalid");
    });
  }

  function setFieldError(name, message) {
    const field = state.form?.querySelector(`[data-feedback-field="${name}"]`);
    const error = state.form?.querySelector(`[data-feedback-error="${name}"]`);
    if (field) field.setAttribute("aria-invalid", "true");
    if (error) {
      error.textContent = message;
      error.setAttribute("data-active", "true");
    }
  }

  /* === BEGIN BLOCK: FEEDBACK_STATUS_AND_SUCCESS_HELPERS_V1 | Zweck: ergänzt robusten Success-State mit Timer-Cleanup und Modal-interner Success-Umschaltung, ohne das bestehende Fehler-/Validierungsverhalten zu verändern; Umfang: ersetzt setStatus() und ergänzt Helper in js/feedback.js === */
  function setStatus(message, kind = "neutral", allowHtml = false) {
    if (!state.refs.status) return;
    state.refs.status.className = message ? `feedback-form__status is-${kind}` : "feedback-form__status";
    if (allowHtml) {
      state.refs.status.innerHTML = message;
    } else {
      state.refs.status.textContent = message;
    }
  }

  function clearCloseTimer() {
    if (!state.closeTimer) return;
    window.clearTimeout(state.closeTimer);
    state.closeTimer = null;
  }

  function setFormSuccessState(active) {
    if (!state.form) return;

    state.form.classList.toggle("is-success", active);

    const secondary = state.form.querySelector(".feedback-secondary-btn");
    if (secondary) {
      secondary.textContent = active ? "Schließen" : "Abbrechen";
    }

    if (state.refs.submit) {
      state.refs.submit.disabled = active || state.submitting;
    }
  }
  /* === END BLOCK: FEEDBACK_STATUS_AND_SUCCESS_HELPERS_V1 === */

  /* === BEGIN BLOCK: FEEDBACK_HIDDEN_FIELDS_CLEAN_SUBJECT_V2 | Zweck: trennt den internen Mail-Betreff vom entfernten UI-Bezug und nutzt ohne konkreten Kontext weiterhin einen sauberen Seitentyp-Fallback | Umfang: ersetzt nur den Kopf von fillHiddenFields() in js/feedback.js === */
  function fillHiddenFields() {
    const context = getActiveContext();
    const search = getSearchValue();
    const filters = getFilters();
    const summary = context
      ? [context.entityTitle, context.entitySubtitle].filter(Boolean).join(" · ")
      : pageType();

    state.refs.feedbackType.value = state.type;
    state.refs.feedbackTypeLabel.value = TYPE_META[state.type]?.label || "Feedback";
    state.refs.sourceLabel.value = safeText(cfg.sourceLabel || "bocholt-erleben-web");
    state.refs.pageType.value = pageType();
    state.refs.pageUrl.value = window.location.href;
    state.refs.route.value = window.location.pathname;
    state.refs.contextTitle.value = context?.entityTitle || "";
    state.refs.contextSubtitle.value = context?.entitySubtitle || "";
    state.refs.searchQuery.value = search;
    state.refs.filters.value = filters;
    state.refs.viewport.value = `${window.innerWidth}x${window.innerHeight}`;
    state.refs.submittedAt.value = new Date().toISOString();
    state.refs.subject.value = `[Bocholt erleben] ${TYPE_META[state.type]?.label || "Feedback"} · ${summary}`;
  }
  /* === END BLOCK: FEEDBACK_HIDDEN_FIELDS_CONTEXTLESS_UI_V2 === */

/* === BEGIN BLOCK: FEEDBACK_OPEN_WITH_OPTIONAL_PRESELECT_V5 | Zweck: setzt beim Öffnen die neue innere Scroll-Fläche sauber auf Start zurück und hält den Mobile-Einstieg oben stabil; Umfang: ersetzt openModal() in js/feedback.js === */
function openModal(opener = null, forcedType = null) {
  ensureModalShell();
  clearCloseTimer();
  setFormSuccessState(false);

  const optionalDetails = state.form?.querySelector(".feedback-optional");
  if (optionalDetails) {
    optionalDetails.open = false;
  }

  const scrollSurface = state.shell?.querySelector(".feedback-modal__scroll");
  const closeButton = state.shell?.querySelector(".feedback-modal__close");
  if (scrollSurface) {
    scrollSurface.scrollTop = 0;
  }

  state.opener = opener;
  state.type = TYPE_META[forcedType] ? forcedType : "";
  clearErrors();
  setStatus("");
  syncTypeUi();

  state.shell.hidden = false;
  state.shell.classList.add("is-active");
  state.shell.setAttribute("aria-hidden", "false");

  document.documentElement.classList.add("is-feedback-open");
  document.body.classList.add("is-feedback-open");

  requestAnimationFrame(() => {
    if (scrollSurface) {
      scrollSurface.scrollTop = 0;
    }

    try {
      if (isDesktop()) {
        state.refs.message?.focus();
      } else {
        closeButton?.focus({ preventScroll: true });
      }
    } catch (_) {}
  });
}
/* === END BLOCK: FEEDBACK_OPEN_WITH_OPTIONAL_PRESELECT_V5 === */

  /* === BEGIN BLOCK: FEEDBACK_MODAL_CLOSE_STATE_SYNC_V2 | Zweck: räumt beim Schließen zusätzlich Success-State und Auto-Close-Timer robust auf, ohne den normalen Draft-Flow zu beschädigen; Umfang: ersetzt closeModal() in js/feedback.js === */
  function closeModal() {
    if (!state.shell) return;

    const hadSuccessState = state.form?.classList.contains("is-success");
    clearCloseTimer();

    state.shell.classList.remove("is-active");
    state.shell.hidden = true;
    state.shell.setAttribute("aria-hidden", "true");

    document.documentElement.classList.remove("is-feedback-open");
    document.body.classList.remove("is-feedback-open");

    if (hadSuccessState) {
      resetFormUiState();
    } else {
      setFormSuccessState(false);
      clearErrors();
      setStatus("");
    }

    if (state.opener && typeof state.opener.focus === "function") {
      state.opener.focus({ preventScroll: true });
    }
  }
  /* === END BLOCK: FEEDBACK_MODAL_CLOSE_STATE_SYNC_V2 === */

  /* === BEGIN BLOCK: FEEDBACK_VALIDATE_REQUIRED_TYPE_V1 | Zweck: macht die Auswahl der Feedback-Art beim globalen Einstieg verpflichtend und prüft zusätzlich weiterhin Nachricht und optionale E-Mail | Umfang: ersetzt nur validateForm() === */
  function validateForm() {
    clearErrors();
    const message = safeText(state.refs.message.value);
    const email = safeText(state.refs.email.value);
    let valid = true;

    if (!TYPE_META[state.type]) {
      setFieldError("type", "Bitte zuerst auswählen, worum es geht.");
      valid = false;
    }

    if (message.length < Number(cfg.minMessageLength || 12)) {
      setFieldError("message", `Bitte etwas genauer beschreiben (mindestens ${Number(cfg.minMessageLength || 12)} Zeichen).`);
      valid = false;
    }

    if (email) {
      const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      if (!emailValid) {
        setFieldError("email", "Bitte eine gültige E-Mail-Adresse eingeben oder das Feld leer lassen.");
        valid = false;
      }
    }

    return valid;
  }
  /* === END BLOCK: FEEDBACK_VALIDATE_REQUIRED_TYPE_V1 === */

  /* === BEGIN BLOCK: FEEDBACK_SUBMIT_SUCCESS_STATE_ENTERPRISE_V3 | Zweck: setzt den Feedback-Dialog nach jedem Öffnen sauber zurück, verhindert offen bleibende Optional-Details und macht den auto-schließenden Success-State etwas lesbarer, ohne zusätzlichen Footer-CTA; Umfang: resetFormUiState(), pruneFormData() und submitForm() in js/feedback.js === */
  function resetFormUiState() {
    clearCloseTimer();
    state.form.reset();
    const optionalDetails = state.form?.querySelector(".feedback-optional");
    if (optionalDetails) {
      optionalDetails.open = false;
    }
    state.type = "";
    renderTypeOptions();
    setFormSuccessState(false);
    syncTypeUi();
    clearErrors();
    setStatus("");
  }

  function pruneFormData(formData) {
    ["source_label", "feedback_type", "route", "viewport", "submitted_at"].forEach((key) => {
      formData.delete(key);
    });

    Array.from(formData.entries()).forEach(([key, value]) => {
      if (typeof value === "string" && !safeText(value)) {
        formData.delete(key);
      }
    });
  }

  async function submitForm(event) {
    event.preventDefault();
    if (state.submitting) return;

    if (!validateForm()) {
      setStatus("Bitte die markierten Felder prüfen.", "error");
      return;
    }

    fillHiddenFields();

    const formData = new FormData(state.form);
    pruneFormData(formData);

    state.submitting = true;
    setFormSuccessState(false);
    state.refs.submit.disabled = true;
    setStatus("Feedback wird gesendet…", "neutral");

    try {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: {
          Accept: "application/json"
        },
        body: formData
      });

      let payload = null;
      try {
        payload = await response.json();
      } catch (error) {
        payload = null;
      }

      if (!response.ok) {
        if (response.status === 429) {
          const fallbackEmail = safeText(cfg.fallbackEmail);
          const fallbackHtml = fallbackEmail
            ? `Das Feedback-Limit ist aktuell erreicht. Bitte später erneut versuchen oder direkt an <a href="mailto:${escapeHtml(fallbackEmail)}">${escapeHtml(fallbackEmail)}</a> schreiben.`
            : "Das Feedback-Limit ist aktuell erreicht. Bitte später erneut versuchen.";
          setStatus(fallbackHtml, "error", true);
          return;
        }

        const firstError = Array.isArray(payload?.errors) ? payload.errors[0] : null;
        if (firstError?.field) {
          setFieldError(firstError.field, firstError.message || "Bitte Eingabe prüfen.");
          setStatus("Bitte die markierten Felder prüfen.", "error");
        } else {
          setStatus(firstError?.message || "Absenden hat nicht geklappt. Bitte später erneut versuchen.", "error");
        }
        return;
      }

      setFormSuccessState(true);
      setStatus(`
        <div class="feedback-success-card" role="status">
          <span class="feedback-success-card__icon" aria-hidden="true">${icon("feedback-success", "feedback-success-card__icon-svg")}</span>
          <span class="feedback-success-card__body">
            <strong class="feedback-success-card__title">Feedback gesendet</strong>
            <span class="feedback-success-card__text">Danke — deine Rückmeldung ist angekommen.</span>
            <span class="feedback-success-card__meta">Das Fenster schließt sich automatisch.</span>
          </span>
        </div>
      `, "success", true);

      const closeDelayMs = Math.max(Number(cfg.successAutoCloseMs || 1400), 3200);
      clearCloseTimer();
      state.closeTimer = window.setTimeout(() => {
        resetFormUiState();
        closeModal();
      }, closeDelayMs);
    } catch (error) {
      console.error("Feedback submit failed", error);
      setStatus("Absenden hat nicht geklappt. Bitte Verbindung prüfen und erneut versuchen.", "error");
    } finally {
      state.submitting = false;
      if (!state.form?.classList.contains("is-success")) {
        state.refs.submit.disabled = false;
      }
    }
  }
  /* === END BLOCK: FEEDBACK_SUBMIT_SUCCESS_STATE_ENTERPRISE_V3 === */

/* === BEGIN BLOCK: FEEDBACK_CONTEXT_TRIGGER_HIERARCHY_V4 | Zweck: benennt die panelinterne Datenkorrektur semantisch sauber um, ohne die tertiäre Utility-Hierarchie zu verändern; Umfang: ersetzt ensureEventPanelTrigger() und ensureOfferPanelTrigger() in js/feedback.js === */
function ensureEventPanelTrigger() {
  const inner = document.querySelector("#overlay-root #event-detail-panel:not(.hidden) .detail-panel-inner");
  if (!inner) return;

  const primaryActions = inner.querySelector(".detail-actions");
  let slot = inner.querySelector(".feedback-context-slot--event");

  if (!slot) {
    slot = document.createElement("div");
    slot.className = "feedback-context-slot feedback-context-slot--event";

    if (primaryActions) {
      primaryActions.insertAdjacentElement("beforebegin", slot);
    } else {
      inner.appendChild(slot);
    }
  }

  if (slot.querySelector(".feedback-context-trigger")) return;

  const trigger = createButton({
    className: "feedback-context-trigger feedback-context-trigger--event",
    label: "Falsche Info melden",
    iconKey: "feedback-data",
    attrs: {
      "aria-label": "Falsche Info zu diesem Event melden",
      "data-feedback-open": "data_issue"
    }
  });

  slot.appendChild(trigger);
}

function ensureOfferPanelTrigger() {
  const body = document.querySelector("#offer-detail-root #event-detail-panel:not(.hidden) .activity-detail__body");
  const primaryActions = body?.querySelector(".activity-detail__actions");
  if (!body || !primaryActions) return;

  let slot = body.querySelector(".feedback-context-slot--activity");
  if (!slot) {
    slot = document.createElement("div");
    slot.className = "feedback-context-slot feedback-context-slot--activity";
    primaryActions.insertAdjacentElement("afterend", slot);
  }

  if (slot.querySelector(".feedback-context-trigger")) return;

  const trigger = createButton({
    className: "feedback-context-trigger feedback-context-trigger--activity",
    label: "Falsche Info melden",
    iconKey: "feedback-data",
    attrs: {
      "aria-label": "Falsche Info zu dieser Aktivität melden",
      "data-feedback-open": "data_issue"
    }
  });

  slot.appendChild(trigger);
}
/* === END BLOCK: FEEDBACK_CONTEXT_TRIGGER_HIERARCHY_V4 === */

  /* === BEGIN BLOCK: FEEDBACK_CONTEXT_REFRESH_DECOUPLE_MODAL_V1 | Zweck: entkoppelt die globale Observer-Refresh-Logik von der Feedback-Modal-UI, damit DOM-Mutationen des Modals keinen Re-Entry mehr auslösen und die Seite nicht blockieren | Umfang: ersetzt nur refreshContextTriggers() in js/feedback.js === */
  function refreshContextTriggers() {
    ensureEventPanelTrigger();
    ensureOfferPanelTrigger();
  }
  /* === END BLOCK: FEEDBACK_CONTEXT_REFRESH_DECOUPLE_MODAL_V1 === */

  function bindEvents() {
    document.addEventListener("click", (event) => {
      const openTrigger = event.target.closest("[data-feedback-open]");
      if (openTrigger) {
        event.preventDefault();
        const forcedType = openTrigger.getAttribute("data-feedback-open");
        openModal(openTrigger, forcedType === "global" ? null : forcedType);
        return;
      }

      if (event.target.closest("[data-feedback-close]")) {
        event.preventDefault();
        closeModal();
      }
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && state.shell && !state.shell.hidden) {
        closeModal();
      }
    });

    state.form.addEventListener("submit", submitForm);
    state.refs.typeGrid.addEventListener("click", (event) => {
      const chip = event.target.closest("[data-feedback-type]");
      if (!chip) return;
      state.type = chip.getAttribute("data-feedback-type");
      syncTypeUi();
    });

    window.addEventListener("resize", () => {
      if (!isDesktop() && !state.inlineMounted) ensureInlineEntry();
    });
  }

  function startObserver() {
    if (state.observer) return;
    state.observer = new MutationObserver(() => refreshContextTriggers());
    state.observer.observe(document.body, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ["class", "hidden"]
    });
  }

  function init() {
    if (state.initialized) return;
    ensureLauncher();
    ensureInlineEntry();
    ensureModalShell();
    bindEvents();
    refreshContextTriggers();
    startObserver();
    state.initialized = true;
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
/* === END FILE: js/feedback.js === */
