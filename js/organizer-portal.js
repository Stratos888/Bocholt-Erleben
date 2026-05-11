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
      unlimited: "Dauerhaft"
    })[key] || "–";
  }

  function formatStatusLabel(status) {
    const key = safeText(status).toLowerCase();
    return ({
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

  function ensureDashboardLogoutLink() {
    const navigation = pageRoot.querySelector('nav[aria-label="Veranstalter-Navigation"]');

    if (!navigation || document.getElementById("organizer-dashboard-logout")) {
      return;
    }

    const logoutButton = document.createElement("button");
    logoutButton.type = "button";
    logoutButton.id = "organizer-dashboard-logout";
    logoutButton.className = "content-link";
    logoutButton.innerHTML = `
      <span class="content-link__label">Abmelden</span>
      <span class="content-link__chevron" data-ui-icon="chevron-right" aria-hidden="true"></span>
    `;

    logoutButton.addEventListener("click", () => {
      void logoutOrganizerPortal(logoutButton);
    });

    navigation.insertBefore(logoutButton, navigation.firstElementChild);
    hydrateIcons(logoutButton);
  }

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
      if (loginNote) {
        loginNote.textContent = "Der Zugangslink wird geprüft. Danach öffnen wir automatisch den Status deiner Einreichung oder deinen Veranstalterbereich.";
      }

      try {
        await consumeMagicLink(token);
        window.location.replace("/fuer-veranstalter/dashboard/");
        return;
      } catch (error) {
        console.warn("Organizer portal: magic link consume failed.", error);

        try {
          await tryLoadPortalState();
          window.location.replace("/fuer-veranstalter/dashboard/");
          return;
        } catch (_ignored) {
          setLoginResult(
            "Der Zugangslink konnte nicht eingelöst werden. Bitte fordere einen neuen Zugangslink an.",
            [{ href: "/fuer-veranstalter/login/", label: "Neuen Zugangslink anfordern", icon: "chevron-right" }]
          );
        }
      }
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

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_AREA_SPLIT_COPY_V1 | Zweck: rendert dieselbe technische Bereichsroute je nach Datenlage als Meine Einreichung oder Mein Veranstalterbereich und ersetzt öffentliche Kontingent-/Abo-Sprache; Umfang: komplette renderDashboard-Funktion === */
   function renderDashboard(data) {
    const authRequiredCard = document.getElementById("organizer-dashboard-auth-required");
    const summaryGrid = document.getElementById("organizer-dashboard-summary");
    const submissionsCard = document.getElementById("organizer-dashboard-submissions-card");

    if (authRequiredCard) {
      authRequiredCard.hidden = true;
      authRequiredCard.setAttribute("hidden", "");
    }
    if (summaryGrid) summaryGrid.hidden = false;
    if (submissionsCard) submissionsCard.hidden = false;

    const organizer = data?.organizer || {};
    const quota = data?.quota || {};
    const subscription = data?.subscription || null;
    const submissions = Array.isArray(data?.recent_submissions) ? data.recent_submissions : [];

    const latestSubmission = submissions[0] || null;
    const defaultPlanKey = safeText(organizer.default_plan_key).toLowerCase();
    const subscriptionPlanKey = safeText(subscription?.plan_key).toLowerCase();
    const hasManageableSubscription = ["starter", "active", "unlimited"].includes(subscriptionPlanKey);
    const effectivePlanKey = hasManageableSubscription ? subscriptionPlanKey : (defaultPlanKey || "single");

    const latestTitleText = latestSubmission
      ? safeText(latestSubmission.title) || "Ohne Titel"
      : "Noch keine Einreichung";
    const latestStatusText = latestSubmission
      ? formatStatusLabel(latestSubmission.status)
      : "Noch kein Status";
    const latestDateText = latestSubmission
      ? formatDate(latestSubmission.start_date)
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
    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_HERO_NOTE_COPY_V3 | Zweck: entfernt den Dashboard-Kicker, zeigt oben nur den Veranstalternamen und hält den Hero knapp auf Mitgliedschaft und Einreichungen ausgerichtet; Umfang: Kicker-, Titel-, Lead- und Note-Texte in renderDashboard === */
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
        lead.textContent = "Hier siehst du deine Mitgliedschaft und letzte Einreichungen.";
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
    /* === END BLOCK: ORGANIZER_DASHBOARD_HERO_NOTE_COPY_V3 === */

    if (dashboardPrimaryCta) {
      const prefilledPlan = ["starter", "active", "unlimited"].includes(effectivePlanKey)
        ? effectivePlanKey
        : "single";

      dashboardPrimaryCta.href = `/events-veroeffentlichen/einreichen/?plan=${encodeURIComponent(prefilledPlan)}`;
      dashboardPrimaryCta.textContent = isSingleStatusView ? "Weitere Veranstaltung einreichen" : "Neue Veranstaltung einreichen";
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

    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_ACCOUNT_CARD_COPY_V3 | Zweck: hält Meine Angaben auf Stammdaten begrenzt und verschiebt Mitgliedschaftsstatus vollständig in die Mitgliedschaftsübersicht; Umfang: Account-Karten-Texte in renderDashboard === */
    if (accountHead) {
      accountHead.textContent = isSingleStatusView ? "Einreichung" : "Meine Angaben";
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
    /* === END BLOCK: ORGANIZER_DASHBOARD_ACCOUNT_CARD_COPY_V3 === */

/* === BEGIN BLOCK: ORGANIZER_DASHBOARD_MEMBERSHIP_OVERVIEW_COPY_V5 | Zweck: hält Mitgliedschaftsstatus als ruhige Datenzeilen und vermeidet headlineartige Wertzeilen; Umfang: komplette Quota-/Mitgliedschaftsübersicht in renderDashboard === */
if (quotaHead) {
  quotaHead.textContent = isSingleStatusView ? "Status" : "Übersicht Mitgliedschaft";
}

if (quotaPeriod) {
  if (isSingleStatusView) {
    quotaPeriod.textContent = latestSubmission
      ? `Termin: ${latestDateText}`
      : "Veranstaltung einreichen";
  } else {
    quotaPeriod.textContent = `Tarif: ${formatPlanLabel(subscriptionPlanKey || effectivePlanKey)}`;
  }
}

if (quotaChange) {
  if (isSingleStatusView) {
    quotaChange.textContent = "";
    quotaChange.hidden = true;
  } else {
    const end = formatDate(safeText(quota.current_period_end || subscription?.current_period_end).split(" ")[0]);
    const subscriptionStatus = safeText(subscription?.status).toLowerCase();
    const cancelAtPeriodEnd = Number(subscription?.cancel_at_period_end || 0) === 1;
    const pendingPlanKey = safeText(subscription?.pending_plan_key).toLowerCase();
    const pendingDate = formatDate(safeText(subscription?.pending_change_effective_at).split(" ")[0]);

    if (cancelAtPeriodEnd && end !== "–") {
      quotaChange.textContent = `Mitgliedschaft endet zum ${end}`;
      quotaChange.hidden = false;
    } else if (pendingPlanKey && pendingPlanKey !== subscriptionPlanKey && pendingDate !== "–") {
      quotaChange.textContent = `Geplanter Tarifwechsel: ${formatPlanLabel(pendingPlanKey)} ab ${pendingDate}`;
      quotaChange.hidden = false;
    } else if (subscriptionStatus === "past_due") {
      quotaChange.textContent = "Zahlungsproblem – bitte Mitgliedschaft verwalten";
      quotaChange.hidden = false;
    } else {
      quotaChange.textContent = "";
      quotaChange.hidden = true;
    }
  }
}

if (quotaSummary) {
  if (isSingleStatusView) {
    quotaSummary.textContent = latestSubmission
      ? "Einreichung erhalten. Die Veröffentlichung erfolgt nach Prüfung."
      : "Noch keine Einreichung gefunden.";
  } else if (quota.has_unlimited) {
    quotaSummary.textContent = `Veröffentlichte Termine: ${Number(quota.consumed_total || 0)} im aktuellen Zeitraum`;
  } else {
    quotaSummary.textContent = `Veröffentlichte Termine: ${Number(quota.consumed_total || 0)} von ${Number(quota.included_total || 0)} im aktuellen Zeitraum`;
  }
}

if (quotaRemaining) {
  if (quota.has_unlimited) {
    quotaRemaining.textContent = "Weitere Einreichungen sind möglich. Gezählt werden veröffentlichte Termine nach Freigabe.";
  } else if (isSingleStatusView) {
    quotaRemaining.textContent = "Für diese Einreichung ist keine Mitgliedschaft aktiv.";
  } else {
    quotaRemaining.textContent = `Noch verfügbar: ${Number(quota.remaining_total || 0)} veröffentlichte Termine.`;
  }
}
/* === END BLOCK: ORGANIZER_DASHBOARD_MEMBERSHIP_OVERVIEW_COPY_V5 === */

/* === BEGIN BLOCK: ORGANIZER_DASHBOARD_CURRENT_SUBMISSIONS_INLINE_DETAILS_V2 | Zweck: zeigt aktuelle/relevante Einreichungen sortiert von neu nach alt und öffnet Details inline statt externe Links direkt zu öffnen; Umfang: Überschrift, Empty-State und komplette Rendering-Logik der Einreichungsliste in renderDashboard === */
if (submissionsHead) {
  submissionsHead.textContent = isSingleStatusView ? "Meine Einreichung" : "Aktuelle Einreichungen";
}

if (submissionsList && submissionsEmpty) {
  submissionsList.innerHTML = "";
  submissionsEmpty.textContent = isSingleStatusView
    ? "Keine Einreichung gefunden."
    : "Keine aktuellen Einreichungen gefunden.";

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
      const eventDateText = formatDate(submission.start_date);
      const locationText = safeText(submission.location_name);
      const eventUrl = normalizeExternalUrl(submission.event_url);
      const detailId = `organizer-submission-detail-${index}`;

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
        <dl class="organizer-submission-detail__facts">
          ${eventDateText !== "–" ? `<div><dt>Termin</dt><dd>${escapeHtml(eventDateText)}</dd></div>` : ""}
          ${locationText ? `<div><dt>Ort</dt><dd>${escapeHtml(locationText)}</dd></div>` : ""}
        </dl>
        ${eventUrl ? `
          <a class="organizer-submission-detail__link" href="${escapeHtml(eventUrl)}" target="_blank" rel="noopener noreferrer">
            Eingereichten Link öffnen
            <span data-ui-icon="external" aria-hidden="true"></span>
          </a>
        ` : ""}
      `;

      row.addEventListener("click", () => {
        const isExpanded = row.getAttribute("aria-expanded") === "true";
        row.setAttribute("aria-expanded", isExpanded ? "false" : "true");
        detail.hidden = isExpanded;
      });

      submissionsList.appendChild(row);
      submissionsList.appendChild(detail);
    });

    hydrateIcons(submissionsList);
  }
}
/* === END BLOCK: ORGANIZER_DASHBOARD_CURRENT_SUBMISSIONS_INLINE_DETAILS_V2 === */
  }
  /* === END BLOCK: ORGANIZER_DASHBOARD_AREA_SPLIT_COPY_V1 === */
  /* === BEGIN BLOCK: ORGANIZER_PORTAL_DASHBOARD_HANDLER_LOGOUT_V2 | Zweck: laedt Dashboard-State und aktiviert bei gueltiger Session den Logout-Link; Umfang: komplette handleDashboardPage-Funktion === */
  async function handleDashboardPage() {
    try {
      const result = await tryLoadPortalState();
      renderDashboard(result?.data || {});
      ensureDashboardLogoutLink();
    } catch (_error) {
      const authRequiredCard = document.getElementById("organizer-dashboard-auth-required");
      const summaryGrid = document.getElementById("organizer-dashboard-summary");
      const submissionsCard = document.getElementById("organizer-dashboard-submissions-card");

      if (summaryGrid) summaryGrid.hidden = true;
      if (submissionsCard) submissionsCard.hidden = true;

      if (authRequiredCard) {
        authRequiredCard.hidden = false;
        authRequiredCard.removeAttribute("hidden");
      }
    }
  }
  /* === END BLOCK: ORGANIZER_PORTAL_DASHBOARD_HANDLER_LOGOUT_V2 === */

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
