/* === BEGIN FILE: js/weather-context.js | Zweck: Wetterkontext fuer Punkt 9 "Fuer dich in Bocholt"; Umfang: Bocholt-Wetterklasse mit Open-Meteo, Cache und sicherem Fallback ohne DOM-Autostart === */
(function () {
  "use strict";

  const STORAGE_KEY = "bocholt_erleben.weather_context.v2";
  const CACHE_TTL_MS = 30 * 60 * 1000;

  const BOCHOLT = Object.freeze({
    latitude: 51.8384,
    longitude: 6.6151,
    timezone: "Europe/Berlin"
  });

  const WEATHER_CLASSES = Object.freeze([
    "dry",
    "rain",
    "hot",
    "cold",
    "windy",
    "unknown"
  ]);

  const RAIN_CODES = new Set([
    51, 53, 55,
    56, 57,
    61, 63, 65,
    66, 67,
    80, 81, 82,
    95, 96, 99
  ]);

  const SNOW_CODES = new Set([
    71, 73, 75,
    77,
    85, 86
  ]);

  function hasStorage() {
    try {
      return typeof window !== "undefined" && !!window.localStorage;
    } catch (_) {
      return false;
    }
  }

  function asNumber(value, fallback) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  }

  function asString(value) {
    return value == null ? "" : String(value).trim();
  }

  function nowMs() {
    return Date.now();
  }

  function safeJsonParse(text) {
    try {
      return JSON.parse(text);
    } catch (_) {
      return null;
    }
  }

  function buildForecastUrl(config) {
    const cfg = config && typeof config === "object" ? config : {};
    const latitude = asNumber(cfg.latitude, BOCHOLT.latitude);
    const longitude = asNumber(cfg.longitude, BOCHOLT.longitude);
    const timezone = asString(cfg.timezone) || BOCHOLT.timezone;

    const params = new URLSearchParams({
      latitude: String(latitude),
      longitude: String(longitude),
      current: "temperature_2m,precipitation,weather_code,wind_speed_10m",
      hourly: "temperature_2m,precipitation_probability,precipitation,weather_code,wind_speed_10m",
      timezone,
      forecast_days: "1"
    });

    return `https://api.open-meteo.com/v1/forecast?${params.toString()}`;
  }

  function normalizeWeatherClass(value) {
    const normalized = asString(value);
    return WEATHER_CLASSES.includes(normalized) ? normalized : "unknown";
  }

  function isRainCode(code) {
    return RAIN_CODES.has(asNumber(code, null));
  }

  function isSnowCode(code) {
    return SNOW_CODES.has(asNumber(code, null));
  }

  function classifyCurrentWeather(current) {
    const data = current && typeof current === "object" ? current : {};

    const temperature = asNumber(data.temperature_2m, null);
    const precipitation = asNumber(data.precipitation, 0);
    const weatherCode = asNumber(data.weather_code, null);
    const windSpeed = asNumber(data.wind_speed_10m, 0);

    if (weatherCode == null && temperature == null) {
      return "unknown";
    }

    if (precipitation > 0.2 || isRainCode(weatherCode)) {
      return "rain";
    }

    if (isSnowCode(weatherCode) || (temperature != null && temperature <= 4)) {
      return "cold";
    }

    if (temperature != null && temperature >= 28) {
      return "hot";
    }

    if (windSpeed >= 35) {
      return "windy";
    }

    return "dry";
  }

  function readHourlyRows(hourly) {
    const data = hourly && typeof hourly === "object" ? hourly : {};
    const times = Array.isArray(data.time) ? data.time : [];

    return times.map((time, index) => {
      const timestamp = Date.parse(time);
      if (!Number.isFinite(timestamp)) return null;

      return {
        time: asString(time),
        timestamp,
        temperature: asNumber(data.temperature_2m?.[index], null),
        precipitationProbability: asNumber(data.precipitation_probability?.[index], null),
        precipitation: asNumber(data.precipitation?.[index], 0),
        weatherCode: asNumber(data.weather_code?.[index], null),
        windSpeed: asNumber(data.wind_speed_10m?.[index], 0)
      };
    }).filter(Boolean);
  }

  function summarizeHourlyForecast(hourlyRows, now) {
    const rows = Array.isArray(hourlyRows) ? hourlyRows : [];
    const base = now instanceof Date && Number.isFinite(now.getTime()) ? now : new Date();
    const nowTime = base.getTime();
    const nextSixHours = nowTime + 6 * 60 * 60 * 1000;
    const endOfDay = new Date(base.getFullYear(), base.getMonth(), base.getDate() + 1).getTime();

    const futureToday = rows.filter((row) => row.timestamp >= nowTime - 30 * 60 * 1000 && row.timestamp < endOfDay);
    const nearTerm = futureToday.filter((row) => row.timestamp <= nextSixHours);
    const relevant = nearTerm.length ? nearTerm : futureToday;

    const rainRowsToday = futureToday.filter((row) => isRainCode(row.weatherCode) || row.precipitation > 0.2 || row.precipitationProbability >= 45);
    const rainRowsNearTerm = nearTerm.filter((row) => isRainCode(row.weatherCode) || row.precipitation > 0.2 || row.precipitationProbability >= 45);

    const maxRainProbability = futureToday.reduce((max, row) => Math.max(max, asNumber(row.precipitationProbability, 0)), 0);
    const nearTermRainProbability = nearTerm.reduce((max, row) => Math.max(max, asNumber(row.precipitationProbability, 0)), 0);
    const rainAmountToday = futureToday.reduce((sum, row) => sum + asNumber(row.precipitation, 0), 0);
    const rainAmountNearTerm = nearTerm.reduce((sum, row) => sum + asNumber(row.precipitation, 0), 0);
    const maxWindSpeed = relevant.reduce((max, row) => Math.max(max, asNumber(row.windSpeed, 0)), 0);

    const showersLikely = rainRowsNearTerm.length >= 1 || rainRowsToday.length >= 3 || maxRainProbability >= 55 || rainAmountToday >= 1.5;
    const rainRisk = showersLikely
      ? (rainRowsNearTerm.length >= 1 || nearTermRainProbability >= 45 || rainAmountNearTerm >= 0.5 ? "near_term" : "later_today")
      : "low";

    return {
      rainRisk,
      showersLikely,
      rainHoursToday: rainRowsToday.length,
      rainHoursNearTerm: rainRowsNearTerm.length,
      maxRainProbability,
      nearTermRainProbability,
      rainAmountToday: Math.round(rainAmountToday * 10) / 10,
      rainAmountNearTerm: Math.round(rainAmountNearTerm * 10) / 10,
      maxWindSpeed: Math.round(maxWindSpeed)
    };
  }

  function classifyForecastWeather(current, forecast) {
    const currentClass = classifyCurrentWeather(current);
    const temperature = asNumber(current?.temperature_2m, null);

    if (currentClass === "rain" || forecast.showersLikely) {
      return "rain";
    }

    if (currentClass === "cold") return "cold";
    if (currentClass === "hot") return "hot";
    if (currentClass === "windy" || forecast.maxWindSpeed >= 35) return "windy";
    if (temperature == null && !forecast.rainHoursToday) return "unknown";

    return "dry";
  }

  function buildOutdoorSummary(weatherClass, forecast) {
    if (weatherClass === "rain") {
      if (forecast.rainRisk === "near_term") {
        return "wechselhaft mit Schauern – wetterfest planen.";
      }
      return "später Schauer möglich – draußen nur mit Plan B.";
    }

    if (weatherClass === "hot") return "Wasser & Schatten sind gute Ideen.";
    if (weatherClass === "cold") return "kurz oder drinnen passt besser.";
    if (weatherClass === "windy") return "windig – geschützte Orte passen besser.";
    if (weatherClass === "dry") return "heute gut für draußen.";

    return "ruhig vorsortiert für heute.";
  }

  function buildContext(payload, source) {
    const data = payload && typeof payload === "object" ? payload : {};
    const current = data.current && typeof data.current === "object" ? data.current : {};
    const forecast = summarizeHourlyForecast(readHourlyRows(data.hourly), new Date());
    const weatherClass = classifyForecastWeather(current, forecast);

    return {
      weather: normalizeWeatherClass(weatherClass),
      outdoorFit: weatherClass === "dry" ? "good" : weatherClass === "rain" ? "changeable" : "limited",
      rainRisk: forecast.rainRisk,
      showersLikely: forecast.showersLikely,
      summaryLabel: buildOutdoorSummary(weatherClass, forecast),
      source: source || "unknown",
      location: "Bocholt",
      latitude: BOCHOLT.latitude,
      longitude: BOCHOLT.longitude,
      temperature: Number.isFinite(Number(current.temperature_2m)) ? Number(current.temperature_2m) : null,
      precipitation: Number.isFinite(Number(current.precipitation)) ? Number(current.precipitation) : null,
      weatherCode: Number.isFinite(Number(current.weather_code)) ? Number(current.weather_code) : null,
      windSpeed: Number.isFinite(Number(current.wind_speed_10m)) ? Number(current.wind_speed_10m) : null,
      forecast,
      fetchedAt: new Date().toISOString()
    };
  }

  function fallbackContext(reason) {
    return {
      weather: "unknown",
      outdoorFit: "unknown",
      rainRisk: "unknown",
      showersLikely: false,
      summaryLabel: "ruhig vorsortiert für heute.",
      source: "fallback",
      location: "Bocholt",
      latitude: BOCHOLT.latitude,
      longitude: BOCHOLT.longitude,
      temperature: null,
      precipitation: null,
      weatherCode: null,
      windSpeed: null,
      fetchedAt: new Date().toISOString(),
      reason: asString(reason) || "unknown"
    };
  }

  function readCachedContext(maxAgeMs) {
    if (!hasStorage()) return null;

    try {
      const raw = window.localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;

      const cached = safeJsonParse(raw);
      if (!cached || typeof cached !== "object") return null;

      const timestamp = asNumber(cached.cachedAt, 0);
      if (!timestamp || nowMs() - timestamp > maxAgeMs) return null;

      const context = cached.context && typeof cached.context === "object" ? cached.context : null;
      if (!context) return null;

      return {
        ...context,
        source: "cache"
      };
    } catch (_) {
      return null;
    }
  }

  function writeCachedContext(context) {
    if (!hasStorage()) return;

    try {
      window.localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
          cachedAt: nowMs(),
          context
        })
      );
    } catch (_) {
      // Cache ist optional. Bei blockiertem localStorage bleibt der Wetterkontext funktional.
    }
  }

  async function fetchWeatherContext(options) {
    const opts = options && typeof options === "object" ? options : {};
    const url = buildForecastUrl(opts);

    if (typeof fetch !== "function") {
      return fallbackContext("fetch_unavailable");
    }

    try {
      const response = await fetch(url, { cache: "no-store" });
      if (!response || !response.ok) {
        return fallbackContext(`http_${response ? response.status : "unknown"}`);
      }

      const payload = await response.json();
      const context = buildContext(payload, "api");
      writeCachedContext(context);

      return context;
    } catch (error) {
      return fallbackContext(error && error.name ? error.name : "fetch_failed");
    }
  }

  async function getWeatherContext(options) {
    const opts = options && typeof options === "object" ? options : {};
    const maxAgeMs = Number.isFinite(Number(opts.maxAgeMs)) ? Number(opts.maxAgeMs) : CACHE_TTL_MS;

    if (!opts.force) {
      const cached = readCachedContext(maxAgeMs);
      if (cached) return cached;
    }

    return fetchWeatherContext(opts);
  }

  function toRecommendationWeather(context) {
    const data = context && typeof context === "object" ? context : { weather: context };
    const weather = normalizeWeatherClass(data.weather);

    if (weather === "dry" && (data.showersLikely || data.rainRisk === "near_term" || data.rainRisk === "later_today")) {
      return "rain";
    }

    return weather;
  }

  window.BEWeatherContext = {
    buildForecastUrl,
    classifyCurrentWeather,
    getWeatherContext,
    toRecommendationWeather,
    fallbackContext
  };
}());
/* === END FILE: js/weather-context.js === */