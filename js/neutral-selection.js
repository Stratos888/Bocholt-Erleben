/* Deterministic, environment-neutral basis shared by build-time rendering and browsers. */
(function (root, factory) {
  const api = factory();
  if (typeof module === "object" && module.exports) module.exports = api;
  root.NeutralSelection = api;
})(typeof globalThis !== "undefined" ? globalThis : this, function () {
  const text = (value) => String(value == null ? "" : value).trim();
  const isoDate = (value) => /^\d{4}-\d{2}-\d{2}$/.test(text(value)) ? text(value) : "";
  const identity = (item) => text(item && item.id);

  function localDate(now, timeZone) {
    const instant = now instanceof Date ? now : new Date(now || Date.now());
    if (Number.isNaN(instant.getTime())) throw new TypeError("Invalid instant");
    const parts = new Intl.DateTimeFormat("en-CA", {
      timeZone: timeZone || "Europe/Berlin", year: "numeric", month: "2-digit", day: "2-digit"
    }).formatToParts(instant).reduce((out, part) => ((out[part.type] = part.value), out), {});
    return `${parts.year}-${parts.month}-${parts.day}`;
  }

  function isCurrentEvent(item, today) {
    if (!identity(item) || !text(item.title)) return false;
    const start = isoDate(item.date);
    if (!start) return false;
    const end = isoDate(item.endDate || item.end_date) || start;
    return end >= today && end >= start;
  }

  function baseScore(item) {
    const direct = Number(item && item.score);
    if (Number.isFinite(direct)) return direct;
    const weight = Number(item && item.recommendation && item.recommendation.recommendation_weight);
    return Number.isFinite(weight) ? weight : 0;
  }

  function selectEvents(items, options) {
    const opts = options || {};
    const today = isoDate(opts.today) || localDate(opts.now, opts.timeZone);
    const limit = Number.isInteger(opts.limit) ? opts.limit : 6;
    const seen = new Set();
    return (Array.isArray(items) ? items : [])
      .filter((item) => {
        const id = identity(item);
        if (!isCurrentEvent(item, today) || seen.has(id)) return false;
        seen.add(id);
        return true;
      })
      .sort((a, b) => baseScore(b) - baseScore(a) || text(a.date).localeCompare(text(b.date)) || identity(a).localeCompare(identity(b)))
      .slice(0, limit);
  }

  function selectTodayEvents(items, options) {
    const opts = options || {};
    const today = isoDate(opts.today) || localDate(opts.now, opts.timeZone);
    return selectEvents(items, { ...opts, today, limit: Number.MAX_SAFE_INTEGER })
      .filter((item) => text(item.date) <= today && (isoDate(item.endDate || item.end_date) || text(item.date)) >= today)
      .slice(0, Number.isInteger(opts.limit) ? opts.limit : 3);
  }

  function selectActivities(items, options) {
    const limit = Number.isInteger(options && options.limit) ? options.limit : 6;
    const seen = new Set();
    return (Array.isArray(items) ? items : [])
      .filter((item) => {
        const id = identity(item);
        if (!id || !text(item.title || item.name) || seen.has(id)) return false;
        seen.add(id);
        return true;
      })
      .sort((a, b) => baseScore(b) - baseScore(a) || identity(a).localeCompare(identity(b)))
      .slice(0, limit);
  }

  return { identity, localDate, isCurrentEvent, baseScore, selectEvents, selectTodayEvents, selectActivities };
});
