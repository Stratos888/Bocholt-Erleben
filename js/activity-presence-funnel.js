/* === BEGIN FILE: js/activity-presence-funnel.js | Zweck: steuert den Aktivitaetspraesenz-Funnel; Umfang: komplette Datei === */
(function () {
  "use strict";

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function valueOf(selector, root) {
    const node = $(selector, root);
    return node ? String(node.value || "").trim() : "";
  }

  function checkedValue(name, root) {
    const node = (root || document).querySelector(`input[name="${name}"]:checked`);
    return node ? String(node.value || "").trim() : "";
  }

  function setStatus(node, text, state) {
    if (!node) return;
    node.textContent = text || "";
    node.dataset.state = state || "";
    node.hidden = !text;
  }

  function setBusy(button, busy, label) {
    if (!button) return;
    if (busy) {
      button.dataset.originalLabel = button.textContent || "";
      button.textContent = label || "Bitte warten …";
      button.disabled = true;
      return;
    }

    button.disabled = false;
    if (button.dataset.originalLabel) {
      button.textContent = button.dataset.originalLabel;
      delete button.dataset.originalLabel;
    }
  }

  function clearInvalid(form) {
    form.querySelectorAll("[aria-invalid='true']").forEach((node) => {
      node.removeAttribute("aria-invalid");
    });

    form.querySelectorAll("[data-field-invalid='true']").forEach((field) => {
      delete field.dataset.fieldInvalid;
    });
  }

  function markInvalid(node) {
    if (!node) return;
    node.setAttribute("aria-invalid", "true");

    const field = node.closest(".content-field");
    if (field) {
      field.dataset.fieldInvalid = "true";
    }
  }

  async function postJson(url, payload) {
    const response = await fetch(url, {
      method: "POST",
      credentials: "same-origin",
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

    if (!response.ok || !data || data.status !== "ok") {
      const message = data?.message || data?.error_message || "request_failed";
      throw new Error(message);
    }

    return data;
  }

  function buildAddress(form) {
    return [
      valueOf("#activity-presence-address", form),
      [
        valueOf("#activity-presence-zip", form),
        valueOf("#activity-presence-city", form)
      ].filter(Boolean).join(" ")
    ].filter(Boolean).join(", ");
  }

  function buildNotes(form) {
    const lines = [
      valueOf("#activity-presence-duration", form) ? `Dauerhaft/regelmäßig buchbar: ${valueOf("#activity-presence-duration", form)}` : "",
      valueOf("#activity-presence-notes", form) ? `Weitere Hinweise: ${valueOf("#activity-presence-notes", form)}` : ""
    ].filter(Boolean);

    return lines.join("\n");
  }

  function validateFunnelForm(form) {
    clearInvalid(form);

    const requiredSelectors = [
      "#activity-presence-organization",
      "#activity-presence-email",
      "#activity-presence-title",
      "#activity-presence-location",
      "#activity-presence-address",
      "#activity-presence-zip",
      "#activity-presence-city",
      "#activity-presence-description",
      "#activity-presence-duration"
    ];

    const invalidNodes = [];
    requiredSelectors.forEach((selector) => {
      const node = $(selector, form);
      if (!node || !String(node.value || "").trim()) {
        invalidNodes.push(node);
      }
    });

    const emailNode = $("#activity-presence-email", form);
    if (emailNode && emailNode.value && !emailNode.checkValidity()) {
      invalidNodes.push(emailNode);
    }

    const confirmNode = $("#activity-presence-confirmed", form);
    if (!confirmNode || !confirmNode.checked) {
      invalidNodes.push(confirmNode);
    }

    invalidNodes.filter(Boolean).forEach(markInvalid);

    if (invalidNodes.length > 0) {
      const first = invalidNodes.find(Boolean);
      if (first && typeof first.focus === "function") first.focus();
      return false;
    }

    return true;
  }

  function buildFunnelPayload(form) {
    return {
      submission_kind: "activity",
      requested_model_key: checkedValue("activityPlan", form) || "activity_basic",
      organization_name: valueOf("#activity-presence-organization", form),
      contact_name: valueOf("#activity-presence-contact", form),
      email: valueOf("#activity-presence-email", form),
      title: valueOf("#activity-presence-title", form),
      time_text: valueOf("#activity-presence-duration", form),
      location_name: valueOf("#activity-presence-location", form),
      location_address: buildAddress(form),
      location_public_confirmed: Boolean($("#activity-presence-confirmed", form)?.checked),
      ticket_url: valueOf("#activity-presence-url", form),
      description_text: valueOf("#activity-presence-description", form),
      notes_text: buildNotes(form)
    };
  }
  /* === BEGIN BLOCK: ACTIVITY_PRESENCE_PLAN_QUERY_PREFILL_V1 | Zweck: liest ?plan=activity_basic/activity_plus aus der URL und waehlt den passenden Tarif im bestehenden Formular vor; Umfang: reine Frontend-Vorauswahl ohne Payload- oder Backend-Aenderung === */
  function applyPlanFromQuery(form) {
    const params = new URLSearchParams(window.location.search);
    const plan = params.get("plan");

    if (!["activity_basic", "activity_plus"].includes(plan)) {
      return;
    }

    const node = form.querySelector(`input[name="activityPlan"][value="${plan}"]`);
    if (node) {
      node.checked = true;
    }
  }
  /* === END BLOCK: ACTIVITY_PRESENCE_PLAN_QUERY_PREFILL_V1 === */

  function initFunnelPage() {
    const form = $("#activity-presence-form");
    if (!form) return;

    const statusNode = $("#activity-presence-status");
    const submitButton = $("#activity-presence-submit");

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_PLAN_QUERY_PREFILL_CALL_V1 | Zweck: setzt den auf der Entscheidungsseite gewaehlten Tarif vor dem Absenden im Formular; Umfang: einmaliger Init-Aufruf auf Formularseiten === */
    applyPlanFromQuery(form);
    /* === END BLOCK: ACTIVITY_PRESENCE_PLAN_QUERY_PREFILL_CALL_V1 === */

    submitButton?.addEventListener("click", async () => {
      if (!validateFunnelForm(form)) {
        setStatus(statusNode, "Bitte fülle die markierten Pflichtfelder aus.", "error");
        return;
      }

      setBusy(submitButton, true, "Einreichung wird gespeichert …");
      setStatus(statusNode, "Einreichung wird gespeichert …", "pending");

      try {
        const result = await postJson("/api/submissions/init.php", buildFunnelPayload(form));
        const submissionRef = result?.data?.payment_reference_key || "";
        const targetUrl = new URL("/angebote/sichtbar-werden/erfolg/", window.location.origin);
        targetUrl.searchParams.set("flow", "submitted");
        if (submissionRef) {
          targetUrl.searchParams.set("submission_ref", submissionRef);
        }
        window.location.href = targetUrl.toString();
      } catch (error) {
        setStatus(statusNode, error && error.message ? error.message : "Einreichung konnte nicht gespeichert werden.", "error");
        setBusy(submitButton, false);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFunnelPage();
  });
})();
/* === END FILE: js/activity-presence-funnel.js === */
