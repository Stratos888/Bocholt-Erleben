/* === BEGIN BLOCK: SITE_FOOTER_RENDERER_V2 | Zweck: rendert den globalen Footer zentral fuer statische Hauptseiten und macht den bestehenden Feedback-Kanal explizit auch als Tipp-Kanal fuer fehlende Events/Aktivitaeten nutzbar; Umfang: Footer-Markup, Feedback-/Tipp-Trigger, Legal-Links; keine Layout- oder Feedback-Logik === */
(function () {
  "use strict";

  var FOOTER_HTML = [
    "<p>Bocholt erleben · 2026</p>",
    "<p>",
    '  <button type="button" class="footer-link" data-feedback-open="missing">Fehlt ein Event oder eine Aktivität?</button>',
    '  <span aria-hidden="true"> · </span>',
    '  <button type="button" class="footer-link" data-feedback-open="global">Feedback</button>',
    '  <span aria-hidden="true"> · </span>',
    '  <a href="/bildnachweise/">Bildnachweise</a>',
    '  <span aria-hidden="true"> · </span>',
    '  <a href="/impressum/">Impressum</a>',
    '  <span aria-hidden="true"> · </span>',
    '  <a href="/datenschutz/">Datenschutz</a>',
    "</p>"
  ].join("\n");

  function renderFooter(root) {
    if (!root || root.dataset.siteFooterReady === "true") return;

    if (!root.getAttribute("aria-label")) {
      root.setAttribute("aria-label", "Fußbereich");
    }

    root.innerHTML = FOOTER_HTML;
    root.dataset.siteFooterReady = "true";
  }

  function renderAllFooters() {
    document.querySelectorAll("footer[data-site-footer]").forEach(renderFooter);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", renderAllFooters);
  } else {
    renderAllFooters();
  }

  window.SiteFooter = Object.freeze({
    render: renderAllFooters
  });
}());
/* === END BLOCK: SITE_FOOTER_RENDERER_V2 === */
