/* === BEGIN BLOCK: BOTTOM_TABBAR_RENDERER_V2 | Zweck: rendert die mobile Primary-Navigation zentral, setzt den Active-State automatisch und wärmt beim Wechsel die Zielroute inkl. Kern-Daten vor, damit Tabs auf Mobile spürbar schneller reagieren | Umfang: komplette Bottom-Tab-Bar-Logik inkl. Root-Rendering, Path-Normalisierung, Warmup/Prefetch und Icon-Hydration === */
(() => {
  "use strict";

  const ROOT_ID = "bottom-tabbar-root";
  const BODY_CLASS = "has-bottom-tabbar";
  const ROUTE_CLASS_PREFIX = "bottom-tabbar-route-";
  const prefetched = new Set();

  const ITEMS = [
    {
      key: "events",
      href: "/",
      label: "Events",
      icon: "calendar-days",
      prefetch: ["/data/events.json"],
      match: (path) => path === "/"
    },
    {
      key: "activities",
      href: "/angebote/",
      label: "Aktivitäten",
      icon: "compass",
      prefetch: ["/data/offers.json"],
      match: (path) => path === "/angebote/" || path.startsWith("/angebote/")
    }
  ];

  function normalizePath(value) {
    let path = String(value || "/").trim();

    if (!path) return "/";

    path = path.replace(/[#?].*$/, "");
    path = path.replace(/\/index\.html$/, "/");
    path = path.replace(/\/+/g, "/");

    if (!path.startsWith("/")) path = `/${path}`;
    if (path.length > 1 && !path.endsWith("/")) path += "/";

    return path || "/";
  }

  function getActiveItem(pathname) {
    return ITEMS.find((item) => item.match(pathname)) || null;
  }

  function clearBodyState(body) {
    if (!body) return;

    body.classList.remove(BODY_CLASS);
    [...body.classList]
      .filter((cls) => cls.startsWith(ROUTE_CLASS_PREFIX))
      .forEach((cls) => body.classList.remove(cls));
  }

  function createItemMarkup(item, activeKey) {
    const isActive = item.key === activeKey;

    return `
      <a class="bottom-tabbar__item${isActive ? " is-active" : ""}" href="${item.href}" data-tab-key="${item.key}"${isActive ? ' aria-current="page"' : ""}>
        <span class="bottom-tabbar__icon" data-ui-icon="${item.icon}" aria-hidden="true"></span>
        <span class="bottom-tabbar__label">${item.label}</span>
      </a>
    `;
  }

  function prefetchUrl(url) {
    const href = String(url || "").trim();
    if (!href || prefetched.has(href)) return;

    prefetched.add(href);

    try {
      const link = document.createElement("link");
      link.rel = "prefetch";
      link.href = href;
      document.head.appendChild(link);
    } catch (_) {
      // no-op
    }

    try {
      fetch(href, {
        credentials: "same-origin",
        cache: "force-cache"
      }).catch(() => {});
    } catch (_) {
      // no-op
    }
  }

  function warmItem(item) {
    if (!item) return;
    prefetchUrl(item.href);
    (item.prefetch || []).forEach(prefetchUrl);
  }

  function bindWarmup(root, activeItem) {
    const anchors = root.querySelectorAll(".bottom-tabbar__item[data-tab-key]");

    anchors.forEach((anchor) => {
      const item = ITEMS.find((entry) => entry.key === anchor.getAttribute("data-tab-key"));
      if (!item || item.key === activeItem?.key) return;

      const warm = () => warmItem(item);

      anchor.addEventListener("pointerdown", warm, { once: true });
      anchor.addEventListener("touchstart", warm, { once: true, passive: true });
      anchor.addEventListener("pointerenter", warm, { once: true });
      anchor.addEventListener("focus", warm, { once: true });
    });

    const inactiveItems = ITEMS.filter((item) => item.key !== activeItem?.key);
    const idleWarmup = () => inactiveItems.forEach(warmItem);

    if (typeof window.requestIdleCallback === "function") {
      window.requestIdleCallback(idleWarmup, { timeout: 1200 });
    } else {
      window.setTimeout(idleWarmup, 450);
    }
  }

  function render() {
    const root = document.getElementById(ROOT_ID);
    const body = document.body;

    if (!root || !body) return;

    const pathname = normalizePath(window.location.pathname);
    const activeItem = getActiveItem(pathname);

    clearBodyState(body);

    if (!activeItem) {
      root.innerHTML = "";
      root.hidden = true;
      return;
    }

    root.hidden = false;
    body.classList.add(BODY_CLASS, `${ROUTE_CLASS_PREFIX}${activeItem.key}`);
    root.setAttribute("data-bottom-tabbar-route", activeItem.key);
    root.innerHTML = `
      <nav class="bottom-tabbar" aria-label="Hauptnavigation">
        <div class="bottom-tabbar__inner" style="--bottom-tab-count: ${ITEMS.length};">
          ${ITEMS.map((item) => createItemMarkup(item, activeItem.key)).join("")}
        </div>
      </nav>
    `;

    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }

    bindWarmup(root, activeItem);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", render, { once: true });
  } else {
    render();
  }
})();
/* === END BLOCK: BOTTOM_TABBAR_RENDERER_V2 === */
