(() => {
  'use strict';

  const state = {
    password: sessionStorage.getItem('be_cc_password') || '',
    view: 'overview', cases: [], overview: null, development: null,
    reviewGroup: 'all', reviewIndex: 0,
    laborMode: 'now', managementType: 'events', managementQuery: '',
    managementItems: { events: [], activities: [] },
    managementLoaded: { events: false, activities: false },
    managementWarnings: { events: [], activities: [] },
  };

  const els = {
    auth: document.querySelector('#cc-auth'), authForm: document.querySelector('#cc-auth-form'), password: document.querySelector('#cc-password'),
    content: document.querySelector('#cc-content'), view: document.querySelector('#cc-view'), title: document.querySelector('#cc-title'), status: document.querySelector('#cc-status'),
    refresh: document.querySelector('#cc-refresh'), menuButton: document.querySelector('#cc-menu-button'), nav: [...document.querySelectorAll('.cc-nav [data-view]')],
    badgeReview: document.querySelector('#cc-badge-review'), badgeLabor: document.querySelector('#cc-badge-labor'),
    dialog: document.querySelector('#cc-dialog'), dialogBody: document.querySelector('#cc-dialog-body'), dialogClose: document.querySelector('#cc-dialog-close'),
  };

  const reviewLabels = { all: 'Alle', new_content: 'Neue Inhalte', quality: 'Qualität', provider: 'Anbieter', approvals: 'Freigaben', other: 'Sonstige' };
  const laborLabels = { now: 'Jetzt', next: 'Als Nächstes', waiting: 'Wartet', blocked: 'Blockiert', backlog: 'Backlog', ideas: 'Ideen', archive: 'Archiv' };
  const rejectReasons = ['Termin liegt in der Vergangenheit', 'Doppelt / bereits abgedeckt', 'Terminangaben unklar', 'Nicht öffentlich zugänglich', 'Quelle / Angaben reichen nicht', 'Nicht lokal genug', 'Redaktionell nicht passend'];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const formatDate = value => { if (!value) return ''; const d = new Date(String(value).replace(' ', 'T')); return Number.isNaN(d.getTime()) ? '' : new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(d); };
  const setStatus = message => { els.status.textContent = message || ''; };

  async function api(path, options = {}) {
    const { timeoutMs = 20000, ...fetchOptions } = options;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetch(path, {
        ...fetchOptions,
        signal: controller.signal,
        headers: { 'Content-Type': 'application/json', 'X-BE-Review-Password': state.password, ...(fetchOptions.headers || {}) },
        cache: 'no-store',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) { if (response.status === 401) logout(); throw new Error(payload.message || 'Anfrage fehlgeschlagen.'); }
      return payload.data;
    } catch (error) {
      if (error?.name === 'AbortError') throw new Error('Die Datenquelle antwortet nicht rechtzeitig. Bitte erneut laden.');
      throw error;
    } finally { window.clearTimeout(timer); }
  }

  function logout() { state.password = ''; sessionStorage.removeItem('be_cc_password'); els.content.hidden = true; els.auth.hidden = false; closeDialog(); }
  function openDialog(html) { els.dialogBody.innerHTML = html; els.dialog.showModal(); }
  function closeDialog() { if (els.dialog.open) els.dialog.close(); els.dialogBody.innerHTML = ''; }
  function activeCases() { return state.cases.filter(i => !['done', 'rejected', 'parked', 'information'].includes(i.state)); }
  function allReviewCases() { return activeCases().filter(i => i.type === 'intake'); }
  function reviewCases() { return allReviewCases().filter(i => state.reviewGroup === 'all' || (i.queue_group || 'other') === state.reviewGroup); }
  function concreteTasks() { return state.cases.filter(i => i.type === 'task'); }
  function backlogCases() { return state.cases.filter(i => i.case_kind === 'backlog_item'); }
  function ideaCases() { return state.cases.filter(i => i.type === 'idea' && i.case_kind !== 'backlog_item'); }

  function laborCases() {
    const tasks = concreteTasks();
    if (state.laborMode === 'now') return tasks.filter(i => !['done','rejected','parked','waiting','blocked','snoozed'].includes(i.state) && (i.overdue || i.bucket === 'now' || i.state === 'in_progress'));
    if (state.laborMode === 'next') return tasks.filter(i => !['done','rejected','parked','waiting','blocked'].includes(i.state) && !i.overdue && i.bucket !== 'now');
    if (state.laborMode === 'waiting') return tasks.filter(i => i.state === 'waiting' || i.state === 'snoozed');
    if (state.laborMode === 'blocked') return tasks.filter(i => i.state === 'blocked');
    if (state.laborMode === 'backlog') return backlogCases().filter(i => !['done','rejected','parked'].includes(i.state));
    if (state.laborMode === 'ideas') return ideaCases().filter(i => !['done','rejected','parked'].includes(i.state));
    return state.cases.filter(i => ['done','rejected','parked'].includes(i.state) && (i.type === 'task' || i.type === 'idea'));
  }

  function setView(view) {
    state.view = view;
    const titles = { overview: 'Übersicht', review: 'Prüfen', labor: 'Arbeit', manage: 'Verwaltung', development: 'Entwicklung' };
    els.title.textContent = titles[view] || 'Steuerzentrale';
    els.nav.forEach(b => b.classList.toggle('is-active', b.dataset.view === view));
    render(); window.scrollTo({ top: 0, behavior: 'instant' });
  }

  function compactSummary(title, count, detail, view, label) {
    return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${count}</strong><p>${escapeHtml(detail)}</p></div>${view ? `<button class="cc-button cc-button--primary" data-go-view="${view}">${escapeHtml(label)}</button>` : ''}</article>`;
  }

  function renderOverview() {
    const cases = activeCases(), urgent = cases.filter(i => i.bucket === 'now'), reviews = allReviewCases(), tasks = cases.filter(i => i.type === 'task'), waiting = tasks.filter(i => ['waiting','snoozed'].includes(i.state)), backlog = backlogCases().filter(i => !['done','rejected','parked'].includes(i.state));
    const groups = reviews.reduce((a, i) => { const k = i.queue_group || 'other'; a[k] = (a[k] || 0) + 1; return a; }, {});
    const groupText = Object.entries(groups).map(([k,n]) => `${n} ${reviewLabels[k] || 'Sonstige'}`).join(' · ') || 'Keine offenen Prüfungen';
    const urgentText = urgent.length ? urgent.slice(0, 2).map(i => i.title).join(' · ') : 'Aktuell ist nichts dringend.';
    const dev = state.development;
    const processAttention = dev?.automation?.process_health?.status === 'attention';
    const devText = dev ? `${dev.content_quality?.coverage_percent ?? 0} % Eventbasis · ${processAttention ? 'Prozessprüfung nötig' : 'Prozesse ohne bekannten Fehler'}` : 'Projektwirkung wird geladen.';
    els.view.innerHTML = `<section class="cc-overview-grid">
      ${compactSummary('Jetzt erforderlich', urgent.length, urgentText, urgent.length ? 'review' : '', 'Jetzt prüfen')}
      ${compactSummary('Zu prüfen', reviews.length, groupText, reviews.length ? 'review' : '', 'Prüfen öffnen')}
      ${compactSummary('Arbeit', tasks.filter(i => !['done','rejected','parked'].includes(i.state)).length, `${waiting.length} warten · ${backlog.length} im Backlog`, 'labor', 'Arbeit öffnen')}
      <article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Entwicklung</span><h2>${escapeHtml(dev?.summary?.label || 'Daten werden geladen')}</h2><p>${escapeHtml(devText)}</p></div><button class="cc-button cc-button--secondary" data-go-view="development">Entwicklung öffnen</button></article>
    </section>`;
    bindViewLinks();
  }

  function renderReviewDetail(item, index, total) {
    const c = item.decision_context || {}, primary = item.primary_action, secondary = item.secondary_actions || [];
    const problem = clean(c.issue_text || item.reason), recommended = clean(c.recommended_action || item.next_action), description = clean(c.current_description || c.description);
    const links = (item.source_links || []).map(l => `<a class="cc-button cc-button--secondary" href="${escapeHtml(l.url)}" target="_blank" rel="noopener">${escapeHtml(l.label)}</a>`).join('');
    return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}"><header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(reviewLabels[item.queue_group] || 'Prüfung')} · ${index + 1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><span class="cc-pill">${escapeHtml(item.display_status)}</span></header>
      ${problem ? `<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>` : ''}
      ${recommended ? `<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>` : ''}
      ${description ? `<details class="cc-disclosure"><summary>Aktuellen Inhalt anzeigen</summary><div>${escapeHtml(description)}</div></details>` : ''}
      <div class="cc-action-primary">${primary ? `<button class="cc-button cc-button--primary cc-button--large" data-review-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>` : `<div class="cc-empty">${escapeHtml(item.waiting_for || 'Aktuell keine Aktion erforderlich.')}</div>`}</div>
      <div class="cc-actions cc-actions--secondary">${secondary.map(a => `<button class="cc-button ${a.destructive ? 'cc-button--danger' : 'cc-button--secondary'}" data-review-action="${escapeHtml(a.key)}">${escapeHtml(a.label)}</button>`).join('')}${links}<button class="cc-button cc-button--ghost" data-review-action="details">Details</button></div>
      <footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-review-move="-1" ${index === 0 ? 'disabled' : ''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-review-move="1" ${index >= total - 1 ? 'disabled' : ''}>Weiter ›</button></footer></article>`;
  }

  function reviewGroupCount(key) { return key === 'all' ? allReviewCases().length : allReviewCases().filter(i => (i.queue_group || 'other') === key).length; }
  function renderReview() {
    const items = reviewCases();
    if (state.reviewIndex >= items.length) state.reviewIndex = Math.max(0, items.length - 1);
    const active = items[state.reviewIndex];
    const queue = items.map((item, index) => `<button class="cc-queue-item ${index === state.reviewIndex ? 'is-active' : ''}" data-review-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(reviewLabels[item.queue_group] || item.display_status)}</small></button>`).join('');
    els.view.innerHTML = `<div class="cc-filter-row">${Object.entries(reviewLabels).map(([k,l]) => `<button class="cc-filter ${state.reviewGroup === k ? 'is-active' : ''}" data-review-group="${k}">${escapeHtml(l)} (${reviewGroupCount(k)})</button>`).join('')}</div>${items.length ? `<div class="cc-work-layout"><aside class="cc-queue">${queue}</aside><main>${renderReviewDetail(active, state.reviewIndex, items.length)}</main></div>` : '<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu prüfen.</div>'}`;
    bindReview();
  }

  function laborActions(item) {
    const actions = [];
    if (item.primary_action && item.primary_action.key !== 'convert_to_task') actions.push(item.primary_action);
    for (const action of item.secondary_actions || []) {
      if (!['convert_to_task', 'details'].includes(action.key)) actions.push(action);
    }
    const seen = new Set();
    return actions.filter(action => action?.key && !seen.has(action.key) && seen.add(action.key));
  }

  function laborRow(item) {
    const kind = item.case_kind === 'backlog_item' ? 'Backlog' : item.type === 'idea' ? 'Idee' : item.display_status;
    const summary = clean(item.next_action || item.reason).slice(0, 150);
    const actions = laborActions(item);
    return `<details class="cc-labor-row" data-case-id="${escapeHtml(item.id)}"><summary><span><b>${escapeHtml(item.title)}</b><small>${escapeHtml(kind)}${item.due_at ? ` · ${escapeHtml(formatDate(item.due_at))}` : ''}</small></span><span class="cc-labor-summary">${escapeHtml(summary)}</span></summary><div class="cc-labor-detail">${item.reason ? `<p>${escapeHtml(item.reason)}</p>` : ''}${item.next_action && item.next_action !== item.reason ? `<p><strong>Nächster Schritt:</strong> ${escapeHtml(item.next_action)}</p>` : ''}${item.waiting_for ? `<p><strong>Wartet auf:</strong> ${escapeHtml(item.waiting_for)}</p>` : ''}${actions.length ? `<div class="cc-actions">${actions.map(a => `<button class="cc-button ${a.destructive ? 'cc-button--danger' : a.key === 'complete' ? 'cc-button--primary' : 'cc-button--secondary'}" data-labor-action="${escapeHtml(a.key)}">${escapeHtml(a.label)}</button>`).join('')}</div>` : ''}</div></details>`;
  }

  function renderLabor() {
    const items = laborCases();
    els.view.innerHTML = `<div class="cc-toolbar"><div class="cc-filter-row cc-filter-row--contained">${Object.entries(laborLabels).map(([k,l]) => `<button class="cc-filter ${state.laborMode === k ? 'is-active' : ''}" data-labor-mode="${k}">${escapeHtml(l)}</button>`).join('')}</div><button class="cc-button cc-button--primary" id="cc-new-work">+ Neu</button></div><div class="cc-labor-list">${items.length ? items.map(laborRow).join('') : `<div class="cc-empty cc-empty--large">${escapeHtml(laborLabels[state.laborMode])}: keine Einträge.</div>`}</div>`;
    bindLabor();
  }

  async function loadManagement(type) {
    if (state.managementLoaded[type]) return;
    state.managementWarnings[type] = [];
    if (type === 'activities') {
      const result = await api('/api/control-center/content.php?type=activities', { timeoutMs: 15000 });
      state.managementItems.activities = result.items || [];
      state.managementLoaded.activities = true;
      return;
    }
    const requests = [
      ['Redaktionelle Events', '/api/control-center/content.php?type=events&source=sheet'],
      ['Anbieter-Events', '/api/control-center/content.php?type=events&source=submissions'],
    ];
    const results = await Promise.allSettled(requests.map(([,path]) => api(path, { timeoutMs: 15000 })));
    const items = [];
    results.forEach((result, index) => {
      if (result.status === 'fulfilled') items.push(...(result.value.items || []));
      else state.managementWarnings.events.push(`${requests[index][0]} konnten nicht geladen werden: ${result.reason?.message || 'unbekannter Fehler'}`);
    });
    if (results.every(result => result.status === 'rejected')) throw new Error('Keine Veranstaltungsquelle konnte geladen werden.');
    state.managementItems.events = items.sort((a,b) => String(a.date || '').localeCompare(String(b.date || '')) || String(a.title || '').localeCompare(String(b.title || ''), 'de'));
    state.managementLoaded.events = true;
  }

  function managementRow(item, type) {
    const detail = [item.date, item.location, item.city, item.category].filter(Boolean).join(' · ');
    const source = type === 'events' ? (item.source_label || 'Veranstaltung') : 'Aktivität';
    return `<article class="cc-manage-row"><div><span class="cc-kicker">${escapeHtml(source)} · ${escapeHtml(item.status || 'veröffentlicht')}</span><h3>${escapeHtml(item.title)}</h3>${detail ? `<p>${escapeHtml(detail)}</p>` : ''}</div><div class="cc-actions"><a class="cc-button cc-button--secondary" href="${escapeHtml(item.public_url)}" target="_blank" rel="noopener">Vorschau</a><button class="cc-button cc-button--primary" data-manage-edit="${escapeHtml(item.id)}">Bearbeiten</button></div></article>`;
  }

  async function renderManage() {
    els.view.innerHTML = '<div class="cc-empty">Inhalte werden geladen …</div>';
    try {
      await loadManagement(state.managementType);
      const source = state.managementItems[state.managementType] || [], q = state.managementQuery.toLowerCase();
      const items = source.filter(i => !q || clean(i.title).toLowerCase().includes(q)).slice(0,100);
      const warning = state.managementWarnings[state.managementType]?.length ? `<div class="cc-notice cc-notice--attention">${state.managementWarnings[state.managementType].map(escapeHtml).join('<br>')}</div>` : '';
      els.view.innerHTML = `<div class="cc-toolbar cc-toolbar--manage"><div class="cc-segment"><button class="${state.managementType === 'events' ? 'is-active' : ''}" data-manage-type="events">Veranstaltungen</button><button class="${state.managementType === 'activities' ? 'is-active' : ''}" data-manage-type="activities">Aktivitäten</button></div><label class="cc-search"><span class="sr-only">Suchen</span><input id="cc-manage-search" type="search" value="${escapeHtml(state.managementQuery)}" placeholder="Suchen …"></label></div>${warning}<div id="cc-manage-results" class="cc-manage-list">${items.map(i => managementRow(i,state.managementType)).join('') || '<div class="cc-empty cc-empty--large">Keine passenden Inhalte gefunden.</div>'}</div>`;
      bindManage();
    } catch (error) {
      els.view.innerHTML = `<div class="cc-empty cc-empty--large"><strong>Verwaltung konnte nicht geladen werden.</strong><br>${escapeHtml(error.message)}</div>`;
    }
  }

  function metricCard(title, value, text, tone = '') { return `<article class="cc-metric ${tone ? `cc-metric--${tone}` : ''}"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong><p>${escapeHtml(text)}</p></article>`; }
  async function renderDevelopment() {
    if (!state.development) {
      els.view.innerHTML = '<div class="cc-empty">Entwicklungsdaten werden geladen …</div>';
      try { state.development = await api('/api/control-center/development.php'); }
      catch (e) { els.view.innerHTML = `<div class="cc-empty cc-empty--large">${escapeHtml(e.message)}</div>`; return; }
    }
    const d = state.development, q = d.content_quality || {}, a = d.automation || {}, s = d.seo || {};
    const process = a.process_health || {};
    const processProblem = process.status === 'attention' || Number(a.blocked_tasks || 0) > 0;
    const gsc = s.funnel?.gsc_rows;
    const visibilityText = gsc === null || gsc === undefined ? 'Search Console noch ohne belastbare Daten' : `${gsc} Search-Console-Datensätze verfügbar`;
    els.view.innerHTML = `<section class="cc-development-head"><span class="cc-kicker">Gesamtprojekt</span><h2>${escapeHtml(d.summary?.label || 'Entwicklungsstand')}</h2><p>Datenstand ${escapeHtml(formatDate(d.generated_at))}</p></section>
      <section class="cc-metric-grid cc-metric-grid--compact">
        ${metricCard('Content', `${q.coverage_percent ?? 0} %`, `${q.open_quality_reviews || 0} offene Qualitätsprüfungen`, q.open_quality_reviews > 0 ? 'attention' : 'good')}
        ${metricCard('Prozesse', processProblem ? 'Prüfen' : 'Okay', process.message || `${a.blocked_tasks || 0} blockiert`, processProblem ? 'attention' : 'good')}
        ${metricCard('Sichtbarkeit', `${s.technical_seo?.coverage_percent ?? s.onpage_event_coverage_percent ?? 0} %`, visibilityText, s.technical_seo?.coverage_percent < 100 ? 'attention' : 'good')}
      </section>
      <details class="cc-disclosure cc-development-details"><summary>Weitere Messwerte</summary><div><p><strong>Aktivitäten:</strong> ${escapeHtml(q.activity_coverage_percent ?? 0)} % vollständig</p><p><strong>Suchlauf:</strong> ${escapeHtml(a.search?.selected_candidates ?? '–')} Kandidaten ausgewählt · ${escapeHtml(a.search?.prevented_candidates ?? '–')} automatisch verhindert</p><p><strong>Lernwirkung:</strong> ${escapeHtml(a.learning?.prevented_total_35d ?? 0)} Wiederholungen verhindert · ${escapeHtml(a.learning?.false_positive_total_35d ?? 0)} False Positives</p></div></details>`;
  }

  function render() { if (state.view === 'overview') renderOverview(); if (state.view === 'review') renderReview(); if (state.view === 'labor') renderLabor(); if (state.view === 'manage') renderManage(); if (state.view === 'development') renderDevelopment(); }
  function updateBadges() { const reviews = allReviewCases().length, labor = activeCases().filter(i => i.type === 'task').length; [[els.badgeReview,reviews],[els.badgeLabor,labor]].forEach(([e,n]) => { e.hidden = n === 0; e.textContent = n > 99 ? '99+' : String(n); }); }
  async function load() { setStatus('Daten werden geladen …'); try { state.overview = await api('/api/control-center/overview.php'); const cases = await api('/api/control-center/cases.php'); state.cases = cases.items || []; state.development = null; updateBadges(); render(); setStatus(''); } catch (e) { setStatus(e.message); } }

  function bindViewLinks() { document.querySelectorAll('[data-go-view]').forEach(b => b.addEventListener('click', () => setView(b.dataset.goView))); }
  function bindReview() { document.querySelectorAll('[data-review-group]').forEach(b => b.addEventListener('click', () => { state.reviewGroup = b.dataset.reviewGroup; state.reviewIndex = 0; renderReview(); })); document.querySelectorAll('[data-review-index]').forEach(b => b.addEventListener('click', () => { state.reviewIndex = Number(b.dataset.reviewIndex); renderReview(); })); document.querySelectorAll('[data-review-move]').forEach(b => b.addEventListener('click', () => { state.reviewIndex += Number(b.dataset.reviewMove); renderReview(); })); document.querySelectorAll('[data-review-action]').forEach(b => b.addEventListener('click', () => handleCaseAction(reviewCases()[state.reviewIndex], b.dataset.reviewAction))); }
  function bindLabor() { document.querySelectorAll('[data-labor-mode]').forEach(b => b.addEventListener('click', () => { state.laborMode = b.dataset.laborMode; renderLabor(); })); document.querySelector('#cc-new-work')?.addEventListener('click', openNewWorkMenu); document.querySelectorAll('[data-labor-action]').forEach(b => b.addEventListener('click', () => { const item = state.cases.find(c => c.id === b.closest('[data-case-id]')?.dataset.caseId); handleCaseAction(item, b.dataset.laborAction); })); }
  function bindManage() { document.querySelectorAll('[data-manage-type]').forEach(b => b.addEventListener('click', () => { state.managementType = b.dataset.manageType; state.managementQuery = ''; renderManage(); })); const input = document.querySelector('#cc-manage-search'); input?.addEventListener('input', e => { state.managementQuery = e.target.value; const items = (state.managementItems[state.managementType] || []).filter(i => !state.managementQuery || clean(i.title).toLowerCase().includes(state.managementQuery.toLowerCase())).slice(0,100); document.querySelector('#cc-manage-results').innerHTML = items.map(i => managementRow(i,state.managementType)).join('') || '<div class="cc-empty">Keine passenden Inhalte.</div>'; bindManageEdit(); }); bindManageEdit(); }
  function bindManageEdit() { document.querySelectorAll('[data-manage-edit]').forEach(b => b.addEventListener('click', () => openContentEditor(b.dataset.manageEdit), { once: true })); }

  function openNewWorkMenu() { openDialog(`<h2>Neuen Eintrag anlegen</h2><div class="cc-link-list"><button class="cc-link-card" data-new-kind="task"><strong>Aufgabe</strong><span>Konkrete Arbeit</span></button><button class="cc-link-card" data-new-kind="backlog"><strong>Backlogpunkt</strong><span>Später zu erledigen</span></button><button class="cc-link-card" data-new-kind="idea"><strong>Idee</strong><span>Noch ungeprüfter Gedanke</span></button></div>`); document.querySelectorAll('[data-new-kind]').forEach(b => b.addEventListener('click', () => { const kind = b.dataset.newKind; closeDialog(); kind === 'backlog' ? openBacklogDialog() : openCreateDialog(kind); })); }
  function openCreateDialog(type) { const task = type === 'task'; openDialog(`<h2>${task ? 'Aufgabe anlegen' : 'Idee erfassen'}</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-create-title" required></label><label class="cc-field"><span>${task ? 'Nächster Schritt' : 'Anlass oder Nutzen'}</span><textarea id="cc-create-note"></textarea></label>${task ? '<label class="cc-field"><span>Fällig am (optional)</span><input id="cc-create-due" type="date"></label>' : ''}<button type="button" class="cc-button cc-button--primary" id="cc-create-save">Speichern</button></div>`); document.querySelector('#cc-create-save').addEventListener('click', async () => { const title = clean(document.querySelector('#cc-create-title').value); if (!title) return setStatus('Titel fehlt.'); const note = clean(document.querySelector('#cc-create-note').value), due = clean(document.querySelector('#cc-create-due')?.value); await api('/api/control-center/cases.php', { method: 'POST', body: JSON.stringify({ type, state: task ? 'open' : 'new', priority: 'normal', title, reason: task ? '' : note, next_action: task ? note : '', due_at: due ? `${due} 17:00:00` : null, source_system: 'manual' }) }); closeDialog(); state.laborMode = task ? 'next' : 'ideas'; await load(); setView('labor'); }); }
  function openBacklogDialog() { openDialog(`<h2>Backlog-Punkt anlegen</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-backlog-title" required></label><label class="cc-field"><span>Beschreibung / Nutzen</span><textarea id="cc-backlog-note"></textarea></label><label class="cc-field"><span>Priorität</span><select id="cc-backlog-priority"><option value="hoch">Hoch</option><option value="mittel" selected>Mittel</option><option value="niedrig">Niedrig</option></select></label><button type="button" class="cc-button cc-button--primary" id="cc-backlog-save">Speichern</button></div>`); document.querySelector('#cc-backlog-save').addEventListener('click', async () => { const title = clean(document.querySelector('#cc-backlog-title').value); if (!title) return setStatus('Titel fehlt.'); await api('/api/growth-backlog/create.php', { method: 'POST', body: JSON.stringify({ title, note: clean(document.querySelector('#cc-backlog-note').value), priority: clean(document.querySelector('#cc-backlog-priority').value) }) }); closeDialog(); state.laborMode = 'backlog'; await load(); setView('labor'); }); }

  async function openContentEditor(id) {
    const type = state.managementType;
    const data = await api(`/api/control-center/content.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, { timeoutMs: 15000 });
    const item = data.item;
    if (type === 'activities' && item.editable === false) { openDialog(`<h2>${escapeHtml(item.title)}</h2><p>${escapeHtml(item.edit_notice)}</p><a class="cc-button cc-button--secondary" href="${escapeHtml(item.public_url)}" target="_blank" rel="noopener">Vorschau öffnen</a>`); return; }
    const field = (fieldId,label,value,inputType='text') => `<label class="cc-field"><span>${label}</span><input id="${fieldId}" type="${inputType}" value="${escapeHtml(value || '')}"></label>`;
    openDialog(`<h2>Veranstaltung bearbeiten</h2><div class="cc-stack">${field('ce-title','Titel',item.title)}${field('ce-date','Startdatum',item.date,'date')}${field('ce-end','Enddatum',item.end_date,'date')}${field('ce-time','Uhrzeit',item.time)}${field('ce-location','Ort',item.location)}${field('ce-city','Stadt',item.city)}${field('ce-category','Kategorie',item.category)}${field('ce-source','Offizielle Quelle',item.source_url,'url')}${field('ce-ticket','Ticketlink',item.ticket_url,'url')}<label class="cc-field"><span>Beschreibung</span><textarea id="ce-description">${escapeHtml(item.description || '')}</textarea></label><button type="button" class="cc-button cc-button--primary" id="ce-save" data-event-id="${escapeHtml(item.id)}">Speichern und Aktualisierung starten</button></div>`);
  }

  function openHeaderMenu() { openDialog(`<h2>Menü</h2><div class="cc-link-list"><a class="cc-link-card" href="/fuer-veranstalter/dashboard/"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Prozesse und Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-deploy"><strong>Deployment starten</strong><span>Datenbestand neu veröffentlichen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div>`); document.querySelector('#cc-system').addEventListener('click', openSystemDialog); document.querySelector('#cc-deploy').addEventListener('click', async () => { closeDialog(); setStatus('Deployment wird gestartet …'); try { const r = await api('/api/control-center/deploy.php', { method:'POST', body:'{}' }); setStatus(r.message); } catch (e) { setStatus(e.message); } }); document.querySelector('#cc-logout').addEventListener('click', logout); }
  function openSystemDialog() { const sync = state.overview?.sync || {}; closeDialog(); openDialog(`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message || 'Keine bekannte Störung mit Auswirkung.')}</p><ul class="cc-system-list"><li><strong>Prüffälle</strong><span>${allReviewCases().length} offen</span></li><li><strong>Arbeit</strong><span>${activeCases().filter(i => i.type === 'task').length} aktiv</span></li><li><strong>Blockiert</strong><span>${activeCases().filter(i => i.state === 'blocked').length}</span></li></ul><details class="cc-disclosure"><summary>Technische Details</summary><div>${escapeHtml(JSON.stringify(sync,null,2))}</div></details>`); }

  function askReason(item, action, title, label) { openDialog(`<h2>${escapeHtml(title)}</h2><label class="cc-field"><span>${escapeHtml(label)}</span><textarea id="cc-action-reason" required></textarea></label><button type="button" class="cc-button cc-button--primary" id="cc-action-confirm">Bestätigen</button>`); document.querySelector('#cc-action-confirm').addEventListener('click', () => { const reason = clean(document.querySelector('#cc-action-reason').value); if (!reason) return; closeDialog(); performAction(item, action, { reason }); }); }
  function askReject(item) { openDialog(`<h2>Ablehnen</h2><label class="cc-field"><span>Grund</span><select id="cc-reject-reason">${rejectReasons.map(r => `<option>${escapeHtml(r)}</option>`).join('')}<option>Bewusst nicht weiterverfolgen</option></select></label><button type="button" class="cc-button cc-button--danger" id="cc-reject-confirm">Bestätigen</button>`); document.querySelector('#cc-reject-confirm').addEventListener('click', () => { const reason = clean(document.querySelector('#cc-reject-reason').value); closeDialog(); performAction(item, 'reject', { reason }); }); }
  function askSnooze(item) { const d = new Date(); d.setDate(d.getDate() + 7); openDialog(`<h2>Zurückstellen</h2><label class="cc-field"><span>Wiedervorlage</span><input id="cc-snooze-date" type="date" value="${d.toISOString().slice(0,10)}"></label><button type="button" class="cc-button cc-button--primary" id="cc-snooze-confirm">Zurückstellen</button>`); document.querySelector('#cc-snooze-confirm').addEventListener('click', () => { const until = clean(document.querySelector('#cc-snooze-date').value); closeDialog(); performAction(item, 'snooze', { until }); }); }
  async function handleCaseAction(item, action) { if (!item) return; if (action === 'details') return openDialog(`<h2>${escapeHtml(item.title)}</h2><p>${escapeHtml(item.reason || '')}</p><dl><dt>Status</dt><dd>${escapeHtml(item.display_status)}</dd><dt>Quelle</dt><dd>${escapeHtml(item.source?.system || '')}</dd></dl>`); if (action === 'reject') return askReject(item); if (action === 'snooze') return askSnooze(item); if (action === 'wait') return askReason(item, 'wait', 'Auf Rückmeldung warten', 'Worauf wird gewartet?'); if (action === 'block') return askReason(item, 'block', 'Aufgabe blockieren', 'Was blockiert die Aufgabe?'); return performAction(item, action, {}); }
  async function performAction(item, action, payload) { setStatus('Aktion wird ausgeführt …'); try { await api('/api/control-center/action.php', { method:'POST', body:JSON.stringify({ case_id:item.id, action, payload }) }); await load(); setStatus('Gespeichert.'); } catch (e) { setStatus(e.message); } }

  els.authForm.addEventListener('submit', async e => { e.preventDefault(); state.password = clean(els.password.value); sessionStorage.setItem('be_cc_password', state.password); els.auth.hidden = true; els.content.hidden = false; await load(); });
  els.refresh.addEventListener('click', () => { state.managementLoaded = { events:false, activities:false }; state.managementWarnings = { events:[], activities:[] }; state.development = null; load(); });
  els.menuButton.addEventListener('click', openHeaderMenu);
  els.nav.forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));
  els.dialogClose.addEventListener('click', closeDialog);
  els.dialog.addEventListener('click', e => { if (e.target === els.dialog) closeDialog(); });
  if (state.password) { els.auth.hidden = true; els.content.hidden = false; load(); }
})();
