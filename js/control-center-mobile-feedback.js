(() => {
  'use strict';

  const view = document.querySelector('#cc-view');
  const status = document.querySelector('#cc-status');
  const title = document.querySelector('#cc-title');
  const dialog = document.querySelector('#cc-dialog');
  const dialogBody = document.querySelector('#cc-dialog-body');
  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  let casesCache = null;
  let casesPromise = null;
  let workMode = 'open';

  async function api(path, options = {}) {
    const response = await fetch(path, {
      ...options,
      headers: { 'Content-Type':'application/json', 'X-BE-Review-Password':password(), ...(options.headers || {}) },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    return payload.data;
  }

  async function cases(force = false) {
    if (force) { casesCache = null; casesPromise = null; }
    if (casesCache) return casesCache;
    if (!casesPromise) casesPromise = api('/api/control-center/cases.php').then(data => (casesCache = data.items || []));
    return casesPromise;
  }

  const active = item => !['done','rejected','parked','information'].includes(item.state);
  const isBacklog = item => item.case_kind === 'backlog_item' && active(item);
  const isOpenWork = item => active(item) && item.type === 'task' && item.case_kind !== 'backlog_item';

  function openDialog(html) { dialogBody.innerHTML = html; dialog.showModal(); }
  function closeDialog() { if (dialog.open) dialog.close(); dialogBody.innerHTML = ''; }

  async function perform(item, action, payload = {}) {
    status.textContent = 'Aktion wird ausgeführt …';
    try {
      await api('/api/control-center/action.php', { method:'POST', body:JSON.stringify({ case_id:item.id, action, payload }) });
      await cases(true);
      status.textContent = 'Gespeichert.';
      renderSimpleWork();
    } catch (error) { status.textContent = error.message; }
  }

  function workRow(item) {
    const summary = clean(item.next_action || item.reason || item.blocked_reason).slice(0, 130);
    const backlog = isBacklog(item);
    const actions = backlog
      ? `<button class="cc-button cc-button--secondary" data-simple-action="edit_source">Bearbeiten</button><button class="cc-button cc-button--primary" data-simple-action="complete">Abschließen</button><button class="cc-button cc-button--danger" data-simple-action="reject">Verwerfen</button>`
      : `<button class="cc-button cc-button--primary" data-simple-action="complete">Abschließen</button>`;
    return `<details class="cc-labor-row" data-simple-case="${esc(item.id)}"><summary><span><b>${esc(item.title)}</b><small>${backlog ? 'Backlog' : 'Offen'}</small></span><span class="cc-labor-summary">${esc(summary)}</span></summary><div class="cc-labor-detail">${item.reason ? `<p>${esc(item.reason)}</p>` : ''}${item.next_action && item.next_action !== item.reason ? `<p><strong>Nächster Schritt:</strong> ${esc(item.next_action)}</p>` : ''}<div class="cc-actions">${actions}</div></div></details>`;
  }

  async function editBacklog(item) {
    const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`);
    openDialog(`<h2>Backlog bearbeiten</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="simple-bl-title" value="${esc(detail.title)}"></label><label class="cc-field"><span>Beschreibung</span><textarea id="simple-bl-note">${esc(detail.reason || '')}</textarea></label><button type="button" class="cc-button cc-button--primary" id="simple-bl-save">Speichern</button></div>`);
    document.querySelector('#simple-bl-save')?.addEventListener('click', async () => {
      const payload = { title:clean(document.querySelector('#simple-bl-title')?.value), description:clean(document.querySelector('#simple-bl-note')?.value) };
      closeDialog();
      await perform(item, 'edit_source', payload);
    });
  }

  function createBacklog() {
    openDialog(`<h2>Backlogpunkt anlegen</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="simple-new-title" required></label><label class="cc-field"><span>Beschreibung / Nutzen</span><textarea id="simple-new-note"></textarea></label><label class="cc-field"><span>Priorität</span><select id="simple-new-priority"><option value="hoch">Hoch</option><option value="mittel" selected>Mittel</option><option value="niedrig">Niedrig</option></select></label><button type="button" class="cc-button cc-button--primary" id="simple-new-save">Speichern</button></div>`);
    document.querySelector('#simple-new-save')?.addEventListener('click', async () => {
      const titleValue = clean(document.querySelector('#simple-new-title')?.value);
      if (!titleValue) { status.textContent = 'Titel fehlt.'; return; }
      const payload = { title:titleValue, note:clean(document.querySelector('#simple-new-note')?.value), priority:clean(document.querySelector('#simple-new-priority')?.value) };
      closeDialog();
      status.textContent = 'Backlog wird gespeichert …';
      try {
        await api('/api/growth-backlog/create.php', { method:'POST', body:JSON.stringify(payload) });
        workMode = 'backlog';
        await cases(true);
        status.textContent = 'Gespeichert.';
        renderSimpleWork();
      } catch (error) { status.textContent = error.message; }
    });
  }

  async function renderSimpleWork() {
    if (title?.textContent !== 'Arbeit') return;
    const items = await cases();
    const openItems = items.filter(isOpenWork);
    const backlogItems = items.filter(isBacklog);
    const visible = workMode === 'backlog' ? backlogItems : openItems;
    view.innerHTML = `<div class="cc-toolbar cc-toolbar--simple-work"><label class="cc-work-filter"><span>Ansicht</span><select id="cc-simple-work-select"><option value="open" ${workMode === 'open' ? 'selected' : ''}>Offen (${openItems.length})</option><option value="backlog" ${workMode === 'backlog' ? 'selected' : ''}>Backlog (${backlogItems.length})</option></select></label><button class="cc-button cc-button--primary" id="cc-simple-new">+ Neu</button></div><div class="cc-labor-list">${visible.length ? visible.map(workRow).join('') : `<div class="cc-empty cc-empty--large">${workMode === 'backlog' ? 'Backlog' : 'Offen'}: keine Einträge.</div>`}</div>`;
    document.querySelector('#cc-simple-work-select')?.addEventListener('change', event => { workMode = event.target.value; renderSimpleWork(); });
    document.querySelector('#cc-simple-new')?.addEventListener('click', createBacklog);
    document.querySelectorAll('[data-simple-action]').forEach(button => button.addEventListener('click', async event => {
      const item = items.find(entry => entry.id === event.target.closest('[data-simple-case]')?.dataset.simpleCase);
      const action = event.target.dataset.simpleAction;
      if (!item) return;
      if (action === 'edit_source') return editBacklog(item);
      if (action === 'reject') return perform(item, action, { reason:'Nicht weiterverfolgen' });
      return perform(item, action, {});
    }));
  }

  async function enhanceReview() {
    if (title?.textContent !== 'Prüfen') return;
    const filterRow = view.querySelector('.cc-filter-row');
    if (filterRow && !view.querySelector('#cc-review-select')) {
      const activeButton = filterRow.querySelector('.is-active');
      const select = document.createElement('label');
      select.className = 'cc-work-filter cc-review-filter';
      select.innerHTML = `<span>Prüfbereich</span><select id="cc-review-select">${[...filterRow.querySelectorAll('[data-review-group]')].map(button => `<option value="${esc(button.dataset.reviewGroup)}" ${button === activeButton ? 'selected' : ''}>${esc(button.textContent.trim())}</option>`).join('')}</select>`;
      filterRow.hidden = true;
      filterRow.insertAdjacentElement('afterend', select);
      select.querySelector('select')?.addEventListener('change', event => filterRow.querySelector(`[data-review-group="${CSS.escape(event.target.value)}"]`)?.click());
    }

    const card = view.querySelector('.cc-work-detail[data-case-id]');
    if (!card || card.dataset.comparisonLoaded === '1') return;
    card.dataset.comparisonLoaded = '1';
    try {
      const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(card.dataset.caseId)}`);
      if (detail.case_kind !== 'content_description_correction') return;
      const context = detail.decision_context || {};
      const current = clean(context.current_description || detail.source_payload?.current_description);
      const suggested = clean(context.suggested_description || detail.source_payload?.suggested_description);
      const target = card.querySelector('.cc-action-primary');
      if (!target) return;
      target.insertAdjacentHTML('beforebegin', `<section class="cc-copy-compare cc-copy-compare--inline"><div><span class="cc-kicker">Aktuell</span><p>${current ? esc(current) : '<em>Keine Beschreibung vorhanden.</em>'}</p></div><div class="cc-copy-compare__proposal"><span class="cc-kicker">Vorschlag</span><p>${suggested ? esc(suggested) : '<em>Kein Vorschlag verfügbar.</em>'}</p></div></section>`);
    } catch (error) { status.textContent = error.message; }
  }

  async function renderSeoDashboard() {
    if (title?.textContent !== 'Entwicklung' || view.dataset.seoDashboard === '1') return;
    view.dataset.seoDashboard = '1';
    try {
      const data = await api('/api/control-center/development.php');
      if (title?.textContent !== 'Entwicklung') return;
      const seo = data.seo || {};
      const funnel = seo.funnel || {};
      const metrics = funnel.metrics || {};
      const metric = key => metrics[key]?.value ?? null;
      const technical = seo.technical_seo?.coverage_percent ?? 0;
      const gscRows = funnel.gsc_rows;
      const ga4Rows = funnel.ga4_rows;
      const valueRows = funnel.value_metric_rows;
      view.innerHTML = `<section class="cc-development-head"><span class="cc-kicker">SEO & Projektwirkung</span><h2>${esc(data.summary?.label || 'Aktueller Stand')}</h2><p>Datenstand ${esc(new Date(data.generated_at).toLocaleDateString('de-DE'))}</p></section><section class="cc-metric-grid cc-metric-grid--compact">${seoCard('Technik', `${technical} %`, 'Title, Description, Canonical, Sitemap und Feeds')}${seoCard('Search Console', gscRows === null || gscRows === undefined ? 'Nicht angebunden' : `${gscRows} Datensätze`, gscRows === null || gscRows === undefined ? 'Noch keine belastbaren Suchdaten' : 'Suchdaten in Betriebsdatenbank vorhanden')}${seoCard('Nutzung', ga4Rows === null || ga4Rows === undefined ? 'Nicht angebunden' : `${ga4Rows} Datensätze`, ga4Rows === null || ga4Rows === undefined ? 'Noch keine belastbaren GA4-Daten' : 'Nutzungsdaten vorhanden')}</section><section class="cc-section cc-seo-compact"><h2>Inhaltliche Sichtbarkeit</h2><dl><div><dt>Eventbasis vollständig</dt><dd>${esc(seo.content_basis_percent ?? 0)} %</dd></div><div><dt>Fehlende Beschreibungen</dt><dd>${esc(seo.missing_descriptions ?? 0)}</dd></div><div><dt>Fehlende Quellen</dt><dd>${esc(seo.missing_sources ?? 0)}</dd></div><div><dt>Wertsignale</dt><dd>${valueRows === null || valueRows === undefined ? 'nicht vorhanden' : `${valueRows} Datensätze`}</dd></div></dl></section><details class="cc-disclosure"><summary>Such- und Intake-Wirkung</summary><div><p><strong>Suchkandidaten:</strong> ${esc(metric('search.weekly.raw_candidates') ?? '–')}</p><p><strong>Ausgewählt:</strong> ${esc(metric('search.weekly.selected_candidates') ?? '–')}</p><p><strong>Automatisch verhindert:</strong> ${esc(metric('search.weekly.prevented_candidates') ?? '–')}</p><p><strong>In Inbox übernommen:</strong> ${esc(metric('intake.manual.appended_items') ?? '–')}</p></div></details>`;
    } catch (error) {
      view.dataset.seoDashboard = '';
      status.textContent = error.message;
    }
  }

  function seoCard(titleText, value, text) {
    return `<article class="cc-metric"><span>${esc(titleText)}</span><strong>${esc(value)}</strong><p>${esc(text)}</p></article>`;
  }

  function removeIdeaCreation() { document.querySelector('[data-new-kind="idea"]')?.remove(); }

  let scheduled = false;
  const enhance = () => {
    if (scheduled) return;
    scheduled = true;
    queueMicrotask(async () => {
      scheduled = false;
      removeIdeaCreation();
      if (title?.textContent === 'Arbeit' && !view.querySelector('#cc-simple-work-select')) await renderSimpleWork();
      await Promise.allSettled([enhanceReview(), renderSeoDashboard()]);
    });
  };

  new MutationObserver(enhance).observe(document.body, { childList:true, subtree:true, characterData:true });
  document.addEventListener('click', event => {
    if (event.target.closest('#cc-refresh')) { casesCache = null; casesPromise = null; view.dataset.seoDashboard = ''; }
  });
  enhance();
})();
