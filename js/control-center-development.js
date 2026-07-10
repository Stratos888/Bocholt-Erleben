(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const view = document.querySelector('#cc-view');
  const title = document.querySelector('#cc-title');
  let loading = null;

  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[char]));
  const formatDelta = (value, positiveIsGood = true) => {
    const number = Number(value || 0);
    const sign = number > 0 ? '+' : '';
    const tone = number === 0 ? 'neutral' : ((number > 0) === positiveIsGood ? 'good' : 'attention');
    return `<span class="cc-trend cc-trend--${tone}">${sign}${number}</span>`;
  };

  async function loadDevelopment() {
    if (loading) return loading;
    loading = fetch('/api/control-center/development.php', {
      headers: { 'X-BE-Review-Password': password() },
      cache: 'no-store',
    }).then(async response => {
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Entwicklungsdaten konnten nicht geladen werden.');
      return payload.data || {};
    }).finally(() => { loading = null; });
    return loading;
  }

  function technicalFailures(seo) {
    const failures = [];
    Object.entries(seo?.technical_seo?.pages || {}).forEach(([route, checks]) => {
      Object.entries(checks || {}).forEach(([check, ok]) => { if (!ok) failures.push(`${route}: ${check}`); });
    });
    Object.entries(seo?.technical_seo?.infrastructure || {}).forEach(([check, ok]) => {
      if (!ok) failures.push(`Infrastruktur: ${check}`);
    });
    return failures;
  }

  async function enhanceOverview() {
    if (title?.textContent !== 'Übersicht' || !view) return;
    const label = [...view.querySelectorAll('.cc-summary-label')].find(node => node.textContent.trim() === 'Entwicklung');
    const card = label?.closest('.cc-summary-card');
    if (!card || card.dataset.liveDevelopment === '1') return;
    card.dataset.liveDevelopment = '1';
    try {
      const data = await loadDevelopment();
      const heading = card.querySelector('h2');
      const paragraph = card.querySelector('p');
      if (heading) heading.textContent = data.summary?.label || 'Entwicklungsstand';
      if (paragraph) paragraph.textContent = `${data.content_quality?.coverage_percent || 0} % Eventbasis vollständig · ${data.automation?.publication_problems || 0} Veröffentlichungsprobleme`;
    } catch (_) {
      card.dataset.liveDevelopment = '0';
    }
  }

  async function enhanceDevelopment() {
    if (title?.textContent !== 'Entwicklung' || !view || view.querySelector('.cc-development-extra')) return;
    try {
      const data = await loadDevelopment();
      if (title?.textContent !== 'Entwicklung' || view.querySelector('.cc-development-extra')) return;
      const trends = data.trends || {};
      const seo = data.seo || {};
      const automation = data.automation || {};
      const failures = technicalFailures(seo);
      const section = document.createElement('section');
      section.className = 'cc-development-extra';
      section.innerHTML = `
        <section class="cc-section">
          <h2>Veränderung seit dem letzten Snapshot</h2>
          ${trends.available ? `
            <div class="cc-trend-grid">
              <div><span>Content-Vollständigkeit</span>${formatDelta(trends.content_coverage_delta, true)} Prozentpunkte</div>
              <div><span>Fehlende Inhaltsangaben</span>${formatDelta(trends.missing_content_delta, false)}</div>
              <div><span>Offene Qualitätsprüfungen</span>${formatDelta(trends.open_quality_reviews_delta, false)}</div>
              <div><span>Blockierte Aufgaben</span>${formatDelta(trends.blocked_tasks_delta, false)}</div>
              <div><span>Technische SEO-Basis</span>${formatDelta(trends.technical_seo_delta, true)} Prozentpunkte</div>
              <div><span>Veröffentlichungsprobleme</span>${formatDelta(trends.publication_problems_delta, false)}</div>
            </div>
            <p class="cc-muted">Vergleichsbasis: ${esc(trends.previous_at || '')}</p>
          ` : '<p>Der aktuelle Snapshot bildet die erste belastbare Vergleichsbasis. Ein Trend wird erst nach einem zeitlich getrennten Folgesnapshot angezeigt.</p>'}
        </section>
        <section class="cc-section">
          <h2>Technische SEO-Basis</h2>
          <p><strong>${esc(seo.technical_seo?.coverage_percent || 0)} %</strong> der geprüften Basisanforderungen erfüllt.</p>
          ${failures.length ? `<details class="cc-disclosure"><summary>${failures.length} offene technische SEO-Punkte</summary><div>${failures.map(item => `<p>${esc(item)}</p>`).join('')}</div></details>` : '<p>Keine offenen Punkte in der aktuellen technischen Basisprüfung.</p>'}
          <p>${esc(seo.search_console_message || '')}</p>
        </section>
        <section class="cc-section">
          <h2>Veröffentlichung und Automatisierung</h2>
          <p>${esc(automation.publication_problems || 0)} nicht bestätigte oder fehlgeschlagene Veröffentlichungen.</p>
          <p>${esc(automation.quality_effect_message || '')}</p>
        </section>`;
      view.appendChild(section);
    } catch (_) {
      // Basissicht bleibt nutzbar; ein erneuter Render versucht die Ergänzung erneut.
    }
  }

  function enhance() {
    enhanceOverview();
    enhanceDevelopment();
  }

  const observer = new MutationObserver(enhance);
  if (view) observer.observe(view, { childList: true, subtree: true });
  if (title) observer.observe(title, { childList: true, characterData: true, subtree: true });
})();
