/* BEGIN BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH)
Zweck:
- Zentrale Lucide-nahe Icon-Registry als einzige Quelle für SVG-Icons.
- Appweit konsistente Icons: 24x24 viewBox, stroke=currentColor, round caps/joins.
- Tokens steuern Darstellung (Größe/Stroke/Opacity) ausschließlich via CSS.
Umfang:
- Exportiert window.Icons.svg(name, { className }), window.Icons.categoryKey(categoryName) und window.Icons.hydrate(root).
*/
(() => {
  "use strict";

  const escAttr = (s) => String(s || "").replace(/"/g, "&quot;");

  /* BEGIN BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE)
  Zweck:
  - Zentrale Registry mit offiziell ausgerichteten Lucide-Formen für UI-Icons und Kategorie-Icons.
  - Keine Lucide-nahen Eigenformen mehr für die primären sichtbaren Projekt-Icons.
  Umfang:
  - UI-Icons + kanonische Kategorie-Icons für Cards, Detailpanel, Hero, Header und Tabbar.
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
      <path d="m21 21-4.3-4.3" />
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
      <line x1="8.59" x2="15.42" y1="13.51" y2="17.49" />
      <line x1="15.41" x2="8.59" y1="6.51" y2="10.49" />
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
    "utensils-crossed": `
      <path d="m16 2-1.5 1.5" />
      <path d="M17.5 3.5 19 2" />
      <path d="m21 8-5-5" />
      <path d="m3 22 9-9" />
      <path d="M6.5 8.5 10 12" />
      <path d="m8 7 8-8" />
      <path d="m14 8 1-1" />
      <path d="m7 21 1-1" />
      <path d="m5 19-3 3" />
      <path d="M14.5 12.5 22 20" />
    `,

    /* === BEGIN BLOCK: ICONS_CATEGORY_SET_OFFICIAL_LUCIDE_V2 | Zweck: Kategorie-Tokens werden auf offizielle bzw. offiziell ausgerichtete Lucide-Formen normalisiert | Umfang: ersetzt nur die Projekt-Kategorie-Token cat-market bis cat-city === */
    "cat-market": `
      <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
      <path d="M13 5v2" />
      <path d="M13 17v2" />
      <path d="M13 11v2" />
    `,
    "cat-culture": `
      <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
      <path d="M13 5v2" />
      <path d="M13 17v2" />
      <path d="M13 11v2" />
    `,
    "cat-music": `
      <path d="M9 18V5l12-2v13" />
      <circle cx="6" cy="18" r="3" />
      <circle cx="18" cy="16" r="3" />
    `,
    "cat-food": `
      <path d="m16 2-1.5 1.5" />
      <path d="M17.5 3.5 19 2" />
      <path d="m21 8-5-5" />
      <path d="m3 22 9-9" />
      <path d="M6.5 8.5 10 12" />
      <path d="m8 7 8-8" />
      <path d="m14 8 1-1" />
      <path d="m7 21 1-1" />
      <path d="m5 19-3 3" />
      <path d="M14.5 12.5 22 20" />
    `,
    "cat-kids": `
      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
      <circle cx="9" cy="7" r="4" />
      <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M16 3.13a4 4 0 0 1 0 7.75" />
    `,
    "cat-sport": `
      <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
    `,
    "cat-nature": `
      <path d="m17 14 3 3-3 3" />
      <path d="M8 17h12" />
      <path d="m7 14 5-5 5 5" />
      <path d="m12 2 5 5H7l5-5Z" />
      <path d="M12 17v5" />
    `,
    "cat-city": `
      <path d="M6 22V4c0-1.1.9-2 2-2h8c1.1 0 2 .9 2 2v18" />
      <path d="M6 12H4a2 2 0 0 0-2 2v8h20v-8a2 2 0 0 0-2-2h-2" />
      <path d="M10 6h4" />
      <path d="M10 10h4" />
      <path d="M10 14h4" />
      <path d="M10 18h4" />
    `,
    /* === END BLOCK: ICONS_CATEGORY_SET_OFFICIAL_LUCIDE_V2 === */
  };
  /* eslint-enable max-len */
  /* END BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE) */

  /* === BEGIN BLOCK: ICONS_CATEGORY_KEY_ALIASES_V2 | Zweck: hält die kanonischen Projektkategorien stabil auf den finalen Lucide-Token-Alias gemappt | Umfang: ersetzt nur CATEGORY_ICON_KEY === */
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
  /* === END BLOCK: ICONS_CATEGORY_KEY_ALIASES_V2 === */

  const svg = (name, { className = "" } = {}) => {
    const body = ICONS[name] || ICONS.external;
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
