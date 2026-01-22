/* === BEGIN BLOCK: MAIN MODULE HEADER (encoding fixed) ===
Zweck: Lesbare Kommentare/Strings (UTF-8 korrekt).
Umfang: Ersetzt nur den Dateikopf-Kommentar.
=== */
/**
 * MAIN.JS – Haupt-Einstiegspunkt
 *
 * Lädt Events und initialisiert alle Module.
 */
/* === END BLOCK: MAIN MODULE HEADER (encoding fixed) === */


const App = {
    events: [],

    /**
     * App starten
     */
    async init() {
        debugLog('=== BOCHOLT EVENTS HUB - APP START ===');

        // Detail Panel init
       /* === BEGIN BLOCK: DETAILPANEL INIT (defensive) ===
Zweck: DetailPanel wird genau einmal initialisiert, wenn verfügbar.
Umfang: Ersetzt nur den DetailPanel-init Abschnitt.
=== */
if (typeof DetailPanel !== "undefined" && typeof DetailPanel.init === "function") {
  DetailPanel.init();
} else {
  console.warn("DetailPanel not available – details.js not loaded or has an error.");
}
/* === END BLOCK: DETAILPANEL INIT (defensive) === */



        // Loading anzeigen
        this.showLoading(true);

        // Events von Airtable laden
        try {
            /* === BEGIN BLOCK: EVENTS FETCH (robust) ===
Zweck: Sauberes Error-Handling bei fehlender/kaputter events.json.
Umfang: Ersetzt nur den fetch/parse Block in App.init().
=== */
const response = await fetch('/data/events.json', { cache: "no-store" });

if (!response.ok) {
  throw new Error(`events.json load failed: ${response.status} ${response.statusText}`);
}

const data = await response.json();
this.events = Array.isArray(data?.events) ? data.events : [];
/* === END BLOCK: EVENTS FETCH (robust) === */



            if (this.events.length === 0) {
                this.showNoEvents();
                return;
            }

            // Module mit Events initialisieren
            this.initModules(this.events);

            // Loading verstecken
            this.showLoading(false);

            debugLog(`=== APP READY - ${this.events.length} events loaded ===`);

        } catch (error) {
            console.error('App initialization failed:', error);
            this.showError('Fehler beim Laden der Events. Bitte Seite neu laden.');
        }
    },

    /**
     * Alle Module mit Events initialisieren
     */
    initModules(events) {
        // Kalender
        if (CONFIG.features.showCalendar) {
            CalendarModule.init(events);
        }

        // Event Cards
        if (CONFIG.features.showEventCards) {
            EventCards.render(events);
        }

        // Filter (NEU!)
       /* === BEGIN BLOCK: FILTER INIT (robust + verified) ===
Zweck: FilterModule muss nach Event-Load sicher initialisiert werden; verhindert “init nie gelaufen”.
Umfang: Ersetzt nur den Filter-Init Abschnitt in initModules(events).
=== */
if (CONFIG.features.showFilters) {
  if (typeof FilterModule !== "undefined" && typeof FilterModule.init === "function") {
    debugLog("Initializing FilterModule with events:", Array.isArray(events) ? events.length : "not-array");
    FilterModule.init(events);

    // Harte Verifikation: wenn allEvents leer bleibt, nochmal mit App.events probieren (sicherer Fallback)
    if ((FilterModule.allEvents?.length || 0) === 0 && (this.events?.length || 0) > 0) {
      console.warn("FilterModule.init got no events – retrying with App.events");
      FilterModule.init(this.events);
    }
  } else {
    console.warn("FilterModule not available – filter.js not loaded or has an error.");
  }
}
/* === END BLOCK: FILTER INIT (robust + verified) === */


        debugLog('All modules initialized');
    },

    /**
     * Loading Indicator
     */
    showLoading(show) {
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.style.display = show ? 'flex' : 'none';
        }
    },

    /**
     * "Keine Events" Nachricht
     */
    showNoEvents() {
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.innerHTML = `
                <div class="info-message">
                    <p>📭­ Aktuell sind keine Events verfÃ¼gbar.</p>
                    <p><small>Bald gibt es hier spannende Events aus Bocholt!</small></p>
                </div>
            `;
            loadingEl.style.display = 'flex';
        }
    },

    /**
     * Generischer Error
     */
    showError(message) {
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.innerHTML = `
                <div class="error-message">
                    <p>⚠️ ${message}</p>
                </div>
            `;
            loadingEl.style.display = 'flex';
        }
    }
};

// App starten sobald DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => App.init());
} else {
    App.init();
}

debugLog('Main module loaded - waiting for DOM ready');


