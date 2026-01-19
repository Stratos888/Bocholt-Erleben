/**
 * FILTER.JS - Such- und Filter-Modul
 * 
 * Ermöglicht Textsuche und Filter nach Location, Kategorie und Zeitraum.
 */

const FilterModule = {
    allEvents: [],
    filteredEvents: [],
    
    filters: {
        searchText: '',
        location: '',
        kategorie: '',
        zeitraum: 'alle'
    },

    /**
     * Init: Event Listeners registrieren
     */
    init(events) {
        this.allEvents = events;
        this.filteredEvents = events;
        
        // Input Elements
        const searchInput = document.getElementById('search-input');
        const locationSelect = document.getElementById('location-filter');
        const kategorieSelect = document.getElementById('kategorie-filter');
        const zeitraumSelect = document.getElementById('zeitraum-filter');
        const resetBtn = document.getElementById('reset-filters');

        if (!searchInput || !locationSelect || !kategorieSelect || !zeitraumSelect) {
            console.error('Filter elements not found!');
            return;
        }

        // Dropdowns befüllen
        this.populateLocationFilter(locationSelect);
        this.populateKategorieFilter(kategorieSelect);

        // Event Listeners
        searchInput.addEventListener('input', (e) => {
            this.filters.searchText = e.target.value.toLowerCase();
            this.applyFilters();
        });

        locationSelect.addEventListener('change', (e) => {
            this.filters.location = e.target.value;
            this.applyFilters();
        });

        kategorieSelect.addEventListener('change', (e) => {
            this.filters.kategorie = e.target.value;
            this.applyFilters();
        });

        zeitraumSelect.addEventListener('change', (e) => {
            this.filters.zeitraum = e.target.value;
            this.applyFilters();
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetFilters());
        }

        debugLog('Filter module initialized');
    },

    /**
     * Location-Dropdown befüllen
     */
    populateLocationFilter(select) {
        const locations = [...new Set(this.allEvents.map(e => e.location).filter(Boolean))];
        
        select.innerHTML = '<option value="">Alle Locations</option>';
        locations.forEach(loc => {
            const option = document.createElement('option');
            option.value = loc;
            option.textContent = loc;
            select.appendChild(option);
        });
    },

    /**
     * Kategorie-Dropdown befüllen
     */
    populateKategorieFilter(select) {
        const kategorien = [...new Set(this.allEvents.map(e => e.kategorie).filter(Boolean))];
        
        select.innerHTML = '<option value="">Alle Kategorien</option>';
        kategorien.forEach(kat => {
            const option = document.createElement('option');
            option.value = kat;
            option.textContent = kat;
            select.appendChild(option);
        });
    },

    /**
     * Filter anwenden
     */
    applyFilters() {
        debugLog('Applying filters:', this.filters);

        this.filteredEvents = this.allEvents.filter(event => {
            // Textsuche
            if (this.filters.searchText) {
                const searchableText = `${event.eventName} ${event.beschreibung} ${event.location}`.toLowerCase();
                if (!searchableText.includes(this.filters.searchText)) {
                    return false;
                }
            }

            // Location Filter
            if (this.filters.location && event.location !== this.filters.location) {
                return false;
            }

            // Kategorie Filter
            if (this.filters.kategorie && event.kategorie !== this.filters.kategorie) {
                return false;
            }

            // Zeitraum Filter
            if (this.filters.zeitraum !== 'alle') {
                const eventDate = new Date(event.datum);
                const now = new Date();
                now.setHours(0, 0, 0, 0);

                switch (this.filters.zeitraum) {
                    case 'diese-woche':
                        const weekEnd = new Date(now);
                        weekEnd.setDate(weekEnd.getDate() + 7);
                        if (eventDate < now || eventDate > weekEnd) return false;
                        break;
                    
                    case 'dieses-wochenende':
                        const dayOfWeek = now.getDay();
                        const daysUntilFriday = (5 - dayOfWeek + 7) % 7;
                        const friday = new Date(now);
                        friday.setDate(now.getDate() + daysUntilFriday);
                        const sunday = new Date(friday);
                        sunday.setDate(friday.getDate() + 2);
                        if (eventDate < friday || eventDate > sunday) return false;
                        break;
                    
                    case 'naechster-monat':
                        const nextMonth = new Date(now);
                        nextMonth.setMonth(nextMonth.getMonth() + 1);
                        const monthAfter = new Date(nextMonth);
                        monthAfter.setMonth(monthAfter.getMonth() + 1);
                        if (eventDate < nextMonth || eventDate > monthAfter) return false;
                        break;
                }
            }

            return true;
        });

        // UI aktualisieren
        this.updateUI();
        
        debugLog(`Filtered: ${this.filteredEvents.length} of ${this.allEvents.length} events`);
    },

    /**
     * UI aktualisieren
     */
    updateUI() {
        // Counter aktualisieren
        const counter = document.getElementById('filter-counter');
        if (counter) {
            counter.textContent = `${this.filteredEvents.length} von ${this.allEvents.length} Events`;
        }

        // Module aktualisieren
        if (CONFIG.features.showCalendar) {
            CalendarModule.refresh(this.filteredEvents);
        }

        if (CONFIG.features.showEventCards) {
            EventCards.refresh(this.filteredEvents);
        }
    },

    /**
     * Filter zurücksetzen
     */
    resetFilters() {
        this.filters = {
            searchText: '',
            location: '',
            kategorie: '',
            zeitraum: 'alle'
        };

        // Inputs zurücksetzen
        document.getElementById('search-input').value = '';
        document.getElementById('location-filter').value = '';
        document.getElementById('kategorie-filter').value = '';
        document.getElementById('zeitraum-filter').value = 'alle';

        this.filteredEvents = this.allEvents;
        this.updateUI();

        debugLog('Filters reset');
    },

    /**
     * Events neu laden (z.B. nach Airtable-Update)
     */
    refresh(events) {
        this.allEvents = events;
        this.applyFilters();
    }
};

debugLog('Filter module loaded');
