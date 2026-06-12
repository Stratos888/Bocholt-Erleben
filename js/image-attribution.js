/* === BEGIN FILE: js/image-attribution.js | Zweck: zentralisiert Bildnachweise fuer Detailpanels und die oeffentliche Bildnachweiseite; Umfang: reine Render-/Normalisierungslogik ohne Datenfetch === */
(function () {
  "use strict";

  const LICENSE_URLS = Object.freeze({
    "CC0 1.0": "https://creativecommons.org/publicdomain/zero/1.0/",
    "CC BY 2.0": "https://creativecommons.org/licenses/by/2.0/",
    "CC BY-SA 2.0 DE": "https://creativecommons.org/licenses/by-sa/2.0/de/",
    "CC BY-SA 3.0": "https://creativecommons.org/licenses/by-sa/3.0/",
    "CC BY-SA 4.0": "https://creativecommons.org/licenses/by-sa/4.0/"
  });

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function escapeHtml(value) {
    return asString(value)
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  }

  function normalizeUrl(value) {
    const raw = asString(value);
    if (!raw) return "";

    try {
      const parsed = new URL(raw, window.location.origin);
      if (parsed.protocol !== "http:" && parsed.protocol !== "https:") return "";
      return parsed.href;
    } catch (_) {
      return "";
    }
  }

  function normalizeLocalOrHttpUrl(value) {
    const raw = asString(value);
    if (!raw) return "";
    if (raw.startsWith("/")) return raw;
    return normalizeUrl(raw);
  }

  function normalizeBool(value) {
    if (value === true || value === false) return value;
    const raw = asString(value).toLowerCase();
    return ["1", "true", "yes", "ja", "symbolic", "symbolbild"].includes(raw);
  }

  function slugify(value) {
    return asString(value)
      .toLowerCase()
      .normalize("NFKD")
      .replace(/[\u0300-\u036f]/g, "")
      .replace(/ä/g, "ae")
      .replace(/ö/g, "oe")
      .replace(/ü/g, "ue")
      .replace(/ß/g, "ss")
      .replace(/[^a-z0-9]+/g, "-")
      .replace(/^-+|-+$/g, "") || "bild";
  }

  function firstValue(...values) {
    for (const value of values) {
      const text = asString(value);
      if (text) return text;
    }
    return "";
  }

  function licenseUrlFor(license, explicitUrl) {
    const explicit = normalizeUrl(explicitUrl);
    if (explicit) return explicit;

    const raw = asString(license);
    return LICENSE_URLS[raw] || "";
  }

  function isGenerated(meta) {
    if (meta.isAiGenerated) return true;

    const haystack = [
      meta.source,
      meta.sourceType,
      meta.rightsStatus,
      meta.reviewStatus
    ].map(asString).join(" ").toLowerCase();

    return /ai_generated|chatgpt_generated|generated/.test(haystack);
  }

  function isLicensedRealPhoto(meta) {
    const haystack = [
      meta.source,
      meta.sourceType,
      meta.rightsStatus,
      meta.license,
      meta.sourcePage
    ].map(asString).join(" ").toLowerCase();

    return /wikimedia|commons|cc by|cc0|licensed_real_photo|attribution_required/.test(haystack);
  }

  function noteFor(meta) {
    const explicit = firstValue(meta.publicNote, meta.note);
    if (explicit) return explicit;

    if (meta.isSymbolic && isGenerated(meta)) return "Symbolbild / KI-generiertes Visual";
    if (meta.isSymbolic) return "Symbolbild";
    if (isGenerated(meta)) return "KI-generiertes Visual";
    return "";
  }

  function attributionLabel(meta) {
    if (isGenerated(meta)) return "KI-generiertes Symbolbild";
    if (meta.isSymbolic) return "Symbolbild";
    if (isLicensedRealPhoto(meta)) return "Realfoto";
    return "Bildnachweis";
  }

  function badgeLabel(input) {
    const meta = normalize(input);
    if (isGenerated(meta)) return "KI-generiertes Symbolbild";
    if (meta.isSymbolic) return "Symbolbild";
    return "";
  }

  function detailSummaryText(meta) {
    if (isGenerated(meta)) return "KI-generiertes Symbolbild";
    if (meta.isSymbolic) return "Symbolbild";

    const parts = ["Realfoto"];
    if (meta.author) parts.push(meta.author);
    if (meta.license) parts.push(meta.license);
    return parts.join(" · ");
  }

  function normalize(input, options = {}) {
    const source = input && typeof input === "object" ? input : {};

    const meta = {
      id: firstValue(source.id, source.imageId, source.image_id, options.imageId),
      src: normalizeLocalOrHttpUrl(firstValue(source.src, source.url, source.image, options.src)),
      alt: firstValue(source.alt, options.alt),
      entityType: firstValue(options.entityType, source.entityType, source.entity_type),
      entityId: firstValue(options.entityId, source.entityId, source.entity_id),
      entityTitle: firstValue(options.entityTitle, source.entityTitle, source.entity_title, source.title, source.label),
      poolKey: firstValue(source.poolKey, source.pool_key, options.poolKey),
      visualKey: firstValue(source.visualKey, source.visual_key, options.visualKey),
      status: firstValue(source.status, options.status),
      source: firstValue(source.source, options.source),
      sourceType: firstValue(source.sourceType, source.source_type, options.sourceType),
      rightsStatus: firstValue(source.rightsStatus, source.rights_status, options.rightsStatus),
      reviewStatus: firstValue(source.reviewStatus, source.review_status, options.reviewStatus),
      sourceTitle: firstValue(source.sourceTitle, source.source_title, options.sourceTitle),
      sourcePage: normalizeUrl(firstValue(source.sourcePage, source.source_page, source.sourceUrl, source.source_url, options.sourcePage, options.sourceUrl)),
      downloadUrl: normalizeUrl(firstValue(source.downloadUrl, source.download_url, options.downloadUrl)),
      author: firstValue(source.author, source.photographer, options.author),
      license: firstValue(source.license, options.license),
      licenseUrl: licenseUrlFor(firstValue(source.license, options.license), firstValue(source.licenseUrl, source.license_url, options.licenseUrl, options.license_url)),
      credit: firstValue(source.credit, source.attribution, options.credit, options.attribution),
      modifications: firstValue(source.modifications, source.modified, source.edit_note, options.modifications),
      publicNote: firstValue(source.publicNote, source.public_note, options.publicNote),
      note: firstValue(source.note, options.note),
      isSymbolic: normalizeBool(firstValue(source.isSymbolic, source.is_symbolic, options.isSymbolic)),
      isAiGenerated: normalizeBool(firstValue(source.isAiGenerated, source.is_ai_generated, options.isAiGenerated, options.is_ai_generated)),
      isDocumentary: normalizeBool(firstValue(source.isDocumentary, source.is_documentary, options.isDocumentary)),
      relatedIds: Array.isArray(options.relatedIds) ? options.relatedIds.map(asString).filter(Boolean) : []
    };

    if (!meta.entityId && meta.relatedIds.length === 1) {
      meta.entityId = meta.relatedIds[0];
    }

    return Object.freeze(meta);
  }

  function anchorId(meta, options = {}) {
    const explicit = firstValue(options.anchorId, meta.anchorId);
    if (explicit) return slugify(explicit);

    if (meta.entityType === "activity" && meta.entityId) {
      return `activity-${slugify(meta.entityId)}`;
    }

    if (meta.entityType === "event" && meta.id) {
      return `event-visual-${slugify(meta.id)}`;
    }

    if (meta.id) return `visual-${slugify(meta.id)}`;
    if (meta.visualKey) return `visual-${slugify(meta.visualKey)}`;
    if (meta.entityTitle) return `visual-${slugify(meta.entityTitle)}`;
    return "bildnachweis";
  }

  function fullCreditHref(meta, options = {}) {
    const id = anchorId(meta, options);
    const base = asString(options.pageUrl) || "/bildnachweise/";
    return `${base}#${encodeURIComponent(id)}`;
  }

  function hasRenderableData(meta) {
    return Boolean(
      noteFor(meta) ||
      meta.author ||
      meta.license ||
      meta.credit ||
      meta.sourcePage ||
      meta.downloadUrl ||
      meta.modifications ||
      meta.sourceTitle ||
      isGenerated(meta)
    );
  }

  function renderExternalLink(href, label, className) {
    const url = normalizeUrl(href);
    if (!url) return "";

    return `<a class="${escapeHtml(className)}" href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(label)}</a>`;
  }

  function renderInternalLink(href, label, className) {
    const raw = asString(href);
    if (!raw) return "";
    return `<a class="${escapeHtml(className)}" href="${escapeHtml(raw)}">${escapeHtml(label)}</a>`;
  }

  function row(key, value) {
    const html = asString(value);
    if (!html) return "";

    return `
      <div class="image-attribution__row">
        <div class="image-attribution__key">${escapeHtml(key)}</div>
        <div class="image-attribution__value">${html}</div>
      </div>
    `.trim();
  }

  function renderRows(meta, options = {}) {
    const note = noteFor(meta);
    const licenseLabel = meta.licenseUrl
      ? renderExternalLink(meta.licenseUrl, meta.license || "Lizenz öffnen", "image-attribution__link")
      : escapeHtml(meta.license);

    const sourceLabel = meta.sourcePage
      ? renderExternalLink(meta.sourcePage, meta.sourceTitle || "Original / Quelle öffnen", "image-attribution__link")
      : (meta.sourceTitle ? escapeHtml(meta.sourceTitle) : (isGenerated(meta) ? "Intern generiertes Projektvisual" : ""));

    const downloadLabel = meta.downloadUrl && options.includeDownload
      ? renderExternalLink(meta.downloadUrl, "Datei öffnen", "image-attribution__link")
      : "";

    const fullLink = options.includeFullLink === false
      ? ""
      : renderInternalLink(fullCreditHref(meta, options), "Vollständiger Nachweis", "image-attribution__link image-attribution__full-link");

    return [
      row("Hinweis", note ? escapeHtml(note) : ""),
      row("Urheber", meta.author ? escapeHtml(meta.author) : ""),
      row("Lizenz", licenseLabel),
      row("Quelle", sourceLabel),
      row("Datei", downloadLabel),
      row("Bearbeitung", meta.modifications ? escapeHtml(meta.modifications) : ""),
      row("Nachweis", meta.credit ? escapeHtml(meta.credit) : ""),
      row("Details", fullLink)
    ].filter(Boolean).join("\n");
  }

  function renderDetailAttribution(input, options = {}) {
    const meta = normalize(input, options);
    if (!hasRenderableData(meta)) return "";

    const infoIcon = window.Icons?.svg
      ? window.Icons.svg("info", { className: "image-attribution__icon-svg" })
      : "";

    const summaryText = attributionLabel(meta);
    const detailText = detailSummaryText(meta);
    const fullLink = renderInternalLink(
      fullCreditHref(meta, options),
      "Vollständiger Nachweis",
      "image-attribution__link image-attribution__full-link"
    );

    return `
      <details class="image-attribution image-attribution--detail" data-image-attribution="${escapeHtml(meta.entityType || "visual")}">
        <summary class="image-attribution__toggle">
          <span class="image-attribution__toggle-main">
            <span class="image-attribution__icon" aria-hidden="true">${infoIcon}</span>
            <span>Bildnachweis</span>
          </span>
          <span class="image-attribution__toggle-secondary">${escapeHtml(summaryText)}</span>
        </summary>
        <div class="image-attribution__panel image-attribution__panel--compact">
          <span class="image-attribution__compact-text">${escapeHtml(detailText)}</span>
          ${fullLink}
        </div>
      </details>
    `.trim();
  }

  function renderCreditCard(entry) {
    const meta = normalize(entry.meta, entry.options || {});
    const id = anchorId(meta, entry.options || {});
    const aliases = Array.isArray(entry.aliases) ? entry.aliases.map(slugify).filter(Boolean) : [];
    const title = asString(entry.title) || meta.entityTitle || meta.sourceTitle || meta.id || "Bild";
    const groupLabel = asString(entry.groupLabel) || (meta.entityType === "event" ? "Event-Bild" : "Aktivitätsbild");
    const rows = renderRows(meta, { includeDownload: false, includeFullLink: false });
    const image = meta.src ? `
      <figure class="image-credit-card__media" aria-hidden="true">
        <img src="${escapeHtml(meta.src)}" alt="" loading="lazy" decoding="async" width="320" height="180">
      </figure>
    ` : "";

    return `
      <article class="image-credit-card" id="${escapeHtml(id)}">
        ${aliases.map((alias) => `<span class="image-credit-anchor" id="${escapeHtml(alias)}"></span>`).join("")}
        ${image}
        <div class="image-credit-card__body">
          <p class="image-credit-card__kicker">${escapeHtml(groupLabel)} · ${escapeHtml(attributionLabel(meta))}</p>
          <h2>${escapeHtml(title)}</h2>
          <div class="image-attribution image-attribution--card">
            <div class="image-attribution__panel">
              ${rows}
            </div>
          </div>
        </div>
      </article>
    `.trim();
  }

  window.ImageAttribution = Object.freeze({
    normalize,
    anchorId,
    fullCreditHref,
    renderDetailAttribution,
    renderCreditCard,
    attributionLabel,
    badgeLabel,
    detailSummaryText,
    escapeHtml,
    slugify
  });
}());
/* === END FILE: js/image-attribution.js === */
