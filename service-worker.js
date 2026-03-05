// BEGIN: FILE_HEADER_SERVICE_WORKER
// Datei: service-worker.js
// Zweck:
// - Caching & Offline-Fähigkeit der App (PWA)
// - Versioniertes Cache-Handling pro Deploy
// - Kontrolle über Fetch-Strategien (Cache / Network)
//
// Verantwortlich für:
// - Installation / Aktivierung des Service Workers
// - Cache-Aufbau und -Bereinigung
// - Abfangen von Fetch-Requests
//
// Nicht verantwortlich für:
// - UI-Logik oder Darstellung
// - Event-Daten, Filter oder Rendering
// - App-Initialisierung (main.js)
//
// Contract:
// - Cache-Version kommt aus /meta/build.json
// - darf niemals DOM manipulieren
// - Änderungen hier wirken global → mit Vorsicht ändern
// END: FILE_HEADER_SERVICE_WORKER


/* === BEGIN BLOCK: BUILD VERSION RESOLUTION (no manual bump) ===
Zweck: Version kommt automatisch aus /meta/build.txt (vom Deploy), kein händisches Hochzählen.
Umfang: Version/Cache-Namen werden zur Laufzeit initialisiert.
=== */
let VERSION = "dev"; // Fallback, falls build.txt fehlt
let STATIC_CACHE = `be-static-${VERSION}`;
let RUNTIME_CACHE = `be-runtime-${VERSION}`;

async function resolveBuildVersion() {
  try {
    const res = await fetch('/meta/build.txt', { cache: 'no-store' });
    if (!res.ok) throw new Error("/meta/build.txt not ok");
    const v = (await res.text()).trim();

    if (v) {
      VERSION = v;
      STATIC_CACHE = `be-static-${VERSION}`;
      RUNTIME_CACHE = `be-runtime-${VERSION}`;
      return;
    }
  } catch (_) {
    // offline/fehler: Fallback auf bestehende produktive Cache-Namen (wichtig für wiederholte Offline-Reloads)
    try {
      const keys = await caches.keys();

      const staticKeys = keys
        .filter((k) => k.startsWith("be-static-") && k !== "be-static-dev")
        .sort();
      const runtimeKeys = keys
        .filter((k) => k.startsWith("be-runtime-") && k !== "be-runtime-dev")
        .sort();

      const latestStatic = staticKeys.length ? staticKeys[staticKeys.length - 1] : null;
      const latestRuntime = runtimeKeys.length ? runtimeKeys[runtimeKeys.length - 1] : null;

      // Prefer STATIC version as source of truth
      if (latestStatic) {
        const v = latestStatic.replace("be-static-", "");
        if (v) {
          VERSION = v;
          STATIC_CACHE = `be-static-${VERSION}`;
          RUNTIME_CACHE = `be-runtime-${VERSION}`;
          return;
        }
      }

      // Fallback: runtime-only (rare)
      if (latestRuntime) {
        const v = latestRuntime.replace("be-runtime-", "");
        if (v) {
          VERSION = v;
          STATIC_CACHE = `be-static-${VERSION}`;
          RUNTIME_CACHE = `be-runtime-${VERSION}`;
          return;
        }
      }
    } catch (_) {
      // letzter Fallback bleibt dev
    }
  }
}
/* === END BLOCK: BUILD VERSION RESOLUTION (no manual bump) === */



/* === BEGIN BLOCK: STATIC ASSETS + INDEX ASSET PRECACHE (offline shell works) ===
Zweck:
- Offline darf niemals "weiß" sein: App-Shell + essentielle CSS/JS müssen offline verfügbar sein.
- Da Querystrings Teil des Cache-Keys sind (kein ignoreSearch), müssen wir die exakten URLs aus index.html cachen.
Umfang:
- Erweitert STATIC_ASSETS minimal (Fallback ohne Query).
- Fügt Helper hinzu, der index.html parst und alle same-origin CSS/JS-URLs (inkl. ?v=...) in STATIC_CACHE precacht.
=== */
const STATIC_ASSETS = [
  "/",
  "/index.html",
  "/manifest.json",

  // Fallbacks (ohne Query) – falls HTML mal ohne ?v ausliefert
  "/css/style.css",
  "/js/main.js",

  // Offline-Datenbasis (für "first offline reload" auf Mobile)
  "/data/events.json",
  "/data/locations.json",

  "/icons/app/icon-180.png",
  "/icons/app/icon-192.png",
  "/icons/app/icon-512.png",

  "/icons/favicon/favicon.ico",
  "/icons/favicon/icon-32.png"
];

async function precacheIndexAssets(cache) {
  try {
    // Wichtig: HTML frisch holen (inkl. ggf. ?v-Links), nicht aus HTTP-Cache
    const res = await fetch("/index.html", { cache: "no-store" });
    if (!res.ok) return;

    const html = await res.text();

    // Sehr bewusst simpel: wir cachen nur same-origin CSS/JS aus href/src
    const urls = new Set();

    const attrRe = /\s(?:href|src)\s*=\s*["']([^"']+)["']/gi;
    let m;
    while ((m = attrRe.exec(html)) !== null) {
      const raw = m[1];
      if (!raw) continue;

      // nur same-origin / relative Pfade
      if (raw.startsWith("http://") || raw.startsWith("https://") || raw.startsWith("//")) continue;
      if (!raw.startsWith("/")) continue;

      // nur CSS/JS (inkl. Querystrings)
      const path = raw.split("?")[0];
      if (!(path.endsWith(".css") || path.endsWith(".js"))) continue;

      urls.add(raw);
    }

    // Cache robust: einzelne Fehlschläge nicht abbrechen lassen
    await Promise.allSettled(
      Array.from(urls).map((u) => cache.add(u))
    );
  } catch (_) {
    // offline/fehler: nichts tun, install darf nicht hard-failen
  }
}
/* === END BLOCK: STATIC ASSETS + INDEX ASSET PRECACHE (offline shell works) === */


self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
      // Version vor dem Caching auflösen (wichtig!)
      await resolveBuildVersion();

      const cache = await caches.open(STATIC_CACHE);
      try {
        await cache.addAll(STATIC_ASSETS);
      } catch (e) {
        console.warn("SW install: STATIC_ASSETS caching failed", e);
      }

      // Zusätzlich: exakte CSS/JS-URLs aus index.html (inkl. ?v=...) precachen
      await precacheIndexAssets(cache);

      self.skipWaiting();
    })()
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    (async () => {
      // Auch hier Version auflösen, falls install übersprungen wurde
      await resolveBuildVersion();

      const keys = await caches.keys();

      /* === BEGIN BLOCK: ACTIVATE GUARD (no cache purge on VERSION=dev) ===
      Zweck:
      - Wenn /meta/build.txt offline nicht auflösbar ist (VERSION=dev),
        dürfen produktive Version-Caches nicht gelöscht werden.
      Umfang:
      - Guard in activate vor dem Cache-Purge
      === */
      if (VERSION === "dev") {
        self.clients.claim();
        return;
      }
      /* === END BLOCK: ACTIVATE GUARD (no cache purge on VERSION=dev) === */

      await Promise.all(
        keys.map((key) => {
          if (key !== STATIC_CACHE && key !== RUNTIME_CACHE) {
            return caches.delete(key);
          }
        })
      );

      self.clients.claim();
    })()
  );
});

self.addEventListener("message", (event) => {
  if (event?.data?.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

/* === BEGIN BLOCK: CACHING HELPERS (cache-busting works) ===
Zweck: Cache-Busting darf NICHT durch ignoreSearch ausgehebelt werden.
Umfang: cache.match ohne ignoreSearch, damit ?v=... wirklich neue Assets erzwingt.
Fixes:
- staleWhileRevalidate: fetch(request) statt request.then(...)
- networkFirst: fetch(request, ...) statt (request, {...})
Ergänzung:
- staleWhileRevalidate: fetch(..., { cache: "reload" }) damit Browser-HTTP-Cache (z.B. max-age/immutable) Änderungen nicht „unsichtbar“ macht.
=== */
async function staleWhileRevalidate(request) {
  /* === BEGIN BLOCK: GS-01.5 OFFLINE ASSET FALLBACK (STATIC+RUNTIME) ===
  Zweck:
  - Offline muss App-Shell inkl. CSS/JS vollständig laden können.
  - STATIC_CACHE enthält precached Assets (inkl. ?v=...), daher hier als Fallback matchen.
  Umfang:
  - Cache-Lookup: RUNTIME zuerst, dann STATIC (ohne ignoreSearch).
  - Writes bleiben im RUNTIME_CACHE (wie bisher).
  === */

  const runtimeCache = await caches.open(RUNTIME_CACHE);
  const staticCache = await caches.open(STATIC_CACHE);

  // WICHTIG: KEIN ignoreSearch -> Querystring ist Teil des Cache-Keys
  const cached =
    (await runtimeCache.match(request)) ||
    (await staticCache.match(request));

  // WICHTIG: "reload" erzwingt ein Re-Fetch (bypasst aggressiven HTTP-Cache),
  // damit Deploy-Änderungen an CSS/JS auch bei gleichbleibender URL sichtbar werden.
  const networkPromise = fetch(request, { cache: "reload" })
    .then((response) => {
      if (response && response.ok) {
        runtimeCache.put(request, response.clone());
      }
      return response;
    })
    .catch(() => null);

  if (cached) {
    // Update nebenbei anstoßen
    networkPromise;
    return cached;
  }

  const network = await networkPromise;
  if (network) return network;

  return new Response("Offline", {
    status: 503,
    headers: { "Content-Type": "text/plain; charset=utf-8" }
  });

  /* === END BLOCK: GS-01.5 OFFLINE ASSET FALLBACK (STATIC+RUNTIME) === */
}

async function networkFirst(request) {
  const runtimeCache = await caches.open(RUNTIME_CACHE);
  const staticCache = await caches.open(STATIC_CACHE);

  try {
    const response = await fetch(request, { cache: "no-store" });
    if (response && response.ok) {
      await runtimeCache.put(request, response.clone());
    }
    return response;
  } catch (e) {
    // 1) Runtime-Fallback (exakter Match)
    const cachedRuntime = await runtimeCache.match(request);
    if (cachedRuntime) return cachedRuntime;

    // 1b) Runtime-Fallback (Query ignorieren) – wichtig für /data/*.json?v=...
    const cachedRuntimeNoSearch = await runtimeCache.match(request, { ignoreSearch: true });
    if (cachedRuntimeNoSearch) return cachedRuntimeNoSearch;

    // 2) Static-Fallback (exakter Match)
    const cachedStatic = await staticCache.match(request);
    if (cachedStatic) return cachedStatic;

    // 2b) Static-Fallback (Query ignorieren) – nutzt precache /data/*.json ohne Query
    const cachedStaticNoSearch = await staticCache.match(request, { ignoreSearch: true });
    if (cachedStaticNoSearch) return cachedStaticNoSearch;

    return new Response("Offline", {
      status: 503,
      headers: { "Content-Type": "text/plain; charset=utf-8" }
    });
  }
}
/* === END BLOCK: CACHING HELPERS (cache-busting works) === */




/* === BEGIN BLOCK: FETCH HANDLER (routing + offline shell) ===
Zweck: Einheitliche Fetch-Strategien:
- navigate: network first, fallback auf cached /index.html
- /data/*.json: network-first (immer aktuell)
- restliche Assets: stale-while-revalidate
Fixes:
- Event heißt "fetch" (nicht leer)
- fetch(req) statt await(req)
=== */
self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);

  // nur same-origin
  if (url.origin !== self.location.origin) return;

  // Navigations-Fallback: Wenn offline, nimm index.html aus STATIC cache
  if (req.mode === "navigate") {
    event.respondWith(
      (async () => {
        try {
          return await fetch(req);
        } catch (_) {
          const cache = await caches.open(STATIC_CACHE);
          const cachedShell = await cache.match("/index.html");
          return cachedShell || new Response("Offline", { status: 503 });
        }
      })()
    );
    return;
  }

  // Daten: Network-First
  if (url.pathname.startsWith("/data/") && url.pathname.endsWith(".json")) {
    event.respondWith(networkFirst(req));
    return;
  }

  /* === BEGIN BLOCK: MANIFEST FETCH SAFETY (prevent 503 spam) ===
Zweck: manifest.json darf niemals als 503 "Offline" enden,
       da Browser/PWA es regelmäßig und aggressiv anfragt.
Umfang: Cache-first Fallback speziell für /manifest.json,
        alles andere bleibt unverändert (SWR).
=== */
if (url.pathname === "/manifest.json") {
  event.respondWith(
    (async () => {
      const cache = await caches.open(STATIC_CACHE);
      const cached = await cache.match("/manifest.json");

      try {
        const response = await fetch(req);
        if (response && response.ok) {
          await cache.put("/manifest.json", response.clone());
        }
        return response;
      } catch (err) {
        if (cached) return cached;

        return new Response("Manifest unavailable", {
          status: 503,
          headers: { "Content-Type": "text/plain; charset=utf-8" }
        });
      }
    })()
  );
  return;
}
/* === END BLOCK: MANIFEST FETCH SAFETY (prevent 503 spam) === */

// alles andere: SWR
event.respondWith(staleWhileRevalidate(req));
});

/* === END BLOCK: FETCH HANDLER (routing + offline shell) === */














