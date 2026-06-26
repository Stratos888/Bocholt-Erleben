/* === BEGIN FILE: js/activity-highlights.js | Zweck: zentrale, quellenbasierte Seasonal-Activity-Highlight-Logik mit Status-Gate fuer zustandsabhaengige Empfehlungen; Umfang: reine Daten-/Scoring-Helfer ohne DOM-Zugriff === */
(function () {
  "use strict";

  const ACTIVE_MODES = new Set(["stable_seasonal", "condition_sensitive"]);
  const HIDDEN_MODES = new Set(["event_only", "candidate_only"]);
  const STATUS_VALUES = new Set(["ok", "watch", "blocked", "unknown"]);
  const DEFAULT_CONDITION_MAX_AGE_DAYS = 7;

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function asArray(value) {
    return Array.isArray(value) ? value.filter((item) => item && typeof item === "object") : [];
  }

  function readObject(value) {
    return value && typeof value === "object" && !Array.isArray(value) ? value : {};
  }

  function normalizeDate(value) {
    const text = asString(value).slice(0, 10);
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(text);
    if (!match) return null;

    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const date = new Date(year, month, day);

    if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day) {
      return null;
    }

    return date;
  }

  function startOfDay(value) {
    const source = value instanceof Date ? value : new Date();
    return new Date(source.getFullYear(), source.getMonth(), source.getDate());
  }

  function diffDays(from, to) {
    return Math.round((startOfDay(to).getTime() - startOfDay(from).getTime()) / 86400000);
  }

  function monthDayToOrdinal(value) {
    const match = /^(\d{2})-(\d{2})$/.exec(asString(value));
    if (!match) return null;

    const month = Number(match[1]);
    const day = Number(match[2]);
    if (month < 1 || month > 12 || day < 1 || day > 31) return null;

    const probe = new Date(2024, month - 1, day);
    if (probe.getMonth() !== month - 1 || probe.getDate() !== day) return null;

    return month * 100 + day;
  }

  function currentMonthDayOrdinal(now) {
    const date = startOfDay(now || new Date());
    return (date.getMonth() + 1) * 100 + date.getDate();
  }

  function inMonthDayWindow(now, starts, ends) {
    const start = monthDayToOrdinal(starts);
    const end = monthDayToOrdinal(ends);
    if (start == null || end == null) return false;

    const current = currentMonthDayOrdinal(now);
    if (start <= end) return current >= start && current <= end;

    return current >= start || current <= end;
  }

  function hasFreshValidity(source, now) {
    const checkedAt = normalizeDate(source.checked_at || source.checkedAt);
    const validUntil = normalizeDate(source.valid_until || source.validUntil);
    const today = startOfDay(now || new Date());

    if (!checkedAt || !validUntil) return false;
    if (checkedAt > today) return false;
    return validUntil >= today;
  }

  function statusState(highlight) {
    const status = readObject(highlight.current_status || highlight.currentStatus);
    const state = asString(status.state || "unknown").toLowerCase();
    return STATUS_VALUES.has(state) ? state : "unknown";
  }

  function conditionStatusIsFresh(highlight, now) {
    const status = readObject(highlight.current_status || highlight.currentStatus);
    const checkedAt = normalizeDate(status.checked_at || status.checkedAt);
    const validUntil = normalizeDate(status.valid_until || status.validUntil);
    const maxAgeDays = Number(highlight.current_status_max_age_days || highlight.currentStatusMaxAgeDays || DEFAULT_CONDITION_MAX_AGE_DAYS);
    const today = startOfDay(now || new Date());

    if (!checkedAt || !validUntil) return false;
    if (checkedAt > today) return false;
    if (validUntil < today) return false;

    return diffDays(checkedAt, today) <= Math.max(1, Number.isFinite(maxAgeDays) ? maxAgeDays : DEFAULT_CONDITION_MAX_AGE_DAYS);
  }

  function sourceTypeAllowedForPositive(highlight) {
    const status = readObject(highlight.current_status || highlight.currentStatus);
    const sourceType = asString(status.source_type || status.sourceType).toLowerCase();
    const policy = readObject(highlight.source_policy || highlight.sourcePolicy);
    const allowed = Array.isArray(policy.positive_requires)
      ? policy.positive_requires.map((entry) => asString(entry).toLowerCase()).filter(Boolean)
      : [];

    if (!allowed.length) return true;
    return allowed.includes(sourceType);
  }

  function normalizeHighlight(highlight) {
    const obj = readObject(highlight);
    const activationMode = asString(obj.activation_mode || obj.activationMode || "stable_seasonal").toLowerCase();

    return {
      ...obj,
      id: asString(obj.id),
      type: asString(obj.type),
      activation_mode: activationMode,
      label: asString(obj.label),
      short_label: asString(obj.short_label || obj.shortLabel || obj.label),
      shortLabel: asString(obj.short_label || obj.shortLabel || obj.label),
      detail_label: asString(obj.detail_label || obj.detailLabel || obj.label),
      detailLabel: asString(obj.detail_label || obj.detailLabel || obj.label),
      starts: asString(obj.starts),
      ends: asString(obj.ends),
      peak_starts: asString(obj.peak_starts || obj.peakStarts),
      peak_ends: asString(obj.peak_ends || obj.peakEnds),
      source_url: asString(obj.source_url || obj.sourceUrl),
      checked_at: asString(obj.checked_at || obj.checkedAt),
      valid_until: asString(obj.valid_until || obj.validUntil),
      public_note: asString(obj.public_note || obj.publicNote),
      publicNote: asString(obj.public_note || obj.publicNote),
      source_label: asString(obj.source_label || obj.sourceLabel),
      sourceLabel: asString(obj.source_label || obj.sourceLabel),
      home_boost: Number(obj.home_boost || obj.homeBoost || 0) || 0,
      homeBoost: Number(obj.home_boost || obj.homeBoost || 0) || 0,
      activity_sort_boost: Number(obj.activity_sort_boost || obj.activitySortBoost || 0) || 0,
      activitySortBoost: Number(obj.activity_sort_boost || obj.activitySortBoost || 0) || 0
    };
  }

  function getHighlights(offer) {
    return asArray(offer?.seasonal_highlights || offer?.seasonalHighlights).map(normalizeHighlight);
  }

  function isInSeason(highlight, now) {
    return inMonthDayWindow(now, highlight.starts, highlight.ends);
  }

  function isInPeak(highlight, now) {
    if (!highlight.peak_starts || !highlight.peak_ends) return false;
    return inMonthDayWindow(now, highlight.peak_starts, highlight.peak_ends);
  }

  function stableSeasonalAllowed(highlight, now) {
    if (!highlight.source_url || !highlight.checked_at || !highlight.valid_until) return false;
    if (!hasFreshValidity(highlight, now)) return false;
    return isInSeason(highlight, now);
  }

  function conditionSensitiveAllowed(highlight, now) {
    if (!isInSeason(highlight, now)) return false;
    if (highlight.requires_current_status !== true && highlight.requiresCurrentStatus !== true) return false;
    if (statusState(highlight) !== "ok") return false;
    if (!conditionStatusIsFresh(highlight, now)) return false;
    return sourceTypeAllowedForPositive(highlight);
  }

  function isActiveHighlight(highlight, context = {}) {
    const item = normalizeHighlight(highlight);
    const now = context.now instanceof Date ? context.now : new Date();

    if (!item.id || !item.label || !item.starts || !item.ends) return false;
    if (HIDDEN_MODES.has(item.activation_mode)) return false;
    if (!ACTIVE_MODES.has(item.activation_mode)) return false;

    if (item.activation_mode === "stable_seasonal") {
      return stableSeasonalAllowed(item, now);
    }

    if (item.activation_mode === "condition_sensitive") {
      return conditionSensitiveAllowed(item, now);
    }

    return false;
  }

  function getActiveHighlights(offer, context = {}) {
    const now = context.now instanceof Date ? context.now : new Date();
    return getHighlights(offer)
      .filter((highlight) => isActiveHighlight(highlight, { ...context, now }))
      .sort((a, b) => {
        const peakDiff = Number(isInPeak(b, now)) - Number(isInPeak(a, now));
        if (peakDiff) return peakDiff;
        const boostDiff = Number(b.home_boost || 0) - Number(a.home_boost || 0);
        if (boostDiff) return boostDiff;
        return a.id.localeCompare(b.id, "de");
      });
  }

  function getPrimaryActiveHighlight(offer, context = {}) {
    return getActiveHighlights(offer, context)[0] || null;
  }

  function getPrimaryHighlight(offer, context = {}) {
    return getPrimaryActiveHighlight(offer, context);
  }

  function getSurfaceBoost(offer, context = {}) {
    const surface = asString(context.surface || "home");
    const now = context.now instanceof Date ? context.now : new Date();
    const highlight = getPrimaryActiveHighlight(offer, { ...context, now });
    if (!highlight) return 0;

    let score = surface === "activity" ? highlight.activity_sort_boost : highlight.home_boost;
    if (surface !== "activity" && isInPeak(highlight, now)) score += 4;
    return Number.isFinite(score) ? score : 0;
  }

  function getScoreAdjustment(offer, context = {}) {
    const mode = asString(context.mode || context.surface || "activity");
    return getSurfaceBoost(offer, { ...context, surface: mode === "home" ? "home" : "activity" });
  }

  function getReasonLabel(offer, context = {}) {
    const highlight = getPrimaryActiveHighlight(offer, context);
    if (!highlight) return "";
    return highlight.short_label || highlight.label;
  }

  function getDetailBlock(offer, context = {}) {
    const highlight = getPrimaryActiveHighlight(offer, context);
    if (!highlight) return null;

    return {
      title: highlight.detail_label || highlight.label,
      label: highlight.label,
      shortLabel: highlight.short_label || highlight.label,
      period: highlight.starts && highlight.ends ? `${highlight.starts} bis ${highlight.ends}` : "",
      note: highlight.public_note || "",
      sourceLabel: highlight.source_label || "Quelle",
      sourceUrl: highlight.source_url || ""
    };
  }

  function getConditionStatusNote(offer, context = {}) {
    const now = context.now instanceof Date ? context.now : new Date();
    const highlights = getHighlights(offer).filter((highlight) => highlight.activation_mode === "condition_sensitive");
    const active = highlights.find((highlight) => isActiveHighlight(highlight, { ...context, now }));
    if (active) return null;

    const candidates = highlights.filter((highlight) => isInSeason(highlight, now));
    if (!candidates.length) return null;

    const candidate = candidates[0];
    const status = readObject(candidate.current_status || candidate.currentStatus);
    const state = statusState(candidate);
    const publicNote = asString(status.public_note || status.publicNote || candidate.status_public_note || candidate.statusPublicNote);

    if (state === "blocked") {
      return {
        title: asString(status.label) || "Statushinweis",
        label: "Aktuell nicht als Highlight empfohlen",
        note: publicNote || asString(status.reason) || "Aktuell liegt kein positives Statussignal für dieses zustandsabhängige Highlight vor.",
        state
      };
    }

    if (state === "watch" || state === "unknown") {
      return {
        title: "Statushinweis",
        label: "Aktuellen Status prüfen",
        note: publicNote || "Für dieses zustandsabhängige Highlight liegt aktuell keine frische positive Freigabe vor.",
        state
      };
    }

    return null;
  }

  function getSearchTerms(offer, context = {}) {
    const active = getActiveHighlights(offer, context).flatMap((highlight) => [
      highlight.label,
      highlight.short_label,
      highlight.detail_label,
      highlight.public_note
    ]);

    const all = getHighlights(offer).flatMap((highlight) => [
      highlight.label,
      highlight.short_label,
      highlight.detail_label
    ]);

    return Array.from(new Set([...active, ...all].map(asString).filter(Boolean)));
  }

  window.BEActivityHighlights = {
    getHighlights,
    getActiveHighlights,
    getPrimaryActiveHighlight,
    getPrimaryHighlight,
    getReasonLabel,
    getSurfaceBoost,
    getScoreAdjustment,
    getDetailBlock,
    getConditionStatusNote,
    getSearchTerms,
    isActiveHighlight,
    isInSeason,
    isInPeak
  };
}());
/* === END FILE: js/activity-highlights.js === */
