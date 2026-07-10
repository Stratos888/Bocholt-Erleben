(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  let developmentData = null;
  let selectedManagementId = '';

  async function api(path) {
    const response = await fetch(path, { headers: { 'X-BE-Review-Password': password() }, cache: 'no-store' });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Integrationsdaten konnten nicht geladen werden.');
    return payload.data || {};
  }

  function processList(processHealth) {
    const items = processHealth?.items || [];
    if (!items.length) return '<div class="cc-empty">Noch keine belastbaren Prozessläufe vorhanden.</div>';
    return `<div class="cc-link-list">${items.map(item => `<div class="cc-link-card"><strong>${esc(item.label)}</strong><span>${esc(item.message || '')}${item.last_run_at ? ` · ${esc(item.last_run_at)}` : ''}</span></div>`).join('')}</div>`;
  }

  function enhanceDevelopment() {
    const view = document.querySelector('#cc-view');
    if (!view || !developmentData || !document.querySelector('[data-view="development"].is-active')) return;
    if (view.querySelector('#cc-development-integrations')) return;
    const automation = developmentData.automation || {};
    const learning = automation.learning || {};
    const seo = developmentData.seo || {};
    const funnel = seo.funnel || {};
    const rules = learning.rules || [];
    const section = document.createElement('section');
    section.id = 'cc-development-integrations';
    section.className = 'cc-section';
    section.innerHTML = `
      <h2>Automatisierungswirkung</h2>
      <div class="cc-metric-grid">
        <article class="cc-metric"><span>Verhinderte Wiederholungen</span><strong>${esc(learning.prevented_total_35d || 0)}</strong><p>Durch Lern- und Deduplizierungsregeln in den letzten 35 Tagen.</p></article>
        <article class="cc-metric ${Number(learning.false_positive_total_35d || 0) > 0 ? 'cc-metric--attention' : ''}"><span>False Positives</span><strong>${esc(learning.false_positive_total_35d || 0)}</strong><p>Als fehlerhaft erkannte Regelwirkungen.</p></article>
        <article class="cc-metric ${Number(learning.recurrence_total_35d || 0) > 0 ? 'cc-metric--attention' : ''}"><span>Wiederkehrende Probleme</span><strong>${esc(learning.recurrence_total_35d || 0)}</strong><p>Erneute Befunde trotz bestehender Regel.</p></article>
      </div>
      <details class="cc-disclosure"><summary>Wirksamste Lernregeln</summary><div class="cc-link-list">${rules.slice(0, 8).map(rule => `<div class="cc-link-card"><strong>${esc(rule.rule_key)}</strong><span>${esc(rule.prevented_count || 0)} verhindert · ${esc(rule.false_positive_count || 0)} falsch positiv · ${esc(rule.recurrence_count || 0)} wiederholt</span></div>`).join('') || '<div class="cc-empty">Noch keine Regelwirkung gespeichert.</div>'}</div></details>
      <h2>Prozesskette</h2>
      ${processList(automation.process_health)}
      <h2>Nutzer-Funnel und SEO-Wirkung</h2>
      <p>${esc(funnel.message || seo.search_console_message || '')}</p>
      <div class="cc-metric-grid">
        <article class="cc-metric"><span>Search-Console-Datensätze</span><strong>${esc(funnel.gsc_rows ?? '–')}</strong><p>Zuletzt erfasste Growth-Auswertung.</p></article>
        <article class="cc-metric"><span>GA4-Datensätze</span><strong>${esc(funnel.ga4_rows ?? '–')}</strong><p>Zuletzt erfasste Nutzungsdaten.</p></article>
        <article class="cc-metric"><span>Wertsignale</span><strong>${esc(funnel.value_metric_rows ?? '–')}</strong><p>Messbare Funnel- beziehungsweise Nutzensignale.</p></article>
      </div>`;
    view.appendChild(section);
  }

  async function loadDevelopmentIntegration() {
    try {
      developmentData = await api('/api/control-center/development.php');
      enhanceDevelopment();
    } catch (_) {
      // Der bestehende Entwicklungsbereich zeigt seinen eigenen Fehlerzustand.
    }
  }

  async function enhanceEventEditor(id) {
    if (!id) return;
    try {
      const data = await api(`/api/control-center/content.php?type=events&id=${encodeURIComponent(id)}`);
      const item = data.item || {};
      const dialogBody = document.querySelector('#cc-dialog-body');
      const location = dialogBody?.querySelector('#ce-location')?.closest('label');
      if (!dialogBody || !location) return;
      const heading = dialogBody.querySelector('h2');
      if (heading) heading.textContent = item.source_label ? `${item.source_label} bearbeiten` : 'Veranstaltung bearbeiten';
      if (item.source_label && !dialogBody.querySelector('[data-content-source-label]')) {
        const note = document.createElement('p');
        note.dataset.contentSourceLabel = '1';
        note.textContent = `Führende Quelle: ${item.source_label}`;
        heading?.after(note);
      }
      if (!dialogBody.querySelector('#ce-address')) {
        const label = document.createElement('label');
        label.className = 'cc-field';
        label.innerHTML = `<span>Adresse</span><input id="ce-address" type="text" value="${esc(item.address || '')}">`;
        location.after(label);
      }
    } catch (_) {}
  }

  document.addEventListener('click', event => {
    const viewButton = event.target.closest('[data-view="development"], [data-go-view="development"]');
    if (viewButton) setTimeout(loadDevelopmentIntegration, 100);
    const edit = event.target.closest('[data-manage-edit]');
    if (edit) {
      selectedManagementId = clean(edit.dataset.manageEdit);
      setTimeout(() => enhanceEventEditor(selectedManagementId), 100);
    }
    const system = event.target.closest('#cc-system');
    if (system) {
      setTimeout(async () => {
        try {
          const overview = await api('/api/control-center/overview.php');
          const dialogBody = document.querySelector('#cc-dialog-body');
          if (!dialogBody || dialogBody.querySelector('#cc-process-health')) return;
          const section = document.createElement('section');
          section.id = 'cc-process-health';
          section.innerHTML = `<h3>Automatisierte Prozesskette</h3>${processList({ items: overview.system?.processes || [] })}`;
          dialogBody.appendChild(section);
        } catch (_) {}
      }, 120);
    }
  }, true);

  const observer = new MutationObserver(() => enhanceDevelopment());
  const view = document.querySelector('#cc-view');
  if (view) observer.observe(view, { childList: true, subtree: true });
})();
