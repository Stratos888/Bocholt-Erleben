/* BEGIN BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH)
Zweck:
- Zentrale offizielle Lucide-Registry als einzige Quelle für SVG-Icons.
- Appweit konsistente Icons: 24x24 viewBox, stroke=currentColor, round caps/joins.
- Zentrale Alias- und Kategorie-Normalisierung, damit Cards, Detailpanel, Header, Hero, Filter und Activities dieselben Icon-Keys nutzen.
Umfang:
- Exportiert window.Icons.svg(name, { className }), window.Icons.resolve(name), window.Icons.categoryKey(categoryName), window.Icons.categoryMeta(categoryName) und window.Icons.hydrate(root).
*/
(() => {
  "use strict";

  const escAttr = (s) => String(s || "").replace(/"/g, "&quot;");

  const normalizeKey = (value) => String(value || "")
    .trim()
    .toLowerCase()
    .replace(/ä/g, "ae")
    .replace(/ö/g, "oe")
    .replace(/ü/g, "ue")
    .replace(/ß/g, "ss")
    .replace(/&/g, " und ")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

  /* BEGIN BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE)
  Zweck:
  - Nur offizielle Lucide-Basisicons in der Registry halten.
  - Alle appweiten UI-Icons über echte SVG-Nodes statt Textzeichen oder Emoji versorgen.
  Umfang:
  - UI-Icons + Kategorie-Basisicons für Cards, Detailpanel, Hero, Header, Filter und Tabbar.
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
    palette: `
      <circle cx="13.5" cy="6.5" r=".5" />
      <circle cx="17.5" cy="10.5" r=".5" />
      <circle cx="8.5" cy="7.5" r=".5" />
      <circle cx="6.5" cy="12.5" r=".5" />
      <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z" />
    `,
    trees: `
      <path d="M10 10v.2A3 3 0 0 1 8.9 16H5a3 3 0 0 1-1-5.8V10a3 3 0 0 1 6 0Z" />
      <path d="M7 16v6" />
      <path d="M13 19v3" />
      <path d="M12 19h8.3a1 1 0 0 0 .7-1.7L18 14h.3a1 1 0 0 0 .7-1.7L16 9h.2a1 1 0 0 0 .8-1.7L13 3l-1.4 1.5" />
    `,
    building: `
      <rect x="4" y="2" width="16" height="20" rx="2" ry="2" />
      <path d="M9 22v-4h6v4" />
      <path d="M8 6h.01" />
      <path d="M16 6h.01" />
      <path d="M12 6h.01" />
      <path d="M12 10h.01" />
      <path d="M12 14h.01" />
      <path d="M16 10h.01" />
      <path d="M16 14h.01" />
      <path d="M8 10h.01" />
      <path d="M8 14h.01" />
    `,
    "shopping-cart": `
      <circle cx="9" cy="21" r="1" />
      <circle cx="20" cy="21" r="1" />
      <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
    `,
    "chevron-right": `
      <path d="m9 18 6-6-6-6" />
    `,
    "chevron-left": `
      <path d="m15 18-6-6 6-6" />
    `,
    "message-square": `
      <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
    `,
    bug: `
      <path d="m8 2 1.88 1.88" />
      <path d="M14.12 3.88 16 2" />
      <path d="M9 7.13V6a3 3 0 1 1 6 0v1.13" />
      <path d="M12 20c-3.31 0-6-2.69-6-6V8h12v6c0 3.31-2.69 6-6 6" />
      <path d="M4 13H2" />
      <path d="M6 9H3" />
      <path d="M10 13h4" />
      <path d="M18 13h2" />
      <path d="M21 9h-3" />
    `,
    "triangle-alert": `
      <path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" />
      <path d="M12 9v4" />
      <path d="M12 17h.01" />
    `,
    lightbulb: `
      <path d="M15 14c.2-.6.6-1.1 1-1.5a6 6 0 1 0-8 0c.4.4.8.9 1 1.5" />
      <path d="M9 18h6" />
      <path d="M10 22h4" />
    `,
    check: `
      <path d="M20 6 9 17l-5-5" />
    `,
    "send-horizontal": `
      <path d="m3 3 3 9-3 9 19-9Z" />
      <path d="M6 12h16" />
    `
  };
  /* eslint-enable max-len */
  /* END BLOCK: ICON DEFINITIONS (OFFICIAL LUCIDE NODES, APP-WIDE) */

  /* === BEGIN BLOCK: ICON TOKEN ALIASES (SEMANTIC TOKENS → OFFICIAL LUCIDE) | Zweck: hält globale semantische Tokens stabil und rendert intern nur offizielle Lucide-Basisicons | Umfang: UI-Tokens, Navigations-Tokens und Kategorie-Tokens === */
  const ICON_ALIASES = {
    close: "x",
    reset: "x",
    website: "external",
    source: "external",
    location: "pin",
    "link-external": "external",
    feedback: "message-square",
    "feedback-idea": "lightbulb",
    "feedback-bug": "bug",
    "feedback-data": "triangle-alert",
    "feedback-submit": "send-horizontal",
    "feedback-success": "check",

    "app-install": "download",
    "app-device": "download",

    "disclosure-right": "chevron-right",
    "disclosure-left": "chevron-left",
    "filter-next": "chevron-right",
    "filter-prev": "chevron-left",

    "cat-market": "ticket",
    "cat-culture": "palette",
    "cat-music": "music",
    "cat-food": "shopping-cart",
    "cat-kids": "users",
    "cat-sport": "activity",
    "cat-nature": "trees",
    "cat-city": "building",
    "cat-business": "building",
    "cat-activity": "compass",
    "cat-default": "calendar"
  };
  /* === END BLOCK: ICON TOKEN ALIASES (SEMANTIC TOKENS → OFFICIAL LUCIDE) === */

  /* === BEGIN BLOCK: CATEGORY NORMALIZATION MAP (RAW + CANONICAL → STABLE TOKENS) | Zweck: sorgt dafür, dass Event-Cards, Detailpanel und Activities dieselben Kategorien global gleich auflösen | Umfang: alle aktuell im Datenbestand und UI relevanten Kategorien/Fallbacks === */
  const CATEGORY_META = {
    markt: { token: "cat-market", canonical: "Markt", family: "market" },
    maerkte: { token: "cat-market", canonical: "Märkte & Feste", family: "market" },
    "maerkte-feste": { token: "cat-market", canonical: "Märkte & Feste", family: "market" },
    "maerkte-und-feste": { token: "cat-market", canonical: "Märkte & Feste", family: "market" },
    feste: { token: "cat-market", canonical: "Märkte & Feste", family: "market" },
    highlights: { token: "cat-market", canonical: "Highlights", family: "market" },

    kinder: { token: "cat-kids", canonical: "Kinder & Familie", family: "kids" },
    familie: { token: "cat-kids", canonical: "Kinder & Familie", family: "kids" },
    "kinder-familie": { token: "cat-kids", canonical: "Kinder & Familie", family: "kids" },
    "kinder-und-familie": { token: "cat-kids", canonical: "Kinder & Familie", family: "kids" },
    "freizeit-familie": { token: "cat-kids", canonical: "Freizeit & Familie", family: "kids" },
    "freizeit-und-familie": { token: "cat-kids", canonical: "Freizeit & Familie", family: "kids" },
    freizeit: { token: "cat-kids", canonical: "Freizeit & Familie", family: "kids" },

    musik: { token: "cat-music", canonical: "Musik & Bühne", family: "music" },
    "musik-buehne": { token: "cat-music", canonical: "Musik & Bühne", family: "music" },
    "musik-und-buehne": { token: "cat-music", canonical: "Musik & Bühne", family: "music" },

    kultur: { token: "cat-culture", canonical: "Kultur & Kunst", family: "culture" },
    kunst: { token: "cat-culture", canonical: "Kultur & Kunst", family: "culture" },
    "kultur-kunst": { token: "cat-culture", canonical: "Kultur & Kunst", family: "culture" },
    "kultur-und-kunst": { token: "cat-culture", canonical: "Kultur & Kunst", family: "culture" },

    sport: { token: "cat-sport", canonical: "Sport & Bewegung", family: "sport" },
    bewegung: { token: "cat-sport", canonical: "Sport & Bewegung", family: "sport" },
    "sport-bewegung": { token: "cat-sport", canonical: "Sport & Bewegung", family: "sport" },
    "sport-und-bewegung": { token: "cat-sport", canonical: "Sport & Bewegung", family: "sport" },

    natur: { token: "cat-nature", canonical: "Natur & Draußen", family: "nature" },
    draussen: { token: "cat-nature", canonical: "Natur & Draußen", family: "nature" },
    "natur-draussen": { token: "cat-nature", canonical: "Natur & Draußen", family: "nature" },
    "natur-und-draussen": { token: "cat-nature", canonical: "Natur & Draußen", family: "nature" },

    innenstadt: { token: "cat-city", canonical: "Innenstadt & Leben", family: "city" },
    leben: { token: "cat-city", canonical: "Innenstadt & Leben", family: "city" },
    "innenstadt-leben": { token: "cat-city", canonical: "Innenstadt & Leben", family: "city" },
    "innenstadt-und-leben": { token: "cat-city", canonical: "Innenstadt & Leben", family: "city" },

    wirtschaft: { token: "cat-business", canonical: "Wirtschaft", family: "business" },

    aktivitaet: { token: "cat-activity", canonical: "Aktivität", family: "activity" },
    aktivitaeten: { token: "cat-activity", canonical: "Aktivität", family: "activity" }
  };
  /* === END BLOCK: CATEGORY NORMALIZATION MAP (RAW + CANONICAL → STABLE TOKENS) === */

  const resolve = (name) => {
    let current = String(name || "").trim();
    const seen = new Set();

    while (current && ICON_ALIASES[current] && !seen.has(current)) {
      seen.add(current);
      current = ICON_ALIASES[current];
    }

    return current;
  };

  const categoryMeta = (categoryName) => {
    const raw = String(categoryName || "").trim();
    const normalized = normalizeKey(raw);

    if (!normalized) {
      return {
        raw: "",
        normalized: "",
        token: "cat-default",
        icon: resolve("cat-default"),
        canonical: "",
        family: "default"
      };
    }

    const meta = CATEGORY_META[normalized] || {
      token: "cat-default",
      canonical: raw,
      family: "default"
    };

    return {
      raw,
      normalized,
      token: meta.token,
      icon: resolve(meta.token),
      canonical: meta.canonical,
      family: meta.family
    };
  };

  const categoryKey = (categoryName) => categoryMeta(categoryName).token || "cat-default";

  const svg = (name, { className = "" } = {}) => {
    const resolvedName = resolve(name);
    const body = ICONS[resolvedName] || ICONS.external;
    const cls = `ui-icon-svg${className ? " " + escAttr(className) : ""}`;

    return `
      <svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        ${body}
      </svg>
    `;
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

  window.Icons = { svg, resolve, categoryKey, categoryMeta, hydrate };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => hydrate(document), { once: true });
  } else {
    hydrate(document);
  }
})();
/* END BLOCK: ICONS REGISTRY (APP-WIDE SINGLE SOURCE OF TRUTH) */
