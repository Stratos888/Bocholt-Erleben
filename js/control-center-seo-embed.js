(() => {
  'use strict';

  function enhanceFrame(frame) {
    if (!frame || frame.dataset.enhanced === '1') return;
    frame.dataset.enhanced = '1';
    frame.addEventListener('load', () => {
      try {
        const doc = frame.contentDocument;
        if (!doc?.head) return;
        const style = doc.createElement('style');
        style.textContent = '.logout{display:none!important}.wrap{width:min(1180px,calc(100% - 16px))!important;padding-top:10px!important}.topbar{margin-bottom:10px!important}';
        doc.head.appendChild(style);
      } catch (_) {
        // Same-Origin-Einbettung bleibt auch ohne kosmetische Anpassung funktionsfähig.
      }
    });
  }

  function scan() {
    document.querySelectorAll('.cc-seo-frame').forEach(enhanceFrame);
  }

  new MutationObserver(scan).observe(document.body, { childList:true, subtree:true });
  scan();
})();
