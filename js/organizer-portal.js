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
        `Request failed with status ${response.status}`;

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
      unlimited: "Unbegrenzt"
    })[key] || "–";
  }

  function formatStatusLabel(status) {
    const key = safeText(status).toLowerCase();
    return ({
      draft: "Entwurf",
      checkout_started: "Zahlung gestartet",
      paid: "Bezahlt",
      in_review: "In Prüfung",
      approved: "Freigegeben",
      rejected: "Abgelehnt"
    })[key] || key || "–";
  }

  function formatSubmissionStatusLabel(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();
    const paymentKind = safeText(submission?.payment_kind).toLowerCase();

    if (statusKey === "paid" && paymentKind === "subscription") {
      return "Event eingereicht";
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

  function formatSubmissionMetaDate(submission) {
    const statusKey = safeText(submission?.status).toLowerCase();

    if (statusKey === "approved") {
      return formatDate(submission?.start_date);
    }

    return formatDate(submission?.created_at || submission?.updated_at || submission?.start_date);
  }

  function hydrateIcons(root = document) {
    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }
  }

  /* === BEGIN BLOCK: ORGANIZER_PORTAL_BILLING_PORTAL_HELPERS_V1 | Zweck: laedt Portal-State und oeffnet bei Bedarf das Stripe Billing Portal; Umfang: API-Helfer fuer Dashboard/Portal === */
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

  async function openBillingPortal(trigger) {
    const button = trigger instanceof HTMLElement ? trigger : null;

    if (button && !button.dataset.defaultLabel) {
      button.dataset.defaultLabel = button.textContent || "Tarif ändern oder Abo kündigen";
    }

    if (button) {
      button.disabled = true;
      button.textContent = "Weiterleitung wird vorbereitet …";
    }

    try {
      const result = await createBillingPortalSession();
      const redirectUrl = safeText(result?.data?.redirect_url);

      if (!redirectUrl) {
        throw new Error("Die Abo-Verwaltung konnte gerade nicht geöffnet werden.");
      }

      window.location.href = redirectUrl;
    } catch (error) {
      window.alert(safeText(error?.message) || "Die Abo-Verwaltung konnte gerade nicht geöffnet werden.");

      if (button) {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "Tarif ändern oder Abo kündigen";
      }
    }
  }
  /* === END BLOCK: ORGANIZER_PORTAL_BILLING_PORTAL_HELPERS_V1 === */

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
        loginSubmit.dataset.defaultLabel = loginSubmit.textContent || "Zugangslink anfordern";
      }
      loginSubmit.disabled = isSubmitting;
      loginSubmit.textContent = isSubmitting ? "Wird vorbereitet ..." : loginSubmit.dataset.defaultLabel;
    };
if (loginEmail && prefillEmail && !safeText(loginEmail.value)) {
  loginEmail.value = prefillEmail;
}

if (membershipStarted) {
  setLoginResult(
    "Deine Mitgliedschaft wurde erfolgreich gestartet. Fordere jetzt deinen Zugangslink an, um in deinen Veranstalterbereich zu kommen."
  );
}
    
    /* === BEGIN BLOCK: ORGANIZER_LOGIN_COPY_AND_FALLBACK_V2 | Zweck: enttechnisiert alle nutzerseitigen Magic-Link-Texte zu Zugangslink-Sprache und korrigiert den Fallback-Link im Login-Flow; Umfang: ersetzt den Token- und Request-Abschnitt in handleLoginPage === */
    if (token) {
      if (loginNote) {
        loginNote.textContent = "Der Zugangslink wird geprüft. Danach leiten wir direkt in deinen Statusbereich weiter.";
      }

      try {
        await consumeMagicLink(token);
        window.location.replace("/fuer-veranstalter/dashboard/");
        return;
      } catch (error) {
        try {
          await tryLoadPortalState();
          window.location.replace("/fuer-veranstalter/dashboard/");
          return;
        } catch (_ignored) {
          setLoginResult(
            safeText(error?.message) || "Der Zugangslink konnte nicht eingelöst werden.",
            [{ href: "/fuer-veranstalter/dashboard/", label: "Trotzdem zum Statusbereich", icon: "chevron-right" }]
          );
        }
      }
    }

    if (!loginForm || !loginEmail) return;

    loginForm.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (typeof loginForm.reportValidity === "function" && !loginForm.reportValidity()) {
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
            label: "Zugangslink direkt öffnen",
            icon: "chevron-right"
          });
        }

        setLoginResult(
          safeText(data.magic_link_url)
            ? "Der Zugangslink wurde erzeugt. In dieser Staging-Umgebung kannst du ihn direkt öffnen."
            : "Der Zugangslink wurde angefordert. Bitte prüfe dein Postfach.",
          links
        );
      } catch (error) {
        setLoginResult(
          safeText(error?.message) || "Der Zugangslink konnte gerade nicht angefordert werden."
        );
      } finally {
        setSubmitting(false);
      }
    });
    /* === END BLOCK: ORGANIZER_LOGIN_COPY_AND_FALLBACK_V2 === */
  }

  /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_MEMBERSHIP_ACTIONS_V2 | Zweck: zeigt im Dashboard fuer aktive Abos eine Verwaltungsaktion an und rendert das effektive Modell sauber; Umfang: komplette renderDashboard-Funktion === */
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

    const title = document.getElementById("organizer-dashboard-title");
    const lead = document.getElementById("organizer-dashboard-lead");
    const note = document.getElementById("organizer-dashboard-note");
    const accountHead = document.getElementById("organizer-dashboard-account-head");
    const accountName = document.getElementById("organizer-account-name");
    const accountEmail = document.getElementById("organizer-account-email");
    const accountPlan = document.getElementById("organizer-account-plan");
    const quotaHead = document.getElementById("organizer-dashboard-quota-head");
    const quotaPeriod = document.getElementById("organizer-quota-period");
    const quotaSummary = document.getElementById("organizer-quota-summary");
    const quotaRemaining = document.getElementById("organizer-quota-remaining");
    const submissionsHead = document.getElementById("organizer-dashboard-submissions-head");
    const submissionsList = document.getElementById("organizer-dashboard-submissions-list");
    const submissionsEmpty = document.getElementById("organizer-dashboard-submissions-empty");
    const dashboardPrimaryCta = document.getElementById("organizer-dashboard-primary-cta");
    const dashboardActions = dashboardPrimaryCta ? dashboardPrimaryCta.parentElement : null;

    if (title) {
      title.textContent = isSingleStatusView
        ? "Deine Einreichungen im Blick."
        : safeText(organizer.organization_name) || "Veranstalterbereich";
    }

    if (lead) {
      const email = safeText(organizer.email);

      if (isSingleStatusView) {
        lead.textContent = latestSubmission
          ? `${latestStatusText} · ${latestDateText}`
          : (email ? `Verknüpft mit ${email}.` : "Statusübersicht für deine Einreichungen.");
      } else {
        lead.textContent = email
          ? `Eingeloggt für ${email}.`
          : "Eingeloggt im Veranstalterbereich.";
      }
    }

    if (note) {
      const email = safeText(organizer.email);

      if (isSingleStatusView) {
        note.textContent = email
          ? `Verknüpft mit ${email}. Für Einzeltermine dient dieser Bereich nur als Statusübersicht – kein dauerhaftes Konto nötig.`
          : "Für Einzeltermine dient dieser Bereich nur als Statusübersicht – kein dauerhaftes Konto nötig.";
      } else if (hasManageableSubscription) {
        note.textContent = "Sichtbar sind Kontingent, Status, die letzten Einreichungen und die Abo-Verwaltung.";
      } else {
        note.textContent = "Sichtbar sind Kontingent, Status und die letzten Einreichungen.";
      }
    }

    if (dashboardPrimaryCta) {
      const prefilledPlan = ["starter", "active", "unlimited"].includes(effectivePlanKey)
        ? effectivePlanKey
        : "single";

      dashboardPrimaryCta.href = `/events-veroeffentlichen/einreichen/?plan=${encodeURIComponent(prefilledPlan)}`;
    }

    if (dashboardActions) {
      let manageSubscriptionButton = document.getElementById("organizer-dashboard-manage-subscription");

      if (hasManageableSubscription) {
        if (!manageSubscriptionButton) {
          manageSubscriptionButton = document.createElement("button");
          manageSubscriptionButton.type = "button";
          manageSubscriptionButton.id = "organizer-dashboard-manage-subscription";
          manageSubscriptionButton.className = "content-cta";
          manageSubscriptionButton.textContent = "Tarif ändern oder Abo kündigen";
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

    if (accountHead) {
      accountHead.textContent = isSingleStatusView ? "Letzter Stand" : "Veranstalter";
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
        accountPlan.textContent = latestLocationText
          ? `Ort: ${latestLocationText}`
          : `Verknüpft mit ${safeText(organizer.email) || "–"}`;
      } else if (hasManageableSubscription) {
        accountPlan.textContent = `Aktives Modell: ${formatPlanLabel(subscriptionPlanKey)}`;
      } else {
        const planLabel = formatPlanLabel(organizer.default_plan_key);
        accountPlan.textContent = `Standardmodell: ${planLabel}`;
      }
    }

    if (quotaHead) {
      quotaHead.textContent = isSingleStatusView ? "Verfügbarkeit" : "Verfügbares Kontingent";
    }

    if (quotaPeriod) {
      if (isSingleStatusView) {
        quotaPeriod.textContent = latestSubmission
          ? `Termin: ${latestDateText}`
          : "Einzeltermin";
      } else {
        const start = formatDateTime(quota.current_period_start);
        const end = formatDateTime(quota.current_period_end);
        quotaPeriod.textContent = quota.current_period_start || quota.current_period_end
          ? `Zeitraum: ${start} bis ${end}`
          : "Zeitraum: aktuell kein aktiver Abo-Zeitraum";
      }
    }

    if (quotaSummary) {
      if (isSingleStatusView) {
        const includedTotal = Number(quota.included_total || 0);
        const consumedTotal = Number(quota.consumed_total || 0);

        quotaSummary.textContent = includedTotal === 0 && consumedTotal === 0
          ? "Noch kein bezahlter Einzeltermin."
          : `Einzeltermine: ${includedTotal} · verbraucht: ${consumedTotal}`;
      } else if (quota.has_unlimited) {
        quotaSummary.textContent = `Kontingent: unbegrenzt · verbraucht: ${Number(quota.consumed_total || 0)}`;
      } else {
        quotaSummary.textContent = `Kontingent: ${Number(quota.included_total || 0)} · verbraucht: ${Number(quota.consumed_total || 0)}`;
      }
    }

    if (quotaRemaining) {
      if (quota.has_unlimited) {
        quotaRemaining.textContent = "Verbleibend: unbegrenzt";
      } else if (isSingleStatusView) {
        quotaRemaining.textContent = `Noch verfügbar: ${Number(quota.remaining_total || 0)}`;
      } else {
        quotaRemaining.textContent = `Verbleibend: ${Number(quota.remaining_total || 0)}`;
      }
    }

    if (submissionsHead) {
      submissionsHead.textContent = "Letzte Einreichungen";
    }

    if (submissionsList && submissionsEmpty) {
      submissionsList.innerHTML = "";

      if (!submissions.length) {
        submissionsEmpty.hidden = false;
      } else {
        submissionsEmpty.hidden = true;

        submissions.slice(0, 8).forEach((submission) => {
          const titleText = safeText(submission.title) || "Ohne Titel";
          const statusText = formatSubmissionStatusLabel(submission);
          const metaDateText = formatSubmissionMetaDate(submission);
          const metaText = `${statusText} · ${metaDateText}`;

          const hasEventUrl = safeText(submission.event_url) !== "";
          const row = document.createElement(hasEventUrl ? "a" : "div");
          row.className = "content-link";

          if (hasEventUrl) {
            row.href = submission.event_url;
            row.target = "_blank";
            row.rel = "noopener noreferrer";
          }

          row.innerHTML = `
            <span class="content-link__label">
              <strong>${escapeHtml(titleText)}</strong><br>
              <small>${escapeHtml(metaText)}</small>
            </span>
            <span class="content-link__chevron" data-ui-icon="${hasEventUrl ? "external" : "chevron-right"}" aria-hidden="true"></span>
          `;

          submissionsList.appendChild(row);
        });

        hydrateIcons(submissionsList);
      }
    }
  }

  async function handleDashboardPage() {
    try {
      const result = await tryLoadPortalState();
      renderDashboard(result?.data || {});
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
