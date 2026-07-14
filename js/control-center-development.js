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

  function processSummary(card) {
    const values = {};
    card?.querySelectorAll('dl > div').forEach(row => {
      const key = row.querySelector('dt')?.textContent.trim() || '';
      const value = row.querySelector('dd')?.textContent.trim() || '';
      if (key) values[key] = value;
    });
    const status = card?.querySelector('h2')?.textContent.trim() || 'Unbekannt';
    const message = card?.querySelector('p')?.textContent.trim() || '';
    const selected = values['Ausgewählte Chancen'];
    const prevented = values['Automatisch verhindert'];
    const details = [];
    if (selected && selected !== '–') details.push(`${selected} Chancen übernommen`);
    if (prevented && prevented !== '–') details.push(`${prevented} unpassende Kandidaten automatisch ausgeschlossen`);
    return { status, message, details: details.join(' · ') };
  }

  function injectDashboardStatus(doc, summary) {
    if (!doc?.body || doc.querySelector('[data-growth-process-summary]')) return;
    const topbar = doc.querySelector('.topbar');
    const section = doc.createElement('section');
    section.dataset.growthProcessSummary = '1';
    section.className = 'embeddedGrowthStatus';
    section.innerHTML = `<strong>Growth-/SEO-Prozess: ${summary.status}</strong>${summary.details ? `<span>${summary.details}</span>` : ''}${summary.message ? `<small>${summary.message}</small>` : ''}`;
    if (topbar) topbar.after(section);
    else doc.body.prepend(section);
  }

  function resizeFrame(frame) {
    try {
      const doc = frame.contentDocument;
      if (!doc?.documentElement) return;
      const height = Math.max(doc.documentElement.scrollHeight, doc.body?.scrollHeight || 0);
      if (height > 0) frame.style.height = `${height + 4}px`;
    } catch (_) {}
  }

  function enhanceSeoFrame(frame, summary) {
    if (!frame || frame.dataset.integrated === '1') return;
    frame.dataset.integrated = '1';
    frame.addEventListener('load', () => {
      try {
        const doc = frame.contentDocument;
        if (!doc?.head) return;
        const style = doc.createElement('style');
        style.textContent = `
          .logout{display:none!important}
          .wrap{width:min(1180px,calc(100% - 16px))!important;padding:10px 0 24px!important}
          .topbar{margin-bottom:10px!important}
          .embeddedGrowthStatus{display:grid;gap:3px;margin:0 0 10px;padding:10px 12px;border:1px solid var(--line);border-radius:14px;background:var(--surface-2);font-size:13px}
          .embeddedGrowthStatus strong{font-size:14px}
          .embeddedGrowthStatus span,.embeddedGrowthStatus small{color:var(--muted)}
          @media(max-width:860px){.embeddedGrowthStatus{padding:9px 10px}.embeddedGrowthStatus small{display:none}}
        `;
        doc.head.appendChild(style);
        injectDashboardStatus(doc, summary);
        resizeFrame(frame);
        const observer = new ResizeObserver(() => resizeFrame(frame));
        observer.observe(doc.documentElement);
        if (doc.body) observer.observe(doc.body);
      } catch (_) {
        // Same-Origin-Einbettung bleibt auch ohne kosmetische Anpassung funktionsfähig.
      }
    }, { once: true });
  }

  function enhanceSeoIntegration() {
    if (title?.textContent !== 'Entwicklung' || !view) return;
    const frame = view.querySelector('.cc-seo-frame');
    const processCard = view.querySelector('.cc-seo-operations');
    if (!frame || frame.dataset.integrated === '1') return;
    const summary = processSummary(processCard);
    processCard?.remove();
    view.classList.add('cc-view--seo');
    enhanceSeoFrame(frame, summary);
  }

  function enhance() {
    enhanceOverview();
    enhanceSeoIntegration();
    if (title?.textContent !== 'Entwicklung' || !view?.querySelector('.cc-seo-frame')) view?.classList.remove('cc-view--seo');
  }

  const observer = new MutationObserver(enhance);
  if (view) observer.observe(view, { childList: true, subtree: true });
  if (title) observer.observe(title, { childList: true, characterData: true, subtree: true });
  enhance();
})();
