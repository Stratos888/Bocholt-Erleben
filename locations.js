/**
 * LOCATIONS.JS – Location → Homepage Mapping
 * Lädt /data/locations.json und liefert URLs für Location-Namen.
 */

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

// Auto-init (kein Framework, kein Build)
Locations.init();
