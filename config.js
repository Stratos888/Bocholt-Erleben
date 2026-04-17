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

debugLog("Config loaded successfully", {
  ui: CONFIG.ui,
  features: CONFIG.features,
  debug: CONFIG.debug
});

/* === END BLOCK: CONFIG (no secrets; still used by app) === */
