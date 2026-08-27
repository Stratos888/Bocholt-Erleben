import {
  escapeHtml, clean, asArray, formatDate, formatDateTime, api, openDialog,
  closeDialog, dialogMessage, field, textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const itemLabels = {
  terms_confirmed: 'Pilotbedingungen ausdrücklich bestätigt', organizer_linked: 'Veranstalterzugang zugeordnet',
  contact_confirmed: 'Ansprechperson bestätigt', portal_access_tested: 'Partnerzugang genutzt',
  pilot_entitlement_readback: 'Pilotfreigabe geprüft', service_scope_confirmed: 'Vereinbarter Umfang bestätigt',
  sources_recorded: 'Inhaltsquellen hinterlegt', maintenance_path_agreed: 'Laufende Pflege geklärt',
  content_rights_cleared: 'Nutzungsrechte bestätigt', first_content_ready: 'Erster Inhalt vorbereitet',
  editorial_review_ready: 'Redaktionelle Prüfung vorbereitet', measurement_ready: 'Erfolgsmessung technisch geprüft',
  distribution_ready: 'Reichweitenbeitrag vereinbart', activation_target_set: 'Startdatum wird beim Pilotstart festgelegt',
};
const statusLabels = {pending:'Offen',complete:'Erledigt',blocked:'Klärung nötig',not_applicable:'Nicht erforderlich'};
const contentStatusLabels = {draft:'Zur Prüfung eingereicht',editorial_ready:'Redaktionell vorbereitet',approved:'Freigegeben',rejected:'Nicht freigegeben',withdrawn:'Zurückgezogen'};
const checkpointLabels = {day_30:'30-Tage-Checkpoint',day_90:'90-Tage-Checkpoint',month_5:'Monat-5-Checkpoint',final:'Abschluss-Checkpoint'};

export function gate4PhaseLabel(phase) {
  return ({
    onboarding:'Piloteinrichtung',activation_ready:'Bereit zum Start',active:'Pilotphase läuft',
    paused:'Pilot pausiert',closing:'Pilotabschluss',ended_without_conversion:'Pilot beendet',
    terminated:'Pilot abgebrochen',converted:'Pilot abgeschlossen',
  })[phase] || 'Piloteinrichtung';
}

export function gate4PriorityMessage(gate4 = {}) {
  return clean(gate4.next_action?.label)
    || (gate4.activation_ready ? 'Alle Voraussetzungen sind erfüllt. Der Pilot kann jetzt gestartet werden.'
      : gate4.blockers?.[0]?.message || 'Der nächste fachliche Schritt ist noch offen.');
}

function itemRow(row, locked) {
  const key=clean(row.item_key),status=clean(row.status)||'pending';
  const canEdit=Number(row.is_manual||0)===1&&!locked;
  const evidence=clean(row.evidence_text||row.evidence_reference);
  return `<article class="cc-startpartner-dimension cc-startpartner-dimension--${escapeHtml(status)}" data-gate4-item="${escapeHtml(key)}">
    <header><strong>${escapeHtml(itemLabels[key]||key)}</strong><span>${escapeHtml(statusLabels[status]||status)}</span></header>
    ${evidence?`<small>${escapeHtml(evidence)}</small>`:'<small>Noch kein Nachweis vorhanden.</small>'}
    ${canEdit?`<div class="cc-actions"><button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:complete">Als erledigt speichern</button><button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:blocked">Klärungsbedarf erfassen</button></div>`:''}
  </article>`;
}

function contentRows(rows = []) {
  if(!rows.length)return '<p class="cc-muted">Noch kein Inhalt für den Pilot eingereicht.</p>';
  return `<div class="cc-stack">${rows.map(row=>`<article class="cc-startpartner-state-card">
    <span class="cc-kicker">${escapeHtml(row.content_type==='activity'?'Aktivität':'Veranstaltung')}</span>
    <strong>${escapeHtml(row.title||`Einreichung ${row.submission_id||''}`)}</strong>
    <p>${escapeHtml(contentStatusLabels[row.status]||row.status||'In Bearbeitung')}${row.start_date?` · ${escapeHtml(formatDate(row.start_date))}`:''}</p>
  </article>`).join('')}</div>`;
}

function limitSummary(gate4={}) {
  const event=gate4.limits?.event,activity=gate4.limits?.activity,rows=[];
  if(event?.available)rows.push(`<div><dt>Events im Pilotmonat</dt><dd>${event.is_unlimited?'vereinbarter Rahmen':`${Number(event.used||0)} / ${Number(event.limit||0)}`}${event.full&&event.reset_date_local?` · neuer Monat ab ${escapeHtml(formatDate(event.reset_date_local))}`:''}</dd></div>`);
  if(activity?.available)rows.push(`<div><dt>Aktivitäten gleichzeitig</dt><dd>${Number(activity.used||0)} / ${Number(activity.limit||0)}</dd></div>`);
  return rows.join('');
}

function runtimeSummary(gate4={}) {
  const measurement=gate4.measurement_runtime||{},distribution=gate4.distribution_runtime||{};
  const measurementText=({usage_observed:'Nutzung vorhanden',zero_usage:'Belastbar 0 Nutzung',no_data_yet_or_too_short:'Noch keine belastbaren abgeschlossenen Daten',query_or_attribution_problem:'Technische Zuordnung prüfen',technical_not_ready:'Technische Prüfung noch offen'})[measurement.status]||'Noch offen';
  const distributionText=({planned:'Vereinbart, noch nicht fällig',due:'Fällig',blocked:'Klärung nötig',completed:'Erfüllt',cancelled:'Entfallen',not_planned:'Noch nicht vereinbart'})[distribution.status]||'Noch offen';
  return `<section class="cc-startpartner-grid"><section class="cc-startpartner-panel"><span class="cc-kicker">Erfolgsmessung</span><h3>${escapeHtml(measurementText)}</h3><p>${measurement.status==='usage_observed'?`${Number(measurement.observed_actions||0)} erfasste Plattformaktionen in abgeschlossenen Tageswerten.`:measurement.status==='zero_usage'?'Mindestens ein abgeschlossener Tageswert liegt vor; die Summe ist 0.':measurement.status==='no_data_yet_or_too_short'?'Fehlende Tageswerte werden nicht als Nullnutzung gewertet.':'Der aktuelle technische Stand wird aus dem bestehenden Messpfad gelesen.'}</p></section><section class="cc-startpartner-panel"><span class="cc-kicker">Reichweitenbeitrag</span><h3>${escapeHtml(distributionText)}</h3><p>${distribution.commitment?.channel?`${escapeHtml(distribution.commitment.channel)} · Zieltermin ${escapeHtml(formatDate(distribution.commitment.planned_at)||'–')}`:'Der vereinbarte Beitrag wird getrennt von seiner späteren Erfüllung geführt.'}</p></section></section>`;
}

function scopeRepairBlockers(gate4={}){return asArray(gate4.blockers).filter(blocker=>blocker?.code==='scope_target_plan_mismatch');}
function firstRequiredBlocker(gate4={}){return asArray(gate4.blockers).find(blocker=>blocker?.code!=='scope_target_plan_mismatch')||null;}
function berlinToday(){const parts=new Intl.DateTimeFormat('en-CA',{timeZone:'Europe/Berlin',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(new Date());const values=Object.fromEntries(parts.map(part=>[part.type,part.value]));return `${values.year}-${values.month}-${values.day}`;}

function preactivationNextAction(gate4={}) {
  const blocker=firstRequiredBlocker(gate4),first=gate4.first_content||null;
  if(blocker?.item_key==='portal_access_tested')return {code:'wait_portal',label:'Warten auf Partnerzugang'};
  if(!first)return {code:'wait_first_content',label:'Warten auf ersten Partnerinhalt'};
  if(first.status==='draft')return {code:'content_review',label:'Nächsten Inhalt redaktionell vorbereiten',action:'mark_content_ready',content_link_id:first.id};
  const firstReady=['editorial_ready','approved'].includes(first.status);
  if(firstReady&&!gate4.ready_measurement)return {code:'measurement_problem',label:'Technische Erfolgsmessung prüfen',action:'measurement'};
  if(firstReady&&!gate4.ready_distribution)return {code:'distribution_setup',label:'Reichweitenbeitrag vereinbaren',action:'distribution_setup'};
  if(gate4.activation_ready)return {code:'activate',label:'Pilot jetzt starten',action:'activate'};
  return gate4.next_action||{code:'onboarding',label:gate4PriorityMessage(gate4)};
}

function controlNextAction(gate4={}) {
  const phase=clean(gate4.phase);
  if(['onboarding','activation_ready'].includes(phase))return preactivationNextAction(gate4);
  return gate4.next_action||{code:'onboarding',label:gate4PriorityMessage(gate4)};
}

function nextActionMarkup(gate4={}) {
  const scopeMismatches=scopeRepairBlockers(gate4);
  if(scopeMismatches.length&&!['active','paused','closing'].includes(gate4.phase))return `<div class="cc-notice cc-notice--attention"><strong>Zielmodell-Zuordnung blockiert</strong><span>Die gespeicherte Event-/Aktivitätszuordnung muss vor weiteren Pilotschritten korrigiert werden.</span></div><button class="cc-button cc-button--primary" data-review-action="gate4:repair-scope">Zielmodell-Zuordnung reparieren</button>`;
  const next=controlNextAction(gate4);
  const button=(()=>{switch(next.action){case'activate':return '<button class="cc-button cc-button--primary cc-button--large" data-review-action="gate4:activate">Pilot jetzt starten</button>';case'start_closeout':return '<button class="cc-button cc-button--primary" data-review-action="gate4:lifecycle:start_closeout">Pilotabschluss einleiten</button>';case'end_without_conversion':return '<button class="cc-button cc-button--primary" data-review-action="gate4:lifecycle:end_without_conversion">Pilot geordnet beenden</button>';case'resume':return '<button class="cc-button cc-button--primary" data-review-action="gate4:lifecycle:resume">Pilot fortsetzen</button>';case'complete_checkpoint':return `<button class="cc-button cc-button--primary" data-review-action="gate4:checkpoint:${escapeHtml(next.checkpoint_key)}">Checkpoint abschließen</button>`;case'measurement':return '<button class="cc-button cc-button--primary" data-review-action="gate4:measurement">Technische Prüfung erneut ausführen</button>';case'distribution_setup':return '<button class="cc-button cc-button--primary" data-review-action="gate4:distribution">Reichweitenbeitrag vereinbaren</button>';case'set_distribution_fulfillment':return `<button class="cc-button cc-button--primary" data-review-action="gate4:distribution-fulfillment:${escapeHtml(next.distribution_id)}">Reichweitenbeitrag klären</button>`;case'mark_content_ready':return `<button class="cc-button cc-button--primary" data-review-action="gate4:content-ready:${escapeHtml(next.content_link_id)}">Redaktionell vorbereiten</button>`;case'approve_content':return `<button class="cc-button cc-button--primary" data-review-action="gate4:content-approve:${escapeHtml(next.content_link_id)}">Pilotinhalt freigeben</button>`;default:return'';}})();
  const tone=next.code==='none'?'success':['closeout_required','measurement_problem'].includes(next.code)?'attention':'info';
  return `<div class="cc-notice cc-notice--${tone}"><strong>Nächste Aktion</strong><span>${escapeHtml(next.label||gate4PriorityMessage(gate4))}</span></div>${button}`;
}

function protectedControls(gate4={}) {
  const phase=clean(gate4.phase),buttons=[];
  if(phase==='active')buttons.push('<button class="cc-button cc-button--secondary" data-review-action="gate4:lifecycle:pause">Pilot pausieren</button>','<button class="cc-button cc-button--secondary" data-review-action="gate4:lifecycle:start_closeout">Abschluss vorzeitig einleiten</button>','<button class="cc-button cc-button--danger" data-review-action="gate4:lifecycle:terminate">Pilot abbrechen</button>');
  if(phase==='paused')buttons.push('<button class="cc-button cc-button--secondary" data-review-action="gate4:lifecycle:start_closeout">Abschluss einleiten</button>','<button class="cc-button cc-button--danger" data-review-action="gate4:lifecycle:terminate">Pilot abbrechen</button>');
  if(phase==='closing')buttons.push('<button class="cc-button cc-button--danger" data-review-action="gate4:lifecycle:terminate">Stattdessen abbrechen</button>');
  if(!buttons.length)return'';
  return `<details class="cc-disclosure"><summary>Pilot steuern</summary><p class="cc-muted">Diese Aktionen ändern den Pilotstatus. Sie lösen keine Zahlung und keine automatische kostenpflichtige Fortführung aus; historische Freigabe- und Nutzungsnachweise bleiben erhalten.</p><div class="cc-actions">${buttons.join('')}</div></details>`;
}

export function renderGate4Panel(candidate={}) {
  const gate4=candidate.gate4||{},pilot=gate4.pilot;if(!pilot)return'';
  const onboarding=gate4.onboarding||{},first=gate4.first_content||{};
  const locked=['active','paused','closing','ended_without_conversion','terminated','converted'].includes(gate4.phase);
  return `<section class="cc-startpartner-panel" data-gate4-panel><header><div><span class="cc-kicker">Einrichtung und Pilotbetrieb</span><h3>${escapeHtml(gate4PhaseLabel(gate4.phase))}</h3></div><span class="cc-pill">${escapeHtml(gate4.phase==='onboarding'||gate4.phase==='activation_ready'?`${Number(onboarding.completed_count||0)} von ${Number(onboarding.total_count||14)} geprüft`:gate4PhaseLabel(gate4.phase))}</span></header>
    <dl class="cc-startpartner-facts"><div><dt>Pilotstart</dt><dd>${escapeHtml(pilot.activation_date_local?formatDate(pilot.activation_date_local):'Noch nicht gestartet')}</dd></div><div><dt>Geplantes Ende</dt><dd>${escapeHtml(pilot.planned_end_date?formatDate(pilot.planned_end_date):'Wird beim Start berechnet')}</dd></div><div><dt>Erster Inhalt</dt><dd>${escapeHtml(contentStatusLabels[first.status]||first.status||'Noch nicht vorbereitet')}</dd></div>${limitSummary(gate4)}</dl>
    ${nextActionMarkup(gate4)}
    ${protectedControls(gate4)}
    <details class="cc-disclosure"><summary>Prüfdetails</summary><div class="cc-startpartner-qualification-group"><div>${asArray(onboarding.items).map(row=>itemRow(row,locked)).join('')}</div></div></details>
    <details class="cc-disclosure"><summary>Inhalte im Pilot</summary>${contentRows(gate4.content_links)}</details>
    ${runtimeSummary(gate4)}
  </section>`;
}

async function latest(item){return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`,{timeoutMs:15000});}
function addCalendarMonths(valueText,months=6){const match=/^(\d{4})-(\d{2})-(\d{2})$/.exec(clean(valueText));if(!match)return'';const year=Number(match[1]),month=Number(match[2]),day=Number(match[3]),monthIndex=year*12+month-1+months,targetYear=Math.floor(monthIndex/12),targetMonth=monthIndex%12,lastDay=new Date(Date.UTC(targetYear,targetMonth+1,0)).getUTCDate();return `${targetYear}-${String(targetMonth+1).padStart(2,'0')}-${String(Math.min(day,lastDay)).padStart(2,'0')}`;}
function operator(data){return clean(data.assigned_to)||'Steuerzentrale';}

async function mutate(path,data,payload,reload,success){try{const result=await api(path,{method:'POST',body:JSON.stringify({candidate_id:data.id,operation_id:`gate4:344:${operationId().replace(/^cc:/,'')}`,expected_revision:Number(data.revision),expected_pilot_revision:Number(data.gate4?.pilot?.revision||0),operator_name:operator(data),...payload}),timeoutMs:70000});await reload({throwOnError:true});closeDialog();setStatus(success,'success');return result;}catch(error){if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');return null;}dialogMessage(error.message||'Die Änderung konnte nicht gespeichert werden.');return null;}}
function confirmButton(label,tone='primary'){return `<button type="button" class="cc-button cc-button--${tone}" id="gate4-confirm">${escapeHtml(label)}</button>`;}

async function scopeRepairDialog(item,reload){const detail=await latest(item),data=detail.startpartner_candidate,mismatches=scopeRepairBlockers(data.gate4||{});if(!mismatches.length){setStatus('Die Zielmodell-Zuordnung ist bereits konsistent. Es wurde nichts geändert.','success');return;}const rows=mismatches.map(m=>`<li><strong>${escapeHtml(m.scope_key||'Bereich')}</strong>: ${escapeHtml(m.actual_target_plan_key||'ohne Zuordnung')} → ${escapeHtml(m.expected_target_plan_key||'erwartetes Zielmodell')}</li>`).join('');openDialog(`<h2>Zielmodell-Zuordnung reparieren</h2><p>Korrigiert wird ausschließlich die technische Zuordnung der noch nicht aktivierten Inhaltsbereiche.</p><ul>${rows}</ul><div class="cc-notice cc-notice--info"><strong>Keine externe Wirkung</strong><span>Es wird nichts versendet, veröffentlicht oder bezahlt und der Pilot startet nicht.</span></div><div id="cc-dialog-message"></div>${confirmButton('Zielmodell-Zuordnung reparieren')}`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/onboarding.php',data,{action:'repair_scope_target_plans'},reload,'Zielmodell-Zuordnung repariert und aktueller Pilotstand neu geprüft.');if(!result)event.currentTarget.disabled=false;});}

async function manualItemDialog(item,key,status,reload){const detail=await latest(item),data=detail.startpartner_candidate,label=itemLabels[key]||key,blocked=status==='blocked';openDialog(`<h2>${escapeHtml(label)}</h2><p>${blocked?'Beschreibe, was noch geklärt werden muss und was als Nächstes passiert.':'Hinterlege den fachlichen Nachweis für diesen historischen Schritt.'}</p><div id="cc-dialog-message"></div><div class="cc-stack">${textarea('gate4-evidence',blocked?'Offener Punkt und nächster Schritt':'Nachweis','','required')}${confirmButton(blocked?'Klärungsbedarf speichern':'Als erledigt speichern',blocked?'danger':'primary')}</div>`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/onboarding.php',data,{action:'update_item',item_key:key,status,evidence_text:value('#gate4-evidence')},reload,blocked?'Klärungsbedarf gespeichert.':'Schritt als erledigt gespeichert.');if(!result)event.currentTarget.disabled=false;});}

async function contentReadyDialog(item,contentLinkId,reload){const detail=await latest(item),data=detail.startpartner_candidate,row=asArray(data.gate4?.content_links).find(entry=>String(entry.id)===contentLinkId),active=data.gate4?.phase==='active';openDialog(`<h2>${active?'Pilotinhalt redaktionell vorbereiten':'Inhalt für den Pilotstart vorbereiten'}</h2><p>${escapeHtml(row?.title||'Ausgewählter Inhalt')}</p><div class="cc-notice cc-notice--info"><strong>Noch nicht freigegeben</strong><span>${active?'Die Vorbereitung verbraucht noch keine Pilot-Einheit. Erst die ausdrückliche Freigabe zählt.':'Der Inhalt wird vorbereitet und die technische Messzuordnung automatisch geprüft; der Pilot startet dadurch noch nicht.'}</span></div><div id="cc-dialog-message"></div>${confirmButton('Redaktionell vorbereiten')}`);document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/onboarding.php',data,{action:'mark_content_ready',content_link_id:contentLinkId},reload,'Der Inhalt ist redaktionell vorbereitet und der aktuelle Stand wurde neu geprüft.');if(!result)event.currentTarget.disabled=false;});}

async function measurementDialog(item,reload){const detail=await latest(item),data=detail.startpartner_candidate,rows=asArray(data.gate4?.content_links).filter(row=>['editorial_ready','approved'].includes(row.status));if(!rows.length){setStatus('Für die technische Prüfung muss zuerst ein Inhalt vorbereitet sein.','attention');return;}const content=data.gate4?.first_content||rows[0];openDialog(`<h2>Technische Erfolgsmessung erneut prüfen</h2><p>Es werden keine Testwerte erzeugt. Das System prüft ausschließlich die bestehende Zuordnung.</p><div id="cc-dialog-message"></div>${confirmButton('Technische Prüfung erneut ausführen')}`);document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/onboarding.php',data,{action:'set_measurement',status:'ready',content_link_id:content.id,evidence_text:'Technische Wiederholungsprüfung aus der Steuerzentrale.'},reload,'Die Erfolgsmessung ist technisch geprüft.');if(!result)event.currentTarget.disabled=false;});}

async function distributionDialog(item,reload){const detail=await latest(item),data=detail.startpartner_candidate,current=data.gate4?.ready_distribution||asArray(data.gate4?.distribution_commitments)[0]||{},planned=clean(current.planned_at).slice(0,10);openDialog(`<h2>Reichweitenbeitrag vereinbaren</h2><p>Halte Kanal und Zieltermin fest. Das ist noch kein Nachweis der späteren Erfüllung.</p><div id="cc-dialog-message"></div><div class="cc-stack">${field('gate4-channel','Vereinbarter Kanal',current.channel||'','text','required')}${field('gate4-target','Ziel-Link, Profil oder Kampagne',current.target_reference||'','text','required')}${field('gate4-date','Vereinbarter Zieltermin',planned||berlinToday(),'date','required')}<label class="cc-field"><span>Stand</span><select id="gate4-distribution-status"><option value="ready">Mit Partner vereinbart</option><option value="blocked">Noch Klärung nötig</option></select></label>${textarea('gate4-distribution-evidence','Kurze Notiz zur Vereinbarung',current.evidence_text||'','required')}${confirmButton('Vereinbarung speichern')}</div>`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/onboarding.php',data,{action:'set_distribution',status:value('#gate4-distribution-status'),channel:value('#gate4-channel'),target_reference:value('#gate4-target'),planned_at:value('#gate4-date'),evidence_text:value('#gate4-distribution-evidence')},reload,'Reichweitenbeitrag als Vereinbarung gespeichert.');if(!result)event.currentTarget.disabled=false;});}

async function activationDialog(item,reload){const detail=await latest(item),data=detail.startpartner_candidate;if(!data.gate4?.activation_ready){setStatus(gate4PriorityMessage(data.gate4||{}),'attention');return;}const defaultDate=berlinToday();openDialog(`<h2>Startpartner-Pilot starten</h2><div class="cc-notice cc-notice--info"><strong>Was beim Start passiert</strong><span>Das Startdatum beginnt die sechsmonatige Laufzeit. Der erste Inhalt und die Pilotfreigabe werden aktiviert. Es wird keine Zahlung ausgelöst.</span></div><div id="cc-dialog-message"></div><div class="cc-stack">${field('gate4-activation-date','Startdatum',defaultDate,'date','required')}<dl class="cc-startpartner-facts"><div><dt>Geplantes Ende</dt><dd id="gate4-end-preview">${escapeHtml(formatDate(addCalendarMonths(defaultDate)))}</dd></div></dl>${confirmButton('Pilot jetzt starten')}</div>`,'cc-dialog--wide');document.querySelector('#gate4-activation-date')?.addEventListener('change',event=>{const preview=document.querySelector('#gate4-end-preview');if(preview)preview.textContent=formatDate(addCalendarMonths(event.target.value));});document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/activation.php',data,{activation_date_local:value('#gate4-activation-date')},reload,'Der Pilot ist gestartet und der aktuelle Stand wurde neu geladen.');if(!result)event.currentTarget.disabled=false;});}

async function contentApproveDialog(item,contentLinkId,reload){const detail=await latest(item),data=detail.startpartner_candidate,row=asArray(data.gate4?.content_links).find(entry=>String(entry.id)===contentLinkId);openDialog(`<h2>Pilotinhalt freigeben</h2><p>${escapeHtml(row?.title||'Ausgewählter Inhalt')}</p><div class="cc-notice cc-notice--attention"><strong>Freigabe mit echter Sichtbarkeitswirkung</strong><span>Bei Erfolg wird der Inhalt für die öffentliche Event-/Aktivitätsprojektion freigegeben und genau eine Pilotnutzung verbucht. Laufzeit und Limit werden serverseitig erneut geprüft; Zahlung oder Partner-Mail werden nicht ausgelöst.</span></div><div id="cc-dialog-message"></div>${confirmButton('Inhalt freigeben')}`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/lifecycle.php',data,{action:'approve_content',content_link_id:contentLinkId},reload,'Pilotinhalt freigegeben, öffentlich projektionsberechtigt und einmalig dem Pilotverbrauch zugeordnet.');if(!result)event.currentTarget.disabled=false;});}

async function lifecycleDialog(item,transition,reload){const detail=await latest(item),data=detail.startpartner_candidate,config={pause:['Pilot pausieren','Während der Pause bleiben bereits freigegebene Pilotinhalte öffentlich sichtbar und historische Nutzungen unverändert. Neue Pilotinhalte sind gesperrt.','Pilot pausieren','secondary'],resume:['Pilot fortsetzen','Der Pilot wird nur fortgesetzt, wenn seine wirksame Laufzeit noch besteht.','Pilot fortsetzen','primary'],start_closeout:['Pilotabschluss einleiten','Im Abschlusszustand bleiben bereits freigegebene Pilotinhalte bis zur endgültigen Beendigung sichtbar; neue Pilotinhalte sind gesperrt.','Abschluss einleiten','primary'],end_without_conversion:['Pilot geordnet beenden','Der Pilot endet ohne automatische kostenpflichtige Fortführung. Seine öffentlichen Pilotprojektionen enden; historische Freigabe- und Nutzungsnachweise bleiben erhalten.','Pilot beenden','primary'],terminate:['Pilot abbrechen','Der Pilot wird kontrolliert abgebrochen. Seine öffentlichen Pilotprojektionen enden; historische Freigabe- und Nutzungsnachweise bleiben erhalten. Es wird nichts kostenpflichtig fortgeführt.','Pilot abbrechen','danger']}[transition];if(!config)return;openDialog(`<h2>${escapeHtml(config[0])}</h2><p>${escapeHtml(config[1])}</p><div id="cc-dialog-message"></div>${confirmButton(config[2],config[3])}`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/lifecycle.php',data,{action:transition},reload,`${config[0]}: aktueller Stand neu geladen.`);if(!result)event.currentTarget.disabled=false;});}

async function checkpointDialog(item,key,reload){const detail=await latest(item),data=detail.startpartner_candidate,label=checkpointLabels[key]||'Pilot-Checkpoint';openDialog(`<h2>${escapeHtml(label)}</h2><p>Dokumentiere den fachlichen Stand dieses fälligen Kontrollpunkts. Es wird keine Laufzeit verlängert und keine kostenpflichtige Fortführung ausgelöst.</p><div id="cc-dialog-message"></div><div class="cc-stack">${textarea('gate4-checkpoint-evidence','Ergebnis und nächste Folgerung','','required')}${confirmButton('Checkpoint abschließen')}</div>`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/lifecycle.php',data,{action:'complete_checkpoint',checkpoint_key:key,evidence_text:value('#gate4-checkpoint-evidence')},reload,'Pilot-Checkpoint dokumentiert.');if(!result)event.currentTarget.disabled=false;});}

async function distributionFulfillmentDialog(item,id,reload){const detail=await latest(item),data=detail.startpartner_candidate,current=asArray(data.gate4?.distribution_commitments).find(row=>String(row.id)===id)||{};const from=clean(current.status);const options=from==='planned'?'<option value="ready">Jetzt verbindlich vorbereitet</option><option value="blocked">Klärung nötig</option><option value="cancelled">Entfallen</option>':from==='blocked'?'<option value="completed">Erfüllt</option><option value="cancelled">Entfallen</option>':'<option value="completed">Erfüllt</option><option value="blocked">Klärung nötig</option><option value="cancelled">Entfallen</option>';openDialog(`<h2>Reichweitenbeitrag klären</h2><p>Die tatsächliche Erfüllung wird getrennt von der vorherigen Vereinbarung dokumentiert.</p><div id="cc-dialog-message"></div><div class="cc-stack"><label class="cc-field"><span>Neuer Stand</span><select id="gate4-fulfillment-status">${options}</select></label>${textarea('gate4-fulfillment-evidence','Belegbarer Stand','','required')}${confirmButton('Stand speichern')}</div>`,'cc-dialog--wide');document.querySelector('#gate4-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/lifecycle.php',data,{action:'set_distribution_fulfillment',distribution_id:id,status:value('#gate4-fulfillment-status'),evidence_text:value('#gate4-fulfillment-evidence')},reload,'Reichweitenbeitrag aktualisiert.');if(!result)event.currentTarget.disabled=false;});}

export async function handleGate4Action(item,action,reload){try{const parts=String(action||'').split(':');if(parts[1]==='repair-scope')return scopeRepairDialog(item,reload);if(parts[1]==='item')return manualItemDialog(item,parts[2],parts[3],reload);if(parts[1]==='content'||parts[1]==='content-ready')return contentReadyDialog(item,parts[2],reload);if(parts[1]==='content-approve')return contentApproveDialog(item,parts[2],reload);if(parts[1]==='measurement')return measurementDialog(item,reload);if(parts[1]==='distribution')return distributionDialog(item,reload);if(parts[1]==='distribution-fulfillment')return distributionFulfillmentDialog(item,parts[2],reload);if(parts[1]==='checkpoint')return checkpointDialog(item,parts[2],reload);if(parts[1]==='lifecycle')return lifecycleDialog(item,parts[2],reload);if(parts[1]==='activate')return activationDialog(item,reload);}catch(error){setStatus(error.message||'Der Pilotstand konnte nicht geöffnet werden.','attention');}}