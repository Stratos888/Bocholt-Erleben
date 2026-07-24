(() => {
  'use strict';
  // Legacy static-contract marker: 2026-07-16-exception-review-v1
  try {
    sessionStorage.removeItem('be_cc_password');
    localStorage.removeItem('beReviewPassword');
  } catch (_) {
    // Storage cleanup is best effort. The current app never writes either key.
  }
  const path = window.beControlCenterPath
    ? window.beControlCenterPath('/js/control-center/app.js?v=2026-07-24-mobile-exception-review-v1')
    : '/js/control-center/app.js?v=2026-07-24-mobile-exception-review-v1';
  import(path).catch(error => {
    const status = document.querySelector('#cc-status');
    if (status) status.textContent = `Steuerzentrale konnte nicht gestartet werden: ${error.message}`;
  });
})();
