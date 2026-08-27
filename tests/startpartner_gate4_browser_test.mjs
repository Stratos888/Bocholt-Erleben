import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args=process.argv.slice(2);
const value=name=>{const index=args.indexOf(name);return index>=0?args[index+1]:'';};
const baseUrl=value('--base-url');
const outDir=value('--out-dir');
if(!baseUrl||!outDir){console.error('Usage: node tests/startpartner_gate4_browser_test.mjs --base-url URL --out-dir DIR');process.exit(2);}
fs.mkdirSync(outDir,{recursive:true});
const results=[];
function assert(condition,message){if(!condition)throw new Error(message);}
async function isRequired(locator){return await locator.getAttribute('required')!==null;}
function containsVisibleText(text,marker){return text.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE'));}

const firstId='24100000-0000-4000-8000-000000000010';
const secondId='24100000-0000-4000-8000-000000000011';
const distributionId='24100000-0000-4000-8000-000000000030';
const postActivation=new Set(['active','active_draft','active_ready','measurement_problem','distribution_due','distribution_blocked','paused','closing','end_due','checkpoint_due','terminal','event_limit_full','activity_limit_full']);

function controlNextActionForScenario(scenario){
  if(scenario==='measurement_problem')return {code:'measurement_problem',label:'Technische Erfolgsmessung prüfen',action:'measurement'};
  if(scenario==='distribution_due')return {code:'distribution_due',label:'Fälligen Reichweitenbeitrag dokumentieren',action:'set_distribution_fulfillment',distribution_id:distributionId};
  if(scenario==='distribution_blocked')return {code:'distribution_blocked',label:'Blockierten Reichweitenbeitrag klären',action:'set_distribution_fulfillment',distribution_id:distributionId};
  if(scenario==='active_draft')return {code:'content_review',label:'Nächsten Pilotinhalt redaktionell prüfen',action:'mark_content_ready',content_link_id:secondId};
  if(scenario==='active_ready')return {code:'content_approval',label:'Vorbereiteten Pilotinhalt freigeben',action:'approve_content',content_link_id:secondId};
  if(scenario==='paused')return {code:'paused',label:'Pilot fortsetzen oder Abschluss einleiten',action:'resume'};
  if(scenario==='closing')return {code:'end_without_conversion',label:'Pilot geordnet abschließen',action:'end_without_conversion'};
  if(scenario==='end_due')return {code:'closeout_required',label:'Pilotende jetzt entscheiden',action:'start_closeout'};
  if(scenario==='checkpoint_due')return {code:'checkpoint_due',label:'Fälligen Pilot-Checkpoint abschließen',action:'complete_checkpoint',checkpoint_key:'day_30',due_date_local:'2026-08-31'};
  if(scenario==='terminal')return {code:'none',label:'Pilot abgeschlossen',action:null};
  if(postActivation.has(scenario))return {code:'monitor_active_pilot',label:'Aktiven Pilot beobachten',action:null};
  return null;
}

function partnerNextActionForScenario(scenario){
  if(scenario==='terminal')return {code:'none',label:'Pilot abgeschlossen',content_type:null};
  if(scenario==='closing')return {code:'closeout',label:'Der Pilot befindet sich im Abschluss.',content_type:null};
  if(scenario==='paused')return {code:'paused',label:'Der Pilot ist aktuell pausiert.',content_type:null};
  if(scenario==='event_limit_full')return {code:'event_limit_full',label:'Event-Limit für diesen Pilotmonat erreicht.',content_type:null};
  if(scenario==='activity_limit_full')return {code:'activity_limit_full',label:'Die vereinbarte gleichzeitige Aktivitätspräsenz ist vollständig belegt.',content_type:null};
  if(postActivation.has(scenario))return {code:'submit_content',label:'Nächsten Termin einreichen',content_type:'event'};
  return null;
}

function gate4Candidate(scenario='access'){
  const keys=['terms_confirmed','organizer_linked','contact_confirmed','portal_access_tested','pilot_entitlement_readback','service_scope_confirmed','sources_recorded','maintenance_path_agreed','content_rights_cleared','first_content_ready','editorial_review_ready','measurement_ready','distribution_ready','activation_target_set'];
  const complete=new Set(['terms_confirmed','organizer_linked','contact_confirmed','pilot_entitlement_readback','service_scope_confirmed','sources_recorded','maintenance_path_agreed','content_rights_cleared','activation_target_set']);
  const afterPortal=scenario!=='access';
  if(afterPortal)complete.add('portal_access_tested');
  if(['measurement','distribution','ready'].includes(scenario)||postActivation.has(scenario))for(const key of ['first_content_ready','editorial_review_ready'])complete.add(key);
  if(['distribution','ready'].includes(scenario)||postActivation.has(scenario))complete.add('measurement_ready');
  if(scenario==='ready'||postActivation.has(scenario))complete.add('distribution_ready');
  const items=keys.map(key=>({item_key:key,status:complete.has(key)?'complete':'pending',is_required:1,is_hard_blocker:1,is_manual:0,evidence_text:complete.has(key)?`Systemnachweis ${key}`:null,revision:1}));

  const phase=scenario==='ready'?'activation_ready':scenario==='paused'?'paused':scenario==='closing'?'closing':scenario==='terminal'?'ended_without_conversion':postActivation.has(scenario)?'active':'onboarding';
  const activated=postActivation.has(scenario);
  const firstStatus=scenario==='content'?'draft':['measurement','distribution','ready'].includes(scenario)?'editorial_ready':activated?'approved':null;
  const content=[];
  if(firstStatus)content.push({id:firstId,submission_id:4241,content_type:'event',status:firstStatus,title:'Synthetischer Startpartner-Kulturtag',start_date:'2026-09-12'});
  if(scenario==='active_draft')content.push({id:secondId,submission_id:4242,content_type:'activity',status:'draft',title:'Synthetische Familienaktivität',start_date:null});
  if(scenario==='active_ready')content.push({id:secondId,submission_id:4243,content_type:'activity',status:'editorial_ready',title:'Synthetische Familienaktivität',start_date:null});
  const firstContent=content[0]||null;

  const blocker=scenario==='access'
    ?{code:'required_item_open',item_key:'portal_access_tested',message:'Der Partnerzugang wurde noch nicht genutzt.'}
    :scenario==='content'
      ?{code:'required_item_open',item_key:'first_content_ready',message:'Der erste Inhalt ist noch nicht für den Pilotstart vorbereitet.'}
      :scenario==='measurement'
        ?{code:'required_item_open',item_key:'measurement_ready',message:'Die technische Erfolgsmessung ist noch nicht geprüft.'}
        :scenario==='distribution'
          ?{code:'required_item_open',item_key:'distribution_ready',message:'Der Reichweitenbeitrag ist noch nicht mit dem Partner vereinbart.'}
          :null;

  const plannedEnd=scenario==='end_due'?'2026-08-26':activated?'2027-02-01':null;
  const pilot={id:'24100000-0000-4000-8000-000000000002',status:phase,revision:7,activation_date_local:activated?'2026-08-01':null,planned_end_date:plannedEnd};
  const readyMeasurement=['distribution','ready'].includes(scenario)||activated?{id:'24100000-0000-4000-8000-000000000020',metrics_owner:'value_metric_daily',checked_at:'2026-08-01 10:00:00'}:null;
  const commitment={id:distributionId,status:'ready',channel:'Newsletter',planned_at:'2026-08-20 12:00:00',target_reference:'https://example.org/reach',evidence_text:'Mit Partner vereinbart.'};
  const readyDistribution=scenario==='ready'||activated?commitment:null;
  const measurementRuntime=activated?{
    status:scenario==='measurement_problem'?'query_or_attribution_problem':'usage_observed',
    observed_actions:scenario==='measurement_problem'?0:3,
  }:{status:readyMeasurement?'no_data_yet_or_too_short':'technical_not_ready',observed_actions:0};
  const distributionRuntime=activated?{
    status:scenario==='distribution_due'?'due':scenario==='distribution_blocked'?'blocked':'planned',
    commitment,
  }:{status:readyDistribution?'planned':'not_planned',commitment:readyDistribution};
  const limits={
    event:{available:true,used:scenario==='event_limit_full'?8:1,limit:8,is_unlimited:false,full:scenario==='event_limit_full',reset_date_local:'2026-09-01'},
    activity:{available:true,used:scenario==='activity_limit_full'?1:0,limit:1,is_unlimited:false,full:scenario==='activity_limit_full'},
  };
  const checkpoints=[
    {checkpoint_key:'day_30',status:scenario==='checkpoint_due'?'due':'upcoming',due_date_local:'2026-08-31'},
    {checkpoint_key:'day_90',status:'upcoming',due_date_local:'2026-10-30'},
    {checkpoint_key:'month_5',status:'upcoming',due_date_local:'2027-01-01'},
    {checkpoint_key:'final',status:'upcoming',due_date_local:'2027-02-01'},
  ];
  const nextAction=controlNextActionForScenario(scenario);

  const scopes=[{scope_key:'events',status:activated?'active':'planned',limit_value:8,is_unlimited:false,period_unit:'pilot_month'},{scope_key:'activities',status:activated?'active':'planned',limit_value:1,is_unlimited:false,period_unit:'concurrent'}];
  const gate4={
    phase,complete:scenario==='ready'||activated,active:phase==='active',effective_active:phase==='active'&&scenario!=='end_due',activation_ready:scenario==='ready',pilot,scopes,
    onboarding:{ready:scenario==='ready'||activated,completed_count:items.filter(row=>row.status==='complete').length,total_count:14,items,blockers:blocker?[blocker]:[]},
    content_links:content,first_content:firstContent,ready_measurement:readyMeasurement,ready_distribution:readyDistribution,
    distribution_commitments:readyDistribution?[commitment]:[],measurement_runtime:measurementRuntime,distribution_runtime:distributionRuntime,
    lifecycle:{checkpoints,closeout_required:scenario==='end_due'},limits,next_action:nextAction,blockers:blocker?[blocker]:[],
    capacity:{occupied_slots:1,active_pilots:activated?1:0,active_reservations:activated?0:1,hard_stop_at:8,soft_stop_at:6},
  };
  return {
    id:'19900000-0000-0000-0000-000000009999',organization_name:'Gate-4-Kulturverein Bocholt',source:'targeted_outreach',desired_content_scope:'both',status:'accepted_pending_terms',revision:12,assigned_to:'M. Muster',next_review_at:'2026-08-04 10:00:00',website_url:'https://example.org/startpartner',description_text:'Lokaler Kulturverein mit Veranstaltungen und Aktivitäten.',contacts:[{contact_name:'Erika Beispiel',email:'erika@example.org',is_primary:true}],qualifications:[],readiness:{ready:true,blockers:[]},capacity:gate4.capacity,reservations:[],active_reservation:activated?null:{id:77,status:'active',ends_at:'2026-08-20 10:00:00'},waitlist:null,decision:{result:'accepted_pending_terms',reason:'Fachlich geeignet.'},events:[],
    gate3:{complete:true,blockers:[],terms_acceptance:{id:301,terms_version:'startpartner-pilot-2026-08-v2',terms_reference:'system://startpartner/pilot-terms/startpartner-pilot-2026-08-v2',terms_digest:'a'.repeat(64),accepted_at:'2026-08-25 12:00:00',confirmation_channel:'email_reply',no_automatic_paid_renewal:1},organizer:{id:401,organization_name:'Gate-4-Kulturverein Bocholt',email:'erika@example.org'},pilot,scopes,entitlement:{id:'23100000-0000-4000-8000-000000000002',status:activated?'active':'pending_activation'},events:[]},gate4,
  };
}

async function openControl(browser,scenario,viewport,name){
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
  await page.route('**/api/control-center/case.php*',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{id:'fixture-gate4',startpartner_candidate:gate4Candidate(scenario)}})}));
  await page.goto(`${baseUrl}/tests/fixtures/control_center_gate4_review.html?scenario=${scenario}`,{waitUntil:'networkidle'});
  await page.waitForSelector('html[data-fixture-ready="true"]');
  await page.screenshot({path:path.join(outDir,`${name}.png`),fullPage:true});
  return {page,context};
}

async function controlState(browser,scenario,viewport,name,markers,{visibleAction=null,visibleActionCount=0}={}){
  const {page,context}=await openControl(browser,scenario,viewport,name);
  const panel=page.locator('[data-gate4-panel]:visible');
  assert(await panel.count()===1,`${name}: Gate-4-Panel fehlt oder ist doppelt`);
  const text=await panel.innerText();
  for(const marker of markers)assert(containsVisibleText(text,marker),`${name}: Marker fehlt: ${marker}`);
  assert(!/\b(editorial_ready|pending_activation|activation_ready|onboarding)\b/.test(text),`${name}: technischer Rohstatus sichtbar`);
  assert(await panel.locator('[data-gate4-item="measurement_ready"] button').count()===0,`${name}: abgeleitete Messbereitschaft ist manuell überschreibbar`);
  assert(await panel.locator('[data-gate4-item="portal_access_tested"] button').count()===0,`${name}: v2-Portalnachweis ist noch manuell überschreibbar`);
  assert(await panel.locator('details[open]').count()===0,`${name}: Detailbereiche müssen initial eingeklappt sein`);
  const visibleButtons=panel.locator('button[data-review-action]:visible');
  assert(await visibleButtons.count()===visibleActionCount,`${name}: erwartet ${visibleActionCount} sichtbare nächste Aktion(en)`);
  if(visibleAction)assert(await panel.locator(`[data-review-action="${visibleAction}"]:visible`).count()===1,`${name}: erwartete nächste Aktion fehlt: ${visibleAction}`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  if(viewport.width<760){const box=await panel.boundingBox(),nav=await page.locator('.cc-nav').boundingBox();assert(box&&nav&&box.x>=0&&box.x+box.width<=viewport.width,`${name}: Panel liegt außerhalb des mobilen Viewports`);}
  await context.close();results.push({name,status:'OK'});
}

async function dialogContract(browser,scenario,action,markers,name){
  const {page,context}=await openControl(browser,scenario,{width:390,height:844},name);
  await page.locator(`[data-review-action="${action}"]:visible`).click();
  await page.waitForSelector('#cc-dialog[open] #gate4-confirm');
  const text=await page.locator('#cc-dialog').innerText();
  for(const marker of markers)assert(containsVisibleText(text,marker),`${name}: Dialogmarker fehlt: ${marker}`);
  await context.close();results.push({name,status:'OK'});
}

function portalPayload(submitted=false){
  return {organizer_id:401,gate4:{phase:'onboarding',active:false,activation_ready:false,pilot:{status:'onboarding',activation_date_local:null,planned_end_date:null},scopes:[{scope_key:'events',status:'planned',limit_value:8,is_unlimited:false,period_unit:'pilot_month'},{scope_key:'activities',status:'planned',limit_value:1,is_unlimited:false,period_unit:'concurrent'}],onboarding:{complete_count:9,total_count:14},content_links:submitted?[{submission_id:909,content_type:'activity',status:'draft',title:'Synthetische Familienaktivität',start_date:null,location_name:'Bocholt'}]:[]}};
}

async function organizerPortal(browser,viewport,name){
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
  let submitted=false;
  await page.route('**/api/organizer-portal/pilot.php',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:portalPayload(submitted)})}));
  await page.route('**/api/startpartner/content.php',async route=>{
    const body=JSON.parse(route.request().postData()||'{}');
    assert(body.content_type==='activity','organizer portal: falscher Inhaltstyp gesendet');
    assert(body.location_public_confirmed===true,'organizer portal: Ortsfreigabe fehlt');
    submitted=true;
    await route.fulfill({status:201,contentType:'application/json',body:JSON.stringify({status:'ok',data:{submission_id:909,idempotent_replay:false}})});
  });
  await page.goto(`${baseUrl}/tests/fixtures/organizer_gate4_portal.html`,{waitUntil:'networkidle'});
  await page.waitForSelector('#organizer-dashboard-pilot-card:not([hidden])');
  const card=page.locator('#organizer-dashboard-pilot-card');
  const initial=await card.innerText();
  for(const marker of ['Kostenloser Startpartner-Pilot','Pilot wird eingerichtet','Nächster Schritt','Als Nächstes: ersten Inhalt einreichen','Deine Inhalte','Pilotumfang und Laufzeit'])assert(containsVisibleText(initial,marker),`${name}: Portalmarker fehlt: ${marker}`);
  assert(!containsVisibleText(initial,'9 von 14'),`${name}: interne Gate-Zählung ist im Partnerportal sichtbar`);
  assert(!/\b(onboarding|draft|pending_activation)\b/.test(initial),`${name}: technischer Rohstatus sichtbar`);
  assert(await card.locator('.content-cta--primary:visible').count()===1,`${name}: erster Partnerzustand hat nicht genau eine prominente Aktion`);
  const form=page.locator('#organizer-pilot-content-form');
  assert(containsVisibleText(await form.innerText(),'Einreichung ist kostenlos'),`${name}: Kostenhinweis fehlt im sichtbaren Einreichungsformular`);
  await form.locator('[name="content_type"]').selectOption('activity');
  assert(!(await isRequired(form.locator('[name="start_date"]'))),`${name}: Aktivität verlangt ein Veranstaltungsdatum`);
  assert(await form.locator('[data-pilot-event-date]').isHidden(),`${name}: Datumsfeld für Aktivität sichtbar`);
  await form.locator('[name="title"]').fill('Synthetische Familienaktivität');
  await form.locator('[name="location_name"]').fill('Bocholt');
  await form.locator('[name="description_text"]').fill('Synthetischer Browsernachweis ohne Produktionswirkung.');
  await form.locator('[name="location_public_confirmed"]').check();
  await form.locator('button[type="submit"]').click();
  await page.waitForFunction(()=>document.querySelector('#organizer-dashboard-pilot-card')?.textContent.includes('Einreichung 909'));
  const refreshedText=await card.innerText();
  for(const marker of ['Dein erster Inhalt wird geprüft','Du musst aktuell nichts weiter tun','Synthetische Familienaktivität','Zur Prüfung eingereicht'])assert(containsVisibleText(refreshedText,marker),`${name}: Status nach Einreichung fehlt: ${marker}`);
  assert(await card.locator('.content-cta--primary:visible').count()===0,`${name}: Wartezustand zeigt eine falsche prominente Partneraktion`);
  assert(await card.locator('#organizer-pilot-content-form').count()===0,`${name}: Wartezustand bietet fälschlich eine weitere Einreichung an`);
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${name}: horizontaler Überlauf`);
  await page.screenshot({path:path.join(outDir,`${name}.png`),fullPage:true});
  await context.close();results.push({name,status:'OK'});
}

async function organizerLifecycleState(browser,scenario,markers,expectedPrimary,{verifyFollowUpForm=false}={}){
  const context=await browser.newContext({viewport:{width:390,height:844}});
  const page=await context.newPage();
  const gate4=gate4Candidate(scenario).gate4;
  gate4.next_action=partnerNextActionForScenario(scenario);
  await page.route('**/api/organizer-portal/pilot.php',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{organizer_id:401,gate4}})}));
  await page.goto(`${baseUrl}/tests/fixtures/organizer_gate4_portal.html`,{waitUntil:'networkidle'});
  await page.waitForSelector('#organizer-dashboard-pilot-card:not([hidden])');
  const card=page.locator('#organizer-dashboard-pilot-card'),text=await card.innerText();
  for(const marker of markers)assert(containsVisibleText(text,marker),`organizer-${scenario}: Marker fehlt: ${marker}`);
  assert(await card.locator('.content-cta--primary:visible').count()===expectedPrimary,`organizer-${scenario}: falsche Zahl prominenter Aktionen`);
  if(verifyFollowUpForm){
    const form=card.locator('#organizer-pilot-content-form');
    assert(await form.count()===1,`organizer-${scenario}: Formular für weiteren Inhalt fehlt`);
    const type=form.locator('[name="content_type"]');
    assert(await type.locator('option[value="event"]').count()===1,`organizer-${scenario}: Event ist im Both-Scope nicht verfügbar`);
    assert(await type.locator('option[value="activity"]').count()===1,`organizer-${scenario}: Aktivität ist im Both-Scope nicht verfügbar`);
    assert(await type.inputValue()==='event',`organizer-${scenario}: Event ist nicht der Standardtyp`);
    assert(await isRequired(form.locator('[name="start_date"]')),`organizer-${scenario}: Eventdatum ist nicht erforderlich`);
    assert(await form.locator('[data-pilot-event-date]').isVisible(),`organizer-${scenario}: Eventdatum ist nicht sichtbar`);
    await type.selectOption('activity');
    assert(!(await isRequired(form.locator('[name="start_date"]'))),`organizer-${scenario}: Aktivität verlangt ein Eventdatum`);
    assert(await form.locator('[data-pilot-event-date]').isHidden(),`organizer-${scenario}: Eventdatum bleibt bei Aktivität sichtbar`);
  }
  await page.screenshot({path:path.join(outDir,`gate4-organizer-${scenario}.png`),fullPage:true});
  await context.close();results.push({name:`gate4-organizer-${scenario}`,status:'OK'});
}

const browser=await chromium.launch({headless:true});
try{
  await controlState(browser,'access',{width:360,height:780},'gate4-mobile-access',['Piloteinrichtung','9 von 14 geprüft','Warten auf Partnerzugang','Erfolgsmessung','Reichweitenbeitrag'],{visibleActionCount:0});
  await controlState(browser,'content',{width:390,height:844},'gate4-mobile-content',['Piloteinrichtung','10 von 14 geprüft','Nächsten Inhalt redaktionell vorbereiten','Zur Prüfung eingereicht'],{visibleAction:`gate4:content-ready:${firstId}`,visibleActionCount:1});
  await controlState(browser,'measurement',{width:390,height:844},'gate4-mobile-measurement',['Piloteinrichtung','12 von 14 geprüft','Technische Erfolgsmessung prüfen','Technische Prüfung noch offen'],{visibleAction:'gate4:measurement',visibleActionCount:1});
  await controlState(browser,'distribution',{width:390,height:844},'gate4-mobile-distribution',['Piloteinrichtung','13 von 14 geprüft','Reichweitenbeitrag vereinbaren','Noch nicht vereinbart'],{visibleAction:'gate4:distribution',visibleActionCount:1});
  await controlState(browser,'ready',{width:390,height:844},'gate4-mobile-ready',['Bereit zum Start','14 von 14 geprüft','Pilot jetzt starten'],{visibleAction:'gate4:activate',visibleActionCount:1});
  await controlState(browser,'active',{width:1440,height:900},'gate4-desktop-active',['Pilotphase läuft','01.08.2026','01.02.2027','Nutzung vorhanden','Vereinbart, noch nicht fällig','Aktiven Pilot beobachten'],{visibleActionCount:0});
  await controlState(browser,'measurement_problem',{width:390,height:844},'gate4-mobile-measurement-problem',['Pilotphase läuft','Technische Zuordnung prüfen','Technische Erfolgsmessung prüfen'],{visibleAction:'gate4:measurement',visibleActionCount:1});
  await controlState(browser,'distribution_due',{width:390,height:844},'gate4-mobile-distribution-due',['Pilotphase läuft','Fällig','Fälligen Reichweitenbeitrag dokumentieren'],{visibleAction:`gate4:distribution-fulfillment:${distributionId}`,visibleActionCount:1});
  await controlState(browser,'active_draft',{width:390,height:844},'gate4-mobile-active-draft',['Pilotphase läuft','Nächsten Pilotinhalt redaktionell prüfen'],{visibleAction:`gate4:content-ready:${secondId}`,visibleActionCount:1});
  await controlState(browser,'active_ready',{width:390,height:844},'gate4-mobile-active-ready',['Pilotphase läuft','Vorbereiteten Pilotinhalt freigeben'],{visibleAction:`gate4:content-approve:${secondId}`,visibleActionCount:1});
  await controlState(browser,'paused',{width:390,height:844},'gate4-mobile-paused',['Pilot pausiert','Pilot fortsetzen oder Abschluss einleiten'],{visibleAction:'gate4:lifecycle:resume',visibleActionCount:1});
  await controlState(browser,'closing',{width:390,height:844},'gate4-mobile-closing',['Pilotabschluss','Pilot geordnet abschließen'],{visibleAction:'gate4:lifecycle:end_without_conversion',visibleActionCount:1});
  await controlState(browser,'end_due',{width:390,height:844},'gate4-mobile-end-due',['Pilotphase läuft','26.08.2026','Pilotende jetzt entscheiden'],{visibleAction:'gate4:lifecycle:start_closeout',visibleActionCount:1});
  await controlState(browser,'checkpoint_due',{width:390,height:844},'gate4-mobile-checkpoint-due',['Pilotphase läuft','Fälligen Pilot-Checkpoint abschließen'],{visibleAction:'gate4:checkpoint:day_30',visibleActionCount:1});
  await controlState(browser,'terminal',{width:390,height:844},'gate4-mobile-terminal',['Pilot beendet','Pilot abgeschlossen'],{visibleActionCount:0});
  await controlState(browser,'event_limit_full',{width:390,height:844},'gate4-mobile-event-limit',['Pilotphase läuft','8 / 8','Aktiven Pilot beobachten'],{visibleActionCount:0});
  await controlState(browser,'activity_limit_full',{width:390,height:844},'gate4-mobile-activity-limit',['Pilotphase läuft','1 / 1','Aktiven Pilot beobachten'],{visibleActionCount:0});

  await dialogContract(browser,'content',`gate4:content-ready:${firstId}`,['Inhalt für den Pilotstart vorbereiten','Noch nicht freigegeben','Pilot startet dadurch noch nicht','Redaktionell vorbereiten'],'gate4-content-ready-dialog');
  await dialogContract(browser,'measurement','gate4:measurement',['Technische Erfolgsmessung erneut prüfen','keine Testwerte erzeugt','Technische Prüfung erneut ausführen'],'gate4-measurement-dialog');
  await dialogContract(browser,'distribution','gate4:distribution',['Reichweitenbeitrag vereinbaren','noch kein Nachweis der späteren Erfüllung','Vereinbarter Kanal','Vereinbarter Zieltermin'],'gate4-distribution-dialog');
  await dialogContract(browser,'ready','gate4:activate',['Was beim Start passiert','keine Zahlung ausgelöst','Startdatum','Geplantes Ende'],'gate4-activation-dialog');
  await dialogContract(browser,'paused','gate4:lifecycle:resume',['Pilot fortsetzen','wirksame Laufzeit'],'gate4-resume-dialog');
  await dialogContract(browser,'checkpoint_due','gate4:checkpoint:day_30',['30-Tage-Checkpoint','keine Laufzeit verlängert','Checkpoint abschließen'],'gate4-checkpoint-dialog');
  await dialogContract(browser,'distribution_due',`gate4:distribution-fulfillment:${distributionId}`,['Reichweitenbeitrag klären','tatsächliche Erfüllung','Neuer Stand'],'gate4-distribution-fulfillment-dialog');

  await organizerPortal(browser,{width:390,height:844},'gate4-organizer-mobile');
  await organizerPortal(browser,{width:1440,height:900},'gate4-organizer-desktop');
  await organizerLifecycleState(browser,'active',['Pilotphase läuft','Nächsten Termin einreichen'],1,{verifyFollowUpForm:true});
  await organizerLifecycleState(browser,'event_limit_full',['Event-Limit erreicht'],0);
  await organizerLifecycleState(browser,'paused',['Pilot ist pausiert','keine neuen Pilotinhalte'],0);
  await organizerLifecycleState(browser,'closing',['Pilot wird abgeschlossen','Neue Pilotinhalte sind gesperrt'],0);
  await organizerLifecycleState(browser,'terminal',['Pilot abgeschlossen','keine weiteren Einreichungen'],0);
}finally{
  await browser.close();
}
fs.writeFileSync(path.join(outDir,'gate4-summary.json'),JSON.stringify({status:'OK',results},null,2)+'\n');
console.log('=== Startpartner Gate-4 Browser Contract: OK ===');
