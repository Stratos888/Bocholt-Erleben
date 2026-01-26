// BEGIN: FILE_HEADER_LOCATIONS_MODAL
// Datei: js/locations-modal.js
// Zweck:
// - Anzeige des Locations-Info-Modals
// - Erklärt Mehrwert & Einstieg für Locations
// - Modal-Interaktion (Open/Close, Overlay, ESC)
//
// Verantwortlich für:
// - Modal-DOM (Locations)
// - Öffnen/Schließen inkl. Scroll-Lock
// - Mehrere Trigger sauber unterstützen
//
// Nicht verantwortlich für:
// - Event-Filter oder Event-Darstellung
// - DetailPanel
// - Tariflogik oder Abrechnung
//
// Contract:
// - wird über definierte Trigger geöffnet
// - verhält sich mental identisch zum Detail-Panel
// END: FILE_HEADER_LOCATIONS_MODAL


const Locations = {
  map: {},
  ready: false,

  async init() {
    try {
      const res = await fetch("/data/locations.json", { cache: "no-store" });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      this.map = data && typeof data === "object" ? data : {};
      this.ready = true;
      debugLog?.("Locations loaded");
    } catch (err) {
      console.warn("Locations: failed to load locations.json", err);
      this.map = {};
      this.ready = false;
    }
  },

  normalize(name) {
    return String(name || "").trim();
  },

  getHomepage(locationName) {
    const key = this.normalize(locationName);
    if (!key) return "";
    return this.map[key] || "";
  },

  getMapsFallback(locationName) {
    const q = this.normalize(locationName);
    const query = q ? `${q} Bocholt` : "Bocholt";
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(query)}`;
  }
};
/* === BEGIN BLOCK: LOCATIONS GLOBAL EXPORT ===
Zweck: Locations API global verfügbar machen (für EventCards Location-Link).
Umfang: Exportiert Locations nach window.
=== */
window.Locations = Locations;
/* === END BLOCK: LOCATIONS GLOBAL EXPORT === */

// Auto-init (kein Framework, kein Build)
Locations.init();


