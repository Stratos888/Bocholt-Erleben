// BEGIN: FILE_HEADER_MAIN
// Datei: js/main.js
// Zweck:
// - App-Einstiegspunkt (Bootstrapping)
// - Lädt Event-Daten (Quelle/Fetch) und hält die vollständige Event-Liste
// - Initialisiert Module in definierter Reihenfolge (Details, Filter, Cards, SEO/PWA…)
// - Verdrahtet Datenfluss: vollständige Events → Filter → gefilterte Events → EventCards
//
// Verantwortlich für:
// - App-Start & Initialisierungsreihenfolge
// - Daten laden + Fehlerbehandlung (Loading/Empty/Error UI)
// - Übergabe der Daten an Module (keine eigene Fachlogik)
//
// Nicht verantwortlich für:
// - Filter-State / Filterlogik (liegt in js/filter.js)
// - Rendering der Event Cards (liegt in js/events.js)
// - DetailPanel-Logik (liegt in js/details.js)
//
// Contract:
// - hält `App.events` als vollständige Quelle
// - ruft FilterModule/ EventCards nur über deren öffentliche API auf
// END: FILE_HEADER_MAIN



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
      /* === BEGIN BLOCK: INITMODULES ORCHESTRATION (single flow, no retries) ===
    Zweck: Deterministische Modul-Initialisierung ohne Retry-Magie.
    Umfang: Ersetzt initModules(events) komplett (Kalender/Filter/EventCards-Orchestrierung).
    Contract:
    - Wenn Filters aktiv sind: FilterModule ist Orchestrator (ruft refresh der Module).
    - Wenn Filters aus sind: main.js rendert direkt EventCards/Calendar.
    === */
    initModules(events) {
        const evts = Array.isArray(events) ? events : [];

        // Kalender: initialisieren, wenn vorhanden
        if (CONFIG?.features?.showCalendar && typeof CalendarModule?.init === "function") {
            CalendarModule.init(evts);
        }

        // Filter: wenn aktiv, übernimmt FilterModule den Datenfluss (applyFilters -> updateUI -> refresh)
        if (CONFIG?.features?.showFilters && typeof FilterModule?.init === "function") {
            FilterModule.init(evts);
        } else {
            // Ohne Filter: direkt rendern/refreshen
            if (CONFIG?.features?.showEventCards && typeof EventCards?.render === "function") {
                EventCards.render(evts);
            }

            if (CONFIG?.features?.showCalendar && typeof CalendarModule?.refresh === "function") {
                CalendarModule.refresh(evts);
            }
        }

        debugLog("All modules initialized");
    },
    /* === END BLOCK: INITMODULES ORCHESTRATION (single flow, no retries) === */


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






