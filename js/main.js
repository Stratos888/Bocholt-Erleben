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




             /* === BEGIN BLOCK: GS-01 LOADING START (skeleton only, no overlay) ===
Zweck: Während Fetch nur Skeleton im Feed anzeigen (enterprise), kein Vollbild-Overlay (verdeckt sonst den Feed).
Umfang: Ersetzt nur diesen Loading-Start-Block.
=== */
        if (typeof EventCards?.renderSkeleton === "function") {
            EventCards.renderSkeleton(8);
        }
        // Overlay beim normalen Laden bewusst aus (Error-State nutzt weiterhin showError()).
        this.showLoading(false);
        /* === END BLOCK: GS-01 LOADING START (skeleton only, no overlay) === */

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
    /* === BEGIN BLOCK: GS-01 SHOWLOADING (overlay + a11y) ===
Zweck: Loading als Overlay steuern (CSS: fixed) + aria-busy sauber setzen.
Umfang: Ersetzt nur showLoading(show).
=== */
    showLoading(show) {
        const loadingEl = document.getElementById("loading");
        if (!loadingEl) return;

        loadingEl.setAttribute("aria-busy", show ? "true" : "false");
        loadingEl.style.display = show ? "flex" : "none";
    },
    /* === END BLOCK: GS-01 SHOWLOADING (overlay + a11y) === */
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
    /* === BEGIN BLOCK: GS-01 ERROR STATE (retry) ===
Zweck: Fehlerzustand mit Retry-CTA (deterministisch: reload) im Loading-Overlay.
Umfang: Ersetzt nur showError(message).
=== */
    showError(message) {
        const loadingEl = document.getElementById("loading");
        if (!loadingEl) return;

        loadingEl.innerHTML = `
            <div class="error-message" role="alert">
                <p>⚠️ ${message}</p>
                <button type="button" class="empty-state__btn" id="error-retry-btn">Erneut versuchen</button>
            </div>
        `;

        loadingEl.setAttribute("aria-busy", "false");
        loadingEl.style.display = "flex";

        const btn = document.getElementById("error-retry-btn");
        if (btn) {
            btn.addEventListener("click", () => location.reload());
        }
    }
    /* === END BLOCK: GS-01 ERROR STATE (retry) === */
};

// App starten sobald DOM ready
/* === BEGIN BLOCK: APP BOOTSTRAP (DOM first, deterministic) ===
Zweck:
- App startet erst nach DOMReady
- Setzt Viewport-CSS-Variablen für Mobile-Browser-UI (visualViewport), damit Bottom-Actions sichtbar bleiben
Umfang:
- Ersetzt den App-Start am Dateiende + fügt Viewport-Observer ein
=== */
document.addEventListener("DOMContentLoaded", () => {
    /* === BEGIN BLOCK: VISUAL VIEWPORT CSS VARS (mobile bottom-safe) ===
    Zweck:
    - Im Mobile-Browser kann die sichtbare Fläche kleiner sein als window.innerHeight (Browser-UI, Tastatur).
    - Wir setzen CSS-Variablen:
      --vvh       = visualViewport.height (sichtbare Höhe)
      --vv-bottom = "verlorene" Pixel unten (Layout-Viewport minus sichtbarer Viewport)
    Umfang:
    - Aktiv in Browsern mit window.visualViewport
    === */
    const root = document.documentElement;

    const applyVV = () => {
        const vv = window.visualViewport;
        if (!vv) {
            root.style.setProperty("--vvh", `${window.innerHeight}px`);
            root.style.setProperty("--vv-bottom", "0px");
            return;
        }

        // Bottom-Gap: wie viel vom Layout-Viewport unten NICHT sichtbar ist
        const bottomGap = Math.max(0, window.innerHeight - (vv.height + vv.offsetTop));

        root.style.setProperty("--vvh", `${vv.height}px`);
        root.style.setProperty("--vv-bottom", `${bottomGap}px`);
    };

    applyVV();

    // Reagiert auf UI-Bar rein/raus, Rotation, Tastatur etc.
    if (window.visualViewport) {
        window.visualViewport.addEventListener("resize", applyVV, { passive: true });
        window.visualViewport.addEventListener("scroll", applyVV, { passive: true });
    }
    window.addEventListener("resize", applyVV, { passive: true });

    /* === END BLOCK: VISUAL VIEWPORT CSS VARS (mobile bottom-safe) === */

    /* === BEGIN BLOCK: GS-01.5 OFFLINE INDICATOR (minimal JS; toast on transitions) ===
    Zweck:
    - Persistentes Badge solange offline (rein informativ)
    - Toast nur bei Zustandswechsel (online->offline / offline->online)
    Umfang:
    - Erzeugt DOM-Hosts (Badge + Toast) dynamisch (kein index.html Patch nötig)
    - Setzt html.is-offline Klasse + zeigt Toast zeitlich begrenzt
    === */
    (function initOfflineIndicator(){
        const root = document.documentElement;

        // Hosts (UI-only, fixed overlay; no layout impact)
        const badge = document.createElement("div");
        badge.className = "offline-badge";
        badge.textContent = "Offline – gespeicherte Daten (ggf. nicht aktuell)";

        const toast = document.createElement("div");
        toast.className = "offline-toast";
        toast.setAttribute("role", "status");
        toast.setAttribute("aria-live", "polite");

        document.body.appendChild(badge);
        document.body.appendChild(toast);

        // Badge unterhalb der Top-Stack (Suche + Pills) verankern, ohne Layout-Shift
        const setBadgeTop = () => {
            const topStack = document.querySelector(".top-stack");
            if (!topStack) return;
            const r = topStack.getBoundingClientRect();
            // r.bottom ist bereits viewport-relativ; fixed top erwartet px im viewport
            const topPx = Math.round(r.bottom + 8); // 8px spacing
            document.documentElement.style.setProperty("--offline-badge-top", `${topPx}px`);
        };

        setBadgeTop();
        window.addEventListener("resize", setBadgeTop, { passive: true });
        window.addEventListener("scroll", setBadgeTop, { passive: true });

        let lastOnline = navigator.onLine;
        let toastTimer = null;

        const setOfflineUI = (isOnline) => {
            root.classList.toggle("is-offline", !isOnline);
        };

        const showToast = (msg) => {
            toast.textContent = msg;
            toast.classList.add("is-visible");

            if (toastTimer) window.clearTimeout(toastTimer);
            toastTimer = window.setTimeout(() => {
                toast.classList.remove("is-visible");
            }, 3200);
        };

        // Initial state (no toast)
        setOfflineUI(lastOnline);

        window.addEventListener("offline", () => {
            if (lastOnline === true) {
                lastOnline = false;
                setOfflineUI(false);
                showToast("Offline – gespeicherte Daten");
            } else {
                setOfflineUI(false);
            }
        }, { passive: true });

        window.addEventListener("online", () => {
            if (lastOnline === false) {
                lastOnline = true;
                setOfflineUI(true);
                showToast("Wieder online");
            } else {
                setOfflineUI(true);
            }
        }, { passive: true });
    })();
    /* === END BLOCK: GS-01.5 OFFLINE INDICATOR (minimal JS; toast on transitions) === */

    App.init();
});
/* === END BLOCK: APP BOOTSTRAP (DOM first, deterministic) === */



debugLog('Main module loaded - waiting for DOM ready');
















