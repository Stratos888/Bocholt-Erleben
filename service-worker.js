/* =========================================================
   SERVICE WORKER – Bocholt erleben (2026)
   Strategie:
   - /data/*.json => Network-First (Events/Locations stets aktuell)
   - same-origin Assets (css/js/html/icons) => Stale-While-Revalidate
   - Cache Cleanup bei Aktivierung
   ========================================================= */

const VERSION = "2026-01-17-04"; // <-- bei Deployments hochzählen
const STATIC_CACHE = `be-static-${VERSION}`;
const RUNTIME_CACHE = `be-runtime-${VERSION}`;

// Minimal: Manifest + Icons (App-Shell kommt über SWR)
const STATIC_ASSETS = [
  "/manifest.json",
  "/icons/app/icon-192.png",
  "/icons/app/icon-512.png",
  "/icons/app/icon-180.png",
  "/icons/favicon/icon-32.png",
  "/icons/favicon/favicon.ico"
];


self.addEventListener("install", (event) => {
  event.waitUntil(
    (async () => {
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
      const keys = await caches.keys();
      await Promise.all(
        keys.map((key) => {
          if (key !== STATIC_CACHE && key !== RUNTIME_CACHE) {
            return caches.delete(key);
          }
        })
      );
      await self.clients.claim();
    })()
  );
});

self.addEventListener("message", (event) => {
  if (event?.data?.type === "SKIP_WAITING") {
    self.skipWaiting();
  }
});

async function staleWhileRevalidate(request) {
  const cache = await caches.open(RUNTIME_CACHE);

  // ignoreSearch, weil du Dateien mit ?v=... lädst.
  const cached = await cache.match(request, { ignoreSearch: true });

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
    const cached = await cache.match(request, { ignoreSearch: true });
    if (cached) return cached;

    return new Response("Offline", {
      status: 503,
      headers: { "Content-Type": "text/plain; charset=utf-8" }
    });
  }
}

self.addEventListener("fetch", (event) => {
  const req = event.request;
  if (req.method !== "GET") return;

  const url = new URL(req.url);

  // nur same-origin
  if (url.origin !== self.location.origin) return;

  // Daten: Network-First
  if (url.pathname.startsWith("/data/") && url.pathname.endsWith(".json")) {
    event.respondWith(networkFirst(req));
    return;
  }

  // alles andere: SWR
  event.respondWith(staleWhileRevalidate(req));
});

