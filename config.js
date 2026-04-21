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

  /* === BEGIN BLOCK: FEEDBACK_AND_PUBLISH_FUNNEL_RUNTIME_CONFIG_V1 | Zweck: hält die öffentliche Runtime-Konfiguration für Feedback und Veranstalter-Funnel zentral in config.js, damit Seiten und Skripte nur noch konsumieren und keine Stripe-/Mailto-Runtime lokal doppeln; Umfang: ersetzt den bisherigen Feedback-Block plus den direkten Debug-Eintrag innerhalb von CONFIG === */
  feedback: {
    formspreeEndpoint: "https://formspree.io/f/mrerpwjy",
    fallbackEmail: "",
    minMessageLength: 12,
    successAutoCloseMs: 1400,
    sourceLabel: "bocholt-erleben-web",
    privacyUrl: "/datenschutz/"
  },

  publishFunnel: {
    automationEmail: "mathias@bocholt-erleben.de",
    storageKey: "be_publish_checkout_context_v1",
    successUrl: "/events-veroeffentlichen/erfolg/",
    cancelUrl: "/events-veroeffentlichen/abgebrochen/",
    paymentLinks: {
      single: "",
      starter: "",
      active: "",
      unlimited: ""
    }
  },

  // Debug Mode (nur lokal aktiv)
  debug: IS_LOCAL
  /* === END BLOCK: FEEDBACK_AND_PUBLISH_FUNNEL_RUNTIME_CONFIG_V1 === */
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

debugLog("Config loaded successfully", {
  ui: CONFIG.ui,
  features: CONFIG.features,
  debug: CONFIG.debug
});

/* === END BLOCK: CONFIG (no secrets; still used by app) === */
