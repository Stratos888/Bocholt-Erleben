import {
  escapeHtml, clean, asArray, formatDate, formatDateTime, api, openDialog,
  closeDialog, dialogMessage, field, textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const statusLabels={
  new:'Neu',prequalifying:'Vorqualifizierung',contact_pending:'Kontakt ausstehend',
  awaiting_response:'Rückmeldung ausstehend',qualifying:'Qualifizierung',
  needs_information:'Angaben fehlen',decision_ready:'Entscheidungsreif',
  accepted_pending_terms:'Platz reserviert · Bedingungen offen',waitlisted:'Warteliste',
  routed_to_regular_product:'Regulärer Weg',rejected:'Abgelehnt',withdrawn:'Zurückgezogen',expired:'Abgelaufen',
};
const sourceLabels={self_service:'Selbstmeldung',targeted_outreach:'Interne Identifizierung'};
const scopeLabels={events:'Events',activities:'Aktivitäten',both:'Events und Aktivitäten',unknown:'Noch offen'};
const assessmentLabels={unknown:'Offen',weak:'Schwach',adequate:'Ausreichend',strong:'Stark'};
const dimensionLabels={
  local_relevance:'Lokaler Bezug',organization_contact:'Organisation und Kontakt',content_sources:'Inhalte und Quellen',
  editorial_fit:'Redaktionelle Passung',content_leverage:'Inhaltshebel',reach_leverage:'Reichweitenhebel',
  user_need:'Nutzerbedarf',maintenance_capability:'Pflegefähigkeit',cooperation_readiness:'Kooperationsbereitschaft',
  setup_effort:'Einrichtungsaufwand',support_effort:'Betreuungsaufwand',regular_path:'Regulärer Zielweg',
  legal_technical:'Recht und Technik',required_information:'Offene Pflichtangaben',
};
const qualificationGroups=[
  ['Grundlage',['local_relevance','organization_contact','content_sources','editorial_fit']],
  ['Wirkung und Zusammenarbeit',['content_leverage','reach_leverage','user_need','maintenance_capability','cooperation_readiness']],
  ['Aufwand und Zielweg',['setup_effort','support_effort','regular_path','legal_technical','required_information']],
];
const contactStatusLabels={not_contacted:'Nicht kontaktiert',contact_pending:'Kontakt ausstehend',contacted:'Kontaktiert',paused:'Pausiert'};

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
    capacity:context.capacity||{},qualifications:[],contacts:[],events:[],reservations:[],
  };
}
function dateInput(valueText){
  const text=clean(valueText);if(!text)return '';
  const date=new Date(text.replace(' ','T'));if(Number.isNaN(date.getTime()))return '';
  const local=new Date(date.getTime()-date.getTimezoneOffset()*60000);return local.toISOString().slice(0,16);
}
function futureDate(days){const date=new Date();date.setDate(date.getDate()+days);return date.toISOString().slice(0,10);}
function metric(label,value,tone=''){return `<div class="cc-startpartner-metric ${tone?`cc-startpartner-metric--${tone}`:''}"><span>${escapeHtml(label)}</span><strong>${escapeHtml(value||'–')}</strong></div>`;}
function blockerText(data){
  const blockers=asArray(data?.readiness?.blockers);
  if(data?.readiness?.ready)return 'Alle 14 Dimensionen sind bewusst bewertet; die Mindestanforderungen sind erfüllt.';
  if(!blockers.length)return 'Qualifizierung ist noch nicht vollständig belegt.';
  const first=blockers[0];return `${dimensionLabels[first.dimension]||first.dimension}: ${first.message||'Prüfung erforderlich.'}`;
}
function capacityText(capacity={}){
  const active=Number(capacity.active_reservations||0),hard=Number(capacity.hard_stop_at||8);
  if(capacity.hard_stop)return `${active} von ${hard} Plätzen reserviert · harte Grenze erreicht`;
  if(capacity.soft_stop)return `${active} von ${hard} Plätzen reserviert · Ausnahmebegründung erforderlich`;
  return `${active} von ${hard} Plätzen reserviert`;
}
function qualificationSummary(data){
  const rows=asArray(data.qualifications);const counts={unknown:0,weak:0,adequate:0,strong:0};
  rows.forEach(row=>{const key=row.assessment||'unknown';counts[key]=(counts[key]||0)+1;});
  const cards=`<div class="cc-startpartner-scorecard">${metric('Offen',counts.unknown,counts.unknown?'attention':'')}${metric('Schwach',counts.weak)}${metric('Ausreichend',counts.adequate,'good')}${metric('Stark',counts.strong,'good')}</div>`;
  const byDimension=Object.fromEntries(rows.map(row=>[row.dimension,row]));
  const groups=qualificationGroups.map(([label,dimensions])=>`<section class="cc-startpartner-qualification-group"><h4>${escapeHtml(label)}</h4><div>${dimensions.map(dimension=>{const row=byDimension[dimension]||{dimension,assessment:'unknown'};return `<article class="cc-startpartner-dimension cc-startpartner-dimension--${escapeHtml(row.assessment||'unknown')}"><header><strong>${escapeHtml(dimensionLabels[dimension]||dimension)}</strong><span>${escapeHtml(assessmentLabels[row.assessment]||row.assessment||'Offen')}</span></header>${row.reason?`<p>${escapeHtml(row.reason)}</p>`:''}${row.evidence_text?`<small>${escapeHtml(row.evidence_text)}</small>`:''}</article>`;}).join('')}</div></section>`).join('');
  return `${cards}<details class="cc-disclosure cc-startpartner-qualifications"><summary>Alle 14 Qualifikationsdimensionen</summary><div>${groups}</div></details>`;
}
function contacts(data){
  const rows=asArray(data.contacts);if(!rows.length)return '<p class="cc-muted">Noch kein Kontakt hinterlegt.</p>';
  return `<div class="cc-startpartner-contact-list">${rows.map(row=>`<article><strong>${escapeHtml(row.contact_name||row.email||'Kontakt')}</strong><span>${escapeHtml([row.contact_role,row.email,row.phone].filter(Boolean).join(' · '))}</span>${row.is_primary?'<small>Hauptkontakt</small>':''}</article>`).join('')}</div>`;
}
function reservation(data){
  const active=data.active_reservation;
  if(active)return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Aktive Reservierung</span><strong>Bis ${escapeHtml(formatDate(active.ends_at))}</strong><p>Der Platz ist reserviert; Bedingungen und Pilotaktivierung sind ausdrücklich noch offen.</p></section>`;
  if(data.waitlist)return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Warteliste</span><strong>Neubewertung ${escapeHtml(formatDate(data.waitlist.next_review_at))}</strong><p>${escapeHtml(data.waitlist.priority_reason||data.waitlist.eligibility_reason||'Erneute Prüfung vorgesehen.')}</p><small>${escapeHtml(contactStatusLabels[data.waitlist.contact_status]||data.waitlist.contact_status||'')}</small></section>`;
  return '';
}
function decision(data){
  const current=data.decision;if(!current)return '';
  return `<section class="cc-startpartner-state-card"><span class="cc-kicker">Aktuelle Entscheidung</span><strong>${escapeHtml(statusLabels[current.result]||current.result)}</strong><p>${escapeHtml(current.reason||'')}</p>${current.regular_alternative?`<small>Alternative: ${escapeHtml(current.regular_alternative)}</small>`:''}</section>`;
}
function audit(data){
  const events=asArray(data.events);if(!events.length)return '<p class="cc-muted">Noch kein Auditverlauf vorhanden.</p>';
  return `<ol class="cc-startpartner-audit">${events.slice().reverse().map(event=>`<li><div><strong>${escapeHtml(event.event_type||'Änderung')}</strong><span>${escapeHtml(formatDateTime(event.created_at))}</span></div><small>${escapeHtml(event.actor_reference||event.actor_type||'System')}</small></li>`).join('')}</ol>`;
}
export function renderStartpartnerReview(item={}){
  const data=candidate(item);const readiness=data.readiness||{};const capacity=data.capacity||{};
  const primary=item.primary_action;
  return `<section class="cc-startpartner-review" data-startpartner-status="${escapeHtml(data.status||'')}">
    <section class="cc-startpartner-priority" aria-label="Priorisierte Startpartner-Prüfung">
      <div class="cc-startpartner-priority__status"><span class="cc-kicker">Aktueller Stand</span><strong>${escapeHtml(statusLabels[data.status]||item.display_status||'Prüfung erforderlich')}</strong><p>${escapeHtml(blockerText(data))}</p></div>
      <div class="cc-startpartner-priority__facts">${metric('Fälligkeit',data.next_review_at?formatDate(data.next_review_at):'Nicht gesetzt',data.next_review_at?'':'attention')}${metric('Bearbeiter',data.assigned_to||'Nicht zugewiesen',data.assigned_to?'':'attention')}${metric('Kapazität',capacityText(capacity),capacity.hard_stop?'attention':capacity.soft_stop?'warning':'good')}</div>
      ${primary?`<button class="cc-button cc-button--primary cc-button--large cc-startpartner-primary" data-review-action="${escapeHtml(primary.key)}">${escapeHtml(primary.label)}</button>`:'<div class="cc-empty">Aktuell keine Fachaktion erforderlich.</div>'}
    </section>
    <section class="cc-startpartner-panel"><header><div><span class="cc-kicker">Qualifizierung</span><h3>${readiness.ready?'Entscheidungsreif':'Blocker offen'}</h3></div><span class="cc-pill">${escapeHtml(`${Number(readiness.assessed_count||0)} / ${Number(readiness.total_count||14)} bewertet`)}</span></header>${qualificationSummary(data)}</section>
    <section class="cc-startpartner-grid"><section class="cc-startpartner-panel"><span class="cc-kicker">Organisation und Kontakt</span><h3>${escapeHtml(data.organization_name||item.title||'Startpartner')}</h3><dl class="cc-startpartner-facts"><div><dt>Herkunft</dt><dd>${escapeHtml(sourceLabels[data.source]||data.source||'–')}</dd></div><div><dt>Scope</dt><dd>${escapeHtml(scopeLabels[data.desired_content_scope]||data.desired_content_scope||'–')}</dd></div><div><dt>Website</dt><dd>${data.website_url?`<a href="${escapeHtml(data.website_url)}" target="_blank" rel="noopener">Website öffnen</a>`:'–'}</dd></div></dl>${contacts(data)}${data.description_text?`<p class="cc-startpartner-description">${escapeHtml(data.description_text)}</p>`:''}</section><section class="cc-startpartner-panel"><span class="cc-kicker">Kapazität und Weg</span><h3>${escapeHtml(capacityText(capacity))}</h3>${reservation(data)}${decision(data)}</section></section>
    <details class="cc-disclosure cc-startpartner-evidence"><summary>Evidence und Auditverlauf</summary><div>${audit(data)}</div></details>
  </section>`;
}

async function latest(item){return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`,{timeoutMs:15000});}
function gate2Id(){return `gate2:199:${operationId().replace(/^cc:/,'')}`;}
function operator(data){return clean(data.assigned_to)||'Steuerzentrale';}
async function mutate(path,data,payload,reload,success){
  try{
    const result=await api(path,{method:'POST',body:JSON.stringify({candidate_id:data.id,operation_id:gate2Id(),expected_revision:Number(data.revision),operator_name:operator(data),...payload}),timeoutMs:70000});
    await reload({throwOnError:true});closeDialog();setStatus(success,'success');return result;
  }catch(error){
    if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');return null;}
    dialogMessage(error.message);return null;
  }
}
function confirmButton(label,tone='primary'){return `<button type="button" class="cc-button cc-button--${tone}" id="sp-confirm">${escapeHtml(label)}</button>`;}
function scopeSelect(selected){return `<label class="cc-field"><span>Inhaltlicher Scope</span><select id="sp-scope">${Object.entries(scopeLabels).map(([key,label])=>`<option value="${key}" ${key===selected?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select></label>`;}
function assessmentSelect(id,selected){return `<select id="${id}">${Object.entries(assessmentLabels).map(([key,label])=>`<option value="${key}" ${key===selected?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select>`;}
async function profileDialog(item,reload){
  setStatus('Startpartner-Profil wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;const contactFields=asArray(data.contacts).map((contact,index)=>`<fieldset class="cc-startpartner-contact-editor"><legend>Kontakt ${index+1}${contact.is_primary?' · Hauptkontakt':''}</legend>${field(`sp-contact-name-${index}`,'Name',contact.contact_name||'')}${field(`sp-contact-role-${index}`,'Rolle',contact.contact_role||'')}${field(`sp-contact-email-${index}`,'E-Mail',contact.email||'','email','required')}${field(`sp-contact-phone-${index}`,'Telefon',contact.phone||'')}</fieldset>`).join('');openDialog(`<h2>Startpartner-Profil bearbeiten</h2><p class="cc-hint">Organisation, Zuständigkeit, Quellenprofil und bestehende Kontakte bleiben gemeinsam konsistent.</p><div id="cc-dialog-message"></div><div class="cc-stack">${field('sp-organization','Organisation',data.organization_name||'')}${field('sp-assigned','Bearbeiter',data.assigned_to||'')}${field('sp-review','Nächste Prüfung',dateInput(data.next_review_at),'datetime-local')}${field('sp-website','Website',data.website_url||'','url')}${scopeSelect(data.desired_content_scope)}${textarea('sp-description','Inhalts- und Organisationsprofil',data.description_text||'')}${contactFields}${confirmButton('Profil speichern')}</div>`,'cc-dialog--wide');setStatus('');document.querySelector('#sp-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const contacts=asArray(data.contacts).map((contact,index)=>({contact_name:value(`#sp-contact-name-${index}`),contact_role:value(`#sp-contact-role-${index}`),email:value(`#sp-contact-email-${index}`),phone:value(`#sp-contact-phone-${index}`),is_primary:Boolean(contact.is_primary)}));const result=await mutate('/api/startpartner/profile.php',data,{organization_name:value('#sp-organization'),assigned_to:value('#sp-assigned'),next_review_at:value('#sp-review'),website_url:value('#sp-website'),desired_content_scope:value('#sp-scope'),description_text:value('#sp-description'),...(contacts.length?{contacts}:{})},reload,'Profil gespeichert und vollständig neu geladen.');if(!result)event.currentTarget.disabled=false;});}catch(error){setStatus(error.message,'attention');}
}
async function qualificationDialog(item,reload){
  setStatus('Qualifizierung wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;const byDimension=Object.fromEntries(asArray(data.qualifications).map(row=>[row.dimension,row]));const sections=qualificationGroups.map(([label,dimensions])=>`<fieldset class="cc-startpartner-qualification-editor"><legend>${escapeHtml(label)}</legend>${dimensions.map(dimension=>{const row=byDimension[dimension]||{assessment:'unknown'};return `<section data-sp-dimension="${dimension}"><h3>${escapeHtml(dimensionLabels[dimension])}</h3><label class="cc-field"><span>Bewertung</span>${assessmentSelect(`sp-assessment-${dimension}`,row.assessment||'unknown')}</label>${textarea(`sp-reason-${dimension}`,'Begründung',row.reason||'')}${textarea(`sp-evidence-${dimension}`,'Evidence',row.evidence_text||'')}</section>`;}).join('')}</fieldset>`).join('');openDialog(`<h2>Qualifizierung bearbeiten</h2><p class="cc-hint">Alle 14 Dimensionen werden bewusst bewertet. Eine Bewertung außer „Offen“ benötigt Begründung und Evidence.</p><div id="cc-dialog-message"></div><div class="cc-stack">${sections}${confirmButton('Qualifizierung speichern')}</div>`,'cc-dialog--wide cc-dialog--qualification');setStatus('');document.querySelector('#sp-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const qualifications=Object.keys(dimensionLabels).map(dimension=>({dimension,assessment:value(`#sp-assessment-${dimension}`),reason:value(`#sp-reason-${dimension}`),evidence_text:value(`#sp-evidence-${dimension}`)}));const result=await mutate('/api/startpartner/qualification.php',data,{qualifications},reload,'Qualifizierung gespeichert und vollständig neu bewertet.');if(!result)event.currentTarget.disabled=false;});}catch(error){setStatus(error.message,'attention');}
}
function actionDialogContent(action,data){
  if(action==='accept_pending_terms')return `${textarea('sp-reason','Entscheidungsbegründung','','required')}${field('sp-reservation-end','Reservierung bis',futureDate(20),'date')}${data.capacity?.soft_stop?textarea('sp-capacity-reason','Kapazitätsausnahme','','required'):''}`;
  if(action==='waitlist'||action==='update_waitlist'){const wait=data.waitlist||{};return `${textarea('sp-reason','Entscheidungsbegründung',action==='update_waitlist'?(data.status_reason||''):'','required')}${textarea('sp-eligibility','Eignungsgrund',wait.eligibility_reason||'','required')}${textarea('sp-priority','Prioritätsgrund',wait.priority_reason||'','required')}${field('sp-review-date','Neubewertung',wait.next_review_at?String(wait.next_review_at).slice(0,10):futureDate(14),'date')}<label class="cc-field"><span>Kontaktstatus</span><select id="sp-contact-status">${Object.entries(contactStatusLabels).map(([key,label])=>`<option value="${key}" ${key===(wait.contact_status||'not_contacted')?'selected':''}>${escapeHtml(label)}</option>`).join('')}</select></label>${field('sp-alternative','Reguläre Alternative',wait.regular_alternative||'')}`;}
  if(action==='extend_reservation')return `${textarea('sp-reason','Grund der Verlängerung','','required')}${field('sp-reservation-end','Neue Reservierung bis',futureDate(25),'date')}`;
  if(action==='release_reservation')return `${textarea('sp-reason','Freigabegrund','','required')}<label class="cc-field"><span>Nächster Zustand</span><select id="sp-target-status"><option value="decision_ready">Entscheidungsreif</option><option value="qualifying">Erneut qualifizieren</option></select></label>`;
  if(action==='route_regular')return `${textarea('sp-reason','Begründung','','required')}${field('sp-alternative','Reguläre Alternative','')}`;
  if(['reject','withdraw','expire','reopen','mark_needs_information'].includes(action))return textarea('sp-reason','Begründung','','required');
  return `<div class="cc-notice cc-notice--info"><strong>Serverseitige Prüfung</strong><span>Revision, Statusübergang, Readiness und Projektion werden vor dem Speichern erneut geprüft.</span></div>`;
}
function actionPayload(action){
  const payload={action};const reason=value('#sp-reason');if(reason)payload.reason=reason;
  if(action==='accept_pending_terms'){payload.reservation_ends_at=value('#sp-reservation-end');const capacity=value('#sp-capacity-reason');if(capacity)payload.capacity_exception_reason=capacity;}
  if(action==='waitlist'||action==='update_waitlist'){payload.eligibility_reason=value('#sp-eligibility');payload.priority_reason=value('#sp-priority');payload.next_review_at=value('#sp-review-date');payload.contact_status=value('#sp-contact-status');payload.regular_alternative=value('#sp-alternative');}
  if(action==='extend_reservation')payload.reservation_ends_at=value('#sp-reservation-end');
  if(action==='release_reservation')payload.target_status=value('#sp-target-status');
  if(action==='route_regular')payload.regular_alternative=value('#sp-alternative');
  return payload;
}
async function workflowDialog(item,action,reload){
  setStatus('Aktueller Startpartner-Stand wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;const label=[item.primary_action,...asArray(item.secondary_actions)].find(entry=>entry?.key===action)?.label||'Aktion bestätigen';const destructive=['reject','withdraw','expire','release_reservation'].includes(action);openDialog(`<h2>${escapeHtml(label)}</h2><p>${escapeHtml(statusLabels[data.status]||data.status)} · Revision ${escapeHtml(data.revision)}</p><div id="cc-dialog-message"></div><div class="cc-stack">${actionDialogContent(action,data)}${confirmButton(label,destructive?'danger':'primary')}</div>`);setStatus('');document.querySelector('#sp-confirm')?.addEventListener('click',async event=>{event.currentTarget.disabled=true;const result=await mutate('/api/startpartner/action.php',data,actionPayload(action),reload,'Aktion gespeichert und vollständiger Serverzustand neu geladen.');if(!result)event.currentTarget.disabled=false;});}catch(error){setStatus(error.message,'attention');}
}
async function detailsDialog(item){setStatus('Audit wird geladen …');try{const detail=await latest(item);const data=detail.startpartner_candidate;openDialog(`<h2>${escapeHtml(data.organization_name)}</h2>${renderStartpartnerReview({...item,startpartner_candidate:data,primary_action:null})}`,'cc-dialog--wide');setStatus('');}catch(error){setStatus(error.message,'attention');}}
export async function handleStartpartnerAction(item,action,reload){
  if(action==='edit_profile')return profileDialog(item,reload);
  if(action==='edit_qualification')return qualificationDialog(item,reload);
  if(action==='details')return detailsDialog(item);
  return workflowDialog(item,action,reload);
}
