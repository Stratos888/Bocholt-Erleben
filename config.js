/**
 * CONFIG.JS - Zentrale Konfiguration
 * 
 * Alle wichtigen Einstellungen an einem Ort.
 * Bei Ã„nderungen nur diese Datei anpassen!
 */

const CONFIG = {
    // Airtable API Einstellungen
    airtable: {
        apiKey: 'patjEbizKDbioSqFL.4fb839ea9966f9c4c6a62d2322f4bc1fe71f661c8cd3ec724d9257f31eb5cd98',
        baseId: 'appiGXETyKcCcypiy',
        tableName: 'Table 1',
        viewName: 'Grid view'
    },

    // Airtable Feld-Namen (wie in deiner Airtable Base)
    fields: {
        eventName: 'Event-Name',
        datum: 'Datum',
        uhrzeit: 'Uhrzeit',
        location: 'Location',
        beschreibung: 'Beschreibung',
        eintritt: 'Eintritt',
        link: 'Link',
        status: 'Status',
        locationBild: 'Location-Bild',
        kategorie: 'Kategorie'  // NEU: Kategorie-Feld
    },

    // UI Einstellungen
    ui: {
        eventsPerPage: 50,              // Wie viele Events laden
        calendarHeight: 'auto',         // Kalender-HÃ¶he
        showPastEvents: false,          // Vergangene Events anzeigen?
        defaultView: 'dayGridMonth'     // Kalender: Monat/Woche/Tag
    },

    // Feature Flags (Features ein/ausschalten)
    features: {
        showCalendar: false,             // Kalender-Ansicht zeigen
        showEventCards: true,           // Event-Cards zeigen
        showDetailPanel: true,          // Detail-Panel bei Click
        showFilters: false               // Such- und Filter-Funktionen (NEU!)
    },

    // Debug Mode (fÃ¼r Entwicklung)
    debug: true  // Auf false setzen wenn live!
};

// Helper: Log nur wenn debug = true
function debugLog(message, data = null) {
    if (CONFIG.debug) {
        console.log(`[DEBUG] ${message}`, data || '');
    }
}

// Helper: Datum in deutsches Format
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('de-DE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

// Helper: Check ob Event in Zukunft
function isUpcoming(dateString) {
    const eventDate = new Date(dateString);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return eventDate >= today;
}

debugLog('Config loaded successfully', CONFIG);
