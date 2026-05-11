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

  /* === BEGIN BLOCK: GA4_RUNTIME_CONFIG_AND_VALUE_METRICS_V3 | Zweck: zentrale Live-Analytics-Konfiguration plus First-Party-Nutzwert-Endpunkt für automatisches Mehrwert-Dashboard; Umfang: ersetzt GA4-Config und Analytics-Bootstrap/Helper === */
  analytics: {
    measurementId: "G-Y6QLCQ4HXT",
    enabledHosts: ["bocholt-erleben.de", "www.bocholt-erleben.de"],
    scriptUrl: "https://www.googletagmanager.com/gtag/js?id=G-Y6QLCQ4HXT",
    valueMetricsEndpoint: "/api/value-track.php"
  },
  /* === END BLOCK: GA4_RUNTIME_CONFIG_AND_VALUE_METRICS_V3 === */

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

/* === BEGIN BLOCK: GA4_BOOTSTRAP_OUTBOUND_AND_VALUE_METRICS_V3 | Zweck: trackt GA4 weiterhin und schreibt zusätzlich anonyme aggregierte Nutzwert-Events in die eigene DB; Umfang: ersetzt nur Analytics-Bootstrap/Helper in config.js === */
function shouldEnableAnalytics() {
  if (typeof window === "undefined" || typeof document === "undefined") return false;
  if (IS_LOCAL) return false;

  const host = String(window.location.hostname || "").toLowerCase();
  return CONFIG.analytics.enabledHosts.includes(host);
}

function sanitizeAnalyticsValue(value, maxLength = 200) {
  const text = String(value ?? "").trim();
  if (!text) return "";
  return text.slice(0, maxLength);
}

function sanitizeMetricKey(value) {
  return sanitizeAnalyticsValue(value, 64).toLowerCase().replace(/[^a-z0-9_-]+/g, "_").replace(/^[_-]+|[_-]+$/g, "");
}

function getDomainFromUrl(url) {
  try {
    return new URL(url).hostname.replace(/^www\./i, "");
  } catch (_) {
    return "";
  }
}

function emitGa4Event(eventName, params = {}) {
  if (!shouldEnableAnalytics()) return;
  if (typeof window.gtag !== "function") return;

  window.gtag("event", eventName, params);
}

function sendValueMetric(metricKey, payload = {}) {
  if (!shouldEnableAnalytics()) return;

  const cleanMetricKey = sanitizeMetricKey(metricKey);
  if (!cleanMetricKey) return;

  const endpoint = CONFIG.analytics.valueMetricsEndpoint || "/api/value-track.php";
  const destinationUrl = sanitizeAnalyticsValue(payload.destinationUrl || payload.url, 500);

  const body = JSON.stringify({
    metric_key: cleanMetricKey,
    entity_type: sanitizeMetricKey(payload.entityType || payload.entity_type),
    entity_id: sanitizeAnalyticsValue(payload.entityId || payload.entity_id, 191),
    entity_title: sanitizeAnalyticsValue(payload.entityTitle || payload.entity_title, 255),
    destination_url: destinationUrl,
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
    destination_domain: destinationDomain,
    destination_url: destinationUrl,
    page_path: pagePath
  });

  emitGa4Event(`be_${metricKey}`, {
    outbound_type: outboundType,
    entity_type: entityType,
    entity_id: entityId,
    entity_title: entityTitle,
    destination_domain: destinationDomain,
    page_path: pagePath
  });

  sendValueMetric(metricKey, {
    ...payload,
    destinationUrl,
    entityType,
    entityId,
    entityTitle
  });
}

function trackValueMetric(metricKey, payload = {}) {
  const cleanMetricKey = sanitizeMetricKey(metricKey);
  if (!cleanMetricKey) return;

  emitGa4Event(`be_${cleanMetricKey}`, {
    entity_type: sanitizeAnalyticsValue(payload.entityType || payload.entity_type, 40),
    entity_id: sanitizeAnalyticsValue(payload.entityId || payload.entity_id, 120),
    entity_title: sanitizeAnalyticsValue(payload.entityTitle || payload.entity_title, 150),
    page_path: sanitizeAnalyticsValue(
      typeof window !== "undefined" ? window.location.pathname : "",
      200
    )
  });

  sendValueMetric(cleanMetricKey, payload);
}

function isOrganizerCtaHref(href) {
  try {
    const url = new URL(href, window.location.origin);
    if (url.origin !== window.location.origin) return false;

    const path = url.pathname.replace(/\/+$/, "/");
    return path.startsWith("/events-veroeffentlichen/") || path.startsWith("/fuer-veranstalter/");
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

function initAnalytics() {
  if (!shouldEnableAnalytics()) {
    debugLog("GA4/value metrics disabled for current host", {
      host: typeof window !== "undefined" ? window.location.hostname : ""
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
  window.gtag("config", CONFIG.analytics.measurementId);

  const script = document.createElement("script");
  script.async = true;
  script.src = CONFIG.analytics.scriptUrl;
  script.setAttribute("data-owner", "bocholt-erleben-ga4");
  document.head.appendChild(script);

  window.BEAnalytics = window.BEAnalytics || {};
  window.BEAnalytics.trackOutboundClick = trackOutboundClick;
  window.BEAnalytics.trackValueMetric = trackValueMetric;

  initOrganizerCtaTracking();

  debugLog("GA4 and value metrics initialized", {
    measurementId: CONFIG.analytics.measurementId
  });
}

initAnalytics();
/* === END BLOCK: GA4_BOOTSTRAP_OUTBOUND_AND_VALUE_METRICS_V3 === */

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
