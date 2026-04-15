/* BEGIN BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH)
Zweck:
- Zentrale offizielle Lucide-Registry als einzige Quelle für SVG-Icons.
- Appweit konsistente Icons: 24x24 viewBox, stroke=currentColor, round caps/joins.
- Kategorie-Tokens bleiben stabil, werden intern aber nur noch auf offizielle Lucide-Basisicons aufgelöst.
Umfang:
- Exportiert window.Icons.svg(name, { className }), window.Icons.categoryKey(categoryName) und window.Icons.hydrate(root).
*/
(() => {
  "use strict";

  const escAttr = (s) => String(s || "").replace(/"/g, "&quot;");

  /* BEGIN BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE)
  Zweck:
  - Nur offizielle Lucide-Basisicons in der Registry halten.
  - Projekt-Kategorie-Tokens nicht mehr als eigene SVG-Formen zeichnen, sondern nur noch per Alias auf offizielle Lucide-Icons mappen.
  Umfang:
  - UI-Icons + offizielle Lucide-Basisicons für Cards, Detailpanel, Hero, Header und Tabbar.
  */
  /* eslint-disable max-len */
  const ICONS = {
    calendar: `
      <path d="M8 2v4" />
      <path d="M16 2v4" />
      <rect width="18" height="18" x="3" y="4" rx="2" />
      <path d="M3 10h18" />
    `,
    "calendar-days": `
      <path d="M8 2v4" />
      <path d="M16 2v4" />
      <rect width="18" height="18" x="3" y="4" rx="2" />
      <path d="M3 10h18" />
      <path d="M8 14h.01" />
      <path d="M12 14h.01" />
      <path d="M16 14h.01" />
      <path d="M8 18h.01" />
      <path d="M12 18h.01" />
      <path d="M16 18h.01" />
    `,
    search: `
      <circle cx="11" cy="11" r="8" />
      <path d="m21 21-4.34-4.34" />
    `,
    plus: `
      <path d="M12 5v14" />
      <path d="M5 12h14" />
    `,
    smartphone: `
      <rect width="14" height="20" x="5" y="2" rx="2" ry="2" />
      <path d="M12 18h.01" />
    `,
    info: `
      <circle cx="12" cy="12" r="10" />
      <path d="M12 16v-4" />
      <path d="M12 8h.01" />
    `,
    download: `
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
      <path d="m7 10 5 5 5-5" />
      <path d="M12 15V3" />
    `,
    x: `
      <path d="M6 6l12 12" />
      <path d="M18 6 6 18" />
    `,
    pin: `
      <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
      <circle cx="12" cy="10" r="3" />
    `,
    external: `
      <path d="M15 3h6v6" />
      <path d="M10 14 21 3" />
      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" />
    `,
    share: `
      <circle cx="18" cy="5" r="3" />
      <circle cx="6" cy="12" r="3" />
      <circle cx="18" cy="19" r="3" />
      <path d="m8.59 13.51 6.83 3.98" />
      <path d="m15.41 6.51-6.82 3.98" />
    `,
    compass: `
      <circle cx="12" cy="12" r="10" />
      <polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76" />
    `,
    ticket: `
      <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
      <path d="M13 5v2" />
      <path d="M13 17v2" />
      <path d="M13 11v2" />
    `,
    music: `
      <path d="M9 18V5l12-2v13" />
      <circle cx="6" cy="18" r="3" />
      <circle cx="18" cy="16" r="3" />
    `,
    users: `
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    `,
    activity: `
      <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
    `,
  };
  /* eslint-enable max-len */
  /* END BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE) */

  /* === BEGIN BLOCK: ICON TOKEN ALIASES (CATEGORY TOKENS → OFFICIAL LUCIDE) | Zweck: hält bestehende Projekt-Tokens stabil, rendert intern aber nur noch offizielle Lucide-Basisicons | Umfang: neue zentrale Alias-Map für cat-* Tokens === */
  const ICON_ALIASES = {
    "cat-market": "ticket",
    "cat-culture": "ticket",
    "cat-music": "music",
    "cat-food": "ticket",
    "cat-kids": "users",
    "cat-sport": "activity",
    "cat-nature": "compass",
    "cat-city": "pin",
  };
  /* === END BLOCK: ICON TOKEN ALIASES (CATEGORY TOKENS → OFFICIAL LUCIDE) === */

  /* === BEGIN BLOCK: ICONS_CATEGORY_KEY_ALIASES_V3 | Zweck: hält die kanonischen Projektkategorien stabil auf bestehende Projekt-Tokens gemappt; die eigentliche Form kommt ausschließlich über ICON_ALIASES aus offiziellen Lucide-Basisicons | Umfang: ersetzt nur CATEGORY_ICON_KEY === */
  const CATEGORY_ICON_KEY = {
    "Märkte & Feste": "cat-market",
    "Kinder & Familie": "cat-kids",
    "Freizeit & Familie": "cat-kids",
    "Musik & Bühne": "cat-music",
    "Kultur & Kunst": "cat-culture",
    "Kultur": "cat-culture",
    "Sport & Bewegung": "cat-sport",
    "Natur & Draußen": "cat-nature",
    "Innenstadt & Leben": "cat-city",
  };
  /* === END BLOCK: ICONS_CATEGORY_KEY_ALIASES_V3 === */

  const svg = (name, { className = "" } = {}) => {
    const resolvedName = ICON_ALIASES[name] || name;
    const body = ICONS[resolvedName] || ICONS.external;
    const cls = `ui-icon-svg${className ? " " + escAttr(className) : ""}`;

    return `
      <svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        ${body}
      </svg>
    `;
  };

  const categoryKey = (canonicalCategory) => {
    if (!canonicalCategory) return "calendar";
    return CATEGORY_ICON_KEY[canonicalCategory] || "calendar";
  };

  const hydrate = (root = document) => {
    if (!root || typeof root.querySelectorAll !== "function") return;

    root.querySelectorAll("[data-ui-icon]").forEach((node) => {
      const name = String(node.getAttribute("data-ui-icon") || "").trim();
      if (!name) return;

      const svgClass = String(node.getAttribute("data-ui-icon-svg-class") || "").trim();
      node.innerHTML = svg(name, { className: svgClass });
    });
  };

  window.Icons = { svg, categoryKey, hydrate };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => hydrate(document), { once: true });
  } else {
    hydrate(document);
  }
})();
/* END BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH) */

  const categoryKey = (canonicalCategory) => {
    if (!canonicalCategory) return "calendar";
    return CATEGORY_ICON_KEY[canonicalCategory] || "calendar";
  };

  const hydrate = (root = document) => {
    if (!root || typeof root.querySelectorAll !== "function") return;

    root.querySelectorAll("[data-ui-icon]").forEach((node) => {
      const name = String(node.getAttribute("data-ui-icon") || "").trim();
      if (!name) return;

      const svgClass = String(node.getAttribute("data-ui-icon-svg-class") || "").trim();
      node.innerHTML = svg(name, { className: svgClass });
    });
  };

  window.Icons = { svg, categoryKey, hydrate };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => hydrate(document), { once: true });
  } else {
    hydrate(document);
  }
})();
/* END BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH) */
