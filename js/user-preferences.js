/* === BEGIN FILE: js/user-preferences.js | Zweck: lokales Nutzerprofil fuer Punkt 9 "Fuer dich in Bocholt"; Umfang: Interessen, Merkliste, Ausblendungen und Modus ohne Account/Sync und ohne DOM-Zugriff === */
(function () {
  "use strict";

  const STORAGE_KEY = "bocholt_erleben.user_preferences.v1";
  const SCHEMA_VERSION = 1;

  const DEFAULT_PROFILE = Object.freeze({
    schemaVersion: SCHEMA_VERSION,
    interests: [],
    saved: [],
    dismissed: [],
    lastMode: "for_you",
    modeCounts: {},
    updatedAt: ""
  });

  const ALLOWED_MODES = Object.freeze([
    "for_you",
    "today",
    "evening",
    "weekend",
    "family",
    "outdoor",
    "rain"
  ]);

  const ALLOWED_INTERESTS = Object.freeze([
    "Familie",
    "Draußen",
    "Bei Regen",
    "Kultur",
    "Musik",
    "Essen & Trinken",
    "Sport & Bewegung",
    "Natur",
    "Kurz & spontan",
    "Wochenende",
    "Café / Einkehr",
    "Wasser",
    "Tiere",
    "Spielplatz"
  ]);

  function hasStorage() {
    try {
      return typeof window !== "undefined" && !!window.localStorage;
    } catch (_) {
      return false;
    }
  }

  function nowIso() {
    return new Date().toISOString();
  }

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function asArray(value) {
    if (Array.isArray(value)) {
      return unique(value.map(asString).filter(Boolean));
    }

    const text = asString(value);
    if (!text) return [];

    return unique(
      text
        .split(/[,;|]/)
        .map(asString)
        .filter(Boolean)
    );
  }

  function unique(values) {
    const out = [];

    values.forEach((value) => {
      if (value && !out.includes(value)) {
        out.push(value);
      }
    });

    return out;
  }

  function normalizeMode(mode) {
    const value = asString(mode);
    return ALLOWED_MODES.includes(value) ? value : DEFAULT_PROFILE.lastMode;
  }

  function normalizeInterestList(values) {
    const allowed = new Set(ALLOWED_INTERESTS);
    return unique(asArray(values).filter((value) => allowed.has(value)));
  }

  function normalizeItemKey(input, maybeId) {
    if (typeof input === "object" && input !== null) {
      const type = asString(input.type || input.content_type || input.kind);
      const id = asString(input.id);
      return type && id ? `${type}:${id}` : id;
    }

    const type = asString(input);
    const id = asString(maybeId);

    if (type && id) return `${type}:${id}`;
    return type || id;
  }

  function normalizeProfile(raw) {
    const source = raw && typeof raw === "object" ? raw : {};

    const profile = {
      schemaVersion: SCHEMA_VERSION,
      interests: normalizeInterestList(source.interests),
      saved: unique(asArray(source.saved)),
      dismissed: unique(asArray(source.dismissed)),
      lastMode: normalizeMode(source.lastMode),
      modeCounts: source.modeCounts && typeof source.modeCounts === "object" && !Array.isArray(source.modeCounts)
        ? { ...source.modeCounts }
        : {},
      updatedAt: asString(source.updatedAt)
    };

    return profile;
  }

  function readProfile() {
    if (!hasStorage()) {
      return normalizeProfile(DEFAULT_PROFILE);
    }

    try {
      const rawText = window.localStorage.getItem(STORAGE_KEY);
      if (!rawText) return normalizeProfile(DEFAULT_PROFILE);

      return normalizeProfile(JSON.parse(rawText));
    } catch (_) {
      return normalizeProfile(DEFAULT_PROFILE);
    }
  }

  function writeProfile(profile) {
    const normalized = normalizeProfile({
      ...profile,
      updatedAt: nowIso()
    });

    if (!hasStorage()) {
      return normalized;
    }

    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(normalized));
    } catch (_) {
      // LocalStorage kann z. B. im privaten Modus blockiert sein.
    }

    return normalized;
  }

  function getProfile() {
    return readProfile();
  }

  function resetProfile() {
    if (hasStorage()) {
      try {
        window.localStorage.removeItem(STORAGE_KEY);
      } catch (_) {}
    }

    return normalizeProfile(DEFAULT_PROFILE);
  }

  function setInterests(interests) {
    const profile = readProfile();
    profile.interests = normalizeInterestList(interests);
    return writeProfile(profile);
  }

  function toggleInterest(interest) {
    const value = asString(interest);
    const allowed = new Set(ALLOWED_INTERESTS);

    if (!allowed.has(value)) {
      return readProfile();
    }

    const profile = readProfile();
    profile.interests = profile.interests.includes(value)
      ? profile.interests.filter((item) => item !== value)
      : unique([...profile.interests, value]);

    return writeProfile(profile);
  }

  function setLastMode(mode) {
    const profile = readProfile();
    const normalizedMode = normalizeMode(mode);

    profile.lastMode = normalizedMode;
    profile.modeCounts = {
      ...profile.modeCounts,
      [normalizedMode]: Number(profile.modeCounts?.[normalizedMode] || 0) + 1
    };

    return writeProfile(profile);
  }

  function saveItem(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    if (!key) return readProfile();

    const profile = readProfile();
    profile.saved = unique([...profile.saved, key]);
    profile.dismissed = profile.dismissed.filter((item) => item !== key);

    return writeProfile(profile);
  }

  function unsaveItem(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    if (!key) return readProfile();

    const profile = readProfile();
    profile.saved = profile.saved.filter((item) => item !== key);

    return writeProfile(profile);
  }

  function toggleSaved(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    if (!key) return readProfile();

    const profile = readProfile();

    return profile.saved.includes(key)
      ? unsaveItem(key)
      : saveItem(key);
  }

  function dismissItem(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    if (!key) return readProfile();

    const profile = readProfile();
    profile.dismissed = unique([...profile.dismissed, key]);
    profile.saved = profile.saved.filter((item) => item !== key);

    return writeProfile(profile);
  }

  function undismissItem(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    if (!key) return readProfile();

    const profile = readProfile();
    profile.dismissed = profile.dismissed.filter((item) => item !== key);

    return writeProfile(profile);
  }

  function isSaved(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    return !!key && readProfile().saved.includes(key);
  }

  function isDismissed(input, maybeId) {
    const key = normalizeItemKey(input, maybeId);
    return !!key && readProfile().dismissed.includes(key);
  }

  function toRecommendationContext(overrides) {
    const profile = readProfile();
    const extra = overrides && typeof overrides === "object" ? overrides : {};

    return {
      mode: normalizeMode(extra.mode || profile.lastMode),
      interests: normalizeInterestList(extra.interests || profile.interests),
      saved: unique(asArray(extra.saved || profile.saved)),
      dismissed: unique(asArray(extra.dismissed || profile.dismissed)),
      weather: asString(extra.weather || "unknown") || "unknown",
      now: extra.now instanceof Date ? extra.now : new Date()
    };
  }

  window.BEUserPreferences = {
    allowedInterests: ALLOWED_INTERESTS.slice(),
    allowedModes: ALLOWED_MODES.slice(),
    getProfile,
    resetProfile,
    setInterests,
    toggleInterest,
    setLastMode,
    saveItem,
    unsaveItem,
    toggleSaved,
    dismissItem,
    undismissItem,
    isSaved,
    isDismissed,
    toRecommendationContext
  };
}());
/* === END FILE: js/user-preferences.js === */