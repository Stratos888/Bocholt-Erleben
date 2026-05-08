(() => {
  "use strict";
  /* === BEGIN FILE: js/organizer-membership.js | Zweck: steuert den minimalen öffentlichen Membership-Start getrennt vom Single-Funnel; Umfang: komplette Datei === */

  const form = document.getElementById("organizer-membership-form");
  const submitButton = document.getElementById("organizer-membership-submit");
  const resultCard = document.getElementById("organizer-membership-result");
  const resultText = document.getElementById("organizer-membership-result-text");
  const planSelect = document.getElementById("organizer-membership-plan");
  const planHint = document.getElementById("organizer-membership-plan-hint");

  if (!form || !submitButton || !resultCard || !resultText || !planSelect) {
    return;
  }

  const safeText = (value) => String(value ?? "").trim();

  /* === BEGIN BLOCK: ORGANIZER_MEMBERSHIP_PLAN_HINT_AND_SUBMIT_COPY_V3 | Zweck: hält Tarifhinweise und Button-Zustände konsistent mit veröffentlichten Terminen und Weiter-zur-Zahlungsmethode-Sprache; Umfang: Tarifhinweise, Change-Handler und Submit-Button-State === */
  const planDescriptions = {
    starter: "Starter passt für bis zu 3 veröffentlichte Termine pro Monat.",
    active: "Aktiv passt für bis zu 8 veröffentlichte Termine pro Monat.",
    unlimited: "Dauerhaft passt für viele veröffentlichte Termine im üblichen Rahmen."
  };

  function updatePlanHint() {
    if (!planHint) return;
    planHint.textContent = planDescriptions[safeText(planSelect.value)] || planDescriptions.starter;
  }

  planSelect.addEventListener("change", updatePlanHint);
  updatePlanHint();

  function setSubmitting(isSubmitting) {
    if (!submitButton.dataset.defaultLabel) {
      submitButton.dataset.defaultLabel = submitButton.textContent || "Weiter zur Zahlungsmethode";
    }

    submitButton.disabled = isSubmitting;
    submitButton.textContent = isSubmitting
      ? "Weiterleitung wird vorbereitet ..."
      : submitButton.dataset.defaultLabel;
  }
  /* === END BLOCK: ORGANIZER_MEMBERSHIP_PLAN_HINT_AND_SUBMIT_COPY_V3 === */

  function showResult(message) {
    resultText.textContent = message;
    resultCard.hidden = false;
  }

  async function postJson(url, payload) {
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
    } catch (_error) {
      data = null;
    }

    if (!response.ok) {
      const message =
        safeText(data?.message) ||
        safeText(data?.error_message) ||
        "request_failed";

      throw new Error(message);
    }

    return data;
  }

  /* === BEGIN BLOCK: ORGANIZER_MEMBERSHIP_SUBMIT_RETRY_SAFE_V3 | Zweck: validiert Mitgliedschafts-Pflichtfelder ohne Browser-Popup, markiert fehlende Felder und startet danach den Stripe-Zahlungsschritt; Umfang: kompletter Submit-Handler inklusive Validierungshelfern === */
  function getValidationStatusNode() {
    let statusNode = form.querySelector("[data-organizer-validation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note organizer-validation-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-organizer-validation-status", "");
    statusNode.setAttribute("aria-live", "assertive");

    const referenceNode = form.querySelector(".content-actions");
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
    const statusNode = form.querySelector("[data-organizer-validation-status]");
    if (!statusNode) return;

    statusNode.hidden = true;
    statusNode.textContent = "";
  }

  function getField(control) {
    return control?.closest?.(".content-field") || null;
  }

  function clearControlValidation(control) {
    if (!control || !control.id || !control.id.startsWith("organizer-membership-")) return;

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

  function validateMembershipForm() {
    const invalidIds = [];

    const organization = document.getElementById("organizer-membership-organization");
    const email = document.getElementById("organizer-membership-email");
    const plan = document.getElementById("organizer-membership-plan");

    if (!safeText(organization?.value)) invalidIds.push("organizer-membership-organization");
    if (!safeText(email?.value) || (email && !email.validity.valid)) invalidIds.push("organizer-membership-email");
    if (!safeText(plan?.value)) invalidIds.push("organizer-membership-plan");

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

  form.addEventListener("input", (event) => {
    clearControlValidation(event.target);
  });

  form.addEventListener("change", (event) => {
    clearControlValidation(event.target);
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    clearValidationState();

    if (!validateMembershipForm()) {
      return;
    }

    setSubmitting(true);
    resultCard.hidden = true;

    try {
      const result = await postJson("/api/subscriptions/start-membership.php", {
        requested_model_key: safeText(document.getElementById("organizer-membership-plan")?.value),
        organization_name: safeText(document.getElementById("organizer-membership-organization")?.value),
        contact_name: safeText(document.getElementById("organizer-membership-contact")?.value),
        email: safeText(document.getElementById("organizer-membership-email")?.value),
      });

      const data = result?.data || {};

      if (data.checkout_required === false && safeText(data.redirect_url)) {
        window.location.href = data.redirect_url;
        return;
      }

      const checkoutUrl = safeText(data.checkout_url);
      if (!checkoutUrl) {
        throw new Error("missing_checkout_url");
      }

      window.location.href = checkoutUrl;
    } catch (error) {
      console.warn("Organizer membership: start failed.", error);
      showResult("Der nächste Schritt konnte gerade nicht vorbereitet werden. Bitte versuche es erneut.");
    } finally {
      setSubmitting(false);
    }
  });
  /* === END BLOCK: ORGANIZER_MEMBERSHIP_SUBMIT_RETRY_SAFE_V3 === */

  /* === END FILE: js/organizer-membership.js === */
})();
