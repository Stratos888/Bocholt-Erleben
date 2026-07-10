(() => {
  'use strict';

  const state = {
    password: sessionStorage.getItem('be_cc_password') || '',
    view: 'overview',
    cases: [],
    overview: null,
    workGroup: 'all',
    workIndex: 0,
    taskMode: 'active',
    managementType: 'events',
    managementQuery: '',
    managementItems: { events: [], activities: [] },
    managementLoaded: false,
  };

  const els = {
    auth: document.querySelector('#cc-auth'), authForm: document.querySelector('#cc-auth-form'), password: document.querySelector('#cc-password'),
    content: document.querySelector('#cc-content'), view: document.querySelector('#cc-view'), title: document.querySelector('#cc-title'),
    status: document.querySelector('#cc-status'), refresh: document.querySelector('#cc-refresh'), nav: [...document.querySelectorAll('.cc-nav [data-view]')],
    badgeWork: document.querySelector('#cc-badge-work'), badgeTasks: document.querySelector('#cc-badge-tasks'),
    dialog: document.querySelector('#cc-dialog'), dialogBody: document.querySelector('#cc-dialog-body'),
  };

  const rejectReasons = ['Termin liegt in der Vergangenheit','Doppelt / bereits abgedeckt','Terminangaben unklar','Nicht öffentlich zugänglich','Quelle / Angaben reichen nicht','Nicht lokal genug','Redaktionell nicht passend'];
  const groupLabels = {all:'Alle',new_content:'Neue Inhalte',quality:'Qualität',provider:'Anbieter',approvals:'Freigaben',other:'Sonstige'};

  const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const clean = value => String(value ?? '').trim();
  const formatDate = value => { if (!value) return ''; const date = new Date(String(value).replace(' ', 'T')); return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('de-DE',{dateStyle:'medium'}).format(date); };
  const setStatus = message => { els.status.textContent = message || ''; };

  async function api(path, options = {}) {
    const response = await fetch(path, {
      ...options,
      headers: {'Content-Type':'application/json','X-BE-Review-Password':state.password,...(options.headers || {})},
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 401) logout();
      throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    }
    return payload.data;
  }

  function logout() {
    state.password = '';
    sessionStorage.removeItem('be_cc_password');
    els.content.hidden = true;
    els.auth.hidden = false;
  }

  function activeCases() { return state.cases.filter(item => !['done','rejected','parked','information'].includes(item.state)); }
  function workCases() { return activeCases().filter(item => item.type === 'intake' && (state.workGroup === 'all' || item.queue_group === state.workGroup)); }
  function taskCases() { return state.cases.filter(item => item.type === 'task' && (state.taskMode === 'archive' ? ['done','rejected','parked'].includes(item.state) : !['done','rejected','parked'].includes(item.state))); }

  function setView(view) {
    state.view = view;
    const titles = {overview:'Übersicht',work:'Bearbeiten',tasks:'Aufgaben',manage:'Verwaltung',more:'Mehr'};
    els.title.textContent = titles[view] || 'Steuerzentrale';
    els.nav.forEach(button => button.classList.toggle('is-active', button.dataset.view === view));
    render();
    window.scrollTo({top:0,behavior:'instant'});
  }

  function compactSummary(title, count, detail, actionView, actionLabel) {
    return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${count}</strong><p>${escapeHtml(detail)}</p></div>${actionView ? `<button class="cc-button cc-button--primary" data-go-view="${actionView}">${escapeHtml(actionLabel)}</button>` : ''}</article>`;
  }

  function renderOverview() {
    const cases = activeCases();
    const urgent = cases.filter(item => item.bucket === 'now');
    const work = cases.filter(item => item.type === 'intake');
    const tasks = cases.filter(item => item.type === 'task');
    const waiting = tasks.filter(item => item.state === 'waiting');
    const groups = work.reduce((acc,item) => { const key=item.queue_group || 'other'; acc[key]=(acc[key]||0)+1; return acc; },{});
    const groupText = Object.entries(groups).map(([key,count]) => `${count} ${groupLabels[key] || 'Sonstige'}`).join(' · ') || 'Keine offenen Entscheidungen';
    const urgentText = urgent.length ? urgent.slice(0,2).map(item => item.title).join(' · ') : 'Aktuell ist nichts dringend.';

    els.view.innerHTML = `<section class="cc-overview-grid">
      ${compactSummary('Jetzt erforderlich', urgent.length, urgentText, urgent.length ? 'work' : '', 'Jetzt bearbeiten')}
      ${compactSummary('Zu bearbeiten', work.length, groupText, work.length ? 'work' : '', 'Bearbeiten öffnen')}
      ${compactSummary('Aufgaben', tasks.length, waiting.length ? `${waiting.length} warten auf Rückmeldung` : 'Keine wartenden Aufgaben', tasks.length ? 'tasks' : '', 'Aufgaben öffnen')}
      <article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Systemzustand</span><h2>Alles ruhig</h2><p>${escapeHtml(state.overview?.system?.message || 'Keine bekannte Störung mit Auswirkung.')}</p></div></article>
    </section>`;
    bindViewLinks();
  }

  function contextRows(item) {
    const c = item.decision_context || {};
    const rows = [];
    const add = (label,value) => { if (clean(value)) rows.push([label,clean(value)]); };
    add('Datum', c.date); add('Enddatum', c.end_date); add('Uhrzeit', c.time); add('Ort', [c.location,c.city].filter(Boolean).join(' · ')); add('Anbieter', c.organization);
    return rows;
  }

  function renderWorkDetail(item, index, total) {
    const links = (item.source_links || []).map(link => `<a class="cc-button cc-button--secondary" href="${escapeHtml(link.url)}" target="_blank" rel="noopener">${escapeHtml(link.label)}</a>`).join('');
    const rows = contextRows(item).map(([label,value]) => `<div class="cc-detail-row"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value)}</strong></div>`).join('');
    const context = item.decision_context || {};
    const problem = clean(context.issue_text || item.reason);
    const recommended = clean(context.recommended_action || item.next_action);
    const description = clean(context.current_description || context.description);
    const primary = item.primary_action;
    const secondary = item.secondary_actions || [];

    return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}">
      <header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(groupLabels[item.queue_group] || 'Vorgang')} · ${index + 1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><span class="cc-pill">${escapeHtml(item.display_status)}</span></header>
      ${problem ? `<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>` : ''}
      ${recommended ? `<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>` : ''}
      ${description ? `<details class="cc-disclosure"><summary>Aktuellen Inhalt anzeigen</summary><div>${escapeHtml(description)}</div></details>` : ''}
      ${rows ? `<section class="cc-detail-grid">${rows}</section>` : ''}
      <div class="cc-action-primary">${primary ? `<button class="cc-button cc-button--primary cc-button--large" data-case-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>` : `<div class="cc-empty">${escapeHtml(item.waiting_for || 'Aktuell keine Aktion erforderlich.')}</div>`}</div>
      <div class="cc-actions cc-actions--secondary">${secondary.map(action => `<button class="cc-button ${action.destructive ? 'cc-button--danger' : 'cc-button--secondary'}" data-case-action="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}${links}<button class="cc-button cc-button--ghost" data-case-action="details">Technische Details</button></div>
      <footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-work-move="-1" ${index===0?'disabled':''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-work-move="1" ${index>=total-1?'disabled':''}>Weiter ›</button></footer>
    </article>`;
  }

  function renderWork() {
    const items = workCases();
    if (state.workIndex >= items.length) state.workIndex = Math.max(0, items.length - 1);
    const active = items[state.workIndex];
    const queue = items.map((item,index) => `<button class="cc-queue-item ${index===state.workIndex?'is-active':''}" data-work-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(groupLabels[item.queue_group] || item.display_status)}</small></button>`).join('');
    els.view.innerHTML = `<div class="cc-filter-row">${Object.entries(groupLabels).map(([key,label]) => `<button class="cc-filter ${state.workGroup===key?'is-active':''}" data-work-group="${key}">${escapeHtml(label)}${key==='all'?` (${activeCases().filter(i=>i.type==='intake').length})`:''}</button>`).join('')}</div>
      ${items.length ? `<div class="cc-work-layout"><aside class="cc-queue" aria-label="Vorgänge">${queue}</aside><main>${renderWorkDetail(active,state.workIndex,items.length)}</main></div>` : `<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu bearbeiten.</div>`}`;
    bindWork();
  }

  function taskCard(item) {
    const due = item.due_at ? `${item.overdue ? 'Überfällig' : 'Fällig'} ${formatDate(item.due_at)}` : '';
    return `<article class="cc-task-card" data-case-id="${escapeHtml(item.id)}"><div><span class="cc-pill">${escapeHtml(item.display_status)}</span><h3>${escapeHtml(item.title)}</h3>${item.next_action?`<p>${escapeHtml(item.next_action)}</p>`:''}${due?`<small>${escapeHtml(due)}</small>`:''}${item.waiting_for?`<small>Wartet auf: ${escapeHtml(item.waiting_for)}</small>`:''}</div><div class="cc-actions">${item.primary_action?`<button class="cc-button cc-button--primary" data-task-action="${item.primary_action.key}">${escapeHtml(item.primary_action.label)}</button>`:''}<button class="cc-button cc-button--secondary" data-task-action="details">Details</button></div></article>`;
  }

  function renderTasks() {
    const items = taskCases();
    els.view.innerHTML = `<div class="cc-toolbar"><div class="cc-segment"><button class="${state.taskMode==='active'?'is-active':''}" data-task-mode="active">Aktiv</button><button class="${state.taskMode==='archive'?'is-active':''}" data-task-mode="archive">Archiv</button></div><button class="cc-button cc-button--primary" id="cc-new-task">+ Aufgabe</button></div><div class="cc-task-list">${items.length?items.map(taskCard).join(''):`<div class="cc-empty cc-empty--large">${state.taskMode==='active'?'Keine offenen Aufgaben.':'Noch keine archivierten Aufgaben.'}</div>`}</div>`;
    bindTasks();
  }

  function normalizeCollection(payload) { if (Array.isArray(payload)) return payload; if (Array.isArray(payload?.items)) return payload.items; if (Array.isArray(payload?.events)) return payload.events; if (Array.isArray(payload?.offers)) return payload.offers; return []; }
  async function loadManagement() {
    if (state.managementLoaded) return;
    const [events,activities] = await Promise.all([
      fetch('/data/events.json',{cache:'no-store'}).then(r=>r.ok?r.json():[]).catch(()=>[]),
      fetch('/data/offers.json',{cache:'no-store'}).then(r=>r.ok?r.json():[]).catch(()=>[]),
    ]);
    state.managementItems.events = normalizeCollection(events);
    state.managementItems.activities = normalizeCollection(activities);
    state.managementLoaded = true;
  }

  function managementItem(item,type) {
    const title = clean(item.title || item.name || item.offer_name || 'Ohne Titel');
    const id = clean(item.id || item.event_id || item.slug || title);
    const date = clean(item.date || item.startDate || item.start_date || item.opening_status || '');
    const publicUrl = type==='events' ? (item.url || `/events/${encodeURIComponent(id)}/`) : (item.url || item.detail_url || '/aktivitaeten/');
    const linked = activeCases().filter(c => c.object?.id && String(c.object.id)===String(id)).length;
    return `<article class="cc-manage-row"><div><span class="cc-kicker">${type==='events'?'Veranstaltung':'Aktivität'}${linked?` · ${linked} offene Vorgänge`:''}</span><h3>${escapeHtml(title)}</h3>${date?`<p>${escapeHtml(date)}</p>`:''}</div><div class="cc-actions"><a class="cc-button cc-button--secondary" href="${escapeHtml(publicUrl)}" target="_blank" rel="noopener">Öffnen</a><button class="cc-button cc-button--primary" data-create-object-task="${escapeHtml(id)}" data-object-title="${escapeHtml(title)}" data-object-type="${type==='events'?'event':'activity'}">Aufgabe anlegen</button></div></article>`;
  }

  async function renderManage() {
    els.view.innerHTML = '<div class="cc-empty">Inhalte werden geladen …</div>';
    await loadManagement();
    const source = state.managementItems[state.managementType] || [];
    const query = state.managementQuery.toLowerCase();
    const items = source.filter(item => !query || clean(item.title || item.name || item.offer_name).toLowerCase().includes(query)).slice(0,100);
    els.view.innerHTML = `<div class="cc-toolbar cc-toolbar--manage"><div class="cc-segment"><button class="${state.managementType==='events'?'is-active':''}" data-manage-type="events">Veranstaltungen</button><button class="${state.managementType==='activities'?'is-active':''}" data-manage-type="activities">Aktivitäten</button></div><label class="cc-search"><span class="sr-only">Suchen</span><input id="cc-manage-search" type="search" value="${escapeHtml(state.managementQuery)}" placeholder="Suchen …"></label></div><div class="cc-manage-list">${items.length?items.map(item=>managementItem(item,state.managementType)).join(''):'<div class="cc-empty cc-empty--large">Keine passenden Inhalte gefunden.</div>'}</div>`;
    bindManage();
  }

  function renderMore() {
    const ideas = state.cases.filter(item => item.type==='idea' && !['done','rejected'].includes(item.state));
    els.view.innerHTML = `<section class="cc-section"><div class="cc-section__head"><h2>Ideen</h2><button class="cc-button cc-button--primary" id="cc-new-idea">+ Idee</button></div>${ideas.length?`<div class="cc-task-list">${ideas.map(taskCard).join('')}</div>`:'<div class="cc-empty">Keine gesammelten Ideen.</div>'}</section><section class="cc-section"><div class="cc-section__head"><h2>Weitere Funktionen</h2></div><div class="cc-link-list"><a class="cc-link-card" href="/fuer-veranstalter/dashboard/"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung öffnen</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Synchronisation und bekannte Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div></section>`;
    document.querySelector('#cc-new-idea')?.addEventListener('click',()=>openCreateDialog('idea'));
    document.querySelector('#cc-system')?.addEventListener('click',()=>openSystemDialog());
    document.querySelector('#cc-logout')?.addEventListener('click',logout);
    bindTaskActions();
  }

  function render() {
    if (state.view==='overview') renderOverview();
    if (state.view==='work') renderWork();
    if (state.view==='tasks') renderTasks();
    if (state.view==='manage') renderManage();
    if (state.view==='more') renderMore();
  }

  function updateBadges() {
    const work = activeCases().filter(item=>item.type==='intake').length;
    const tasks = activeCases().filter(item=>item.type==='task').length;
    [[els.badgeWork,work],[els.badgeTasks,tasks]].forEach(([el,count])=>{el.hidden=count===0;el.textContent=count>99?'99+':String(count);});
  }

  async function load() {
    setStatus('Daten werden geladen …');
    try {
      state.overview = await api('/api/control-center/overview.php');
      const cases = await api('/api/control-center/cases.php');
      state.cases = cases.items || [];
      updateBadges(); render(); setStatus('');
    } catch (error) { setStatus(error.message); }
  }

  function bindViewLinks() { document.querySelectorAll('[data-go-view]').forEach(button=>button.addEventListener('click',()=>setView(button.dataset.goView))); }
  function bindWork() {
    document.querySelectorAll('[data-work-group]').forEach(button=>button.addEventListener('click',()=>{state.workGroup=button.dataset.workGroup;state.workIndex=0;renderWork();}));
    document.querySelectorAll('[data-work-index]').forEach(button=>button.addEventListener('click',()=>{state.workIndex=Number(button.dataset.workIndex)||0;renderWork();}));
    document.querySelectorAll('[data-work-move]').forEach(button=>button.addEventListener('click',()=>{state.workIndex+=Number(button.dataset.workMove)||0;renderWork();}));
    document.querySelectorAll('[data-case-action]').forEach(button=>button.addEventListener('click',()=>handleCaseAction(workCases()[state.workIndex],button.dataset.caseAction)));
  }
  function bindTasks() {
    document.querySelectorAll('[data-task-mode]').forEach(button=>button.addEventListener('click',()=>{state.taskMode=button.dataset.taskMode;renderTasks();}));
    document.querySelector('#cc-new-task')?.addEventListener('click',()=>openCreateDialog('task'));
    bindTaskActions();
  }
  function bindTaskActions() { document.querySelectorAll('[data-task-action]').forEach(button=>button.addEventListener('click',()=>{const card=button.closest('[data-case-id]');const item=state.cases.find(c=>c.id===card?.dataset.caseId);if(item) handleCaseAction(item,button.dataset.taskAction);})); }
  function bindManage() {
    document.querySelectorAll('[data-manage-type]').forEach(button=>button.addEventListener('click',()=>{state.managementType=button.dataset.manageType;state.managementQuery='';renderManage();}));
    document.querySelector('#cc-manage-search')?.addEventListener('input',event=>{state.managementQuery=event.target.value;renderManage();});
    document.querySelectorAll('[data-create-object-task]').forEach(button=>button.addEventListener('click',()=>openCreateDialog('task',{object_id:button.dataset.createObjectTask,object_title:button.dataset.objectTitle,object_type:button.dataset.objectType,title:`${button.dataset.objectTitle} bearbeiten`})));
  }

  function openCreateDialog(type, defaults={}) {
    const isTask=type==='task';
    els.dialogBody.innerHTML=`<h2>${isTask?'Aufgabe anlegen':'Idee erfassen'}</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-create-title" value="${escapeHtml(defaults.title||'')}" required></label><label class="cc-field"><span>${isTask?'Nächster Schritt':'Anlass oder Nutzen'}</span><textarea id="cc-create-note"></textarea></label>${isTask?'<label class="cc-field"><span>Fällig am (optional)</span><input id="cc-create-due" type="date"></label>':''}<button class="cc-button cc-button--primary" id="cc-create-save">Speichern</button></div>`;
    els.dialog.showModal();
    document.querySelector('#cc-create-save')?.addEventListener('click',async()=>{
      const title=clean(document.querySelector('#cc-create-title')?.value); if(!title) return;
      const note=clean(document.querySelector('#cc-create-note')?.value); const due=clean(document.querySelector('#cc-create-due')?.value);
      await api('/api/control-center/cases.php',{method:'POST',body:JSON.stringify({type,state:isTask?'open':'new',priority:'normal',title,reason:isTask?'':note,next_action:isTask?note:'',due_at:due?`${due} 17:00:00`:null,source_system:'manual',object_type:defaults.object_type||'',object_id:defaults.object_id||'',object_title:defaults.object_title||''})});
      els.dialog.close(); await load(); setView(isTask?'tasks':'more');
    });
  }

  function openSystemDialog() {
    const sync=state.overview?.sync||{};
    els.dialogBody.innerHTML=`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message||'Keine bekannte Störung.')}</p><dl>${Object.entries(sync).map(([key,value])=>`<dt>${escapeHtml(key)}</dt><dd>${escapeHtml(JSON.stringify(value))}</dd>`).join('')}</dl>`;
    els.dialog.showModal();
  }

  async function showDetails(item) {
    const detail=await api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`);
    els.dialogBody.innerHTML=`<h2>${escapeHtml(detail.title)}</h2><p>${escapeHtml(detail.reason||'')}</p><dl><dt>Status</dt><dd>${escapeHtml(detail.display_status)}</dd><dt>Quelle</dt><dd>${escapeHtml(detail.source?.system)} · ${escapeHtml(detail.source?.reference)}</dd></dl>`;
    els.dialog.showModal();
  }

  function askReject(item) {
    els.dialogBody.innerHTML=`<h2>Ablehnen</h2><p>${escapeHtml(item.title)}</p><label class="cc-field"><span>Grund</span><select id="cc-reject-reason">${rejectReasons.map(reason=>`<option>${escapeHtml(reason)}</option>`).join('')}</select></label><button class="cc-button cc-button--danger" id="cc-confirm-reject">Ablehnen</button>`;
    els.dialog.showModal(); document.querySelector('#cc-confirm-reject')?.addEventListener('click',()=>{const reason=clean(document.querySelector('#cc-reject-reason')?.value);els.dialog.close();performAction(item,'reject',{reason});});
  }
  function askSnooze(item) {
    const date=new Date();date.setDate(date.getDate()+7);
    els.dialogBody.innerHTML=`<h2>Zurückstellen</h2><label class="cc-field"><span>Wiedervorlage</span><input id="cc-snooze-date" type="date" value="${date.toISOString().slice(0,10)}"></label><button class="cc-button cc-button--primary" id="cc-confirm-snooze">Zurückstellen</button>`;
    els.dialog.showModal();document.querySelector('#cc-confirm-snooze')?.addEventListener('click',()=>{const until=clean(document.querySelector('#cc-snooze-date')?.value);els.dialog.close();performAction(item,'snooze',{until});});
  }

  async function handleCaseAction(item,action) {
    if(!item) return;
    if(action==='details') return showDetails(item);
    if(action==='reject') return askReject(item);
    if(action==='snooze') return askSnooze(item);
    if(action==='approve' && item.case_kind?.includes('correction')) return showDetails(item);
    return performAction(item,action,{});
  }

  async function performAction(item,action,payload) {
    setStatus('Aktion wird ausgeführt …');
    try { await api('/api/control-center/action.php',{method:'POST',body:JSON.stringify({case_id:item.id,action,payload})}); await load(); setStatus('Gespeichert.'); }
    catch(error){setStatus(error.message);}
  }

  els.authForm.addEventListener('submit',async event=>{event.preventDefault();state.password=clean(els.password.value);sessionStorage.setItem('be_cc_password',state.password);els.auth.hidden=true;els.content.hidden=false;await load();});
  els.refresh.addEventListener('click',load);
  els.nav.forEach(button=>button.addEventListener('click',()=>setView(button.dataset.view)));
  els.dialog.addEventListener('click',event=>{if(event.target===els.dialog)els.dialog.close();});

  if(state.password){els.auth.hidden=true;els.content.hidden=false;load();}
})();