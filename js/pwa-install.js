// BEGIN: FILE_HEADER_PWA_INSTALL
// Datei: js/pwa-install.js
// Zweck:
// - Behandlung der PWA-Installationslogik
// - Anzeigen / Ausblenden des „App installieren“-Buttons
// - Reaktion auf beforeinstallprompt / appinstalled
//
// Verantwortlich für:
// - PWA-Install-UX
// - Button-State (sichtbar / nicht sichtbar)
//
// Nicht verantwortlich für:
// - App-Layout oder Header-Struktur
// - Event-Daten oder Filter
// - Service-Worker-Logik
//
// Contract:
// - reagiert ausschließlich auf Browser-PWA-Events
// - beeinflusst keine anderen Module
// END: FILE_HEADER_PWA_INSTALL


// === BEGIN BLOCK: MULTI INSTALL TRIGGERS (header + desktop entry) ===
(() => {
  const installButtons = Array.from(
    document.querySelectorAll("[data-pwa-install-trigger]")
  );

  if (!installButtons.length) return;

  let deferredPrompt = null;

  const setButtonsVisible = (isVisible) => {
    installButtons.forEach((button) => {
      button.style.display = isVisible ? "inline-flex" : "none";
    });
  };

  const isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

  setButtonsVisible(false);

  if (isStandalone) {
    return;
  }

  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredPrompt = event;
    setButtonsVisible(true);
  });

  installButtons.forEach((button) => {
    button.addEventListener("click", async () => {
      if (!deferredPrompt) return;

      setButtonsVisible(false);

      deferredPrompt.prompt();
      await deferredPrompt.userChoice;

      deferredPrompt = null;
    });
  });

  window.addEventListener("appinstalled", () => {
    setButtonsVisible(false);
    deferredPrompt = null;
  });
})();
// === END BLOCK: MULTI INSTALL TRIGGERS (header + desktop entry) ===



