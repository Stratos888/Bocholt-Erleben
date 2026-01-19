/**
 * MAIN.JS - Haupt-Einstiegspunkt
 * 
 * LÃ¤dt Events und initialisiert alle Module.
 */

const App = {
    events: [],

    /**
     * App starten
     */
    async init() {
        debugLog('=== BOCHOLT EVENTS HUB - APP START ===');

        // Check: Ist Airtable konfiguriert?
        //if (!this.checkConfig()) {
          //  return;
        //}

        // Detail Panel init
        if (typeof DetailPanel !== "undefined" && DetailPanel.init) {
    DetailPanel.init();
} else {
    console.warn("DetailPanel not available – details.js not loaded or has an error.");
}


        // Loading anzeigen
        this.showLoading(true);

        // Events von Airtable laden
        try {
            const response = await fetch('/data/events.json');
const data = await response.json();
this.events = data.events;


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
        if (CONFIG.features.showFilters) {
            FilterModule.init(events);
        }

        debugLog('All modules initialized');
    },

    /**
     * Config-Check: Sind API Keys gesetzt?
     */
    checkConfig() {
        const { apiKey, baseId } = CONFIG.airtable;

        if (apiKey === 'YOUR_AIRTABLE_API_KEY' || baseId === 'YOUR_BASE_ID') {
            this.showConfigError();
            return false;
        }

        return true;
    },

    /**
     * Config-Fehler anzeigen
     */
    showConfigError() {
        const loadingEl = document.getElementById('loading');
        if (loadingEl) {
            loadingEl.innerHTML = `
                <div class="error-message">
                    <h3>âš™ï¸ Konfiguration fehlt!</h3>
                    <p>Bitte trage deine Airtable API-Zugangsdaten in <code>js/config.js</code> ein:</p>
                    <ul style="text-align: left; margin: 20px auto; max-width: 400px;">
                        <li>Airtable API Key</li>
                        <li>Base ID</li>
                    </ul>
                    <p><small>Siehe Anleitung in der README.md</small></p>
                </div>
            `;
        }
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
                    <p>ðŸ“­ Aktuell sind keine Events verfÃ¼gbar.</p>
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
                    <p>âš ï¸ ${message}</p>
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
