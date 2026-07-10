(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const view = document.querySelector('#cc-view');
  const title = document.querySelector('#cc-title');
  let loading = null;

  async function loadDevelopment() {
    if (loading) return loading;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 15000);
    loading = fetch('/api/control-center/development.php', {
      headers: { 'X-BE-Review-Password': password() },
      cache: 'no-store',
      signal: controller.signal,
    }).then(async response => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Entwicklungsdaten konnten nicht geladen werden.');
      return payload.data || {};
    }).finally(() => {
      window.clearTimeout(timer);
      loading = null;
    });
    return loading;
  }

  async function enhanceOverview() {
    if (title?.textContent !== 'Übersicht' || !view) return;
    const label = [...view.querySelectorAll('.cc-summary-label')].find(node => node.textContent.trim() === 'Entwicklung');
    const card = label?.closest('.cc-summary-card');
    if (!card || card.dataset.liveDevelopment === '1') return;
    card.dataset.liveDevelopment = '1';
    try {
      const data = await loadDevelopment();
      if (title?.textContent !== 'Übersicht') return;
      const heading = card.querySelector('h2');
      const paragraph = card.querySelector('p');
      const processProblem = data.automation?.process_health?.status === 'attention';
      if (heading) heading.textContent = data.summary?.label || 'Entwicklungsstand';
      if (paragraph) paragraph.textContent = `${data.content_quality?.coverage_percent ?? 0} % Eventbasis · ${processProblem ? 'Prozessprüfung nötig' : 'Prozesse ohne bekannten Fehler'}`;
    } catch (_) {
      card.dataset.liveDevelopment = '0';
    }
  }

  const observer = new MutationObserver(enhanceOverview);
  if (view) observer.observe(view, { childList: true, subtree: true });
  if (title) observer.observe(title, { childList: true, characterData: true, subtree: true });
})();
