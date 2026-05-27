// BEGIN: FILE_HEADER_OFFERS
// Datei: js/offers.js
// Zweck:
// - Rendert Activity Cards für Mobile + Desktop
// - Nutzt auf Mobile das Detailpanel, auf Desktop direkten Ziel-Open wie bei Events
// - Stellt die visuelle Kategorie-Logik zentral über window.OfferVisuals bereit
// END: FILE_HEADER_OFFERS

const OfferVisuals = (() => {
  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, (ch) => {
      switch (ch) {
        case "&": return "&amp;";
        case "<": return "&lt;";
        case ">": return "&gt;";
        case '"': return "&quot;";
        case "'": return "&#39;";
        default: return ch;
      }
    });
  }

  /* === BEGIN BLOCK: OFFERS_CENTRAL_IMAGE_RESOLVER_WITH_GENERIC_COMMONS_POOL_V2 | Zweck: bündelt Normalisierung, generische Commons-Symbolbilder und die zentrale Bildauflösung für Activities nachhaltig an einer Stelle, damit Cards + Detailpanel dieselbe Bildquelle und dieselbe Metadatenlogik verwenden | Umfang: ersetzt nur den bisherigen URL-Normalisierungsblock in js/offers.js === */
  function normalizeHttpUrl(raw) {
    const value = String(raw || "").trim();
    if (!value) return "";

    try {
      return new URL(value).href;
    } catch (_) {
      try {
        return new URL(value, window.location.href).href;
      } catch (_) {
        try {
          return new URL(`https://${value}`).href;
        } catch (_) {
          return "";
        }
      }
    }
  }

  function normalizeBoolean(value) {
    if (typeof value === "boolean") return value;
    const normalized = String(value || "").trim().toLowerCase();
    return normalized === "true" || normalized === "1" || normalized === "yes" || normalized === "ja";
  }

  function normalizeLookupKey(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[äÄ]/g, "ae")
      .replace(/[öÖ]/g, "oe")
      .replace(/[üÜ]/g, "ue")
      .replace(/[ß]/g, "ss")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "");
  }

  const GENERIC_ACTIVITY_IMAGES = Object.freeze({
    "park-green": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/D%C3%BClmen%2C%20Wildpark%2C%20Baumgruppe%20--%202024%20--%206306.jpg",
      positionX: "50%",
      positionY: "52%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:D%C3%BClmen,_Wildpark,_Baumgruppe_--_2024_--_6306.jpg",
      author: "Dietmar Rabich",
      license: "CC BY-SA 4.0",
      credit: "Dietmar Rabich, CC BY-SA 4.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "forest-path": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Forest%20path%20%28009%29.jpg",
      positionX: "50%",
      positionY: "56%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Forest_path_(009).jpg",
      author: "XxeoN472000",
      license: "CC BY-SA 4.0",
      credit: "XxeoN472000, CC BY-SA 4.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "trail-signs": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Trail%20signs%20in%20forest.jpg",
      positionX: "50%",
      positionY: "50%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Trail_signs_in_forest.jpg",
      author: "Tiia Monto",
      license: "CC BY-SA 3.0",
      credit: "Tiia Monto, CC BY-SA 3.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "playground-wood": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Indian%20Boundary%20Park%20-%20Wooden%20Playground%201.jpg",
      positionX: "50%",
      positionY: "50%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Indian_Boundary_Park_-_Wooden_Playground_1.jpg",
      author: "Chicagoshim",
      license: "CC BY-SA 4.0",
      credit: "Chicagoshim, CC BY-SA 4.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "adventure-playground": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Adventure%20Playground.jpg",
      positionX: "50%",
      positionY: "52%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Adventure_Playground.jpg",
      author: "Atelierdreiseitl",
      license: "CC BY-SA 3.0",
      credit: "Atelierdreiseitl, CC BY-SA 3.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "ropes-course": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Malta%20-%20Attard%20-%20Ta%27%20Qali%20BOV%20Adventure%20Park%20-%20High%20Ropes%20Course%2001%20ies.jpg",
      positionX: "50%",
      positionY: "42%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Malta_-_Attard_-_Ta%27_Qali_BOV_Adventure_Park_-_High_Ropes_Course_01_ies.jpg",
      author: "Frank Vincentz",
      license: "CC BY-SA 3.0",
      credit: "Frank Vincentz, CC BY-SA 3.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "soccer-field": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Medart%20Recreation%20Park%20soccer%20field.jpg",
      positionX: "50%",
      positionY: "58%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Medart_Recreation_Park_soccer_field.jpg",
      author: "The Bushranger",
      license: "CC BY-SA 4.0",
      credit: "The Bushranger, CC BY-SA 4.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "water-play": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/Wasserspielplatz%20Wasserspr%C3%BChfeld%20Berlin%2010v10.jpg",
      positionX: "50%",
      positionY: "54%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:Wasserspielplatz_Wasserspr%C3%BChfeld_Berlin_10v10.jpg",
      author: "Singlespeedfahrer",
      license: "CC0 1.0",
      credit: "Singlespeedfahrer, CC0 1.0, via Wikimedia Commons",
      note: "Symbolbild"
    },
    "historic-alley": {
      url: "https://commons.wikimedia.org/wiki/Special:FilePath/European%20alley.jpg",
      positionX: "50%",
      positionY: "52%",
      fit: "cover",
      sourcePage: "https://commons.wikimedia.org/wiki/File:European_alley.jpg",
      author: "Tom O'Neill",
      license: "CC BY 2.0",
      credit: "Tom O'Neill, CC BY 2.0, via Wikimedia Commons",
      note: "Symbolbild"
    }
  });

  function inferGenericImageKey(offer) {
    const explicitKey = normalizeLookupKey(offer?.visual_key || offer?.image_visual_key || "");
    if (explicitKey && GENERIC_ACTIVITY_IMAGES[explicitKey]) return explicitKey;

    const haystack = [
      offer?.id,
      offer?.title,
      offer?.location,
      offer?.description,
      offer?.kategorie,
      ...(Array.isArray(offer?.tags) ? offer.tags : [])
    ]
      .map((entry) => String(entry || "").trim().toLowerCase())
      .join(" ");

    if (/(wasserspiel|sprueh|sprüh|splash|wasserpark)/i.test(haystack)) return "water-play";
    if (/(maerchenspielplatz|märchenspielplatz|spielplatz)/i.test(haystack)) return "playground-wood";
    if (/(bauspielplatz|abenteuerspielplatz|abenteuer\-?spielplatz|babaluu)/i.test(haystack)) return "adventure-playground";
    if (/(seilgarten|tiefseilgarten|hochseilgarten|zipline|kletterpark)/i.test(haystack)) return "ropes-course";
    if (/(soccer|fussball|fußball|bolzplatz|sportfeld)/i.test(haystack)) return "soccer-field";
    if (/(gaengeskes|gängeskes|handwerk|gasse|innenstadt|altstadt|shopping|bummel)/i.test(haystack)) return "historic-alley";
    if (/(waldlehrpfad|mysterium|olle kerkpatt|entdeckerweg|rundweg|wanderweg|noaberpad|pfad)/i.test(haystack)) return "trail-signs";
    if (/(stadtwald|buergerpark|bürgerpark|park|freizeitanlage)/i.test(haystack)) return "park-green";
    if (/(mtb|mountainbike|waldweg|wald|trail)/i.test(haystack)) return "forest-path";

    const category = String(offer?.kategorie || "").trim().toLowerCase();
    if (category.includes("freizeit")) return "playground-wood";
    if (category.includes("sport")) return "forest-path";
    if (category.includes("kultur")) return "historic-alley";
    if (category.includes("natur")) return "forest-path";

    return "";
  }

  function resolveImageData(offer) {
    const explicitUrl = normalizeHttpUrl(offer?.image);
    if (explicitUrl) {
      return {
        url: explicitUrl,
        positionX: String(offer?.image_position_x || "50%").trim() || "50%",
        positionY: String(offer?.image_position_y || "50%").trim() || "50%",
        fit: String(offer?.image_fit || "cover").trim() || "cover",
        sourcePage: String(offer?.image_source_page || "").trim(),
        author: String(offer?.image_author || "").trim(),
        license: String(offer?.image_license || "").trim(),
        credit: String(offer?.image_credit || "").trim(),
        isSymbolic: normalizeBoolean(offer?.image_is_symbolic),
        note: String(offer?.image_note || "").trim()
      };
    }

    const genericKey = inferGenericImageKey(offer);
    const genericImage = genericKey ? GENERIC_ACTIVITY_IMAGES[genericKey] : null;

    if (genericImage) {
      return {
        url: normalizeHttpUrl(genericImage.url),
        positionX: genericImage.positionX || "50%",
        positionY: genericImage.positionY || "50%",
        fit: genericImage.fit || "cover",
        sourcePage: genericImage.sourcePage || "",
        author: genericImage.author || "",
        license: genericImage.license || "",
        credit: genericImage.credit || "",
        isSymbolic: true,
        note: genericImage.note || "Symbolbild"
      };
    }

    return {
      url: "",
      positionX: "50%",
      positionY: "50%",
      fit: "cover",
      sourcePage: "",
      author: "",
      license: "",
      credit: "",
      isSymbolic: false,
      note: ""
    };
  }
  /* === END BLOCK: OFFERS_CENTRAL_IMAGE_RESOLVER_WITH_GENERIC_COMMONS_POOL_V2 === */

  function slugify(value) {
    return String(value || "")
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9äöüß]+/gi, "-")
      .replace(/^-+|-+$/g, "");
  }

function getCategoryPresentation(category) {
  const raw = String(category || "").trim();
  const fallbackLabel = raw || "Aktivität";

  if (window.Icons && typeof window.Icons.categoryMeta === "function") {
    const meta = window.Icons.categoryMeta(raw);

    if (meta && meta.token) {
      const labelByFamily = {
        market: "Markt",
        kids: "Freizeit",
        music: "Musik",
        culture: "Kultur",
        sport: "Aktiv",
        nature: "Natur",
        city: "Lokal",
        business: "Wirtschaft",
        activity: "Aktivität",
        default: fallbackLabel
      };

      const modifierByFamily = {
        market: "markt",
        kids: "freizeit-familie",
        music: "musik",
        culture: "kultur",
        sport: "sport-bewegung",
        nature: "natur",
        city: "innenstadt-leben",
        business: "wirtschaft",
        activity: "aktivitaet",
        default: slugify(meta.canonical || raw || "aktivitaet") || "aktivitaet"
      };

      return {
        rawLabel: meta.canonical || fallbackLabel,
        label: labelByFamily[meta.family] || fallbackLabel,
        iconKey: meta.token,
        modifier: modifierByFamily[meta.family] || slugify(meta.canonical || raw || "aktivitaet") || "aktivitaet"
      };
    }
  }

  return {
    rawLabel: fallbackLabel,
    label: fallbackLabel,
    iconKey: "cat-activity",
    modifier: slugify(fallbackLabel) || "aktivitaet"
  };
}

  /* === BEGIN BLOCK: OFFERS_CARD_DISCOVERY_VALUE_V3 | Zweck: bereinigt Activity-Tags systemisch um redundante Titel-/Kategorie-Wiederholungen und macht saisonale Einschränkungen optional für die Detail-Meta nutzbar | Umfang: ersetzt nur die Helper von buildMetaLine() bis buildFactItems() in js/offers.js === */
  function buildMetaLine(offer, options = {}) {
    const includeSeasonalRestriction = !!options.includeSeasonalRestriction;
    const metaParts = [offer?.duration, offer?.mode, offer?.price]
      .map((entry) => toSingleLine(entry))
      .filter(Boolean);

    const season = toSingleLine(offer?.season);
    if (includeSeasonalRestriction && season && season !== "Ganzjährig") {
      metaParts.push(season);
    }

    return metaParts.join(" · ");
  }

  function toSingleLine(value) {
    return String(value || "").replace(/\s+/g, " ").trim();
  }

  function normalizeComparable(value) {
    return toSingleLine(value)
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function getTagPriority(tag) {
    const value = toSingleLine(tag);
    const priorityMap = {
      "Spielplatz": 10,
      "Badebucht": 12,
      "Badesee": 14,
      "Strand": 16,
      "Snacks & Getränke": 20,
      "Café": 21,
      "Restaurant": 22,
      "Wassersport": 24,
      "Aussicht": 26,
      "Vogelbeobachtung": 28,
      "Spielgeräte": 30,
      "Moor": 32,
      "Grenze": 34,
      "Grenzort": 36,
      "Geschichte": 38,
      "Naturpfad": 40,
      "Rundweg": 42,
      "Bohlenweg": 44,
      "Wildblumenwiese": 46,
      "Werkstätten": 48,
      "Führungen": 50,
      "Lehrbienenstand": 52,
      "Schmugglerwege": 54,
      "Lauschtour": 56,
      "Brücken": 58
    };

    return priorityMap[value] ?? 500;
  }

  function getMeaningfulTagItems(offer) {
    const tags = Array.isArray(offer?.tags)
      ? offer.tags.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    const uniqueTags = Array.from(new Set(tags));
    const titleNeedle = normalizeComparable(offer?.title);
    const categoryNeedle = normalizeComparable(offer?.kategorie);
    const genericFormatTags = new Set(["spaziergang", "wanderroute", "radroute", "erlebnisweg"]);

    const filteredTags = uniqueTags.filter((tag) => {
      const tagNeedle = normalizeComparable(tag);
      if (!tagNeedle) return false;

      if (uniqueTags.length <= 1) return true;
      if (genericFormatTags.has(tagNeedle)) return false;
      if (titleNeedle.includes(tagNeedle)) return false;
      if (categoryNeedle.includes(tagNeedle)) return false;

      return true;
    });

    return filteredTags.length ? filteredTags : uniqueTags;
  }

  function getRankedTagItems(offer, limit = Infinity) {
    const meaningfulTags = getMeaningfulTagItems(offer);
    meaningfulTags.sort((a, b) => {
      const delta = getTagPriority(a) - getTagPriority(b);
      if (delta !== 0) return delta;
      return a.localeCompare(b, "de");
    });

    return meaningfulTags.slice(0, Math.max(0, Number.isFinite(limit) ? limit : meaningfulTags.length));
  }

  /* === BEGIN BLOCK: ACTIVITIES_CARD_SUPPORTING_MATCH_SUMMARY_V1 | Zweck: nutzt bei aktiven Filtern die Card-Zeile auf Mobile als direkte Passungsbegründung statt nur als allgemeine Tag-Zeile; Umfang: ersetzt nur pickSupportingLabel(offer) === */
  function pickSupportingLabel(offer) {
    const activeMatches = Array.isArray(offer?.activeMatchLabels)
      ? offer.activeMatchLabels.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    if (activeMatches.length) {
      const merged = [];
      const seen = new Set();

      [...activeMatches, ...getRankedTagItems(offer, 3)].forEach((entry) => {
        const value = toSingleLine(entry);
        const key = normalizeComparable(value);
        if (!value || seen.has(key)) return;

        seen.add(key);
        merged.push(value);
      });

      return merged.slice(0, 3).join(" · ");
    }

    const primaryTags = getRankedTagItems(offer, 2);
    if (primaryTags.length) return primaryTags.join(" · ");

    const season = toSingleLine(offer?.season);
    if (season && season !== "Ganzjährig") return season;

    return "";
  }
  /* === END BLOCK: ACTIVITIES_CARD_SUPPORTING_MATCH_SUMMARY_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_CARD_MATCH_FACT_PRIORITY_V1 | Zweck: priorisiert bei aktiven Filtern die konkret passenden Merkmale auf Activity-Cards; Umfang: ersetzt nur buildFactItems(offer), ohne Meta-/Tag-Helfer zu verändern === */
  function buildFactItems(offer) {
    const activeMatches = Array.isArray(offer?.activeMatchLabels)
      ? offer.activeMatchLabels.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    const primaryTags = getRankedTagItems(offer, 3);
    const curatedFacts = Array.isArray(offer?.cardFacts)
      ? offer.cardFacts.map((entry) => toSingleLine(entry)).filter(Boolean)
      : [];

    const fallbackFacts = [offer?.duration, offer?.mode, offer?.price]
      .map((entry) => toSingleLine(entry))
      .filter(Boolean);

    if (activeMatches.length) {
      const merged = [];
      const seen = new Set();

      [...activeMatches, ...primaryTags, ...curatedFacts, ...fallbackFacts].forEach((entry) => {
        const value = toSingleLine(entry);
        const key = normalizeComparable(value);
        if (!value || seen.has(key)) return;

        seen.add(key);
        merged.push(value);
      });

      return merged.slice(0, 3);
    }

    if (primaryTags.length) {
      return primaryTags;
    }

    if (curatedFacts.length) {
      return curatedFacts.slice(0, 3);
    }

    return fallbackFacts.slice(0, 3);
  }
  /* === END BLOCK: ACTIVITIES_CARD_MATCH_FACT_PRIORITY_V1 === */
  /* === END BLOCK: OFFERS_CARD_DISCOVERY_VALUE_V3 === */

  return {
    escapeHtml,
    normalizeHttpUrl,
    resolveImageData,
    slugify,
    getCategoryPresentation,
    buildMetaLine,
    buildFactItems,
    pickSupportingLabel,
    getRankedTagItems
  };
})();

window.OfferVisuals = OfferVisuals;

const OfferCards = (() => {
  let container = null;

  const isDesktopViewport = () => window.matchMedia("(min-width: 900px)").matches;

  function ensureContainer() {
    if (!container) container = document.getElementById("offer-cards");
    return container;
  }

  function renderCategoryIcon(visual) {
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-category-icon-svg" })
      : `<span class="activity-card-category-icon-fallback">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    return `<span class="event-category-icon activity-card-category-icon" aria-hidden="true">${iconHtml}</span>`;
  }

  function renderDescription(offer) {
    const text = String(offer?.description || "").trim();
    if (!text) return "";
    return `<p class="event-card-desc">${OfferVisuals.escapeHtml(text)}</p>`;
  }

  /* === BEGIN BLOCK: ACTIVITIES_CARD_MATCH_CHIP_RENDER_V1 | Zweck: markiert aktuell passende Filtermerkmale auf Activity-Cards sichtbar, ohne die bestehende Fact-Chip-Struktur zu ersetzen; Umfang: ersetzt nur renderFactChips(offer) === */
  function renderFactChips(offer) {
    const items = OfferVisuals.buildFactItems(offer);
    if (!items.length) return "";

    const activeMatches = new Set(
      (Array.isArray(offer?.activeMatchLabels) ? offer.activeMatchLabels : [])
        .map((entry) => String(entry || "").replace(/\s+/g, " ").trim())
        .filter(Boolean)
    );

    return `
      <div class="activity-card-facts" aria-label="${activeMatches.size ? "Passende Merkmale" : "Wichtige Merkmale"}">
        ${items.map((item) => {
          const label = String(item || "").replace(/\s+/g, " ").trim();
          const isMatch = activeMatches.has(label);
          const className = isMatch
            ? "activity-card-fact activity-card-fact--match"
            : "activity-card-fact";

          return `<span class="${className}">${OfferVisuals.escapeHtml(label)}</span>`;
        }).join("")}
      </div>
    `.trim();
  }
  /* === END BLOCK: ACTIVITIES_CARD_MATCH_CHIP_RENDER_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_CARD_QUIET_SEGMENT_RENDER_ENTERPRISE | Zweck: rendert die mobile Passungszeile in trennzeichensicheren Segmenten, damit keine abgetrennten Punkte am Zeilenende entstehen; Umfang: ersetzt nur renderSupportingLine(offer) === */
  function renderSupportingLine(offer) {
    const text = OfferVisuals.pickSupportingLabel(offer);
    const items = String(text || "")
      .split(/\s*·\s*/)
      .map((entry) => entry.trim())
      .filter(Boolean);

    if (!items.length) return "";

    return `
      <div class="activity-card-quiet" aria-label="Warum diese Aktivität passt">
        ${items.map((item) => `<span class="activity-card-quiet__item">${OfferVisuals.escapeHtml(item)}</span>`).join("")}
      </div>
    `.trim();
  }
  /* === END BLOCK: ACTIVITIES_CARD_QUIET_SEGMENT_RENDER_ENTERPRISE === */
  /* === END BLOCK: OFFERS_CARD_DISCOVERY_VALUE_V2 === */
  /* === BEGIN BLOCK: ACTIVITIES_IMAGE_LOADING_STRATEGY_WITH_RESOLVED_VISUALS_V3 | Zweck: behaelt die bestehende Bildlade-Logik der Activity-Cards bei und ergänzt belastbare Alt-Texte fuer Suchmaschinen und Barrierefreiheit, ohne Kartenlayout oder Ladeprioritäten zu verändern | Umfang: ersetzt nur Helper + renderMedia() vor openPrimaryDesktopTarget() === */
  const preconnectedImageOrigins = new Set();

  function ensureImageOriginHints(imageUrl) {
    try {
      const url = new URL(imageUrl, window.location.href);
      if (!url.origin || url.origin === window.location.origin) return;
      if (preconnectedImageOrigins.has(url.origin)) return;

      const head = document.head;
      if (!head) return;

      if (!head.querySelector(`link[rel="dns-prefetch"][href="${url.origin}"]`)) {
        const dnsPrefetch = document.createElement("link");
        dnsPrefetch.rel = "dns-prefetch";
        dnsPrefetch.href = url.origin;
        head.appendChild(dnsPrefetch);
      }

      if (!head.querySelector(`link[rel="preconnect"][href="${url.origin}"]`)) {
        const preconnect = document.createElement("link");
        preconnect.rel = "preconnect";
        preconnect.href = url.origin;
        preconnect.crossOrigin = "anonymous";
        head.appendChild(preconnect);
      }

      preconnectedImageOrigins.add(url.origin);
    } catch (_) {}
  }

  function getImageLoadingProfile(index) {
    const numericIndex = Number(index);
    const cardIndex = Number.isFinite(numericIndex) ? numericIndex : Number.MAX_SAFE_INTEGER;
    const desktop = isDesktopViewport();

    const eagerCount = desktop ? 4 : 2;
    const highPriorityCount = desktop ? 4 : 1;

    return {
      loading: cardIndex < eagerCount ? "eager" : "lazy",
      fetchPriority: cardIndex < highPriorityCount ? "high" : "auto"
    };
  }

  function buildActivityImageAltText(offer, imageData) {
    const title = String(offer?.title || "").trim();
    const location = String(offer?.location || "").trim();
    const note = String(imageData?.note || "").trim();

    if (imageData?.isSymbolic) {
      if (title && location) return `Symbolbild für ${title} – ${location}`;
      if (title) return `Symbolbild für ${title}`;
      return "Symbolbild für Aktivität in Bocholt und Umgebung";
    }

    if (title && location) return `${title} – ${location}`;
    if (title) return title;
    if (location) return `Aktivität in ${location}`;
    if (note) return note;
    return "Aktivität in Bocholt und Umgebung";
  }

  function renderMedia(offer, visual, index) {
    const imageData = OfferVisuals.resolveImageData(offer);
    const imageUrl = imageData.url;
    const modifier = OfferVisuals.escapeHtml(visual.modifier);
    const label = OfferVisuals.escapeHtml(visual.label);
    const iconHtml = window.Icons?.svg
      ? window.Icons.svg(visual.iconKey, { className: "activity-card-media-icon-svg" })
      : `<span class="activity-card-media-icon-fallback">${OfferVisuals.escapeHtml(visual.label.slice(0, 1))}</span>`;

    if (imageUrl) {
      ensureImageOriginHints(imageUrl);
      const { loading, fetchPriority } = getImageLoadingProfile(index);
      const symbolicLabel = imageData.isSymbolic
        ? OfferVisuals.escapeHtml(String(imageData.note || "").trim() || "Symbolbild")
        : "";
      const imageAlt = OfferVisuals.escapeHtml(buildActivityImageAltText(offer, imageData));

      return `
        <div class="activity-card-media activity-card-media--image activity-card-media--${modifier}">
<img
  class="activity-card-media__image"
  src="${OfferVisuals.escapeHtml(imageUrl)}"
  alt="${imageAlt}"
  loading="${loading}"
  fetchpriority="${fetchPriority}"
  decoding="async"
  referrerpolicy="no-referrer"
  style="--activity-image-pos-x:${OfferVisuals.escapeHtml(imageData.positionX || "50%")}; --activity-image-pos-y:${OfferVisuals.escapeHtml(imageData.positionY || "50%")}; --activity-image-fit:${OfferVisuals.escapeHtml(imageData.fit || "cover")};"
>
          ${symbolicLabel ? `<span class="activity-card-media__badge">${symbolicLabel}</span>` : ""}
        </div>
      `.trim();
    }

    return `
      <div class="activity-card-media activity-card-media--fallback activity-card-media--${modifier}" aria-hidden="true">
        <div class="activity-card-media__scrim"></div>
        <div class="activity-card-media__content">
          <span class="activity-card-media__icon">${iconHtml}</span>
          <span class="activity-card-media__label">${label}</span>
        </div>
      </div>
    `.trim();
  }
  /* === END BLOCK: ACTIVITIES_IMAGE_LOADING_STRATEGY_WITH_RESOLVED_VISUALS_V3 === */

  /* === BEGIN BLOCK: ACTIVITIES_DESKTOP_DIRECT_OPEN_ANALYTICS_V1 | Zweck: zählt Desktop-Direktklicks auf Activity-Cards als anonymen website_click, bevor das externe Ziel geöffnet wird; Umfang: ersetzt nur openPrimaryDesktopTarget() und ergänzt den Card-Payload in createCard() === */
  function buildPrimaryOutboundPayload(offer, primaryUrl) {
    if (!primaryUrl) return null;

    return {
      outboundType: "website",
      entityType: "activity",
      entityId: String(offer?.id || "").trim(),
      entityTitle: String(offer?.title || "").trim(),
      destinationUrl: primaryUrl,
      ...(window.BEAnalytics?.buildReportingTargetPayload
        ? window.BEAnalytics.buildReportingTargetPayload(offer)
        : {})
    };
  }

  function openPrimaryDesktopTarget(url, analyticsPayload = null) {
    if (!url) return false;

    try {
      if (
        analyticsPayload &&
        window.BEAnalytics &&
        typeof window.BEAnalytics.trackOutboundClick === "function"
      ) {
        window.BEAnalytics.trackOutboundClick(analyticsPayload);
      }

      window.open(url, "_blank", "noopener,noreferrer");
      return true;
    } catch (_) {
      return false;
    }
  }
  /* === END BLOCK: ACTIVITIES_DESKTOP_DIRECT_OPEN_ANALYTICS_V1 === */

  /* === BEGIN BLOCK: ACTIVITIES_CREATECARD_DESKTOP_ANALYTICS_PLUMBING_V2 | Zweck: reicht den Activity-Outbound-Payload in alle direkten Card-Open-Pfade durch, damit Desktop-Klicks und URL-Fallbacks messbar werden; Umfang: ersetzt nur createCard() === */
  function createCard(offer, index) {
    const article = document.createElement("article");
    const visual = OfferVisuals.getCategoryPresentation(offer.kategorie);
    const primaryUrl = OfferVisuals.normalizeHttpUrl(offer.url);
    const primaryOutboundPayload = buildPrimaryOutboundPayload(offer, primaryUrl);

    article.className = "event-card discovery-card--compact activity-card--rich";
    article.tabIndex = 0;
    article.setAttribute("role", "button");
    article.setAttribute(
      "aria-label",
      primaryUrl ? `Aktivität öffnen: ${offer.title}` : `Aktivität anzeigen: ${offer.title}`
    );

    article.innerHTML = `
      ${renderMedia(offer, visual, index)}
      <div class="event-card-body">
        <div class="activity-card-kicker">${OfferVisuals.escapeHtml(visual.label)}</div>
        <h2 class="event-title">
          <span class="event-title__text">${OfferVisuals.escapeHtml(offer.title)}</span>
          ${renderCategoryIcon(visual)}
        </h2>
        ${renderDescription(offer)}
        <div class="event-meta">
          <span class="event-meta__place">${OfferVisuals.escapeHtml(offer.location)}</span>
        </div>
        ${renderFactChips(offer)}
        ${renderSupportingLine(offer)}
      </div>
    `.trim();

    const open = (event) => {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }

      if (isDesktopViewport() && openPrimaryDesktopTarget(primaryUrl, primaryOutboundPayload)) {
        return;
      }

      if (window.OfferDetailPanel?.show) {
        window.OfferDetailPanel.show(offer);
        return;
      }

      openPrimaryDesktopTarget(primaryUrl, primaryOutboundPayload);
    };

    article.addEventListener("click", open);
    article.addEventListener("keydown", (event) => {
      if (event.key !== "Enter" && event.key !== " ") return;
      open(event);
    });

    return article;
  }
  /* === END BLOCK: ACTIVITIES_CREATECARD_DESKTOP_ANALYTICS_PLUMBING_V2 === */

  /* === BEGIN BLOCK: ACTIVITIES_SKELETON_AND_EMPTYSTATE_PARITY_V1 | Zweck: ergänzt Desktop-Skeletons und gleicht Empty-State mit Reset-CTA an die Event-Seite an | Umfang: ersetzt nur Render-/Export-Block des OfferCards-Moduls === */
  function createSkeletonCard() {
    const article = document.createElement("article");
    article.className = "event-card discovery-card--compact activity-card--rich is-skeleton";
    article.tabIndex = -1;
    article.setAttribute("aria-hidden", "true");

    article.innerHTML = `
      <div class="activity-card-media activity-card-media--image" aria-hidden="true">
        <div class="activity-card-skeleton-media skel-block"></div>
      </div>
      <div class="event-card-body">
        <div class="activity-card-kicker">
          <span class="skel-line skel-line--sm" style="width:64px"></span>
        </div>
        <h2 class="event-title">
          <span class="event-title__text">
            <span class="skel-line" style="width:72%"></span>
          </span>
        </h2>
        <div class="event-card-desc">
          <span class="skel-line" style="width:96%"></span>
          <span class="skel-line" style="width:78%; margin-top:8px;"></span>
        </div>
        <div class="event-meta">
          <span class="event-meta__place">
            <span class="skel-line skel-line--sm" style="width:38%"></span>
          </span>
        </div>
        <div class="activity-card-facts" aria-hidden="true">
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:56px"></span></span>
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:62px"></span></span>
          <span class="activity-card-fact"><span class="skel-line skel-line--sm" style="width:68px"></span></span>
        </div>
      </div>
    `.trim();

    return article;
  }

  function renderSkeleton(count = 8) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const n = Math.max(1, Math.min(12, Number(count) || 8));
    for (let i = 0; i < n; i++) {
      target.appendChild(createSkeletonCard());
    }
  }

  /* === BEGIN BLOCK: ACTIVITIES_MOBILE_PRESENCE_FEED_ENTRY_V1 | Zweck: rendert den Aktivitäts-Funnel-Einstieg auf Mobile als Feed-Card im Stil der Event-veröffentlichen-Card statt als Hero-Service-Button; Umfang: ersetzt render(offers) + Return-Zeile und ergänzt createActivityPresenceEntry() === */
  function createActivityPresenceEntry() {
    const link = document.createElement("a");
    link.className = "feed-publish-entry activity-presence-feed-entry";
    link.href = "/angebote/sichtbar-werden/";
    link.setAttribute("aria-label", "Für Anbieter – als Aktivität sichtbar werden");

    const label = document.createElement("span");
    label.className = "feed-publish-entry__label";
    label.textContent = "Für Anbieter";

    const main = document.createElement("span");
    main.className = "feed-publish-entry__main";

    const title = document.createElement("span");
    title.className = "feed-publish-entry__title";
    title.textContent = "Als Aktivität sichtbar werden";

    const chevron = document.createElement("span");
    chevron.className = "feed-publish-entry__chevron";
    chevron.setAttribute("aria-hidden", "true");
    chevron.innerHTML = window.Icons?.svg
      ? window.Icons.svg("chevron-right", { className: "feed-publish-entry__chevron-svg" })
      : "";

    main.append(title, chevron);
    link.append(label, main);

    return link;
  }

  function render(offers) {
    const target = ensureContainer();
    if (!target) return;

    target.innerHTML = "";

    const list = Array.isArray(offers) ? offers : [];
    if (!list.length) {
      target.innerHTML = `
        <section class="empty-state">
          <div class="empty-state__card" role="status" aria-live="polite">
            <div class="empty-state__title">Keine Aktivitäten gefunden</div>
            <div class="empty-state__text">Filter anpassen oder zurücksetzen.</div>
            <button type="button" class="empty-state__btn" id="empty-reset-btn">Filter zurücksetzen</button>
          </div>
        </section>
      `.trim();

      const btn = document.getElementById("empty-reset-btn");
      if (btn) {
        btn.addEventListener("click", () => {
          window.dispatchEvent(new CustomEvent("offers:reset-filters"));
        });
      }
      return;
    }

    list.forEach((offer, index) => {
      target.appendChild(createCard(offer, index));
      if (index === 0) {
        target.appendChild(createActivityPresenceEntry());
      }
    });
  }

  return { render, renderSkeleton };
  /* === END BLOCK: ACTIVITIES_MOBILE_PRESENCE_FEED_ENTRY_V1 === */
  /* === END BLOCK: ACTIVITIES_SKELETON_AND_EMPTYSTATE_PARITY_V1 === */
})();

window.OfferCards = OfferCards;
