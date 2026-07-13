(() => {
  'use strict';

  function bridge() {
    document.querySelectorAll('[data-backlog-action="edit_source"]:not([data-labor-action])').forEach(button => {
      button.dataset.laborAction = 'edit_source';
    });
  }

  new MutationObserver(bridge).observe(document.body, { childList:true, subtree:true });
  bridge();
})();

// Deployment marker: control-center-final-v4
