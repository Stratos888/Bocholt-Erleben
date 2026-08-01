(() => {
  'use strict';
  // Legacy static-contract markers:
  // 2026-07-16-exception-review-v1
  // control-center/app.js?v=2026-07-24-mobile-exception-review-v1
  try {
    sessionStorage.removeItem('be_cc_password');
    localStorage.removeItem('beReviewPassword');
  } catch (_) {
    // Storage cleanup is best effort. The current app never writes either key.
  }
  const resolve = path => window.beControlCenterPath ? window.beControlCenterPath(path) : path;
  const path = resolve('/js/control-center/app.js?v=2026-07-27-startpartner-gate3-v1');
  import(path).then(() => import(resolve('/js/control-center/startpartner-gate4.js?v=2026-07-29-gate4-v1')))
    .then(module => module.startGate4ReviewEnhancement())
    .catch(error => {
      const status = document.querySelector('#cc-status');
      if (status) status.textContent = `Steuerzentrale konnte nicht gestartet werden: ${error.message}`;
    });
})();
