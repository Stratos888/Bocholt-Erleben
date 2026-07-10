(() => {
  'use strict';

  const state = {
    password: sessionStorage.getItem('be_cc_password') || '',
    view: 'overview',
    cases: [], overview: null,
    workGroup: 'all', workIndex: 0,
    laborMode: 'now',
    managementType: 'events', managementQuery: '',
    managementItems: {events: [], activities: []}, managementLoaded: {events:false,activities:false},
  };

  const els = {
    auth:document.querySelector('#cc-auth'), authForm:document.querySelector('#cc-auth-form'), password:document.querySelector('#cc-password'),
    content:document.querySelector('#cc-content'), view:document.querySelector('#cc-view'), title:document.querySelector('#cc-title'), status:document.querySelector('#cc-status'),
    refresh:document.querySelector('#cc-refresh'), nav:[...document.querySelectorAll('.cc-nav [data-view]')],
    badgeWork:document.querySelector('#cc-badge-work'), badgeLabor:document.querySelector('#cc-badge-labor'),
    dialog:document.querySelector('#cc-dialog'), dialogBody:document.querySelector('#cc-dialog-body'),
  };

  const rejectReasons=['Termin liegt in der Vergangenheit','Doppelt / bereits abgedeckt','Terminangaben unklar','Nicht öffentlich zugänglich','Quelle / Angaben reichen nicht','Nicht lokal genug','Redaktionell nicht passend'];
  const groupLabels={all:'Alle',new_content:'Neue Inhalte',quality:'Qualität',provider:'Anbieter',approvals:'Freigaben',other:'Sonstige'};
  const laborLabels={now:'Jetzt',next:'Als Nächstes',waiting:'Wartet',blocked:'Blockiert',backlog:'Backlog',ideas:'Ideen',archive:'Archiv'};
  const escapeHtml=value=>String(value??'').replace(/[&<>'"]/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  const clean=value=>String(value??'').trim();
  const formatDate=value=>{if(!value)return'';const d=new Date(String(value).replace(' ','T'));return Number.isNaN(d.getTime())?'':new Intl.DateTimeFormat('de-DE',{dateStyle:'medium'}).format(d);};
  const setStatus=message=>{els.status.textContent=message||'';};

  async function api(path,options={}){
    const response=await fetch(path,{...options,headers:{'Content-Type':'application/json','X-BE-Review-Password':state.password,...(options.headers||{})},cache:'no-store'});
    const payload=await response.json().catch(()=>({}));
    if(!response.ok){if(response.status===401)logout();throw new Error(payload.message||'Anfrage fehlgeschlagen.');}
    return payload.data;
  }

  function logout(){state.password='';sessionStorage.removeItem('be_cc_password');els.content.hidden=true;els.auth.hidden=false;}
  function activeCases(){return state.cases.filter(item=>!['done','rejected','parked','information'].includes(item.state));}
  function workCases(){return activeCases().filter(item=>item.type==='intake'&&(state.workGroup==='all'||item.queue_group===state.workGroup));}
  function concreteTasks(){return state.cases.filter(item=>item.type==='task');}
  function backlogCases(){return state.cases.filter(item=>item.case_kind==='backlog_item');}
  function ideaCases(){return state.cases.filter(item=>item.type==='idea'&&item.case_kind!=='backlog_item');}

  function laborCases(){
    const tasks=concreteTasks();
    if(state.laborMode==='now') return tasks.filter(i=>!['done','rejected','parked','waiting','blocked','snoozed'].includes(i.state)&&(i.overdue||i.bucket==='now'||i.state==='in_progress'));
    if(state.laborMode==='next') return tasks.filter(i=>!['done','rejected','parked','waiting','blocked'].includes(i.state)&&!i.overdue&&i.bucket!=='now');
    if(state.laborMode==='waiting') return tasks.filter(i=>i.state==='waiting'||i.state==='snoozed');
    if(state.laborMode==='blocked') return tasks.filter(i=>i.state==='blocked');
    if(state.laborMode==='backlog') return backlogCases().filter(i=>!['done','rejected','parked'].includes(i.state));
    if(state.laborMode==='ideas') return ideaCases().filter(i=>!['done','rejected','parked'].includes(i.state));
    return state.cases.filter(i=>['done','rejected','parked'].includes(i.state)&&(i.type==='task'||i.type==='idea'));
  }

  function setView(view){
    state.view=view;
    const titles={overview:'Übersicht',work:'Bearbeiten',labor:'Arbeit',manage:'Verwaltung',menu:'Menü'};
    els.title.textContent=titles[view]||'Steuerzentrale';
    els.nav.forEach(b=>b.classList.toggle('is-active',b.dataset.view===view));
    render();
    window.scrollTo({top:0,behavior:'instant'});
  }

  function compactSummary(title,count,detail,view,label){return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${count}</strong><p>${escapeHtml(detail)}</p></div>${view?`<button class="cc-button cc-button--primary" data-go-view="${view}">${escapeHtml(label)}</button>`:''}</article>`;}

  function renderOverview(){
    const cases=activeCases(),urgent=cases.filter(i=>i.bucket==='now'),work=cases.filter(i=>i.type==='intake'),tasks=cases.filter(i=>i.type==='task'),waiting=tasks.filter(i=>i.state==='waiting'||i.state==='snoozed'),backlog=backlogCases().filter(i=>!['done','rejected','parked'].includes(i.state));
    const groups=work.reduce((a,i)=>{const k=i.queue_group||'other';a[k]=(a[k]||0)+1;return a;},{});
    const groupText=Object.entries(groups).map(([k,n])=>`${n} ${groupLabels[k]||'Sonstige'}`).join(' · ')||'Keine offenen Entscheidungen';
    const urgentText=urgent.length?urgent.slice(0,2).map(i=>i.title).join(' · '):'Aktuell ist nichts dringend.';
    const workText=waiting.length?`${waiting.length} warten auf Rückmeldung · ${backlog.length} im Backlog`:`${backlog.length} im Backlog`;
    els.view.innerHTML=`<section class="cc-overview-grid">${compactSummary('Jetzt erforderlich',urgent.length,urgentText,urgent.length?'work':'','Jetzt bearbeiten')}${compactSummary('Zu bearbeiten',work.length,groupText,work.length?'work':'','Bearbeiten öffnen')}${compactSummary('Arbeit',tasks.filter(i=>!['done','rejected','parked'].includes(i.state)).length,workText,'labor','Arbeit öffnen')}<article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Systemzustand</span><h2>Alles ruhig</h2><p>${escapeHtml(state.overview?.system?.message||'Keine bekannte Störung mit Auswirkung.')}</p></div></article></section>`;
    bindViewLinks();
  }

  function contextRows(item){const c=item.decision_context||{},rows=[];const add=(l,v)=>{if(clean(v))rows.push([l,clean(v)]);};add('Datum',c.date);add('Enddatum',c.end_date);add('Uhrzeit',c.time);add('Ort',[c.location,c.city].filter(Boolean).join(' · '));add('Anbieter',c.organization);return rows;}

  function renderWorkDetail(item,index,total){
    const links=(item.source_links||[]).map(link=>`<a class="cc-button cc-button--secondary" href="${escapeHtml(link.url)}" target="_blank" rel="noopener">${escapeHtml(link.label)}</a>`).join('');
    const rows=contextRows(item).map(([l,v])=>`<div class="cc-detail-row"><span>${escapeHtml(l)}</span><strong>${escapeHtml(v)}</strong></div>`).join('');
    const c=item.decision_context||{},problem=clean(c.issue_text||item.reason),recommended=clean(c.recommended_action||item.next_action),description=clean(c.current_description||c.description),primary=item.primary_action,secondary=item.secondary_actions||[];
    return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}"><header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(groupLabels[item.queue_group]||'Vorgang')} · ${index+1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><span class="cc-pill">${escapeHtml(item.display_status)}</span></header>${problem?`<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>`:''}${recommended?`<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>`:''}${description?`<details class="cc-disclosure"><summary>Aktuellen Inhalt anzeigen</summary><div>${escapeHtml(description)}</div></details>`:''}${rows?`<section class="cc-detail-grid">${rows}</section>`:''}<div class="cc-action-primary">${primary?`<button class="cc-button cc-button--primary cc-button--large" data-case-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>`:`<div class="cc-empty">${escapeHtml(item.waiting_for||'Aktuell keine Aktion erforderlich.')}</div>`}</div><div class="cc-actions cc-actions--secondary">${secondary.map(a=>`<button class="cc-button ${a.destructive?'cc-button--danger':'cc-button--secondary'}" data-case-action="${escapeHtml(a.key)}">${escapeHtml(a.label)}</button>`).join('')}${links}<button class="cc-button cc-button--ghost" data-case-action="details">Technische Details</button></div><footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-work-move="-1" ${index===0?'disabled':''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-work-move="1" ${index>=total-1?'disabled':''}>Weiter ›</button></footer></article>`;
  }

  function renderWork(){
    const items=workCases();if(state.workIndex>=items.length)state.workIndex=Math.max(0,items.length-1);const active=items[state.workIndex];
    const queue=items.map((item,index)=>`<button class="cc-queue-item ${index===state.workIndex?'is-active':''}" data-work-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(groupLabels[item.queue_group]||item.display_status)}</small></button>`).join('');
    els.view.innerHTML=`<div class="cc-filter-row">${Object.entries(groupLabels).map(([k,l])=>`<button class="cc-filter ${state.workGroup===k?'is-active':''}" data-work-group="${k}">${escapeHtml(l)}${k==='all'?` (${activeCases().filter(i=>i.type==='intake').length})`:''}</button>`).join('')}</div>${items.length?`<div class="cc-work-layout"><aside class="cc-queue" aria-label="Vorgänge">${queue}</aside><main>${renderWorkDetail(active,state.workIndex,items.length)}</main></div>`:'<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu bearbeiten.</div>'}`;
    bindWork();
  }

  function laborCard(item){
    const due=item.due_at?`${item.overdue?'Überfällig':'Fällig'} ${formatDate(item.due_at)}`:'';
    const primary=item.primary_action;
    const secondary=item.secondary_actions||[];
    return `<article class="cc-task-card" data-case-id="${escapeHtml(item.id)}"><div><span class="cc-pill">${escapeHtml(item.case_kind==='backlog_item'?'Backlog':item.type==='idea'?'Idee':item.display_status)}</span><h3>${escapeHtml(item.title)}</h3>${item.reason?`<p>${escapeHtml(item.reason)}</p>`:''}${item.next_action?`<p><strong>Nächster Schritt:</strong> ${escapeHtml(item.next_action)}</p>`:''}${due?`<small>${escapeHtml(due)}</small>`:''}${item.waiting_for?`<small>Wartet auf: ${escapeHtml(item.waiting_for)}</small>`:''}</div><div class="cc-actions">${primary?`<button class="cc-button cc-button--primary" data-labor-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>`:''}${secondary.slice(0,2).map(a=>`<button class="cc-button ${a.destructive?'cc-button--danger':'cc-button--secondary'}" data-labor-action="${escapeHtml(a.key)}">${escapeHtml(a.label)}</button>`).join('')}<button class="cc-button cc-button--secondary" data-labor-action="details">Details</button></div></article>`;
  }

  function renderLabor(){
    const items=laborCases();
    els.view.innerHTML=`<div class="cc-toolbar"><div class="cc-filter-row cc-filter-row--contained">${Object.entries(laborLabels).map(([k,l])=>`<button class="cc-filter ${state.laborMode===k?'is-active':''}" data-labor-mode="${k}">${escapeHtml(l)}</button>`).join('')}</div><div class="cc-actions"><button class="cc-button cc-button--secondary" id="cc-new-idea">+ Idee</button><button class="cc-button cc-button--secondary" id="cc-new-backlog">+ Backlog</button><button class="cc-button cc-button--primary" id="cc-new-task">+ Aufgabe</button></div></div><div class="cc-task-list">${items.length?items.map(laborCard).join(''):`<div class="cc-empty cc-empty--large">${escapeHtml(laborLabels[state.laborMode])}: keine Einträge.</div>`}</div>`;
    bindLabor();
  }

  async function loadManagement(type){if(state.managementLoaded[type])return;const result=await api(`/api/control-center/content.php?type=${encodeURIComponent(type)}`);state.managementItems[type]=Array.isArray(result.items)?result.items:[];state.managementLoaded[type]=true;}
  function managementItem(item,type){const title=clean(item.title||'Ohne Titel'),id=clean(item.id||title),detail=[item.date,item.end_date,item.location,item.city,item.category].filter(Boolean).join(' · '),publicUrl=clean(item.public_url||item.source_url||(type==='events'?'/events/':'/aktivitaeten/')),linked=activeCases().filter(c=>String(c.object?.id||'')===String(id)).length;return `<article class="cc-manage-row"><div><span class="cc-kicker">${type==='events'?'Veranstaltung':'Aktivität'}${linked?` · ${linked} offene Vorgänge`:''}</span><h3>${escapeHtml(title)}</h3>${detail?`<p>${escapeHtml(detail)}</p>`:''}</div><div class="cc-actions"><a class="cc-button cc-button--secondary" href="${escapeHtml(publicUrl)}" target="_blank" rel="noopener">Öffnen</a><button class="cc-button cc-button--primary" data-create-object-task="${escapeHtml(id)}" data-object-title="${escapeHtml(title)}" data-object-type="${type==='events'?'event':'activity'}">Aufgabe anlegen</button></div></article>`;}
  async function renderManage(){els.view.innerHTML='<div class="cc-empty">Inhalte werden geladen …</div>';try{await loadManagement(state.managementType);const source=state.managementItems[state.managementType]||[],query=state.managementQuery.toLowerCase(),items=source.filter(i=>!query||clean(i.title).toLowerCase().includes(query)).slice(0,100);els.view.innerHTML=`<div class="cc-toolbar cc-toolbar--manage"><div class="cc-segment"><button class="${state.managementType==='events'?'is-active':''}" data-manage-type="events">Veranstaltungen</button><button class="${state.managementType==='activities'?'is-active':''}" data-manage-type="activities">Aktivitäten</button></div><label class="cc-search"><span class="sr-only">Suchen</span><input id="cc-manage-search" type="search" value="${escapeHtml(state.managementQuery)}" placeholder="Suchen …"></label></div><div id="cc-manage-results" class="cc-manage-list">${items.length?items.map(i=>managementItem(i,state.managementType)).join(''):'<div class="cc-empty cc-empty--large">Keine passenden Inhalte gefunden.</div>'}</div>`;bindManage();}catch(error){els.view.innerHTML=`<div class="cc-empty cc-empty--large">${escapeHtml(error.message)}</div>`;}}

  function renderMenu(){els.view.innerHTML=`<section class="cc-section"><div class="cc-section__head"><h2>Menü</h2></div><div class="cc-link-list"><a class="cc-link-card" href="/fuer-veranstalter/dashboard/"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung öffnen</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Synchronisation und bekannte Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div></section>`;document.querySelector('#cc-system')?.addEventListener('click',openSystemDialog);document.querySelector('#cc-logout')?.addEventListener('click',logout);}

  function render(){if(state.view==='overview')renderOverview();if(state.view==='work')renderWork();if(state.view==='labor')renderLabor();if(state.view==='manage')renderManage();if(state.view==='menu')renderMenu();}
  function updateBadges(){const work=activeCases().filter(i=>i.type==='intake').length,labor=activeCases().filter(i=>i.type==='task').length;[[els.badgeWork,work],[els.badgeLabor,labor]].forEach(([e,n])=>{e.hidden=n===0;e.textContent=n>99?'99+':String(n);});}
  async function load(){setStatus('Daten werden geladen …');try{state.overview=await api('/api/control-center/overview.php');const cases=await api('/api/control-center/cases.php');state.cases=cases.items||[];updateBadges();render();setStatus('');}catch(error){setStatus(error.message);}}

  function bindViewLinks(){document.querySelectorAll('[data-go-view]').forEach(b=>b.addEventListener('click',()=>setView(b.dataset.goView)));}
  function bindWork(){document.querySelectorAll('[data-work-group]').forEach(b=>b.addEventListener('click',()=>{state.workGroup=b.dataset.workGroup;state.workIndex=0;renderWork();}));document.querySelectorAll('[data-work-index]').forEach(b=>b.addEventListener('click',()=>{state.workIndex=Number(b.dataset.workIndex||0);renderWork();}));document.querySelectorAll('[data-work-move]').forEach(b=>b.addEventListener('click',()=>{state.workIndex+=Number(b.dataset.workMove||0);renderWork();}));document.querySelectorAll('[data-case-action]').forEach(b=>b.addEventListener('click',()=>handleCaseAction(workCases()[state.workIndex],b.dataset.caseAction)));}
  function bindLabor(){document.querySelectorAll('[data-labor-mode]').forEach(b=>b.addEventListener('click',()=>{state.laborMode=b.dataset.laborMode;renderLabor();}));document.querySelector('#cc-new-task')?.addEventListener('click',()=>openCreateDialog('task'));document.querySelector('#cc-new-idea')?.addEventListener('click',()=>openCreateDialog('idea'));document.querySelector('#cc-new-backlog')?.addEventListener('click',openBacklogCreateDialog);document.querySelectorAll('[data-labor-action]').forEach(b=>b.addEventListener('click',()=>{const item=state.cases.find(c=>c.id===b.closest('[data-case-id]')?.dataset.caseId);if(item)handleCaseAction(item,b.dataset.laborAction);}));}
  function bindManage(){document.querySelectorAll('[data-manage-type]').forEach(b=>b.addEventListener('click',()=>{state.managementType=b.dataset.manageType;state.managementQuery='';renderManage();}));const input=document.querySelector('#cc-manage-search');input?.addEventListener('input',e=>{state.managementQuery=e.target.value;const source=state.managementItems[state.managementType]||[],query=state.managementQuery.toLowerCase(),items=source.filter(i=>!query||clean(i.title).toLowerCase().includes(query)).slice(0,100);const results=document.querySelector('#cc-manage-results');if(results)results.innerHTML=items.length?items.map(i=>managementItem(i,state.managementType)).join(''):'<div class="cc-empty cc-empty--large">Keine passenden Inhalte gefunden.</div>';bindObjectTaskButtons();});bindObjectTaskButtons();}
  function bindObjectTaskButtons(){document.querySelectorAll('[data-create-object-task]').forEach(b=>b.addEventListener('click',()=>openCreateDialog('task',{object_id:b.dataset.createObjectTask,object_title:b.dataset.objectTitle,object_type:b.dataset.objectType,title:`${b.dataset.objectTitle} bearbeiten`}),{once:true}));}

  function openCreateDialog(type,defaults={}){const task=type==='task';els.dialogBody.innerHTML=`<h2>${task?'Aufgabe anlegen':'Idee erfassen'}</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-create-title" value="${escapeHtml(defaults.title||'')}" required></label><label class="cc-field"><span>${task?'Nächster Schritt':'Anlass oder Nutzen'}</span><textarea id="cc-create-note"></textarea></label>${task?'<label class="cc-field"><span>Fällig am (optional)</span><input id="cc-create-due" type="date"></label>':''}<button class="cc-button cc-button--primary" id="cc-create-save">Speichern</button></div>`;els.dialog.showModal();document.querySelector('#cc-create-save')?.addEventListener('click',async()=>{const title=clean(document.querySelector('#cc-create-title')?.value);if(!title)return;const note=clean(document.querySelector('#cc-create-note')?.value),due=clean(document.querySelector('#cc-create-due')?.value);await api('/api/control-center/cases.php',{method:'POST',body:JSON.stringify({type,state:task?'open':'new',priority:'normal',title,reason:task?'':note,next_action:task?note:'',due_at:due?`${due} 17:00:00`:null,source_system:'manual',object_type:defaults.object_type||'',object_id:defaults.object_id||'',object_title:defaults.object_title||''})});els.dialog.close();state.laborMode=task?'next':'ideas';await load();setView('labor');});}
  function openBacklogCreateDialog(){els.dialogBody.innerHTML=`<h2>Backlog-Punkt anlegen</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-backlog-title" required></label><label class="cc-field"><span>Beschreibung / Nutzen</span><textarea id="cc-backlog-note"></textarea></label><label class="cc-field"><span>Priorität</span><select id="cc-backlog-priority"><option value="hoch">Hoch</option><option value="mittel" selected>Mittel</option><option value="niedrig">Niedrig</option></select></label><button class="cc-button cc-button--primary" id="cc-backlog-save">Speichern</button></div>`;els.dialog.showModal();document.querySelector('#cc-backlog-save')?.addEventListener('click',async()=>{const title=clean(document.querySelector('#cc-backlog-title')?.value);if(!title)return;await api('/api/growth-backlog/create.php',{method:'POST',body:JSON.stringify({title,note:clean(document.querySelector('#cc-backlog-note')?.value),priority:clean(document.querySelector('#cc-backlog-priority')?.value)})});els.dialog.close();state.laborMode='backlog';await load();setView('labor');});}
  function openBacklogEdit(item){els.dialogBody.innerHTML=`<h2>Backlog bearbeiten</h2><div class="cc-stack"><label class="cc-field"><span>Titel</span><input id="cc-backlog-edit-title" value="${escapeHtml(item.title)}"></label><label class="cc-field"><span>Beschreibung</span><textarea id="cc-backlog-edit-description">${escapeHtml(item.reason||'')}</textarea></label><label class="cc-field"><span>Priorität</span><select id="cc-backlog-edit-priority"><option value="hoch" ${item.priority==='high'?'selected':''}>Hoch</option><option value="mittel" ${item.priority==='normal'?'selected':''}>Mittel</option><option value="niedrig" ${item.priority==='low'?'selected':''}>Niedrig</option></select></label><button class="cc-button cc-button--primary" id="cc-backlog-edit-save">Speichern</button></div>`;els.dialog.showModal();document.querySelector('#cc-backlog-edit-save')?.addEventListener('click',()=>{els.dialog.close();performAction(item,'edit_source',{title:clean(document.querySelector('#cc-backlog-edit-title')?.value),description:clean(document.querySelector('#cc-backlog-edit-description')?.value),priority:clean(document.querySelector('#cc-backlog-edit-priority')?.value)});});}

  function openSystemDialog(){const sync=state.overview?.sync||{};els.dialogBody.innerHTML=`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message||'Keine bekannte Störung.')}</p><details class="cc-disclosure"><summary>Technische Details</summary><div>${escapeHtml(JSON.stringify(sync,null,2))}</div></details>`;els.dialog.showModal();}
  function editField(id,label,value,type='text'){return `<label class="cc-field"><span>${escapeHtml(label)}</span><input id="${id}" type="${type}" value="${escapeHtml(value||'')}"></label>`;}
  function correctionForm(detail){const p=detail.source_payload||{},c=detail.decision_context||{};if(detail.source?.system!=='content_audit'||!String(detail.case_kind||'').includes('correction'))return'';return `<section class="cc-stack"><h3>Korrektur</h3>${editField('cc-edit-source','Offizielle Quelle',p.suggested_url||p.source_url||c.suggested_url||c.source_url,'url')}${editField('cc-edit-title','Titel',p.title)}${editField('cc-edit-date','Startdatum',p.date)}${editField('cc-edit-end','Enddatum',p.endDate||p.end_date)}${editField('cc-edit-time','Uhrzeit',p.time)}${editField('cc-edit-city','Stadt',p.city)}${editField('cc-edit-location','Ort',p.location)}${String(detail.case_kind).includes('description')?`<label class="cc-field"><span>Beschreibung</span><textarea id="cc-edit-description">${escapeHtml(p.description||p.current_description||'')}</textarea></label>`:''}<button class="cc-button cc-button--primary" id="cc-save-correction">Korrigieren und übernehmen</button></section>`;}
  async function showDetails(item){try{const detail=await api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`);els.dialogBody.innerHTML=`<h2>${escapeHtml(detail.title)}</h2>${detail.reason?`<p>${escapeHtml(detail.reason)}</p>`:''}<dl><dt>Status</dt><dd>${escapeHtml(detail.display_status)}</dd><dt>Quelle</dt><dd>${escapeHtml(detail.source?.system)} · ${escapeHtml(detail.source?.reference)}</dd></dl>${correctionForm(detail)}`;els.dialog.showModal();document.querySelector('#cc-save-correction')?.addEventListener('click',async()=>{const value=id=>clean(document.querySelector(id)?.value),source=value('#cc-edit-source'),updates={title:value('#cc-edit-title'),date:value('#cc-edit-date'),endDate:value('#cc-edit-end'),end_date:value('#cc-edit-end'),time:value('#cc-edit-time'),city:value('#cc-edit-city'),location:value('#cc-edit-location'),description:value('#cc-edit-description')};if(source){updates.source_url=source;updates.url=source;updates.event_url=source;}Object.keys(updates).forEach(k=>{if(!updates[k])delete updates[k];});els.dialog.close();await performAction(detail,'approve',{event_updates:updates,note:'Über Steuerzentrale korrigiert und fachlich geprüft.'});});}catch(error){setStatus(error.message);}}
  function askReject(item){els.dialogBody.innerHTML=`<h2>${item.case_kind==='backlog_item'?'Backlog verwerfen':'Ablehnen'}</h2><p>${escapeHtml(item.title)}</p><label class="cc-field"><span>Grund</span><select id="cc-reject-reason">${rejectReasons.map(r=>`<option>${escapeHtml(r)}</option>`).join('')}<option>Bewusst nicht weiterverfolgen</option></select></label><button class="cc-button cc-button--danger" id="cc-confirm-reject">Bestätigen</button>`;els.dialog.showModal();document.querySelector('#cc-confirm-reject')?.addEventListener('click',()=>{const reason=clean(document.querySelector('#cc-reject-reason')?.value);els.dialog.close();performAction(item,'reject',{reason});});}
  function askSnooze(item){const d=new Date();d.setDate(d.getDate()+7);els.dialogBody.innerHTML=`<h2>Zurückstellen</h2><label class="cc-field"><span>Wiedervorlage</span><input id="cc-snooze-date" type="date" value="${d.toISOString().slice(0,10)}"></label><button class="cc-button cc-button--primary" id="cc-confirm-snooze">Zurückstellen</button>`;els.dialog.showModal();document.querySelector('#cc-confirm-snooze')?.addEventListener('click',()=>{const until=clean(document.querySelector('#cc-snooze-date')?.value);els.dialog.close();performAction(item,'snooze',{until});});}
  async function handleCaseAction(item,action){if(!item)return;if(action==='details')return showDetails(item);if(action==='reject')return askReject(item);if(action==='snooze')return askSnooze(item);if(action==='edit_source')return openBacklogEdit(item);if(action==='approve'&&String(item.case_kind||'').includes('correction'))return showDetails(item);return performAction(item,action,{});}
  async function performAction(item,action,payload){setStatus('Aktion wird ausgeführt …');try{await api('/api/control-center/action.php',{method:'POST',body:JSON.stringify({case_id:item.id,action,payload})});await load();setStatus('Gespeichert.');}catch(error){setStatus(error.message);}}

  els.authForm.addEventListener('submit',async e=>{e.preventDefault();state.password=clean(els.password.value);sessionStorage.setItem('be_cc_password',state.password);els.auth.hidden=true;els.content.hidden=false;await load();});
  els.refresh.addEventListener('click',()=>{state.managementLoaded={events:false,activities:false};load();});
  els.nav.forEach(b=>b.addEventListener('click',()=>setView(b.dataset.view)));
  els.dialog.addEventListener('click',e=>{if(e.target===els.dialog)els.dialog.close();});
  if(state.password){els.auth.hidden=true;els.content.hidden=false;load();}
})();
