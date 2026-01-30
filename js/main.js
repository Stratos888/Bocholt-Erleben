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
             /* === BEGIN BLOCK: DETAILPANEL INIT (defensive + verify) ===
Zweck: DetailPanel wird genau einmal initialisiert und MUSS danach "ready" sein (panel gesetzt),
       sonst werden Card-Interaktionen bewusst deaktiviert (kein Silent-Fail).
Umfang: Ersetzt nur den DetailPanel-init Abschnitt.
=== */
        if (typeof DetailPanel !== "undefined" && typeof DetailPanel.init === "function") {
            DetailPanel.init();

            const panelEl = document.getElementById("event-detail-panel");
            const ready = !!(DetailPanel.panel || panelEl);

            if (!ready) {
                console.warn("DetailPanel init ran, but panel not ready. Card clicks will not open details.");
            }
        } else {
            console.warn("DetailPanel not available – details.js not loaded or has an error.");
        }
        /* === END BLOCK: DETAILPANEL INIT (defensive + verify) === */




        // Loading anzeigen
        this.showLoading(true);

        // Events von Airtable laden
        try {
                      /* === BEGIN BLOCK: EVENTS FETCH + NORMALIZE (robust, canonical fields) ===
Zweck: Sauberes Error-Handling bei events.json + Normalisierung auf kanonische Felder,
       damit alle Module stabil mit { title, date, time, location, kategorie, beschreibung } arbeiten.
       Unterstützt beide JSON-Formate:
       (A) Array-Root: [ {...}, {...} ]
       (B) Objekt-Root: { events: [ {...}, {...} ] }
Umfang: Ersetzt nur den fetch/parse Block in App.init().
=== */
            const response = await fetch("/data/events.json", { cache: "no-store" });

            if (!response.ok) {
                throw new Error(`events.json load failed: ${response.status} ${response.statusText}`);
            }

            const data = await response.json();

            const rawEvents = Array.isArray(data)
                ? data
                : (Array.isArray(data?.events) ? data.events : []);

            const normalizeEvent = (e) => {
                const obj = e && typeof e === "object" ? e : {};

                const title = (obj.title ?? obj.eventName ?? "").toString().trim();
                const date = (obj.date ?? obj.datum ?? "").toString().trim(); // ISO YYYY-MM-DD erwartet
                const time = (obj.time ?? obj.uhrzeit ?? obj.startzeit ?? "").toString().trim();
                const location = (obj.location ?? obj.ort ?? "").toString().trim();
                const kategorie = (obj.kategorie ?? obj.category ?? "").toString().trim();
                const beschreibung = (obj.beschreibung ?? obj.description ?? "").toString().trim();

                return {
                    ...obj,
                    title,
                    eventName: (obj.eventName ?? title).toString().trim(),
                    date,
                    datum: (obj.datum ?? date).toString().trim(),
                    time,
                    location,
                    kategorie,
                    beschreibung
                };
            };

            this.events = rawEvents.map(normalizeEvent);
            /* === END BLOCK: EVENTS FETCH + NORMALIZE (robust, canonical fields) === */





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
       Wenn Filters aktiv sind, muss FilterModule.init() erfolgreich sein – kein Silent-Fail.
Umfang: Ersetzt initModules(events) komplett (Kalender/Filter/EventCards-Orchestrierung).
Contract:
- showFilters=true => FilterModule.init(evts) muss _isInit=true setzen, sonst sichtbarer Fehler + Fallback.
- showFilters=false => main.js rendert direkt EventCards/Calendar.
=== */
    initModules(events) {
        const evts = Array.isArray(events) ? events : [];

        // Kalender: initialisieren, wenn vorhanden
        if (CONFIG?.features?.showCalendar && typeof CalendarModule?.init === "function") {
            CalendarModule.init(evts);
        }

        const wantsFilters = CONFIG?.features?.showFilters === true;

        if (wantsFilters) {
            if (typeof window.FilterModule === "undefined" || typeof FilterModule?.init !== "function") {
                console.error("❌ [InitModules] showFilters=true but FilterModule.init is missing");
                this.showError("Filter konnten nicht geladen werden. Bitte Seite neu laden.");
                // Fallback: Seite bleibt nutzbar (ohne Filter)
                if (CONFIG?.features?.showEventCards && typeof EventCards?.render === "function") {
                    EventCards.render(evts);
                }
                if (CONFIG?.features?.showCalendar && typeof CalendarModule?.refresh === "function") {
                    CalendarModule.refresh(evts);
                }
                return;
            }

            // Filter muss erfolgreich initialisieren (kein Silent-Fail)
            FilterModule.init(evts);

            if (FilterModule._isInit !== true) {
                console.error("❌ [InitModules] FilterModule.init() did not complete (_isInit=false). UI will be non-interactive.", {
                    showFilters: CONFIG?.features?.showFilters,
                    hasSearch: !!document.getElementById("search-filter"),
                    hasTimePill: !!document.getElementById("filter-time-pill"),
                    hasCatPill: !!document.getElementById("filter-category-pill"),
                    hasTimeSheet: !!document.getElementById("sheet-time"),
                    hasCatSheet: !!document.getElementById("sheet-category"),
                    hasReset: !!document.getElementById("filter-reset-pill")
                });

                this.showError("Filter-UI konnte nicht initialisiert werden. Bitte Seite neu laden.");

                // Fallback: Seite bleibt nutzbar (ohne Filter)
                if (CONFIG?.features?.showEventCards && typeof EventCards?.render === "function") {
                    EventCards.render(evts);
                }
                if (CONFIG?.features?.showCalendar && typeof CalendarModule?.refresh === "function") {
                    CalendarModule.refresh(evts);
                }
                return;
            }

            debugLog("[InitModules] FilterModule initialized OK");
            debugLog("All modules initialized");
            return;
        }

        // Ohne Filter: direkt rendern/refreshen
        if (CONFIG?.features?.showEventCards && typeof EventCards?.render === "function") {
            EventCards.render(evts);
        }

        if (CONFIG?.features?.showCalendar && typeof CalendarModule?.refresh === "function") {
            CalendarModule.refresh(evts);
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
/* === BEGIN BLOCK: APP BOOTSTRAP (DOM first, deterministic) ===
Zweck: UI-abhängige Module (Filter) dürfen erst nach DOMReady initialisiert werden.
Umfang: Ersetzt den App-Start am Dateiende.
=== */
document.addEventListener("DOMContentLoaded", () => {
    App.init();
});
/* === END BLOCK: APP BOOTSTRAP (DOM first, deterministic) === */


debugLog('Main module loaded - waiting for DOM ready');











