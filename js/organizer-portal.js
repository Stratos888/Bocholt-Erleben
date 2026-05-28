(() => {
  "use strict";
  /* === BEGIN FILE: js/organizer-portal.js | Zweck: kleinstmögliche Frontend-Logik für Veranstalter-Login per Magic Link und den minimalen Veranstalterbereich; Umfang: komplette Datei === */

  const pageRoot = document.querySelector("[data-organizer-page]");
  if (!pageRoot) return;

  const pageMode = String(pageRoot.getAttribute("data-organizer-page") || "").trim();

  const safeText = (value) => String(value ?? "").trim();

  function escapeHtml(value) {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#39;");
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      ...options,
      headers: {
        Accept: "application/json",
        ...(options.headers || {})
      }
    });

    const text = await response.text();
    let data = null;

    try {
      data = text ? JSON.parse(text) : null;
    } catch (error) {
      data = null;
    }

    if (!response.ok) {
      const message =
        safeText(data?.message) ||
        safeText(data?.error_message) ||
        "request_failed";

      const requestError = new Error(message);
      requestError.status = response.status;
      requestError.payload = data;
      throw requestError;
    }

    return data;
  }

  function formatPlanLabel(planKey) {
    const key = safeText(planKey).toLowerCase();
    return ({
      single: "Einzeltermin",
      starter: "Starter",
      active: "Aktiv",
      unlimited: "Dauerhaft",
      activity_basic: "Aktivitätspräsenz Basis",
      activity_plus: "Aktivitätspräsenz Plus"
    })[key] || "–";
  }

  function formatStatusLabel(status) {
    const key = safeText(status).toLowerCase();
    return ({
      pending_review: "Eingereicht",
      payment_released: "Zur Zahlung freigegeben",
      draft: "Entwurf",
      checkout_started: "Zahlung gestartet",
      paid: "Einreichung erhalten",
      in_review: "In Prüfung",
      approved: "Freigegeben",
      rejected: "Abgelehnt"
    })[key] || key || "–";
  }

  function formatSubmissionStatusLabel(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();
    const paymentKind = safeText(submission?.payment_kind).toLowerCase();
    const submissionKind = safeText(submission?.submission_kind || "event").toLowerCase();

    if (submissionKind === "activity") {
      if (statusKey === "pending_review") return "Aktivität eingereicht";
      if (statusKey === "payment_released") return "Zahlung freigegeben";
      if (statusKey === "checkout_started") return "Zahlung gestartet";
      if (statusKey === "paid" || statusKey === "in_review") return "Aktivität bezahlt – in Prüfung";
      if (statusKey === "approved") return "Aktivität veröffentlicht";
      if (statusKey === "rejected") return "Aktivität abgelehnt";
    }

    if (statusKey === "paid" && paymentKind === "subscription") {
      return "Veranstaltung eingereicht";
    }

    return formatStatusLabel(statusKey);
  }

  function formatDate(value) {
    const text = safeText(value);
    if (!text) return "–";

    const date = new Date(text);
    if (!Number.isNaN(date.getTime())) {
      return new Intl.DateTimeFormat("de-DE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric"
      }).format(date);
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
      const [year, month, day] = text.split("-");
      return `${day}.${month}.${year}`;
    }

    return text;
  }

  function formatInteger(value) {
    const number = Number.parseInt(value, 10);
    if (!Number.isFinite(number)) return "0";
    return new Intl.NumberFormat("de-DE").format(Math.max(0, number));
  }

  function metricValue(row, key) {
    return Number.parseInt(row?.[key] ?? 0, 10) || 0;
  }

  function formatImpactPeriod(period) {
    const start = formatDate(period?.start_date);
    const end = formatDate(period?.end_date);
    if (start === "–" && end === "–") return "Zeitraum: letzte 28 Tage";
    return `Zeitraum: ${start} – ${end}`;
  }

  function formatDateTime(value) {
    const text = safeText(value);
    if (!text) return "–";

    const date = new Date(text.replace(" ", "T") + (text.includes("T") ? "" : "Z"));
    if (!Number.isNaN(date.getTime())) {
      return new Intl.DateTimeFormat("de-DE", {
        day: "2-digit",
        month: "2-digit",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit"
      }).format(date);
    }

    return text;
  }

/* === BEGIN BLOCK: ORGANIZER_DASHBOARD_SUBMISSION_DETAIL_HELPERS_V1 | Zweck: bereitet Status-Erklärungen und optionale Links für die aufklappbare Einreichungsdetailansicht vor; Umfang: Submission-Helfer vor hydrateIcons === */
function formatSubmissionMetaDate(submission) {
  const statusKey = safeText(submission?.status).toLowerCase();

  if (statusKey === "approved") {
    return formatDate(submission?.start_date);
  }

  return formatDate(submission?.created_at || submission?.updated_at || submission?.start_date);
}

function formatSubmissionStatusDescription(submission) {
  const statusKey = safeText(submission?.status).toLowerCase();
  const paymentKind = safeText(submission?.payment_kind).toLowerCase();
  const submissionKind = safeText(submission?.submission_kind || "event").toLowerCase();

  if (submissionKind === "activity") {
    if (statusKey === "pending_review") return "Die Aktivität wurde eingereicht und wird auf Eignung geprüft.";
    if (statusKey === "payment_released") return "Die Aktivität wurde zur Zahlung freigegeben. Der Zahlungsstart wurde per E-Mail gesendet.";
    if (statusKey === "checkout_started") return "Die Zahlung wurde gestartet, aber noch nicht vollständig abgeschlossen.";
    if (statusKey === "paid" || statusKey === "in_review") return "Die Aktivität ist bezahlt und wird redaktionell aufbereitet sowie final geprüft.";
    if (statusKey === "approved") return "Die Aktivität wurde veröffentlicht.";
    if (statusKey === "rejected") return "Die Aktivität wurde nicht veröffentlicht.";
  }

  if (statusKey === "pending_review") {
    return "Die Einreichung wurde erhalten und wird vor der Zahlung geprüft.";
  }

  if (statusKey === "payment_released") {
    return "Die Einreichung wurde zur Zahlung freigegeben. Der Zahlungsstart wurde per E-Mail gesendet.";
  }

  if (statusKey === "draft") {
    return "Die Einreichung wurde begonnen, aber noch nicht abgeschlossen.";
  }

  if (statusKey === "checkout_started") {
    return "Die Zahlung wurde gestartet, aber noch nicht vollständig abgeschlossen.";
  }

  if (statusKey === "paid" || statusKey === "in_review") {
    return paymentKind === "subscription"
      ? "Die Einreichung ist angekommen und wird geprüft."
      : "Die Einreichung ist angekommen. Die Veröffentlichung erfolgt nach Prüfung.";
  }

  if (statusKey === "approved") {
    return "Die Veranstaltung wurde freigegeben.";
  }

  if (statusKey === "rejected") {
    return "Die Veranstaltung wurde nicht veröffentlicht.";
  }

  return "Der aktuelle Status liegt vor. Bei Rückfragen melden wir uns per E-Mail.";
}

function normalizeExternalUrl(value) {
  const text = safeText(value);
  if (!text) return "";

  try {
    const url = new URL(text, window.location.origin);
    return ["http:", "https:"].includes(url.protocol) ? url.href : "";
  } catch (error) {
    return "";
  }
}
/* === END BLOCK: ORGANIZER_DASHBOARD_SUBMISSION_DETAIL_HELPERS_V1 === */

  function hydrateIcons(root = document) {
    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }
  }

  /* === BEGIN BLOCK: ORGANIZER_PORTAL_SESSION_AND_BILLING_HELPERS_V2 | Zweck: laedt Portal-State, oeffnet Stripe Billing Portal und beendet die Veranstalter-Session; Umfang: API-/Session-Helfer fuer Dashboard/Portal === */
  async function tryLoadPortalState() {
    return requestJson("/api/organizer-portal/me.php", {
      method: "GET"
    });
  }

  async function createBillingPortalSession() {
    return requestJson("/api/organizer-portal/create-billing-portal-session.php", {
      method: "POST"
    });
  }

  async function logoutOrganizerPortal(trigger) {
    const button = trigger instanceof HTMLElement ? trigger : null;

    if (button && !button.dataset.defaultLabel) {
      button.dataset.defaultLabel = button.textContent || "Abmelden";
    }

    if (button) {
      button.disabled = true;
      button.textContent = "Wird abgemeldet …";
    }

    try {
      await requestJson("/api/organizer-portal/logout.php", {
        method: "POST"
      });

      window.location.assign("/fuer-veranstalter/login/");
    } catch (error) {
      window.alert("Abmelden war gerade nicht möglich. Bitte versuche es erneut.");

      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "Abmelden";
      }
    }
  }

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_HEADER_LOGOUT_BUTTON_V1 | Zweck: aktiviert den statischen Abmelden-Button im Dashboard-Header nach erfolgreicher Session-Prüfung; Umfang: ersetzt ensureDashboardLogoutLink() === */
  function ensureDashboardLogoutButton() {
    const logoutButton = document.getElementById("organizer-dashboard-logout");

    if (!logoutButton) {
      return;
    }

    logoutButton.hidden = false;
    logoutButton.removeAttribute("hidden");

    if (logoutButton.dataset.logoutBound === "true") {
      return;
    }

    logoutButton.dataset.logoutBound = "true";
    logoutButton.addEventListener("click", () => {
      void logoutOrganizerPortal(logoutButton);
    });
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_HEADER_LOGOUT_BUTTON_V1 === */

  async function openBillingPortal(trigger) {
    const button = trigger instanceof HTMLElement ? trigger : null;

    if (button && !button.dataset.defaultLabel) {
      button.dataset.defaultLabel = button.textContent || "Mitgliedschaft verwalten";
    }

    if (button) {
      button.disabled = true;
      button.textContent = "Weiterleitung wird vorbereitet …";
    }

    try {
      const result = await createBillingPortalSession();
      const redirectUrl = safeText(result?.data?.redirect_url);

      if (!redirectUrl) {
        throw new Error("missing_billing_portal_redirect_url");
      }

      window.location.assign(redirectUrl);
    } catch (error) {
      console.warn("Organizer portal: billing portal failed.", error);
      window.alert("Die Mitgliedschaftsverwaltung konnte gerade nicht geöffnet werden. Bitte versuche es erneut.");

      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "Mitgliedschaft verwalten";
      }
    }
  }

  window.addEventListener("pageshow", () => {
    const billingButton = document.getElementById("organizer-dashboard-manage-subscription");
    if (!billingButton) return;

    billingButton.disabled = false;
    billingButton.textContent = billingButton.dataset.defaultLabel || "Mitgliedschaft verwalten";
  });
  /* === END BLOCK: ORGANIZER_PORTAL_SESSION_AND_BILLING_HELPERS_V2 === */

  function setLoginResult(message, links = []) {
    const resultCard = document.getElementById("organizer-login-result");
    const resultText = document.getElementById("organizer-login-result-text");
    const resultLinks = document.getElementById("organizer-login-result-links");

    if (!resultCard || !resultText || !resultLinks) return;

    resultText.textContent = message;
    resultCard.hidden = false;

    resultLinks.innerHTML = "";
    resultLinks.hidden = links.length === 0;

    links.forEach((link) => {
      const anchor = document.createElement("a");
      anchor.className = "content-link";
      anchor.href = link.href;
      if (link.targetBlank) {
        anchor.target = "_blank";
        anchor.rel = "noopener noreferrer";
      }
      anchor.innerHTML = `
        <span class="content-link__label">${escapeHtml(link.label)}</span>
        <span class="content-link__chevron" data-ui-icon="${escapeHtml(link.icon || "chevron-right")}" aria-hidden="true"></span>
      `;
      resultLinks.appendChild(anchor);
    });

    hydrateIcons(resultLinks);
  }

  async function consumeMagicLink(token) {
    return requestJson("/api/organizer-portal/consume-magic-link.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ token })
    });
  }

  /* === BEGIN BLOCK: ORGANIZER_LOGIN_MAGIC_LINK_FORM_V6 | Zweck: finalisiert Login-Wording, deaktiviert native Browser-Bubbles und markiert fehlende E-Mail-Pflichtfelder analog zu den anderen Formularen; Umfang: komplette handleLoginPage-Funktion === */
  async function handleLoginPage() {
    const loginForm = document.getElementById("organizer-login-form");
    const loginSubmit = document.getElementById("organizer-login-submit");
    const loginEmail = document.getElementById("organizer-login-email");
    const loginNote = document.getElementById("organizer-login-note");

    const params = new URLSearchParams(window.location.search);
    const token = safeText(params.get("token"));
    const membershipStarted = safeText(params.get("membership_started")) === "1";
    const prefillEmail = safeText(params.get("email"));

    const setSubmitting = (isSubmitting) => {
      if (!loginSubmit) return;
      if (!loginSubmit.dataset.defaultLabel) {
        loginSubmit.dataset.defaultLabel = loginSubmit.textContent || "Zugangslink senden";
      }
      loginSubmit.disabled = isSubmitting;
      loginSubmit.textContent = isSubmitting ? "Zugangslink wird vorbereitet ..." : loginSubmit.dataset.defaultLabel;
    };

    function getLoginValidationStatus() {
      let statusNode = loginForm.querySelector("[data-organizer-login-validation-status]");
      if (statusNode) return statusNode;

      statusNode = document.createElement("p");
      statusNode.className = "content-form-note organizer-validation-note";
      statusNode.hidden = true;
      statusNode.setAttribute("data-organizer-login-validation-status", "");
      statusNode.setAttribute("aria-live", "assertive");

      const referenceNode = loginForm.querySelector(".content-actions");
      if (referenceNode) {
        referenceNode.insertAdjacentElement("beforebegin", statusNode);
      } else {
        loginForm.appendChild(statusNode);
      }

      return statusNode;
    }

    function setLoginValidationStatus(message) {
      const statusNode = getLoginValidationStatus();
      statusNode.hidden = false;
      statusNode.textContent = safeText(message);
    }

    function clearLoginValidationStatus() {
      const statusNode = loginForm.querySelector("[data-organizer-login-validation-status]");
      if (!statusNode) return;
      statusNode.hidden = true;
      statusNode.textContent = "";
    }

    function clearLoginValidationState() {
      if (loginEmail) {
        loginEmail.removeAttribute("aria-invalid");
        const field = loginEmail.closest(".content-field");
        if (field) delete field.dataset.fieldInvalid;
      }

      clearLoginValidationStatus();
    }

    function markLoginEmailInvalid() {
      if (!loginEmail) return;

      loginEmail.setAttribute("aria-invalid", "true");
      const field = loginEmail.closest(".content-field");
      if (field) field.dataset.fieldInvalid = "true";

      setLoginValidationStatus("Bitte fülle die markierten Pflichtfelder aus.");

      if (typeof loginEmail.focus === "function") {
        loginEmail.focus({ preventScroll: false });
      }
    }

    function validateLoginForm() {
      if (!loginEmail || !safeText(loginEmail.value) || !loginEmail.validity.valid) {
        markLoginEmailInvalid();
        return false;
      }

      return true;
    }

    if (loginEmail && prefillEmail && !safeText(loginEmail.value)) {
      loginEmail.value = prefillEmail;
    }

    if (membershipStarted) {
      setLoginResult(
        "Deine Mitgliedschaft wurde gestartet. Fordere jetzt deinen Zugangslink an, um deinen Veranstalterbereich zu öffnen."
      );
    }

    if (token) {
      /* === BEGIN BLOCK: ORGANIZER_LOGIN_MAGIC_LINK_STRICT_SESSION_V1 | Zweck: verhindert, dass ein fehlgeschlagener Magic-Link alte Portal-Sessions öffnet; Umfang: ersetzt nur den Token-Consume-Zweig der Login-Seite === */
      if (loginNote) {
        loginNote.textContent = "Der Zugangslink wird geprüft. Danach öffnen wir automatisch den passenden Anbieterbereich.";
      }

      try {
        await consumeMagicLink(token);
        window.location.replace("/fuer-veranstalter/dashboard/");
        return;
      } catch (error) {
        console.warn("Organizer portal: magic link consume failed.", error);
        setLoginResult(
          "Der Zugangslink konnte nicht eingelöst werden. Bitte fordere einen neuen Zugangslink an.",
          [{ href: "/fuer-veranstalter/login/", label: "Neuen Zugangslink anfordern", icon: "chevron-right" }]
        );
        return;
      }
      /* === END BLOCK: ORGANIZER_LOGIN_MAGIC_LINK_STRICT_SESSION_V1 === */
    }

    if (!loginForm || !loginEmail) return;

    loginForm.noValidate = true;
    if (loginSubmit) loginSubmit.formNoValidate = true;

    loginForm.addEventListener("input", () => {
      clearLoginValidationState();
    });

    loginForm.addEventListener("change", () => {
      clearLoginValidationState();
    });

    loginForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      clearLoginValidationState();

      if (!validateLoginForm()) {
        return;
      }

      setSubmitting(true);

      try {
        const result = await requestJson("/api/organizer-portal/request-magic-link.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            email: safeText(loginEmail.value)
          })
        });

        const data = result?.data || {};
        const links = [];

        if (safeText(data.magic_link_url)) {
          links.push({
            href: data.magic_link_url,
            label: "Status oder Veranstalterbereich öffnen",
            icon: "chevron-right"
          });
        }

        setLoginResult(
          safeText(data.magic_link_url)
            ? "Der Zugangslink wurde erzeugt. In dieser Staging-Umgebung kannst du den Status oder Veranstalterbereich direkt öffnen."
            : "Der Zugangslink wurde angefordert. Bitte prüfe dein Postfach.",
          links
        );
      } catch (error) {
        console.warn("Organizer portal: magic link request failed.", error);
        setLoginResult("Dein Zugangslink konnte gerade nicht angefordert werden. Bitte prüfe die E-Mail-Adresse oder versuche es später erneut.");
      } finally {
        setSubmitting(false);
      }
    });
  }
  /* === END BLOCK: ORGANIZER_LOGIN_MAGIC_LINK_FORM_V6 === */

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_V2_STRUCTURE_HELPERS_V1 | Zweck: verdichtet Anbieter-Dashboard-Status und gruppiert wiederholte Tarifzeilen; Umfang: additive Helper vor renderDashboard === */
  function buildOrganizerTariffRows(activeSubscriptions) {
    const grouped = new Map();

    (Array.isArray(activeSubscriptions) ? activeSubscriptions : []).forEach((item) => {
      const label = safeText(item?.plan_label) || formatPlanLabel(item?.plan_key);
      const amount = safeText(item?.monthly_amount_label) || "–";
      if (!label) return;

      const key = `${label}||${amount}`;
      const current = grouped.get(key) || { label, amount, count: 0 };
      current.count += 1;
      grouped.set(key, current);
    });

    return Array.from(grouped.values()).map((row) => ({
      label: row.count > 1 ? `${formatInteger(row.count)} × ${row.label}` : row.label,
      amount: row.count > 1 ? `${formatInteger(row.count)} × ${row.amount}` : row.amount
    }));
  }

  function buildOrganizerIncludedRows(quotaByPlan) {
    const buckets = {
      events: { consumed: 0, included: 0, unlimited: false },
      activities: { consumed: 0, included: 0, unlimited: false }
    };

    (Array.isArray(quotaByPlan) ? quotaByPlan : []).forEach((item) => {
      const planKey = safeText(item?.plan_key).toLowerCase();
      const bucket = ["activity_basic", "activity_plus"].includes(planKey) ? buckets.activities : buckets.events;

      if (!["starter", "active", "unlimited", "activity_basic", "activity_plus"].includes(planKey)) return;

      bucket.consumed += Number(item?.consumed_total || 0);
      bucket.included += Number(item?.included_total || 0);
      bucket.unlimited = bucket.unlimited || Boolean(item?.has_unlimited);
    });

    const rows = [];

    if (buckets.events.unlimited) {
      rows.push("Veranstaltungen: unbegrenzt veröffentlichte Termine");
    } else if (buckets.events.consumed > 0 || buckets.events.included > 0) {
      rows.push(`Veranstaltungen: ${formatInteger(buckets.events.consumed)} von ${formatInteger(buckets.events.included)} veröffentlichten Terminen genutzt`);
    }

    if (buckets.activities.unlimited) {
      rows.push("Aktivitäten: unbegrenzt veröffentlichte Aktivitäten");
    } else if (buckets.activities.consumed > 0 || buckets.activities.included > 0) {
      rows.push(`Aktivitäten: ${formatInteger(buckets.activities.consumed)} von ${formatInteger(buckets.activities.included)} veröffentlichten Aktivitäten genutzt`);
    }

    return rows;
  }

  function isOrganizerPublishedSubmission(submission) {
    return safeText(submission?.status).toLowerCase() === "approved";
  }

  function isOrganizerOpenSubmission(submission) {
    return new Set(["draft", "checkout_started", "pending_review", "payment_released", "paid", "in_review"]).has(
      safeText(submission?.status).toLowerCase()
    );
  }

  function organizerSubmissionAreaLabel(activeSubscriptions, submissions, isActivityPlanView) {
    const planKeys = (Array.isArray(activeSubscriptions) ? activeSubscriptions : [])
      .map((item) => safeText(item?.plan_key).toLowerCase());
    const hasActivityPlan = planKeys.some((key) => key === "activity_basic" || key === "activity_plus");
    const hasEventPlan = planKeys.some((key) => key === "starter" || key === "active" || key === "unlimited");
    const hasActivitySubmission = (Array.isArray(submissions) ? submissions : [])
      .some((item) => safeText(item?.submission_kind || "event").toLowerCase() === "activity");
    const hasEventSubmission = (Array.isArray(submissions) ? submissions : [])
      .some((item) => safeText(item?.submission_kind || "event").toLowerCase() !== "activity");

    if ((hasActivityPlan || hasActivitySubmission || isActivityPlanView) && (hasEventPlan || hasEventSubmission)) {
      return "Veranstaltungen & Aktivitäten";
    }

    if (hasActivityPlan || hasActivitySubmission || isActivityPlanView) {
      return "Aktivitäten";
    }

    return "Veranstaltungen";
  }

  function renderOrganizerSubmissionsSummary(context) {
    const summary = document.getElementById("organizer-dashboard-submissions-summary");
    const overview = document.getElementById("organizer-dashboard-submissions-overview");
    const nextStep = document.getElementById("organizer-dashboard-next-step");

    if (!summary || !overview || !nextStep) return;

    const submissions = Array.isArray(context?.submissions) ? context.submissions : [];
    const activeSubscriptions = Array.isArray(context?.activeSubscriptions) ? context.activeSubscriptions : [];
    const latestSubmission = context?.latestSubmission || null;

    if (context?.isSingleStatusView) {
      summary.textContent = latestSubmission
        ? `${formatStatusLabel(latestSubmission.status)} · ${formatDate(latestSubmission.start_date || latestSubmission.created_at)}`
        : "Noch keine Einreichung gefunden.";

      overview.innerHTML = [
        ["Bereich", "Einzeltermin"],
        ["Status", latestSubmission ? formatStatusLabel(latestSubmission.status) : "–"],
        ["Ort", latestSubmission ? (safeText(latestSubmission.location_name) || "–") : "–"]
      ].map(([label, value]) => `
        <span class="organizer-tariff-row" role="listitem">
          <span class="organizer-tariff-label">${escapeHtml(label)}</span>
          <span class="organizer-tariff-price">${escapeHtml(value)}</span>
        </span>
      `).join("");

      nextStep.textContent = "Öffne unten die Einreichung, um Status, Angaben und mögliche Änderungen zu prüfen.";
      return;
    }

    const visibleCount = submissions.filter((item) => safeText(item?.status).toLowerCase() === "approved").length;
    const reviewCount = submissions.filter((item) => {
      const status = safeText(item?.status).toLowerCase();
      return status === "pending_review" || status === "paid" || status === "in_review";
    }).length;
    const paymentOpenCount = submissions.filter((item) => {
      const status = safeText(item?.status).toLowerCase();
      return status === "payment_released" || status === "checkout_started";
    }).length;
    const rejectedCount = submissions.filter((item) => safeText(item?.status).toLowerCase() === "rejected").length;
    const areaLabel = organizerSubmissionAreaLabel(activeSubscriptions, submissions, context?.isActivityPlanView);

    summary.textContent = `${areaLabel}. ${formatInteger(visibleCount)} sichtbar, ${formatInteger(reviewCount)} in Prüfung.`;

    overview.innerHTML = [
      ["Sichtbar", formatInteger(visibleCount)],
      ["In Prüfung", formatInteger(reviewCount)],
      ["Zahlung offen", formatInteger(paymentOpenCount)],
      ["Abgelehnt", formatInteger(rejectedCount)]
    ].map(([label, value]) => `
      <span class="organizer-tariff-row" role="listitem">
        <span class="organizer-tariff-label">${escapeHtml(label)}</span>
        <span class="organizer-tariff-price">${escapeHtml(value)}</span>
      </span>
    `).join("");

    if (paymentOpenCount > 0) {
      nextStep.textContent = "Es gibt mindestens eine Einreichung mit offenem Zahlungsschritt.";
    } else if (reviewCount > 0) {
      nextStep.textContent = "Einreichungen sind in Prüfung. Du musst aktuell nichts tun.";
    } else {
      nextStep.textContent = "Du kannst neue Inhalte einreichen oder bestehende Einreichungen unten prüfen.";
    }
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_V2_STRUCTURE_HELPERS_V1 === */

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_IMPACT_RENDERER_V1 | Zweck: rendert sichere Anbieter-Nutzwertzahlen als vorsichtiges Wertzentrum; Umfang: additive Helfer vor renderDashboard === */
  function renderOrganizerImpactCard(data, isSingleStatusView, isActivityPlanView = false) {
    const card = document.getElementById("organizer-dashboard-impact-card");
    const periodNode = document.getElementById("organizer-impact-period");
    const metricsNode = document.getElementById("organizer-impact-metrics");
    const noteNode = document.getElementById("organizer-impact-note");

    if (!card || !periodNode || !metricsNode || !noteNode) return;

    if (isSingleStatusView || isActivityPlanView) {
      card.hidden = true;
      card.setAttribute("hidden", "");
      return;
    }

    const impact = data?.impact_summary || {};
    const metrics = impact?.metrics || {};
    const previous = impact?.previous_metrics || {};
    const total = metricValue(metrics, "total_interactions");
    const directClicks =
      metricValue(metrics, "website_clicks") +
      metricValue(metrics, "maps_clicks") +
      metricValue(metrics, "location_clicks") +
      metricValue(metrics, "organizer_cta_clicks");
    const previousTotal = metricValue(previous, "total_interactions");
    const totalDelta = total - previousTotal;
    const deltaLabel = totalDelta > 0
      ? `+${formatInteger(totalDelta)} gegenüber dem vorherigen Zeitraum`
      : totalDelta < 0
        ? `-${formatInteger(Math.abs(totalDelta))} gegenüber dem vorherigen Zeitraum`
        : "kein Unterschied zum vorherigen Zeitraum";

    card.hidden = false;
    card.removeAttribute("hidden");
    periodNode.textContent = formatImpactPeriod(impact?.period);

    metricsNode.innerHTML = [
      ["Interaktionen gesamt", total],
      ["Detail-Aufrufe", metricValue(metrics, "detail_views")],
      ["Website-/Ticket-Klicks", metricValue(metrics, "website_clicks")],
      ["Route/Maps", metricValue(metrics, "maps_clicks")]
    ].map(([label, value]) => `
      <span class="organizer-tariff-row" role="listitem">
        <span class="organizer-tariff-label">${escapeHtml(label)}</span>
        <span class="organizer-tariff-price">${escapeHtml(formatInteger(value))}</span>
      </span>
    `).join("");

    if (impact?.status === "not_configured" || impact?.status === "not_available") {
      noteNode.textContent = impact?.message || "Die Nutzwert-Auswertung ist für diesen Bereich noch nicht verfügbar.";
      return;
    }

    if (total > 0) {
      noteNode.textContent = `Nutzer haben deine veröffentlichten Inhalte geöffnet oder weiterführende Links genutzt. Direkt erklärbare Klicks: ${formatInteger(directClicks)}; ${deltaLabel}. Keine Besucherzahlen vor Ort, keine Buchungen, keine eindeutigen Personen.`;
    } else {
      noteNode.textContent = "Noch keine Nutzsignale im aktuellen Zeitraum. Die Messung läuft; Werte erscheinen, sobald veröffentlichte Inhalte angesehen oder weiterführende Links genutzt werden.";
    }
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_IMPACT_RENDERER_V1 === */

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_AREA_SPLIT_COPY_V2_VALUE_CENTER_COPY | Zweck: rendert dieselbe technische Bereichsroute je nach Datenlage als Einreichungsstatus oder Veranstalter-Wertzentrum ohne neue Datenlogik; Umfang: komplette renderDashboard-Funktion === */
   function renderDashboard(data) {
    const authRequiredCard = document.getElementById("organizer-dashboard-auth-required");
    const summaryGrid = document.getElementById("organizer-dashboard-summary");
    const submissionsCard = document.getElementById("organizer-dashboard-submissions-card");
    const impactCard = document.getElementById("organizer-dashboard-impact-card");

    if (authRequiredCard) {
      authRequiredCard.hidden = true;
      authRequiredCard.setAttribute("hidden", "");
    }
    if (summaryGrid) summaryGrid.hidden = false;
    if (impactCard) {
      impactCard.hidden = true;
      impactCard.setAttribute("hidden", "");
    }
    if (submissionsCard) submissionsCard.hidden = false;

    const organizer = data?.organizer || {};
    const quota = data?.quota || {};
    const subscription = data?.subscription || null;
    const activeSubscriptions = Array.isArray(data?.active_subscriptions) ? data.active_subscriptions : [];
    const quotaByPlan = Array.isArray(data?.quota_by_plan) ? data.quota_by_plan : [];
    const billingSummary = data?.billing_summary || {};
    const submissions = Array.isArray(data?.recent_submissions) ? data.recent_submissions : [];

    const latestSubmission = submissions[0] || null;
    const defaultPlanKey = safeText(organizer.default_plan_key).toLowerCase();
    const subscriptionPlanKey = safeText(subscription?.plan_key).toLowerCase();
    const hasManageableSubscription = ["starter", "active", "unlimited", "activity_basic", "activity_plus"].includes(subscriptionPlanKey);
    const effectivePlanKey = hasManageableSubscription ? subscriptionPlanKey : (defaultPlanKey || "single");
    const isActivityPlanView = ["activity_basic", "activity_plus"].includes(effectivePlanKey);
    const latestTitleText = latestSubmission
      ? safeText(latestSubmission.title) || "Ohne Titel"
      : "Noch keine Einreichung";
    const latestStatusText = latestSubmission
      ? formatStatusLabel(latestSubmission.status)
      : "Noch kein Status";
    const latestDateText = latestSubmission
      ? formatDate(latestSubmission.start_date || latestSubmission.created_at)
      : "–";
    const latestLocationText = latestSubmission
      ? safeText(latestSubmission.location_name)
      : "";

    const isSingleStatusView =
      effectivePlanKey === "single" &&
      !hasManageableSubscription &&
      !quota.has_unlimited;
    const kicker = document.getElementById("organizer-dashboard-kicker");
    const title = document.getElementById("organizer-dashboard-title");
    const lead = document.getElementById("organizer-dashboard-lead");
    const note = document.getElementById("organizer-dashboard-note");
    const accountHead = document.getElementById("organizer-dashboard-account-head");
    const accountName = document.getElementById("organizer-account-name");
    const accountEmail = document.getElementById("organizer-account-email");
    const accountPlan = document.getElementById("organizer-account-plan");
    const quotaHead = document.getElementById("organizer-dashboard-quota-head");
    const quotaPeriod = document.getElementById("organizer-quota-period");
    const quotaChange = document.getElementById("organizer-quota-change");
    const quotaSummary = document.getElementById("organizer-quota-summary");
    const quotaRemaining = document.getElementById("organizer-quota-remaining");
    const submissionsHead = document.getElementById("organizer-dashboard-submissions-head");
    const submissionsList = document.getElementById("organizer-dashboard-submissions-list");
    const submissionsEmpty = document.getElementById("organizer-dashboard-submissions-empty");
    const dashboardPrimaryCta = document.getElementById("organizer-dashboard-primary-cta");
    const dashboardActions = dashboardPrimaryCta ? dashboardPrimaryCta.parentElement : null;
    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_HERO_NOTE_COPY_V4_VALUE_CENTER_COPY | Zweck: richtet den Dashboard-Hero auf Einreichungen, Veröffentlichungsstatus und Mitgliedschaft aus; Umfang: Kicker-, Titel-, Lead- und Note-Texte in renderDashboard === */
    if (kicker) {
      kicker.textContent = "";
      kicker.hidden = true;
    }

    if (title) {
      title.textContent = isSingleStatusView
        ? "Meine Einreichung"
        : safeText(organizer.organization_name) || "Mein Veranstalterbereich";
    }

    if (lead) {
      if (isSingleStatusView) {
        lead.textContent = latestSubmission
          ? `${latestStatusText} · ${latestDateText}`
          : "Status deiner Einreichung.";
      } else {
        lead.textContent = isActivityPlanView
          ? "Hier siehst du deine Aktivitätspräsenz, Einreichungen und Veröffentlichungsstatus an einem Ort."
          : "Hier siehst du deine Einreichungen, Veröffentlichungsstatus und Mitgliedschaft an einem Ort.";
      }
    }

    if (note) {
      if (isSingleStatusView) {
        note.textContent = "Hier siehst du den aktuellen Stand deiner Einreichung.";
        note.hidden = false;
      } else {
        note.textContent = "";
        note.hidden = true;
      }
    }
    /* === END BLOCK: ORGANIZER_DASHBOARD_HERO_NOTE_COPY_V4_VALUE_CENTER_COPY === */

    if (dashboardPrimaryCta) {
      if (isActivityPlanView) {
        dashboardPrimaryCta.href = "/angebote/sichtbar-werden/";
        dashboardPrimaryCta.textContent = "Weitere Aktivität einreichen";
      } else {
        const prefilledPlan = ["starter", "active", "unlimited"].includes(effectivePlanKey)
          ? effectivePlanKey
          : "single";

        dashboardPrimaryCta.href = `/events-veroeffentlichen/einreichen/?plan=${encodeURIComponent(prefilledPlan)}`;
        dashboardPrimaryCta.textContent = isSingleStatusView ? "Weitere Veranstaltung einreichen" : "Neue Veranstaltung einreichen";
      }
    }

    if (dashboardActions) {
      let manageSubscriptionButton = document.getElementById("organizer-dashboard-manage-subscription");

      if (hasManageableSubscription) {
        if (!manageSubscriptionButton) {
          manageSubscriptionButton = document.createElement("button");
          manageSubscriptionButton.type = "button";
          manageSubscriptionButton.id = "organizer-dashboard-manage-subscription";
          manageSubscriptionButton.className = "content-cta";
          manageSubscriptionButton.textContent = "Mitgliedschaft verwalten";
          dashboardActions.appendChild(manageSubscriptionButton);
        }

        manageSubscriptionButton.hidden = false;
        manageSubscriptionButton.disabled = false;
        manageSubscriptionButton.onclick = () => {
          void openBillingPortal(manageSubscriptionButton);
        };
      } else if (manageSubscriptionButton) {
        manageSubscriptionButton.remove();
      }
    }

    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_ACCOUNT_CARD_COPY_V4_VALUE_CENTER_COPY | Zweck: benennt die Stammdatenkarte klarer als Kontakt- und Organisationsbereich; Umfang: Account-Karten-Texte in renderDashboard === */
    if (accountHead) {
      accountHead.textContent = isSingleStatusView ? "Einreichung" : "Kontakt & Organisation";
    }

    if (accountName) {
      accountName.textContent = isSingleStatusView
        ? latestTitleText
        : safeText(organizer.organization_name) || "–";
    }

    if (accountEmail) {
      accountEmail.textContent = isSingleStatusView
        ? `${latestStatusText} · ${latestDateText}`
        : safeText(organizer.email) || "–";
    }

    if (accountPlan) {
      if (isSingleStatusView) {
        accountPlan.hidden = false;
        accountPlan.textContent = latestLocationText
          ? `Ort: ${latestLocationText}`
          : `E-Mail: ${safeText(organizer.email) || "–"}`;
      } else {
        accountPlan.textContent = "";
        accountPlan.hidden = true;
      }
    }
    /* === END BLOCK: ORGANIZER_DASHBOARD_ACCOUNT_CARD_COPY_V4_VALUE_CENTER_COPY === */

    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_MULTI_TARIFF_OVERVIEW_V3_VALUE_CENTER_COPY | Zweck: benennt Tarife und Veröffentlichungen verständlicher ohne Kontingent-/Token-Sprache; Umfang: ersetzt komplette Tarif-/Kontingentübersicht in renderDashboard === */
    const tariffRows = buildOrganizerTariffRows(activeSubscriptions);
    const includedRows = buildOrganizerIncludedRows(quotaByPlan);

    const monthlyTotal = safeText(billingSummary?.monthly_total_label);

    if (quotaHead) {
      quotaHead.textContent = isSingleStatusView ? "Status" : "Tarife & Veröffentlichungen";
    }

    if (quotaPeriod) {
      quotaPeriod.hidden = false;

      if (isSingleStatusView) {
        quotaPeriod.textContent = latestSubmission
          ? `Termin: ${latestDateText}`
          : "Veranstaltung einreichen";
      } else if (tariffRows.length > 0) {
        quotaPeriod.innerHTML = `
          <span class="organizer-tariff-section-title">Aktive Tarife</span>
          <span class="organizer-tariff-table" role="list">
            ${tariffRows.map((row) => `
              <span class="organizer-tariff-row" role="listitem">
                <span class="organizer-tariff-label">${escapeHtml(row.label)}</span>
                <span class="organizer-tariff-price">${escapeHtml(row.amount)}</span>
              </span>
            `).join("")}
          </span>
        `;
      } else {
        quotaPeriod.textContent = `Tarif: ${formatPlanLabel(subscriptionPlanKey || effectivePlanKey)}`;
      }
    }

    if (quotaChange) {
      if (isSingleStatusView || !monthlyTotal) {
        quotaChange.textContent = "";
        quotaChange.hidden = true;
      } else {
        quotaChange.hidden = false;
        quotaChange.innerHTML = `
          <span class="organizer-tariff-total" aria-label="Monatliche Gesamtsumme">
            <span class="organizer-tariff-total-label">Monatlich gesamt</span>
            <span class="organizer-tariff-total-price">${escapeHtml(monthlyTotal)}</span>
          </span>
        `;
      }
    }

    if (quotaSummary) {
      if (isSingleStatusView) {
        quotaSummary.textContent = latestSubmission
          ? "Einreichung erhalten. Die Veröffentlichung erfolgt nach Prüfung."
          : "Noch keine Einreichung gefunden.";
      } else if (includedRows.length > 0) {
        quotaSummary.innerHTML = `
          <span class="organizer-tariff-section-title">Enthalten</span>
          <span class="organizer-tariff-bullets">
            ${includedRows.map((line) => `
              <span class="organizer-tariff-bullet">${escapeHtml(line)}</span>
            `).join("")}
          </span>
        `;
      } else if (quota.has_unlimited) {
        quotaSummary.textContent = `${isActivityPlanView ? "Aktivitäten" : "Events"}: unbegrenzt`;
      } else {
        quotaSummary.textContent = `${Number(quota.consumed_total || 0)} von ${Number(quota.included_total || 0)} genutzt`;
      }
    }

    if (quotaRemaining) {
      if (isSingleStatusView) {
        quotaRemaining.textContent = "Für diese Einreichung ist keine Mitgliedschaft aktiv.";
      } else {
        quotaRemaining.textContent = "Gezählt wird erst nach redaktioneller Freigabe.";
      }
    }
    /* === END BLOCK: ORGANIZER_DASHBOARD_MULTI_TARIFF_OVERVIEW_V3_VALUE_CENTER_COPY === */

    renderOrganizerSubmissionsSummary({
      isSingleStatusView,
      isActivityPlanView,
      activeSubscriptions,
      submissions,
      latestSubmission
    });

    const hasActivityPresencePlan = activeSubscriptions.some((item) => {
      const planKey = safeText(item?.plan_key).toLowerCase();
      return planKey === "activity_basic" || planKey === "activity_plus";
    });

    renderOrganizerImpactCard(data, isSingleStatusView, isActivityPlanView || hasActivityPresencePlan);

/* === BEGIN BLOCK: ORGANIZER_DASHBOARD_CURRENT_SUBMISSIONS_INLINE_EDIT_V2_VALUE_CENTER_COPY | Zweck: zeigt Einreichungen mit Status, Details und Änderungsmöglichkeit vor Veröffentlichung; Umfang: Überschrift, Empty-State und komplette Rendering-/Edit-Logik der Einreichungsliste in renderDashboard === */
if (submissionsHead) {
  submissionsHead.textContent = isSingleStatusView ? "Meine Einreichung" : "Einreichungen & Status";
}

if (submissionsList && submissionsEmpty) {
  /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_EDIT_ELIGIBILITY_FRONTEND_V2 | Zweck: zeigt den Änderungsbutton auch für bereits freigegebene Zukunftstermine; Umfang: lokale Editierbarkeitsprüfung im Dashboard === */
  const editableStatuses = new Set(["draft", "checkout_started", "paid", "in_review"]);

  function isTodayOrFutureSubmission(submission) {
    const startDate = safeText(submission?.start_date);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(startDate)) return false;

    const now = new Date();
    const year = String(now.getFullYear()).padStart(4, "0");
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");

    return startDate >= `${year}-${month}-${day}`;
  }

  function isSubmissionEditable(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();
    const kindKey = safeText(submission?.submission_kind || "event").toLowerCase();

    if (kindKey === "activity") {
      return ["pending_review", "payment_released", "paid", "in_review", "approved"].includes(statusKey);
    }

    if (kindKey !== "event") return false;
    if (editableStatuses.has(statusKey)) return true;

    return statusKey === "approved" && isTodayOrFutureSubmission(submission);
  }
  /* === END BLOCK: ORGANIZER_SUBMISSION_EDIT_ELIGIBILITY_FRONTEND_V2 === */

  function renderSubmissionFactsHtml(values) {
    const submissionKind = safeText(values?.submission_kind || "event").toLowerCase();
    const eventDateText = formatDate(values?.start_date);
    const timeText = safeText(values?.time_text);
    const locationText = safeText(values?.location_name);
    const addressText = safeText(values?.location_address);

    if (submissionKind === "activity") {
      return `
        ${timeText ? `<div><dt>Verfügbarkeit</dt><dd>${escapeHtml(timeText)}</dd></div>` : ""}
        ${locationText ? `<div><dt>Anbieter / Ort</dt><dd>${escapeHtml(locationText)}</dd></div>` : ""}
        ${addressText ? `<div><dt>Adresse</dt><dd>${escapeHtml(addressText)}</dd></div>` : ""}
      `;
    }

    return `
      ${eventDateText !== "–" ? `<div><dt>Termin</dt><dd>${escapeHtml(eventDateText)}</dd></div>` : ""}
      ${timeText ? `<div><dt>Uhrzeit</dt><dd>${escapeHtml(timeText)}</dd></div>` : ""}
      ${locationText ? `<div><dt>Ort</dt><dd>${escapeHtml(locationText)}</dd></div>` : ""}
      ${addressText ? `<div><dt>Adresse</dt><dd>${escapeHtml(addressText)}</dd></div>` : ""}
    `;
  }

  /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_EDIT_STATUS_VISIBILITY_V4 | Zweck: zeigt Speicher- und Fehlermeldungen zuverlässig sichtbar beim Änderungsformular; Umfang: komplette Statusausgabe für Inline-Edit === */
  function setEditStatus(form, message, variant = "info") {
    const statusNode = form.querySelector("[data-submission-edit-status]");
    if (!statusNode) return;

    statusNode.hidden = false;
    statusNode.textContent = safeText(message);
    statusNode.dataset.statusVariant = variant;

    window.requestAnimationFrame(() => {
      statusNode.scrollIntoView({ block: "center", behavior: "smooth" });
    });
  }
  /* === END BLOCK: ORGANIZER_SUBMISSION_EDIT_STATUS_VISIBILITY_V4 === */

  function clearEditValidation(form) {
    form.querySelectorAll("[aria-invalid='true']").forEach((control) => {
      control.removeAttribute("aria-invalid");
    });

    form.querySelectorAll(".content-field[data-field-invalid='true']").forEach((field) => {
      delete field.dataset.fieldInvalid;
    });

    const statusNode = form.querySelector("[data-submission-edit-status]");
    if (statusNode) {
      statusNode.hidden = true;
      statusNode.textContent = "";
      delete statusNode.dataset.statusVariant;
    }
  }

  function markEditInvalid(form, controlNames) {
    const names = Array.from(new Set(controlNames));
    let firstInvalidControl = null;

    names.forEach((name) => {
      const control = form.elements[name];
      if (!control) return;

      control.setAttribute("aria-invalid", "true");
      const field = control.closest(".content-field");
      if (field) field.dataset.fieldInvalid = "true";

      if (!firstInvalidControl && typeof control.focus === "function") {
        firstInvalidControl = control;
      }
    });

    setEditStatus(form, "Bitte fülle die markierten Pflichtfelder aus.", "error");

    if (firstInvalidControl) {
      firstInvalidControl.focus({ preventScroll: false });
    }
  }

  function readEditValues(form, submission) {
    const kindKey = safeText(submission?.submission_kind || "event").toLowerCase();

    return {
      submission_id: Number(submission?.id || 0),
      submission_kind: kindKey,
      title: safeText(form.elements.title?.value),
      start_date: kindKey === "activity" ? "" : safeText(form.elements.start_date?.value),
      time_text: safeText(form.elements.time_text?.value),
      location_name: safeText(form.elements.location_name?.value),
      location_address: safeText(form.elements.location_address?.value),
      event_url: safeText(form.elements.event_url?.value),
      description_text: safeText(form.elements.description_text?.value),
      location_public_confirmed: form.elements.location_public_confirmed?.checked === true
    };
  }

  /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_EDIT_VALIDATION_V5 | Zweck: validiert Änderungsfelder getrennt für Events und Aktivitaetspraesenzen; Umfang: komplette Edit-Form-Validierung === */
  function validateEditValues(form, values) {
    const invalidNames = [];
    const eventUrlControl = form.elements.event_url;
    const kindKey = safeText(values.submission_kind || "event").toLowerCase();

    if (!values.submission_id) invalidNames.push("title");
    if (!values.title) invalidNames.push("title");
    if (kindKey !== "activity" && !values.start_date) invalidNames.push("start_date");
    if (!values.location_name) invalidNames.push("location_name");
    if (values.event_url && eventUrlControl && !eventUrlControl.validity.valid) invalidNames.push("event_url");

    if (invalidNames.length) {
      markEditInvalid(form, invalidNames);
      return false;
    }

    return true;
  }
  /* === END BLOCK: ORGANIZER_SUBMISSION_EDIT_VALIDATION_V5 === */

  function setSaveButtonState(button, isSaving) {
    if (!button) return;

    if (!button.dataset.defaultLabel) {
      button.dataset.defaultLabel = button.textContent || "Änderung speichern";
    }

    button.disabled = isSaving;
    button.textContent = isSaving ? "Änderung wird gespeichert …" : button.dataset.defaultLabel;
  }

  /* === BEGIN BLOCK: ORGANIZER_SUBMISSION_RENDER_UPDATE_AFTER_EDIT_V2 | Zweck: aktualisiert nach dem Speichern Titel, Status, Metadaten, Fakten und Link ohne Neuladen; Umfang: komplette Render-Aktualisierung einer geänderten Einreichung === */
  function updateRenderedSubmission(row, detail, submission, values, responseSubmission) {
    const titleNode = row.querySelector(".organizer-submission-row__title");
    const metaNode = row.querySelector(".organizer-submission-row__meta");
    const statusNode = detail.querySelector(".organizer-submission-detail__status span");
    const statusDescriptionNode = detail.querySelector(".organizer-submission-detail__status small");
    const factsNode = detail.querySelector(".organizer-submission-detail__facts");
    const changedNode = detail.querySelector("[data-submission-edited-note]");
    const linkSlot = detail.querySelector("[data-submission-link-slot]");
    const updated = responseSubmission || values;

    submission.status = updated.status || submission.status;
    submission.title = updated.title;
    submission.start_date = updated.start_date;
    submission.time_text = updated.time_text;
    submission.location_name = updated.location_name;
    submission.location_address = updated.location_address;
    submission.location_public_confirmed = updated.location_public_confirmed;
    submission.event_url = updated.event_url;
    submission.ticket_url = updated.ticket_url || submission.ticket_url || updated.event_url;
    submission.description_text = updated.description_text;
    submission.organizer_edited_at = updated.organizer_edited_at || submission.organizer_edited_at;
    submission.organizer_edit_count = updated.organizer_edit_count || submission.organizer_edit_count || 1;

    const nextStatusText = formatSubmissionStatusLabel(submission);
    const nextMetaDateText = formatSubmissionMetaDate(submission);

    if (titleNode) titleNode.textContent = safeText(submission.title) || "Ohne Titel";
    if (metaNode) metaNode.textContent = `${nextStatusText} · ${nextMetaDateText}`;
    if (statusNode) statusNode.textContent = `Status: ${nextStatusText}`;
    if (statusDescriptionNode) statusDescriptionNode.textContent = formatSubmissionStatusDescription(submission);
    if (factsNode) factsNode.innerHTML = renderSubmissionFactsHtml(submission);

    if (changedNode) {
      changedNode.hidden = false;
      changedNode.textContent = "Zuletzt durch dich geändert. Wir prüfen die aktuelle Version.";
    }

    if (linkSlot) {
      const nextEventUrl = normalizeExternalUrl(submission.event_url || submission.ticket_url);
      linkSlot.innerHTML = nextEventUrl
        ? `<a class="organizer-submission-detail__link" href="${escapeHtml(nextEventUrl)}" target="_blank" rel="noopener noreferrer">
            Eingereichten Link öffnen
            <span data-ui-icon="external" aria-hidden="true"></span>
          </a>`
        : "";
      hydrateIcons(linkSlot);
    }
  }
  /* === END BLOCK: ORGANIZER_SUBMISSION_RENDER_UPDATE_AFTER_EDIT_V2 === */

  async function saveSubmissionEdit(form, saveButton, row, detail, submission) {
    clearEditValidation(form);

    const values = readEditValues(form, submission);
    if (!validateEditValues(form, values)) {
      return;
    }

    setSaveButtonState(saveButton, true);

    try {
      const result = await requestJson("/api/organizer-portal/update-submission.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/json"
        },
        body: JSON.stringify(values)
      });

      updateRenderedSubmission(row, detail, submission, values, result?.data?.submission || null);
      setEditStatus(form, safeText(result?.message) || "Änderung gespeichert. Wir prüfen die aktuelle Version deiner Einreichung.", "success");
    } catch (error) {
      console.warn("Organizer portal: submission update failed.", error);
      const message = safeText(error?.payload?.message) || "Die Änderung konnte gerade nicht gespeichert werden. Bitte versuche es erneut.";
      setEditStatus(form, message, "error");
    } finally {
      setSaveButtonState(saveButton, false);
    }
  }

  submissionsList.innerHTML = "";
  submissionsEmpty.textContent = isSingleStatusView
    ? "Keine Einreichung gefunden."
    : "Noch keine aktuellen Einreichungen vorhanden.";

  if (!submissions.length) {
    submissionsEmpty.hidden = false;
  } else {
    submissionsEmpty.hidden = true;

    submissions.slice(0, 10).forEach((submission, index) => {
      const titleText = safeText(submission.title) || "Ohne Titel";
      const statusText = formatSubmissionStatusLabel(submission);
      const metaDateText = formatSubmissionMetaDate(submission);
      const metaText = `${statusText} · ${metaDateText}`;
      const statusDescription = formatSubmissionStatusDescription(submission);
      const eventUrl = normalizeExternalUrl(submission.event_url || submission.ticket_url);
      const detailId = `organizer-submission-detail-${index}`;
      const editFormId = `organizer-submission-edit-${safeText(submission.id) || index}`;
      const hasOrganizerEdit = Number(submission.organizer_edit_count || 0) > 0;
      const canEdit = isSubmissionEditable(submission);

      const row = document.createElement("button");
      row.type = "button";
      row.className = "content-link organizer-submission-row";
      row.setAttribute("aria-expanded", "false");
      row.setAttribute("aria-controls", detailId);

      row.innerHTML = `
        <span class="content-link__label organizer-submission-row__label">
          <strong class="organizer-submission-row__title">${escapeHtml(titleText)}</strong>
          <small class="organizer-submission-row__meta">${escapeHtml(metaText)}</small>
        </span>
        <span class="content-link__chevron organizer-submission-row__chevron" data-ui-icon="chevron-right" aria-hidden="true"></span>
      `;

      const detail = document.createElement("div");
      detail.className = "organizer-submission-detail";
      detail.id = detailId;
      detail.hidden = true;

      detail.innerHTML = `
        <p class="organizer-submission-detail__status">
          <span>Status: ${escapeHtml(statusText)}</span>
          <small>${escapeHtml(statusDescription)}</small>
        </p>
        <p class="content-form-note organizer-submission-detail__edited-note" data-submission-edited-note ${hasOrganizerEdit ? "" : "hidden"}>
          Zuletzt durch dich geändert. Wir prüfen die aktuelle Version.
        </p>
        <dl class="organizer-submission-detail__facts">
          ${renderSubmissionFactsHtml(submission)}
        </dl>
        <div class="organizer-submission-detail__actions">
          <span data-submission-link-slot>
            ${eventUrl ? `
              <a class="organizer-submission-detail__link" href="${escapeHtml(eventUrl)}" target="_blank" rel="noopener noreferrer">
                Eingereichten Link öffnen
                <span data-ui-icon="external" aria-hidden="true"></span>
              </a>
            ` : ""}
          </span>
          ${canEdit ? `
            <button class="organizer-submission-detail__link organizer-submission-detail__edit-toggle" type="button" aria-expanded="false" aria-controls="${escapeHtml(editFormId)}">
              Angaben ändern
            </button>
          ` : ""}
        </div>
        ${canEdit ? `
          <!-- === BEGIN BLOCK: ORGANIZER_SUBMISSION_EDIT_FORM_FIELDS_V5 | Zweck: zeigt passende Änderungsfelder für Events und Aktivitaetspraesenzen; Umfang: komplettes Inline-Edit-Formular einer Einreichung === -->
          <form class="content-form organizer-submission-edit-form" id="${escapeHtml(editFormId)}" data-submission-edit-form hidden novalidate>
            <div class="content-form-grid">
              <label class="content-field content-field--full" for="${escapeHtml(editFormId)}-title">
                <span class="content-field__label">${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "Name der Aktivität *" : "Titel der Veranstaltung *"}</span>
                <input class="content-field__control" id="${escapeHtml(editFormId)}-title" name="title" type="text" value="${escapeHtml(submission.title)}" required>
              </label>
              ${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "" : `
                <label class="content-field" for="${escapeHtml(editFormId)}-date">
                  <span class="content-field__label">Datum *</span>
                  <input class="content-field__control" id="${escapeHtml(editFormId)}-date" name="start_date" type="date" value="${escapeHtml(submission.start_date)}" required>
                </label>
              `}
              <label class="content-field" for="${escapeHtml(editFormId)}-time">
                <span class="content-field__label">${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "Verfügbarkeit" : "Uhrzeit"}</span>
                <input class="content-field__control" id="${escapeHtml(editFormId)}-time" name="time_text" type="text" inputmode="text" value="${escapeHtml(submission.time_text)}" placeholder="${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "z. B. ganzjährig buchbar" : "z. B. 19:00 Uhr"}">
              </label>
              <label class="content-field content-field--full" for="${escapeHtml(editFormId)}-location">
                <span class="content-field__label">${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "Anbieter / Ort der Aktivität *" : "Veranstaltungsort / Location *"}</span>
                <input class="content-field__control" id="${escapeHtml(editFormId)}-location" name="location_name" type="text" value="${escapeHtml(submission.location_name)}" required>
              </label>
              <label class="content-field content-field--full" for="${escapeHtml(editFormId)}-address">
                <span class="content-field__label">Adresse / offizieller Treffpunkt</span>
                <input class="content-field__control" id="${escapeHtml(editFormId)}-address" name="location_address" type="text" value="${escapeHtml(submission.location_address)}">
                <p class="content-field__hint">Optional, aber hilfreich für die Prüfung. Keine Privatadressen Dritter eintragen. Bei Outdoor-Angeboten den offiziellen Start- oder Treffpunkt angeben.</p>
              </label>
              <label class="content-field content-field--full" for="${escapeHtml(editFormId)}-event-url">
                <span class="content-field__label">${safeText(submission.submission_kind || "event").toLowerCase() === "activity" ? "Website / Buchungslink" : "Link zur Veranstaltungsseite"}</span>
                <input class="content-field__control" id="${escapeHtml(editFormId)}-event-url" name="event_url" type="url" inputmode="url" value="${escapeHtml(submission.event_url || submission.ticket_url)}" placeholder="https://...">
              </label>
              <label class="content-field content-field--full" for="${escapeHtml(editFormId)}-description">
                <span class="content-field__label">Kurze Beschreibung / Hinweise</span>
                <textarea class="content-field__control" id="${escapeHtml(editFormId)}-description" name="description_text" rows="3">${escapeHtml(submission.description_text)}</textarea>
              </label>
              <label class="content-field content-field--full content-field--checkbox" for="${escapeHtml(editFormId)}-confirmed">
                <span class="content-field__label">Bestätigung</span>
                <span class="content-field__hint">
                  <input id="${escapeHtml(editFormId)}-confirmed" name="location_public_confirmed" type="checkbox" ${Number(submission.location_public_confirmed || 0) === 1 ? "checked" : ""}>
                  Ich bestätige, dass ich berechtigt bin, diese Einreichung zu ändern, und dass der Ort öffentlich genannt werden darf.
                </span>
              </label>
            </div>
            <p class="content-form-note organizer-validation-note" data-submission-edit-status hidden></p>
            <div class="content-actions content-actions--inline organizer-submission-edit-actions">
              <button class="content-cta content-cta--primary" type="submit">Änderung speichern</button>
              <button class="content-cta organizer-submission-edit-cancel" type="button" data-submission-edit-cancel>Abbrechen</button>
            </div>
          </form>
          <!-- === END BLOCK: ORGANIZER_SUBMISSION_EDIT_FORM_FIELDS_V5 === -->
        ` : `
          <!-- === BEGIN BLOCK: ORGANIZER_SUBMISSION_LOCKED_NOTE_COPY_V2 | Zweck: erklärt, warum nur vergangene freigegebene oder abgelehnte Einreichungen nicht direkt änderbar sind; Umfang: Hinweistext für nicht editierbare Einreichungen === -->
          <p class="content-form-note organizer-submission-detail__locked-note">
            Vergangene freigegebene oder abgelehnte Einreichungen können im Dashboard nicht mehr direkt geändert werden.
          </p>
          <!-- === END BLOCK: ORGANIZER_SUBMISSION_LOCKED_NOTE_COPY_V2 === -->
        `}
      `;

      row.addEventListener("click", () => {
        const isExpanded = row.getAttribute("aria-expanded") === "true";
        row.setAttribute("aria-expanded", isExpanded ? "false" : "true");
        detail.hidden = isExpanded;
      });

      const editToggle = detail.querySelector(".organizer-submission-detail__edit-toggle");
      const editForm = detail.querySelector("[data-submission-edit-form]");

      if (editToggle && editForm) {
        editToggle.addEventListener("click", () => {
          const isOpen = editToggle.getAttribute("aria-expanded") === "true";
          editToggle.setAttribute("aria-expanded", isOpen ? "false" : "true");
          editForm.hidden = isOpen;
        });

        editForm.addEventListener("input", () => clearEditValidation(editForm));
        editForm.addEventListener("change", () => clearEditValidation(editForm));

        editForm.addEventListener("submit", (event) => {
          event.preventDefault();
          const saveButton = editForm.querySelector("button[type='submit']");
          void saveSubmissionEdit(editForm, saveButton, row, detail, submission);
        });

        const cancelButton = editForm.querySelector("[data-submission-edit-cancel]");
        if (cancelButton) {
          cancelButton.addEventListener("click", () => {
            clearEditValidation(editForm);
            editForm.hidden = true;
            editToggle.setAttribute("aria-expanded", "false");
          });
        }
      }

      submissionsList.appendChild(row);
      submissionsList.appendChild(detail);
    });

    hydrateIcons(submissionsList);
  }
}
/* === END BLOCK: ORGANIZER_DASHBOARD_CURRENT_SUBMISSIONS_INLINE_EDIT_V2_VALUE_CENTER_COPY === */
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_AREA_SPLIT_COPY_V2_VALUE_CENTER_COPY === */
  /* === BEGIN BLOCK: ORGANIZER_PORTAL_DASHBOARD_HANDLER_LOGOUT_V3 | Zweck: lädt Dashboard-State und zeigt den Header-Abmelden-Button nur bei gültiger Session; Umfang: komplette handleDashboardPage-Funktion === */
  async function handleDashboardPage() {
    const logoutButton = document.getElementById("organizer-dashboard-logout");

    try {
      const result = await tryLoadPortalState();
      renderDashboard(result?.data || {});
      ensureDashboardLogoutButton();
    } catch (_error) {
      const authRequiredCard = document.getElementById("organizer-dashboard-auth-required");
      const summaryGrid = document.getElementById("organizer-dashboard-summary");
      const submissionsCard = document.getElementById("organizer-dashboard-submissions-card");
      const impactCard = document.getElementById("organizer-dashboard-impact-card");

      if (logoutButton) {
        logoutButton.hidden = true;
        logoutButton.setAttribute("hidden", "");
      }

      if (summaryGrid) summaryGrid.hidden = true;
      if (impactCard) impactCard.hidden = true;
      if (submissionsCard) submissionsCard.hidden = true;

      if (authRequiredCard) {
        authRequiredCard.hidden = false;
        authRequiredCard.removeAttribute("hidden");
      }
    }
  }
  /* === END BLOCK: ORGANIZER_PORTAL_DASHBOARD_HANDLER_LOGOUT_V3 === */

  hydrateIcons(document);

  if (pageMode === "login") {
    handleLoginPage();
    return;
  }

  if (pageMode === "dashboard") {
    handleDashboardPage();
  }

  /* === END FILE: js/organizer-portal.js === */
})();
