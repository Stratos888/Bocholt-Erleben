/* === BEGIN BLOCK: LOCATIONS MODAL + OVERFLOW MENU LOGIC (CONSOLIDATED) ===
Zweck: Steuert Overflow-Menü im Header und öffnet/schließt das Locations-Modal robust.
Umfang: Komplette Datei js/locations-modal.js (ersetzt alles).
=== */
(function () {
  function byId(id) {
    return document.getElementById(id);
  }

  // Header overflow menu (optional – funktioniert auch wenn nicht vorhanden)
  const menuBtn = byId("header-menu-button");
  const menu = byId("header-menu");

  // IMPORTANT: In index.html gab es zeitweise doppelte IDs ("open-locations-modal").
  // getElementById() würde nur das erste Element finden -> deshalb querySelectorAll.
  const openLocationsBtns = Array.from(document.querySelectorAll("#open-locations-modal"));

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
    // Prevent duplicates
    if (document.querySelector("[data-modal-overlay]")) return;

    const tpl = document.getElementById("locations-modal-template");
    if (!tpl) return;

    const node = tpl.content.cloneNode(true);
    const overlay = node.querySelector("[data-modal-overlay]");
    const closeBtn = node.querySelector("[data-modal-close]");

    // Basic scroll lock (restore on close)
    const prevOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";

    function cleanup() {
      document.removeEventListener("keydown", onKeyDown);
      document.body.style.overflow = prevOverflow || "";
    }

    function closeModal() {
      cleanup();
      const existingOverlay = document.querySelector("[data-modal-overlay]");
      if (existingOverlay && existingOverlay.parentNode) {
        existingOverlay.parentNode.removeChild(existingOverlay);
      }
    }

    function onKeyDown(e) {
      if (e.key === "Escape") closeModal();
    }

    if (overlay) {
      overlay.addEventListener("click", (e) => {
        // close only if user clicks outside the card
        if (e.target === overlay) closeModal();
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener("click", closeModal);
    }

    document.addEventListener("keydown", onKeyDown);
    document.body.appendChild(node);

    // Focus close button for accessibility
    const liveCloseBtn = document.querySelector("[data-modal-close]");
    if (liveCloseBtn) liveCloseBtn.focus();
  }

  // Menu toggle
  if (menuBtn) {
    menuBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      toggleMenu();
    });
  }

  // Open modal (supports multiple triggers)
  openLocationsBtns.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      e.preventDefault();
      closeMenu();
      openLocationsModal();
    });
  });

  // Click outside closes menu (if menu exists)
  document.addEventListener("click", () => {
    closeMenu();
  });
})();
/* === END BLOCK: LOCATIONS MODAL + OVERFLOW MENU LOGIC (CONSOLIDATED) === */
