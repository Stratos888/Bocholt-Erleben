import {
  state, els, reviewLabels, escapeHtml, clean, asArray, formatDate,
  reviewGroup, allReviewCases, reviewCases,
} from './shared.js?v=2026-07-16-e2e-state-v5';

let handleAction = async () => {};
export function configureReviewRenderer(callbacks = {}) { if (callbacks.handleAction) handleAction = callbacks.handleAction; }
function contract(item) { return item.review_contract || item.decision_context?.review_contract || null; }

const evidenceLabels = {
  officially_verified:'Offiziell bestätigt', cross_checked:'Abgeglichen', format_validated:'Formal geprüft',
  system_suggestion:'Systemvorschlag', human_required:'Menschliche Prüfung', unverifiable:'Noch nicht belegbar', conflict:'Konflikt',
};
const confidenceLabels = { high:'hoch', medium:'mittel', low:'niedrig' };
const taskStateLabels = { open:'Offen', waiting_external:'Wartet auf Folgeprozess', candidate_ready:'Ergebnis prüfen', conflict:'Konflikt', failed_verification:'Prüfung fehlgeschlagen' };
const timeLabels = {
  fixed_time:'Feste Uhrzeit', all_day:'Ganztägig', during_opening_hours:'Während der Öffnungszeiten',
  multiple_times:'Mehrere Zeiten', time_not_published:'Noch nicht veröffentlicht', time_conflict:'Zeitkonflikt', time_not_applicable:'Nicht anwendbar',
};
function evidenceBadge(evidence={}) {
  const status=clean(evidence.status)||'human_required'; const confidence=clean(evidence.confidence);
  return `<span class="cc-evidence cc-evidence--${escapeHtml(status)}">${escapeHtml(evidenceLabels[status]||status)}${confidence?` · ${escapeHtml(confidenceLabels[confidence]||confidence)}`:''}</span>`;
}
function assetPreview(asset) {
  if (!asset?.src) return '';
  const label=clean(asset.label)||'Freigegebenes Eventbild';
  return `<figure class="cc-review-asset"><img src="${escapeHtml(asset.src)}" alt="${escapeHtml(asset.alt||'Vorgeschlagenes Eventbild')}" loading="lazy"><figcaption><strong>${escapeHtml(label)}</strong><span>${escapeHtml(asset.alt||'Freigegebenes symbolisches Eventbild.')}</span></figcaption></figure>`;
}
function taskActionButtons(task, className='') {
  const actions=asArray(task.actions);
  return `<div class="cc-review-task-actions ${className}">${actions.map(action=>`<button class="cc-button ${action.tone==='danger'?'cc-button--danger':action.tone==='secondary'?'cc-button--secondary':'cc-button--primary'}" data-review-task-id="${escapeHtml(task.task_id)}" data-review-task-revision="${escapeHtml(task.task_revision)}" data-review-task-resolution="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}</div>`;
}
function taskEvidence(evidence, asset) {
  const copy=evidence.explanation?`<div class="cc-review-evidence-copy"><strong>Evidenz</strong><span>${escapeHtml(evidence.explanation)}</span>${evidence.source_url?`<a href="${escapeHtml(evidence.source_url)}" target="_blank" rel="noopener">Relevante Quelle öffnen</a>`:''}</div>`:'';
  return `${copy}${assetPreview(asset)}`;
}
function renderTask(task) {
  const evidence=task.evidence||{}; const actions=asArray(task.actions);
  const asset=evidence.asset||evidence.fallback||null;
  const evidenceContent=taskEvidence(evidence,asset);
  return `<article class="cc-review-task cc-review-task--${escapeHtml(task.state||'open')}" data-review-task="${escapeHtml(task.task_id)}" tabindex="-1">
    <header><div><span class="cc-kicker">${escapeHtml(taskStateLabels[task.state]||task.state||'Offen')}</span><h3>${escapeHtml(task.title||'Prüfpunkt')}</h3></div>${evidenceBadge(evidence)}</header>
    <p>${escapeHtml(task.message||'')}</p>
    ${evidenceContent?`<div class="cc-review-task-evidence--desktop">${evidenceContent}</div><details class="cc-review-task-evidence--mobile"><summary>Evidence und Begründung</summary><div>${evidenceContent}</div></details>`:''}
    ${actions.length?`${taskActionButtons(task,'cc-review-task-actions--desktop')}<details class="cc-review-task-options"><summary>Weitere Optionen</summary>${taskActionButtons(task,'cc-review-task-actions--mobile')}</details>`:''}
  </article>`;
}
function renderGate(review) {
  const summary=review?.summary||{}; const gate=review?.decision_gate||{};
  if (gate.ready) return '<div class="cc-gate cc-gate--ready"><strong>Event entscheidungsreif</strong><span>Alle blockierenden Ausnahmen und Folgeprozesse sind verifiziert abgeschlossen. Prüfe jetzt die kompakte Gesamtfassung.</span></div>';
  return `<div class="cc-gate cc-gate--blocked"><strong>${escapeHtml(summary.headline||'Offene Punkte klären')}</strong><span>Bearbeite nur die folgenden Ausnahmen. Bereits bestätigte Angaben bleiben unverändert.</span></div>`;
}
function verifiedFact(label,value,evidence,url='') {
  if (!clean(value)) return '';
  const rendered=url?`<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(value)}</a>`:escapeHtml(value);
  return `<div><dt>${escapeHtml(label)}</dt><dd>${rendered}${evidence?evidenceBadge(evidence):''}</dd></div>`;
}
function verifiedFactsContent(review) {
  const facts=review.facts||{}, source=review.source||{}, visual=review.visual||{}, classification=review.classification||{}, evidence=review.field_evidence||{};
  const date=[facts.date?formatDate(facts.date):'',facts.end_date?`bis ${formatDate(facts.end_date)}`:''].filter(Boolean).join(' ');
  const time=facts.time_details||facts.time||timeLabels[facts.time_status]||'';
  const asset=visual.asset;
  const visualKey=visual.key_label||visual.key;
  const visualMotif=visual.motif_label||visual.motif;
  const visualAsset=visual.asset_label||(asset?'Freigegebenes Eventbild':'');
  return `<dl class="cc-fact-grid">${verifiedFact('Termin',date,evidence.date)}${verifiedFact('Uhrzeit',time,evidence.time_details||evidence.time||evidence.time_status)}${verifiedFact('Ort',[facts.location,facts.city].filter(Boolean).join(' · '),evidence.location)}${verifiedFact('Adresse',facts.address,evidence.address)}${verifiedFact('Kategorie',classification.category,evidence.category)}${verifiedFact('Prüfquelle',source.name||source.url,evidence.source_url,source.url)}${verifiedFact('Eventseite',source.event_url,evidence.event_url,source.event_url)}${verifiedFact('Ticket / Anmeldung',source.ticket_url,evidence.ticket_url,source.ticket_url)}${verifiedFact('Bildbereich',visualKey,evidence.visual_key)}${verifiedFact('Motiv',visualMotif,evidence.visual_motif)}${verifiedFact('Gebundenes Bild',visualAsset,evidence.visual_asset_id)}</dl>
    ${assetPreview(asset)}
    <section class="cc-detail-section"><h3>Finale Beschreibung</h3><p class="cc-description-copy">${escapeHtml(review.description?.final||'Keine Beschreibung vorhanden.')}</p></section>`;
}
function renderVerifiedFacts(review) {
  const content=verifiedFactsContent(review);
  return `<details class="cc-disclosure cc-reviewed-summary cc-reviewed-summary--desktop" ${review.decision_gate?.ready?'open':''}><summary>Geprüfte Gesamtfassung</summary>${content}</details><details class="cc-disclosure cc-reviewed-summary cc-reviewed-summary--mobile"><summary>Geprüfte Gesamtfassung</summary>${content}</details>`;
}
function duplicateTask(tasks) { return tasks.find(task=>task.task_id==='dedupe.decision'||task.finding_code==='hard_duplicate')||null; }
function comparisonFact(label,value,formatter=value=>value) {
  const cleaned=clean(value); if(!cleaned)return '';
  return `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(formatter(cleaned))}</dd></div>`;
}
function formatMatchScore(value) {
  const text=clean(value); if(!text)return '';
  if(text.includes('%'))return text;
  const numeric=Number(text.replace(',','.'));
  if(!Number.isFinite(numeric))return text;
  const percent=numeric<=1?numeric*100:numeric;
  return `${Number.isInteger(percent)?percent:percent.toFixed(1).replace('.',',')} %`;
}
function renderDuplicateComparison(review,tasks) {
  const task=duplicateTask(tasks); if(!task)return '';
  const evidence=task.evidence||{}, facts=review.facts||{};
  const candidateLocation=[facts.location,facts.city].filter(Boolean).join(' · ');
  const score=formatMatchScore(evidence.score||review.dedupe?.score);
  const sourceUrl=clean(evidence.source_url||review.dedupe?.url);
  return `<section class="cc-duplicate-comparison" aria-label="Kandidat und bestehendes Event vergleichen">
    <header><div><span class="cc-kicker">Treffervergleich</span><strong>Kandidat und Bestand</strong></div>${score?`<span class="cc-match-score">Match ${escapeHtml(score)}</span>`:''}</header>
    <div class="cc-duplicate-comparison__columns">
      <section><span class="cc-duplicate-comparison__label">Kandidat</span><dl>${comparisonFact('Titel',facts.title)}${comparisonFact('Datum',facts.date,formatDate)}${comparisonFact('Ort',candidateLocation)}</dl></section>
      <section><span class="cc-duplicate-comparison__label">Bestehendes Event</span><dl>${comparisonFact('Titel',evidence.matched_title)}${comparisonFact('Datum',evidence.matched_date,formatDate)}${comparisonFact('Ort',evidence.matched_location)}</dl>${sourceUrl?`<a href="${escapeHtml(sourceUrl)}" target="_blank" rel="noopener">Bestehendes Event öffnen</a>`:''}</section>
    </div>
  </section>`;
}
function trueWaiting(item,tasks) {
  if(clean(item?.waiting_for))return true;
  return tasks.length>0&&tasks.every(task=>task.state==='waiting_external');
}
function renderMobileDecision(review,item,tasks) {
  if(review?.decision_gate?.ready)return '<section class="cc-mobile-decision cc-mobile-decision--ready"><span>Bereit zur Übernahme</span><button class="cc-button cc-button--primary cc-button--large" data-review-action="approve">Event übernehmen</button></section>';
  if(trueWaiting(item,tasks))return `<section class="cc-mobile-decision cc-mobile-decision--waiting"><strong>Folgeprozess läuft</strong><span>${escapeHtml(item?.waiting_for||'Für diesen Fall ist aktuell keine Entscheidung erforderlich.')}</span></section>`;
  const task=tasks.find(entry=>entry.state!=='waiting_external'&&asArray(entry.actions).length)||tasks.find(entry=>asArray(entry.actions).length);
  if(!task)return '<section class="cc-mobile-decision cc-mobile-decision--waiting"><strong>Keine unmittelbare Aktion</strong><span>Der Fall bleibt sichtbar, bis eine belastbare Entscheidung möglich ist.</span></section>';
  const actions=asArray(task.actions);
  if(actions.length===1)return `<section class="cc-mobile-decision"><span>${escapeHtml(task.title||'Nächsten Prüfpunkt entscheiden')}</span>${taskActionButtons(task,'cc-review-task-actions--focus')}</section>`;
  return `<details class="cc-mobile-decision cc-mobile-decision--choices"><summary>Entscheidung auswählen</summary><div><span class="cc-mobile-decision__context">${escapeHtml(task.title||'Nächsten Prüfpunkt entscheiden')}</span>${taskActionButtons(task,'cc-review-task-actions--focus')}</div></details>`;
}
export function renderEventReview(review,item={}) {
  if (!review) return '';
  const tasks=asArray(review.review_tasks);
  return `<section class="cc-review-data"><div class="cc-mobile-priority">${renderDuplicateComparison(review,tasks)}${renderMobileDecision(review,item,tasks)}</div>${renderGate(review)}${tasks.length?`<section class="cc-review-task-list" aria-label="Offene Prüfaufgaben">${tasks.map(renderTask).join('')}</section>`:''}${renderVerifiedFacts(review)}</section>`;
}
function generic(item) {
  const context=item.decision_context||{}; const problem=clean(context.issue_text||item.reason); const recommended=clean(context.recommended_action||item.next_action); const current=clean(context.current_description||context.description); const suggested=clean(context.suggested_description);
  return `${problem?`<section class="cc-detail-section"><h3>Worum es geht</h3><p>${escapeHtml(problem)}</p></section>`:''}${recommended?`<section class="cc-detail-section cc-detail-section--accent"><h3>Erforderlicher Schritt</h3><p>${escapeHtml(recommended)}</p></section>`:''}${current||suggested?`<div class="cc-copy-compare"><section><span class="cc-kicker">Aktuell</span><p>${escapeHtml(current||'Keine aktuelle Fassung.')}</p></section>${suggested?`<section class="cc-copy-compare__proposal"><span class="cc-kicker">Vorschlag</span><p>${escapeHtml(suggested||'Kein Vorschlag verfügbar.')}</p></section>`:''}</div>`:''}`;
}
function actions(item) {
  if(item.case_kind==='event_candidate') {
    const review=contract(item); const ready=Boolean(review?.decision_gate?.ready);
    return {primary:ready?{key:'approve',label:'Event übernehmen'}:{key:'resolve_review_task',label:review?.summary?.headline||'Offene Punkte klären'},secondary:[...(ready?[{key:'edit_and_approve',label:'Gesamtfassung bearbeiten'}]:[]),{key:'snooze',label:'Gesamten Fall zurückstellen'},{key:'reject',label:'Event ablehnen',destructive:true},{key:'details',label:'Quelldaten'}]};
  }
  if(String(item.case_kind||'').includes('correction')) return {primary:{key:'edit_and_approve',label:item.primary_action?.label||'Korrigieren und übernehmen'},secondary:[{key:'snooze',label:'Zurückstellen'},{key:'reject',label:'Ablehnen',destructive:true},{key:'details',label:'Details'}]};
  const secondary=[...asArray(item.secondary_actions),{key:'details',label:'Details'}].filter((entry,index,list)=>entry?.key&&list.findIndex(item=>item.key===entry.key)===index);
  return {primary:item.primary_action,secondary};
}
function secondaryActions(available,links,className='') {
  return `<div class="cc-actions cc-actions--secondary ${className}">${available.secondary.map(action=>`<button class="cc-button ${action.destructive?'cc-button--danger':'cc-button--secondary'}" data-review-action="${escapeHtml(action.key)}">${escapeHtml(action.label)}</button>`).join('')}${links}</div>`;
}
function detail(item,index,total) {
  const available=actions(item); const links=asArray(item.source_links).map(link=>`<a class="cc-button cc-button--secondary" href="${escapeHtml(link.url)}" target="_blank" rel="noopener">${escapeHtml(link.label)}</a>`).join(''); const review=contract(item); const body=item.case_kind==='event_candidate'?renderEventReview(review,item):generic(item);
  const blockerCount=Number(review?.summary?.open_task_count||0);
  const secondary=secondaryActions(available,links,'cc-actions--secondary-desktop');
  const mobileSecondary=`<details class="cc-mobile-case-options"><summary>Weitere Falloptionen und Quellen</summary>${secondaryActions(available,links,'cc-actions--secondary-mobile')}</details>`;
  return `<article class="cc-work-detail" data-case-id="${escapeHtml(item.id)}" data-case-kind="${escapeHtml(item.case_kind||'')}"><header class="cc-work-head"><div><span class="cc-kicker">${escapeHtml(reviewLabels[reviewGroup(item)]||'Prüfung')} · ${index+1} von ${total}</span><h2>${escapeHtml(item.title)}</h2></div><div class="cc-work-head__status"><span class="cc-pill">${escapeHtml(item.display_status)}</span>${item.case_kind==='event_candidate'?`<span class="cc-mobile-blocker-count">${blockerCount===0?'Keine offenen Punkte':`${blockerCount} ${blockerCount===1?'offener Punkt':'offene Punkte'}`}</span>`:''}</div></header>${item.waiting_for?`<div class="cc-notice cc-notice--info"><strong>Folgeprozess aktiv</strong><span>${escapeHtml(item.waiting_for)}</span></div>`:''}${body}<div class="cc-action-primary ${item.case_kind==='event_candidate'?'cc-action-primary--event-candidate':''}">${available.primary?`<button class="cc-button cc-button--primary cc-button--large" data-review-action="${escapeHtml(available.primary.key)}">${escapeHtml(available.primary.label)}</button>`:`<div class="cc-empty">${escapeHtml(item.waiting_for||'Aktuell keine Aktion erforderlich.')}</div>`}</div>${secondary}${mobileSecondary}<footer class="cc-work-nav"><button class="cc-button cc-button--ghost" data-review-move="-1" ${index===0?'disabled':''}>‹ Zurück</button><button class="cc-button cc-button--ghost" data-review-move="1" ${index>=total-1?'disabled':''}>Weiter ›</button></footer></article>`;
}
function count(key){return key==='all'?allReviewCases().length:allReviewCases().filter(item=>reviewGroup(item)===key).length;}
export function renderReview() {
  const groups=Object.entries(reviewLabels).filter(([key])=>key==='all'||count(key)>0); if(!groups.some(([key])=>key===state.reviewGroup))state.reviewGroup='all'; const items=reviewCases(); if(state.reviewIndex>=items.length)state.reviewIndex=Math.max(0,items.length-1);
  const queue=items.map((item,index)=>`<button class="cc-queue-item ${index===state.reviewIndex?'is-active':''}" data-review-index="${index}"><span>${escapeHtml(item.title)}</span><small>${escapeHtml(reviewLabels[reviewGroup(item)]||item.display_status)}</small></button>`).join('');
  const pills=groups.map(([key,label])=>`<button class="cc-filter ${state.reviewGroup===key?'is-active':''}" data-review-group="${key}">${escapeHtml(label)} (${count(key)})</button>`).join('');
  const options=groups.map(([key,label])=>`<option value="${key}" ${state.reviewGroup===key?'selected':''}>${escapeHtml(label)} (${count(key)})</option>`).join('');
  els.view.innerHTML=`<div class="cc-review-controls"><div class="cc-filter-row cc-review-pills">${pills}</div><label class="cc-work-filter cc-review-select"><span>Prüfbereich</span><select id="cc-review-select">${options}</select></label></div>${items.length?`<div class="cc-work-layout"><aside class="cc-queue">${queue}</aside><main>${detail(items[state.reviewIndex],state.reviewIndex,items.length)}</main></div>`:'<div class="cc-empty cc-empty--large">In diesem Bereich gibt es aktuell nichts zu prüfen.</div>'}`;
  document.querySelectorAll('details.cc-mobile-decision, details.cc-review-task-evidence--mobile, details.cc-review-task-options, details.cc-reviewed-summary--mobile, details.cc-mobile-case-options').forEach(details=>{const summary=details.querySelector(':scope > summary');if(!summary)return;const syncExpanded=()=>summary.setAttribute('aria-expanded',details.open?'true':'false');syncExpanded();details.addEventListener('toggle',syncExpanded);});
  document.querySelectorAll('[data-review-group]').forEach(button=>button.addEventListener('click',()=>{state.reviewGroup=button.dataset.reviewGroup;state.reviewIndex=0;renderReview();})); document.querySelector('#cc-review-select')?.addEventListener('change',event=>{state.reviewGroup=event.target.value;state.reviewIndex=0;renderReview();}); document.querySelectorAll('[data-review-index]').forEach(button=>button.addEventListener('click',()=>{state.reviewIndex=Number(button.dataset.reviewIndex);renderReview();})); document.querySelectorAll('[data-review-move]').forEach(button=>button.addEventListener('click',()=>{state.reviewIndex+=Number(button.dataset.reviewMove);renderReview();})); document.querySelectorAll('[data-review-action]').forEach(button=>button.addEventListener('click',()=>handleAction(reviewCases()[state.reviewIndex],button.dataset.reviewAction)));
  document.querySelectorAll('[data-review-task-resolution]').forEach(button=>button.addEventListener('click',()=>handleAction(reviewCases()[state.reviewIndex],'resolve_review_task',{taskId:button.dataset.reviewTaskId,taskRevision:button.dataset.reviewTaskRevision,resolution:button.dataset.reviewTaskResolution})));
}
