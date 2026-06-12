/* === BEGIN FILE: js/activity-presence-funnel.js | Zweck: steuert den Aktivitaetspraesenz-Funnel; Umfang: komplette Datei === */
(function () {
  "use strict";

  /* === BEGIN BLOCK: ACTIVITY_PRESENCE_OPENING_CLIENT_MODEL_V1 | Zweck: definiert Client-Konstanten fuer strukturierte Aktivitaets-Zugaenglichkeit; Umfang: additive Konstanten ohne bestehende Formularlogik zu entfernen === */
  const OPENING_DAYS = Object.freeze([
    "monday",
    "tuesday",
    "wednesday",
    "thursday",
    "friday",
    "saturday",
    "sunday"
  ]);

  const OPENING_TIME_RE = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
  /* === END BLOCK: ACTIVITY_PRESENCE_OPENING_CLIENT_MODEL_V1 === */

  function $(selector, root) {
    return (root || document).querySelector(selector);
  }

  function valueOf(selector, root) {
    const node = $(selector, root);
    return node ? String(node.value || "").trim() : "";
  }

  function selectedOption(selector, root) {
    const node = $(selector, root);
    return node?.selectedOptions?.[0] || null;
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

  /* === BEGIN BLOCK: ACTIVITY_PRESENCE_OPENING_PAYLOAD_V1 | Zweck: baut und validiert strukturierte Oeffnungszeitdaten aus dem bestehenden Aktivitaetspraesenz-Formular; Umfang: neue Helfer fuer activity_opening-Payload === */
  function getAccessType(form) {
    const option = selectedOption("#activity-presence-duration", form);
    return option?.dataset?.accessType || "";
  }

  function syncOpeningFields(form) {
    const isOpeningHours = getAccessType(form) === "opening_hours";
    const section = $("#activity-presence-opening-hours", form);
    if (section) section.hidden = !isOpeningHours;

    (form || document).querySelectorAll("[data-opening-day]").forEach((row) => {
      const closed = row.querySelector("[data-opening-closed]");
      const start = row.querySelector("[data-opening-start]");
      const end = row.querySelector("[data-opening-end]");
      const timeRow = row.querySelector(".activity-presence-opening-time-row");
      const isClosed = Boolean(closed?.checked);
      const disabled = !isOpeningHours || isClosed;

      row.dataset.openingClosed = isClosed ? "true" : "false";
      if (timeRow) timeRow.hidden = disabled;

      if (start) start.disabled = disabled;
      if (end) end.disabled = disabled;
    });
  }

  function buildWeeklyOpening(form) {
    const weekly = {};

    OPENING_DAYS.forEach((day) => {
      const row = (form || document).querySelector(`[data-opening-day="${day}"]`);
      const closed = row?.querySelector("[data-opening-closed]");
      const start = row?.querySelector("[data-opening-start]");
      const end = row?.querySelector("[data-opening-end]");

      if (!row || closed?.checked) {
        weekly[day] = [];
        return;
      }

      weekly[day] = [[
        String(start?.value || "").trim(),
        String(end?.value || "").trim()
      ]];
    });

    return weekly;
  }

  function buildActivityOpening(form) {
    const accessType = getAccessType(form) || "check_required";
    const payload = {
      access_type: accessType,
      holiday_policy: valueOf("#activity-presence-holiday-policy", form) || "check"
    };

    if (accessType === "opening_hours") {
      payload.weekly = buildWeeklyOpening(form);
    }

    const notes = valueOf("#activity-presence-notes", form);
    if (notes) payload.notes = notes;

    const sourceUrl = valueOf("#activity-presence-url", form);
    if (sourceUrl) payload.source_url = sourceUrl;

    return payload;
  }

  function isValidOpeningInterval(start, end) {
    if (!OPENING_TIME_RE.test(start) || !OPENING_TIME_RE.test(end)) return false;
    return start < end;
  }

  function validateActivityOpening(form, invalidNodes) {
    if (getAccessType(form) !== "opening_hours") return;

    let openDayCount = 0;

    (form || document).querySelectorAll("[data-opening-day]").forEach((row) => {
      const closed = row.querySelector("[data-opening-closed]");
      const start = row.querySelector("[data-opening-start]");
      const end = row.querySelector("[data-opening-end]");

      if (closed?.checked) return;
      openDayCount += 1;

      if (!isValidOpeningInterval(String(start?.value || "").trim(), String(end?.value || "").trim())) {
        invalidNodes.push(start, end);
      }
    });

    if (openDayCount <= 0) {
      invalidNodes.push($("#activity-presence-duration", form));
    }
  }
  /* === END BLOCK: ACTIVITY_PRESENCE_OPENING_PAYLOAD_V1 === */

  /* === BEGIN BLOCK: ACTIVITY_PRESENCE_IMAGE_MATERIAL_PAYLOAD_V1 | Zweck: steuert die optionale Bildmaterial-Abfrage ohne Datei-Upload; Umfang: additive Helfer fuer Anzeige, Validierung und Payload === */
  const IMAGE_METHODS_REQUIRING_URL = Object.freeze(["download_link", "website_gallery"]);
  const DEFAULT_IMAGE_NOTES_PLACEHOLDER = "Optional: Welche Bilder sollen bevorzugt verwendet werden? Gibt es Motive, die wir vermeiden sollen?";
  const IMAGE_NOTES_PLACEHOLDERS = Object.freeze({
    download_link: DEFAULT_IMAGE_NOTES_PLACEHOLDER,
    website_gallery: "Optional: Welche Bilder aus der Galerie passen besonders gut?",
    email_later: "Optional: Welche Bilder sendest du später? Gibt es etwas, das wir beachten sollen?",
    other: "Bitte kurz beschreiben, wie wir an das Bildmaterial kommen oder was wir beachten sollen."
  });

  function getImageNotesPlaceholder(method) {
    return IMAGE_NOTES_PLACEHOLDERS[method] || DEFAULT_IMAGE_NOTES_PLACEHOLDER;
  }

  function getImageAvailability(form) {
    return valueOf("#activity-presence-image-availability", form);
  }

  function getImageMethod(form) {
    return valueOf("#activity-presence-image-method", form);
  }

  function imageMethodRequiresUrl(method) {
    return IMAGE_METHODS_REQUIRING_URL.includes(method);
  }

  function syncImageFields(form) {
    const availability = getImageAvailability(form);
    const hasImageMaterial = availability === "yes";
    const details = $("#activity-presence-image-details", form);
    const methodNode = $("#activity-presence-image-method", form);
    const urlField = form?.querySelector("[data-image-url-field]");
    const urlNode = $("#activity-presence-image-url", form);
    const notesNode = $("#activity-presence-image-notes", form);
    const rightsNode = $("#activity-presence-image-rights-confirmed", form);
    const method = getImageMethod(form);
    const needsUrl = hasImageMaterial && imageMethodRequiresUrl(method);

    if (details) details.hidden = !hasImageMaterial;
    if (urlField) urlField.hidden = !needsUrl;

    if (methodNode) {
      methodNode.disabled = !hasImageMaterial;
      if (!hasImageMaterial) methodNode.value = "";
    }

    if (urlNode) {
      urlNode.disabled = !needsUrl;
      if (!needsUrl) urlNode.value = "";
    }

    if (notesNode) {
      notesNode.disabled = !hasImageMaterial;
      notesNode.placeholder = getImageNotesPlaceholder(hasImageMaterial ? method : "");
      if (!hasImageMaterial) notesNode.value = "";
    }

    if (rightsNode) {
      rightsNode.disabled = !hasImageMaterial;
      if (!hasImageMaterial) rightsNode.checked = false;
    }
  }

  function validateActivityImage(form, invalidNodes) {
    const availabilityNode = $("#activity-presence-image-availability", form);
    const availability = getImageAvailability(form);

    if (!["yes", "no", "unsure"].includes(availability)) {
      invalidNodes.push(availabilityNode);
      return;
    }

    if (availability !== "yes") return;

    const methodNode = $("#activity-presence-image-method", form);
    const method = getImageMethod(form);
    const allowedMethods = ["download_link", "website_gallery", "email_later", "other"];
    if (!allowedMethods.includes(method)) {
      invalidNodes.push(methodNode);
    }

    if (imageMethodRequiresUrl(method)) {
      const urlNode = $("#activity-presence-image-url", form);
      if (!urlNode || !String(urlNode.value || "").trim() || !urlNode.checkValidity()) {
        invalidNodes.push(urlNode);
      }
    }

    if (method === "other" && !valueOf("#activity-presence-image-notes", form)) {
      invalidNodes.push($("#activity-presence-image-notes", form));
    }

    const rightsNode = $("#activity-presence-image-rights-confirmed", form);
    if (!rightsNode || !rightsNode.checked) {
      invalidNodes.push(rightsNode);
    }
  }

  function buildActivityImage(form) {
    const availability = getImageAvailability(form) || "unsure";
    const payload = {
      availability,
      rights_confirmed: Boolean($("#activity-presence-image-rights-confirmed", form)?.checked)
    };

    if (availability === "yes") {
      const method = getImageMethod(form);
      if (method) payload.method = method;

      const imageUrl = valueOf("#activity-presence-image-url", form);
      if (imageUrl) payload.url = imageUrl;

      const notes = valueOf("#activity-presence-image-notes", form);
      if (notes) payload.notes = notes;
    }

    return payload;
  }
  /* === END BLOCK: ACTIVITY_PRESENCE_IMAGE_MATERIAL_PAYLOAD_V1 === */

  function buildNotes(form) {
    const lines = [
      valueOf("#activity-presence-duration", form) ? `Zugänglichkeit: ${valueOf("#activity-presence-duration", form)}` : "",
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
      "#activity-presence-duration",
      "#activity-presence-image-availability"
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

    validateActivityOpening(form, invalidNodes);
    validateActivityImage(form, invalidNodes);

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
      notes_text: buildNotes(form),
      activity_opening: buildActivityOpening(form),
      activity_image: buildActivityImage(form)
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

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_OPENING_BINDINGS_V1 | Zweck: synchronisiert die sichtbaren Oeffnungszeitfelder mit der gewaehlen Zugaenglichkeitsart; Umfang: additive Event-Bindings im bestehenden Formular-Setup === */
    syncOpeningFields(form);
    $("#activity-presence-duration", form)?.addEventListener("change", () => syncOpeningFields(form));
    form.querySelectorAll("[data-opening-closed]").forEach((node) => {
      node.addEventListener("change", () => syncOpeningFields(form));
    });
    /* === END BLOCK: ACTIVITY_PRESENCE_OPENING_BINDINGS_V1 === */

    /* === BEGIN BLOCK: ACTIVITY_PRESENCE_IMAGE_MATERIAL_BINDINGS_V1 | Zweck: bindet die optionale Bildmaterial-Abfrage an den bestehenden Formular-Init; Umfang: additive Change-Listener ohne Upload-Handling === */
    syncImageFields(form);
    $("#activity-presence-image-availability", form)?.addEventListener("change", () => syncImageFields(form));
    $("#activity-presence-image-method", form)?.addEventListener("change", () => syncImageFields(form));
    /* === END BLOCK: ACTIVITY_PRESENCE_IMAGE_MATERIAL_BINDINGS_V1 === */

    submitButton?.addEventListener("click", async () => {
      syncOpeningFields(form);
      syncImageFields(form);

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
        /* === BEGIN BLOCK: ACTIVITY_PRESENCE_SUBMIT_ERROR_MESSAGE_V1 | Zweck: zeigt bei technischen Netzwerk-/Fetch-Fehlern eine nutzerverstaendliche Meldung statt Browser-Rohtext; Umfang: ersetzt nur die Fehlerausgabe im Submit-Catch === */
        const rawMessage = error && error.message ? String(error.message) : "";
        const technicalNetworkError = [
          "failed to fetch",
          "networkerror",
          "load failed",
          "net::"
        ].some((token) => rawMessage.toLowerCase().includes(token));
        const displayMessage = technicalNetworkError || rawMessage === ""
          ? "Einreichung konnte nicht gespeichert werden. Bitte prüfe deine Verbindung und versuche es erneut."
          : rawMessage;
        setStatus(statusNode, displayMessage, "error");
        /* === END BLOCK: ACTIVITY_PRESENCE_SUBMIT_ERROR_MESSAGE_V1 === */
        setBusy(submitButton, false);
      }
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    initFunnelPage();
  });
})();
/* === END FILE: js/activity-presence-funnel.js === */
