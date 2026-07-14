(() => {
  'use strict';

  const view = document.querySelector('#cc-view');
  const status = document.querySelector('#cc-status');
  const title = document.querySelector('#cc-title');
  const password = () => sessionStorage.getItem('be_cc_password') || '';
  let cached = null;
  let pending = null;

  async function development() {
    if (cached) return cached;
    if (!pending) {
      pending = fetch(window.beControlCenterPath ? window.beControlCenterPath('/api/control-center/development.php') : '/api/control-center/development.php', {
        headers: { 'X-BE-Review-Password': password() },
        cache: 'no-store',
      }).then(async response => {
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || 'Projektstatus konnte nicht geladen werden.');
        cached = payload.data || {};
        return cached;
      }).finally(() => { pending = null; });
    }
    return pending;
  }

  async function clarifyOverview() {
    if (title?.textContent !== 'Übersicht') return;
    const label = [...view.querySelectorAll('.cc-summary-label')].find(node => node.textContent.trim() === 'Entwicklung');
    const card = label?.closest('.cc-summary-card');
    if (!card || card.dataset.stabilityClarified === '1') return;
    card.dataset.stabilityClarified = '1';
    try {
      const data = await development();
      if (title?.textContent !== 'Übersicht') return;
      const heading = card.querySelector('h2');
      const paragraph = card.querySelector('p');
      if (heading) heading.textContent = data.operator_status || data.summary?.label || 'Projektstatus';
      const quality = data.content_quality || {};
      const process = data.automation?.process_health || {};
      const processText = process.status === 'attention' ? 'Prozessprüfung erforderlich' : 'Prozesse ohne bekannten Fehler';
      if (paragraph) paragraph.textContent = `${quality.scope_label || 'Aktive Eventbasis'} ${quality.coverage_percent ?? 0} % vollständig · ${processText}`;
    } catch (_) {
      card.dataset.stabilityClarified = '0';
    }
  }

  function showSavedFeedback() {
    if (!status || status.textContent.trim() !== 'Gespeichert.') return;
    status.classList.add('cc-status--toast');
    window.setTimeout(() => status.classList.remove('cc-status--toast'), 2200);
  }

  const observer = new MutationObserver(() => {
    clarifyOverview();
    showSavedFeedback();
  });
  observer.observe(document.body, { childList:true, subtree:true, characterData:true });
  document.querySelector('#cc-refresh')?.addEventListener('click', () => { cached = null; });
  clarifyOverview();
})();
