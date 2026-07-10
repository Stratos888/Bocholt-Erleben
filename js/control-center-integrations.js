(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const dialogBody = document.querySelector('#cc-dialog-body');
  let developmentData = null;
  let selectedManagementId = '';
  let enhancedEditorId = '';
  let enhancementPending = false;

  async function api(path) {
    const response = await fetch(path, { headers: { 'X-BE-Review-Password': password() }, cache: 'no-store' });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Integrationsdaten konnten nicht geladen werden.');
    return payload.data || {};
  }

  function processList(processHealth) {
    const items = processHealth?.items || [];
    if (!items.length) return '<div class="cc-empty">Noch keine belastbaren Prozessläufe vorhanden.</div>';
    return `<div class="cc-link-list">${items.map(item => {
      const content = `<strong>${esc(item.label)}</strong><span>${esc(item.message || '')}${item.last_run_at ? ` · ${esc(item.last_run_at)}` : ''}</span>`;
      return item.run_url
        ? `<a class="cc-link-card" href="${esc(item.run_url)}" target="_blank" rel="noopener">${content}</a>`
        : `<div class="cc-link-card">${content}</div>`;
    }).join('')}</div>`;
  }

  function metricValue(value, suffix = '') {
    return value === null || value === undefined ? '–' : `${value}${suffix}`;
  }

  function enhanceDevelopment() {
    const view = document.querySelector('#cc-view');
    if (!view || !developmentData || !document.querySelector('[data-view="development"].is-active')) return;
    if (view.querySelector('#cc-development-integrations')) return;
    const automation = developmentData.automation || {};
    const learning = automation.learning || {};
    const search = automation.search || {};
    const seo = developmentData.seo || {};
    const funnel = seo.funnel || {};
    const rules = learning.rules || [];
    const section = document.createElement('section');
    section.id = 'cc-development-integrations';
    section.className = 'cc-section';
    section.innerHTML = `
      <h2>Suchlauf und Intake</h2>
      <div class="cc-metric-grid">
        <article class="cc-metric"><span>Gefundene Kandidaten</span><strong>${esc(metricValue(search.raw_candidates))}</strong><p>Rohkandidaten des letzten erfassten KI-Suchlaufs.</p></article>
        <article class="cc-metric"><span>Für Prüfung ausgewählt</span><strong>${esc(metricValue(search.selected_candidates))}</strong><p>${esc(metricValue(search.selected_rate_percent, ' %'))} der Rohkandidaten.</p></article>
        <article class="cc-metric"><span>Automatisch verhindert</span><strong>${esc(metricValue(search.prevented_candidates))}</strong><p>Dubletten, unzulässige oder ungeeignete Kandidaten.</p></article>
        <article class="cc-metric"><span>In Inbox übernommen</span><strong>${esc(metricValue(search.intake_appended))}</strong><p>${esc(metricValue(search.intake_skipped))} beim Intake übersprungen.</p></article>
      </div>
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
        <article class="cc-metric"><span>Search-Console-Datensätze</span><strong>${esc(metricValue(funnel.gsc_rows))}</strong><p>Zuletzt erfasste Growth-Auswertung.</p></article>
        <article class="cc-metric"><span>GA4-Datensätze</span><strong>${esc(metricValue(funnel.ga4_rows))}</strong><p>Zuletzt erfasste Nutzungsdaten.</p></article>
        <article class="cc-metric"><span>Wertsignale</span><strong>${esc(metricValue(funnel.value_metric_rows))}</strong><p>Messbare Funnel- beziehungsweise Nutzensignale.</p></article>
      </div>`;
    view.appendChild(section);
  }

  async function loadDevelopmentIntegration() {
    try {
      const [development, overview] = await Promise.all([
        api('/api/control-center/development.php'),
        api('/api/control-center/overview.php'),
      ]);
      developmentData = development;
      developmentData.automation = developmentData.automation || {};
      developmentData.automation.process_health = {
        status: overview.system?.status || 'unknown',
        message: overview.system?.message || '',
        items: overview.system?.processes || [],
      };
      enhanceDevelopment();
    } catch (_) {
      // Der bestehende Entwicklungsbereich zeigt seinen eigenen Fehlerzustand.
    }
  }

  function labelManagementSources() {
    document.querySelectorAll('[data-manage-edit]').forEach(button => {
      const row = button.closest('.cc-manage-row');
      const kicker = row?.querySelector('.cc-kicker');
      if (!kicker) return;
      kicker.textContent = clean(button.dataset.manageEdit).startsWith('submission-')
        ? 'Anbieter-Event · veröffentlicht'
        : (kicker.textContent.includes('Aktivität') ? kicker.textContent : 'Redaktionelles Event · veröffentlicht');
    });
  }

  async function enhanceEventEditor(id) {
    if (!id || enhancedEditorId === id || enhancementPending) return;
    if (!dialogBody?.querySelector('#ce-location')) return;
    enhancementPending = true;
    try {
      const data = await api(`/api/control-center/content.php?type=events&id=${encodeURIComponent(id)}`);
      if (selectedManagementId !== id || !dialogBody.querySelector('#ce-location')) return;
      const item = data.item || {};
      const location = dialogBody.querySelector('#ce-location')?.closest('label');
      if (!location) return;
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
      if (item.source_system === 'submission_db' && !dialogBody.querySelector('[data-provider-field-note]')) {
        ['#ce-end', '#ce-city', '#ce-category'].forEach(selector => {
          const field = dialogBody.querySelector(selector);
          if (!field) return;
          field.disabled = true;
          field.closest('label')?.classList.add('is-disabled');
        });
        const note = document.createElement('p');
        note.className = 'cc-hint';
        note.dataset.providerFieldNote = '1';
        note.textContent = 'Enddatum wird für Anbieter-Events noch nicht separat geführt. Stadt und Kategorie werden aus Adresse und Inhalt abgeleitet.';
        dialogBody.querySelector('#ce-category')?.closest('label')?.after(note);
      }
      enhancedEditorId = id;
    } catch (_) {
      // Der Basiseditor bleibt nutzbar; der Quellhinweis wird beim nächsten Öffnen erneut versucht.
    } finally {
      enhancementPending = false;
    }
  }

  function maybeEnhanceCurrentEditor() {
    if (!selectedManagementId || !dialogBody?.querySelector('#ce-location')) return;
    enhanceEventEditor(selectedManagementId);
  }

  document.addEventListener('click', event => {
    const viewButton = event.target.closest('[data-view="development"], [data-go-view="development"]');
    if (viewButton) setTimeout(loadDevelopmentIntegration, 100);
    const manageButton = event.target.closest('[data-view="manage"], [data-manage-type]');
    if (manageButton) setTimeout(labelManagementSources, 180);
    const edit = event.target.closest('[data-manage-edit]');
    if (edit) {
      selectedManagementId = clean(edit.dataset.manageEdit);
      enhancedEditorId = '';
      setTimeout(maybeEnhanceCurrentEditor, 0);
    }
    const system = event.target.closest('#cc-system');
    if (system) {
      setTimeout(async () => {
        try {
          const overview = await api('/api/control-center/overview.php');
          if (!dialogBody || dialogBody.querySelector('#cc-process-health')) return;
          const section = document.createElement('section');
          section.id = 'cc-process-health';
          section.innerHTML = `<h3>Automatisierte Prozesskette</h3>${processList({ items: overview.system?.processes || [] })}`;
          dialogBody.appendChild(section);
        } catch (_) {}
      }, 120);
    }
  }, true);

  const viewObserver = new MutationObserver(() => {
    enhanceDevelopment();
    labelManagementSources();
  });
  const view = document.querySelector('#cc-view');
  if (view) viewObserver.observe(view, { childList: true, subtree: true });

  const dialogObserver = new MutationObserver(() => {
    if (!dialogBody?.querySelector('#ce-location')) {
      enhancedEditorId = '';
      return;
    }
    maybeEnhanceCurrentEditor();
  });
  if (dialogBody) dialogObserver.observe(dialogBody, { childList: true, subtree: true });
})();
