(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const view = document.querySelector('#cc-view');
  const title = document.querySelector('#cc-title');

  const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[char]));
  const formatDelta = (value, positiveIsGood = true) => {
    const number = Number(value || 0);
    const sign = number > 0 ? '+' : '';
    const tone = number === 0 ? 'neutral' : ((number > 0) === positiveIsGood ? 'good' : 'attention');
    return `<span class="cc-trend cc-trend--${tone}">${sign}${number}</span>`;
  };

  async function loadDevelopment() {
    const response = await fetch('/api/control-center/development.php', {
      headers: { 'X-BE-Review-Password': password() },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Entwicklungsdaten konnten nicht geladen werden.');
    return payload.data || {};
  }

  function technicalFailures(seo) {
    const failures = [];
    const pages = seo?.technical_seo?.pages || {};
    Object.entries(pages).forEach(([route, checks]) => {
      Object.entries(checks || {}).forEach(([check, ok]) => {
        if (!ok) failures.push(`${route}: ${check}`);
      });
    });
    Object.entries(seo?.technical_seo?.infrastructure || {}).forEach(([check, ok]) => {
      if (!ok) failures.push(`Infrastruktur: ${check}`);
    });
    return failures;
  }

  async function enhance() {
    if (title?.textContent !== 'Entwicklung' || !view || view.dataset.developmentEnhanced === '1') return;
    view.dataset.developmentEnhanced = '1';
    try {
      const data = await loadDevelopment();
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
          ` : '<p>Der aktuelle Snapshot bildet die erste belastbare Vergleichsbasis. Ein Trend wird erst ab dem nächsten Snapshot angezeigt.</p>'}
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
    } catch (error) {
      view.dataset.developmentEnhanced = '0';
    }
  }

  const observer = new MutationObserver(enhance);
  if (view) observer.observe(view, { childList: true, subtree: true });
  if (title) observer.observe(title, { childList: true, characterData: true, subtree: true });
})();
