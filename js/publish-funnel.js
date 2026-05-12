/* === BEGIN FILE: js/publish-funnel.js | Zweck: zentraler JS-Owner für den Veranstalter-Funnel mit prüfpflichtiger Einzeltermin-Einreichung und Mailto-Vorbereitung im Profi-Pfad; Umfang: komplette Datei === */
(() => {
  "use strict";

  const runtimeConfig =
    (typeof CONFIG !== "undefined" && CONFIG && CONFIG.publishFunnel)
      ? CONFIG.publishFunnel
      : ((window.CONFIG && window.CONFIG.publishFunnel) ? window.CONFIG.publishFunnel : (window.BE_PUBLISH_FUNNEL_CONFIG || {}));

  /* === BEGIN BLOCK: PUBLISH_FUNNEL_RUNTIME_AND_AUTOMATION_FORMSPREE_V3 | Zweck: hält Runtime-Config syntaktisch korrekt und aktualisiert Standardmeldungen fuer die kostenlose Pruefung automatischer Uebernahme; Umfang: kompletter cfg-Initialisierungsblock === */
  const cfg = {
    automationEmail: "mathias@bocholt-erleben.de",
    automationFormspreeEndpoint: "",
    automationSuccessMessage: "Anfrage erfolgreich gesendet. Wir prüfen deine Angaben und melden uns, falls noch etwas fehlt.",
    automationErrorMessage: "Die Anfrage konnte gerade nicht gesendet werden. Bitte versuche es erneut oder nutze alternativ die direkte Kontaktmöglichkeit.",
    paymentLinks: {
      single: "",
      starter: "",
      active: "",
      unlimited: ""
    },
    ...runtimeConfig,
    paymentLinks: {
      single: "",
      starter: "",
      active: "",
      unlimited: "",
      ...(runtimeConfig && runtimeConfig.paymentLinks ? runtimeConfig.paymentLinks : {})
    }
  };
  /* === END BLOCK: PUBLISH_FUNNEL_RUNTIME_AND_AUTOMATION_FORMSPREE_V3 === */

  const byId = (id) => document.getElementById(id);
  const safeText = (value) => String(value ?? "").trim();
  const hasValue = (value) => safeText(value).length > 0;

  function isPublishRoute() {
    const path = window.location.pathname || "/";
    return path.startsWith("/events-veroeffentlichen/");
  }

  function slugify(value) {
    return safeText(value)
      .toLowerCase()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "")
      .slice(0, 40);
  }

  function lineIf(label, value) {
    return hasValue(value) ? `${label}: ${safeText(value)}` : "";
  }

  function joinLines(lines) {
    return lines.filter((line) => hasValue(line)).join("\n");
  }

  function buildMailto(subject, body) {
    return `mailto:${encodeURIComponent(cfg.automationEmail)}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  }

  function buildLeadId(plan, organization) {
    return `be_publish_${safeText(plan)}_${Date.now().toString(36)}_${slugify(organization)}`.slice(0, 120);
  }

  function getPlanPaymentLink(plan) {
    switch (safeText(plan)) {
      case "single":
        return safeText(cfg.paymentLinks.single);
      case "starter":
        return safeText(cfg.paymentLinks.starter);
      case "active":
        return safeText(cfg.paymentLinks.active);
      case "unlimited":
        return safeText(cfg.paymentLinks.unlimited);
      default:
        return "";
    }
  }

  /* === BEGIN BLOCK: PUBLISH_STANDARD_PORTAL_SYNC_V1 | Zweck: liest optionale Portal-Session, fuellt bekannte Veranstalterdaten vor und sperrt das Modell bei aktivem Abo; Umfang: Plan-Preset-, Portal- und Modell-Lock-Helfer === */
  function getAutomationEndpoint() {
    return safeText(cfg.automationFormspreeEndpoint);
  }

  function getAllowedPlanKeys() {
    return ["single", "starter", "active", "unlimited"];
  }

  function formatPlanLabel(planKey) {
    const key = safeText(planKey).toLowerCase();

    return ({
      single: "Einzeltermin",
      starter: "Starter",
      active: "Aktiv",
      unlimited: "Dauerhaft"
    })[key] || key;
  }

  function ensurePlanLockHint(message) {
    const planSelect = byId("publish-standard-plan");
    if (!planSelect) return;

    let hint = document.getElementById("publish-standard-plan-lock-hint");
    if (!hint) {
      hint = document.createElement("p");
      hint.id = "publish-standard-plan-lock-hint";
      hint.className = "content-form-note";
      planSelect.insertAdjacentElement("afterend", hint);
    }

    hint.textContent = message;
  }

// === BEGIN FUNCTION: applyPlanPresetFromUrl | Zweck: setzt URL-Plan-Presets nur bei echten Modell-Selects und lässt Einzeltermin-Hidden-Inputs unverändert; Umfang: komplette Funktion ===
function applyPlanPresetFromUrl() {
  const planSelect = byId("publish-standard-plan");
  if (!planSelect || planSelect.tagName !== "SELECT") return;

  const params = new URLSearchParams(window.location.search);
  const requestedPlan = safeText(params.get("plan")).toLowerCase();
  if (!requestedPlan) return;

  const allowedPlans = getAllowedPlanKeys();
  if (!allowedPlans.includes(requestedPlan)) return;

  const hasOption = Array.from(planSelect.options).some((option) => safeText(option.value) === requestedPlan);
  if (!hasOption) return;

  planSelect.value = requestedPlan;
}
// === END FUNCTION: applyPlanPresetFromUrl ===

  async function tryLoadOrganizerPortalState() {
    const response = await fetch("/api/organizer-portal/me.php", {
      method: "GET",
      credentials: "same-origin",
      headers: {
        Accept: "application/json"
      }
    });

    if (response.status === 401) {
      return null;
    }

    const rawText = await response.text();
    let data = null;

    try {
      data = rawText ? JSON.parse(rawText) : null;
    } catch (_error) {
      data = null;
    }

    if (!response.ok) {
      return null;
    }

    return data?.data || null;
  }

  async function syncStandardFormWithPortalSession() {
    /* === BEGIN FUNCTION: syncStandardFormWithPortalSession | Zweck: übernimmt aktive Veranstalter-Session auch auf der Einzelterminseite und sperrt bekannte Stammdaten; Umfang: komplette Funktion === */
    const form = byId("publish-standard-form");
    if (!form) return;

    const pageMode = safeText(document.body?.dataset.publishMode).toLowerCase();
    if (!["single", "standard"].includes(pageMode)) return;

    const portalData = await tryLoadOrganizerPortalState();
    if (!portalData || typeof portalData !== "object") return;

    const organizer = portalData.organizer || {};
    const subscription = portalData.subscription || null;

    const planField = byId("publish-standard-plan");
    const organizationField = byId("publish-standard-organization");
    const contactField = byId("publish-standard-contact");
    const emailField = byId("publish-standard-email");

    function lockField(field, value) {
      const normalizedValue = safeText(value);
      if (!field || !normalizedValue) return;

      field.value = normalizedValue;
      field.readOnly = true;
      field.setAttribute("readonly", "readonly");
      field.setAttribute("aria-readonly", "true");
      field.dataset.portalLocked = "true";
    }

    lockField(organizationField, organizer.organization_name);
    lockField(contactField, organizer.contact_name);
    lockField(emailField, organizer.email);

    const activePlanKey = safeText(subscription?.plan_key).toLowerCase();
    if (!planField || !["starter", "active", "unlimited"].includes(activePlanKey)) return;

    planField.dataset.activeMembershipPlan = activePlanKey;

    if (planField.tagName !== "SELECT") return;

    const hasOption = Array.from(planField.options).some((option) => safeText(option.value) === activePlanKey);
    if (!hasOption) return;

    planField.value = activePlanKey;
    planField.disabled = true;
    planField.setAttribute("aria-disabled", "true");
    planField.dataset.portalLocked = "true";

    ensurePlanLockHint(
      `Deine aktive Mitgliedschaft ${formatPlanLabel(activePlanKey)} wird für diese Einreichung automatisch verwendet. Änderungen verwaltest du in deinem Veranstalterbereich.`
    );
    /* === END FUNCTION: syncStandardFormWithPortalSession === */
  }
  /* === END BLOCK: PUBLISH_STANDARD_PORTAL_SYNC_V1 === */
  
  function persistCheckoutContext(context) {
    try {
      window.sessionStorage.setItem(cfg.storageKey, JSON.stringify(context));
    } catch (error) {
      console.warn("Publish funnel: checkout context could not be stored.", error);
    }
  }

  // === BEGIN BLOCK: PUBLISH_SINGLE_AND_AUTOMATION_CONTEXTS_V4 | Zweck: konsolidiert Einzeltermin-Kontext, Automationsanfrage und Standard-Checkout-Helper ohne doppelte/halb eingefügte Funktionen; Umfang: kompletter Block von buildStandardContext bis setStandardSubmitting ===
  function buildStandardContext() {
    const placeStreet = safeText(byId("publish-standard-place-street")?.value);
    const placeZip = safeText(byId("publish-standard-place-zip")?.value);
    const placeCity = safeText(byId("publish-standard-place-city")?.value);
    const placeAddress = [
      placeStreet,
      [placeZip, placeCity].filter(hasValue).join(" ")
    ].filter(hasValue).join(", ");

    return {
      plan: safeText(byId("publish-standard-plan")?.value),
      organization: safeText(byId("publish-standard-organization")?.value),
      contact: "",
      email: safeText(byId("publish-standard-email")?.value),
      eventLink: safeText(byId("publish-standard-event-link")?.value),
      title: safeText(byId("publish-standard-title")?.value),
      date: safeText(byId("publish-standard-date")?.value),
      time: safeText(byId("publish-standard-time")?.value),
      place: safeText(byId("publish-standard-place")?.value),
      placeStreet,
      placeZip,
      placeCity,
      placeAddress,
      website: safeText(byId("publish-standard-website")?.value),
      description: safeText(byId("publish-standard-description")?.value),
      notes: safeText(byId("publish-standard-notes")?.value),
      locationConfirmed: byId("publish-standard-location-confirmed")?.checked === true
    };
  }

  function buildAutomationContext() {
    return {
      organization: safeText(byId("publish-automation-organization")?.value),
      website: safeText(byId("publish-automation-website")?.value),
      contact: safeText(byId("publish-automation-contact")?.value),
      email: safeText(byId("publish-automation-email")?.value),
      techContact: safeText(byId("publish-automation-tech-contact")?.value),
      monthlyVolume: safeText(byId("publish-automation-volume")?.value),
      sourceType: safeText(byId("publish-automation-source-type")?.value),
      sourceLink: safeText(byId("publish-automation-source-link")?.value),
      cadence: safeText(byId("publish-automation-cadence")?.value),
      notes: safeText(byId("publish-automation-notes")?.value)
    };
  }

  /* === BEGIN BLOCK: PUBLISH_AUTOMATION_SUMMARY_STATUS_AND_VALIDATION_V2 | Zweck: aktualisiert Mail-/Status-Wording auf automatische Übernahme und stellt Validierungshelfer für rote Pflichtfeldmarkierung bereit; Umfang: Automation-Summary, Submit-Status und Feldvalidierung bis vor submitAutomationFormspree === */
/* === BEGIN BLOCK: PUBLISH_AUTOMATION_SUMMARY_SOURCE_HINT_V1 | Zweck: benennt die optionale Quelle neutral als Link oder Hinweis, damit lokale Dateien/Tabellen korrekt abgebildet werden; Umfang: ersetzt buildAutomationSummary === */
function buildAutomationSummary(context) {
  return joinLines([
    "Hallo Mathias,",
    "",
    "ich möchte eine kostenlose Prüfung für eine automatische Übernahme unserer Veranstaltungen bei Bocholt erleben anfragen.",
    "",
    lineIf("Organisation / Veranstalter", context.organization),
    lineIf("Ansprechperson", context.contact),
    lineIf("E-Mail", context.email),
    lineIf("Wo stehen die Veranstaltungen?", context.sourceType),
    lineIf("Link oder Hinweis zur Quelle", context.sourceLink),
    lineIf("Aktualisierungsrhythmus", context.cadence),
    lineIf("Geschätzte Anzahl Veranstaltungen pro Monat", context.monthlyVolume),
    lineIf("Technische Ansprechperson", context.techContact),
    "",
    lineIf("Weitere Hinweise (optional)", context.notes),
    "",
    "Viele Grüße"
  ]);
}
/* === END BLOCK: PUBLISH_AUTOMATION_SUMMARY_SOURCE_HINT_V1 === */

  function ensureAutomationStatusNode(form) {
    let statusNode = form.querySelector("[data-publish-automation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-publish-automation-status", "");
    statusNode.setAttribute("aria-live", "polite");

    const referenceNode =
      form.querySelector(".publish-final-actions")?.nextElementSibling ||
      form.querySelector(".publish-final-actions");

    if (referenceNode) {
      referenceNode.insertAdjacentElement("beforebegin", statusNode);
    } else {
      form.appendChild(statusNode);
    }

    return statusNode;
  }

  function setAutomationStatus(form, kind, message) {
    const statusNode = ensureAutomationStatusNode(form);
    statusNode.hidden = false;
    statusNode.textContent = safeText(message);
    statusNode.dataset.state = kind === "error" ? "error" : "success";
    statusNode.setAttribute("aria-live", kind === "error" ? "assertive" : "polite");
  }

  function clearAutomationStatus(form) {
    const statusNode = form.querySelector("[data-publish-automation-status]");
    if (!statusNode) return;
    statusNode.hidden = true;
    statusNode.textContent = "";
    delete statusNode.dataset.state;
  }

  function ensureAutomationValidationStatus(form) {
    let statusNode = form.querySelector("[data-automation-validation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note publish-validation-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-automation-validation-status", "");
    statusNode.setAttribute("aria-live", "assertive");

    const referenceNode = form.querySelector(".publish-final-actions");
    if (referenceNode) {
      referenceNode.insertAdjacentElement("beforebegin", statusNode);
    } else {
      form.appendChild(statusNode);
    }

    return statusNode;
  }

  function setAutomationValidationStatus(form, message) {
    const statusNode = ensureAutomationValidationStatus(form);
    statusNode.hidden = false;
    statusNode.textContent = safeText(message);
  }

  function clearAutomationValidationStatus(form) {
    const statusNode = form.querySelector("[data-automation-validation-status]");
    if (!statusNode) return;

    statusNode.hidden = true;
    statusNode.textContent = "";
  }

  function getAutomationField(control) {
    return control?.closest?.(".content-field") || null;
  }

  function clearAutomationControlValidation(control) {
    if (!control || !control.id || !control.id.startsWith("publish-automation-")) return;

    control.removeAttribute("aria-invalid");

    const field = getAutomationField(control);
    if (field) {
      delete field.dataset.fieldInvalid;
    }

    const form = control.closest("form");
    if (form && !form.querySelector('[data-field-invalid="true"]')) {
      clearAutomationValidationStatus(form);
    }
  }

  function clearAutomationValidationState(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((control) => {
      control.removeAttribute("aria-invalid");
    });

    form.querySelectorAll('[data-field-invalid="true"]').forEach((field) => {
      delete field.dataset.fieldInvalid;
    });

    clearAutomationValidationStatus(form);
  }

  function markAutomationFieldInvalid(id) {
    const control = byId(id);
    if (!control) return null;

    control.setAttribute("aria-invalid", "true");

    const field = getAutomationField(control);
    if (field) {
      field.dataset.fieldInvalid = "true";
    }

    return control;
  }

  function setAutomationSubmitting(trigger, isSubmitting) {
    if (!trigger) return;
    if (!trigger.dataset.defaultLabel) {
      trigger.dataset.defaultLabel = trigger.textContent || "Kostenlose Prüfung anfragen";
    }

    trigger.disabled = isSubmitting;
    trigger.setAttribute("aria-busy", isSubmitting ? "true" : "false");
    trigger.textContent = isSubmitting ? "Anfrage wird gesendet ..." : trigger.dataset.defaultLabel;
  }

  function buildAutomationPayload(context) {
    const formData = new FormData();
    formData.append("subject", "Bocholt erleben - Automatische Übernahme kostenlos prüfen");
    formData.append("request_type", "publish_automation_request");
    formData.append("source_label", "bocholt-erleben-web");
    formData.append("page_url", window.location.href);
    formData.append("route", window.location.pathname || "/events-veroeffentlichen/anbindung/");
    formData.append("organization", context.organization);
    formData.append("contact", context.contact);
    formData.append("email", context.email);
    formData.append("source_type", context.sourceType);
    formData.append("source_link", context.sourceLink);
    formData.append("website", context.website);
    formData.append("cadence", context.cadence);
    formData.append("monthly_volume", context.monthlyVolume);
    formData.append("tech_contact", context.techContact);
    formData.append("notes", context.notes);
    formData.append("message", buildAutomationSummary(context));
    formData.append("submitted_at", new Date().toISOString());
    return formData;
  }
  /* === END BLOCK: PUBLISH_AUTOMATION_SUMMARY_STATUS_AND_VALIDATION_V2 === */

  async function submitAutomationFormspree(context) {
    const endpoint = getAutomationEndpoint();
    if (!endpoint) return { mode: "mailto_fallback" };

    const response = await fetch(endpoint, {
      method: "POST",
      headers: {
        Accept: "application/json"
      },
      body: buildAutomationPayload(context)
    });

    if (!response.ok) {
      throw new Error("automation_request_failed");
    }

    return { mode: "formspree" };
  }

    /* === BEGIN BLOCK: STANDARD_PUBLISH_PATH_REVIEW_BEFORE_PAYMENT_V1 | Zweck: validiert einzelne Veranstaltung ohne Browser-Popup, markiert fehlende Pflichtfelder visuell und sendet die Einreichung zuerst zur redaktionellen Prüfung; Umfang: Standard-Validierung, API-Helper, Payload-Builder, Submit-State und Bindung === */
  function getStandardValidationStatus(form) {
    let statusNode = form.querySelector("[data-standard-validation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note publish-validation-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-standard-validation-status", "");
    statusNode.setAttribute("aria-live", "assertive");

    const referenceNode = form.querySelector(".publish-final-actions");
    if (referenceNode) {
      referenceNode.insertAdjacentElement("beforebegin", statusNode);
    } else {
      form.appendChild(statusNode);
    }

    return statusNode;
  }

  function setStandardValidationStatus(form, message) {
    const statusNode = getStandardValidationStatus(form);
    statusNode.hidden = false;
    statusNode.textContent = safeText(message);
  }

  function clearStandardValidationStatus(form) {
    const statusNode = form.querySelector("[data-standard-validation-status]");
    if (!statusNode) return;

    statusNode.hidden = true;
    statusNode.textContent = "";
  }

  function getStandardField(control) {
    return control?.closest?.(".content-field") || null;
  }

  function clearStandardControlValidation(control) {
    if (!control || !control.id || !control.id.startsWith("publish-standard-")) return;

    control.removeAttribute("aria-invalid");

    const field = getStandardField(control);
    if (field) {
      delete field.dataset.fieldInvalid;
    }

    const form = control.closest("form");
    if (form && !form.querySelector('[data-field-invalid="true"]')) {
      clearStandardValidationStatus(form);
    }
  }

  function clearStandardValidationState(form) {
    form.querySelectorAll('[aria-invalid="true"]').forEach((control) => {
      control.removeAttribute("aria-invalid");
    });

    form.querySelectorAll('[data-field-invalid="true"]').forEach((field) => {
      delete field.dataset.fieldInvalid;
    });

    clearStandardValidationStatus(form);
  }

  function markStandardFieldInvalid(id) {
    const control = byId(id);
    if (!control) return null;

    control.setAttribute("aria-invalid", "true");

    const field = getStandardField(control);
    if (field) {
      field.dataset.fieldInvalid = "true";
    }

    return control;
  }

  function validateStandardContext(context, form) {
    const emailControl = byId("publish-standard-email");
    const invalidIds = [];

    if (!hasValue(context.organization)) invalidIds.push("publish-standard-organization");
    if (!hasValue(context.email) || (emailControl && !emailControl.validity.valid)) invalidIds.push("publish-standard-email");
    if (!hasValue(context.title)) invalidIds.push("publish-standard-title");
    if (!hasValue(context.date)) invalidIds.push("publish-standard-date");
    if (!hasValue(context.place)) invalidIds.push("publish-standard-place");
    if (!hasValue(context.placeStreet)) invalidIds.push("publish-standard-place-street");
    if (!hasValue(context.placeZip)) invalidIds.push("publish-standard-place-zip");
    if (!hasValue(context.placeCity)) invalidIds.push("publish-standard-place-city");
    if (context.locationConfirmed !== true) invalidIds.push("publish-standard-location-confirmed");

    if (!invalidIds.length) return true;

    const firstInvalidControl = invalidIds
      .map((id) => markStandardFieldInvalid(id))
      .find(Boolean);

    setStandardValidationStatus(form, "Bitte fülle die markierten Pflichtfelder aus.");

    if (firstInvalidControl && typeof firstInvalidControl.focus === "function") {
      firstInvalidControl.focus({ preventScroll: false });
    }

    return false;
  }

/* === BEGIN BLOCK: PUBLISH_AUTOMATION_VALIDATE_REQUIRED_FIELDS_V3 | Zweck: validiert nur echte Pflichtfelder der automatischen Übernahme; Quellen-Link ist optional, weil lokale Dateien oder Tabellen keinen öffentlichen Link haben müssen; Umfang: komplette validateAutomationContext-Funktion === */
function validateAutomationContext(context, form) {
  const emailControl = byId("publish-automation-email");
  const invalidIds = [];

  if (!hasValue(context.organization)) invalidIds.push("publish-automation-organization");
  if (!hasValue(context.contact)) invalidIds.push("publish-automation-contact");
  if (!hasValue(context.email) || (emailControl && !emailControl.validity.valid)) invalidIds.push("publish-automation-email");
  if (!hasValue(context.sourceType)) invalidIds.push("publish-automation-source-type");

  if (!invalidIds.length) return true;

  const firstInvalidControl = invalidIds
    .map((id) => markAutomationFieldInvalid(id))
    .find(Boolean);

  setAutomationValidationStatus(form, "Bitte fülle die markierten Pflichtfelder aus.");

  if (firstInvalidControl && typeof firstInvalidControl.focus === "function") {
    firstInvalidControl.focus({ preventScroll: false });
  }

  return false;
}
/* === END BLOCK: PUBLISH_AUTOMATION_VALIDATE_REQUIRED_FIELDS_V3 === */

  async function postPublishApiJson(url, payload) {
    const response = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json"
      },
      body: JSON.stringify(payload)
    });

    const rawText = await response.text();
    let data = null;

    try {
      data = rawText ? JSON.parse(rawText) : null;
    } catch (error) {
      data = null;
    }

    if (!response.ok) {
      const message =
        safeText(data?.message) ||
        safeText(data?.error_message) ||
        "request_failed";

      const requestError = new Error(message);
      requestError.responseStatus = response.status;
      requestError.responsePayload = data;
      throw requestError;
    }

    if (!data || typeof data !== "object") {
      throw new Error("invalid_api_response");
    }

    return data;
  }

  /* === BEGIN BLOCK: PUBLISH_STANDARD_SUBMISSION_PAYLOAD_REVIEW_FIELDS_V2 | Zweck: sendet prüfpflichtige Ortsangaben als eigene Felder an die Submission-API; Umfang: ersetzt buildStandardSubmissionPayload ohne Änderung des Checkout-Flows === */
  function buildStandardSubmissionPayload(context) {
    const notesText = joinLines([
      lineIf("Straße, Hausnummer / offizieller Treffpunkt", context.placeStreet),
      lineIf("PLZ", context.placeZip),
      lineIf("Stadt / Ort", context.placeCity),
      lineIf("Adresse zusammengesetzt", context.placeAddress),
      context.locationConfirmed === true
        ? "Ortsbestätigung: Einreicher bestätigt Berechtigung und öffentliche Nennung des Ortes."
        : "",
      lineIf("Weitere Hinweise", context.notes)
    ]);

    return {
      requested_model_key: "single",
      organization_name: context.organization,
      contact_name: context.contact,
      email: context.email,
      event_url: context.eventLink,
      title: context.title,
      start_date: context.date,
      time_text: context.time,
      location_name: context.place,
      location_address: context.placeAddress,
      location_public_confirmed: context.locationConfirmed === true,
      ticket_url: context.website,
      description_text: context.description,
      notes_text: notesText
    };
  }
  /* === END BLOCK: PUBLISH_STANDARD_SUBMISSION_PAYLOAD_REVIEW_FIELDS_V2 === */

  function setStandardSubmitting(trigger, isSubmitting) {
    if (!trigger) return;

    if (!trigger.dataset.defaultLabel) {
      trigger.dataset.defaultLabel = trigger.textContent || "Zur Prüfung einreichen";
    }

    trigger.disabled = isSubmitting;
    trigger.setAttribute("aria-busy", isSubmitting ? "true" : "false");
    trigger.textContent = isSubmitting ? "Einreichung wird gesendet ..." : trigger.dataset.defaultLabel;
  }

  function bindStandardPath() {
    const form = byId("publish-standard-form");
    const trigger = byId("publish-standard-pay");

    if (!form || !trigger) return;

    form.addEventListener("input", (event) => {
      clearStandardControlValidation(event.target);
    });

    form.addEventListener("change", (event) => {
      clearStandardControlValidation(event.target);
    });

    trigger.addEventListener("click", async (event) => {
      event.preventDefault();

      clearStandardValidationState(form);

      const context = buildStandardContext();
      if (!validateStandardContext(context, form)) return;

      setStandardSubmitting(trigger, true);

      try {
        const initResult = await postPublishApiJson(
          "/api/submissions/init.php",
          buildStandardSubmissionPayload(context)
        );

        const submissionId = Number(initResult?.data?.submission_id || 0);
        const paymentReferenceKey = safeText(initResult?.data?.payment_reference_key);

        if (submissionId <= 0) {
          throw new Error("missing_submission_id");
        }

        persistCheckoutContext({
          submissionId,
          paymentReferenceKey,
          ...context,
          savedAt: new Date().toISOString()
        });

        const targetUrl = new URL("/events-veroeffentlichen/erfolg/", window.location.origin);
        targetUrl.searchParams.set("flow", "submitted");
        if (hasValue(paymentReferenceKey)) {
          targetUrl.searchParams.set("submission_ref", paymentReferenceKey);
        }

        window.location.href = targetUrl.toString();
      } catch (error) {
        console.warn("Publish funnel: standard submission failed.", error);
        window.alert("Die Einreichung konnte gerade nicht gesendet werden. Bitte versuche es erneut.");
        setStandardSubmitting(trigger, false);
      }
    });
  }
  /* === END BLOCK: STANDARD_PUBLISH_PATH_REVIEW_BEFORE_PAYMENT_V1 === */

  /* === BEGIN BLOCK: PUBLISH_AUTOMATION_BIND_FREE_CHECK_V2 | Zweck: bindet die kostenlose Prüfung mit eigener Pflichtfeldmarkierung, ohne native Browser-Popups; Umfang: kompletter bindAutomationPath-Handler === */
  function bindAutomationPath() {
    const form = byId("publish-automation-form");
    const trigger = byId("publish-automation-submit");

    if (!form || !trigger) return;

    form.noValidate = true;
    trigger.formNoValidate = true;

    form.addEventListener("input", (event) => {
      clearAutomationControlValidation(event.target);
    });

    form.addEventListener("change", (event) => {
      clearAutomationControlValidation(event.target);
    });

    trigger.addEventListener("click", async (event) => {
      event.preventDefault();

      clearAutomationValidationState(form);
      clearAutomationStatus(form);

      const context = buildAutomationContext();
      if (!validateAutomationContext(context, form)) return;

      setAutomationSubmitting(trigger, true);

      try {
        const result = await submitAutomationFormspree(context);

        if (result.mode === "mailto_fallback") {
          const body = buildAutomationSummary(context);
          window.location.href = buildMailto("Bocholt erleben - Automatische Übernahme kostenlos prüfen", body);
          return;
        }

        form.reset();
        setAutomationStatus(form, "success", safeText(cfg.automationSuccessMessage) || "Anfrage erfolgreich gesendet.");
      } catch (error) {
        console.warn("Publish funnel: automation request failed.", error);
        setAutomationStatus(form, "error", safeText(cfg.automationErrorMessage) || "Die Anfrage konnte gerade nicht gesendet werden.");
      } finally {
        setAutomationSubmitting(trigger, false);
      }
    });
  }
  /* === END BLOCK: PUBLISH_AUTOMATION_BIND_FREE_CHECK_V2 === */
  /* === END BLOCK: PUBLISH_FUNNEL_RUNTIME_AND_AUTOMATION_FORMSPREE_V1 === */

  /* === BEGIN BLOCK: PUBLISH_STANDARD_PORTAL_SYNC_BOOT_V2 | Zweck: startet den Publish-Funnel nach DOM-Bereitschaft und synchronisiert dabei optional die aktive Veranstalter-Session; Umfang: Initialisierung am Dateiende === */
  async function initPublishFunnel() {
    if (!isPublishRoute()) return;

    applyPlanPresetFromUrl();
    await syncStandardFormWithPortalSession();
    bindAutomationPath();
    bindStandardPath();
  }

  function bootPublishFunnel() {
    void initPublishFunnel();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", bootPublishFunnel, { once: true });
  } else {
    bootPublishFunnel();
  }
  /* === END BLOCK: PUBLISH_STANDARD_PORTAL_SYNC_BOOT_V2 === */
})();
/* === END FILE: js/publish-funnel.js === */
