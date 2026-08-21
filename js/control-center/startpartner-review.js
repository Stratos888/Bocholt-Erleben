import {
  escapeHtml, clean, asArray, formatDate, formatDateTime, api, openDialog,
  closeDialog, dialogMessage, field, textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';
import {
  startpartnerAiReviewStatuses, renderStartpartnerAiReview, handleStartpartnerAiReviewAction,
} from './startpartner-ai-review.js?v=2026-08-21-ai-assisted-review-v1';

const statusLabels={
  new:'Prüfung offen',prequalifying:'Prüfung offen',contact_pending:'Rückfrage vorbereitet',
  awaiting_response:'Rückmeldung ausstehend',qualifying:'Prüfung offen',needs_information:'Rückfrage nötig',
  decision_ready:'Prüfung offen',accepted_pending_terms:'Platz reserviert · Bedingungen offen',waitlisted:'Warteliste',
  routed_to_regular_product:'Regulärer Weg',rejected:'Nicht geeignet',withdrawn:'Zurückgezogen',expired:'Abgelaufen',
};
const sourceLabels={self_service:'Selbstmeldung',targeted_outreach:'Interne Identifizierung'};
const scopeLabels={events:'Veranstaltungen',activities:'Aktivitäten',both:'Veranstaltungen und Aktivitäten',unknown:'Noch offen'};
const channelLabels={operator_recorded:'Intern protokolliert',signed_document:'Unterzeichnetes Dokument',email_reply:'E-Mail-Bestätigung',portal:'Bestätigung im Veranstalterportal'};
const contactStatusLabels={not_contacted:'Nicht kontaktiert',contact_pending:'Kontakt ausstehend',contacted:'Kontaktiert',paused:'Pausiert'};
const pilotStatusLabels={onboarding:'Einrichtung läuft',activation_ready:'Bereit zum Start',active:'Pilotphase läuft',completed:'Abgeschlossen',ended:'Beendet'};
const entitlementStatusLabels={pending_activation:'Noch nicht aktiv',active:'Aktiv',ended:'Beendet',revoked:'Beendet'};

function candidate(item){
  const full=item?.startpartner_candidate;
  if(full)return full;
  const context=item?.decision_context||{};
  return {
    id:context.candidate_id||item?.object_id||item?.source_reference||'',
    organization_name:item?.object_title||item?.title||'',status:context.candidate_status||'new',
    revision:Number(context.candidate_revision||0),assigned_to:context.assigned_to||'',
    next_review_at:context.next_review_at||'',desired_content_scope:context.desired_content_scope||'',
    source:context.candidate_source||'',readiness:context.readiness||{ready:false,blockers:[]},
    capacity:context.capacity||{},gate3:context.gate3||{complete:false,blockers:[]},
    qualifications:[],contacts:[],events:[],reservations:[],
  };
}
function dateInput(valueText){
  const text=clean(valueText);if(!text)return '';
  const date=new Date(text.replace(' ','T'));if(Number.isNaN(date.getTime()))return '';
  const local=new Date(date.getTime()-date.getTimezoneOffset()*60000);return local.toISOString().slice(0,16);
}
function nowInput(){return dateInput(new Date().toISOString());}
function futureDate(days){const date=new Date();date.setDate(date.getDate()+days);return date.toISOString().slice(0,10);}
function metric(label,value,tone=''){return `<div class="cc-startpartner-metric ${tone?`cc-startpartner-metric--${tone}`:''}"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value||'–')}</strong></div>`;}
function blockerText(data){
  const status=String(data?.status||'');const gate3=data?.gate3||{};
  if(status==='accepted_pending_terms'){
    if(gate3.complete)return 'Bedingungen bestätigt. Die Piloteinrichtung kann beginnen; Pilotphase und Laufzeit starten noch nicht.';
    const blocker=asArray(gate3.blockers)[0];
    return blocker?.message||'Die Pilotbedingungen müssen bestätigt und einem Veranstalterzugang zugeordnet werden.';
  }
  if(status==='contact_pending')return 'Rückfrage ist vorbereitet. Der Versand an den Hauptkontakt steht noch aus.';
  if(status==='awaiting_response')return 'Rückfrage wurde per E-Mail versendet. Wir warten auf die Rückmeldung.';
  if(status==='needs_information')return 'Für die Entscheidung fehlen noch Angaben. Die Rückfrage kann unten versendet oder angepasst werden.';
  if(startpartnerAiReviewStatuses.has(status))return 'Prüfprompt kopieren, ChatGPT-Auswertung prüfen und anschließend selbst entscheiden.';
  if(status==='waitlisted')return data.status_reason||'Kandidat ist vorgemerkt und wird bei verfügbarer Kapazität erneut geprüft.';
  return data.status_reason||'Aktuellen Stand prüfen.';
}
function capacityText(capacity={}){
  const active=Number(capacity.active_reservations||0),hard=Number(capacity.hard_stop_at||8);
  if(capacity.hard_stop)return `${active} von ${hard} Plätzen reserviert · Grenze erreicht`;
  if(capacity.soft_stop)return `${active} von ${hard} Plätzen reserviert · bewusste Ausnahme nötig`;
  return `${active} von ${hard} Plätzen reserviert`;
}
function contacts(data){
  const rows=asArray(data.contacts);if(!rows.length)return '<p class="cc-muted">Noch kein Kontakt hinterlegt.</p>';
  return `<div class="cc-startpartner-contact-list">${rows.map(row=>`<article><strong>${escapeHtml(row.contact_name||row.email||'Kontakt')}</strong><span>${escapeHtml([row.contact_role,row.email,row.phone].filter(Boolean).join(' · '))}</span>${row.is_primary?'<small>Hauptkontakt</small>':''}</article>`).join('')}</div>`;
}
function reservation(data){
  const active=data.active_reservation;
  if(active){
    const message=data?.gate3?.complete
      ? 'Die Reservierung belegt den Platz weiterhin. Die Pilotphase beginnt erst nach der vollständigen Einrichtung.'
      : 'Der Platz ist reserviert. Bedingungen und Pilotstart sind noch offen.';
    return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Aktive Reservierung</span><strong>Bis ${escapeHtml(formatDate(active.ends_at))}</strong><p>${escapeHtml(message)}</p></section>`;
  }
  if(data.waitlist)return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Warteliste</span><strong>Neubewertung ${escapeHtml(formatDate(data.waitlist.next_review_at))}</strong><p>${escapeHtml(data.waitlist.priority_reason||data.waitlist.eligibility_reason||'Erneute Prüfung vorgesehen.')}</p><small>${escapeHtml(contactStatusLabels[data.waitlist.contact_status]||data.waitlist.contact_status||'')}</small></section>`;
  return '';
}
function decision(data){
  const current=data.decision;if(!current)return '';
  return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Aktuelle Entscheidung</span><strong>${escapeHtml(statusLabels[current.result]||current.result)}</strong><p>${escapeHtml(current.reason||'')}</p>${current.regular_alternative?`<small>Alternative: ${escapeHtml(current.regular_alternative)}</small>`:''}</section>`;
}
function gate3Summary(data){
  if(data.status!=='accepted_pending_terms'&&!data?.gate3?.pilot)return '';
  const gate3=data.gate3||{};
  if(!gate3.complete){
    const blocker=asArray(gate3.blockers)[0];
    return `<section class="cc-startpartner-panel"><header><div><span class="cc-kicker">Pilotbedingungen und Veranstalterzugang</span><h3>Vorbereitung noch offen</h3></div><span class="cc-pill">Bedingungen offen</span></header><p>${escapeHtml(blocker?.message||'Bestätigung, Veranstalterzugang und Pilotfreigabe fehlen.')}</p></section>`;
  }
  const terms=gate3.terms_acceptance||{},organizer=gate3.organizer||{},pilot=gate3.pilot||{},entitlement=gate3.entitlement||{};
  const scopes=asArray(gate3.scopes).filter(scope=>['events','activities'].includes(scope.scope_key)).map(scope=>`${scope.scope_key==='events'?'Veranstaltungen':'Aktivitäten'}: ${scope.is_unlimited?'unbegrenzt':scope.limit_value||'–'}${scope.period_unit==='pilot_month'?' pro Monat':scope.period_unit==='concurrent'?' gleichzeitig':''}`).join(' · ');
  return `<section class="cc-startpartner-panel"><header><div><span class="cc-kicker">Pilotbedingungen und Veranstalterzugang</span><h3>Piloteinrichtung vorbereitet</h3></div><span class="cc-pill">Pilotstart ausstehend</span></header><dl class="cc-startpartner-facts"><div><dt>Bedingungen</dt><dd>${escapeHtml(terms.terms_version||'–')} · ${escapeHtml(formatDateTime(terms.accepted_at)||'–')}</dd></div><div><dt>Veranstalterzugang</dt><dd>${escapeHtml(organizer.organization_name||'–')} · ${escapeHtml(organizer.email||'–')}</dd></div><div><dt>Pilot</dt><dd>${escapeHtml(pilotStatusLabels[pilot.status]||pilot.status||'Einrichtung läuft')}</dd></div><div><dt>Pilotfreigabe</dt><dd>${escapeHtml(entitlementStatusLabels[entitlement.status]||entitlement.status||'Noch nicht aktiv')} · Veröffentlichung noch nicht freigeschaltet</dd></div></dl>${scopes?`<p>${escapeHtml(scopes)}</p>`:''}</section>`;
}
function audit(data){
  const events=[...asArray(data.events),...asArray(data?.gate3?.events)];if(!events.length)return '<p class="cc-muted">Noch kein Verlauf vorhanden.</p>';
  return `<ol class="cc-startpartner-audit">${events.slice().reverse().map(event=>`<li><div><strong>${escapeHtml(event.event_type||'Änderung')}</strong><span>${escapeHtml(formatDateTime(event.created_at))}</span></div><small>${escapeHtml(event.actor_reference||event.actor_type||'System')}</small></li>`).join('')}</ol>`;
}
function actionLabel(action){return action?.label||'';}

export function renderStartpartnerReview(item={}){
  const data=candidate(item);const capacity=data.capacity||{};const reviewing=startpartnerAiReviewStatuses.has(String(data.status||''));
  const primary=reviewing?null:item.primary_action;
  const displayStatus=data?.gate3?.complete?'Piloteinrichtung':(statusLabels[data.status]||item.display_status||'Prüfung erforderlich');
  return `<section class="cc-startpartner-review" data-startpartner-status="${escapeHtml(data.status||'')}">
    <section class="cc-startpartner-priority" aria-label="Priorisierte Startpartner-Prüfung">
      <div class="cc-startpartner-priority__status"><span class="cc-kicker">Aktueller Stand</span><strong>${escapeHtml(displayStatus)}</strong><p>${escapeHtml(blockerText(data))}</p></div>
      <div class="cc-startpartner-priority__facts">${metric('Fälligkeit',data.next_review_at?formatDate(data.next_review_at):'Nicht gesetzt')}${metric('Bearbeiter',data.assigned_to||'Nicht zugewiesen')}${metric('Kapazität',capacityText(capacity),capacity.hard_stop?'attention':capacity.soft_stop?'warning':'good')}</div>
      ${primary?`<button class="cc-button cc-button--primary cc-button--large cc-startpartner-primary" data-review-action="${escapeHtml(primary.key)}">${escapeHtml(actionLabel(primary))}</button>`:reviewing?'':'<div class="cc-empty">Aktuell keine Aktion erforderlich.</div>'}
    </section>
    ${renderStartpartnerAiReview(data)}
    ${gate3Summary(data)}
    <section class="cc-startpartner-grid"><section class="cc-startpartner-panel"><span class="cc-kicker">Organisation und Kontakt</span><h3>${escapeHtml(data.organization_name||item.title||'Startpartner')}</h3><dl class="cc-startpartner-facts"><div><dt>Herkunft</dt><dd>${escapeHtml(sourceLabels[data.source]||data.source||'–')}</dd></div><div><dt>Inhaltsumfang</dt><dd>${escapeHtml(scopeLabels[data.desired_content_scope]||data.desired_content_scope||'–')}</dd></div><div><dt>Website</dt><dd>${data.website_url?`<a href="${escapeHtml(data.website_url)}" target="_blank" rel="noopener">Website öffnen</a>`:'–'}</dd></div></dl>${contacts(data)}${data.description_text?`<p class="cc-startpartner-description">${escapeHtml(data.description_text)}</p>`:''}</section><section class="cc-startpartner-panel"><span class="cc-kicker">Platz und weiterer Weg</span><h3>${escapeHtml(capacityText(capacity))}</h3>${reservation(data)}${decision(data)}</section></section>
    <details class="cc-disclosure cc-startpartner-evidence"><summary>Nachweise und Verlauf</summary><div>${audit(data)}</div></details>
  </section>`;
}

async function latest(item){return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`,{timeoutMs:15000});}
function mutationId(prefix='gate2:199'){return `${prefix}:${operationId().replace(/^cc:/,'')}`;}
function operator(data){return clean(data.assigned_to)||'Steuerzentrale';}
async function mutate(path,data,payload,reload,success,prefix='gate2:199'){
  try{
    const result=await api(path,{method:'POST',body:JSON.stringify({candidate_id:data.id,operation_id:mutationId(prefix),expected_revision:Number(data.revision),operator_name:operator(data),...payload}),timeoutMs:70000});
    await reload({throwOnError:true});closeDialog();setStatus(success,'success');return result;
  }catch(error){
    if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');return null;}
    dialogMessage(error.message);return null;
  }
}
function confirmButton(label,tone='primary'){return `<button type="button" class="cc-button cc-button--${tone}" id="sp-confirm">${escapeHtml(label)}</button>`;}
function scopeSelect(selected){return `<label class="cc-field"><span>Inhaltsumfang</span><select id="sp-scope">${Object.entries(scopeLabels).map(([key,label])=>`<option value="${key}" ${key===selected?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select></label>`;}
function selectField(id,label,options,selected=''){return `<label class="cc-field"><span>${escapeHtml(label)}</span><select id="${id}">${Object.entries(options).map(([key,text])=>`<option value="${escapeHtml(key)}" ${key===selected?'selected':''}>${escapeHtml(text)}</option>`).join('')}</select></label>`;}

async function profileDialog(item,reload){
  setStatus('Startpartner-Profil wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;const contactFields=asArray(data.contacts).map((contact,index)=>`<fieldset class="cc-startpartner-contact-editor"><legend>Kontakt ${index+1}${contact.is_primary?' · Hauptkontakt':''}</legend>${field(`sp-contact-name-${index}`,'Name',contact.contact_name||'')}${field(`sp-contact-role-${index}`,'Rolle',contact.contact_role||'')}${field(`sp-contact-email-${index}`,'E-Mail',contact.email||'','email','required')}${field(`sp-contact-phone-${index}`,'Telefon',contact.phone||'')}</fieldset>`).join('');openDialog(`<h2>Startpartner-Profil bearbeiten</h2><p class="cc-hint">Organisation, Zuständigkeit, Inhaltsquellen und Kontakte werden gemeinsam gespeichert.</p><div id="cc-dialog-message"></div><div class="cc-stack">${field('sp-organization','Organisation',data.organization_name||'')}${field('sp-assigned','Bearbeiter',data.assigned_to||'')}${field('sp-review','Nächste Prüfung',dateInput(data.next_review_at),'datetime-local')}${field('sp-website','Website',data.website_url||'','url')}${scopeSelect(data.desired_content_scope)}${textarea('sp-description','Inhalts- und Organisationsprofil',data.description_text||'')}${contactFields}${confirmButton('Profil speichern')}</div>`,'cc-dialog--wide');setStatus('');document.querySelector('#sp-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const contacts=asArray(data.contacts).map((contact,index)=>({contact_name:value(`#sp-contact-name-${index}`),contact_role:value(`#sp-contact-role-${index}`),email:value(`#sp-contact-email-${index}`),phone:value(`#sp-contact-phone-${index}`),is_primary:Boolean(contact.is_primary)}));const result=await mutate('/api/startpartner/profile.php',data,{organization_name:value('#sp-organization'),assigned_to:value('#sp-assigned'),next_review_at:value('#sp-review'),website_url:value('#sp-website'),desired_content_scope:value('#sp-scope'),description_text:value('#sp-description'),...(contacts.length?{contacts}:{})},reload,'Profil gespeichert und vollständig neu geladen.');if(!result)event.currentTarget.disabled=false;});}catch(error){setStatus(error.message,'attention');}
}
function gate3DialogContent(data){
  const primary=asArray(data.contacts).find(contact=>contact.is_primary)||asArray(data.contacts)[0]||{};
  return `<div class="cc-notice cc-notice--info"><strong>Ausdrückliche Bestätigung</strong><span>Diese interne Erfassung sendet keine Nachricht, richtet keinen Veranstalterzugang ein und veröffentlicht keine Inhalte.</span></div>
    ${field('sp-terms-version','Version der Pilotbedingungen','','text','required')}
    ${field('sp-terms-reference','Referenz der bestätigten Fassung','','text','required')}
    ${field('sp-terms-digest','Prüfsumme der bestätigten Fassung (SHA-256)','','text','required')}
    ${field('sp-accepting-person','Bestätigende Person',primary.contact_name||'','text','required')}
    ${field('sp-accepting-organization','Bestätigende Organisation',data.organization_name||'','text','required')}
    ${field('sp-accepted-at','Bestätigt am',nowInput(),'datetime-local','required')}
    ${selectField('sp-confirmation-channel','Bestätigungskanal',channelLabels,'operator_recorded')}
    ${field('sp-target-plans','Mögliche Tarife nach dem Pilot','','text','required')}
    ${field('sp-cohort','Pilotgruppe','','text','required')}
    ${data.desired_content_scope==='activities'?'':field('sp-event-limit','Veranstaltungen pro Monat','8','number','required')}
    ${data.desired_content_scope==='events'?'':field('sp-activity-limit','Gleichzeitige Aktivitäten','1','number','required')}
    ${textarea('sp-source-care','Inhaltsquellen und Pflege','','required')}
    ${textarea('sp-maintenance','Vereinbarte Betreuung und Pflege','','required')}
    ${textarea('sp-reach','Reichweitenbeitrag','','required')}
    ${field('sp-privacy-version','Datenschutzhinweis-Version','','text')}
    ${field('sp-communication-version','Kommunikationshinweis-Version','','text')}
    ${field('sp-planned-start','Geplanter Pilotstart','','date')}
    ${field('sp-planned-end','Geplantes Pilotende','','date')}
    <label class="cc-field"><span><input id="sp-no-auto-renewal" type="checkbox" required> Keine automatische kostenpflichtige Verlängerung bestätigt</span></label>`;
}
function actionDialogContent(action,data){
  if(action==='confirm_pilot_terms')return gate3DialogContent(data);
  if(action==='accept_pending_terms')return `${textarea('sp-reason','Entscheidungsbegründung','','required')}${field('sp-reservation-end','Reservierung bis',futureDate(20),'date')}${data.capacity?.soft_stop?textarea('sp-capacity-reason','Begründung für die Ausnahme','','required'):''}`;
  if(action==='waitlist'||action==='update_waitlist'){const wait=data.waitlist||{};return `${textarea('sp-reason','Entscheidungsbegründung',action==='update_waitlist'?(data.status_reason||''):'','required')}${textarea('sp-eligibility','Eignungsgrund',wait.eligibility_reason||'','required')}${textarea('sp-priority','Prioritätsgrund',wait.priority_reason||'','required')}${field('sp-review-date','Neubewertung',wait.next_review_at?String(wait.next_review_at).slice(0,10):futureDate(14),'date')}<label class="cc-field"><span>Kontaktstatus</span><select id="sp-contact-status">${Object.entries(contactStatusLabels).map(([key,label])=>`<option value="${key}" ${key===(wait.contact_status||'not_contacted')?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select></label>${field('sp-alternative','Reguläre Alternative',wait.regular_alternative||'')}`;}
  if(action==='extend_reservation')return `${textarea('sp-reason','Grund der Verlängerung','','required')}${field('sp-reservation-end','Neue Reservierung bis',futureDate(25),'date')}`;
  if(action==='release_reservation')return `${textarea('sp-reason','Freigabegrund','','required')}<label class="cc-field"><span>Nächster Zustand</span><select id="sp-target-status"><option value="decision_ready">Entscheidungsreif</option><option value="qualifying">Erneut prüfen</option></select></label>`;
  if(action==='route_regular')return `${textarea('sp-reason','Begründung','','required')}${field('sp-alternative','Reguläre Alternative','')}`;
  if(['reject','withdraw','expire','reopen','mark_needs_information'].includes(action))return textarea('sp-reason','Begründung','','required');
  return `<div class="cc-notice cc-notice--info"><strong>Aktuellen Stand prüfen</strong><span>Das System prüft vor dem Speichern, ob der angezeigte Stand noch gültig ist.</span></div>`;
}
function actionPayload(action){
  const payload={action};const reason=value('#sp-reason');if(reason)payload.reason=reason;
  if(action==='confirm_pilot_terms'){
    return {
      action,terms_version:value('#sp-terms-version'),terms_reference:value('#sp-terms-reference'),terms_digest:value('#sp-terms-digest'),
      accepting_person:value('#sp-accepting-person'),accepting_organization:value('#sp-accepting-organization'),accepted_at:value('#sp-accepted-at'),
      confirmation_channel:value('#sp-confirmation-channel'),target_plan_keys:value('#sp-target-plans').split(',').map(item=>item.trim()).filter(Boolean),cohort_key:value('#sp-cohort'),
      event_limit_per_pilot_month:value('#sp-event-limit'),activity_concurrent_limit:value('#sp-activity-limit'),is_event_unlimited:false,
      source_care_text:value('#sp-source-care'),maintenance_scope_text:value('#sp-maintenance'),reach_contribution_text:value('#sp-reach'),
      privacy_notice_version:value('#sp-privacy-version'),communication_notice_version:value('#sp-communication-version'),
      planned_activation_start:value('#sp-planned-start'),planned_activation_end:value('#sp-planned-end'),
      no_automatic_paid_renewal:Boolean(document.querySelector('#sp-no-auto-renewal')?.checked),
    };
  }
  if(action==='accept_pending_terms'){payload.reservation_ends_at=value('#sp-reservation-end');const capacity=value('#sp-capacity-reason');if(capacity)payload.capacity_exception_reason=capacity;}
  if(action==='waitlist'||action==='update_waitlist'){payload.eligibility_reason=value('#sp-eligibility');payload.priority_reason=value('#sp-priority');payload.next_review_at=value('#sp-review-date');payload.contact_status=value('#sp-contact-status');payload.regular_alternative=value('#sp-alternative');}
  if(action==='extend_reservation')payload.reservation_ends_at=value('#sp-reservation-end');
  if(action==='release_reservation')payload.target_status=value('#sp-target-status');
  if(action==='route_regular')payload.regular_alternative=value('#sp-alternative');
  return payload;
}
async function workflowDialog(item,action,reload){
  setStatus('Aktueller Startpartner-Stand wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;const found=[item.primary_action,...asArray(item.secondary_actions)].find(entry=>entry?.key===action);const label=actionLabel(found)||'Aktion bestätigen';const destructive=['reject','withdraw','expire','release_reservation'].includes(action);openDialog(`<h2>${escapeHtml(label)}</h2><p>${escapeHtml(data?.gate3?.complete?'Piloteinrichtung':(statusLabels[data.status]||data.status))}</p><div id="cc-dialog-message"></div><div class="cc-stack">${actionDialogContent(action,data)}${confirmButton(label,destructive?'danger':'primary')}</div>`,'cc-dialog--wide');setStatus('');document.querySelector('#sp-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const prefix=action==='confirm_pilot_terms'?'gate3:231':'gate2:199';const result=await mutate('/api/startpartner/action.php',data,actionPayload(action),reload,action==='confirm_pilot_terms'?'Bedingungen, Veranstalterzugang und Pilot wurden gespeichert und vollständig neu geladen.':'Aktion gespeichert und aktueller Stand neu geladen.',prefix);if(!result)event.currentTarget.disabled=false;});}catch(error){setStatus(error.message,'attention');}
}
async function detailsDialog(item){setStatus('Verlauf wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;openDialog(`<h2>${escapeHtml(data.organization_name)}</h2>${renderStartpartnerReview({...item,startpartner_candidate:data,primary_action:null})}`,'cc-dialog--wide');setStatus('');}catch(error){setStatus(error.message,'attention');}}
export async function handleStartpartnerAction(item,action,reload){
  if(action==='edit_profile')return profileDialog(item,reload);
  if(await handleStartpartnerAiReviewAction(item,action,reload))return;
  if(action==='details')return detailsDialog(item);
  return workflowDialog(item,action,reload);
}
