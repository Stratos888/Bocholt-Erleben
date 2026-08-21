import {
  escapeHtml, clean, api, openDialog, closeDialog, dialogMessage,
  textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const scopeLabels={
  events:'Veranstaltungen',activities:'Aktivitäten',both:'Veranstaltungen und Aktivitäten',unknown:'Noch offen',
};
export const startpartnerAiReviewStatuses=new Set([
  'new','prequalifying','contact_pending','awaiting_response','qualifying','needs_information','decision_ready',
]);

function reviewPrompt(data={}){
  const organization=clean(data.organization_name)||'Nicht angegeben';
  const website=clean(data.website_url)||'Nicht angegeben';
  const scope=scopeLabels[data.desired_content_scope]||clean(data.desired_content_scope)||'Noch offen';
  const description=clean(data.description_text)||'Nicht angegeben';
  return `Du prüfst für die lokale Plattform „Bocholt erleben“, ob eine Organisation als Startpartner für einen kostenlosen sechsmonatigen Test geeignet ist.

WICHTIGER ROLLENRAHMEN
Du recherchierst, analysierst und gibst eine Empfehlung. Du triffst keine verbindliche Aufnahmeentscheidung. Diese trifft anschließend ein Mensch. Bewerte keine Kapazität; die maximal acht Startpartnerplätze werden separat im System geprüft.

ANFRAGEDATEN
Organisation: ${organization}
Website / öffentliche Quelle: ${website}
Gewünschter Bereich: ${scope}
Beschreibung aus der Anfrage: ${description}

AUFGABE
1. Prüfe zuerst die angegebene Website/Quelle. Wenn keine Website angegeben ist, recherchiere anhand des Organisationsnamens. Nutze bei Bedarf weitere belastbare öffentliche Quellen.
2. Trenne belegte Fakten klar von Annahmen. Erfinde nichts. Wenn eine Information öffentlich nicht verlässlich feststellbar ist, markiere sie als offen.
3. Prüfe diese sechs Punkte:
   a) lokale und redaktionelle Passung zu Bocholt erleben
   b) geeignete Inhalte bzw. belastbare Quellen für Veranstaltungen/Aktivitäten
   c) relevanter Mehrwert für Nutzer und potenzieller Beitrag zur Reichweite
   d) realistische Zusammenarbeit und laufende Pflege; soweit öffentlich nicht prüfbar, als Rückfrage markieren statt negativ zu unterstellen
   e) sinnvoller Einrichtungs-/Betreuungsaufwand und plausibler späterer regulärer Weg
   f) erkennbare Rechte-, Technik- oder Pflichtangaben-Risiken; nicht öffentlich klärbare Rechtefragen als Rückfrage markieren
4. Eine grundsätzlich passende Organisation darf nicht allein deshalb als ungeeignet gelten, weil interne Kooperationsdetails oder Rechtebestätigungen noch erfragt werden müssen. Dann ist „RÜCKFRAGE NÖTIG“ die richtige Empfehlung.
5. Recherchiere keine privaten personenbezogenen Daten und versuche nicht, Kontaktinformationen zu ergänzen.
6. Führe unter QUELLEN ausschließlich belastbare öffentliche Quellen auf, die konkrete Aussagen über die zu prüfende Organisation oder deren Angebote belegen. Allgemeine Referenzseiten zu Bocholt, Veranstaltungskalender oder Bocholt erleben sind keine Kandidatenbelege und dürfen dort nicht erscheinen. Verwende möglichst die kanonische Quell-URL und entferne unnötige Trackingparameter wie utm_*. Wenn keine belastbare kandidatenbezogene öffentliche Quelle gefunden wurde, gib unter QUELLEN ausschließlich „Keine belastbare kandidatenbezogene öffentliche Quelle gefunden.“ aus.

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

function mutationId(){return `gate2:299:review:${operationId().replace(/^cc:/,'')}`;}
function operator(data){return clean(data.assigned_to)||'Steuerzentrale';}
async function latest(item){return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`,{timeoutMs:15000});}
function openQuestion(data={}){
  if(String(data.status||'')!=='needs_information')return '';
  const question=clean(data.status_reason);if(!question)return '';
  const rendered=escapeHtml(question).replace(/\r?\n/g,'<br>');
  return `<section class="cc-startpartner-panel cc-startpartner-open-question">
    <header><div><span class="cc-kicker">Offene Rückfrage</span><h3>Diese Angaben fehlen noch</h3></div></header>
    <p>${rendered}</p>
    <button class="cc-button cc-button--secondary" data-review-action="copy_review_question">Rückfrage kopieren</button>
  </section>`;
}

export function renderStartpartnerAiReview(data={}){
  if(!startpartnerAiReviewStatuses.has(String(data.status||'')))return '';
  const hard=Boolean(data.capacity?.hard_stop);
  const positive=hard
    ? '<button class="cc-button cc-button--primary" data-review-action="review_waitlist">Auf Warteliste</button>'
    : '<button class="cc-button cc-button--primary" data-review-action="review_approve">Startpartner aufnehmen</button>';
  const questionActionLabel=String(data.status||'')==='needs_information'?'Rückfrage ändern':'Rückfrage nötig';
  return `${openQuestion(data)}<section class="cc-startpartner-panel cc-startpartner-ai-review">
    <header><div><span class="cc-kicker">KI-gestützte Prüfung</span><h3>Prüfen lassen, selbst entscheiden</h3></div></header>
    <p>Der Prüfprompt enthält nur die fachlich nötigen Anfragedaten. ChatGPT recherchiert und gibt eine Empfehlung; die verbindliche Entscheidung bleibt bei dir.</p>
    <button class="cc-button cc-button--secondary cc-button--large" data-review-action="copy_review_prompt">Prüfprompt kopieren</button>
    <div class="cc-actions cc-actions--inline">${positive}<button class="cc-button cc-button--secondary" data-review-action="review_needs_information">${questionActionLabel}</button><button class="cc-button cc-button--danger" data-review-action="review_reject">Nicht geeignet</button></div>
  </section>`;
}

export function aiReviewStatusLabel(status){
  if(status==='needs_information')return 'Rückfrage nötig';
  return startpartnerAiReviewStatuses.has(String(status||''))?'Prüfung offen':'';
}

export async function handleStartpartnerAiReviewAction(item,action,reload){
  if(action==='copy_review_question'){
    setStatus('Aktuelle Rückfrage wird geladen …');
    try{
      const detail=await latest(item);const question=clean(detail.startpartner_candidate?.status_reason);
      if(!question){setStatus('Keine offene Rückfrage gefunden.','attention');return true;}
      const copied=await copyText(question);
      setStatus(copied?'Rückfrage kopiert.':'Rückfrage konnte nicht automatisch kopiert werden.',copied?'success':'attention');
    }catch(error){setStatus(error.message,'attention');}
    return true;
  }
  if(action==='copy_review_prompt'||action==='edit_qualification'||action==='start_prequalification'){
    setStatus('Aktuelle Anfragedaten werden geladen …');
    try{
      const detail=await latest(item);const copied=await copyText(reviewPrompt(detail.startpartner_candidate||{}));
      setStatus(copied?'Prüfprompt kopiert. In ChatGPT einfügen und die Auswertung anschließend hier entscheiden.':'Prüfprompt konnte nicht automatisch kopiert werden.',copied?'success':'attention');
    }catch(error){setStatus(error.message,'attention');}
    return true;
  }
  const decisionByAction={review_approve:'approve',review_needs_information:'needs_information',review_reject:'reject',review_waitlist:'waitlist'};
  const decision=decisionByAction[action];if(!decision)return false;

  setStatus('Aktueller Startpartner-Stand wird geladen …');
  try{
    const detail=await latest(item);const data=detail.startpartner_candidate||{};
    let title='Entscheidung speichern',body='',tone='primary';
    if(decision==='approve'){
      title='Startpartner aufnehmen';
      body=`<p class="cc-hint">Die ChatGPT-Auswertung ist Entscheidungshilfe. Du bestätigst die Aufnahme selbst. Es wird nur ein Platz für 20 Tage reserviert; noch kein Pilot gestartet.</p>${textarea('sp-review-reason','Notiz zur Aufnahme (optional)','')}${data.capacity?.soft_stop?textarea('sp-capacity-reason','Begründung für die Kapazitätsausnahme','','required'):''}`;
    }else if(decision==='needs_information'){
      title='Rückfrage nötig';body=textarea('sp-review-reason','Welche Information fehlt bzw. soll zurückgefragt werden?','','required');
    }else if(decision==='reject'){
      title='Nicht geeignet';tone='danger';body=textarea('sp-review-reason','Begründung','','required');
    }else{
      title='Auf Warteliste';body=`<p class="cc-hint">Die fachliche Eignung wird nicht verworfen. Wegen der Kapazitätsgrenze wird der Kandidat vorgemerkt und standardmäßig in 14 Tagen erneut fällig.</p>${textarea('sp-review-reason','Priorität / Hinweis (optional)','')}`;
    }
    openDialog(`<h2>${escapeHtml(title)}</h2><div id="cc-dialog-message"></div><div class="cc-stack">${body}<button type="button" class="cc-button cc-button--${tone}" id="sp-review-confirm">${escapeHtml(title)}</button></div>`,'cc-dialog--wide');
    setStatus('');
    document.querySelector('#sp-review-confirm')?.addEventListener('click',async event=>{
      event.currentTarget.disabled=true;
      const payload={candidate_id:data.id,operation_id:mutationId(),expected_revision:Number(data.revision),operator_name:operator(data),decision};
      const reason=value('#sp-review-reason');if(reason)payload.reason=reason;
      const capacityReason=value('#sp-capacity-reason');if(capacityReason)payload.capacity_exception_reason=capacityReason;
      try{
        await api('/api/startpartner/review-decision.php',{method:'POST',body:JSON.stringify(payload),timeoutMs:70000});
        await reload({throwOnError:true});closeDialog();setStatus('Entscheidung gespeichert und aktueller Stand neu geladen.','success');
      }catch(error){
        if(error.status===409){await reload({throwOnError:true}).catch(()=>{});dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');}
        else dialogMessage(error.message||'Die Entscheidung konnte nicht gespeichert werden.');
        event.currentTarget.disabled=false;
      }
    });
  }catch(error){setStatus(error.message,'attention');}
  return true;
}
