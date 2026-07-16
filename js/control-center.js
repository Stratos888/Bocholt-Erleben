(() => {
  'use strict';
  const path = window.beControlCenterPath ? window.beControlCenterPath('/js/control-center/app.js?v=2026-07-16-backlog-editor-v2') : '/js/control-center/app.js?v=2026-07-16-backlog-editor-v2';
  import(path).catch(error => {
    const status = document.querySelector('#cc-status');
    if (status) status.textContent = `Steuerzentrale konnte nicht gestartet werden: ${error.message}`;
  });
})();
