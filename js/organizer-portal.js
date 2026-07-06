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

  function formatSubmissionKindLabel(submission) {
    return safeText(submission?.submission_kind || "event").toLowerCase() === "activity"
      ? "Aktivität"
      : "Veranstaltung";
  }

  function formatSubmissionBadgeLabel(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();

    if (statusKey === "approved") return "Veröffentlicht";
    if (statusKey === "rejected") return "Abgelehnt";
    if (statusKey === "payment_released" || statusKey === "checkout_started") return "Zahlung offen";
    if (statusKey === "pending_review" || statusKey === "paid" || statusKey === "in_review") return "In Prüfung";
    if (statusKey === "draft") return "Entwurf";

    return formatStatusLabel(statusKey);
  }

  function formatSubmissionBadgeTone(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();

    if (statusKey === "approved") return "success";
    if (statusKey === "rejected") return "danger";
    if (statusKey === "payment_released" || statusKey === "checkout_started") return "warning";
    if (statusKey === "pending_review" || statusKey === "paid" || statusKey === "in_review") return "neutral";

    return "muted";
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
    const compactDate = (value) => {
      const text = safeText(value);
      if (/^\d{4}-\d{2}-\d{2}$/.test(text)) {
        const [, month, day] = text.split("-");
        return `${day}.${month}.`;
      }

      const formatted = formatDate(text);
      return formatted === "–" ? "" : formatted.replace(/\.\d{4}$/, ".");
    };

    const start = compactDate(period?.start_date);
    const end = compactDate(period?.end_date);
    if (!start && !end) return "Letzte 28 Tage";
    if (start && end) return `Letzte 28 Tage · ${start}–${end}`;
    return "Letzte 28 Tage";
  }
  function impactShareTotal(metrics) {
    return metricValue(metrics, "share_clicks") + metricValue(metrics, "copy_link_clicks");
  }

  function renderImpactMetricRows(rows) {
    return `
      <span class="organizer-impact-metric-grid" role="list">
        ${rows.map(([label, value]) => `
          <span class="organizer-impact-metric" role="listitem">
            <span class="organizer-impact-metric__label">${escapeHtml(label)}</span>
            <span class="organizer-impact-metric__value">${escapeHtml(formatInteger(value))}</span>
          </span>
        `).join("")}
      </span>
    `;
  }

  function renderImpactItemRows(items) {
    const topItem = (Array.isArray(items) ? items : [])
      .filter((item) => metricValue(item?.metrics, "total_interactions") > 0)
      .sort((a, b) => metricValue(b?.metrics, "total_interactions") - metricValue(a?.metrics, "total_interactions"))[0];

    if (!topItem) return "";

    const metrics = topItem?.metrics || {};
    const title = safeText(topItem?.entity_title) || safeText(topItem?.entity_id) || "Veröffentlichter Inhalt";
    const total = metricValue(metrics, "total_interactions");

    return `
      <span class="organizer-impact-top-item" role="note">
        <span class="organizer-impact-top-item__label">Stärkster Inhalt</span>
        <span class="organizer-impact-top-item__title">${escapeHtml(title)}</span>
        <span class="organizer-impact-top-item__meta">${escapeHtml(formatInteger(total))} gemessene Aktionen</span>
      </span>
    `;
  }

  function renderSubmissionImpactHtml(submission) {
    const metrics = submission?.impact_metrics || {};
    const total = metricValue(metrics, "total_interactions");
    const status = safeText(submission?.status).toLowerCase();
    if (total <= 0 && status !== "approved") return "";

    const shares = impactShareTotal(metrics);
    const websiteOrInfo = metricValue(metrics, "website_clicks") + metricValue(metrics, "organizer_cta_clicks");
    const routeOrMaps = metricValue(metrics, "maps_clicks") + metricValue(metrics, "location_clicks");
    const note = total > 0
      ? "Gemessene Aktionen in den letzten 28 Tagen."
      : "Noch keine Aktionen in den letzten 28 Tagen.";

    return `
      <div class="organizer-submission-impact-fact">
        <dt>Wirkung dieses Inhalts</dt>
        <dd>
          <span class="organizer-submission-impact-total">${escapeHtml(formatInteger(total))} gemessene Aktionen</span>
          <span class="organizer-submission-impact-note">${escapeHtml(note)}</span>
          <span class="organizer-submission-impact-grid" role="list">
            <span role="listitem"><span>Detail-Aufrufe</span><strong>${escapeHtml(formatInteger(metricValue(metrics, "detail_views")))}</strong></span>
            <span role="listitem"><span>Website/Info</span><strong>${escapeHtml(formatInteger(websiteOrInfo))}</strong></span>
            <span role="listitem"><span>Route/Maps</span><strong>${escapeHtml(formatInteger(routeOrMaps))}</strong></span>
            <span role="listitem"><span>Teilungen</span><strong>${escapeHtml(formatInteger(shares))}</strong></span>
          </span>
        </dd>
      </div>
    `;
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
      const planKey = safeText(item?.plan_key).toLowerCase();
      let label = safeText(item?.plan_label) || formatPlanLabel(item?.plan_key);
      const amount = safeText(item?.monthly_amount_label) || "–";
      if (["starter", "active", "unlimited"].includes(planKey) && !/veranstaltung/i.test(label)) {
        label = `${label} – Veranstaltungen`;
      }
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
      rows.push(`Veranstaltungen: ${formatInteger(buckets.events.consumed)} von ${formatInteger(buckets.events.included)} Terminen genutzt`);
    }

    if (buckets.activities.unlimited) {
      rows.push("Aktivitäten: unbegrenzt veröffentlichte Aktivitäten");
    } else if (buckets.activities.consumed > 0 || buckets.activities.included > 0) {
      rows.push(`Aktivitäten: ${formatInteger(buckets.activities.consumed)} von ${formatInteger(buckets.activities.included)} Aktivitäten genutzt`);
    }

    return rows;
  }

  function buildOrganizerIncludedCompactRows(quotaByPlan) {
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
      rows.push("Veranstaltungen unbegrenzt");
    } else if (buckets.events.consumed > 0 || buckets.events.included > 0) {
      rows.push(`Veranstaltungen ${formatInteger(buckets.events.consumed)}/${formatInteger(buckets.events.included)}`);
    }

    if (buckets.activities.unlimited) {
      rows.push("Aktivitäten unbegrenzt");
    } else if (buckets.activities.consumed > 0 || buckets.activities.included > 0) {
      rows.push(`Aktivitäten ${formatInteger(buckets.activities.consumed)}/${formatInteger(buckets.activities.included)}`);
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

  function getOrganizerDashboardCounts(submissions) {
    const rows = Array.isArray(submissions) ? submissions : [];
    return {
      visible: rows.filter((item) => safeText(item?.status).toLowerCase() === "approved").length,
      review: rows.filter((item) => {
        const status = safeText(item?.status).toLowerCase();
        return status === "pending_review" || status === "paid" || status === "in_review";
      }).length,
      paymentOpen: rows.filter((item) => {
        const status = safeText(item?.status).toLowerCase();
        return status === "payment_released" || status === "checkout_started";
      }).length,
      rejected: rows.filter((item) => safeText(item?.status).toLowerCase() === "rejected").length
    };
  }

  function buildOrganizerDashboardStatus(counts) {
    if (Number(counts?.paymentOpen || 0) > 0) {
      return {
        label: "Rückmeldung erforderlich",
        detail: `${formatInteger(counts.paymentOpen)} Einreichung${counts.paymentOpen === 1 ? " hat" : "en haben"} einen offenen Zahlungsschritt.`
      };
    }

    if (Number(counts?.review || 0) > 0) {
      return {
        label: "Keine Rückmeldung erforderlich",
        detail: `${formatInteger(counts.review)} Einreichung${counts.review === 1 ? " ist" : "en sind"} bei uns in redaktioneller Prüfung.`
      };
    }

    if (Number(counts?.rejected || 0) > 0) {
      return {
        label: "Keine Rückmeldung erforderlich",
        detail: "Abgelehnte Einreichungen bleiben zur Nachverfolgung sichtbar."
      };
    }

    return {
      label: "Keine Rückmeldung erforderlich",
      detail: "Aktuell ist keine Rückmeldung von dir erforderlich."
    };
  }

  function formatOrganizerCountsLine(counts) {
    const parts = [
      `${formatInteger(counts?.visible || 0)} sichtbar`,
      `${formatInteger(counts?.review || 0)} in Prüfung`,
    ];

    if (Number(counts?.rejected || 0) > 0) {
      parts.push(`${formatInteger(counts.rejected)} abgelehnt`);
    }

    parts.push(Number(counts?.paymentOpen || 0) > 0
      ? `${formatInteger(counts.paymentOpen)} Zahlung offen`
      : "keine Zahlung offen");

    return parts.join(" · ");
  }

  function organizerHasEventImpactContext({ activeSubscriptions, subscriptionPlanKey, defaultPlanKey, submissions, impactSummary }) {
    const planKeys = new Set(
      (Array.isArray(activeSubscriptions) ? activeSubscriptions : [])
        .map((item) => safeText(item?.plan_key).toLowerCase())
        .filter(Boolean)
    );
    [subscriptionPlanKey, defaultPlanKey].forEach((key) => {
      const normalized = safeText(key).toLowerCase();
      if (normalized) planKeys.add(normalized);
    });

    const hasEventPlan = ["starter", "active", "unlimited"].some((key) => planKeys.has(key));
    const hasEventSubmission = (Array.isArray(submissions) ? submissions : [])
      .some((item) => safeText(item?.submission_kind || "event").toLowerCase() !== "activity");
    const metrics = impactSummary?.metrics || {};
    const hasImpactMetrics = metricValue(metrics, "total_interactions") > 0 ||
      metricValue(metrics, "detail_views") > 0 ||
      metricValue(metrics, "website_clicks") > 0 ||
      metricValue(metrics, "maps_clicks") > 0 ||
      impactShareTotal(metrics) > 0;
    const hasImpactItems = (Array.isArray(impactSummary?.items) ? impactSummary.items : [])
      .some((item) => metricValue(item?.metrics, "total_interactions") > 0);

    return hasEventPlan || hasEventSubmission || hasImpactMetrics || hasImpactItems;
  }

  function organizerSubmissionGroupKey(submission) {
    const status = safeText(submission?.status).toLowerCase();
    if (status === "payment_released" || status === "checkout_started" || status === "draft") return "action";
    if (status === "pending_review" || status === "paid" || status === "in_review") return "review";
    if (status === "approved") return "published";
    if (status === "rejected") return "rejected";
    return "other";
  }

  function buildOrganizerSubmissionGroups(submissions) {
    const order = [
      ["action", "Aktion nötig"],
      ["review", "In Prüfung"],
      ["published", "Veröffentlicht"],
      ["rejected", "Abgelehnt"],
      ["other", "Weitere"]
    ];
    const buckets = new Map(order.map(([key, label]) => [key, { key, label, items: [] }]));

    (Array.isArray(submissions) ? submissions : []).forEach((item) => {
      const key = organizerSubmissionGroupKey(item);
      (buckets.get(key) || buckets.get("other")).items.push(item);
    });

    return order.map(([key]) => buckets.get(key)).filter((group) => group.items.length > 0);
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
        <span class="organizer-submission-summary-item" role="listitem">
          <span class="organizer-submission-summary-label">${escapeHtml(label)}</span>
          <span class="organizer-submission-summary-value">${escapeHtml(value)}</span>
        </span>
      `).join("");

      nextStep.textContent = "Öffne unten die Einreichung, um Status, Angaben und mögliche Änderungen zu prüfen.";
      return;
    }

    const counts = context?.counts || getOrganizerDashboardCounts(submissions);
    const status = buildOrganizerDashboardStatus(counts);

    summary.textContent = formatOrganizerCountsLine(counts);

    overview.innerHTML = "";
    overview.hidden = true;
    overview.setAttribute("hidden", "");

    nextStep.textContent = status.detail;
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_V2_STRUCTURE_HELPERS_V1 === */

  function renderImpactExplainerDetails() {
    return `
      <details class="organizer-impact-explainer">
        <summary>Was wird gezählt?</summary>
        <div class="organizer-impact-explainer__body">
          <p>Gemessene Aktionen zeigen, wie Nutzer mit deinen veröffentlichten Inhalten auf Bocholt erleben interagieren.</p>
          <dl>
            <div><dt>Detail-Aufrufe</dt><dd>Nutzer öffnen Veranstaltungs- oder Aktivitätsdetails.</dd></div>
            <div><dt>Website/Info</dt><dd>Nutzer klicken zur Website, Veranstaltungsseite oder Ticketseite.</dd></div>
            <div><dt>Route/Maps</dt><dd>Nutzer öffnen die Route.</dd></div>
            <div><dt>Teilungen</dt><dd>Nutzer teilen oder kopieren den Link.</dd></div>
          </dl>
          <p>Die Werte sind gemessene Aktionen, keine eindeutigen Personen, keine Ticketverkäufe und keine Besucherzahlen vor Ort.</p>
        </div>
      </details>
    `;
  }

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_IMPACT_RENDERER_V3_SCOPE_SELECTOR | Zweck: rendert Gesamtwirkung und objektgenaue Einzelwirkung in einer zentralen Auswahl, ohne zusätzliche Scrolllisten zu erzeugen; Umfang: Impact-Helper und renderOrganizerImpactCard === */
  function impactTotalInteractions(metrics) {
    return metricValue(metrics, "total_interactions");
  }

  function impactWebsiteOrInfo(metrics) {
    return metricValue(metrics, "website_clicks") + metricValue(metrics, "organizer_cta_clicks");
  }

  function impactRouteOrMaps(metrics) {
    return metricValue(metrics, "maps_clicks") + metricValue(metrics, "location_clicks");
  }

  function createEmptyImpactMetrics() {
    return {
      website_clicks: 0,
      maps_clicks: 0,
      location_clicks: 0,
      organizer_cta_clicks: 0,
      share_clicks: 0,
      copy_link_clicks: 0,
      detail_views: 0,
      total_interactions: 0
    };
  }

  function normalizeImpactScopeMetrics(metrics) {
    return metrics && typeof metrics === "object" ? metrics : createEmptyImpactMetrics();
  }

  function buildImpactScopeKey(entityType, entityId) {
    const type = safeText(entityType || "content").toLowerCase() || "content";
    const id = safeText(entityId);
    return id ? `${type}:${id}` : "";
  }

  function formatImpactScopeKindLabel(entityType) {
    const type = safeText(entityType).toLowerCase();
    if (type === "activity") return "Aktivität";
    if (type === "event") return "Veranstaltung";
    return "Inhalt";
  }

  function buildOrganizerImpactScopes(data) {
    const impact = data?.impact_summary || {};
    const submissions = Array.isArray(data?.recent_submissions) ? data.recent_submissions : [];
    const items = new Map();

    const addItem = ({ entityType, entityId, title, metrics, sourceIndex = 0 }) => {
      const key = buildImpactScopeKey(entityType, entityId);
      if (!key) return;

      const current = items.get(key) || {
        key,
        entityType: safeText(entityType).toLowerCase() || "content",
        entityId: safeText(entityId),
        title: "",
        metrics: createEmptyImpactMetrics(),
        sourceIndex
      };

      const normalizedTitle = safeText(title);
      if (normalizedTitle && (!current.title || impactTotalInteractions(metrics) >= impactTotalInteractions(current.metrics))) {
        current.title = normalizedTitle;
      }

      const normalizedMetrics = normalizeImpactScopeMetrics(metrics);
      if (impactTotalInteractions(normalizedMetrics) >= impactTotalInteractions(current.metrics)) {
        current.metrics = normalizedMetrics;
      }

      current.sourceIndex = Math.min(current.sourceIndex, sourceIndex);
      items.set(key, current);
    };

    (Array.isArray(impact?.items) ? impact.items : []).forEach((item, index) => {
      addItem({
        entityType: item?.entity_type,
        entityId: item?.entity_id,
        title: item?.entity_title,
        metrics: item?.metrics,
        sourceIndex: index
      });
    });

    submissions.forEach((submission, index) => {
      if (!isOrganizerPublishedSubmission(submission)) return;

      const entityType = safeText(submission?.submission_kind || "event").toLowerCase() === "activity" ? "activity" : "event";
      addItem({
        entityType,
        entityId: submission?.impact_entity_id || `submission-${safeText(submission?.id)}`,
        title: submission?.title,
        metrics: submission?.impact_metrics,
        sourceIndex: 1000 + index
      });
    });

    const itemScopes = Array.from(items.values())
      .map((item) => ({
        key: `item:${item.key}`,
        label: safeText(item.title) || formatImpactScopeKindLabel(item.entityType),
        meta: formatImpactScopeKindLabel(item.entityType),
        metrics: normalizeImpactScopeMetrics(item.metrics),
        isTotal: false,
        sourceIndex: item.sourceIndex
      }))
      .sort((a, b) => {
        const totalDiff = impactTotalInteractions(b.metrics) - impactTotalInteractions(a.metrics);
        if (totalDiff !== 0) return totalDiff;
        return a.sourceIndex - b.sourceIndex || a.label.localeCompare(b.label, "de");
      });

    return [
      {
        key: "total",
        label: "Gesamtwirkung deiner Inhalte",
        meta: "Alle veröffentlichten Inhalte",
        metrics: normalizeImpactScopeMetrics(impact?.metrics),
        isTotal: true,
        sourceIndex: -1
      },
      ...itemScopes
    ];
  }

  function renderImpactScopeControl(scopes, selectedKey) {
    if (!Array.isArray(scopes) || scopes.length <= 1) {
      return `<span class="organizer-impact-scope-static">Gesamtwirkung deiner Inhalte</span>`;
    }

    return `
      <label class="organizer-impact-scope" for="organizer-impact-scope-select">
        <span class="organizer-impact-scope__label">Auswertung</span>
        <select class="organizer-impact-scope__select" id="organizer-impact-scope-select">
          ${scopes.map((scope) => `
            <option value="${escapeHtml(scope.key)}" ${scope.key === selectedKey ? "selected" : ""}>
              ${escapeHtml(scope.label)}
            </option>
          `).join("")}
        </select>
      </label>
    `;
  }

  function renderImpactScopeMetrics(scope) {
    const metrics = normalizeImpactScopeMetrics(scope?.metrics);
    const total = impactTotalInteractions(metrics);
    const shares = impactShareTotal(metrics);
    const note = total > 0
      ? (scope?.isTotal ? "Gemessene Aktionen deiner veröffentlichten Inhalte im aktuellen Zeitraum." : "Gemessene Aktionen dieses Inhalts im aktuellen Zeitraum.")
      : (scope?.isTotal ? "Messung aktiv. Noch keine Aktionen im aktuellen Zeitraum." : "Messung aktiv. Noch keine Aktionen für diesen Inhalt im aktuellen Zeitraum.");

    return `
      <span class="organizer-impact-total" aria-label="Gemessene Aktionen im aktuellen Zeitraum">
        <strong class="organizer-impact-total__value">${escapeHtml(formatInteger(total))}</strong>
        <span class="organizer-impact-total__suffix">gemessene Aktionen</span>
      </span>
      ${renderImpactMetricRows([
        ["Detail-Aufrufe", metricValue(metrics, "detail_views")],
        ["Website/Info", impactWebsiteOrInfo(metrics)],
        ["Route/Maps", impactRouteOrMaps(metrics)],
        ["Teilungen", shares]
      ])}
      <p class="organizer-impact-status-note">${escapeHtml(note)}</p>
      ${renderImpactExplainerDetails()}
    `;
  }

  function renderOrganizerImpactCard(data, options = {}) {
    const card = document.getElementById("organizer-dashboard-impact-card");
    const periodNode = document.getElementById("organizer-impact-period");
    const metricsNode = document.getElementById("organizer-impact-metrics");
    const noteNode = document.getElementById("organizer-impact-note");

    if (!card || !periodNode || !metricsNode || !noteNode) return;

    const isSingleStatusView = Boolean(options?.isSingleStatusView);
    const hasEventImpactContext = Boolean(options?.hasEventImpactContext);

    if (isSingleStatusView || !hasEventImpactContext) {
      card.hidden = true;
      card.setAttribute("hidden", "");
      return;
    }

    const impact = data?.impact_summary || {};
    const scopes = buildOrganizerImpactScopes(data);
    const initialKey = "total";

    card.hidden = false;
    card.removeAttribute("hidden");
    periodNode.innerHTML = `
      ${renderImpactScopeControl(scopes, initialKey)}
      <span class="organizer-impact-period-label">${escapeHtml(formatImpactPeriod(impact?.period))}</span>
    `;
    noteNode.textContent = "";
    noteNode.hidden = true;

    const renderSelectedScope = (scopeKey) => {
      const selected = scopes.find((scope) => scope.key === scopeKey) || scopes[0];
      metricsNode.innerHTML = renderImpactScopeMetrics(selected);
    };

    const select = periodNode.querySelector("#organizer-impact-scope-select");
    if (select) {
      select.addEventListener("change", () => {
        renderSelectedScope(select.value);
      });
    }

    if (impact?.status === "not_configured" || impact?.status === "not_available") {
      metricsNode.innerHTML = `
        <p class="organizer-impact-status-note">${escapeHtml(impact?.message || "Die Wirkungsauswertung ist für diesen Bereich noch nicht verfügbar.")}</p>
        ${renderImpactExplainerDetails()}
      `;
      return;
    }

    renderSelectedScope(initialKey);
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_IMPACT_RENDERER_V3_SCOPE_SELECTOR === */

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
    const dashboardCounts = getOrganizerDashboardCounts(submissions);
    const dashboardStatus = buildOrganizerDashboardStatus(dashboardCounts);

    const latestSubmission = submissions[0] || null;
    const defaultPlanKey = safeText(organizer.default_plan_key).toLowerCase();
    const subscriptionPlanKey = safeText(subscription?.plan_key).toLowerCase();
    const hasManageableSubscription = ["starter", "active", "unlimited", "activity_basic", "activity_plus"].includes(subscriptionPlanKey);
    const effectivePlanKey = hasManageableSubscription ? subscriptionPlanKey : (defaultPlanKey || "single");
    const isActivityPlanView = ["activity_basic", "activity_plus"].includes(effectivePlanKey);
    const activePlanKeys = (Array.isArray(activeSubscriptions) ? activeSubscriptions : [])
      .map((item) => safeText(item?.plan_key).toLowerCase());
    const hasEventPresencePlan = activePlanKeys.some((key) => ["starter", "active", "unlimited"].includes(key)) ||
      ["starter", "active", "unlimited"].includes(subscriptionPlanKey) ||
      ["starter", "active", "unlimited"].includes(defaultPlanKey);
    const hasActivityPresencePlan = activePlanKeys.some((key) => key === "activity_basic" || key === "activity_plus") ||
      ["activity_basic", "activity_plus"].includes(subscriptionPlanKey) ||
      ["activity_basic", "activity_plus"].includes(defaultPlanKey);
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
    const submissionsToggle = document.getElementById("organizer-dashboard-submissions-toggle");
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
        lead.textContent = dashboardStatus.label;
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
      if (isActivityPlanView && !hasEventPresencePlan) {
        dashboardPrimaryCta.href = "/aktivitaeten/sichtbar-werden/";
        dashboardPrimaryCta.textContent = "Weitere Aktivität einreichen";
      } else {
        const prefilledPlan = ["starter", "active", "unlimited"].includes(effectivePlanKey)
          ? effectivePlanKey
          : (hasEventPresencePlan ? "starter" : "single");

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

    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_MULTI_TARIFF_OVERVIEW_V4_COMPACT_DETAILS | Zweck: verdichtet Tarif und Nutzung mobil-first und legt Details in ein aufklappbares Segment; Umfang: komplette Tarif-/Kontingentübersicht in renderDashboard === */
    const tariffRows = buildOrganizerTariffRows(activeSubscriptions);
    const includedRows = buildOrganizerIncludedRows(quotaByPlan);
    const includedCompactRows = buildOrganizerIncludedCompactRows(quotaByPlan);

    const monthlyTotal = safeText(billingSummary?.monthly_total_label);

    if (quotaHead) {
      quotaHead.textContent = isSingleStatusView ? "Status" : "Tarif & Nutzung";
    }

    if (quotaPeriod) {
      quotaPeriod.hidden = false;

      if (isSingleStatusView) {
        quotaPeriod.textContent = latestSubmission
          ? `Termin: ${latestDateText}`
          : "Veranstaltung einreichen";
      } else {
        const compactPrice = monthlyTotal || "–";
        const compactUsage = includedCompactRows.length > 0
          ? includedCompactRows.join(" · ")
          : (quota.has_unlimited ? `${isActivityPlanView ? "Aktivitäten" : "Events"} unbegrenzt` : `${Number(quota.consumed_total || 0)} von ${Number(quota.included_total || 0)} genutzt`);
        quotaPeriod.innerHTML = `
          <span class="organizer-tariff-compact" aria-label="Tarif und Nutzung kompakt">
            <span class="organizer-tariff-compact__amount">${escapeHtml(compactPrice)}</span>
            <span class="organizer-tariff-compact__usage">${escapeHtml(compactUsage)}</span>
          </span>
          <details class="organizer-tariff-details">
            <summary>Details anzeigen</summary>
            ${tariffRows.length > 0 ? `
              <span class="organizer-tariff-section-title">Aktive Tarife</span>
              <span class="organizer-tariff-table" role="list">
                ${tariffRows.map((row) => `
                  <span class="organizer-tariff-row" role="listitem">
                    <span class="organizer-tariff-label">${escapeHtml(row.label)}</span>
                    <span class="organizer-tariff-price">${escapeHtml(row.amount)}</span>
                  </span>
                `).join("")}
              </span>
            ` : ""}
            ${includedRows.length > 0 ? `
              <span class="organizer-tariff-section-title">Enthalten</span>
              <span class="organizer-tariff-bullets">
                ${includedRows.map((line) => `
                  <span class="organizer-tariff-bullet">${escapeHtml(line)}</span>
                `).join("")}
              </span>
            ` : ""}
            <span class="organizer-tariff-hint">Gezählt wird erst nach redaktioneller Freigabe.</span>
          </details>
        `;
      }
    }

    if (quotaChange) {
      quotaChange.textContent = "";
      quotaChange.hidden = true;
    }

    if (quotaSummary) {
      if (isSingleStatusView) {
        quotaSummary.hidden = false;
        quotaSummary.textContent = latestSubmission
          ? "Einreichung erhalten. Die Veröffentlichung erfolgt nach Prüfung."
          : "Noch keine Einreichung gefunden.";
      } else {
        quotaSummary.textContent = "";
        quotaSummary.hidden = true;
      }
    }

    if (quotaRemaining) {
      if (isSingleStatusView) {
        quotaRemaining.hidden = false;
        quotaRemaining.textContent = "Für diese Einreichung ist keine Mitgliedschaft aktiv.";
      } else {
        quotaRemaining.textContent = "";
        quotaRemaining.hidden = true;
      }
    }
    /* === END BLOCK: ORGANIZER_DASHBOARD_MULTI_TARIFF_OVERVIEW_V4_COMPACT_DETAILS === */

    renderOrganizerSubmissionsSummary({
      isSingleStatusView,
      isActivityPlanView,
      activeSubscriptions,
      submissions,
      latestSubmission,
      counts: dashboardCounts
    });

    const hasEventImpactContext = organizerHasEventImpactContext({
      activeSubscriptions,
      subscriptionPlanKey,
      defaultPlanKey,
      submissions,
      impactSummary: data?.impact_summary || {}
    });

    renderOrganizerImpactCard(data, {
      isSingleStatusView,
      hasEventImpactContext
    });

/* === BEGIN BLOCK: ORGANIZER_DASHBOARD_CURRENT_SUBMISSIONS_INLINE_EDIT_V2_VALUE_CENTER_COPY | Zweck: zeigt Einreichungen mit Status, Details und Änderungsmöglichkeit vor Veröffentlichung; Umfang: Überschrift, Empty-State und komplette Rendering-/Edit-Logik der Einreichungsliste in renderDashboard === */
if (submissionsHead) {
  submissionsHead.textContent = isSingleStatusView ? "Meine Einreichung" : "Einreichungen & Veröffentlichungen";
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
  submissionsList.hidden = false;
  submissionsEmpty.textContent = isSingleStatusView
    ? "Keine Einreichung gefunden."
    : "Noch keine aktuellen Einreichungen vorhanden.";

  const setSubmissionsListOpen = (isOpen) => {
    submissionsList.hidden = !isOpen;
    if (!submissionsToggle) return;

    submissionsToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    submissionsToggle.textContent = isOpen
      ? "Einreichungen ausblenden"
      : "Einreichungen anzeigen";
  };

  if (!submissions.length) {
    submissionsEmpty.hidden = false;
    if (submissionsToggle) submissionsToggle.hidden = true;
  } else {
    submissionsEmpty.hidden = true;

    const visibleSubmissions = submissions.slice(0, 10);
    const submissionGroups = isSingleStatusView
      ? [{ key: "single", label: "", items: visibleSubmissions }]
      : buildOrganizerSubmissionGroups(visibleSubmissions);
    let renderedSubmissionIndex = 0;

    submissionGroups.forEach((group) => {
      if (safeText(group.label)) {
        const heading = document.createElement("h3");
        heading.className = "organizer-submission-group-title";
        heading.textContent = group.label;
        submissionsList.appendChild(heading);
      }

      group.items.forEach((submission) => {
        const index = renderedSubmissionIndex++;
      const titleText = safeText(submission.title) || "Ohne Titel";
      const statusText = formatSubmissionStatusLabel(submission);
      const metaDateText = formatSubmissionMetaDate(submission);
      const statusBadgeText = formatSubmissionBadgeLabel(submission);
      const statusBadgeTone = formatSubmissionBadgeTone(submission);
      const kindText = formatSubmissionKindLabel(submission);
      const metaText = metaDateText && metaDateText !== "–"
        ? `${kindText} · ${metaDateText}`
        : kindText;
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
          <span class="organizer-submission-row__head">
            <strong class="organizer-submission-row__title">${escapeHtml(titleText)}</strong>
            <span class="organizer-submission-status-badge organizer-submission-status-badge--${escapeHtml(statusBadgeTone)}">${escapeHtml(statusBadgeText)}</span>
          </span>
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
          ${renderSubmissionImpactHtml(submission)}
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
    });

    hydrateIcons(submissionsList);

    const hasActionItems = submissions.some((item) => organizerSubmissionGroupKey(item) === "action");
    const defaultOpen = Boolean(isSingleStatusView || hasActionItems);

    if (submissionsToggle) {
      submissionsToggle.hidden = Boolean(isSingleStatusView);
      submissionsToggle.onclick = () => {
        const isExpanded = submissionsToggle.getAttribute("aria-expanded") === "true";
        setSubmissionsListOpen(!isExpanded);
      };
    }

    setSubmissionsListOpen(defaultOpen);
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
