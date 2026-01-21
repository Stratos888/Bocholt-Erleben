/* =========================================================
   SERVICE WORKER – Bocholt erleben (2026)
   Ziel: Deploys ohne Cache-Probleme (voll automatisch)
   Strategie:
   - /data/*.json => Network-First (Events/Locations stets aktuell)
   - same-origin Assets (css/js/icons/html) => Stale-While-Revalidate
   - Cache Version automatisch über /build.json (vom Deploy erzeugt)
   - Cache Cleanup bei Aktivierung
   ========================================================= */

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
    }
  } catch (_) {
    // Fallback bleibt "dev"
  }
}
/* === END BLOCK: BUILD VERSION RESOLUTION (no manual bump) === */



/* === BEGIN BLOCK: STATIC ASSETS (minimal shell) ===
Zweck: Minimal offline shell. Rest kommt über SWR aus Runtime-Cache.
Umfang: Liste bewusst klein gehalten.
=== */
const STATIC_ASSETS = [
  "/",
  "/index.html",
  "/manifest.json",

  "/icons/app/icon-180.png",
  "/icons/app/icon-192.png",
  "/icons/app/icon-512.png",

  "/icons/favicon/favicon.ico",
  "/icons/favicon/icon-32.png"
];
/* === END BLOCK: STATIC ASSETS (minimal shell) === */


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
=== */
async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);

  // WICHTIG: KEIN ignoreSearch -> Querystring ist Teil des Cache-Keys
  const cached = await cache.match(request);

  const networkPromise = fetch(request)
    .then((response) => {
      if (response && response.ok) {
        cache.put(request, response.clone());
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
}

async function networkFirst(request) {
  const cache = await caches.open(RUNTIME_CACHE);

  try {
    const response = await fetch(request, { cache: "no-store" });
    if (response && response.ok) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch (e) {
    const cached = await cache.match(request);
    if (cached) return cached;

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

  // alles andere: SWR
  event.respondWith(staleWhileRevalidate(req));
});
/* === END BLOCK: FETCH HANDLER (routing + offline shell) === */




