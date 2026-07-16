import {
  state, els, reviewLabels, escapeHtml, clean, asArray, formatDate, fact,
  reviewGroup, allReviewCases, reviewCases,
} from './shared.js?v=2026-07-15-control-center-editorial-v1';

let handleAction = async () => {};
export function configureReviewRenderer(callbacks = {}) { if (callbacks.handleAction) handleAction = callbacks.handleAction; }
function contract(item) { return item.review_contract || item.decision_context?.review_contract || null; }
function renderGate(review) {
  const gate = review?.decision_gate || {};
  const blockers = asArray(gate.blockers);
  const warnings = asArray(gate.warnings);
  if (!blockers.length && !warnings.length) return '<div class="cc-gate cc-gate--ready"><strong>Entscheidungsreif</strong><span>Alle zwingenden Angaben sind vorhanden und es besteht kein harter Dublettenkonflikt.</span></div>';
  return `<div class="cc-gate ${blockers.length ? 'cc-gate--blocked' : 'cc-gate--warning'}"><strong>${blockers.length ? 'Noch nicht übernehmbar' : 'Übernehmbar mit Hinweis'}</strong>${blockers.length ? `<ul>${blockers.map(item => `<li>${escapeHtml(item.message || item.code)}</li>`).join('')}</ul>` : ''}${warnings.length ? `<ul>${warnings.map(item => `<li>${escapeHtml(item.message || item.code)}</li>`).join('')}</ul>` : ''}</div>`;
}
export function renderEventReview(review) {
  if (!review) return '';
  const facts=review.facts||{}, source=review.source||{}, visual=review.visual||{}, classification=review.classification||{}, dedupe=review.dedupe||{};
  const date=[facts.date?formatDate(facts.date):'',facts.end_date?`bis ${formatDate(facts.end_date)}`:''].filter(Boolean).join(' ');
  return `<section class="cc-review-data">${renderGate(review)}<dl class="cc-fact-grid">${fact('Termin',date)}${fact('Uhrzeit',facts.time||facts.time_reason)}${fact('Ort',[facts.location,facts.city].filter(Boolean).join(' · '))}${fact('Adresse',facts.address)}${fact('Kategorie',classification.category)}${fact('Quelle',source.name||source.url,source.url)}${fact('Ticket',source.ticket_url,source.ticket_url)}${fact('Visual-Key',visual.key)}${fact('Motiv',visual.motif)}${fact('Dublette',dedupe.hard_conflict?`Konflikt mit ${dedupe.matched_event_id||'bestehendem Event'}`:'Keine harte Dublette')}</dl><section class="cc-detail-section"><h3>Finale Beschreibung</h3><p class="cc-description-copy">${escapeHtml(review.description?.final||'Keine Beschreibung vorhanden.')}</p></section></section>`;
}
function generic(item) {
  const context=item.decision_context||{}; const problem=clean(context.issue_text||item.reason); const recommended=clean(context.recommended_action||item.next_action); const current=clean(context.current_description||context.description); const suggested=clean(context.suggested_description);
  return `${problem?`<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>`:''}${recommended?`<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>`:''}${current||suggested?`<div class="cc-copy-compare"><section><span class="cc-kicker">Aktuell</span><p>${escapeHtml(current||'Keine aktuelle Fassung.')}</p></section>${suggested?`<section class="cc-copy-compare__proposal"><span class="cc-kicker">Vorschlag</span><p>${escapeHtml(suggested)}</p></section>`:''}</div>`:''}`;
}
function actions(item) {
  if(item.case_kind==='event_candidate') { const ready=Boolean(contract(item)?.decision_gate?.ready); return {primary:{key:ready?'approve':'edit_and_approve',label:ready?'Übernehmen':'Bearbeiten und übernehmen'},secondary:[...(ready?[{key:'edit_and_approve',label:'Bearbeiten und übernehmen'}]:[]),{key:'snooze',label:'Zurückstellen'},{key:'reject',label:'Ablehnen',destructive:true},{key:'details',label:'Quelldaten'}]}; }
  if(String(item.case_kind||'').includes('correction')) return {primary:{key:'edit_and_approve',label:item.primary_action?.label||'Korrigieren und übernehmen'},secondary:[{key:'snooze',label:'Zurückstellen'},{key:'reject',label:'Ablehnen',destructive:true},{key:'details',label:'Details'}]};
  const secondary=[...asArray(item.secondary_actions),{key:'details',label:'Details'}].filter((entry,index,list)=>entry?.key&&list.findIndex(item=>item.key===entry.key)===index);
  return {primary:item.primary_action,secondary};
}
function detail(item,index,total) {
  const available=actions(item); const links=asArray(item.source_links).map(link=>`<a class="cc-button cc-button--secondary" href="${escapeHtml(link.url)}" target="_blank" rel="noopener">${escapeHtml(link.label)}</a>`).join(''); const body=item.case_kind==='event_candidate'?renderEventReview(contract(item)):generic(item);
  return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}"><header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(reviewLabels[reviewGroup(item)]||'Prüfung')} · ${index+1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><span class="cc-pill">${escapeHtml(item.display_status)}</span></header>${body}<div class="cc-action-primary">${available.primary?`<button class="cc-button cc-button--primary cc-button--large" data-review-action="${escapeHtml(available.primary.key)}">${escapeHtml(available.primary.label)}</button>`:`<div class="cc-empty">${escapeHtml(item.waiting_for||'Aktuell keine Aktion erforderlich.')}</div>`}</div><div class="cc-actions cc-actions--secondary">${available.secondary.map(action=>`<button class="cc-button ${action.destructive?'cc-button--danger':'cc-button--secondary'}" data-review-action="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}${links}</div><footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-review-move="-1" ${index===0?'disabled':''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-review-move="1" ${index>=total-1?'disabled':''}>Weiter ›</button></footer></article>`;
}
function count(key){return key==='all'?allReviewCases().length:allReviewCases().filter(item=>reviewGroup(item)===key).length;}
export function renderReview() {
  const groups=Object.entries(reviewLabels).filter(([key])=>key==='all'||count(key)>0); if(!groups.some(([key])=>key===state.reviewGroup))state.reviewGroup='all'; const items=reviewCases(); if(state.reviewIndex>=items.length)state.reviewIndex=Math.max(0,items.length-1);
  const queue=items.map((item,index)=>`<button class="cc-queue-item ${index===state.reviewIndex?'is-active':''}" data-review-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(reviewLabels[reviewGroup(item)]||item.display_status)}</small></button>`).join('');
  const pills=groups.map(([key,label])=>`<button class="cc-filter ${state.reviewGroup===key?'is-active':''}" data-review-group="${key}">${escapeHtml(label)} (${count(key)})</button>`).join('');
  const options=groups.map(([key,label])=>`<option value="${key}" ${state.reviewGroup===key?'selected':''}>${escapeHtml(label)} (${count(key)})</option>`).join('');
  els.view.innerHTML=`<div class="cc-review-controls"><div class="cc-filter-row cc-review-pills">${pills}</div><label class="cc-work-filter cc-review-select"><span>Prüfbereich</span><select id="cc-review-select">${options}</select></label></div>${items.length?`<div class="cc-work-layout"><aside class="cc-queue">${queue}</aside><main>${detail(items[state.reviewIndex],state.reviewIndex,items.length)}</main></div>`:'<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu prüfen.</div>'}`;
  document.querySelectorAll('[data-review-group]').forEach(button=>button.addEventListener('click',()=>{state.reviewGroup=button.dataset.reviewGroup;state.reviewIndex=0;renderReview();})); document.querySelector('#cc-review-select')?.addEventListener('change',event=>{state.reviewGroup=event.target.value;state.reviewIndex=0;renderReview();}); document.querySelectorAll('[data-review-index]').forEach(button=>button.addEventListener('click',()=>{state.reviewIndex=Number(button.dataset.reviewIndex);renderReview();})); document.querySelectorAll('[data-review-move]').forEach(button=>button.addEventListener('click',()=>{state.reviewIndex+=Number(button.dataset.reviewMove);renderReview();})); document.querySelectorAll('[data-review-action]').forEach(button=>button.addEventListener('click',()=>handleAction(reviewCases()[state.reviewIndex],button.dataset.reviewAction)));
}
