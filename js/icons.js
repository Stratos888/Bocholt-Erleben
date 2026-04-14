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

  /* BEGIN BLOCK: ICON DEFINITIONS (LUCIDE-SHAPES, APP-WIDE)
  Zweck:
  - Saubere, moderne Line-Icons für Header, Hero, Filter, Tabbar, Detailpanel und Kategorien.
  - Beibehaltung der Token-Steuerung (Größe/Stroke/Opacity) via CSS.
  Umfang:
  - UI-Icons + häufige Kategorie-Icons (Detailpanel & Cards).
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
      <circle cx="11" cy="11" r="7" />
      <path d="m20 20-3.5-3.5" />
    `,
    plus: `
      <path d="M12 5v14" />
      <path d="M5 12h14" />
    `,
    smartphone: `
      <rect width="10" height="18" x="7" y="3" rx="2" />
      <path d="M11 17h2" />
    `,
    info: `
      <circle cx="12" cy="12" r="10" />
      <path d="M12 16v-4" />
      <path d="M12 8h.01" />
    `,
    download: `
      <path d="M12 3v10" />
      <path d="m8 9 4 4 4-4" />
      <path d="M5 17v1a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-1" />
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
      <circle cx="12" cy="12" r="9" />
      <path d="m16.24 7.76-2.12 6.36-6.36 2.12 2.12-6.36z" />
    `,

    /* === BEGIN BLOCK: ICONS_CATEGORY_SET_LUCIDE_CLEANUP_V1 | Zweck: ersetzt die schwächeren Kategorie-Icons global durch klarere, kleinere und ruhiger lesbare Formen | Umfang: ersetzt nur die Kategorie-Icon-Definitionen von cat-market bis cat-city === */
    "cat-market": `
      <path d="M4 9h16" />
      <path d="M5 9v10h14V9" />
      <path d="M4.5 9 6 5h12l1.5 4" />
      <path d="M9 19v-5h6v5" />
    `,
    "cat-culture": `
      <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
      <path d="M13 5v2" />
      <path d="M13 17v2" />
      <path d="M13 11v2" />
    `,
    "cat-music": `
      <path d="M9 18V5l10-2v13" />
      <circle cx="6" cy="18" r="3" />
      <circle cx="18" cy="16" r="3" />
    `,
    "cat-food": `
      <path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2" />
      <path d="M7 2v20" />
      <path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7" />
    `,
    "cat-kids": `
      <path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
      <circle cx="10" cy="7" r="3" />
      <path d="M20 21v-2a4 4 0 0 0-3-3.87" />
      <path d="M14 4.13a4 4 0 0 1 0 5.74" />
    `,
    "cat-sport": `
      <path d="M6 9v6" />
      <path d="M4 10v4" />
      <path d="M10 8v8" />
      <path d="M14 8v8" />
      <path d="M18 9v6" />
      <path d="M20 10v4" />
      <path d="M10 12h4" />
    `,
    "cat-nature": `
      <path d="M6 20C17 20 20 8 20 4 16 4 6 7 6 20Z" />
      <path d="M6 20c4-6 8-10 14-16" />
    `,
    "cat-city": `
      <path d="M3 21h18" />
      <path d="M5 21V7l7-4 7 4v14" />
      <path d="M9 9h.01" />
      <path d="M9 13h.01" />
      <path d="M9 17h.01" />
      <path d="M15 9h.01" />
      <path d="M15 13h.01" />
      <path d="M15 17h.01" />
    `,
    /* === END BLOCK: ICONS_CATEGORY_SET_LUCIDE_CLEANUP_V1 === */
  };
  /* eslint-enable max-len */
  /* END BLOCK: ICON DEFINITIONS (LUCIDE-SHAPES, APP-WIDE) */

  /* === BEGIN BLOCK: ICONS_CATEGORY_KEY_ALIASES_V1 | Zweck: ergänzt neben den Event-Kategorien auch die aktuellen Activity-Kategorien als globale kanonische Icon-Aliases | Umfang: ersetzt nur CATEGORY_ICON_KEY === */
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
  /* === END BLOCK: ICONS_CATEGORY_KEY_ALIASES_V1 === */

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
