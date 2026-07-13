(() => {
  'use strict';

  const pathname = window.location.pathname || '/';
  const match = pathname.match(/^\/(staging)(?:\/|$)/);
  const base = match ? `/${match[1]}` : '';

  function appPath(value) {
    const path = String(value || '');
    if (!base || !path.startsWith('/') || path === base || path.startsWith(`${base}/`)) return path;
    if (path.startsWith('//')) return path;
    return `${base}${path}`;
  }

  window.BE_CONTROL_CENTER_BASE = base;
  window.beControlCenterPath = appPath;

  const nativeFetch = window.fetch.bind(window);
  window.fetch = (input, init) => {
    if (typeof input === 'string') return nativeFetch(appPath(input), init);
    if (input instanceof Request) {
      const url = new URL(input.url, window.location.href);
      if (url.origin === window.location.origin) {
        const mapped = appPath(`${url.pathname}${url.search}${url.hash}`);
        if (mapped !== `${url.pathname}${url.search}${url.hash}`) input = new Request(mapped, input);
      }
    }
    return nativeFetch(input, init);
  };

  function rewriteElement(element) {
    if (!(element instanceof Element) || !base) return;
    for (const attribute of ['href', 'src', 'action']) {
      if (!element.hasAttribute(attribute)) continue;
      const current = element.getAttribute(attribute) || '';
      const mapped = appPath(current);
      if (mapped !== current) element.setAttribute(attribute, mapped);
    }
    element.querySelectorAll?.('[href],[src],[action]').forEach(rewriteElement);
  }

  new MutationObserver(records => {
    records.forEach(record => record.addedNodes.forEach(node => rewriteElement(node)));
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
