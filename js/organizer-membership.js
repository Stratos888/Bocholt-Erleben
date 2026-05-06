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

  /* === BEGIN BLOCK: ORGANIZER_MEMBERSHIP_PLAN_HINT_V1 | Zweck: zeigt unter der Modellauswahl nur die Kurzbeschreibung des aktuell gewählten Modells; Umfang: Modellhinweise und Change-Handler === */
  const planDescriptions = {
    starter: "Starter passt für gelegentliche Termine und kleine Veranstalter.",
    active: "Aktiv passt für regelmäßige Programme mit mehreren Terminen im Monat.",
    unlimited: "Dauerhaft passt für laufende Programme mit vielen Terminen im üblichen Rahmen."
  };

  function updatePlanHint() {
    if (!planHint) return;
    planHint.textContent = planDescriptions[safeText(planSelect.value)] || planDescriptions.starter;
  }

  planSelect.addEventListener("change", updatePlanHint);
  updatePlanHint();
  /* === END BLOCK: ORGANIZER_MEMBERSHIP_PLAN_HINT_V1 === */

  function setSubmitting(isSubmitting) {
    if (!submitButton.dataset.defaultLabel) {
      submitButton.dataset.defaultLabel = submitButton.textContent || "Weiter zur Zahlungsmethode";
    }

    submitButton.disabled = isSubmitting;
    submitButton.textContent = isSubmitting
      ? "Wird vorbereitet ..."
      : submitButton.dataset.defaultLabel;
  }

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

  /* === BEGIN BLOCK: ORGANIZER_MEMBERSHIP_SUBMIT_RETRY_SAFE_V1 | Zweck: bereitet Mitgliedschafts-Checkout vor und gibt den Button nach Fehlern wieder frei; Umfang: kompletter Submit-Handler des Mitgliedschaftsformulars === */
  form.addEventListener("submit", async (event) => {
    event.preventDefault();

    if (typeof form.reportValidity === "function" && !form.reportValidity()) {
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
      showResult("Die Zahlungsmethode konnte gerade nicht vorbereitet werden. Bitte versuche es erneut.");
    } finally {
      setSubmitting(false);
    }
  });
  /* === END BLOCK: ORGANIZER_MEMBERSHIP_SUBMIT_RETRY_SAFE_V1 === */

  /* === END FILE: js/organizer-membership.js === */
})();
