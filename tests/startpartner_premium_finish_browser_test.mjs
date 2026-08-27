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
  return {organizer_id:401,gate4:{...candidate.gate4,next_action:{code:'submit_content',label:'Nächsten Termin einreichen',content_type:'event'}}};
}

async function operatorContract(browser,viewport,name){
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
  await page.screenshot({path:path.join(outDir,`${name}.png`),fullPage:true});

  await history.locator(':scope > summary').click();
  const historyText=await history.innerText();
  assert(includes(historyText,'Vor Pilotstart abgeschlossen'),`${name}: historische Einrichtung ist nicht als abgeschlossen bezeichnet`);
  assert(includes(historyText,'Abgeschlossen'),`${name}: Abschlussstatus der Historie fehlt`);
  for(const stale of ['Piloteinrichtung vorbereitet','Pilotstart ausstehend','Veröffentlichung noch nicht freigeschaltet','Platz reserviert · Bedingungen offen'])assert(!includes(historyText,stale),`${name}: historische Detailkopie bleibt zukunftsgerichtet: ${stale}`);
  await page.screenshot({path:path.join(outDir,`${name}-history-open.png`),fullPage:true});
  await context.close();
}

async function partnerContract(browser){
  const context=await browser.newContext({viewport:{width:390,height:844}});
  const page=await context.newPage();
  await page.route('**/api/organizer-portal/pilot.php',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:partnerProjection()})}));
  await page.goto(`${baseUrl}/tests/fixtures/organizer_gate4_portal.html`,{waitUntil:'networkidle'});
  await page.waitForSelector('#organizer-dashboard-pilot-card:not([hidden])');
  const card=page.locator('#organizer-dashboard-pilot-card');
  const text=await card.innerText();
  assert(includes(text,'Startpartner · 6 Monate kostenlos'),'partner-premium: kompakter Pilotkontext fehlt');
  assert(includes(text,'Pilotphase läuft'),'partner-premium: aktuelle Phase fehlt');
  assert(includes(text,'Nächste Veranstaltung einreichen'),'partner-premium: primärer nächster Schritt fehlt');
  assert(includes(text,'Deine Inhalte'),'partner-premium: Inhaltsbereich fehlt');
  assert(includes(text,'Pilotdetails'),'partner-premium: sekundäre Pilotdetails fehlen');
  for(const stale of ['Event-Limit','Pilotverbrauch','Pilotumfang und Laufzeit','Kostenlos zur Prüfung einreichen'])assert(!includes(text,stale),`partner-premium: interne/alte Sprache sichtbar: ${stale}`);
  assert(await card.locator('.organizer-status-badge').count()===0,'partner-premium: redundanter Status-Badge ist noch vorhanden');
  assert(await card.locator('.content-card .content-card').count()===0,'partner-premium: verschachtelte Kartenhierarchie im Pilotbereich');
  assert(await card.locator('.content-cta--primary:visible').count()===1,'partner-premium: genau eine prominente Aktion erwartet');
  const openButton=card.locator('#organizer-pilot-open-form');
  const shell=card.locator('#organizer-pilot-form-shell');
  const form=card.locator('#organizer-pilot-content-form');
  assert(await openButton.isVisible(),'partner-premium: kompakte Einreichungsaktion fehlt');
  assert(await shell.isHidden(),'partner-premium: vollständiges Formular ist initial sichtbar');
  assert(await form.isHidden(),'partner-premium: Formular muss initial eingeklappt sein');
  assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),'partner-premium: horizontaler Überlauf');
  await page.screenshot({path:path.join(outDir,'startpartner-premium-partner-mobile.png'),fullPage:true});

  await openButton.click();
  assert(await openButton.isHidden(),'partner-premium: Öffnen-Aktion bleibt nach Formularöffnung sichtbar');
  assert(await shell.isVisible(),'partner-premium: Formular öffnet nicht');
  assert(await card.locator('.content-cta--primary:visible').count()===1,'partner-premium: nach Formularöffnung mehr als eine Primäraktion sichtbar');
  assert(includes(await form.innerText(),'Zur Prüfung einreichen'),'partner-premium: kanonischer Einreichungs-CTA fehlt');
  assert(includes(await form.innerText(),'Titel der Veranstaltung'),'partner-premium: Event-Feldsprache ist nicht am regulären Funnel ausgerichtet');
  assert(includes(await form.innerText(),'Veranstaltungsort / Location'),'partner-premium: Event-Ortsfeld ist nicht konsistent');
  const type=form.locator('[name="content_type"]');
  await type.selectOption('activity');
  const activityText=await form.innerText();
  for(const marker of ['Name der Aktivität','Name des Standorts','Adresse / offizieller Treffpunkt','Website / Buchungslink','Kurzbeschreibung der Aktivität'])assert(includes(activityText,marker),`partner-premium: Activity-Feldsprache fehlt: ${marker}`);
  assert(await form.locator('[data-pilot-event-date]').isHidden(),'partner-premium: Eventdatum bleibt bei Aktivität sichtbar');
  await page.screenshot({path:path.join(outDir,'startpartner-premium-partner-mobile-form-open.png'),fullPage:true});
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
      card:read('.content-card',['borderRadius','boxShadow']),
      primary:read('.content-cta--primary',['borderRadius','minHeight','fontSize','fontWeight']),
    };
  });
}

function assertVisualMatch(actual,reference,label){
  for(const group of Object.keys(reference)){
    assert(actual[group]&&reference[group],`${label}: visuelle Gruppe fehlt: ${group}`);
    for(const [property,value] of Object.entries(reference[group])){
      assert(actual[group][property]===value,`${label}: ${group}.${property} weicht ab (${actual[group][property]} != ${value})`);
    }
  }
}

async function publicFunnelContract(browser){
  const context=await browser.newContext({viewport:{width:390,height:844}});
  const page=await context.newPage();
  const routes=[
    {name:'events-publish',path:'/events-veroeffentlichen/',markers:['Veranstaltung sichtbar machen','Wähle den passenden Veröffentlichungsweg']},
    {name:'activity-presence',path:'/aktivitaeten/sichtbar-werden/',markers:['Als Aktivität bei Bocholt erleben sichtbar werden','Wähle den passenden Tarif']},
    {name:'startpartner-public',path:'/startpartner/',markers:['Als Startpartner 6 Monate kostenlos testen','Veranstaltungen, Aktivitäten oder beides testen','ohne Zahlungsart','keine automatische kostenpflichtige Verlängerung','Wir prüfen, ob Startpartner zu deinem Angebot passt']},
  ];
  let reference=null;
  for(const route of routes){
    const response=await page.goto(`${baseUrl}${route.path}`,{waitUntil:'networkidle'});
    assert(response?.status()===200,`${route.name}: HTTP 200 fehlt`);
    const text=await page.locator('body').innerText();
    for(const marker of route.markers)assert(includes(text,marker),`${route.name}: Marker fehlt: ${marker}`);
    assert(await page.locator('main.page--publish').count()===1,`${route.name}: gemeinsame Publish-Familie fehlt`);
    assert(await page.locator('.content-hero--panel').count()===1,`${route.name}: gemeinsamer Hero fehlt`);
    assert(await page.locator('.content-card').count()>0,`${route.name}: gemeinsame Card-Primitives fehlen`);
    assert(await page.locator('.content-cta--primary').count()>0,`${route.name}: gemeinsame Primäraktion fehlt`);
    if(route.name==='startpartner-public')assert(await page.locator('.content-kicker').count()===0,'startpartner-public: unnötiger Kicker sichtbar');
    const signature=await visualSignature(page);
    if(!reference)reference=signature;else assertVisualMatch(signature,reference,`${route.name}: visuelle Funnel-Konsistenz`);
    assert(!(await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth)),`${route.name}: horizontaler Überlauf`);
    await page.screenshot({path:path.join(outDir,`${route.name}-mobile-fold.png`)});
  }
  await context.close();
}

const browser=await chromium.launch({headless:true});
try{
  await operatorContract(browser,{width:390,height:844},'startpartner-premium-operator-mobile');
  await operatorContract(browser,{width:1440,height:900},'startpartner-premium-operator-desktop');
  await partnerContract(browser);
  await publicFunnelContract(browser);
}finally{
  await browser.close();
}
fs.writeFileSync(path.join(outDir,'startpartner-premium-summary.json'),JSON.stringify({status:'OK',checks:['operator-mobile-default','operator-desktop-default','operator-history-open','partner-mobile-compact','partner-mobile-form-open','public-funnel-mobile-visual-consistency']},null,2)+'\n');
console.log('=== Startpartner Premium Finish Browser Contract: OK ===');