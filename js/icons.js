/* BEGIN BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH)
Zweck:
- Zentrale Icon-Registry als einzige Quelle für SVG-Icons (state-of-the-art line icons).
- Appweit konsistente Icons: 24x24 viewBox, stroke=currentColor, round caps/joins.
- Tokens steuern Darstellung (Größe/Stroke/Opacity) ausschließlich via CSS.
Umfang:
- Exportiert window.Icons.svg(name, { className }) und window.Icons.categoryKey(categoryName).
*/
(() => {
  "use strict";

  const escAttr = (s) => String(s || "").replace(/"/g, "&quot;");

  // Keep the set small & curated: only what the app uses.
  // Rules: 24x24 grid, fill="none", stroke="currentColor", round caps/joins.
  const ICONS = {
    // UI icons
    calendar: `
      <path d="M7 2v3M17 2v3" />
      <path d="M3.5 9h17" />
      <path d="M6 5h12a2.5 2.5 0 0 1 2.5 2.5v11A2.5 2.5 0 0 1 18 21H6A2.5 2.5 0 0 1 3.5 18.5v-11A2.5 2.5 0 0 1 6 5z" />
      <path d="M7.5 13h3M7.5 16h6" />
    `,
    pin: `
      <path d="M12 21s6-5.1 6-10a6 6 0 1 0-12 0c0 4.9 6 10 6 10z" />
      <path d="M12 11.25h0" />
    `,
    external: `
      <path d="M10 14l8-8" />
      <path d="M13 6h5v5" />
      <path d="M19 13v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h6" />
    `,
    share: `
      <path d="M12 3a7 7 0 0 1 7 7c0 3.9-3.1 7-7 7H8l-3.5 3.5.9-4A7 7 0 0 1 12 3z" />
      <path d="M9 10h6M9 13h4" />
    `,

    // Category icons (clean, app-like, consistent)
    "cat-market": `
      <path d="M6.5 10.5l1.2 9.5h8.6l1.2-9.5" />
      <path d="M8 10.5V9a4 4 0 0 1 8 0v1.5" />
      <path d="M5.5 10.5h13" />
    `,
    "cat-culture": `
      <path d="M4 16c2.5-2 4.5-3 8-3s5.5 1 8 3" />
      <path d="M7 10c.8-1 1.9-1.5 3.3-1.5 1.3 0 2.4.5 3.2 1.5" />
      <path d="M9.5 11.5h0M14.5 11.5h0" />
      <path d="M6 17.5V19M18 17.5V19" />
    `,
    "cat-music": `
      <path d="M12 4v11" />
      <path d="M12 4l8-2v11" />
      <path d="M9 18a2.5 2.5 0 1 0 0 .1" />
      <path d="M17 16a2.5 2.5 0 1 0 0 .1" />
    `,
    "cat-food": `
      <path d="M7 3v8M9 3v8M5 3v8" />
      <path d="M7 11v10" />
      <path d="M15 3v18" />
      <path d="M15 7c0-2 1-4 3-4v8c-2 0-3-2-3-4z" />
    `,
    "cat-sport": `
      <path d="M15 6a2 2 0 1 1-4 0 2 2 0 0 1 4 0z" />
      <path d="M10 20l2-6 3 2 2 4" />
      <path d="M6 13l4-2 2-3 4 2" />
    `,
    "cat-kids": `
      <path d="M8 10a4 4 0 0 1 8 0" />
      <path d="M7 20c.5-3 2.5-5 5-5s4.5 2 5 5" />
      <path d="M9 12h0M15 12h0" />
    `,
    "cat-nature": `
      <path d="M6 19c7-1 12-6 12-13-7 1-12 6-12 13z" />
      <path d="M6 19c3-4 7-7 12-9" />
    `,
    "cat-city": `
      <path d="M4 20V9l5-2v13" />
      <path d="M9 20V6l6-2v16" />
      <path d="M15 20v-8l5 2v6" />
    `,
  };

  // Category → icon key (canonical mapping; app-wide)
  const CATEGORY_ICON_KEY = {
    "Musik": "cat-music",
    "Musik & Bühne": "cat-music",
    "Kultur": "cat-culture",
    "Kultur & Kunst": "cat-culture",
    "Essen & Trinken": "cat-food",
    "Sport": "cat-sport",
    "Sport & Bewegung": "cat-sport",
    "Familie": "cat-kids",
    "Kinder & Familie": "cat-kids",
    "Natur & Draußen": "cat-nature",
    "Innenstadt & Leben": "cat-city",
    "Märkte & Feste": "cat-market",
    "Märkte": "cat-market",
    "Markt": "cat-market",
  };

  const svg = (name, { className = "" } = {}) => {
    const body = ICONS[name] || ICONS.external;
    const cls = `ui-icon-svg${className ? " " + escAttr(className) : ""}`;

    // NOTE: Styling is driven by CSS tokens (size/stroke/color/opacity).
    return `
      <svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        ${body}
      </svg>
    `;
  };

  const categoryKey = (categoryName) => {
    const c = String(categoryName || "").trim();
    return CATEGORY_ICON_KEY[c] || "calendar";
  };

  window.Icons = { svg, categoryKey };
})();
 /* END BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH) */
