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


(() => {
  const installButton = document.querySelector(".pwa-install-button");
  if (!installButton) return;

  // Button ist standardmäßig verborgen
  installButton.style.display = "none";

  let deferredPrompt = null;

  // Standalone-Erkennung (Android + iOS)
  const isStandalone =
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

  // Wenn App bereits installiert / standalone → Button dauerhaft ausblenden
  if (isStandalone) {
    installButton.style.display = "none";
    return;
  }

  // beforeinstallprompt abfangen
  window.addEventListener("beforeinstallprompt", (e) => {
    e.preventDefault();
    deferredPrompt = e;

    // Button jetzt sichtbar machen
    installButton.style.display = "inline-flex";
  });

  // Klick auf Install-Button
  installButton.addEventListener("click", async () => {
    if (!deferredPrompt) return;

    installButton.style.display = "none";

    deferredPrompt.prompt();
    await deferredPrompt.userChoice;

    deferredPrompt = null;
  });

  // Falls App nachträglich installiert wurde
  window.addEventListener("appinstalled", () => {
    installButton.style.display = "none";
    deferredPrompt = null;
  });
})();

