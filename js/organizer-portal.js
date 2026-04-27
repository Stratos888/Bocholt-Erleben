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

  function hydrateIcons(root = document) {
    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }
  }

  async function tryLoadPortalState() {
    return requestJson("/api/organizer-portal/me.php", {
      method: "GET"
    });
  }

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

    const setSubmitting = (isSubmitting) => {
      if (!loginSubmit) return;
      if (!loginSubmit.dataset.defaultLabel) {
        loginSubmit.dataset.defaultLabel = loginSubmit.textContent || "Zugangslink anfordern";
      }
      loginSubmit.disabled = isSubmitting;
      loginSubmit.textContent = isSubmitting ? "Wird vorbereitet ..." : loginSubmit.dataset.defaultLabel;
    };

    if (token) {
      if (loginNote) {
        loginNote.textContent = "Der Zugangslink wird geprüft. Danach leiten wir direkt in den Veranstalterbereich weiter.";
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
            safeText(error?.message) || "Der Magic Link konnte nicht eingelöst werden.",
          { href: "/fuer-veranstalter/dashboard/", label: "Trotzdem zum Veranstalterbereich", icon: "chevron-right" }
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
            label: "Magic Link direkt öffnen",
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
  }

  function renderDashboard(data) {
    const authRequiredCard = document.getElementById("organizer-dashboard-auth-required");
    const summaryGrid = document.getElementById("organizer-dashboard-summary");
    const submissionsCard = document.getElementById("organizer-dashboard-submissions-card");

    if (authRequiredCard) authRequiredCard.hidden = true;
    if (summaryGrid) summaryGrid.hidden = false;
    if (submissionsCard) submissionsCard.hidden = false;

    const organizer = data?.organizer || {};
    const quota = data?.quota || {};
    const submissions = Array.isArray(data?.recent_submissions) ? data.recent_submissions : [];

    const title = document.getElementById("organizer-dashboard-title");
    const lead = document.getElementById("organizer-dashboard-lead");
    const note = document.getElementById("organizer-dashboard-note");
    const accountName = document.getElementById("organizer-account-name");
    const accountEmail = document.getElementById("organizer-account-email");
    const accountPlan = document.getElementById("organizer-account-plan");
    const quotaPeriod = document.getElementById("organizer-quota-period");
    const quotaSummary = document.getElementById("organizer-quota-summary");
    const quotaRemaining = document.getElementById("organizer-quota-remaining");
    const submissionsList = document.getElementById("organizer-dashboard-submissions-list");
    const submissionsEmpty = document.getElementById("organizer-dashboard-submissions-empty");

    if (title) {
      title.textContent = safeText(organizer.organization_name) || "Veranstalterbereich";
    }

    if (lead) {
      const email = safeText(organizer.email);
      lead.textContent = email
        ? `Eingeloggt für ${email}.`
        : "Eingeloggt im Veranstalterbereich.";
    }

    if (note) {
      note.textContent = "Sichtbar sind Kontingent, Status und die letzten Einreichungen.";
    }

    if (accountName) {
      accountName.textContent = safeText(organizer.organization_name) || "–";
    }

    if (accountEmail) {
      accountEmail.textContent = safeText(organizer.email) || "–";
    }

    if (accountPlan) {
      const planLabel = formatPlanLabel(organizer.default_plan_key);
      accountPlan.textContent = `Standardmodell: ${planLabel}`;
    }

    if (quotaPeriod) {
      const start = formatDateTime(quota.current_period_start);
      const end = formatDateTime(quota.current_period_end);
      quotaPeriod.textContent = quota.current_period_start || quota.current_period_end
        ? `Zeitraum: ${start} bis ${end}`
        : "Zeitraum: aktuell kein aktiver Abo-Zeitraum";
    }

    if (quotaSummary) {
      if (quota.has_unlimited) {
        quotaSummary.textContent = `Kontingent: unbegrenzt · verbraucht: ${Number(quota.consumed_total || 0)}`;
      } else {
        quotaSummary.textContent = `Kontingent: ${Number(quota.included_total || 0)} · verbraucht: ${Number(quota.consumed_total || 0)}`;
      }
    }

    if (quotaRemaining) {
      quotaRemaining.textContent = quota.has_unlimited
        ? "Verbleibend: unbegrenzt"
        : `Verbleibend: ${Number(quota.remaining_total || 0)}`;
    }

    if (submissionsList && submissionsEmpty) {
      submissionsList.innerHTML = "";

      if (!submissions.length) {
        submissionsEmpty.hidden = false;
      } else {
        submissionsEmpty.hidden = true;

        submissions.slice(0, 8).forEach((submission) => {
          const titleText = safeText(submission.title) || "Ohne Titel";
          const statusText = formatStatusLabel(submission.status);
          const dateText = formatDate(submission.start_date);
          const metaText = `${statusText} · ${dateText}`;

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
      if (authRequiredCard) authRequiredCard.hidden = false;
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
