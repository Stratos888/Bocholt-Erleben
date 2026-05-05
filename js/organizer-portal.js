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
        loginSubmit.dataset.defaultLabel = loginSubmit.textContent || "Zugangslink per E-Mail erhalten";
      }
      loginSubmit.disabled = isSubmitting;
      loginSubmit.textContent = isSubmitting ? "Zugangslink wird vorbereitet ..." : loginSubmit.dataset.defaultLabel;
    };

    if (loginEmail && prefillEmail && !safeText(loginEmail.value)) {
      loginEmail.value = prefillEmail;
    }

    if (membershipStarted) {
      setLoginResult(
        "Deine Mitgliedschaft wurde erfolgreich gestartet. Fordere jetzt deinen Zugangslink an, um deine Einreichung oder deinen Veranstalterbereich zu öffnen."
      );
    }

    /* === BEGIN BLOCK: ORGANIZER_LOGIN_COPY_AND_FALLBACK_V4_SYNTAX_SAFE_ACCESS_LINK | Zweck: rendert Login, Token-Einlösung und Magic-Link-Anforderung syntaktisch robust mit konkreter Einreichung-/Veranstalterbereich-Sprache; Umfang: kompletter Token- und Request-Abschnitt in handleLoginPage === */
    if (token) {
      if (loginNote) {
        loginNote.textContent = "Der Zugangslink wird geprüft. Danach zeigen wir dir automatisch deine Einreichung oder deinen Veranstalterbereich.";
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
            label: "Einreichung oder Veranstalterbereich öffnen",
            icon: "chevron-right"
          });
        }

        setLoginResult(
          safeText(data.magic_link_url)
            ? "Der Zugangslink wurde erzeugt. In dieser Staging-Umgebung kannst du deine Einreichung oder deinen Veranstalterbereich direkt öffnen."
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
    /* === END BLOCK: ORGANIZER_LOGIN_COPY_AND_FALLBACK_V4_SYNTAX_SAFE_ACCESS_LINK === */
  }

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
    const quotaSummary = document.getElementById("organizer-quota-summary");
    const quotaRemaining = document.getElementById("organizer-quota-remaining");
    const submissionsHead = document.getElementById("organizer-dashboard-submissions-head");
    const submissionsList = document.getElementById("organizer-dashboard-submissions-list");
    const submissionsEmpty = document.getElementById("organizer-dashboard-submissions-empty");
    const dashboardPrimaryCta = document.getElementById("organizer-dashboard-primary-cta");
    const dashboardActions = dashboardPrimaryCta ? dashboardPrimaryCta.parentElement : null;
    if (kicker) {
      kicker.textContent = isSingleStatusView ? "Meine Einreichung" : "Mein Veranstalterbereich";
    }

    if (title) {
      title.textContent = isSingleStatusView
        ? "Meine Einreichung"
        : safeText(organizer.organization_name) || "Mein Veranstalterbereich";
    }

    if (lead) {
      const email = safeText(organizer.email);

      if (isSingleStatusView) {
        lead.textContent = latestSubmission
          ? `${latestStatusText} · ${latestDateText}`
          : (email ? `Verknüpft mit ${email}.` : "Status deiner Einreichung.");
      } else {
        lead.textContent = email
          ? `Eingeloggt für ${email}.`
          : "Hier verwaltest du deine Mitgliedschaft und reichst neue Veranstaltungen ein.";
      }
    }

    if (note) {
      const email = safeText(organizer.email);

      if (isSingleStatusView) {
        note.textContent = email
          ? `Verknüpft mit ${email}. Für Einzeltermine zeigen wir hier nur den Stand deiner Einreichung.`
          : "Für Einzeltermine zeigen wir hier nur den Stand deiner Einreichung.";
      } else if (hasManageableSubscription) {
        note.textContent = "Sichtbar sind Mitgliedschaft, aktueller Zeitraum, veröffentlichte Termine und deine letzten Einreichungen.";
      } else {
        note.textContent = "Sichtbar sind Status und die letzten Einreichungen.";
      }
    }

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

    if (accountHead) {
      accountHead.textContent = isSingleStatusView ? "Einreichung" : "Veranstalter";
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
        const cancelAtPeriodEnd = Number(subscription?.cancel_at_period_end || 0) === 1;
        const currentPeriodEndDate = formatDate(safeText(subscription?.current_period_end).split(" ")[0]);
        const pendingPlanKey = safeText(subscription?.pending_plan_key).toLowerCase();
        const pendingDate = formatDate(safeText(subscription?.pending_change_effective_at).split(" ")[0]);

        if (cancelAtPeriodEnd && currentPeriodEndDate !== "–") {
            accountPlan.textContent = `Mitgliedschaft: ${formatPlanLabel(subscriptionPlanKey)} · endet zum ${currentPeriodEndDate}`;
        } else if (pendingPlanKey && pendingPlanKey !== subscriptionPlanKey && pendingDate !== "–") {
          accountPlan.textContent = `Mitgliedschaft: ${formatPlanLabel(subscriptionPlanKey)} · Wechsel zu ${formatPlanLabel(pendingPlanKey)} ab ${pendingDate}`;
        } else {
          accountPlan.textContent = `Mitgliedschaft: ${formatPlanLabel(subscriptionPlanKey)}`;
        }
      } else {
        const planLabel = formatPlanLabel(organizer.default_plan_key);
        accountPlan.textContent = `Modell: ${planLabel}`;
      }
    }

    if (quotaHead) {
      quotaHead.textContent = isSingleStatusView ? "Status" : "Veröffentlichte Termine";
    }

    if (quotaPeriod) {
      if (isSingleStatusView) {
        quotaPeriod.textContent = latestSubmission
          ? `Termin: ${latestDateText}`
          : "Veranstaltung einreichen";
      } else {
        const start = formatDate(safeText(quota.current_period_start || subscription?.current_period_start).split(" ")[0]);
        const end = formatDate(safeText(quota.current_period_end || subscription?.current_period_end).split(" ")[0]);
        const subscriptionStatus = safeText(subscription?.status).toLowerCase();
        const cancelAtPeriodEnd = Number(subscription?.cancel_at_period_end || 0) === 1;
        const pendingPlanKey = safeText(subscription?.pending_plan_key).toLowerCase();
        const pendingDate = formatDate(safeText(subscription?.pending_change_effective_at).split(" ")[0]);

        if (cancelAtPeriodEnd && end !== "–") {
          quotaPeriod.textContent = `Status: endet zum ${end}`;
        } else if (pendingPlanKey && pendingPlanKey !== subscriptionPlanKey && pendingDate !== "–") {
          quotaPeriod.textContent = `Status: Wechsel zu ${formatPlanLabel(pendingPlanKey)} ab ${pendingDate}`;
        } else if ((quota.current_period_start || subscription?.current_period_start) && (quota.current_period_end || subscription?.current_period_end)) {
          quotaPeriod.textContent = `Aktueller Zeitraum: ${start} bis ${end}`;
        } else if (subscriptionStatus === "active" || subscriptionStatus === "trialing") {
          quotaPeriod.textContent = "Status: aktive Mitgliedschaft";
        } else if (subscriptionStatus === "past_due") {
          quotaPeriod.textContent = "Status: Zahlungsproblem – bitte Mitgliedschaft verwalten";
        } else {
          quotaPeriod.textContent = "Status: aktuell kein aktiver Zeitraum";
        }
      }
    }

    /* === BEGIN BLOCK: ORGANIZER_DASHBOARD_APPROVED_PUBLICATION_COUNT_COPY_V1 | Zweck: macht im Dashboard klar, dass nur freigegebene Veröffentlichungen gezählt werden; Umfang: Quota-/Status-Texte in renderDashboard === */
    if (quotaSummary) {
      if (isSingleStatusView) {
        quotaSummary.textContent = latestSubmission
          ? "Einreichung erhalten. Veröffentlichung erst nach Prüfung."
          : "Noch keine Einreichung gefunden.";
      } else if (quota.has_unlimited) {
        quotaSummary.textContent = `Freigegebene Veröffentlichungen: ${Number(quota.consumed_total || 0)} · laufendes Programm`;
      } else {
        quotaSummary.textContent = `Freigegebene Veröffentlichungen: ${Number(quota.consumed_total || 0)} von ${Number(quota.included_total || 0)}`;
      }
    }

    if (quotaRemaining) {
      if (quota.has_unlimited) {
        quotaRemaining.textContent = "Gezählt werden freigegebene Veröffentlichungen. Weitere Termine sind im üblichen Rahmen möglich.";
      } else if (isSingleStatusView) {
        quotaRemaining.textContent = "Keine Mitgliedschaft aktiv.";
      } else {
        quotaRemaining.textContent = `Noch veröffentlichbar nach Freigabe: ${Number(quota.remaining_total || 0)}`;
      }
    }
    /* === END BLOCK: ORGANIZER_DASHBOARD_APPROVED_PUBLICATION_COUNT_COPY_V1 === */
    }

    if (submissionsHead) {
      submissionsHead.textContent = isSingleStatusView ? "Meine Einreichung" : "Letzte Einreichungen";
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
