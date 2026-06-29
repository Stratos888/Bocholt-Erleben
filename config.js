/* === BEGIN BLOCK: CONFIG (no secrets; still used by app) ===
Zweck:
- Entfernt Airtable/PAT/Keys komplett aus dem Client
- Behält nur genutzte App-Config + Helper (debugLog, formatDate)
Umfang:
- Gesamte Datei config.js
=== */

const IS_LOCAL =
  (typeof location !== "undefined") &&
  (location.hostname === "localhost" ||
   location.hostname === "127.0.0.1" ||
   location.hostname === "0.0.0.0");

const CONFIG = {
  // UI Einstellungen
  ui: {
    eventsPerPage: 50,
    calendarHeight: "auto",
    showPastEvents: false,
    defaultView: "dayGridMonth"
  },

  // Feature Flags
  features: {
    showCalendar: false,
    showEventCards: true,
    showDetailPanel: true,
    showFilters: true
  },

  /* === BEGIN BLOCK: FEEDBACK_FORMSPREE_CONFIG_V1 | Zweck: zentraler Runtime-Contract für das globale Feedback-System mit Formspree Free; Umfang: nur öffentliche Client-Konfiguration, keine Secrets === */
  feedback: {
    formspreeEndpoint: "https://formspree.io/f/mrerpwjy",
    fallbackEmail: "",
    minMessageLength: 12,
    successAutoCloseMs: 1400,
    sourceLabel: "bocholt-erleben-web",
    privacyUrl: "/datenschutz/"
  },
  /* === END BLOCK: FEEDBACK_FORMSPREE_CONFIG_V1 === */

  /* === BEGIN BLOCK: GA4_RUNTIME_CONFIG_AND_VALUE_METRICS_V4 | Zweck: zentrale Live-Analytics-Konfiguration plus First-Party-Nutzwert-Endpunkt; Tracking wird erst nach aktiver Statistik-Zustimmung gestartet; Umfang: ersetzt Analytics-Konfiguration === */
  analytics: {
    measurementId: "G-Y6QLCQ4HXT",
    enabledHosts: ["bocholt-erleben.de", "www.bocholt-erleben.de"],
    scriptUrl: "https://www.googletagmanager.com/gtag/js?id=G-Y6QLCQ4HXT",
    valueMetricsEndpoint: "/api/value-track.php"
  },

  privacy: {
    statisticsConsentStorageKey: "be_statistics_consent_v1",
    statisticsConsentCookieName: "be_statistics_consent",
    statisticsConsentMaxAgeDays: 180,
    consentUiHosts: ["bocholt-erleben.de", "www.bocholt-erleben.de", "staging.bocholt-erleben.de"],
    settingsUrl: "/datenschutz/#datenschutz-einstellungen"
  },
  /* === END BLOCK: GA4_RUNTIME_CONFIG_AND_VALUE_METRICS_V4 === */

  // Debug Mode (nur lokal aktiv)
  debug: IS_LOCAL
};

// Helper: Log nur wenn debug = true
function debugLog(message, data = null) {
  if (CONFIG.debug) {
    console.log(`[DEBUG] ${message}`, data || "");
  }
}

// Helper: Datum in deutsches Format
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  });
}

/* === BEGIN BLOCK: GA4_BOOTSTRAP_OUTBOUND_AND_VALUE_METRICS_V4 | Zweck: startet GA4 und First-Party-Nutzwerttracking erst nach aktiver Statistik-Zustimmung; Umfang: ersetzt Analytics-Bootstrap/Helper in config.js === */
function isAnalyticsHostAllowed() {
  if (typeof window === "undefined" || typeof document === "undefined") return false;
  if (IS_LOCAL) return false;

  const host = String(window.location.hostname || "").toLowerCase();
  return CONFIG.analytics.enabledHosts.includes(host);
}

function isConsentUiHostAllowed() {
  if (typeof window === "undefined" || typeof document === "undefined") return false;
  if (IS_LOCAL) return false;

  const host = String(window.location.hostname || "").toLowerCase();
  const hosts = Array.isArray(CONFIG.privacy?.consentUiHosts)
    ? CONFIG.privacy.consentUiHosts
    : CONFIG.analytics.enabledHosts;

  return hosts.includes(host);
}

function getCookieValue(name) {
  const cookieName = `${name}=`;
  return String(document.cookie || "")
    .split(";")
    .map((entry) => entry.trim())
    .find((entry) => entry.startsWith(cookieName))
    ?.slice(cookieName.length) || "";
}

function writeCookieValue(name, value, maxAgeSeconds) {
  const secure = window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAgeSeconds}; Path=/; SameSite=Lax${secure}`;
}

function getStatisticsConsentState() {
  const key = CONFIG.privacy.statisticsConsentStorageKey;
  const cookieName = CONFIG.privacy.statisticsConsentCookieName;

  try {
    const stored = window.localStorage?.getItem(key);
    if (stored === "granted" || stored === "denied") return stored;
  } catch (_) {}

  const cookieValue = decodeURIComponent(getCookieValue(cookieName) || "");
  if (cookieValue === "granted" || cookieValue === "denied") return cookieValue;

  return "unset";
}

function hasStatisticsConsentChoice() {
  return getStatisticsConsentState() !== "unset";
}

function isStatisticsConsentGranted() {
  return getStatisticsConsentState() === "granted";
}

function persistStatisticsConsent(state) {
  if (state !== "granted" && state !== "denied") return;

  const key = CONFIG.privacy.statisticsConsentStorageKey;
  const cookieName = CONFIG.privacy.statisticsConsentCookieName;
  const maxAgeSeconds = Math.max(1, Number(CONFIG.privacy.statisticsConsentMaxAgeDays || 180)) * 24 * 60 * 60;

  try {
    window.localStorage?.setItem(key, state);
  } catch (_) {}

  writeCookieValue(cookieName, state, maxAgeSeconds);
}

function setStatisticsConsent(granted) {
  persistStatisticsConsent(granted ? "granted" : "denied");
  removePrivacyConsentBanner();
  updatePrivacySettingsPanel();

  if (granted) {
    initAnalytics();
  }
}

function resetStatisticsConsent() {
  const key = CONFIG.privacy.statisticsConsentStorageKey;
  const cookieName = CONFIG.privacy.statisticsConsentCookieName;

  try {
    window.localStorage?.removeItem(key);
  } catch (_) {}

  writeCookieValue(cookieName, "", 0);
  removePrivacyConsentBanner();
  updatePrivacySettingsPanel();
  initPrivacyConsentUi();
}

function shouldOfferStatisticsConsent() {
  return isConsentUiHostAllowed() && !hasStatisticsConsentChoice();
}

function shouldEnableAnalytics() {
  return isAnalyticsHostAllowed() && isStatisticsConsentGranted();
}

function sanitizeAnalyticsValue(value, maxLength = 200) {
  const text = String(value ?? "").trim();
  if (!text) return "";
  return text.slice(0, maxLength);
}

function sanitizeMetricKey(value) {
  return sanitizeAnalyticsValue(value, 64).toLowerCase().replace(/[^a-z0-9_-]+/g, "_").replace(/^[_-]+|[_-]+$/g, "");
}

/* === BEGIN BLOCK: VALUE_METRICS_OPTOUT_HELPERS_V2 | Zweck: haelt Legacy-Opt-out-Helfer kompatibel; neue Steuerung erfolgt ueber die Statistik-Zustimmung; Umfang: ersetzt Client-Opt-out-Helfer === */
const VALUE_METRICS_OPT_OUT_KEY = "be_value_metrics_opt_out";

function isValueMetricsOptedOut() {
  return !isStatisticsConsentGranted();
}

function setValueMetricsOptOut(enabled) {
  setStatisticsConsent(!enabled);
}
/* === END BLOCK: VALUE_METRICS_OPTOUT_HELPERS_V2 === */

function getDomainFromUrl(url) {
  try {
    return new URL(url).hostname.replace(/^www\./i, "");
  } catch (_) {
    return "";
  }
}

/* === BEGIN BLOCK: VALUE_METRICS_REPORTING_TARGET_HELPERS_V1 | Zweck: normalisiert optionale Reporting-Ziele für Anbieter-/Location-Auswertungen; Umfang: ergänzt nur Analytics-Helfer ohne Pflichtdaten === */
function normalizeReportingTargetPayload(payload = {}) {
  const target = payload.reportingTarget || payload.reporting_target || {};

  return {
    reportingTargetType: sanitizeMetricKey(
      payload.reportingTargetType ||
      payload.reporting_target_type ||
      target.type ||
      target.reporting_target_type ||
      ""
    ),
    reportingTargetId: sanitizeAnalyticsValue(
      payload.reportingTargetId ||
      payload.reporting_target_id ||
      target.id ||
      target.reporting_target_id ||
      "",
      191
    ),
    reportingTargetTitle: sanitizeAnalyticsValue(
      payload.reportingTargetTitle ||
      payload.reporting_target_title ||
      target.title ||
      target.name ||
      target.reporting_target_title ||
      "",
      255
    )
  };
}

function buildReportingTargetPayload(source = {}) {
  return normalizeReportingTargetPayload(source);
}
/* === END BLOCK: VALUE_METRICS_REPORTING_TARGET_HELPERS_V1 === */

function emitGa4Event(eventName, params = {}) {
  if (!shouldEnableAnalytics()) return;
  if (typeof window.gtag !== "function") return;

  window.gtag("event", eventName, params);
}

function sendValueMetric(metricKey, payload = {}) {
  if (!shouldEnableAnalytics()) return;

  const cleanMetricKey = sanitizeMetricKey(metricKey);
  if (!cleanMetricKey) return;
  if (isValueMetricsOptedOut()) return;

  const endpoint = CONFIG.analytics.valueMetricsEndpoint || "/api/value-track.php";
  const destinationUrl = sanitizeAnalyticsValue(payload.destinationUrl || payload.url, 500);
  const reportingTarget = normalizeReportingTargetPayload(payload);

  const body = JSON.stringify({
    metric_key: cleanMetricKey,
    entity_type: sanitizeMetricKey(payload.entityType || payload.entity_type),
    entity_id: sanitizeAnalyticsValue(payload.entityId || payload.entity_id, 191),
    entity_title: sanitizeAnalyticsValue(payload.entityTitle || payload.entity_title, 255),
    destination_url: destinationUrl,
    reporting_target_type: reportingTarget.reportingTargetType,
    reporting_target_id: reportingTarget.reportingTargetId,
    reporting_target_title: reportingTarget.reportingTargetTitle,
    page_path: sanitizeAnalyticsValue(
      typeof window !== "undefined" ? window.location.pathname : "",
      255
    )
  });

  try {
    if (navigator.sendBeacon) {
      const blob = new Blob([body], { type: "application/json" });
      if (navigator.sendBeacon(endpoint, blob)) return;
    }
  } catch (_) {}

  try {
    fetch(endpoint, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body,
      keepalive: true,
      credentials: "same-origin"
    }).catch(() => {});
  } catch (_) {}
}

function outboundMetricKeyFromPayload(payload = {}) {
  const outboundType = sanitizeMetricKey(payload.outboundType || payload.outbound_type);

  if (outboundType === "maps" || outboundType === "route" || outboundType === "directions") {
    return "maps_click";
  }

  if (outboundType === "location") {
    return "location_click";
  }

  if (outboundType === "website" || outboundType === "source" || outboundType === "external") {
    return "website_click";
  }

  return "website_click";
}

function trackOutboundClick(payload = {}) {
  if (!shouldEnableAnalytics()) return;

  const destinationUrl = sanitizeAnalyticsValue(payload.destinationUrl || payload.url, 500);
  const destinationDomain = sanitizeAnalyticsValue(
    payload.destinationDomain || getDomainFromUrl(destinationUrl),
    120
  );
  const outboundType = sanitizeAnalyticsValue(payload.outboundType, 40) || "external";
  const entityType = sanitizeAnalyticsValue(payload.entityType, 40) || "";
  const entityId = sanitizeAnalyticsValue(payload.entityId, 120) || "";
  const entityTitle = sanitizeAnalyticsValue(payload.entityTitle, 150) || "";
  const reportingTarget = normalizeReportingTargetPayload(payload);
  const pagePath = sanitizeAnalyticsValue(
    typeof window !== "undefined" ? window.location.pathname : "",
    200
  );
  const metricKey = outboundMetricKeyFromPayload(payload);

  emitGa4Event("outbound_click", {
    outbound_type: outboundType,
    entity_type: entityType,
    entity_id: entityId,
    entity_title: entityTitle,
    reporting_target_type: reportingTarget.reportingTargetType,
    reporting_target_id: reportingTarget.reportingTargetId,
    reporting_target_title: reportingTarget.reportingTargetTitle,
    destination_domain: destinationDomain,
    destination_url: destinationUrl,
    page_path: pagePath
  });

  emitGa4Event(`be_${metricKey}`, {
    outbound_type: outboundType,
    entity_type: entityType,
    entity_id: entityId,
    entity_title: entityTitle,
    reporting_target_type: reportingTarget.reportingTargetType,
    reporting_target_id: reportingTarget.reportingTargetId,
    reporting_target_title: reportingTarget.reportingTargetTitle,
    destination_domain: destinationDomain,
    page_path: pagePath
  });

  sendValueMetric(metricKey, {
    ...payload,
    destinationUrl,
    entityType,
    entityId,
    entityTitle,
    ...reportingTarget
  });
}

function trackValueMetric(metricKey, payload = {}) {
  const cleanMetricKey = sanitizeMetricKey(metricKey);
  if (!cleanMetricKey) return;

  const reportingTarget = normalizeReportingTargetPayload(payload);

  emitGa4Event(`be_${cleanMetricKey}`, {
    entity_type: sanitizeAnalyticsValue(payload.entityType || payload.entity_type, 40),
    entity_id: sanitizeAnalyticsValue(payload.entityId || payload.entity_id, 120),
    entity_title: sanitizeAnalyticsValue(payload.entityTitle || payload.entity_title, 150),
    reporting_target_type: reportingTarget.reportingTargetType,
    reporting_target_id: reportingTarget.reportingTargetId,
    reporting_target_title: reportingTarget.reportingTargetTitle,
    page_path: sanitizeAnalyticsValue(
      typeof window !== "undefined" ? window.location.pathname : "",
      200
    )
  });

  sendValueMetric(cleanMetricKey, {
    ...payload,
    ...reportingTarget
  });
}

function isOrganizerCtaHref(href) {
  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin) return false;

    const path = url.pathname.replace(/\/+$/, "/");
    return path.startsWith("/events-veroeffentlichen/") ||
      path.startsWith("/fuer-veranstalter/") ||
      path.startsWith("/aktivitaeten/sichtbar-werden/");
  } catch (_) {
    return false;
  }
}

function initOrganizerCtaTracking() {
  if (window.__beOrganizerCtaTrackingInitialized) return;
  window.__beOrganizerCtaTrackingInitialized = true;

  document.addEventListener("click", (event) => {
    const target = event.target instanceof Element ? event.target : null;
    const link = target?.closest ? target.closest("a[href]") : null;
    if (!link || !isOrganizerCtaHref(link.href)) return;

    trackValueMetric("organizer_cta_click", {
      entityType: "organizer_funnel",
      entityId: link.pathname || "organizer_link",
      entityTitle: sanitizeAnalyticsValue(link.textContent || link.getAttribute("aria-label") || "Veranstalter-CTA", 150),
      destinationUrl: link.href
    });
  }, { capture: true });
}

function initPrivacyApi() {
  if (typeof window === "undefined") return;

  window.BEPrivacy = window.BEPrivacy || {};
  window.BEPrivacy.getStatisticsConsentState = getStatisticsConsentState;
  window.BEPrivacy.setStatisticsConsent = setStatisticsConsent;
  window.BEPrivacy.resetStatisticsConsent = resetStatisticsConsent;
  window.BEPrivacy.updateSettingsPanel = updatePrivacySettingsPanel;
}

function removePrivacyConsentBanner() {
  document.querySelector("[data-privacy-consent-banner]")?.remove();
}

function buildPrivacyButton(label, action, variant = "secondary") {
  const button = document.createElement("button");
  button.type = "button";
  button.className = `privacy-consent__button privacy-consent__button--${variant}`;
  button.textContent = label;
  button.addEventListener("click", action);
  return button;
}

function initPrivacyConsentUi() {
  if (typeof document === "undefined") return;
  if (!shouldOfferStatisticsConsent()) return;
  if (document.querySelector("[data-privacy-consent-banner]")) return;

  const banner = document.createElement("section");
  banner.className = "privacy-consent";
  banner.setAttribute("data-privacy-consent-banner", "");
  banner.setAttribute("role", "dialog");
  banner.setAttribute("aria-live", "polite");
  banner.setAttribute("aria-labelledby", "privacy-consent-title");

  const content = document.createElement("div");
  content.className = "privacy-consent__content";

  const kicker = document.createElement("div");
  kicker.className = "privacy-consent__kicker";
  kicker.textContent = "Datenschutz";

  const title = document.createElement("h2");
  title.id = "privacy-consent-title";
  title.textContent = "Optionale Statistik";

  const copy = document.createElement("p");
  copy.textContent = "Wir nutzen optionale, anonyme Statistik, um Bocholt erleben zu verbessern. Ohne Zustimmung bleibt alles vollständig nutzbar.";

  const actions = document.createElement("div");
  actions.className = "privacy-consent__actions";
  actions.append(
    buildPrivacyButton("Ohne Statistik", () => setStatisticsConsent(false)),
    buildPrivacyButton("Statistik erlauben", () => setStatisticsConsent(true), "primary")
  );

  const link = document.createElement("a");
  link.className = "privacy-consent__link";
  link.href = CONFIG.privacy.settingsUrl || "/datenschutz/#datenschutz-einstellungen";
  link.textContent = "Details & Einstellungen";

  content.append(kicker, title, copy, actions, link);
  banner.append(content);
  document.body.appendChild(banner);
}

function updatePrivacySettingsPanel() {
  if (typeof document === "undefined") return;

  const panel = document.querySelector("[data-privacy-settings]");
  if (!panel) return;

  const state = getStatisticsConsentState();
  const isConsentHost = isConsentUiHostAllowed();
  const isAnalyticsHost = isAnalyticsHostAllowed();
  const stagingSuffix = isConsentHost && !isAnalyticsHost ? " (Staging-Vorschau, kein Analytics-Start)" : "";
  const statusLabel = !isConsentHost
    ? "Auf dieser Umgebung nicht aktiv"
    : state === "granted"
      ? `Statistik erlaubt${stagingSuffix}`
      : state === "denied"
        ? `Nur notwendige Funktionen${stagingSuffix}`
        : `Noch keine Auswahl${stagingSuffix}`;

  panel.innerHTML = `
    <div class="privacy-settings__status">
      <strong>Aktueller Status:</strong> ${statusLabel}
    </div>
    <div class="privacy-settings__actions">
      <button type="button" class="privacy-consent__button privacy-consent__button--primary" data-privacy-action="grant">Statistik erlauben</button>
      <button type="button" class="privacy-consent__button privacy-consent__button--secondary" data-privacy-action="deny">Nur notwendige Funktionen</button>
      <button type="button" class="privacy-consent__button privacy-consent__button--ghost" data-privacy-action="reset">Auswahl zurücksetzen</button>
    </div>
  `;

  panel.querySelector('[data-privacy-action="grant"]')?.addEventListener("click", () => setStatisticsConsent(true));
  panel.querySelector('[data-privacy-action="deny"]')?.addEventListener("click", () => setStatisticsConsent(false));
  panel.querySelector('[data-privacy-action="reset"]')?.addEventListener("click", resetStatisticsConsent);
}

function initAnalytics() {
  if (!shouldEnableAnalytics()) {
    debugLog("GA4/value metrics disabled until statistics consent is granted", {
      host: typeof window !== "undefined" ? window.location.hostname : "",
      consent: getStatisticsConsentState()
    });
    return;
  }

  if (window.__beAnalyticsInitialized) return;
  window.__beAnalyticsInitialized = true;

  window.dataLayer = window.dataLayer || [];
  window.gtag = window.gtag || function gtag() {
    window.dataLayer.push(arguments);
  };

  window.gtag("js", new Date());
  window.gtag("config", CONFIG.analytics.measurementId, {
    anonymize_ip: true
  });

  const script = document.createElement("script");
  script.async = true;
  script.src = CONFIG.analytics.scriptUrl;
  script.setAttribute("data-owner", "bocholt-erleben-ga4");
  document.head.appendChild(script);

  window.BEAnalytics = window.BEAnalytics || {};
  window.BEAnalytics.trackOutboundClick = trackOutboundClick;
  window.BEAnalytics.trackValueMetric = trackValueMetric;
  window.BEAnalytics.buildReportingTargetPayload = buildReportingTargetPayload;
  window.BEAnalytics.setValueMetricsOptOut = setValueMetricsOptOut;
  window.BEAnalytics.isValueMetricsOptedOut = isValueMetricsOptedOut;

  initOrganizerCtaTracking();

  debugLog("GA4 and value metrics initialized after statistics consent", {
    measurementId: CONFIG.analytics.measurementId
  });
}

function initPrivacyRuntime() {
  initPrivacyApi();
  updatePrivacySettingsPanel();
  initPrivacyConsentUi();
  initAnalytics();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initPrivacyRuntime, { once: true });
} else {
  initPrivacyRuntime();
}
/* === END BLOCK: GA4_BOOTSTRAP_OUTBOUND_AND_VALUE_METRICS_V4 === */

debugLog("Config loaded successfully", {
  ui: CONFIG.ui,
  features: CONFIG.features,
  analyticsEnabled: shouldEnableAnalytics(),
  debug: CONFIG.debug
});

// Helper: Datum in deutsches Format
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString("de-DE", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric"
  });
}

debugLog("Config loaded successfully", {
  ui: CONFIG.ui,
  features: CONFIG.features,
  debug: CONFIG.debug
});

/* === END BLOCK: CONFIG (no secrets; still used by app) === */
