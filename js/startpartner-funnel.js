/* === BEGIN FILE: js/startpartner-funnel.js | Zweck: steuert die kompakte Startpartner-Anfrage mit bestehender Funnel-Validierung: keine nativen Browser-Bubbles, klare Feldmarkierung, Formspree-Submit; Umfang: komplette Datei === */
(() => {
  "use strict";

  const form = document.getElementById("startpartner-request-form");
  const submitButton = document.getElementById("startpartner-request-submit");
  const resultCard = document.getElementById("startpartner-request-result");
  const resultText = document.getElementById("startpartner-request-result-text");

  if (!form || !submitButton || !resultCard || !resultText) {
    return;
  }

  form.noValidate = true;
  submitButton.formNoValidate = true;

  const safeText = (value) => String(value ?? "").trim();

  function setSubmitting(isSubmitting) {
    if (!submitButton.dataset.defaultLabel) {
      submitButton.dataset.defaultLabel = submitButton.textContent || "Startpartner-Anfrage senden";
    }

    submitButton.disabled = isSubmitting;
    submitButton.textContent = isSubmitting
      ? "Anfrage wird gesendet ..."
      : submitButton.dataset.defaultLabel;
  }

  function showResult(message) {
    resultText.textContent = safeText(message);
    resultCard.hidden = false;
  }

  function hideResult() {
    resultText.textContent = "";
    resultCard.hidden = true;
  }

  function getValidationStatusNode() {
    let statusNode = form.querySelector("[data-startpartner-validation-status]");
    if (statusNode) return statusNode;

    statusNode = document.createElement("p");
    statusNode.className = "content-form-note organizer-validation-note";
    statusNode.hidden = true;
    statusNode.setAttribute("data-startpartner-validation-status", "");
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

    const organization = document.getElementById("startpartner-organization");
    const email = document.getElementById("startpartner-email");
    const note = document.getElementById("startpartner-note");
    const privacy = document.getElementById("startpartner-privacy-confirmed");

    if (!safeText(organization?.value)) invalidIds.push("startpartner-organization");
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

  async function submitStartpartnerRequest() {
    const formData = new FormData(form);
    formData.set("page_url", window.location.href);

    const response = await fetch(form.action, {
      method: "POST",
      headers: {
        Accept: "application/json"
      },
      body: formData
    });

    if (!response.ok) {
      throw new Error(`formspree_${response.status}`);
    }
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
    hideResult();

    if (!validateStartpartnerForm()) {
      return;
    }

    setSubmitting(true);

    try {
      await submitStartpartnerRequest();
      form.reset();
      showResult("Deine Startpartner-Anfrage ist angekommen. Bocholt erleben prüft sie und meldet sich danach zurück.");
    } catch (error) {
      console.warn("Startpartner request failed.", error);
      showResult("Die Anfrage konnte gerade nicht gesendet werden. Bitte später erneut versuchen oder über Feedback Kontakt aufnehmen.");
    } finally {
      setSubmitting(false);
    }
  });
})();
/* === END FILE: js/startpartner-funnel.js === */