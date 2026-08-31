import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args=process.argv.slice(2);
const value=name=>{const index=args.indexOf(name);return index>=0?args[index+1]:'';};
const baseUrl=value('--base-url');
const outDir=value('--out-dir');
if(!baseUrl||!outDir){console.error('Usage: node tests/startpartner_premium_finish_browser_test.mjs --base-url URL --out-dir DIR');process.exit(2);}
fs.mkdirSync(outDir,{recursive:true});
function assert(condition,message){if(!condition)throw new Error(message);}
function includes(text,marker){return text.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE'));}

const pilotId='24100000-0000-4000-8000-000000000002';
const contentId='24100000-0000-4000-8000-000000000010';
const distributionId='24100000-0000-4000-8000-000000000030';

const responsiveProfiles=[
  {name:'mobile-360x780',viewport:{width:360,height:780},mode:'mobile'},
  {name:'mobile-390x844',viewport:{width:390,height:844},mode:'mobile'},
  {name:'pre-desktop-719',viewport:{width:719,height:900},mode:'mobile'},
  {name:'pre-desktop-720',viewport:{width:720,height:900},mode:'mobile'},
  {name:'pre-desktop-759',viewport:{width:759,height:900},mode:'mobile'},
  {name:'pre-desktop-760',viewport:{width:760,height:900},mode:'mobile'},
  {name:'pre-desktop-899',viewport:{width:899,height:900},mode:'mobile'},
  {name:'desktop-900',viewport:{width:900,height:900},mode:'desktop'},
  {name:'desktop-1024',viewport:{width:1024,height:900},mode:'desktop'},
  {name:'desktop-1099',viewport:{width:1099,height:900},mode:'desktop'},
  {name:'desktop-1100',viewport:{width:1100,height:900},mode:'desktop'},
  {name:'desktop-1279',viewport:{width:1279,height:900},mode:'desktop'},
  {name:'desktop-1280',viewport:{width:1280,height:900},mode:'desktop'},
  {name:'desktop-1440',viewport:{width:1440,height:900},mode:'desktop'},
  {name:'desktop-1478',viewport:{width:1478,height:900},mode:'desktop'},
];

const formScreenshotProfiles=new Set(['mobile-390x844','pre-desktop-899','desktop-900','desktop-1280','desktop-1478']);
const historyScreenshotProfiles=new Set(['mobile-390x844','desktop-900','desktop-1440']);
const anchorEvidenceProfiles=new Set(['mobile-390x844','desktop-1280']);

function activeCandidate(){
  const onboardingKeys=['terms_confirmed','organizer_linked','contact_confirmed','portal_access_tested','pilot_entitlement_readback','service_scope_confirmed','sources_recorded','maintenance_path_agreed','content_rights_cleared','first_content_ready','editorial_review_ready','measurement_ready','distribution_ready','activation_target_set'];
  const pilot={id:pilotId,status:'active',revision:7,activation_date_local:'2026-08-01',planned_end_date:'2027-02-01'};
  const scopes=[
    {scope_key:'events',status:'active',limit_value:8,is_unlimited:false,period_unit:'pilot_month'},
    {scope_key:'activities',status:'active',limit_value:1,is_unlimited:false,period_unit:'concurrent'},
  ];
  const content={id:contentId,submission_id:4241,content_type:'event',status:'approved',title:'Synthetischer Startpartner-Kulturtag',start_date:'2026-09-12'};
  const commitment={id:distributionId,status:'ready',channel:'Newsletter',planned_at:'2026-09-20 12:00:00',target_reference:'https://example.org/reach'};
  return {
    id:'19900000-0000-0000-0000-000000009999',organization_name:'Gate-4-Kulturverein Bocholt',source:'targeted_outreach',desired_content_scope:'both',status:'accepted_pending_terms',revision:12,assigned_to:'M. Muster',next_review_at:'2026-09-01 10:00:00',website_url:'https://example.org/startpartner',description_text:'Lokaler Kulturverein mit Veranstaltungen und Aktivitäten.',contacts:[{contact_name:'Erika Beispiel',email:'erika@example.org',is_primary:true}],readiness:{ready:true,blockers:[]},capacity:{occupied_slots:1,active_pilots:1,active_reservations:0,hard_stop_at:8,soft_stop_at:6},reservations:[],active_reservation:null,waitlist:null,decision:{result:'accepted_pending_terms',reason:'Fachlich geeignet.'},events:[],
    gate3:{complete:true,blockers:[],terms_acceptance:{id:301,terms_version:'startpartner-pilot-2026-08-v2',accepted_at:'2026-08-25 12:00:00',confirmation_channel:'email_reply',no_automatic_paid_renewal:1},organizer:{id:401,organization_name:'Gate-4-Kulturverein Bocholt',email:'erika@example.org'},pilot,scopes,entitlement:{id:'23100000-0000-4000-8000-000000000002',status:'active'},events:[]},
    gate4:{phase:'active',complete:true,active:true,effective_active:true,activation_ready:false,pilot,scopes,onboarding:{ready:true,completed_count:14,total_count:14,items:onboardingKeys.map(item_key=>({item_key,status:'complete',is_required:1,is_hard_blocker:1,is_manual:0,evidence_text:`Systemnachweis ${item_key}`,revision:1})),blockers:[]},content_links:[content],first_content:content,ready_measurement:{id:'24100000-0000-4000-8000-000000000020',checked_at:'2026-08-01 10:00:00'},ready_distribution:commitment,measurement_runtime:{status:'usage_observed',observed_actions:3},distribution_runtime:{status:'planned',commitment},lifecycle:{checkpoints:[],closeout_required:false},limits:{event:{available:true,used:1,limit:8,is_unlimited:false,full:false,reset_date_local:'2026-09-01'},activity:{available:true,used:0,limit:1,is_unlimited:false,full:false}},next_action:{code:'monitor_active_pilot',label:'Aktiven Pilot beobachten',action:null},blockers:[],capacity:{occupied_slots:1,active_pilots:1,active_reservations:0,hard_stop_at:8,soft_stop_at:6}},
  };
}

function partnerProjection(){
  const candidate=activeCandidate();
  return {organizer_id:401,gate4:{
    ...candidate.gate4,
    next_action:{code:'submit_content',label:'Nächsten Termin einreichen',content_type:'event'},
    distribution:{status:'due'},
    measurement:{status:'no_data_yet_or_too_short'},
  }};
}

function zeroImpactMetrics(){
  return {website_clicks:0,maps_clicks:0,location_clicks:0,organizer_cta_clicks:0,share_clicks:0,copy_link_clicks:0,detail_views:0,total_interactions:0};
}

function organizerPortalProjection(){
  const submission={
    id:4241,
    submission_kind:'event',
    status:'approved',
    payment_kind:'single',
    title:'Synthetischer Startpartner-Kulturtag',
    start_date:'2026-09-12',
    time_text:'19:00 Uhr',
    location_name:'Kulturort Bocholt',
    location_address:'Musterstraße 1, Bocholt',
    event_url:'https://example.org/event',
    ticket_url:'',
    description_text:'Synthetischer Browsernachweis ohne Produktionswirkung.',
    location_public_confirmed:1,
    created_at:'2026-08-25 10:00:00',
    updated_at:'2026-08-25 12:00:00',
    impact_entity_id:'4241',
    impact_metrics:zeroImpactMetrics(),
    impact_last_interaction_at:null,
  };
  return {
    organizer:{id:401,organization_name:'Gate-4-Kulturverein Bocholt',contact_name:'Erika Beispiel',email:'erika@example.org',default_plan_key:'single',stripe_customer_id:null},
    portal_session:{id:7001,expires_at_utc:'2026-09-30 00:00:00',last_seen_at_utc:'2026-08-30 20:00:00'},
    subscription:null,
    active_subscriptions:[],
    quota:{entitlement_count:0,has_unlimited:false,included_total:0,consumed_total:0,remaining_total:0,current_period_start:null,current_period_end:null},
    quota_by_plan:[],
    billing_summary:{currency:'EUR',subscription_count:0,monthly_total_cents:0,monthly_total_label:'0,00 € / Monat',items:[]},
    impact_summary:{
      status:'ok',
      reporting_target:{type:'organizer',id:'401'},
      period:{start_date:'2026-08-03',end_date:'2026-08-30'},
      previous_period:{start_date:'2026-07-06',end_date:'2026-08-02'},
      metrics:zeroImpactMetrics(),
      previous_metrics:zeroImpactMetrics(),
      items:[],
    },
    recent_submissions:[submission],
    published_content:[submission],
  };
}

async function operatorContract(browser,profile){
  const {viewport,name}=profile;
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
  await page.route('**/api/control-center/case.php*',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{id:'fixture-gate4',startpartner_candidate:activeCandidate()}})}));
  await page.goto(`${baseUrl}/tests/fixtures/control_center_gate4_review.html`,{waitUntil:'networkidle'});
  await page.waitForSelector('html[data-fixture-ready="true"]');
  const panel=page.locator('[data-gate4-panel]:visible');
  const history=page.locator('details.cc-startpartner-history');
  assert(await panel.count()===1,`${name}: aktueller Pilotbereich fehlt`);
  assert(await history.count()===1,`${name}: genau eine Aufnahme-/Einrichtungshistorie erwartet`);
  assert(!(await history.evaluate(node=>node.open)),`${name}: Historie muss initial eingeklappt sein`);
  const bodyText=await page.locator('body').innerText();
  for(const marker of ['Pilotphase läuft','Aktiven Pilot beobachten','Aufnahme & Einrichtung'])assert(includes(bodyText,marker),`${name}: sichtbarer Marker fehlt: ${marker}`);
  for(const stale of ['Piloteinrichtung vorbereitet','Platz reserviert · Bedingungen offen','Aufnahmebestätigung noch offen'])assert(!includes(bodyText,stale),`${name}: historischer Stand wirkt noch wie aktuelle Arbeit: ${stale}`);
  const order=await page.evaluate(()=>{
    const panel=document.querySelector('[data-gate4-panel]');const history=document.querySelector('details.cc-startpartner-history');
    return Boolean(panel&&history&&(panel.compareDocumentPosition(history)&Node.DOCUMENT_POSITION_FOLLOWING));
  });
  assert(order,`${name}: aktueller Pilotbereich muss vor der Historie stehen`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  await page.screenshot({path:path.join(outDir,`startpartner-premium-operator-${name}.png`),fullPage:true});

  await history.locator(':scope > summary').click();
  const historyText=await history.innerText();
  assert(includes(historyText,'Vor Pilotstart abgeschlossen'),`${name}: historische Einrichtung ist nicht als abgeschlossen bezeichnet`);
  assert(includes(historyText,'Abgeschlossen'),`${name}: Abschlussstatus der Historie fehlt`);
  for(const stale of ['Piloteinrichtung vorbereitet','Pilotstart ausstehend','Veröffentlichung noch nicht freigeschaltet','Platz reserviert · Bedingungen offen'])assert(!includes(historyText,stale),`${name}: historische Detailkopie bleibt zukunftsgerichtet: ${stale}`);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${name}: Historie erzeugt horizontalen Überlauf`);
  if(historyScreenshotProfiles.has(name))await page.screenshot({path:path.join(outDir,`startpartner-premium-operator-${name}-history-open.png`),fullPage:true});
  await context.close();
}

async function partnerContract(browser,profile){
  const {viewport,name,mode}=profile;
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
  await page.route('**/api/organizer-portal/me.php',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:organizerPortalProjection()})}));
  await page.route('**/api/organizer-portal/pilot.php',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:partnerProjection()})}));
  await page.goto(`${baseUrl}/fuer-veranstalter/dashboard/`,{waitUntil:'networkidle'});
  await page.waitForSelector('#organizer-dashboard-pilot-card:not([hidden])');
  const card=page.locator('#organizer-dashboard-pilot-card');
  const text=await card.innerText();
  assert(includes(text,'Startpartner · 6 Monate kostenlos'),`${name}: kompakter Pilotkontext fehlt`);
  assert(includes(text,'Pilotphase läuft'),`${name}: aktuelle Phase fehlt`);
  assert(includes(text,'Nächste Veranstaltung einreichen'),`${name}: primärer nächster Schritt fehlt`);
  assert(includes(text,'Deine Inhalte'),`${name}: Inhaltsbereich fehlt`);
  assert(includes(text,'Veröffentlicht'),`${name}: veröffentlichter Pilotinhalt ist nicht verständlich bezeichnet`);
  assert(includes(text,'Pilotdetails'),`${name}: sekundäre Pilotdetails fehlen`);
  assert(includes(text,'Details & Änderungen'),`${name}: sekundärer Detail-/Änderungspfad fehlt`);
  for(const stale of ['Event-Limit','Pilotverbrauch','Pilotumfang und Laufzeit','Kostenlos zur Prüfung einreichen','Freigegebene Inhalte'])assert(!includes(text,stale),`${name}: interne/alte oder redundante Sprache sichtbar: ${stale}`);
  for(const operational of ['Reichweitenbeitrag ist fällig','belastbare Nutzungsbewertung'])assert(!includes(text,operational),`${name}: operative/no-data Erklärung ist im Premium-Partnerkontext sichtbar: ${operational}`);
  assert(await card.locator('.organizer-status-badge').count()===0,`${name}: redundanter Status-Badge ist noch vorhanden`);
  assert(await card.locator('.content-card .content-card').count()===0,`${name}: verschachtelte Kartenhierarchie im Pilotbereich`);
  assert(await card.locator('.content-cta--primary:visible').count()===1,`${name}: genau eine prominente Aktion erwartet`);

  const genericHero=page.locator('main.page--organizers > .content-hero--panel');
  const genericSubmissions=page.locator('#organizer-dashboard-submissions-card');
  const genericSummary=page.locator('#organizer-dashboard-summary');
  const genericImpact=page.locator('#organizer-dashboard-impact-card');
  assert(await genericHero.isHidden(),`${name}: generischer Meine-Einreichung-Hero konkurriert mit dem Startpartner-Kontext`);
  assert(await genericSubmissions.isHidden(),`${name}: generische Einreichungs-/Statuskarte dupliziert den Pilotinhalt im Defaultzustand`);
  assert(await genericSummary.isHidden(),`${name}: generische Status-/Einreichungskarten duplizieren den aktiven Pilotkontext`);
  assert(await genericImpact.isHidden(),`${name}: Wirkung ohne belastbare Aktionen darf nicht als zusätzlicher leerer Bereich erscheinen`);
  const visibleText=await page.locator('body').innerText();
  assert(!includes(visibleText,'Für diese Einreichung ist keine Mitgliedschaft aktiv.'),`${name}: reguläre Mitgliedschafts-Negativbotschaft widerspricht dem aktiven kostenlosen Pilotkontext`);

  const openButton=card.locator('#organizer-pilot-open-form');
  const shell=card.locator('#organizer-pilot-form-shell');
  const form=card.locator('#organizer-pilot-content-form');
  assert(await openButton.isVisible(),`${name}: kompakte Einreichungsaktion fehlt`);
  assert(await shell.isHidden(),`${name}: vollständiges Formular ist initial sichtbar`);
  assert(await form.isHidden(),`${name}: Formular muss initial eingeklappt sein`);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${name}: horizontaler Überlauf`);
  await page.screenshot({path:path.join(outDir,`startpartner-premium-partner-${name}.png`),fullPage:true});

  await openButton.click();
  assert(await openButton.isHidden(),`${name}: Öffnen-Aktion bleibt nach Formularöffnung sichtbar`);
  assert(await shell.isVisible(),`${name}: Formular öffnet nicht`);
  assert(await card.locator('.content-cta--primary:visible').count()===1,`${name}: nach Formularöffnung mehr als eine Primäraktion sichtbar`);
  const formText=await form.innerText();
  assert(includes(formText,'Zur Prüfung einreichen'),`${name}: kanonischer Einreichungs-CTA fehlt`);
  assert(includes(formText,'Formular schließen'),`${name}: klarer Rückweg aus dem langen Formular fehlt`);
  assert(includes(formText,'Die Einreichung ist kostenlos und löst keine Zahlung aus.'),`${name}: fail-closed Zahlungsgrenze fehlt`);
  assert(includes(formText,'Titel der Veranstaltung'),`${name}: Event-Feldsprache ist nicht am regulären Funnel ausgerichtet`);
  assert(includes(formText,'Veranstaltungsort / Location'),`${name}: Event-Ortsfeld ist nicht konsistent`);
  const columns=await form.locator('.content-form-grid').evaluate(element=>getComputedStyle(element).gridTemplateColumns.split(' ').filter(Boolean).length);
  assert(columns===(mode==='desktop'?2:1),`${name}: ${mode==='desktop'?'Desktop':'Mobile/Pre-Desktop'}-Formular erwartet ${mode==='desktop'?2:1} Spalte(n), erhalten ${columns}`);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${name}: geöffnetes Formular erzeugt horizontalen Überlauf`);
  if(formScreenshotProfiles.has(name))await page.screenshot({path:path.join(outDir,`startpartner-premium-partner-${name}-form-open.png`),fullPage:true});

  if(name==='mobile-390x844'){
    const type=form.locator('[name="content_type"]');
    await type.selectOption('activity');
    const activityText=await form.innerText();
    for(const marker of ['Name der Aktivität','Name des Standorts','Adresse / offizieller Treffpunkt','Website / Buchungslink','Kurzbeschreibung der Aktivität'])assert(includes(activityText,marker),`partner-premium: Activity-Feldsprache fehlt: ${marker}`);
    assert(await form.locator('[data-pilot-event-date]').isHidden(),'partner-premium: Eventdatum bleibt bei Aktivität sichtbar');
  }

  await card.locator('#organizer-pilot-close-form').click();
  assert(await shell.isHidden(),`${name}: Formular lässt sich nicht wieder schließen`);
  assert(await openButton.isVisible(),`${name}: kompakte Einreichungsaktion kehrt nach dem Schließen nicht zurück`);

  if(name==='mobile-390x844'){
    await card.locator('#organizer-pilot-open-submission-details').click();
    assert(await genericSubmissions.isVisible(),'partner-premium: sekundärer Detail-/Änderungsbereich öffnet nicht');
    assert(includes(await page.locator('#organizer-dashboard-submissions-head').innerText(),'Details & Änderungen'),'partner-premium: sekundärer Detailbereich bleibt als konkurrierende aktuelle Einreichung bezeichnet');
    assert(await page.locator('#organizer-dashboard-submissions-summary').isHidden(),'partner-premium: redundante generische Statuszusammenfassung bleibt im Detailpfad sichtbar');
    assert(await page.locator('#organizer-dashboard-next-step').isHidden(),'partner-premium: redundanter generischer nächster Schritt bleibt im Detailpfad sichtbar');
    assert(await genericSummary.isHidden(),'partner-premium: generische Statuskarten werden durch den Detailpfad wieder eingeblendet');
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),'partner-premium: Detail-/Änderungspfad erzeugt horizontalen Überlauf');
    await page.screenshot({path:path.join(outDir,'startpartner-premium-partner-mobile-390x844-details-open.png'),fullPage:true});
  }
  await context.close();
}

async function visualSignature(page){
  return page.evaluate(()=>{
    const read=(selector,properties)=>{
      const element=document.querySelector(selector);
      if(!element)return null;
      const styles=getComputedStyle(element);
      return Object.fromEntries(properties.map(property=>[property,styles[property]]));
    };
    return {
      hero:read('.content-hero--panel',['borderRadius','boxShadow','paddingTop','paddingLeft','backgroundColor']),
      heading:read('.content-hero--panel h1',['fontFamily','fontWeight','lineHeight','letterSpacing']),
      card:read('.content-card',['borderRadius']),
      primary:read('.content-cta--primary',['borderRadius','minHeight','fontSize','fontWeight']),
    };
  });
}

async function componentSignature(page,selector,properties,label){
  const locator=page.locator(selector).first();
  assert(await locator.count()===1,`${label}: Referenzkomponente fehlt: ${selector}`);
  return locator.evaluate((element,props)=>{
    const styles=getComputedStyle(element);
    return Object.fromEntries(props.map(property=>[property,styles[property]]));
  },properties);
}

function assertVisualMatch(actual,reference,label){
  for(const group of Object.keys(reference)){
    assert(actual[group]&&reference[group],`${label}: visuelle Gruppe fehlt: ${group}`);
    for(const [property,value] of Object.entries(reference[group])){
      assert(actual[group][property]===value,`${label}: ${group}.${property} weicht ab (${actual[group][property]} != ${value})`);
    }
  }
}

async function publicFunnelContract(browser,profile){
  const {viewport,name,mode}=profile;
  const context=await browser.newContext({viewport});
  const page=await context.newPage();

  const formReferenceResponse=await page.goto(`${baseUrl}/events-veroeffentlichen/anbindung/`,{waitUntil:'networkidle'});
  assert(formReferenceResponse?.status()===200,`publish-form-reference-${name}: HTTP 200 fehlt`);
  const publishStartCardReference=await componentSignature(page,'.publish-start-card',['borderRadius','boxShadow'],`publish-form-reference-${name}`);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`publish-form-reference-${name}: horizontaler Überlauf`);

  const routes=[
    {name:'events-publish',path:'/events-veroeffentlichen/',markers:['Veranstaltung sichtbar machen','Wähle den passenden Veröffentlichungsweg']},
    {name:'activity-presence',path:'/aktivitaeten/sichtbar-werden/',markers:['Als Aktivität bei Bocholt erleben sichtbar werden','Wähle den passenden Tarif']},
    {name:'startpartner-public',path:'/startpartner/',markers:['Als Startpartner 6 Monate kostenlos testen','Veranstaltungen, Aktivitäten oder beides testen','keine Zahlungsart erforderlich','keine automatische kostenpflichtige Verlängerung','Wir prüfen, ob Startpartner zu deinem Angebot passt']},
  ];
  let reference=null;
  for(const route of routes){
    const response=await page.goto(`${baseUrl}${route.path}`,{waitUntil:'networkidle'});
    assert(response?.status()===200,`${route.name}-${name}: HTTP 200 fehlt`);
    const text=await page.locator('body').innerText();
    for(const marker of route.markers)assert(includes(text,marker),`${route.name}-${name}: Marker fehlt: ${marker}`);
    if(route.name==='startpartner-public')assert(!includes(text,'ohne Zahlungsart'),`${name}: alte Zahlungsart-Variante sichtbar`);
    assert(await page.locator('main.page--publish').count()===1,`${route.name}-${name}: gemeinsame Publish-Familie fehlt`);
    assert(await page.locator('.content-hero--panel').count()===1,`${route.name}-${name}: gemeinsamer Hero fehlt`);
    assert(await page.locator('.content-card').count()>0,`${route.name}-${name}: gemeinsame Card-Primitives fehlen`);
    assert(await page.locator('.content-cta--primary').count()>0,`${route.name}-${name}: gemeinsame Primäraktion fehlt`);

    if(route.name==='events-publish'){
      const pathLabels=await page.locator('.publish-model-list > li .publish-model-copy strong').allTextContents();
      assert(pathLabels.length===3,`${name}: drei primäre Veröffentlichungswege erwartet`);
      assert(pathLabels[0]?.trim()==='Einzelne Veranstaltung einreichen',`${name}: Einzeltermin ist nicht erster Veröffentlichungsweg`);
      assert(pathLabels[1]?.trim()==='Mitgliedschaft für regelmäßige Termine',`${name}: Mitgliedschaft ist nicht zweiter Veröffentlichungsweg`);
      assert(pathLabels[2]?.trim()==='Startpartner',`${name}: Startpartner ist nicht dritter primärer Veröffentlichungsweg`);
      assert(!pathLabels.some(label=>includes(label,'Automatische Übernahme')),`${name}: automatische Übernahme konkurriert weiter als primärer Veröffentlichungsweg`);
      assert(await page.locator('a.content-link[href="/events-veroeffentlichen/anbindung/"]').count()===1,`${name}: sekundärer Pfad zur automatischen Übernahme fehlt`);
      assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner-weg"]').count()===1,`${name}: Startpartner-Erklärlink zeigt nicht auf den sichtbaren Abschnitt`);
    }

    if(route.name==='startpartner-public'){
      assert(await page.locator('.content-kicker').count()===0,`${name}: Startpartner darf keinen unnötigen Kicker haben`);
      const startpartnerFormCard=await componentSignature(page,'.publish-start-card',['borderRadius','boxShadow'],`startpartner-public-${name}`);
      assertVisualMatch({publishStartCard:startpartnerFormCard},{publishStartCard:publishStartCardReference},`startpartner-public-${name}: etablierte Publish-Form-Card-DNA`);
      assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner-weg"]').count()===1,`${name}: Startpartner-Formular verweist nicht auf den sichtbaren Erklärabschnitt`);
      if(mode==='desktop'){
        const regularActions=page.locator('section[aria-labelledby="startpartner-regular-paths-title"] .publish-model-list > li .content-actions');
        assert(await regularActions.count()===2,`${name}: zwei reguläre Alternativen erwartet`);
        const boxes=await Promise.all([regularActions.nth(0).boundingBox(),regularActions.nth(1).boundingBox()]);
        assert(boxes[0]&&boxes[1]&&Math.abs(boxes[0].y-boxes[1].y)<=3,`${name}: reguläre Startpartner-Alternativen sind desktopseitig nicht sauber ausgerichtet`);
      }
    }

    const signature=await visualSignature(page);
    if(!reference)reference=signature;else assertVisualMatch(signature,reference,`${route.name}-${name}: visuelle Funnel-Konsistenz`);
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${route.name}-${name}: horizontaler Überlauf`);
    await page.screenshot({path:path.join(outDir,`${route.name}-${name}-fold.png`)});
  }

  if(anchorEvidenceProfiles.has(name)){
    await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner-weg"]').click();
    await page.waitForLoadState('networkidle');
    assert(page.url().includes('/veroeffentlichung-erklaert/#startpartner-weg'),`${name}: Startpartner-Erklärlink landet nicht am kanonischen Abschnitt`);
    const geometry=await page.evaluate(()=>{
      const header=document.querySelector('.app-header');
      const target=document.getElementById('startpartner-weg');
      const heading=document.getElementById('publish-explainer-startpartner-title');
      if(!target||!heading)return null;
      const headerBottom=header?.getBoundingClientRect().bottom||0;
      const targetBox=target.getBoundingClientRect();
      const headingBox=heading.getBoundingClientRect();
      return {headerBottom,targetTop:targetBox.top,headingTop:headingBox.top,headingBottom:headingBox.bottom,viewportHeight:window.innerHeight};
    });
    assert(geometry,`${name}: Startpartner-Sprungziel fehlt`);
    assert(geometry.headingTop>=geometry.headerBottom+8,`${name}: Startpartner-Überschrift wird nach Sprung vom Header verdeckt`);
    assert(geometry.headingBottom<geometry.viewportHeight,`${name}: Startpartner-Überschrift ist nach Sprung nicht im sichtbaren Viewport`);
    await page.screenshot({path:path.join(outDir,`startpartner-explainer-anchor-${name}.png`)});
  }
  await context.close();
}

const browser=await chromium.launch({headless:true});
try{
  for(const profile of responsiveProfiles)await operatorContract(browser,profile);
  for(const profile of responsiveProfiles)await partnerContract(browser,profile);
  for(const profile of responsiveProfiles)await publicFunnelContract(browser,profile);
}finally{
  await browser.close();
}
fs.writeFileSync(path.join(outDir,'startpartner-premium-summary.json'),JSON.stringify({
  status:'OK',
  responsiveContract:{
    mobilePreDesktop:'<900 CSS px',
    desktop:'>=900 CSS px',
    profiles:responsiveProfiles,
  },
  checks:[
    'operator-responsive-matrix-default-and-collapsed-history',
    'partner-real-dashboard-integrated-with-organizer-portal-and-pilot-renderer',
    'partner-single-current-context-without-generic-status-chain',
    'partner-secondary-details-and-edit-path',
    'partner-responsive-matrix-compact-form-open-and-close',
    'partner-form-one-column-below-900-and-two-columns-from-900',
    'partner-no-payment-boundary',
    'events-publish-startpartner-primary-hierarchy-before-secondary-automation',
    'startpartner-explainer-anchor-visible-below-header',
    'startpartner-regular-alternatives-desktop-alignment',
    'public-funnel-responsive-visual-consistency',
    'publish-start-card-shadow-matches-established-form-owner',
    'no-horizontal-overflow-across-established-widths',
  ],
},null,2)+'\n');
console.log('=== Startpartner Premium Finish Browser Contract: OK ===');