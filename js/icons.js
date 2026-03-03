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
/* BEGIN BLOCK: ICON DEFINITIONS (LUCIDE-SHAPES, APP-WIDE)
Zweck:
- Ersetzt die bisherigen handgebauten Icons durch saubere, moderne Line-Icons (Lucide-Formen).
- Beibehaltung der Token-Steuerung (Größe/Stroke/Opacity) via CSS.
Umfang:
- UI-Icons + häufige Kategorie-Icons (Detailpanel & Cards).
*/
/* eslint-disable max-len */
  const ICONS = {
    // UI icons (used in cards, detailpanel actions, etc.)
    calendar: `
      <path d="M8 2v4" />
      <path d="M16 2v4" />
      <rect width="18" height="18" x="3" y="4" rx="2" />
      <path d="M3 10h18" />
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

    // Category icons (canonical keys)
    "cat-market": `
      <circle cx="8" cy="21" r="1" />
      <circle cx="19" cy="21" r="1" />
      <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12" />
    `,
    "cat-culture": `
      <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
      <path d="M13 5v2" />
      <path d="M13 17v2" />
      <path d="M13 11v2" />
    `,
    "cat-music": `
      <path d="M12 19v3" />
      <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
      <rect x="9" y="2" width="6" height="13" rx="3" />
    `,
    "cat-food": `
      <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
      <path d="M7 2v20" />
      <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
    `,
    "cat-kids": `
      <path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5" />
      <path d="M15 12h.01" />
      <path d="M19.38 6.813A9 9 0 0 1 20.8 10.2a2 2 0 0 1 0 3.6 9 9 0 0 1-17.6 0 2 2 0 0 1 0-3.6A9 9 0 0 1 12 3c2 0 3.5 1.1 3.5 2.5S14.6 8 13.5 8c-.8 0-1.5-.4-1.5-1" />
      <path d="M9 12h.01" />
    `,

    // Remaining categories keep existing until we map them explicitly (still same stroke/tokens via .ui-icon-svg)
    "cat-sport": `
      <path d="M6 8c0-2.2 1.8-4 4-4h1c2.2 0 4 1.8 4 4v.5" />
      <path d="M8 20h8" />
      <path d="M9 20V9.5a2.5 2.5 0 0 1 5 0V20" />
    `,
    "cat-nature": `
      <path d="M5 20c6-1 9-6 10-14 4 3 5 8 3 14-2 6-8 6-13 0z" />
      <path d="M8 18c2-2 5-5 7-10" />
    `,
    "cat-city": `
      <path d="M4.5 20V8.5l7.5-3 7.5 3V20" />
      <path d="M7.5 20V13h3v7" />
      <path d="M13.5 10h2M13.5 13h2M13.5 16h2" />
    `,
  };
/* eslint-enable max-len */
/* END BLOCK: ICON DEFINITIONS (LUCIDE-SHAPES, APP-WIDE) */

  // Category → icon key (canonical mapping; app-wide)
  // Canonical Category → IconKey
  // WICHTIG: Hier nur noch kanonische Kategorien aus FilterModule!
  const CATEGORY_ICON_KEY = {
    "Märkte & Feste": "cat-market",
    "Kinder & Familie": "cat-kids",
    "Musik & Bühne": "cat-music",
    "Kultur & Kunst": "cat-culture",
    "Sport & Bewegung": "cat-sport",
    "Natur & Draußen": "cat-nature",
    "Innenstadt & Leben": "cat-city",
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

  const categoryKey = (canonicalCategory) => {
    if (!canonicalCategory) return "calendar";
    return CATEGORY_ICON_KEY[canonicalCategory] || "calendar";
  };

  window.Icons = { svg, categoryKey };
})();
 /* END BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH) */
