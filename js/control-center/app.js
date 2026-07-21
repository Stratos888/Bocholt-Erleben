import { state, els, escapeHtml, clean, setStatus, appPath, api, openDialog, closeDialog, reviewGroup, allReviewCases } from './shared.js?v=2026-07-16-e2e-state-v5';
import { configureReview, renderReview } from './review.js?v=2026-07-17-review-presentation-v1';
import { configureBacklog, renderBacklog } from './backlog.js?v=2026-07-16-e2e-state-v5';
import { renderManage } from './manage.js?v=2026-07-16-e2e-state-v5';
import { renderDevelopment } from './development.js?v=2026-07-16-e2e-state-v5';

async function initializeSharedSession() { return api('/api/control-center/auth.php', { method:'POST', body:JSON.stringify({ password:state.password }), timeoutMs:12000 }); }
async function logout(callServer = true) {
  if (callServer) { try { await fetch(appPath('/api/control-center/auth.php'), { method:'DELETE', credentials:'same-origin', cache:'no-store' }); } catch (_) {} }
  state.password=''; els.password.value=''; els.content.hidden=true; els.auth.hidden=false; closeDialog();
}
function compactSummary(title,count,detail,view,label){return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${escapeHtml(count)}</strong><p>${escapeHtml(detail)}</p></div>${view?`<button class="cc-button cc-button--primary" data-go-view="${view}">${escapeHtml(label)}</button>`:''}</article>`;}
function renderOverview() {
  const reviews=allReviewCases(); const urgent=reviews.filter(item=>item.bucket==='now'||item.overdue||item.state==='blocked');
  const groups=reviews.reduce((result,item)=>{const key=reviewGroup(item);result[key]=(result[key]||0)+1;return result;},{});
  const labels={new_content:'Neue Inhalte',quality:'Qualität',provider:'Anbieter',approvals:'Freigaben',system:'System',other:'Sonstige'};
  const groupText=Object.entries(groups).map(([key,count])=>`${count} ${labels[key]||'Sonstige'}`).join(' · ')||'Keine offenen Prüfungen';
  const quality=state.development?.content_quality||{}; const process=state.development?.automation?.process_health||{};
  const developmentText=`${quality.scope_label||'Aktive Eventbasis'} ${quality.coverage_percent??0} % vollständig · ${['attention','unknown'].includes(process.status)?'Prozessprüfung erforderlich':'Prozesse ohne bekannten Fehler'}`;
  const counts=state.backlog?.counts||{total:0,open:0,completed:0};
  const backlogText=state.backlog?.status==='error'?'Kanonischer Backlog konnte nicht geladen werden':`${counts.open} offen · ${counts.completed} abgeschlossen · alle sichtbar`;
  els.view.innerHTML=`<section class="cc-overview-grid">
    ${compactSummary('Jetzt erforderlich',urgent.length,urgent.length?urgent.slice(0,2).map(item=>item.title).join(' · '):'Aktuell ist nichts dringend.',urgent.length?'review':'','Jetzt prüfen')}
    ${compactSummary('Zu prüfen',reviews.length,groupText,reviews.length?'review':'','Prüfen öffnen')}
    ${compactSummary('Backlog',counts.total,backlogText,'backlog','Backlog öffnen')}
    <article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Entwicklung</span><h2>${escapeHtml(state.development?.operator_status||state.development?.summary?.label||'Projektstatus')}</h2><p>${escapeHtml(developmentText)}</p></div><button class="cc-button cc-button--secondary" data-go-view="development">Entwicklung öffnen</button></article>
  </section>`;
  document.querySelectorAll('[data-go-view]').forEach(button=>button.addEventListener('click',()=>setView(button.dataset.goView)));
}
function updateBadges(){els.badgeReview.hidden=allReviewCases().length===0;els.badgeReview.textContent=allReviewCases().length>99?'99+':String(allReviewCases().length);els.badgeBacklog.hidden=true;els.badgeBacklog.textContent='';}
export async function load(options={}) {
  const {throwOnError=false}=options; setStatus('Daten werden geladen …');
  try {
    const overview=await api('/api/control-center/overview.php',{timeoutMs:70000});
    const [cases,development,backlogResult]=await Promise.all([
      api('/api/control-center/cases.php?active=1'),api('/api/control-center/development.php'),
      api('/api/growth-backlog/list.php').then(data=>({ok:true,data})).catch(error=>({ok:false,error})),
    ]);
    state.overview=overview; state.cases=cases.items||[]; state.development=development;
    state.backlog=backlogResult.ok?{status:'ok',label:backlogResult.data.label||'Growth-Backlog',items:backlogResult.data.items||[],counts:backlogResult.data.counts||{total:0,open:0,completed:0},message:''}:{status:'error',label:'Growth-Backlog',items:[],counts:{total:0,open:0,completed:0},message:backlogResult.error?.message||'Backlog konnte nicht geladen werden.'};
    updateBadges(); render(); setStatus(''); return {overview:state.overview,cases:state.cases,development:state.development};
  } catch(error){if(error.status===401)logout(false);setStatus(error.message,'attention');if(throwOnError)throw error;return null;}
}
configureReview({reload:load}); configureBacklog({reload:load});
function render(){if(state.view!=='development'||state.developmentMode!=='seo')els.view.classList.remove('cc-view--seo');if(state.view==='overview')renderOverview();else if(state.view==='review')renderReview();else if(state.view==='backlog')renderBacklog();else if(state.view==='manage')renderManage();else if(state.view==='development')renderDevelopment();}
function setView(view){state.view=view;setStatus('');const titles={overview:'Übersicht',review:'Prüfen',backlog:'Backlog',manage:'Verwaltung',development:'Entwicklung'};els.title.textContent=titles[view]||'Steuerzentrale';els.nav.forEach(button=>button.classList.toggle('is-active',button.dataset.view===view));render();window.scrollTo({top:0,behavior:'instant'});}
function openSystemDialog(){const systemCases=allReviewCases().filter(item=>reviewGroup(item)==='system');const counts=state.backlog?.counts||{total:0,open:0,completed:0};closeDialog();openDialog(`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message||'Keine bekannte Störung mit Auswirkung.')}</p><ul class="cc-system-list"><li><strong>Prüffälle</strong><span>${allReviewCases().length} offen</span></li><li><strong>Systemfälle</strong><span>${systemCases.length} offen</span></li><li><strong>Backlog</strong><span>${counts.total} insgesamt · ${counts.open} offen</span></li></ul><details class="cc-disclosure"><summary>Technische Details</summary><div>${escapeHtml(JSON.stringify(state.overview?.sync||{},null,2))}</div></details>`);}
function openHeaderMenu(){openDialog(`<h2>Menü</h2><div class="cc-link-list"><a class="cc-link-card" href="${escapeHtml(appPath('/fuer-veranstalter/dashboard/'))}"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Prozesse und Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-deploy"><strong>Deployment starten</strong><span>Datenbestand neu veröffentlichen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div>`);document.querySelector('#cc-system')?.addEventListener('click',openSystemDialog);document.querySelector('#cc-deploy')?.addEventListener('click',async()=>{closeDialog();setStatus('Deployment wird gestartet …','info');try{const result=await api('/api/control-center/deploy.php',{method:'POST',body:'{}'});setStatus(result.message,'success');}catch(error){setStatus(error.message,'attention');}});document.querySelector('#cc-logout')?.addEventListener('click',()=>logout(true));}
els.authForm.addEventListener('submit',async event=>{event.preventDefault();state.password=clean(els.password.value);setStatus('Zugang wird geprüft …');try{await initializeSharedSession();els.password.value='';els.auth.hidden=true;els.content.hidden=false;await load();}catch(error){state.password='';els.password.value='';setStatus(error.message,'attention');}});
els.refresh.addEventListener('click',()=>{state.managementLoaded={events:false,activities:false};state.managementWarnings={events:[],activities:[]};state.development=null;load();});
els.menuButton.addEventListener('click',openHeaderMenu);els.nav.forEach(button=>button.addEventListener('click',()=>setView(button.dataset.view)));els.dialogClose.addEventListener('click',closeDialog);els.dialog.addEventListener('click',event=>{if(event.target===els.dialog)closeDialog();});
