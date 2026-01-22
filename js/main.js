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
     /* === BEGIN BLOCK: FILTER INIT (self-healing retries) ===
Zweck: FilterModule muss zuverlässig initialisieren (Listener müssen sicher dran sein),
       auch wenn der erste Versuch zu früh/abgebrochen passiert.
Umfang: Ersetzt nur den Filter-Init Abschnitt in initModules(events).
=== */
if (CONFIG.features.showFilters) {
  const MAX_TRIES = 4;
  let tries = 0;

  const hasFilterUI = () => {
    // Minimal-Check: wenn diese fehlen, kann FilterModule.init nur scheitern/abbrechen
    return !!(
      document.getElementById("search-filter") &&
      document.getElementById("filter-time-pill") &&
      document.getElementById("filter-time-value") &&
      document.getElementById("sheet-time") &&
      document.getElementById("filter-category-pill") &&
      document.getElementById("filter-category-value") &&
      document.getElementById("sheet-category") &&
      document.getElementById("filter-reset-pill")
    );
  };

  const tryInitFilters = (label, evts) => {
    tries += 1;

    if (typeof FilterModule === "undefined" || typeof FilterModule.init !== "function") {
      console.warn(`[FilterInit] (${label}) FilterModule not ready (try ${tries}/${MAX_TRIES})`);
      return false;
    }

    if (!hasFilterUI()) {
      console.warn(`[FilterInit] (${label}) Filter UI not ready (try ${tries}/${MAX_TRIES})`);
      return false;
    }

    try {
      const count = Array.isArray(evts) ? evts.length : 0;
      debugLog(`[FilterInit] (${label}) calling FilterModule.init with events: ${count}`);
      FilterModule.init(evts);

      // Verifikation: Listener-Init ist nur sinnvoll, wenn allEvents auch wirklich gesetzt wurde
      const ok = (FilterModule.allEvents?.length || 0) > 0 || (count === 0);
      if (ok) {
        FilterModule.__initialized = true;
        FilterModule.__initLabel = label;
        FilterModule.__initTries = tries;
        debugLog(`[FilterInit] initialized OK (${label})`, {
          tries,
          allEvents: FilterModule.allEvents?.length || 0
        });
        return true;
      }

      console.warn(`[FilterInit] (${label}) init ran but allEvents still empty (try ${tries}/${MAX_TRIES})`);
      return false;
    } catch (err) {
      console.error(`[FilterInit] (${label}) init crashed (try ${tries}/${MAX_TRIES})`, err);
      return false;
    }
  };

  const boot = () => {
    if (FilterModule?.__initialized) return;

    // 1) sofort mit dem übergebenen events versuchen
    if (tryInitFilters("immediate", events)) return;

    // 2) fallback: mit App.events versuchen
    if (tryInitFilters("fallback-App.events", this.events)) return;

    // 3) retries mit Lifecycle-Ticks (ohne Endlosschleife)
    if (tries < MAX_TRIES) {
      requestAnimationFrame(() => {
        if (FilterModule?.__initialized) return;
        if (tryInitFilters("rAF", this.events)) return;

        setTimeout(() => {
          if (FilterModule?.__initialized) return;
          if (tryInitFilters("timeout-200ms", this.events)) return;
        }, 200);
      });
    }

    // 4) final: nach window.load (wenn wirklich alles da ist)
    if (tries < MAX_TRIES) {
      window.addEventListener(
        "load",
        () => {
          if (FilterModule?.__initialized) return;
          tryInitFilters("window-load", this.events);
        },
        { once: true }
      );
    }
  };

  boot();
}
/* === END BLOCK: FILTER INIT (self-healing retries) === */



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



