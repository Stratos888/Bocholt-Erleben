/* === BEGIN BLOCK: LOCATIONS MODAL + OVERFLOW MENU LOGIC ===
Zweck: Steuert Overflow-Menü im Header und öffnet/schließt das Locations-Modal.
Umfang: Komplette Datei js/locations-modal.js.
=== */
(function () {
  function $(id) {
    return document.getElementById(id);
  }

  const menuBtn = $("header-menu-button");
  const menu = $("header-menu");
  const openLocationsBtn = $("open-locations-modal");

  function closeMenu() {
    if (!menuBtn || !menu) return;
    menu.classList.remove("is-open");
    menuBtn.setAttribute("aria-expanded", "false");
    menu.setAttribute("aria-hidden", "true");
  }

  function openMenu() {
    if (!menuBtn || !menu) return;
    menu.classList.add("is-open");
    menuBtn.setAttribute("aria-expanded", "true");
    menu.setAttribute("aria-hidden", "false");
  }

  function toggleMenu() {
    if (!menu) return;
    menu.classList.contains("is-open") ? closeMenu() : openMenu();
  }

  function openLocationsModal() {
    const tpl = document.getElementById("locations-modal-template");
    if (!tpl) return;

    const node = tpl.content.cloneNode(true);
    const overlay = node.querySelector("[data-modal-overlay]");
    const closeBtn = node.querySelector("[data-modal-close]");

    function closeModal() {
      document.removeEventListener("keydown", onKeyDown);
      if (overlay && overlay.parentNode) overlay.parentNode.removeChild(overlay);
    }

    function onKeyDown(e) {
      if (e.key === "Escape") closeModal();
    }

    if (overlay) {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) closeModal();
      });
    }

    if (closeBtn) closeBtn.addEventListener("click", closeModal);

    document.addEventListener("keydown", onKeyDown);
    document.body.appendChild(node);
  }

  if (menuBtn) {
    menuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleMenu();
    });
  }

  if (openLocationsBtn) {
    openLocationsBtn.addEventListener("click", () => {
      closeMenu();
      openLocationsModal();
    });
  }

  document.addEventListener("click", () => {
    closeMenu();
  });
})();
/* === END BLOCK: LOCATIONS MODAL + OVERFLOW MENU LOGIC === */
