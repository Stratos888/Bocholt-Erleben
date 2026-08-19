/* === BEGIN FILE: js/startpartner-funnel.js | Zweck: steuert die öffentliche Startpartner-Anfrage über den kanonischen First-Party-Intake mit Scope-Vorauswahl, Idempotenz, Validierung sowie eindeutigem Erfolg/Fehler; Umfang: komplette Datei === */
(() => {
  "use strict";

  const form = document.getElementById("startpartner-request-form");
  const submitButton = document.getElementById("startpartner-request-submit");
  const resultNode = document.getElementById("startpartner-request-result");
  const scopeSelect = document.getElementById("startpartner-scope");

  if (!form || !submitButton || !resultNode || !scopeSelect) {
    return;
  }

  form.noValidate = true;
  submitButton.formNoValidate = true;

  const safeText = (value) => String(value ?? "").trim();
  const allowedScopes = new Set(["events", "activities", "both", "unsure"]);
  const idempotencyStorageKey = "be_startpartner_public_intake_idempotency_v1";

  function requestedScopeFromUrl() {
    const rawScope = safeText(new URLSearchParams(window.location.search).get("scope")).toLowerCase();
    const aliases = {
      event: "events",
      events: "events",
      activity: "activities",
      activities: "activities",
      both: "both",
      unsure: "unsure",
    };
    const normalized = aliases[rawScope] || "";
    return allowedScopes.has(normalized) ? normalized : "";
  }

  function applyScopeFromUrl() {
    const requestedScope = requestedScopeFromUrl();
    if (requestedScope) {
      scopeSelect.value = requestedScope;
    }
  }

  function randomIdempotencyKey() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return window.crypto.randomUUID();
    }
    const randomPart = Math.random().toString(36).slice(2);
    return `startpartner-${Date.now().toString(36)}-${randomPart}-${Math.random().toString(36).slice(2)}`;
  }

  function getIdempotencyKey() {
    try {
      const existing = safeText(window.sessionStorage.getItem(idempotencyStorageKey));
      if (existing.length >= 16) return existing;
      const created = randomIdempotencyKey();
      window.sessionStorage.setItem(idempotencyStorageKey, created);
      return created;
    } catch (_) {
      return randomIdempotencyKey();
    }
  }

  function clearIdempotencyKey() {
    try {
      window.sessionStorage.removeItem(idempotencyStorageKey);
    } catch (_) {}
  }

  function setSubmitting(isSubmitting) {
    if (!submitButton.dataset.defaultLabel) {
      submitButton.dataset.defaultLabel = submitButton.textContent || "Startpartner anfragen";
    }

    submitButton.disabled = isSubmitting;
    submitButton.textContent = isSubmitting
      ? "Anfrage wird gesendet ..."
      : submitButton.dataset.defaultLabel;
  }

  function showResult(message, kind = "error") {
    resultNode.textContent = safeText(message);
    resultNode.hidden = false;
    resultNode.dataset.state = kind;
    resultNode.setAttribute("aria-live", kind === "error" ? "assertive" : "polite");
    if (typeof resultNode.scrollIntoView === "function") {
      resultNode.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function hideResult() {
    resultNode.textContent = "";
    resultNode.hidden = true;
    delete resultNode.dataset.state;
  }

  function getValidationStatusNode() {
    let statusNode = form.querySelector("[data-startpartner-validation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note organizer-validation-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-startpartner-validation-status", "");
    statusNode.setAttribute("aria-live", "assertive");

    const referenceNode = form.querySelector(".publish-final-actions");
    if (referenceNode) {
      referenceNode.insertAdjacentElement("beforebegin", statusNode);
    } else {
      form.appendChild(statusNode);
    }

    return statusNode;
  }

  function setValidationMessage(message) {
    const statusNode = getValidationStatusNode();
    statusNode.hidden = false;
    statusNode.textContent = safeText(message);
  }

  function clearValidationMessage() {
    const statusNode = form.querySelector("[data-startpartner-validation-status]");
    if (!statusNode) return;

    statusNode.hidden = true;
    statusNode.textContent = "";
  }

  function getField(control) {
    return control?.closest?.(".content-field") || null;
  }

  function clearControlValidation(control) {
    if (!control || !control.id || !control.id.startsWith("startpartner-")) return;

    control.removeAttribute("aria-invalid");

    const field = getField(control);
    if (field) {
      delete field.dataset.fieldInvalid;
    }

    if (!form.querySelector('[data-field-invalid="true"]')) {
      clearValidationMessage();
    }
  }

  function clearValidationState() {
    form.querySelectorAll('[aria-invalid="true"]').forEach((control) => {
      control.removeAttribute("aria-invalid");
    });

    form.querySelectorAll('[data-field-invalid="true"]').forEach((field) => {
      delete field.dataset.fieldInvalid;
    });

    clearValidationMessage();
  }

  function markFieldInvalid(id) {
    const control = document.getElementById(id);
    if (!control) return null;

    control.setAttribute("aria-invalid", "true");

    const field = getField(control);
    if (field) {
      field.dataset.fieldInvalid = "true";
    }

    return control;
  }

  function validateStartpartnerForm() {
    const invalidIds = [];

    const scope = document.getElementById("startpartner-scope");
    const organization = document.getElementById("startpartner-organization");
    const contact = document.getElementById("startpartner-contact");
    const email = document.getElementById("startpartner-email");
    const note = document.getElementById("startpartner-note");
    const privacy = document.getElementById("startpartner-privacy-confirmed");

    if (!allowedScopes.has(safeText(scope?.value))) invalidIds.push("startpartner-scope");
    if (safeText(organization?.value).length < 2) invalidIds.push("startpartner-organization");
    if (safeText(contact?.value).length < 2) invalidIds.push("startpartner-contact");
    if (!safeText(email?.value) || (email && !email.validity.valid)) invalidIds.push("startpartner-email");
    if (safeText(note?.value).length < 8) invalidIds.push("startpartner-note");
    if (!privacy?.checked) invalidIds.push("startpartner-privacy-confirmed");

    if (!invalidIds.length) return true;

    const firstInvalidControl = invalidIds
      .map((id) => markFieldInvalid(id))
      .find(Boolean);

    setValidationMessage("Bitte fülle die markierten Pflichtfelder aus.");

    if (firstInvalidControl && typeof firstInvalidControl.focus === "function") {
      firstInvalidControl.focus({ preventScroll: false });
    }

    return false;
  }

  function buildPayload() {
    return {
      source: "self_service",
      desired_content_scope: safeText(scopeSelect.value),
      organization: safeText(document.getElementById("startpartner-organization")?.value),
      contact_name: safeText(document.getElementById("startpartner-contact")?.value),
      email: safeText(document.getElementById("startpartner-email")?.value),
      website: safeText(document.getElementById("startpartner-website")?.value),
      description: safeText(document.getElementById("startpartner-note")?.value),
      privacy_confirmed: document.getElementById("startpartner-privacy-confirmed")?.checked === true,
      website_confirm: safeText(document.getElementById("startpartner-website-confirm")?.value),
      page_url: window.location.href,
    };
  }

  async function submitStartpartnerRequest() {
    const response = await fetch(form.action, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "Idempotency-Key": getIdempotencyKey(),
      },
      body: JSON.stringify(buildPayload()),
    });

    const rawText = await response.text();
    let data = null;
    try {
      data = rawText ? JSON.parse(rawText) : null;
    } catch (_) {
      data = null;
    }

    if (!response.ok || data?.status !== "ok") {
      throw new Error(`startpartner_intake_${response.status}`);
    }

    return data?.data || {};
  }

  form.addEventListener("input", (event) => {
    clearControlValidation(event.target);
    hideResult();
  });

  form.addEventListener("change", (event) => {
    clearControlValidation(event.target);
    hideResult();
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    clearValidationState();
    hideResult();

    if (!validateStartpartnerForm()) {
      return;
    }

    setSubmitting(true);

    try {
      const result = await submitStartpartnerRequest();
      clearIdempotencyKey();
      const target = new URL("/startpartner/erfolg/", window.location.origin);
      target.searchParams.set("mail", result.confirmation_mail_sent === true ? "sent" : "pending");
      window.location.assign(target.toString());
    } catch (error) {
      console.warn("Startpartner intake failed.", error);
      showResult("Die Anfrage konnte gerade nicht sicher gespeichert werden. Deine Angaben bleiben erhalten. Bitte versuche es erneut.");
    } finally {
      setSubmitting(false);
    }
  });

  applyScopeFromUrl();

  const initialError = safeText(new URLSearchParams(window.location.search).get("error"));
  if (initialError) {
    showResult("Die Anfrage konnte gerade nicht sicher gespeichert werden. Bitte prüfe deine Angaben und versuche es erneut.");
  }
})();
/* === END FILE: js/startpartner-funnel.js === */
