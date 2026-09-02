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

  function addDaysIso(value, days) {
    const valid = isoDate(value);
    if (!valid) return "";
    const [year, month, day] = valid.split("-").map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    date.setUTCDate(date.getUTCDate() + Number(days || 0));
    return date.toISOString().slice(0, 10);
  }

  function weekdayIso(value) {
    const valid = isoDate(value);
    if (!valid) return null;
    const [year, month, day] = valid.split("-").map(Number);
    return new Date(Date.UTC(year, month - 1, day)).getUTCDay();
  }

  function weekendRange(options) {
    const opts = options || {};
    const today = isoDate(opts.today) || localDate(opts.now, opts.timeZone);
    const weekday = weekdayIso(today);
    if (weekday == null) throw new TypeError("Invalid local date");
    let fridayOffset;
    if (weekday === 5) fridayOffset = 0;
    else if (weekday === 6) fridayOffset = -1;
    else if (weekday === 0) fridayOffset = -2;
    else fridayOffset = (5 - weekday + 7) % 7;
    const start = addDaysIso(today, fridayOffset);
    return { start, end: addDaysIso(start, 2) };
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

  function selectWeekendEvents(items, options) {
    const opts = options || {};
    const range = weekendRange(opts);
    const limit = Number.isInteger(opts.limit) ? opts.limit : Number.MAX_SAFE_INTEGER;
    const seen = new Set();
    return (Array.isArray(items) ? items : [])
      .filter((item) => {
        const id = identity(item);
        const start = isoDate(item && item.date);
        const end = isoDate(item && (item.endDate || item.end_date)) || start;
        if (!id || !text(item && item.title) || !start || end < start || seen.has(id)) return false;
        if (end < range.start || start > range.end) return false;
        seen.add(id);
        return true;
      })
      .sort((a, b) => text(a.date).localeCompare(text(b.date)) || baseScore(b) - baseScore(a) || identity(a).localeCompare(identity(b)))
      .slice(0, limit);
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

  return {
    identity,
    localDate,
    weekendRange,
    isCurrentEvent,
    baseScore,
    selectEvents,
    selectTodayEvents,
    selectWeekendEvents,
    selectActivities
  };
});