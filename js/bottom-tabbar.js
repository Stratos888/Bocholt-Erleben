/* === BEGIN BLOCK: BOTTOM_TABBAR_RENDERER_V2 | Zweck: verhindert Reload auf dem bereits aktiven Tab und wärmt die jeweils andere Route zentral per Prefetch/Prerender vor, damit Mobile-Tabwechsel mit der aktuellen MPA-Struktur maximal direkt wirken | Umfang: komplette Bottom-Tab-Bar-Logik inkl. Root-Rendering, Path-Normalisierung, Active-Tab-Guard, Prefetch/Prerender und Icon-Hydration === */
(() => {
  "use strict";

  const ROOT_ID = "bottom-tabbar-root";
  const BODY_CLASS = "has-bottom-tabbar";
  const ROUTE_CLASS_PREFIX = "bottom-tabbar-route-";
  const SPECULATION_RULES_ID = "bottom-tabbar-speculation-rules";

  const warmedUrls = new Set();

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

  function supportsSpeculationRules() {
    try {
      return !!(
        window.HTMLScriptElement &&
        typeof window.HTMLScriptElement.supports === "function" &&
        window.HTMLScriptElement.supports("speculationrules")
      );
    } catch (_) {
      return false;
    }
  }

  function addDocumentPrefetch(href) {
    const normalizedHref = normalizePath(href);
    if (!normalizedHref || warmedUrls.has(`doc:${normalizedHref}`) || !document.head) return;

    const existing = document.head.querySelector(`link[rel="prefetch"][href="${normalizedHref}"]`);
    if (!existing) {
      const link = document.createElement("link");
      link.rel = "prefetch";
      link.href = normalizedHref;
      document.head.appendChild(link);
    }

    warmedUrls.add(`doc:${normalizedHref}`);
  }

  function warmDataUrl(url) {
    const href = String(url || "").trim();
    if (!href || warmedUrls.has(`data:${href}`)) return;

    warmedUrls.add(`data:${href}`);

    try {
      fetch(href, {
        credentials: "same-origin",
        cache: "force-cache"
      }).catch(() => {});
    } catch (_) {
      // no-op
    }
  }

  function applyPrerenderRules(urls) {
    if (!supportsSpeculationRules() || !document.head) return;

    const normalizedUrls = Array.from(
      new Set(
        (Array.isArray(urls) ? urls : [])
          .map((url) => normalizePath(url))
          .filter(Boolean)
      )
    );

    if (!normalizedUrls.length) return;

    const existing = document.getElementById(SPECULATION_RULES_ID);
    if (existing) existing.remove();

    const script = document.createElement("script");
    script.type = "speculationrules";
    script.id = SPECULATION_RULES_ID;
    script.textContent = JSON.stringify({
      prerender: [
        {
          source: "list",
          urls: normalizedUrls
        }
      ]
    });

    document.head.appendChild(script);
  }

  function warmItem(item) {
    if (!item) return;
    addDocumentPrefetch(item.href);
    (item.prefetch || []).forEach(warmDataUrl);
  }

  function warmInactiveRoutes(activeKey) {
    const inactiveItems = ITEMS.filter((item) => item.key !== activeKey);

    const run = () => {
      inactiveItems.forEach(warmItem);
      applyPrerenderRules(inactiveItems.map((item) => item.href));
    };

    if ("requestIdleCallback" in window) {
      window.requestIdleCallback(run, { timeout: 1200 });
      return;
    }

    window.setTimeout(run, 220);
  }

  function bindTabBehavior(root, activeItem) {
    if (!root || !activeItem) return;

    root.querySelectorAll(".bottom-tabbar__item[data-tab-key]").forEach((link) => {
      const key = String(link.getAttribute("data-tab-key") || "").trim();
      const item = ITEMS.find((entry) => entry.key === key);
      if (!item) return;

      if (item.key === activeItem.key) {
        link.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
        });
        return;
      }

      const warm = () => {
        warmItem(item);
        applyPrerenderRules([item.href]);
      };

      link.addEventListener("pointerdown", warm, { once: true });
      link.addEventListener("pointerenter", warm, { once: true });
      link.addEventListener("touchstart", warm, { once: true, passive: true });
      link.addEventListener("focus", warm, { once: true });
    });
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

    bindTabBehavior(root, activeItem);
    warmInactiveRoutes(activeItem.key);

    if (window.Icons && typeof window.Icons.hydrate === "function") {
      window.Icons.hydrate(root);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", render, { once: true });
  } else {
    render();
  }
})();
/* === END BLOCK: BOTTOM_TABBAR_RENDERER_V2 === */
