(() => {
  'use strict';

  const appPath = value => window.beControlCenterPath ? window.beControlCenterPath(value) : value;

  function mapDocumentUrl(value) {
    const raw = String(value || '');
    if (!raw.startsWith('/') || raw.startsWith('//')) return raw;
    return appPath(raw);
  }

  function enhanceFrame(frame) {
    if (!frame || frame.dataset.enhanced === '1') return;
    frame.dataset.enhanced = '1';
    frame.addEventListener('load', () => {
      try {
        const win = frame.contentWindow;
        const doc = frame.contentDocument;
        if (!win || !doc?.head || !doc.body) return;

        const style = doc.createElement('style');
        style.textContent = '.logout{display:none!important}.wrap{width:min(1180px,calc(100% - 16px))!important;padding-top:10px!important}.topbar{margin-bottom:10px!important}';
        doc.head.appendChild(style);

        doc.querySelectorAll('[href],[src],[action]').forEach(element => {
          for (const attribute of ['href','src','action']) {
            if (!element.hasAttribute(attribute)) continue;
            const current = element.getAttribute(attribute) || '';
            const mapped = mapDocumentUrl(current);
            if (mapped !== current) element.setAttribute(attribute, mapped);
          }
        });

        if (!win.__beSeoFetchIsolated) {
          const nativeFetch = win.fetch.bind(win);
          win.fetch = (input, init) => {
            if (typeof input === 'string') return nativeFetch(mapDocumentUrl(input), init);
            if (input instanceof win.Request) {
              const url = new URL(input.url, win.location.href);
              if (url.origin === win.location.origin) {
                const mapped = mapDocumentUrl(`${url.pathname}${url.search}${url.hash}`);
                input = new win.Request(mapped, input);
              }
            }
            return nativeFetch(input, init);
          };
          win.__beSeoFetchIsolated = true;
        }

        const resize = () => {
          const height = Math.max(doc.documentElement.scrollHeight, doc.body.scrollHeight, 640);
          frame.style.height = `${height}px`;
        };
        resize();
        if ('ResizeObserver' in win) new win.ResizeObserver(resize).observe(doc.body);
        new win.MutationObserver(resize).observe(doc.body, { childList:true, subtree:true, attributes:true });
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

// Deployment marker: control-center-stability-v1
