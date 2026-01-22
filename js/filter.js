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
    /* === BEGIN BLOCK: FILTER INIT (Top-App Pills + Sheets, search-filter id) ===
Zweck: Umstellung von Dropdowns/legacy IDs auf Search + strukturierte Filter-Pills (Zeit/Kategorie).
Umfang: Ersetzt FilterModule.init(events) vollständig.
=== */
init(events) {
  this.allEvents = events;
  this.filteredEvents = events;

  // Neue UI-Elemente (HTML)
  const searchInput = document.getElementById('search-filter');

  const timePill = document.getElementById('filter-time-pill');
  const timeValue = document.getElementById('filter-time-value');
  const timeSheet = document.getElementById('sheet-time');

  const catPill = document.getElementById('filter-category-pill');
  const catValue = document.getElementById('filter-category-value');
  const catSheet = document.getElementById('sheet-category');

  const resetPill = document.getElementById('filter-reset-pill');

  // Minimal-Guard: Filter UI muss existieren
  if (!searchInput || !timePill || !timeValue || !timeSheet || !catPill || !catValue || !catSheet || !resetPill) {
    console.error('Filter UI elements not found (search-filter / filter pills / sheets).');
    return;
  }

  // Legacy-Felder neutralisieren (Ort später)
  this.filters.location = '';

  // Search
  searchInput.addEventListener('input', (e) => {
    this.filters.searchText = (e.target.value || '').toLowerCase();
    this.applyFilters();
    this.updateFilterBarUI(timeValue, catValue, resetPill);
  });

  // Öffnen der Sheets
  const openSheet = (sheetEl) => {
    sheetEl.hidden = false;
    document.body.classList.add('is-sheet-open');
  };
  const closeSheet = (sheetEl) => {
    sheetEl.hidden = true;
    // Wenn beide Sheets zu sind, Scroll lock entfernen
    if (timeSheet.hidden && catSheet.hidden) {
      document.body.classList.remove('is-sheet-open');
    }
  };

  timePill.addEventListener('click', () => openSheet(timeSheet));
  catPill.addEventListener('click', () => openSheet(catSheet));

  // Close-Handler (Overlay + X)
  const wireSheetClose = (sheetEl) => {
    sheetEl.addEventListener('click', (e) => {
      const t = e.target;
      if (t && t.hasAttribute && t.hasAttribute('data-close-sheet')) closeSheet(sheetEl);
    });
  };
  wireSheetClose(timeSheet);
  wireSheetClose(catSheet);

  // ESC schließen (Top-App expected)
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (!timeSheet.hidden) closeSheet(timeSheet);
    if (!catSheet.hidden) closeSheet(catSheet);
  });

  // Zeit-Auswahl (mapping auf neue Werte)
  timeSheet.querySelectorAll('[data-time]').forEach(btn => {
    btn.addEventListener('click', () => {
      const v = btn.getAttribute('data-time') || 'all';
      this.filters.zeitraum = v; // 'all' | 'today' | 'weekend' | 'soon'
      this.applyFilters();
      this.setActiveOption(timeSheet, btn);
      this.updateFilterBarUI(timeValue, catValue, resetPill);
      closeSheet(timeSheet);
    });
  });

  // Kategorie-Auswahl (Single)
  catSheet.querySelectorAll('[data-category]').forEach(btn => {
    btn.addEventListener('click', () => {
      const v = btn.getAttribute('data-category'); // '' = Alle
      this.filters.kategorie = (v ?? '');
      this.applyFilters();
      this.setActiveOption(catSheet, btn);
      this.updateFilterBarUI(timeValue, catValue, resetPill);
      closeSheet(catSheet);
    });
  });

  // Reset
  resetPill.addEventListener('click', () => {
    this.resetFilters();
    // UI zurücksetzen
    searchInput.value = '';
    this.setActiveOption(timeSheet, timeSheet.querySelector('[data-time="all"]'));
    this.setActiveOption(catSheet, catSheet.querySelector('[data-category=""]'));
    this.updateFilterBarUI(timeValue, catValue, resetPill);
  });

  // Initial UI State
  this.filters.searchText = '';
  this.filters.kategorie = '';
  this.filters.zeitraum = 'all';
  this.applyFilters();
  this.updateFilterBarUI(timeValue, catValue, resetPill);

  debugLog('Filter module initialized (Top-App pills + sheets)');
},
/* === END BLOCK: FILTER INIT (Top-App Pills + Sheets, search-filter id) === */


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

            /* === BEGIN BLOCK: ZEITFILTER MAPPING (all/today/weekend/soon) ===
Zweck: Neue Zeitwerte aus UI-Sheet sauber filtern.
Umfang: Ersetzt nur den Zeitraum-Filter-Block in applyFilters().
=== */
// Zeitraum Filter (neue Werte)
if ((this.filters.zeitraum || 'all') !== 'all') {
  const eventDate = new Date(event.datum);
  const now = new Date();
  now.setHours(0, 0, 0, 0);

  // Ungültige Daten raus
  if (Number.isNaN(eventDate.getTime())) return false;

  const endOfToday = new Date(now);
  endOfToday.setDate(endOfToday.getDate() + 1);

  switch (this.filters.zeitraum) {
    case 'today': {
      // heute: [now, morgen 00:00)
      if (eventDate < now || eventDate >= endOfToday) return false;
      break;
    }

    case 'weekend': {
      // nächstes Wochenende: Fr 00:00 bis Mo 00:00
      const day = now.getDay(); // 0=So ... 6=Sa
      const daysUntilFriday = (5 - day + 7) % 7;
      const friday = new Date(now);
      friday.setDate(now.getDate() + daysUntilFriday);

      const monday = new Date(friday);
      monday.setDate(friday.getDate() + 3);

      if (eventDate < friday || eventDate >= monday) return false;
      break;
    }

    case 'soon': {
      // demnächst: nächste 14 Tage (inkl. heute)
      const soonEnd = new Date(now);
      soonEnd.setDate(now.getDate() + 14);
      if (eventDate < now || eventDate >= soonEnd) return false;
      break;
    }
  }
}
/* === END BLOCK: ZEITFILTER MAPPING (all/today/weekend/soon) === */


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

        /* === BEGIN BLOCK: FILTER UI HELPERS (active state + pill labels) ===
Zweck: UI-State sauber halten: aktive Option markieren, Pill-Labels updaten, Reset nur bei aktiven Filtern zeigen.
Umfang: Fügt setActiveOption() + updateFilterBarUI() hinzu.
=== */
setActiveOption(sheetEl, activeBtn){
  if (!sheetEl) return;
  sheetEl.querySelectorAll('.filter-option').forEach(b => b.classList.remove('is-active'));
  if (activeBtn) activeBtn.classList.add('is-active');
},

updateFilterBarUI(timeValueEl, catValueEl, resetEl){
  const timeMap = {
    all: 'Alle',
    today: 'Heute',
    weekend: 'Wochenende',
    soon: 'Demnächst'
  };

  const timeKey = this.filters.zeitraum || 'all';
  const cat = (this.filters.kategorie || '').trim();

  if (timeValueEl) timeValueEl.textContent = timeMap[timeKey] || 'Alle';
  if (catValueEl) catValueEl.textContent = cat ? cat : 'Alle';

  const hasActive =
    (this.filters.searchText && this.filters.searchText.trim().length > 0) ||
    (timeKey !== 'all') ||
    (cat.length > 0);

  if (resetEl) resetEl.hidden = !hasActive;
},
/* === END BLOCK: FILTER UI HELPERS (active state + pill labels) === */

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

    setSearchText(text) {
   const searchInput = document.getElementById('search-filter');

    const normalized = (text || '').trim();

    this.filters.searchText = normalized.toLowerCase();

    if (searchInput) {
        searchInput.value = normalized; // ersetzt komplett
    }

    this.applyFilters();
},

    /**
     * Filter zurücksetzen
     */
   /* === BEGIN BLOCK: RESET FILTERS (Top-App pills) ===
Zweck: Reset ohne Dropdowns; setzt interne Defaults zurück.
Umfang: Ersetzt FilterModule.resetFilters() vollständig.
=== */
resetFilters() {
  this.filters = {
    searchText: '',
    location: '',
    kategorie: '',
    zeitraum: 'all'
  };

  this.filteredEvents = this.allEvents;
  this.updateUI();

  debugLog('Filters reset');
},
/* === END BLOCK: RESET FILTERS (Top-App pills) === */


    /**
     * Events neu laden (z.B. nach Airtable-Update)
     */
    refresh(events) {
        this.allEvents = events;
        this.applyFilters();
    }
};

debugLog('Filter module loaded');
