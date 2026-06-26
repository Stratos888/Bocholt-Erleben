/* === BEGIN FILE: js/activity-highlights.js | Zweck: zentrale, quellenbasierte Saison-/Zeit-Highlight-Logik fuer Activities; Umfang: reine Datenlogik ohne DOM-Autostart === */
(function () {
  "use strict";

  const VERIFIED_CONFIDENCE = new Set([
    "verified_stable",
    "verified_current",
    "official_source"
  ]);

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function asArray(value) {
    return Array.isArray(value) ? value : [];
  }

  function readObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : {};
  }

  function unique(values) {
    const out = [];
    values.forEach((value) => {
      const text = asString(value);
      if (text && !out.includes(text)) out.push(text);
    });
    return out;
  }

  function parseDateLocal(value) {
    const text = asString(value);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const date = new Date(year, month, day);

    if (
      date.getFullYear() !== year ||
      date.getMonth() !== month ||
      date.getDate() !== day
    ) {
      return null;
    }

    return date;
  }

  function parseMonthDay(value) {
    const text = asString(value);
    const match = /^(\d{2})-(\d{2})$/.exec(text);
    if (!match) return null;

    const month = Number(match[1]);
    const day = Number(match[2]);
    if (month < 1 || month > 12 || day < 1 || day > 31) return null;

    return { month, day };
  }

  function monthDayToDate(monthDay, year) {
    if (!monthDay) return null;

    const date = new Date(year, monthDay.month - 1, monthDay.day);
    if (date.getMonth() !== monthDay.month - 1 || date.getDate() !== monthDay.day) return null;
    return date;
  }

  function startOfDay(value) {
    const date = value instanceof Date ? value : new Date();
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
  }

  function isDateExpired(validUntil, now) {
    const date = parseDateLocal(validUntil);
    if (!date) return false;
    return startOfDay(date).getTime() < startOfDay(now).getTime();
  }

  function isInMonthDayWindow(startText, endText, now) {
    const start = parseMonthDay(startText);
    const end = parseMonthDay(endText);
    const current = startOfDay(now || new Date());
    if (!start || !end) return false;

    const year = current.getFullYear();
    let startDate = monthDayToDate(start, year);
    let endDate = monthDayToDate(end, year);
    if (!startDate || !endDate) return false;

    if (endDate.getTime() < startDate.getTime()) {
      if (current.getTime() < startDate.getTime()) {
        startDate = monthDayToDate(start, year - 1);
      } else {
        endDate = monthDayToDate(end, year + 1);
      }
    }

    return current.getTime() >= startOfDay(startDate).getTime() && current.getTime() <= startOfDay(endDate).getTime();
  }

  function normalizeHighlight(raw, now) {
    const obj = readObject(raw);
    const id = asString(obj.id);
    const label = asString(obj.label);
    const sourceUrl = asString(obj.source_url || obj.sourceUrl);
    const confidence = asString(obj.confidence || obj.verification);

    if (!id || !label) return null;
    if (!VERIFIED_CONFIDENCE.has(confidence)) return null;
    if (!sourceUrl) return null;
    if (isDateExpired(obj.valid_until || obj.validUntil, now)) return null;

    const active = isInMonthDayWindow(obj.starts, obj.ends, now);
    const peak = active && isInMonthDayWindow(obj.peak_starts || obj.peakStarts, obj.peak_ends || obj.peakEnds, now);

    return Object.freeze({
      id,
      type: asString(obj.type) || "seasonal",
      label,
      shortLabel: asString(obj.short_label || obj.shortLabel) || label,
      publicNote: asString(obj.public_note || obj.publicNote),
      sourceUrl,
      checkedAt: asString(obj.checked_at || obj.checkedAt),
      validUntil: asString(obj.valid_until || obj.validUntil),
      starts: asString(obj.starts),
      ends: asString(obj.ends),
      peakStarts: asString(obj.peak_starts || obj.peakStarts),
      peakEnds: asString(obj.peak_ends || obj.peakEnds),
      confidence,
      homeBoost: Number.isFinite(Number(obj.home_boost || obj.homeBoost)) ? Number(obj.home_boost || obj.homeBoost) : 18,
      activitySortBoost: Number.isFinite(Number(obj.activity_sort_boost || obj.activitySortBoost)) ? Number(obj.activity_sort_boost || obj.activitySortBoost) : 8,
      active,
      peak
    });
  }

  function getHighlights(offer, options = {}) {
    const now = options.now instanceof Date ? options.now : new Date();
    return asArray(offer?.seasonal_highlights || offer?.seasonalHighlights)
      .map((entry) => normalizeHighlight(entry, now))
      .filter(Boolean);
  }

  function getActiveHighlights(offer, options = {}) {
    return getHighlights(offer, options).filter((highlight) => highlight.active);
  }

  function getPrimaryHighlight(offer, options = {}) {
    const active = getActiveHighlights(offer, options);
    if (!active.length) return null;

    return [...active].sort((a, b) => {
      const peakDiff = Number(b.peak) - Number(a.peak);
      if (peakDiff) return peakDiff;

      const scoreDiff = b.activitySortBoost - a.activitySortBoost;
      if (scoreDiff) return scoreDiff;

      return a.label.localeCompare(b.label, "de");
    })[0] || null;
  }

  function hasActiveHighlight(offer, options = {}) {
    return !!getPrimaryHighlight(offer, options);
  }

  function getScoreAdjustment(offer, options = {}) {
    const mode = asString(options.mode || "activity");
    const highlight = getPrimaryHighlight(offer, options);
    if (!highlight) return 0;

    const base = mode === "home" ? highlight.homeBoost : highlight.activitySortBoost;
    return base + (highlight.peak ? Math.max(3, Math.round(base * 0.25)) : 0);
  }

  function getSearchTerms(offer, options = {}) {
    return unique(getHighlights(offer, options).flatMap((highlight) => [
      highlight.label,
      highlight.shortLabel,
      highlight.publicNote,
      highlight.type,
      highlight.peak ? "Jetzt besonders" : "",
      "Saisonales Highlight"
    ]));
  }

  function formatWindowLabel(highlight) {
    if (!highlight) return "";

    const start = asString(highlight.starts);
    const end = asString(highlight.ends);
    if (!start || !end) return "";

    const monthNames = [
      "Januar", "Februar", "März", "April", "Mai", "Juni",
      "Juli", "August", "September", "Oktober", "November", "Dezember"
    ];

    const parse = (value) => {
      const parsed = parseMonthDay(value);
      return parsed ? monthNames[parsed.month - 1] : "";
    };

    const startLabel = parse(start);
    const endLabel = parse(end);
    if (!startLabel || !endLabel) return "";
    if (startLabel === endLabel) return startLabel;
    return `${startLabel} bis ${endLabel}`;
  }

  window.BEActivityHighlights = {
    getHighlights,
    getActiveHighlights,
    getPrimaryHighlight,
    hasActiveHighlight,
    getScoreAdjustment,
    getSearchTerms,
    formatWindowLabel
  };
}());
/* === END FILE: js/activity-highlights.js === */
