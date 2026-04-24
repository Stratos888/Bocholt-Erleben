/* === BEGIN FILE: js/publish-funnel.js | Zweck: zentraler JS-Owner für den Veranstalter-Funnel mit Stripe-Weiterleitung im Standardweg und Mailto-Vorbereitung im Profi-Pfad; Umfang: komplette neue Datei === */
(() => {
  "use strict";

  const runtimeConfig =
    (typeof CONFIG !== "undefined" && CONFIG && CONFIG.publishFunnel)
      ? CONFIG.publishFunnel
      : ((window.CONFIG && window.CONFIG.publishFunnel) ? window.CONFIG.publishFunnel : (window.BE_PUBLISH_FUNNEL_CONFIG || {}));

  /* === BEGIN BLOCK: PUBLISH_FUNNEL_RUNTIME_AND_AUTOMATION_FORMSPREE_V1 | Zweck: erweitert den zentralen Veranstalter-Funnel um einen Formspree-basierten Profi-Pfad mit Inline-Status, sauberem Submit-State und Mailto-Fallback, ohne den Standardweg zu verändern; Umfang: Runtime-Config, Helper und Bindings bis vor init() in js/publish-funnel.js === */
  const cfg = {
    automationEmail: "mathias@bocholt-erleben.de",
    automationFormspreeEndpoint: "",
    automationSuccessMessage: "Anfrage erfolgreich gesendet. Wir prüfen deine Quelle und melden uns, falls noch Angaben fehlen.",
    automationErrorMessage: "Die Anfrage konnte gerade nicht gesendet werden. Bitte versuche es erneut oder nutze alternativ die direkte Kontaktmöglichkeit.",
    storageKey: "be_publish_checkout_context_v1",
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

  function getAutomationEndpoint() {
    return safeText(cfg.automationFormspreeEndpoint);
  }

  function persistCheckoutContext(context) {
    try {
      window.sessionStorage.setItem(cfg.storageKey, JSON.stringify(context));
    } catch (error) {
      console.warn("Publish funnel: checkout context could not be stored.", error);
    }
  }

  function buildStandardContext() {
    return {
      plan: safeText(byId("publish-standard-plan")?.value),
      organization: safeText(byId("publish-standard-organization")?.value),
      contact: safeText(byId("publish-standard-contact")?.value),
      email: safeText(byId("publish-standard-email")?.value),
      eventLink: safeText(byId("publish-standard-event-link")?.value),
      title: safeText(byId("publish-standard-title")?.value),
      date: safeText(byId("publish-standard-date")?.value),
      time: safeText(byId("publish-standard-time")?.value),
      place: safeText(byId("publish-standard-place")?.value),
      website: safeText(byId("publish-standard-website")?.value),
      description: safeText(byId("publish-standard-description")?.value),
      notes: safeText(byId("publish-standard-notes")?.value)
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

  function buildAutomationSummary(context) {
    return joinLines([
      "Hallo Mathias,",
      "",
      "ich möchte eine automatische Anbindung für unsere Termine bei Bocholt erleben anfragen.",
      "",
      lineIf("Organisation / Veranstalter", context.organization),
      lineIf("Ansprechpartner", context.contact),
      lineIf("E-Mail", context.email),
      lineIf("Vorhandene Quelle", context.sourceType),
      lineIf("Link zur Quelle oder zu Beispielterminen", context.sourceLink),
      lineIf("Website", context.website),
      lineIf("Aktualisierungsrhythmus", context.cadence),
      lineIf("Geschätzte Anzahl veröffentlichter Termine pro Monat", context.monthlyVolume),
      lineIf("Technischer Ansprechpartner", context.techContact),
      "",
      lineIf("Weitere Hinweise (optional)", context.notes),
      "",
      "Viele Grüße"
    ]);
  }

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

  function setAutomationSubmitting(trigger, isSubmitting) {
    if (!trigger) return;
    if (!trigger.dataset.defaultLabel) {
      trigger.dataset.defaultLabel = trigger.textContent || "Anfrage senden";
    }

    trigger.disabled = isSubmitting;
    trigger.setAttribute("aria-busy", isSubmitting ? "true" : "false");
    trigger.textContent = isSubmitting ? "Anfrage wird gesendet ..." : trigger.dataset.defaultLabel;
  }

  function buildAutomationPayload(context) {
    const formData = new FormData();
    formData.append("subject", "Bocholt erleben - Automatische Anbindung anfragen");
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
      throw new Error(`Formspree request failed with status ${response.status}`);
    }

    return { mode: "formspree" };
  }

  /* === BEGIN BLOCK: STANDARD_PUBLISH_PATH_BACKEND_CHECKOUT_V1 | Zweck: ersetzt den alten Payment-Link-Flow durch den serverseitigen Submission->Checkout-Flow; Umfang: Standard-Validierung, API-Helper, Submit-State und bindStandardPath in js/publish-funnel.js === */
  function validateStandardContext(context) {
    if (!hasValue(context.plan)) {
      window.alert("Bitte wähle zuerst ein Modell aus.");
      return false;
    }

    if (!hasValue(context.eventLink) && !(hasValue(context.title) && hasValue(context.date) && hasValue(context.place))) {
      window.alert("Bitte füge einen Event-Link ein. Wenn dort wichtige Angaben fehlen, ergänze Titel, Datum und Ort direkt im Formular.");
      return false;
    }

    return true;
  }

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
        `Request failed with status ${response.status}`;

      const requestError = new Error(message);
      requestError.responseStatus = response.status;
      requestError.responsePayload = data;
      throw requestError;
    }

    if (!data || typeof data !== "object") {
      throw new Error("API response is invalid.");
    }

    return data;
  }

  function buildStandardSubmissionPayload(context) {
    return {
      requested_model_key: context.plan,
      organization_name: context.organization,
      contact_name: context.contact,
      email: context.email,
      event_url: context.eventLink,
      title: context.title,
      start_date: context.date,
      time_text: context.time,
      location_name: context.place,
      ticket_url: context.website,
      description_text: context.description,
      notes_text: context.notes
    };
  }

  function setStandardSubmitting(trigger, isSubmitting) {
    if (!trigger) return;

    if (!trigger.dataset.defaultLabel) {
      trigger.dataset.defaultLabel = trigger.textContent || "Weiter zur Zahlung";
    }

    trigger.disabled = isSubmitting;
    trigger.setAttribute("aria-busy", isSubmitting ? "true" : "false");
    trigger.textContent = isSubmitting ? "Weiterleitung wird vorbereitet ..." : trigger.dataset.defaultLabel;
  }

  function validateAutomationContext(context) {
    if (!hasValue(context.sourceLink) && !hasValue(context.website)) {
      window.alert("Bitte gib mindestens einen Link zu deiner Quelle oder Website an.");
      return false;
    }

    return true;
  }

  function bindStandardPath() {
    const form = byId("publish-standard-form");
    const trigger = byId("publish-standard-pay");

    if (!form || !trigger) return;

    trigger.addEventListener("click", async (event) => {
      event.preventDefault();

      if (typeof form.reportValidity === "function" && !form.reportValidity()) return;

      const context = buildStandardContext();
      if (!validateStandardContext(context)) return;

      setStandardSubmitting(trigger, true);

      try {
        const initResult = await postPublishApiJson(
          "/api/submissions/init.php",
          buildStandardSubmissionPayload(context)
        );

        const submissionId = Number(initResult?.data?.submission_id || 0);
        const paymentReferenceKey = safeText(initResult?.data?.payment_reference_key);

        if (submissionId <= 0) {
          throw new Error("Submission konnte nicht angelegt werden.");
        }

        persistCheckoutContext({
          submissionId,
          paymentReferenceKey,
          ...context,
          savedAt: new Date().toISOString()
        });

        const checkoutResult = await postPublishApiJson(
          "/api/stripe/create-checkout-session.php",
          { submission_id: submissionId }
        );

        const checkoutUrl = safeText(checkoutResult?.data?.checkout_url);
        if (!hasValue(checkoutUrl)) {
          throw new Error("Checkout-URL fehlt.");
        }

        window.location.href = checkoutUrl;
      } catch (error) {
        console.warn("Publish funnel: standard checkout init failed.", error);
        window.alert(safeText(error?.message) || "Die Zahlung konnte gerade nicht vorbereitet werden. Bitte versuche es erneut.");
        setStandardSubmitting(trigger, false);
      }
    });
  }
  /* === END BLOCK: STANDARD_PUBLISH_PATH_BACKEND_CHECKOUT_V1 === */

  function bindAutomationPath() {
    const form = byId("publish-automation-form");
    const trigger = byId("publish-automation-submit");

    if (!form || !trigger) return;

    trigger.addEventListener("click", async (event) => {
      event.preventDefault();

      if (typeof form.reportValidity === "function" && !form.reportValidity()) return;

      const context = buildAutomationContext();
      if (!validateAutomationContext(context)) return;

      clearAutomationStatus(form);
      setAutomationSubmitting(trigger, true);

      try {
        const result = await submitAutomationFormspree(context);

        if (result.mode === "mailto_fallback") {
          const body = buildAutomationSummary(context);
          window.location.href = buildMailto("Bocholt erleben - Automatische Anbindung anfragen", body);
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
  /* === END BLOCK: PUBLISH_FUNNEL_RUNTIME_AND_AUTOMATION_FORMSPREE_V1 === */

  function init() {
    if (!isPublishRoute()) return;
    bindStandardPath();
    bindAutomationPath();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init, { once: true });
  } else {
    init();
  }
})();
/* === END FILE: js/publish-funnel.js === */
