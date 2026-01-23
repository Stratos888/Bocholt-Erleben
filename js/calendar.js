// BEGIN: FILE_HEADER_CALENDAR
// Datei: js/calendar.js
// Zweck:
// - Kalenderansicht für Events (FullCalendar)
// - Nimmt Event-Liste entgegen und rendert sie im Kalender
// - Optional: Interaktion im Kalender (z. B. Klick → DetailPanel)
//
// Verantwortlich für:
// - Kalender-Initialisierung und -Rendering
// - Mapping: Event-Daten → Kalender-Events
//
// Nicht verantwortlich für:
// - Filter-State / Filter-UI
// - Event-Cards Rendering
// - Laden der Daten
//
// Contract:
// - init(events) bekommt vollständige oder bereits gefilterte Events (je nach main.js)
// - darf DetailPanel öffnen, aber nicht filtern
// END: FILE_HEADER_CALENDAR


const CalendarModule = {
    calendar: null,
    events: [],

    /**
     * Kalender initialisieren
     */
    init(events) {
        if (!CONFIG.features.showCalendar) {
            debugLog('Calendar disabled in config');
            return;
        }

        this.events = events;
        const calendarEl = document.getElementById('calendar');
        
        if (!calendarEl) {
            console.error('Calendar element not found!');
            return;
        }

        debugLog('Initializing calendar with events:', events);

        // FullCalendar erstellen
        this.calendar = new FullCalendar.Calendar(calendarEl, {
            // Einstellungen
            locale: 'de',
            firstDay: 1,  // Montag als erster Tag
            height: CONFIG.ui.calendarHeight,
            initialView: CONFIG.ui.defaultView,
            
            // Header Buttons
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,dayGridWeek'
            },

            // Button Texte auf Deutsch
            buttonText: {
                today: 'Heute',
                month: 'Monat',
                week: 'Woche',
                day: 'Tag'
            },

            // Events laden
            events: this.formatEventsForCalendar(events),

            // Event Click Handler
            eventClick: (info) => {
                if (CONFIG.features.showDetailPanel) {
                    const eventId = info.event.id;
                    const event = this.events.find(e => e.id === eventId);
                    if (event) {
                        DetailPanel.show(event);
                    }
                }
            },

            // Event Styling
            eventClassNames: (arg) => {
                return ['custom-event'];
            },

            // Datum Format
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                meridiem: false
            }
        });

        this.calendar.render();
        debugLog('Calendar rendered successfully');
    },

    /**
     * Events in FullCalendar Format umwandeln
     */
    formatEventsForCalendar(events) {
        return events.map(event => {
            // Datum + Uhrzeit kombinieren
            const startDateTime = this.combineDateTime(event.datum, event.uhrzeit);

            return {
                id: event.id,
                title: event.eventName,
                start: startDateTime,
                allDay: !event.uhrzeit,  // Ganztägig wenn keine Uhrzeit
                extendedProps: {
                    location: event.location,
                    beschreibung: event.beschreibung,
                    eintritt: event.eintritt,
                    link: event.link,
                    locationBild: event.locationBild
                }
            };
        });
    },

    /**
     * Datum + Uhrzeit zu ISO String kombinieren
     */
    combineDateTime(datum, uhrzeit) {
        if (!datum) return new Date().toISOString();

        // Datum ist im Format "2025-01-17"
        if (uhrzeit) {
            // Uhrzeit hinzufügen: "20:00 Uhr" → "20:00"
            const time = uhrzeit.replace(' Uhr', '').trim();
            return `${datum}T${time}:00`;
        }

        // Nur Datum, ohne Uhrzeit
        return datum;
    },

    /**
     * Kalender aktualisieren (wenn sich Events ändern)
     */
    refresh(events) {
        this.events = events;
        if (this.calendar) {
            this.calendar.removeAllEvents();
            this.calendar.addEventSource(this.formatEventsForCalendar(events));
            debugLog('Calendar refreshed with new events');
        }
    }
};

debugLog('Calendar module loaded');
