(() => {
  'use strict';

  async function runTaskTransition(button, action) {
    const card = button.closest('[data-case-id]');
    const caseId = card?.dataset.caseId || '';
    if (!caseId) return;

    const label = action === 'wait'
      ? 'Worauf wird gewartet?'
      : 'Was blockiert die Aufgabe?';
    const reason = window.prompt(label, '');
    if (reason === null || !reason.trim()) return;

    const password = sessionStorage.getItem('be_cc_password') || '';
    button.disabled = true;
    try {
      const response = await fetch('/api/control-center/action.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-BE-Review-Password': password,
        },
        cache: 'no-store',
        body: JSON.stringify({
          case_id: caseId,
          action,
          payload: { reason: reason.trim() },
        }),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Status konnte nicht geändert werden.');
      window.location.reload();
    } catch (error) {
      button.disabled = false;
      window.alert(error instanceof Error ? error.message : String(error));
    }
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('[data-labor-action="wait"], [data-labor-action="block"]');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const action = button.dataset.laborAction;
    runTaskTransition(button, action);
  }, true);
})();
