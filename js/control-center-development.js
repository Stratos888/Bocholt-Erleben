(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const view = document.querySelector('#cc-view');
  const title = document.querySelector('#cc-title');
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
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
      if (heading) heading.textContent = data.operator_status || data.summary?.label || 'Entwicklungsstand';
      if (paragraph) paragraph.textContent = `${data.content_quality?.scope_label || 'Aktive Eventbasis'} ${data.content_quality?.coverage_percent ?? 0} % · ${data.automation?.process_health?.status === 'attention' ? 'Prozessprüfung nötig' : 'Kernprozesse aktuell'}`;
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
    const details = [];
    if (values['Ausgewählte Chancen'] && values['Ausgewählte Chancen'] !== '–') details.push(`${values['Ausgewählte Chancen']} Chancen übernommen`);
    if (values['Automatisch verhindert'] && values['Automatisch verhindert'] !== '–') details.push(`${values['Automatisch verhindert']} unpassende Kandidaten automatisch ausgeschlossen`);
    return { status, message, details: details.join(' · ') };
  }

  function injectDashboardStatus(doc, summary) {
    if (!doc?.body || doc.querySelector('[data-growth-process-summary]')) return;
    const topbar = doc.querySelector('.topbar');
    const section = doc.createElement('section');
    section.dataset.growthProcessSummary = '1';
    section.className = 'embeddedGrowthStatus';
    section.innerHTML = `<strong>Growth-/SEO-Prozess: ${esc(summary.status)}</strong>${summary.details ? `<span>${esc(summary.details)}</span>` : ''}${summary.message ? `<small>${esc(summary.message)}</small>` : ''}`;
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
        style.textContent = `.logout{display:none!important}.wrap{width:min(1180px,calc(100% - 16px))!important;padding:10px 0 24px!important}.topbar{margin-bottom:10px!important}.embeddedGrowthStatus{display:grid;gap:3px;margin:0 0 10px;padding:10px 12px;border:1px solid var(--line);border-radius:14px;background:var(--surface-2);font-size:13px}.embeddedGrowthStatus strong{font-size:14px}.embeddedGrowthStatus span,.embeddedGrowthStatus small{color:var(--muted)}@media(max-width:860px){.embeddedGrowthStatus{padding:9px 10px}.embeddedGrowthStatus small{display:none}}`;
        doc.head.appendChild(style);
        injectDashboardStatus(doc, summary);
        resizeFrame(frame);
        const observer = new ResizeObserver(() => resizeFrame(frame));
        observer.observe(doc.documentElement);
        if (doc.body) observer.observe(doc.body);
      } catch (_) {}
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

  const trendClass = trend => ['improved','stable','worsened','attention','insufficient'].includes(trend) ? trend : 'insufficient';
  const metricValue = value => Number.isInteger(Number(value)) ? String(Number(value)) : String(Number(value).toFixed(1));

  function componentRow(item) {
    const evidence = (item.evidence || []).map(metric => {
      const sign = Number(metric.delta) > 0 ? '+' : '';
      return `<div><span>${esc(metric.label)}</span><strong>${esc(metricValue(metric.current))}${esc(metric.unit || '')}</strong><small>${esc(metricValue(metric.previous))}${esc(metric.unit || '')} → ${sign}${esc(metricValue(metric.delta))}${esc(metric.unit || '')}</small></div>`;
    }).join('');
    const details = evidence
      ? `<details class="cc-component-details"><summary>Messwerte anzeigen</summary><div class="cc-component-evidence">${evidence}</div></details>`
      : '<p class="cc-component-note">Ein belastbarer Sieben-Tage-Vergleich entsteht automatisch, sobald genügend Verlaufssnapshots vorliegen.</p>';
    const runLink = item.run_url ? `<a href="${esc(item.run_url)}" target="_blank" rel="noopener">Letzten Lauf öffnen</a>` : '';
    return `<article class="cc-component-row"><header><div><h3>${esc(item.label)}</h3><p>${esc(item.technical_message || 'Kein aktueller Prozesshinweis.')}</p></div><span class="cc-component-trend cc-component-trend--${trendClass(item.trend)}">${esc(item.trend_label)}</span></header>${details}${runLink ? `<div class="cc-component-link">${runLink}</div>` : ''}</article>`;
  }

  async function enhanceComponentImprovement() {
    if (title?.textContent !== 'Entwicklung' || !view || view.querySelector('.cc-seo-frame') || view.querySelector('[data-component-improvement]')) return;
    const metricGrid = view.querySelector('.cc-metric-grid');
    if (!metricGrid) return;
    const placeholder = document.createElement('section');
    placeholder.dataset.componentImprovement = 'loading';
    placeholder.className = 'cc-component-improvement';
    placeholder.innerHTML = '<div class="cc-empty">Verbesserungswirkung wird ausgewertet …</div>';
    metricGrid.after(placeholder);
    try {
      const data = await loadDevelopment();
      if (title?.textContent !== 'Entwicklung' || view.querySelector('.cc-seo-frame')) return;
      const improvement = data.component_improvement || {};
      const items = improvement.items || [];
      placeholder.dataset.componentImprovement = 'ready';
      placeholder.innerHTML = `<header class="cc-component-head"><div><span class="cc-kicker">Automatisierte Verbesserung</span><h2>Wie entwickeln sich die Projektbausteine?</h2><p>Bewertung aus Betriebszustand, Qualität, Lernwirkung und einem mindestens sieben Tage alten Vergleich.</p></div>${improvement.comparison_at ? `<span class="cc-component-period">Vergleich seit ${esc(new Intl.DateTimeFormat('de-DE',{dateStyle:'medium'}).format(new Date(String(improvement.comparison_at).replace(' ','T'))))}</span>` : '<span class="cc-component-period">Verlauf wird aufgebaut</span>'}</header><div class="cc-component-list">${items.map(componentRow).join('') || '<div class="cc-empty">Noch keine Bausteindaten verfügbar.</div>'}</div>`;
    } catch (error) {
      placeholder.innerHTML = `<div class="cc-empty">${esc(error.message)}</div>`;
    }
  }

  function enhance() {
    enhanceOverview();
    enhanceSeoIntegration();
    enhanceComponentImprovement();
    if (title?.textContent !== 'Entwicklung' || !view?.querySelector('.cc-seo-frame')) view?.classList.remove('cc-view--seo');
  }

  const observer = new MutationObserver(enhance);
  if (view) observer.observe(view, { childList: true, subtree: true });
  if (title) observer.observe(title, { childList: true, characterData: true, subtree: true });
  enhance();
})();
