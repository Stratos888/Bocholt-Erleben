import {
  escapeHtml, clean, asArray, api, openDialog, closeDialog, dialogMessage,
  textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const scopeLabels={
  events:'Veranstaltungen',activities:'Aktivitäten',both:'Veranstaltungen und Aktivitäten',unknown:'Noch offen',
};
export const startpartnerAiReviewStatuses=new Set([
  'new','prequalifying','contact_pending','awaiting_response','qualifying','needs_information','decision_ready',
]);

function latestRecordedReply(data={}){
  const events=asArray(data.events).slice().reverse();
  const event=events.find(entry=>entry?.event_type==='gate2_action_applied'
    && entry?.from_status==='awaiting_response'
    && entry?.payload?.action==='start_qualification'
    && clean(entry?.payload?.reason));
  return clean(event?.payload?.reason);
}
function latestMailEvent(data={},topic=''){
  return asArray(data.events).slice().reverse().find(entry=>
    ['review_mail_sent','review_mail_failed'].includes(String(entry?.event_type||''))
    && String(entry?.payload?.topic||'')===topic
  )||null;
}
function reviewPrompt(data={}){
  const organization=clean(data.organization_name)||'Nicht angegeben';
  const website=clean(data.website_url)||'Nicht angegeben';
  const scope=scopeLabels[data.desired_content_scope]||clean(data.desired_content_scope)||'Noch offen';
  const description=clean(data.description_text)||'Nicht angegeben';
  const reply=latestRecordedReply(data)||'Keine nachgereichten Angaben vorhanden.';
  return `Du prüfst für die lokale Plattform „Bocholt erleben“, ob eine Organisation als Startpartner für einen kostenlosen sechsmonatigen Test geeignet ist.

WICHTIGER ROLLENRAHMEN
Du recherchierst, analysierst und gibst eine Empfehlung. Du triffst keine verbindliche Aufnahmeentscheidung. Diese trifft anschließend ein Mensch. Bewerte keine Kapazität; die maximal acht Startpartnerplätze werden separat im System geprüft.

ANFRAGEDATEN
Organisation: ${organization}
Website / öffentliche Quelle: ${website}
Gewünschter Bereich: ${scope}
Beschreibung aus der Anfrage: ${description}
Nachgereichte Angaben aus einer Rückfrage: ${reply}

AUFGABE
1. Prüfe zuerst die angegebene Website/Quelle. Wenn keine Website angegeben ist, recherchiere anhand des Organisationsnamens. Nutze bei Bedarf weitere belastbare öffentliche Quellen.
2. Berücksichtige ausdrücklich die nachgereichten Angaben, falls vorhanden. Trenne belegte Fakten klar von Angaben des Antragstellers und von Annahmen.
3. Erfinde nichts. Wenn eine Information öffentlich oder durch die Anfrage nicht verlässlich feststellbar ist, markiere sie als offen.
4. Prüfe diese sechs Punkte:
   a) lokale und redaktionelle Passung zu Bocholt erleben
   b) geeignete Inhalte bzw. belastbare Quellen für Veranstaltungen/Aktivitäten
   c) relevanter Mehrwert für Nutzer und potenzieller Beitrag zur Reichweite
   d) realistische Zusammenarbeit und laufende Pflege; soweit nicht prüfbar, als Rückfrage markieren statt negativ zu unterstellen
   e) sinnvoller Einrichtungs-/Betreuungsaufwand und plausibler späterer regulärer Weg
   f) erkennbare Rechte-, Technik- oder Pflichtangaben-Risiken; nicht klärbare Rechtefragen als Rückfrage markieren
5. Eine grundsätzlich passende Organisation darf nicht allein deshalb als ungeeignet gelten, weil interne Kooperationsdetails oder Rechtebestätigungen noch erfragt werden müssen. Dann ist „RÜCKFRAGE NÖTIG“ die richtige Empfehlung.
6. Recherchiere keine privaten personenbezogenen Daten und versuche nicht, Kontaktinformationen zu ergänzen.
7. Führe unter QUELLEN ausschließlich belastbare öffentliche Quellen auf, die konkrete Aussagen über die zu prüfende Organisation oder deren Angebote belegen. Allgemeine Referenzseiten zu Bocholt, Veranstaltungskalender oder Bocholt erleben sind keine Kandidatenbelege und dürfen dort nicht erscheinen. Verwende möglichst die kanonische Quell-URL und entferne unnötige Trackingparameter wie utm_*. Wenn keine belastbare kandidatenbezogene öffentliche Quelle gefunden wurde, gib unter QUELLEN ausschließlich „Keine belastbare kandidatenbezogene öffentliche Quelle gefunden.“ aus.

GIB GENAU DIESE STRUKTUR AUS
EMPFEHLUNG: AUFNEHMEN | RÜCKFRAGE NÖTIG | NICHT GEEIGNET
SICHERHEIT: hoch | mittel | niedrig
KURZBEGRÜNDUNG: <maximal 5 Sätze>

KRITERIEN
1. Lokale/redaktionelle Passung: PASST | UNKLAR | PASST NICHT — <kurze Begründung>
2. Inhalte/Quellen: PASST | UNKLAR | PASST NICHT — <kurze Begründung>
3. Mehrwert/Reichweite: PASST | UNKLAR | PASST NICHT — <kurze Begründung>
4. Zusammenarbeit/Pflege: PASST | UNKLAR | PASST NICHT — <kurze Begründung>
5. Aufwand/weiterer Weg: PASST | UNKLAR | PASST NICHT — <kurze Begründung>
6. Rechte/Technik/Pflichtangaben: PASST | UNKLAR | PASST NICHT — <kurze Begründung>

OFFENE PUNKTE / KONKRETE RÜCKFRAGEN
- <nur tatsächlich notwendige Punkte; sonst „Keine“>

QUELLEN
- <ausschließlich kandidatenbezogene Quelle und kanonische URL ohne Trackingparameter; falls keine: „Keine belastbare kandidatenbezogene öffentliche Quelle gefunden.“>

Schließe mit einem Satz ab: „Verbindliche Entscheidung bleibt beim Betreiber von Bocholt erleben.“`;
}

async function copyText(text){
  try{
    if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(text);return true;}
  }catch(_error){}
  const area=document.createElement('textarea');
  area.value=text;area.setAttribute('readonly','');area.style.position='fixed';area.style.opacity='0';
  document.body.appendChild(area);area.select();
  const copied=document.execCommand?.('copy')===true;area.remove();return copied;
}

function mutationId(prefix='gate2:304:review'){return `${prefix}:${operationId().replace(/^cc:/,'')}`;}
function operator(data){return clean(data.assigned_to)||'Steuerzentrale';}
async function latest(item){return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`,{timeoutMs:15000});}
function renderedText(text){return escapeHtml(text).replace(/\r?\n/g,'<br>');}
function openQuestion(data={},mode='prepared'){
  const question=clean(data.status_reason);if(!question)return '';
  const waiting=mode==='waiting';
  return `<section class="cc-startpartner-panel cc-startpartner-open-question">
    <header><div><span class="cc-kicker">${waiting?'Versendete Rückfrage':'Rückfrage vorbereitet'}</span><h3>${waiting?'Rückmeldung ausstehend':'Diese Angaben fehlen noch'}</h3></div></header>
    <p>${renderedText(question)}</p>
    <div class="cc-actions cc-actions--inline">${waiting
      ? '<button class="cc-button cc-button--primary" data-review-action="record_review_reply">Antwort eintragen</button><button class="cc-button cc-button--secondary" data-review-action="resend_review_question">Rückfrage erneut senden</button>'
      : '<button class="cc-button cc-button--primary" data-review-action="send_review_question">Rückfrage senden</button><button class="cc-button cc-button--secondary" data-review-action="review_needs_information">Rückfrage ändern und senden</button>'}
    </div>
  </section>`;
}
function decisionCommunicationPanel(data={}){
  const status=String(data.status||'');
  const config={
    accepted_pending_terms:['accepted','Aufnahmebestätigung'],
    rejected:['rejected','Ablehnungsnachricht'],
    waitlisted:['waitlisted','Wartelisten-Nachricht'],
  }[status];
  if(!config)return '';
  const [topic,label]=config;const event=latestMailEvent(data,topic);
  if(event?.event_type==='review_mail_sent')return '';
  const retryAction={accepted:'retry_review_accepted',rejected:'retry_review_rejected',waitlisted:'retry_review_waitlisted'}[topic];
  const failed=event?.event_type==='review_mail_failed';
  return `<section class="cc-startpartner-panel cc-startpartner-open-question">
    <header><div><span class="cc-kicker">Kommunikation</span><h3>${failed?'Versand fehlgeschlagen':`${label} noch offen`}</h3></div></header>
    <p>${failed?'Die fachliche Entscheidung ist gespeichert, die E-Mail wurde aber nicht bestätigt versendet.':'Für diese Entscheidung ist noch kein erfolgreicher Mailversand dokumentiert.'}</p>
    <button class="cc-button cc-button--secondary" data-review-action="${retryAction}">${escapeHtml(label)} erneut senden</button>
  </section>`;
}

export function renderStartpartnerAiReview(data={}){
  const status=String(data.status||'');
  const communication=decisionCommunicationPanel(data);
  if(communication)return communication;
  if(status==='contact_pending'||status==='needs_information')return openQuestion(data,'prepared');
  if(status==='awaiting_response')return openQuestion(data,'waiting');
  if(!startpartnerAiReviewStatuses.has(status))return '';
  const hard=Boolean(data.capacity?.hard_stop);
  const positive=hard
    ? '<button class="cc-button cc-button--primary" data-review-action="review_waitlist">Auf Warteliste</button>'
    : '<button class="cc-button cc-button--primary" data-review-action="review_approve">Startpartner aufnehmen</button>';
  return `<section class="cc-startpartner-panel cc-startpartner-ai-review">
    <header><div><span class="cc-kicker">KI-gestützte Prüfung</span><h3>Prüfen lassen, selbst entscheiden</h3></div></header>
    <p>Der Prüfprompt enthält nur die fachlich nötigen Anfragedaten. ChatGPT recherchiert und gibt eine Empfehlung; die verbindliche Entscheidung bleibt bei dir.</p>
    <button class="cc-button cc-button--secondary cc-button--large" data-review-action="copy_review_prompt">Prüfprompt kopieren</button>
    <div class="cc-actions cc-actions--inline">${positive}<button class="cc-button cc-button--secondary" data-review-action="review_needs_information">Rückfrage nötig</button><button class="cc-button cc-button--danger" data-review-action="review_reject">Nicht geeignet</button></div>
  </section>`;
}

export function aiReviewStatusLabel(status){
  if(status==='contact_pending'||status==='needs_information')return 'Rückfrage vorbereitet';
  if(status==='awaiting_response')return 'Rückmeldung ausstehend';
  return startpartnerAiReviewStatuses.has(String(status||''))?'Prüfung offen':'';
}

async function sendCommunication(item,topic,reload,customerMessage=''){
  setStatus('Aktueller Startpartner-Stand wird geladen …');
  try{
    const detail=await latest(item);const data=detail.startpartner_candidate||{};
    const payload={
      candidate_id:data.id,operation_id:mutationId('gate2:304:mail'),expected_revision:Number(data.revision),
      operator_name:operator(data),topic,
    };
    if(customerMessage)payload.customer_message=customerMessage;
    const result=await api('/api/startpartner/review-communication.php',{method:'POST',body:JSON.stringify(payload),timeoutMs:70000});
    await reload({throwOnError:true});
    if(result.sent)setStatus(topic==='question'?'Rückfrage versendet. Rückmeldung ausstehend.':'Nachricht versendet.','success');
    else setStatus('E-Mail konnte nicht versendet werden. Der fachliche Stand bleibt erhalten; der Versand kann erneut versucht werden.','attention');
  }catch(error){
    if(error.status===409)await reload({throwOnError:true}).catch(()=>{});
    setStatus(error.status===409?'Zwischenzeitlich geändert. Bitte den aktuellen Stand prüfen.':error.message,'attention');
  }
}
function failedCustomerMessage(data={},topic=''){
  const event=latestMailEvent(data,topic);return clean(event?.payload?.customer_message);
}
async function recordReplyDialog(item,reload){
  setStatus('Aktuelle Rückfrage wird geladen …');
  try{
    const detail=await latest(item);const data=detail.startpartner_candidate||{};
    openDialog(`<h2>Antwort eintragen</h2><p class="cc-hint">Übernimm die relevante Rückmeldung des Startpartners. Danach ist die Anfrage wieder offen für die nächste Prüfung.</p><div id="cc-dialog-message"></div><div class="cc-stack">${textarea('sp-review-reply','Nachgereichte Angaben','','required')}<button type="button" class="cc-button cc-button--primary" id="sp-review-reply-confirm">Antwort speichern</button></div>`,'cc-dialog--wide');
    setStatus('');
    document.querySelector('#sp-review-reply-confirm')?.addEventListener('click',async event=>{
      event.currentTarget.disabled=true;const reply=value('#sp-review-reply');
      if(!reply){dialogMessage('Bitte die nachgereichten Angaben eintragen.');event.currentTarget.disabled=false;return;}
      try{
        await api('/api/startpartner/action.php',{method:'POST',body:JSON.stringify({
          candidate_id:data.id,operation_id:mutationId('gate2:304:reply'),expected_revision:Number(data.revision),operator_name:operator(data),
          action:'start_qualification',reason:reply,
        }),timeoutMs:70000});
        await reload({throwOnError:true});closeDialog();setStatus('Antwort gespeichert. Die Anfrage ist wieder zur Prüfung offen.','success');
      }catch(error){
        if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');}
        else dialogMessage(error.message||'Die Antwort konnte nicht gespeichert werden.');
        event.currentTarget.disabled=false;
      }
    });
  }catch(error){setStatus(error.message,'attention');}
}

export async function handleStartpartnerAiReviewAction(item,action,reload){
  if(action==='copy_review_prompt'||action==='edit_qualification'||action==='start_prequalification'){
    setStatus('Aktuelle Anfragedaten werden geladen …');
    try{
      const detail=await latest(item);const copied=await copyText(reviewPrompt(detail.startpartner_candidate||{}));
      setStatus(copied?'Prüfprompt kopiert. In ChatGPT einfügen und die Auswertung anschließend hier entscheiden.':'Prüfprompt konnte nicht automatisch kopiert werden.',copied?'success':'attention');
    }catch(error){setStatus(error.message,'attention');}
    return true;
  }
  if(action==='send_review_question'||action==='resend_review_question'){
    const detail=await latest(item).catch(error=>{setStatus(error.message,'attention');return null;});if(!detail)return true;
    const question=clean(detail.startpartner_candidate?.status_reason);
    if(!question){setStatus('Keine Rückfrage zum Versenden gefunden.','attention');return true;}
    await sendCommunication(item,'question',reload,question);return true;
  }
  if(action==='record_review_reply'){await recordReplyDialog(item,reload);return true;}
  const retryTopicByAction={retry_review_accepted:'accepted',retry_review_rejected:'rejected',retry_review_waitlisted:'waitlisted'};
  if(retryTopicByAction[action]){
    const detail=await latest(item).catch(error=>{setStatus(error.message,'attention');return null;});if(!detail)return true;
    const data=detail.startpartner_candidate||{};const topic=retryTopicByAction[action];
    await sendCommunication(item,topic,reload,failedCustomerMessage(data,topic));return true;
  }

  const decisionByAction={review_approve:'approve',review_needs_information:'needs_information',review_reject:'reject',review_waitlist:'waitlist'};
  const decision=decisionByAction[action];if(!decision)return false;

  setStatus('Aktueller Startpartner-Stand wird geladen …');
  try{
    const detail=await latest(item);const data=detail.startpartner_candidate||{};
    let title='Entscheidung speichern',body='',tone='primary';
    if(decision==='approve'){
      title='Startpartner aufnehmen';
      body=`<p class="cc-hint">Du bestätigst die Aufnahme selbst. Das System reserviert den Platz für 20 Tage und sendet anschließend die Aufnahmebestätigung. Pilot, Tarif und Zahlung starten dadurch noch nicht.</p>${textarea('sp-review-reason','Interne Notiz zur Aufnahme (optional)','')}${data.capacity?.soft_stop?textarea('sp-capacity-reason','Begründung für die Kapazitätsausnahme','','required'):''}`;
    }else if(decision==='needs_information'){
      title='Rückfrage senden';body=`<p class="cc-hint">Die bestätigte Rückfrage wird automatisch an den hinterlegten Hauptkontakt gesendet. Erst nach erfolgreichem Versand wechselt der Fall auf „Rückmeldung ausstehend“.</p>${textarea('sp-review-reason','Welche Information fehlt bzw. soll zurückgefragt werden?','','required')}`;
    }else if(decision==='reject'){
      title='Nicht geeignet';tone='danger';body=`${textarea('sp-review-reason','Interne Begründung','','required')}${textarea('sp-review-customer-message','Nachricht an den Antragsteller (optional)','')}<p class="cc-hint">Die interne Begründung wird nicht automatisch nach außen übernommen. Ohne separate Nachricht verwendet das System eine neutrale Standardformulierung.</p>`;
    }else{
      title='Auf Warteliste';body=`<p class="cc-hint">Die fachliche Eignung wird nicht verworfen. Wegen der Kapazitätsgrenze wird der Kandidat vorgemerkt, standardmäßig in 14 Tagen erneut fällig und über die Warteliste informiert.</p>${textarea('sp-review-reason','Interner Prioritätshinweis (optional)','')}`;
    }
    openDialog(`<h2>${escapeHtml(title)}</h2><div id="cc-dialog-message"></div><div class="cc-stack">${body}<button type="button" class="cc-button cc-button--${tone}" id="sp-review-confirm">${escapeHtml(title)}</button></div>`,'cc-dialog--wide');
    setStatus('');
    document.querySelector('#sp-review-confirm')?.addEventListener('click',async event=>{
      event.currentTarget.disabled=true;
      const payload={candidate_id:data.id,operation_id:mutationId(),expected_revision:Number(data.revision),operator_name:operator(data),decision};
      const reason=value('#sp-review-reason');if(reason)payload.reason=reason;
      const customerMessage=value('#sp-review-customer-message');if(customerMessage)payload.customer_message=customerMessage;
      const capacityReason=value('#sp-capacity-reason');if(capacityReason)payload.capacity_exception_reason=capacityReason;
      try{
        const result=await api('/api/startpartner/review-decision.php',{method:'POST',body:JSON.stringify(payload),timeoutMs:70000});
        await reload({throwOnError:true});closeDialog();
        if(result.communication?.sent){
          const success=decision==='needs_information'?'Rückfrage versendet. Rückmeldung ausstehend.':'Entscheidung gespeichert und passende Nachricht versendet.';
          setStatus(success,'success');
        }else{
          setStatus('Entscheidung gespeichert. Die E-Mail konnte nicht bestätigt versendet werden und kann in der Ansicht erneut gesendet werden.','attention');
        }
      }catch(error){
        if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');}
        else dialogMessage(error.message||'Die Entscheidung konnte nicht gespeichert werden.');
        event.currentTarget.disabled=false;
      }
    });
  }catch(error){setStatus(error.message,'attention');}
  return true;
}
