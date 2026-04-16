/* === BEGIN BLOCK: BOTTOM_TABBAR_RENDERER_V1 | Zweck: rendert die mobile Primary-Navigation aus genau einer zentralen Route-/Tab-Definition, setzt den Active-State automatisch und hält die Body-Klassen für Reserve-Space/Route konsistent | Umfang: komplette Bottom-Tab-Bar-Logik inkl. Root-Rendering, Path-Normalisierung und Icon-Hydration === */
(() => {
  "use strict";

  const ROOT_ID = "bottom-tabbar-root";
  const BODY_CLASS = "has-bottom-tabbar";
  const ROUTE_CLASS_PREFIX = "bottom-tabbar-route-";

  const ITEMS = [
    {
      key: "events",
      href: "/",
      label: "Events",
      icon: "calendar-days",
      match: (path) => path === "/"
    },
    {
      key: "activities",
      href: "/angebote/",
      label: "Aktivitäten",
      icon: "compass",
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
      <a class="bottom-tabbar__item${isActive ? " is-active" : ""}" href="${item.href}"${isActive ? ' aria-current="page"' : ""}>
        <span class="bottom-tabbar__icon" data-ui-icon="${item.icon}" aria-hidden="true"></span>
        <span class="bottom-tabbar__label">${item.label}</span>
      </a>
    `;
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
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", render, { once: true });
  } else {
    render();
  }
})();
/* === END BLOCK: BOTTOM_TABBAR_RENDERER_V1 === */
