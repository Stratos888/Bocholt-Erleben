/* === BEGIN FILE: js/opening-status.js | Zweck: zentrale Oeffnungsstatus- und Schliessrisiko-Logik fuer Activities; Umfang: reine Bewertungslogik ohne Datenladen, DOM-Zugriff oder Auto-Start === */
(function () {
  "use strict";

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function asArray(value) {
    if (Array.isArray(value)) return value.filter(Boolean);
    if (value == null || value === "") return [];
    return [value];
  }

  function hasAny(values, candidates) {
    const source = asArray(values);
    const wanted = asArray(candidates);
    return wanted.some((candidate) => source.includes(candidate));
  }

  function itemSearchText(item) {
    return [
      item?.title,
      item?.description,
      item?.category,
      item?.location,
      item?.url,
      ...(Array.isArray(item?.interestTags) ? item.interestTags : []),
      ...(Array.isArray(item?.weatherProfile) ? item.weatherProfile : [])
    ].map(asString).filter(Boolean).join(" ").toLowerCase();
  }

  function hasClosureRisk(item) {
    if (item?.type !== "activity") return false;

    const holidayPolicy = asString(item.holidayPolicy).toLowerCase();
    if (["closed", "limited", "opening_hours_check", "check"].includes(holidayPolicy)) return true;
    if (item.availability === "opening_hours_check") return true;

    const text = itemSearchText(item);
    return [
      "innenstadt",
      "shopping",
      "geschäft",
      "geschaeft",
      "einzelhandel",
      "fußgängerzone",
      "fussgaengerzone",
      "arkaden",
      "museum",
      "ausstellung",
      "bücherei",
      "bibliothek"
    ].some((needle) => text.includes(needle));
  }

  function isWeatherTopTipEligible(item, context) {
    if (context?.weather !== "rain") return true;
    if (item?.type !== "activity") return true;
    if (!hasAny(item.interestTags, "Draußen")) return true;
    return hasAny(item.weatherProfile, ["indoor", "weather_independent"]);
  }

  function isTopTipEligible(item, context) {
    if (context?.isNonBusinessDay && hasClosureRisk(item)) return false;
    if (!isWeatherTopTipEligible(item, context)) return false;
    return true;
  }

  window.OpeningStatus = Object.freeze({
    hasClosureRisk,
    isTopTipEligible
  });
})();
/* === END FILE: js/opening-status.js === */
