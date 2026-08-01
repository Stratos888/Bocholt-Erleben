import { api, escapeHtml, clean, formatDate } from './shared.js?v=2026-07-16-e2e-state-v5';

const labels={
  terms_confirmed:'Pilotbedingungen bestätigt',organizer_linked:'Organizer verknüpft',contact_confirmed:'Ansprechpartner bestätigt',
  portal_access_tested:'Portalzugang getestet',pilot_entitlement_readback:'Pilotberechtigung zurückgelesen',service_scope_confirmed:'Serviceumfang bestätigt',
  sources_recorded:'Quellen erfasst',maintenance_path_agreed:'Pflegeweg vereinbart',content_rights_cleared:'Inhalts- und Bildrechte geklärt',
  first_content_ready:'Erster Inhalt vorbereitet',editorial_review_ready:'Redaktionelle Prüfung möglich',measurement_ready:'Messzuordnung vorbereitet',
  distribution_ready:'Partner-Reichweitenstart vorbereitet',activation_target_set:'Aktivierungszieltermin festgelegt'
};
const statusLabels={pending:'Offen',complete:'Belegt',blocked:'Blockiert',not_applicable:'Nicht anwendbar'};
let rendering=false;
function ensureStyles(){if(document.getElementById('cc-gate4-styles'))return;const style=document.createElement('style');style.id='cc-gate4-styles';style.textContent=`.cc-gate4-panel{border:1px solid var(--cc-border,#d8decf);background:var(--cc-surface,#fff)}.cc-gate4-panel>header,.cc-gate4-item>div,.cc-gate4-content{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}.cc-gate4-list{display:grid;gap:.75rem;margin-top:.75rem}.cc-gate4-item{display:grid;gap:.65rem;padding:.8rem;border:1px solid var(--cc-border,#d8decf);border-radius:.75rem}.cc-gate4-item .cc-actions{display:flex;flex-wrap:wrap;gap:.5rem}.cc-gate4-content{padding:.65rem 0;border-bottom:1px solid var(--cc-border,#d8decf)}.cc-gate4-inline-form{display:grid;gap:.55rem;padding-top:.8rem}.cc-gate4-inline-form input{width:100%;min-height:2.75rem;padding:.65rem;border:1px solid var(--cc-border,#cbd4c2);border-radius:.55rem}@media(max-width:600px){.cc-gate4-panel>header,.cc-gate4-item>div,.cc-gate4-content{align-items:stretch;flex-direction:column}.cc-gate4-item .cc-button{width:100%}}`;document.head.appendChild(style);}
const op=prefix=>`${prefix}:${Date.now().toString(36)}:${Math.random().toString(36).slice(2,10)}`;

function firstBlocker(gate4){return gate4?.blockers?.[0]?.message||'Alle Aktivierungsbedingungen sind belegt.';}
function phaseLabel(phase){return ({onboarding:'Onboarding',activation_ready:'Aktivierungsbereit',active:'Pilot aktiv'})[phase]||phase||'Onboarding';}
function itemRow(row){
  const key=clean(row.item_key),status=clean(row.status)||'pending';
  return `<article class="cc-gate4-item" data-gate4-item="${escapeHtml(key)}"><div><strong>${escapeHtml(labels[key]||key)}</strong><span class="cc-pill">${escapeHtml(statusLabels[status]||status)}</span></div><label class="cc-field"><span>Evidence</span><input data-gate4-evidence value="${escapeHtml(row.evidence_text||'')}"></label><div class="cc-actions"><button class="cc-button cc-button--secondary" data-gate4-item-save="complete">Als belegt speichern</button><button class="cc-button cc-button--secondary" data-gate4-item-save="blocked">Blockieren</button></div></article>`;
}
function contentRows(rows=[]){
  if(!rows.length)return '<p class="cc-muted">Noch kein Pilotinhalt eingereicht.</p>';
  return rows.map(row=>`<article class="cc-gate4-content"><div><strong>${escapeHtml(row.title||`Submission ${row.submission_id}`)}</strong><span>${escapeHtml(row.content_type||'')} · ${escapeHtml(row.status||'')}</span></div>${row.status==='draft'?`<button class="cc-button cc-button--secondary" data-gate4-content-ready="${escapeHtml(row.id)}">Redaktionell bereit</button>`:''}</article>`).join('');
}
function panel(candidate){
  const g=candidate.gate4||{},pilot=g.pilot||{},onboarding=g.onboarding||{},first=g.first_content||{};
  const action=g.activation_ready?'<button class="cc-button cc-button--primary cc-button--large" data-gate4-activate>Pilot jetzt aktivieren</button>':g.active?'<div class="cc-notice cc-notice--success"><strong>Sechsmonatige Pilotphase läuft</strong><span>Aktivierung und erster Inhalt sind konsistent zurückgelesen.</span></div>':'<div class="cc-notice cc-notice--info"><strong>Nächster Blocker</strong><span>'+escapeHtml(firstBlocker(g))+'</span></div>';
  return `<section class="cc-startpartner-panel cc-gate4-panel" data-gate4-panel data-candidate-id="${escapeHtml(candidate.id)}" data-candidate-revision="${escapeHtml(candidate.revision)}" data-pilot-revision="${escapeHtml(pilot.revision||1)}"><header><div><span class="cc-kicker">Onboarding, Inhalt und Aktivierung</span><h3>${escapeHtml(phaseLabel(g.phase))}</h3></div><span class="cc-pill">${escapeHtml(`${Number(onboarding.complete_count||0)} / ${Number(onboarding.total_count||14)} belegt`)}</span></header><dl class="cc-startpartner-facts"><div><dt>Pilot</dt><dd>${escapeHtml(pilot.id||'–')}</dd></div><div><dt>Aktivierungsdatum</dt><dd>${escapeHtml(pilot.activation_date_local?formatDate(pilot.activation_date_local):'Noch offen')}</dd></div><div><dt>Geplantes Ende</dt><dd>${escapeHtml(pilot.planned_end_date?formatDate(pilot.planned_end_date):'Noch offen')}</dd></div><div><dt>Erster Inhalt</dt><dd>${escapeHtml(first.status||'Noch nicht bereit')}</dd></div></dl>${action}<details class="cc-disclosure cc-gate4-checklist"><summary>Onboarding-Checkliste und Evidence</summary><div class="cc-gate4-list">${(onboarding.items||[]).map(itemRow).join('')}</div></details><details class="cc-disclosure"><summary>Pilotinhalte und Aktivierungsvorbereitung</summary><div class="cc-stack"><section><h4>Verknüpfte Inhalte</h4>${contentRows(g.content_links)}</section><section class="cc-gate4-inline-form"><h4>Messpreflight</h4><input data-gate4-measure-content placeholder="Content-Link-ID" value="${escapeHtml(first.id||'')}"><input data-gate4-measure-evidence placeholder="Evidence zur Messzuordnung"><button class="cc-button cc-button--secondary" data-gate4-measure>Messung als bereit speichern</button></section><section class="cc-gate4-inline-form"><h4>Partnerdistribution</h4><input data-gate4-channel placeholder="Kanal, z. B. Newsletter"><input data-gate4-target placeholder="Ziel-Link oder Kampagnenreferenz"><input data-gate4-date type="date"><button class="cc-button cc-button--secondary" data-gate4-distribution>Distribution als bereit speichern</button></section></div></details></section>`;
}

async function mutate(root,path,payload){
  const candidateId=root.dataset.candidateId,candidateRevision=Number(root.dataset.candidateRevision),pilotRevision=Number(root.dataset.pilotRevision);
  try{
    await api(path,{method:'POST',body:JSON.stringify({candidate_id:candidateId,expected_revision:candidateRevision,expected_pilot_revision:pilotRevision,operator_name:'Steuerzentrale',operation_id:op('gate4:241'),...payload}),timeoutMs:70000});
    await hydrate(true);
  }catch(error){window.alert(error.status===409?'Zwischenzeitlich geändert. Der aktuelle Zustand wird neu geladen.':error.message);await hydrate(true);}
}
function bind(root){
  root.querySelectorAll('[data-gate4-item-save]').forEach(button=>button.addEventListener('click',()=>{const row=button.closest('[data-gate4-item]');mutate(root,'/api/startpartner/onboarding.php',{action:'update_item',item_key:row.dataset.gate4Item,status:button.dataset.gate4ItemSave,evidence_text:row.querySelector('[data-gate4-evidence]')?.value||''});}));
  root.querySelectorAll('[data-gate4-content-ready]').forEach(button=>button.addEventListener('click',()=>mutate(root,'/api/startpartner/onboarding.php',{action:'mark_content_ready',content_link_id:button.dataset.gate4ContentReady})));
  root.querySelector('[data-gate4-measure]')?.addEventListener('click',()=>mutate(root,'/api/startpartner/onboarding.php',{action:'set_measurement',status:'ready',content_link_id:root.querySelector('[data-gate4-measure-content]')?.value||'',evidence_text:root.querySelector('[data-gate4-measure-evidence]')?.value||''}));
  root.querySelector('[data-gate4-distribution]')?.addEventListener('click',()=>mutate(root,'/api/startpartner/onboarding.php',{action:'set_distribution',status:'ready',channel:root.querySelector('[data-gate4-channel]')?.value||'',target_reference:root.querySelector('[data-gate4-target]')?.value||'',planned_at:root.querySelector('[data-gate4-date]')?.value||'',evidence_text:'Im Onboarding konkret vereinbart.'}));
  root.querySelector('[data-gate4-activate]')?.addEventListener('click',()=>{const date=window.prompt('Lokales Aktivierungsdatum (YYYY-MM-DD)',new Date().toISOString().slice(0,10));if(date)mutate(root,'/api/startpartner/activation.php',{activation_date_local:date});});
}
async function hydrate(force=false){
  if(rendering)return;const article=document.querySelector('.cc-work-detail[data-case-kind="startpartner_candidate"]');
  if(!article)return;const existing=article.querySelector('[data-gate4-panel]');if(existing&&!force)return;
  rendering=true;try{const detail=await api(`/api/control-center/case.php?id=${encodeURIComponent(article.dataset.caseId)}`,{timeoutMs:15000});const candidate=detail.startpartner_candidate;if(!candidate?.gate4?.pilot)return;existing?.remove();const host=article.querySelector('.cc-startpartner-review')||article;host.insertAdjacentHTML('afterbegin',panel(candidate));bind(host.querySelector('[data-gate4-panel]'));}catch(error){console.warn('Gate 4 panel could not be loaded.',error);}finally{rendering=false;}
}
export function startGate4ReviewEnhancement(){ensureStyles();const target=document.querySelector('#cc-view')||document.body;new MutationObserver(()=>void hydrate()).observe(target,{childList:true,subtree:true});void hydrate();}
