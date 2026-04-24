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

  /* === BEGIN BLOCK: GA4_RUNTIME_CONFIG_V1 | Zweck: zentrale, umgebungsbewusste GA4-Basiskonfiguration; live aktiv, lokal und staging aus | Umfang: nur öffentliche Runtime-Konfiguration, keine Secrets === */
  analytics: {
    measurementId: "G-Y6QLCQ4HXT",
    enabledHosts: ["bocholt-erleben.de", "www.bocholt-erleben.de"],
    scriptUrl: "https://www.googletagmanager.com/gtag/js?id=G-Y6QLCQ4HXT"
  },
  /* === END BLOCK: GA4_RUNTIME_CONFIG_V1 === */

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

/* === BEGIN BLOCK: GA4_BOOTSTRAP_V1 | Zweck: bindet das Google-Tag zentral nur auf Live-Domains ein und lässt staging bewusst ungetrackt | Umfang: ergänzt nur die schlanke Laufzeit-Initialisierung in config.js === */
function shouldEnableAnalytics() {
  if (typeof window === "undefined" || typeof document === "undefined") return false;
  if (IS_LOCAL) return false;

  const host = String(window.location.hostname || "").toLowerCase();
  return CONFIG.analytics.enabledHosts.includes(host);
}

function initAnalytics() {
  if (!shouldEnableAnalytics()) {
    debugLog("GA4 disabled for current host", {
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

  debugLog("GA4 initialized", {
    measurementId: CONFIG.analytics.measurementId
  });
}

initAnalytics();
/* === END BLOCK: GA4_BOOTSTRAP_V1 === */

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
