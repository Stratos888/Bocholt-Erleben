(() => {
  'use strict';
  // Legacy static-contract marker: 2026-07-16-exception-review-v1
  const path = window.beControlCenterPath
    ? window.beControlCenterPath('/js/control-center/app.js?v=2026-07-17-review-presentation-v1')
    : '/js/control-center/app.js?v=2026-07-17-review-presentation-v1';
  import(path).catch(error => {
    const status = document.querySelector('#cc-status');
    if (status) status.textContent = `Steuerzentrale konnte nicht gestartet werden: ${error.message}`;
  });
})();
