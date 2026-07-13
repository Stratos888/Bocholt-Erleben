(() => {
  'use strict';

  const state = {
    password: sessionStorage.getItem('be_cc_password') || '',
    view: 'overview',
    cases: [],
    overview: null,
    development: null,
    developmentMode: 'project',
    reviewGroup: 'all',
    reviewIndex: 0,
    managementType: 'events',
    managementQuery: '',
    managementItems: { events: [], activities: [] },
    managementLoaded: { events: false, activities: false },
    managementWarnings: { events: [], activities: [] },
  };

  const els = {
    auth: document.querySelector('#cc-auth'),
    authForm: document.querySelector('#cc-auth-form'),
    password: document.querySelector('#cc-password'),
    content: document.querySelector('#cc-content'),
    view: document.querySelector('#cc-view'),
    title: document.querySelector('#cc-title'),
    status: document.querySelector('#cc-status'),
    refresh: document.querySelector('#cc-refresh'),
    menuButton: document.querySelector('#cc-menu-button'),
    nav: [...document.querySelectorAll('.cc-nav [data-view]')],
    badgeReview: document.querySelector('#cc-badge-review'),
    badgeBacklog: document.querySelector('#cc-badge-backlog'),
    dialog: document.querySelector('#cc-dialog'),
    dialogBody: document.querySelector('#cc-dialog-body'),
    dialogClose: document.querySelector('#cc-dialog-close'),
  };

  const reviewLabels = {
    all: 'Alle',
    new_content: 'Neue Inhalte',
    quality: 'Qualität',
    provider: 'Anbieter',
    approvals: 'Freigaben',
    system: 'System',
    other: 'Sonstige',
  };
  const rejectReasons = ['Termin liegt in der Vergangenheit', 'Doppelt / bereits abgedeckt', 'Terminangaben unklar', 'Nicht öffentlich zugänglich', 'Quelle / Angaben reichen nicht', 'Nicht lokal genug', 'Redaktionell nicht passend'];
  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const formatDate = value => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium' }).format(date);
  };
  const setStatus = message => { els.status.textContent = message || ''; };

  async function api(path, options = {}) {
    const { timeoutMs = 20000, ...fetchOptions } = options;
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), timeoutMs);
    try {
      const response = await fetch(path, {
        ...fetchOptions,
        signal: controller.signal,
        headers: { 'Content-Type':'application/json', 'X-BE-Review-Password':state.password, ...(fetchOptions.headers || {}) },
        cache: 'no-store',
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        if (response.status === 401) logout(false);
        throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
      }
      return payload.data;
    } catch (error) {
      if (error?.name === 'AbortError') throw new Error('Die Datenquelle antwortet nicht rechtzeitig. Bitte erneut laden.');
      throw error;
    } finally {
      window.clearTimeout(timer);
    }
  }

  async function initializeSharedSession() {
    return api('/api/control-center/auth.php', {
      method: 'POST',
      body: JSON.stringify({ password: state.password }),
      timeoutMs: 12000,
    });
  }

  async function logout(callServer = true) {
    if (callServer) {
      try { await fetch('/api/control-center/auth.php', { method:'DELETE', cache:'no-store' }); } catch (_) {}
    }
    state.password = '';
    sessionStorage.removeItem('be_cc_password');
    els.content.hidden = true;
    els.auth.hidden = false;
    closeDialog();
  }

  function openDialog(html) { els.dialogBody.innerHTML = html; els.dialog.showModal(); }
  function closeDialog() { if (els.dialog.open) els.dialog.close(); els.dialogBody.innerHTML = ''; }
  function activeCases() { return state.cases.filter(item => !['done','rejected','parked','information'].includes(item.state)); }
  function backlogCases() { return activeCases().filter(item => item.case_kind === 'backlog_item'); }
  function reviewGroup(item) { return item.type === 'task' ? 'system' : (item.queue_group || 'other'); }
  function allReviewCases() { return activeCases().filter(item => item.type === 'intake' || (item.type === 'task' && item.case_kind !== 'backlog_item')); }
  function reviewCases() { return allReviewCases().filter(item => state.reviewGroup === 'all' || reviewGroup(item) === state.reviewGroup); }

  function setView(view) {
    state.view = view;
    const titles = { overview:'Übersicht', review:'Prüfen', backlog:'Backlog', manage:'Verwaltung', development:'Entwicklung' };
    els.title.textContent = titles[view] || 'Steuerzentrale';
    els.nav.forEach(button => button.classList.toggle('is-active', button.dataset.view === view));
    render();
    window.scrollTo({ top:0, behavior:'instant' });
  }

  function compactSummary(title, count, detail, view, label) {
    return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${count}</strong><p>${escapeHtml(detail)}</p></div>${view ? `<button class="cc-button cc-button--primary" data-go-view="${view}">${escapeHtml(label)}</button>` : ''}</article>`;
  }

  function renderOverview() {
    const reviews = allReviewCases();
    const backlog = backlogCases();
    const urgent = reviews.filter(item => item.bucket === 'now' || item.overdue || item.state === 'blocked');
    const groups = reviews.reduce((result, item) => {
      const key = reviewGroup(item);
      result[key] = (result[key] || 0) + 1;
      return result;
    }, {});
    const groupText = Object.entries(groups).map(([key,count]) => `${count} ${reviewLabels[key] || 'Sonstige'}`).join(' · ') || 'Keine offenen Prüfungen';
    const urgentText = urgent.length ? urgent.slice(0,2).map(item => item.title).join(' · ') : 'Aktuell ist nichts dringend.';
    const development = state.development;
    const processAttention = development?.automation?.process_health?.status === 'attention';
    const developmentText = development
      ? `${development.content_quality?.coverage_percent ?? 0} % Eventbasis · ${processAttention ? 'Prozessprüfung nötig' : 'Prozesse ohne bekannten Fehler'}`
      : 'Projektstatus wird geladen.';

    els.view.innerHTML = `<section class="cc-overview-grid">
      ${compactSummary('Jetzt erforderlich', urgent.length, urgentText, urgent.length ? 'review' : '', 'Jetzt prüfen')}
      ${compactSummary('Zu prüfen', reviews.length, groupText, reviews.length ? 'review' : '', 'Prüfen öffnen')}
      ${compactSummary('Backlog', backlog.length, backlog.length ? 'Offene geplante Verbesserungen und Vorhaben' : 'Kein offener Backlogpunkt', 'backlog', 'Backlog öffnen')}
      <article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Entwicklung</span><h2>${escapeHtml(development?.summary?.label || 'Daten werden geladen')}</h2><p>${escapeHtml(developmentText)}</p></div><button class="cc-button cc-button--secondary" data-go-view="development">Entwicklung öffnen</button></article>
    </section>`;
    bindViewLinks();
  }

  function renderReviewDetail(item, index, total) {
    const context = item.decision_context || {};
    const primary = item.primary_action;
    const secondary = (item.secondary_actions || []).filter(action => action.key !== 'details');
    const problem = clean(context.issue_text || item.reason);
    const recommended = clean(context.recommended_action || item.next_action);
    const description = clean(context.current_description || context.description);
    const links = (item.source_links || []).map(link => `<a class="cc-button cc-button--secondary" href="${escapeHtml(link.url)}" target="_blank" rel="noopener">${escapeHtml(link.label)}</a>`).join('');
    return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}"><header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(reviewLabels[reviewGroup(item)] || 'Prüfung')} · ${index + 1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><span class="cc-pill">${escapeHtml(item.display_status)}</span></header>
      ${problem ? `<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>` : ''}
      ${recommended ? `<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>` : ''}
      ${description ? `<details class="cc-disclosure"><summary>Aktuellen Inhalt anzeigen</summary><div>${escapeHtml(description)}</div></details>` : ''}
      <div class="cc-action-primary">${primary ? `<button class="cc-button cc-button--primary cc-button--large" data-review-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>` : `<div class="cc-empty">${escapeHtml(item.waiting_for || 'Aktuell keine Aktion erforderlich.')}</div>`}</div>
      <div class="cc-actions cc-actions--secondary">${secondary.map(action => `<button class="cc-button ${action.destructive ? 'cc-button--danger' : 'cc-button--secondary'}" data-review-action="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}${links}</div>
      <footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-review-move="-1" ${index === 0 ? 'disabled' : ''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-review-move="1" ${index >= total - 1 ? 'disabled' : ''}>Weiter ›</button></footer></article>`;
  }

  function reviewGroupCount(key) {
    return key === 'all' ? allReviewCases().length : allReviewCases().filter(item => reviewGroup(item) === key).length;
  }

  function visibleReviewGroups() {
    return Object.entries(reviewLabels).filter(([key]) => key === 'all' || reviewGroupCount(key) > 0);
  }

  function renderReview() {
    const availableGroups = visibleReviewGroups();
    if (!availableGroups.some(([key]) => key === state.reviewGroup)) state.reviewGroup = 'all';
    const items = reviewCases();
    if (state.reviewIndex >= items.length) state.reviewIndex = Math.max(0, items.length - 1);
    const active = items[state.reviewIndex];
    const queue = items.map((item,index) => `<button class="cc-queue-item ${index === state.reviewIndex ? 'is-active' : ''}" data-review-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(reviewLabels[reviewGroup(item)] || item.display_status)}</small></button>`).join('');
    const pills = availableGroups.map(([key,label]) => `<button class="cc-filter ${state.reviewGroup === key ? 'is-active' : ''}" data-review-group="${key}">${escapeHtml(label)} (${reviewGroupCount(key)})</button>`).join('');
    const options = availableGroups.map(([key,label]) => `<option value="${key}" ${state.reviewGroup === key ? 'selected' : ''}>${escapeHtml(label)} (${reviewGroupCount(key)})</option>`).join('');
    els.view.innerHTML = `<div class="cc-review-controls"><div class="cc-filter-row cc-review-pills">${pills}</div><label class="cc-work-filter cc-review-select"><span>Prüfbereich</span><select id="cc-review-select">${options}</select></label></div>${items.length ? `<div class="cc-work-layout"><aside class="cc-queue">${queue}</aside><main>${renderReviewDetail(active,state.reviewIndex,items.length)}</main></div>` : '<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu prüfen.</div>'}`;
    bindReview();
  }

  function backlogActions(item) {
    const actions = [];
    if (item.primary_action && !['convert_to_task','details'].includes(item.primary_action.key)) actions.push(item.primary_action);
    for (const action of item.secondary_actions || []) if (!['convert_to_task','details'].includes(action.key)) actions.push(action);
    const seen = new Set();
    return actions.filter(action => action?.key && !seen.has(action.key) && seen.add(action.key));
  }

  function backlogRow(item) {
    const summary = clean(item.next_action || item.reason).slice(0,150);
    const actions = backlogActions(item);
    return `<details class="cc-labor-row" data-case-id="${escapeHtml(item.id)}"><summary><span><b>${escapeHtml(item.title)}</b><small>Backlog${item.priority ? ` · ${escapeHtml(item.priority)}` : ''}</small></span><span class="cc-labor-summary">${escapeHtml(summary)}</span></summary><div class="cc-labor-detail">${item.reason ? `<p>${escapeHtml(item.reason)}</p>` : ''}${item.next_action && item.next_action !== item.reason ? `<p><strong>Nächster Schritt:</strong> ${escapeHtml(item.next_action)}</p>` : ''}${actions.length ? `<div class="cc-actions">${actions.map(action => `<button class="cc-button ${action.destructive ? 'cc-button--danger' : action.key === 'complete' ? 'cc-button--primary' : 'cc-button--secondary'}" data-backlog-action="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}</div>` : ''}</div></details>`;
  }

  function renderBacklog() {
    const items = backlogCases();
    els.view.innerHTML = `<div class="cc-toolbar cc-toolbar--backlog"><div><span class="cc-kicker">Geplante Entwicklung</span><strong>${items.length} offen</strong></div><button class="cc-button cc-button--primary" id="cc-new-backlog">+ Neu</button></div><div class="cc-labor-list">${items.length ? items.map(backlogRow).join('') : '<div class="cc-empty cc-empty--large">Kein offener Backlogpunkt.</div>'}</div>`;
    bindBacklog();
  }

  async function loadManagement(type) {
    if (state.managementLoaded[type]) return;
    state.managementWarnings[type] = [];
    if (type === 'activities') {
      const result = await api('/api/control-center/content.php?type=activities', { timeoutMs:15000 });
      state.managementItems.activities = result.items || [];
      state.managementLoaded.activities = true;
      return;
    }
    const requests = [
      ['Redaktionelle Events','/api/control-center/content.php?type=events&source=sheet'],
      ['Anbieter-Events','/api/control-center/content.php?type=events&source=submissions'],
    ];
    const results = await Promise.allSettled(requests.map(([,path]) => api(path, { timeoutMs:15000 })));
    const items = [];
    results.forEach((result,index) => {
      if (result.status === 'fulfilled') items.push(...(result.value.items || []));
      else state.managementWarnings.events.push(`${requests[index][0]} konnten nicht geladen werden: ${result.reason?.message || 'unbekannter Fehler'}`);
    });
    if (results.every(result => result.status === 'rejected')) throw new Error('Keine Veranstaltungsquelle konnte geladen werden.');
    state.managementItems.events = items.sort((a,b) => String(a.date || '').localeCompare(String(b.date || '')) || String(a.title || '').localeCompare(String(b.title || ''),'de'));
    state.managementLoaded.events = true;
  }

  function managementRow(item, type) {
    const detail = [item.date, item.location, item.city].filter(Boolean).join(' · ');
    const source = type === 'events' ? (item.source_label || 'Event') : 'Aktivität';
    return `<article class="cc-manage-row"><div><div class="cc-manage-meta"><span>${escapeHtml(source)}</span><span>${escapeHtml(item.status || 'veröffentlicht')}</span></div><h3>${escapeHtml(item.title)}</h3>${detail ? `<p>${escapeHtml(detail)}</p>` : ''}</div><div class="cc-actions"><button class="cc-button cc-button--primary" data-manage-edit="${escapeHtml(item.id)}">Bearbeiten</button><a class="cc-button cc-button--secondary" href="${escapeHtml(item.public_url)}" target="_blank" rel="noopener">Vorschau</a></div></article>`;
  }

  async function renderManage() {
    els.view.innerHTML = '<div class="cc-empty">Inhalte werden geladen …</div>';
    try {
      await loadManagement(state.managementType);
      const source = state.managementItems[state.managementType] || [];
      const query = state.managementQuery.toLowerCase();
      const items = source.filter(item => !query || clean(item.title).toLowerCase().includes(query)).slice(0,100);
      const warning = state.managementWarnings[state.managementType]?.length ? `<div class="cc-notice cc-notice--attention">${state.managementWarnings[state.managementType].map(escapeHtml).join('<br>')}</div>` : '';
      els.view.innerHTML = `<div class="cc-toolbar cc-toolbar--manage"><div class="cc-segment"><button class="${state.managementType === 'events' ? 'is-active' : ''}" data-manage-type="events">Veranstaltungen</button><button class="${state.managementType === 'activities' ? 'is-active' : ''}" data-manage-type="activities">Aktivitäten</button></div><label class="cc-search"><span class="sr-only">Suchen</span><input id="cc-manage-search" type="search" value="${escapeHtml(state.managementQuery)}" placeholder="Suchen …"></label></div>${warning}<div id="cc-manage-results" class="cc-manage-list">${items.map(item => managementRow(item,state.managementType)).join('') || '<div class="cc-empty cc-empty--large">Keine passenden Inhalte gefunden.</div>'}</div>`;
      bindManage();
    } catch (error) {
      els.view.innerHTML = `<div class="cc-empty cc-empty--large"><strong>Verwaltung konnte nicht geladen werden.</strong><br>${escapeHtml(error.message)}</div>`;
    }
  }

  function metricCard(title, value, text, tone = '') {
    return `<article class="cc-metric ${tone ? `cc-metric--${tone}` : ''}"><span>${escapeHtml(title)}</span><strong>${escapeHtml(value)}</strong><p>${escapeHtml(text)}</p></article>`;
  }

  function developmentSwitcher() {
    return `<div class="cc-segment cc-development-switch"><button class="${state.developmentMode === 'project' ? 'is-active' : ''}" data-development-mode="project">Projektstatus</button><button class="${state.developmentMode === 'seo' ? 'is-active' : ''}" data-development-mode="seo">SEO & Reichweite</button></div>`;
  }

  function renderProjectStatus() {
    const data = state.development;
    const quality = data.content_quality || {};
    const automation = data.automation || {};
    const process = automation.process_health || {};
    const processProblem = process.status === 'attention' || process.status === 'unknown';
    els.view.innerHTML = `${developmentSwitcher()}<section class="cc-development-head"><span class="cc-kicker">Gesamtprojekt</span><h2>${escapeHtml(data.summary?.label || 'Entwicklungsstand')}</h2><p>Datenstand ${escapeHtml(formatDate(data.generated_at))}</p></section><section class="cc-metric-grid cc-metric-grid--compact">${metricCard('Content',`${quality.coverage_percent ?? 0} %`,`${quality.open_quality_reviews || 0} offene Qualitätsprüfungen`,quality.open_quality_reviews > 0 ? 'attention' : 'good')}${metricCard('Prozesse',processProblem ? 'Prüfen' : 'Okay',process.message || 'Kein bekannter Prozessfehler',processProblem ? 'attention' : 'good')}${metricCard('Backlog',String(backlogCases().length),'Offene geplante Verbesserungen',backlogCases().length > 0 ? '' : 'good')}</section>`;
    bindDevelopment();
  }

  function renderSeoIntegration() {
    const data = state.development;
    const automation = data.automation || {};
    const processItems = automation.process_health?.items || [];
    const growth = processItems.find(item => item.key === 'growth_intelligence');
    const search = automation.search || {};
    const funnel = data.seo?.funnel || {};
    const growthStatus = growth?.status === 'ok' ? 'Aktuell' : growth?.status === 'attention' ? 'Prüfen' : 'Unbekannt';
    const growthText = growth?.message || 'Noch kein aktueller Growth-/SEO-Lauf nachgewiesen.';
    const selected = search.selected_candidates ?? '–';
    const prevented = search.prevented_candidates ?? '–';
    const dataState = funnel.available ? 'Betriebsdaten vorhanden' : 'Noch keine belastbaren Growth-Daten';
    els.view.innerHTML = `${developmentSwitcher()}<section class="cc-seo-operations"><div><span class="cc-kicker">Growth-/SEO-Prozess</span><h2>${escapeHtml(growthStatus)}</h2><p>${escapeHtml(growthText)}</p></div><dl><div><dt>Datenbasis</dt><dd>${escapeHtml(dataState)}</dd></div><div><dt>Ausgewählte Chancen</dt><dd>${escapeHtml(selected)}</dd></div><div><dt>Automatisch verhindert</dt><dd>${escapeHtml(prevented)}</dd></div><div><dt>Backlog</dt><dd>${backlogCases().length}</dd></div></dl></section><div class="cc-seo-frame-wrap"><iframe class="cc-seo-frame" src="/intern/seo-dashboard/" title="SEO- und Mehrwert-Dashboard" loading="eager"></iframe></div>`;
    bindDevelopment();
  }

  async function renderDevelopment() {
    if (!state.development) {
      els.view.innerHTML = '<div class="cc-empty">Entwicklungsdaten werden geladen …</div>';
      try { state.development = await api('/api/control-center/development.php'); }
      catch (error) { els.view.innerHTML = `<div class="cc-empty cc-empty--large">${escapeHtml(error.message)}</div>`; return; }
    }
    if (state.developmentMode === 'seo') renderSeoIntegration();
    else renderProjectStatus();
  }

  function render() {
    if (state.view === 'overview') renderOverview();
    if (state.view === 'review') renderReview();
    if (state.view === 'backlog') renderBacklog();
    if (state.view === 'manage') renderManage();
    if (state.view === 'development') renderDevelopment();
  }

  function updateBadges() {
    const reviews = allReviewCases().length;
    const backlog = backlogCases().length;
    [[els.badgeReview,reviews],[els.badgeBacklog,backlog]].forEach(([element,count]) => {
      element.hidden = count === 0;
      element.textContent = count > 99 ? '99+' : String(count);
    });
  }

  async function load() {
    setStatus('Daten werden geladen …');
    try {
      const [overview,cases,development] = await Promise.all([
        api('/api/control-center/overview.php'),
        api('/api/control-center/cases.php'),
        api('/api/control-center/development.php'),
      ]);
      state.overview = overview;
      state.cases = cases.items || [];
      state.development = development;
      updateBadges();
      render();
      setStatus('');
    } catch (error) {
      setStatus(error.message);
    }
  }

  function bindViewLinks() { document.querySelectorAll('[data-go-view]').forEach(button => button.addEventListener('click', () => setView(button.dataset.goView))); }
  function bindReview() {
    document.querySelectorAll('[data-review-group]').forEach(button => button.addEventListener('click', () => { state.reviewGroup = button.dataset.reviewGroup; state.reviewIndex = 0; renderReview(); }));
    document.querySelector('#cc-review-select')?.addEventListener('change', event => { state.reviewGroup = event.target.value; state.reviewIndex = 0; renderReview(); });
    document.querySelectorAll('[data-review-index]').forEach(button => button.addEventListener('click', () => { state.reviewIndex = Number(button.dataset.reviewIndex); renderReview(); }));
    document.querySelectorAll('[data-review-move]').forEach(button => button.addEventListener('click', () => { state.reviewIndex += Number(button.dataset.reviewMove); renderReview(); }));
    document.querySelectorAll('[data-review-action]').forEach(button => button.addEventListener('click', () => handleCaseAction(reviewCases()[state.reviewIndex],button.dataset.reviewAction)));
  }
  function bindBacklog() {
    document.querySelector('#cc-new-backlog')?.addEventListener('click', openBacklogDialog);
    document.querySelectorAll('[data-backlog-action]').forEach(button => button.addEventListener('click', () => {
      const item = state.cases.find(candidate => candidate.id === button.closest('[data-case-id]')?.dataset.caseId);
      handleCaseAction(item,button.dataset.backlogAction);
    }));
  }
  function bindManage() {
    document.querySelectorAll('[data-manage-type]').forEach(button => button.addEventListener('click', () => { state.managementType = button.dataset.manageType; state.managementQuery = ''; renderManage(); }));
    document.querySelector('#cc-manage-search')?.addEventListener('input', event => {
      state.managementQuery = event.target.value;
      const items = (state.managementItems[state.managementType] || []).filter(item => !state.managementQuery || clean(item.title).toLowerCase().includes(state.managementQuery.toLowerCase())).slice(0,100);
      document.querySelector('#cc-manage-results').innerHTML = items.map(item => managementRow(item,state.managementType)).join('') || '<div class="cc-empty">Keine passenden Inhalte.</div>';
      bindManageEdit();
    });
    bindManageEdit();
  }
  function bindManageEdit() { document.querySelectorAll('[data-manage-edit]').forEach(button => button.addEventListener('click', () => openContentEditor(button.dataset.manageEdit), { once:true })); }
  function bindDevelopment() { document.querySelectorAll('[data-development-mode]').forEach(button => button.addEventListener('click', () => { state.developmentMode = button.dataset.developmentMode; renderDevelopment(); })); }

  function openBacklogDialog() {
    openDialog(`<h2>Backlogpunkt anlegen</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-backlog-title" required></label><label class="cc-field"><span>Beschreibung / Nutzen</span><textarea id="cc-backlog-note"></textarea></label><label class="cc-field"><span>Priorität</span><select id="cc-backlog-priority"><option value="hoch">Hoch</option><option value="mittel" selected>Mittel</option><option value="niedrig">Niedrig</option></select></label><button type="button" class="cc-button cc-button--primary" id="cc-backlog-save">Speichern</button></div>`);
    document.querySelector('#cc-backlog-save')?.addEventListener('click', async () => {
      const title = clean(document.querySelector('#cc-backlog-title')?.value);
      if (!title) return setStatus('Titel fehlt.');
      await api('/api/growth-backlog/create.php', { method:'POST', body:JSON.stringify({ title, note:clean(document.querySelector('#cc-backlog-note')?.value), priority:clean(document.querySelector('#cc-backlog-priority')?.value) }) });
      closeDialog();
      await load();
      setView('backlog');
    });
  }

  async function openContentEditor(id) {
    const type = state.managementType;
    const data = await api(`/api/control-center/content.php?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`, { timeoutMs:15000 });
    const item = data.item;
    if (type === 'activities' && item.editable === false) {
      openDialog(`<h2>${escapeHtml(item.title)}</h2><p>${escapeHtml(item.edit_notice)}</p><a class="cc-button cc-button--secondary" href="${escapeHtml(item.public_url)}" target="_blank" rel="noopener">Vorschau öffnen</a>`);
      return;
    }
    const field = (fieldId,label,value,inputType='text') => `<label class="cc-field"><span>${label}</span><input id="${fieldId}" type="${inputType}" value="${escapeHtml(value || '')}"></label>`;
    openDialog(`<h2>Veranstaltung bearbeiten</h2><div class="cc-stack">${field('ce-title','Titel',item.title)}${field('ce-date','Startdatum',item.date,'date')}${field('ce-end','Enddatum',item.end_date,'date')}${field('ce-time','Uhrzeit',item.time)}${field('ce-location','Ort',item.location)}${field('ce-city','Stadt',item.city)}${field('ce-category','Kategorie',item.category)}${field('ce-source','Offizielle Quelle',item.source_url,'url')}${field('ce-ticket','Ticketlink',item.ticket_url,'url')}<label class="cc-field"><span>Beschreibung</span><textarea id="ce-description">${escapeHtml(item.description || '')}</textarea></label><button type="button" class="cc-button cc-button--primary" id="ce-save" data-event-id="${escapeHtml(item.id)}">Speichern und Aktualisierung starten</button></div>`);
  }

  function openHeaderMenu() {
    openDialog(`<h2>Menü</h2><div class="cc-link-list"><a class="cc-link-card" href="/fuer-veranstalter/dashboard/"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Prozesse und Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-deploy"><strong>Deployment starten</strong><span>Datenbestand neu veröffentlichen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div>`);
    document.querySelector('#cc-system')?.addEventListener('click',openSystemDialog);
    document.querySelector('#cc-deploy')?.addEventListener('click',async () => { closeDialog(); setStatus('Deployment wird gestartet …'); try { const result = await api('/api/control-center/deploy.php',{ method:'POST',body:'{}' }); setStatus(result.message); } catch (error) { setStatus(error.message); } });
    document.querySelector('#cc-logout')?.addEventListener('click',() => logout(true));
  }

  function openSystemDialog() {
    const sync = state.overview?.sync || {};
    const systemCases = allReviewCases().filter(item => reviewGroup(item) === 'system');
    closeDialog();
    openDialog(`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message || 'Keine bekannte Störung mit Auswirkung.')}</p><ul class="cc-system-list"><li><strong>Prüffälle</strong><span>${allReviewCases().length} offen</span></li><li><strong>Systemfälle</strong><span>${systemCases.length} offen</span></li><li><strong>Backlog</strong><span>${backlogCases().length} offen</span></li></ul><details class="cc-disclosure"><summary>Technische Details</summary><div>${escapeHtml(JSON.stringify(sync,null,2))}</div></details>`);
  }

  function askReason(item, action, title, label) {
    openDialog(`<h2>${escapeHtml(title)}</h2><label class="cc-field"><span>${escapeHtml(label)}</span><textarea id="cc-action-reason" required></textarea></label><button type="button" class="cc-button cc-button--primary" id="cc-action-confirm">Bestätigen</button>`);
    document.querySelector('#cc-action-confirm')?.addEventListener('click',() => { const reason = clean(document.querySelector('#cc-action-reason')?.value); if (!reason) return; closeDialog(); performAction(item,action,{ reason }); });
  }
  function askReject(item) {
    openDialog(`<h2>Ablehnen</h2><label class="cc-field"><span>Grund</span><select id="cc-reject-reason">${rejectReasons.map(reason => `<option>${escapeHtml(reason)}</option>`).join('')}<option>Bewusst nicht weiterverfolgen</option></select></label><button type="button" class="cc-button cc-button--danger" id="cc-reject-confirm">Bestätigen</button>`);
    document.querySelector('#cc-reject-confirm')?.addEventListener('click',() => { const reason = clean(document.querySelector('#cc-reject-reason')?.value); closeDialog(); performAction(item,'reject',{ reason }); });
  }
  function askSnooze(item) {
    const date = new Date(); date.setDate(date.getDate() + 7);
    openDialog(`<h2>Zurückstellen</h2><label class="cc-field"><span>Wiedervorlage</span><input id="cc-snooze-date" type="date" value="${date.toISOString().slice(0,10)}"></label><button type="button" class="cc-button cc-button--primary" id="cc-snooze-confirm">Zurückstellen</button>`);
    document.querySelector('#cc-snooze-confirm')?.addEventListener('click',() => { const until = clean(document.querySelector('#cc-snooze-date')?.value); closeDialog(); performAction(item,'snooze',{ until }); });
  }
  async function handleCaseAction(item, action) {
    if (!item) return;
    if (action === 'reject') return askReject(item);
    if (action === 'snooze') return askSnooze(item);
    if (action === 'wait') return askReason(item,'wait','Auf Rückmeldung warten','Worauf wird gewartet?');
    if (action === 'block') return askReason(item,'block','Vorgang blockieren','Was blockiert den Vorgang?');
    return performAction(item,action,{});
  }
  async function performAction(item, action, payload) {
    setStatus('Aktion wird ausgeführt …');
    try {
      await api('/api/control-center/action.php',{ method:'POST',body:JSON.stringify({ case_id:item.id,action,payload }) });
      await load();
      setStatus('Gespeichert.');
    } catch (error) { setStatus(error.message); }
  }

  els.authForm.addEventListener('submit', async event => {
    event.preventDefault();
    state.password = clean(els.password.value);
    setStatus('Zugang wird geprüft …');
    try {
      await initializeSharedSession();
      sessionStorage.setItem('be_cc_password',state.password);
      els.auth.hidden = true;
      els.content.hidden = false;
      await load();
    } catch (error) {
      state.password = '';
      setStatus(error.message);
    }
  });
  els.refresh.addEventListener('click',() => { state.managementLoaded = { events:false,activities:false }; state.managementWarnings = { events:[],activities:[] }; state.development = null; load(); });
  els.menuButton.addEventListener('click',openHeaderMenu);
  els.nav.forEach(button => button.addEventListener('click',() => setView(button.dataset.view)));
  els.dialogClose.addEventListener('click',closeDialog);
  els.dialog.addEventListener('click',event => { if (event.target === els.dialog) closeDialog(); });

  if (state.password) {
    els.auth.hidden = true;
    els.content.hidden = false;
    initializeSharedSession().then(load).catch(() => logout(false));
  }
})();
