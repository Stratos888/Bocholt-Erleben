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

function gate4Candidate(scenario='onboarding'){
  const keys=['terms_confirmed','organizer_linked','contact_confirmed','portal_access_tested','pilot_entitlement_readback','service_scope_confirmed','sources_recorded','maintenance_path_agreed','content_rights_cleared','first_content_ready','editorial_review_ready','measurement_ready','distribution_ready','activation_target_set'];
  const manual=new Set(['portal_access_tested','content_rights_cleared','activation_target_set']);
  const complete=scenario==='onboarding'?new Set(['terms_confirmed','organizer_linked','contact_confirmed','pilot_entitlement_readback','service_scope_confirmed','sources_recorded','maintenance_path_agreed','content_rights_cleared','activation_target_set']):new Set(keys);
  const items=keys.map(key=>({item_key:key,status:complete.has(key)?'complete':'pending',is_required:1,is_hard_blocker:1,is_manual:manual.has(key)?1:0,evidence_text:complete.has(key)?`Nachweis ${key}`:null,revision:1}));
  const contentStatus=scenario==='onboarding'?'draft':scenario==='active'?'approved':'editorial_ready';
  const pilot={id:'24100000-0000-4000-8000-000000000002',status:scenario==='active'?'active':scenario==='ready'?'activation_ready':'onboarding',revision:7,activation_date_local:scenario==='active'?'2026-08-01':null,planned_end_date:scenario==='active'?'2027-02-01':null};
  const gate4={phase:scenario==='active'?'active':scenario==='ready'?'activation_ready':'onboarding',complete:scenario==='active',active:scenario==='active',activation_ready:scenario==='ready',pilot,onboarding:{ready:scenario!=='onboarding',completed_count:items.filter(row=>row.status==='complete').length,total_count:14,items,blockers:[]},content_links:[{id:'24100000-0000-4000-8000-000000000010',submission_id:4241,content_type:'event',status:contentStatus,title:'Synthetischer Startpartner-Kulturtag',start_date:'2026-09-12'}],first_content:{id:'24100000-0000-4000-8000-000000000010',submission_id:4241,content_type:'event',status:contentStatus,title:'Synthetischer Startpartner-Kulturtag',start_date:'2026-09-12'},ready_measurement:scenario==='onboarding'?null:{id:'24100000-0000-4000-8000-000000000020',metrics_owner:'value_metric_daily',checked_at:'2026-08-01 10:00:00'},ready_distribution:scenario==='onboarding'?null:{id:'24100000-0000-4000-8000-000000000030',channel:'Newsletter',planned_at:'2026-08-08 22:00:00'},blockers:scenario==='onboarding'?[{message:'Der Veranstalterzugang ist noch nicht getestet.'}]:[],capacity:{occupied_slots:1,active_pilots:scenario==='active'?1:0,active_reservations:scenario==='active'?0:1,hard_stop_at:8,soft_stop_at:6}};
  return {id:'19900000-0000-0000-0000-000000009999',organization_name:'Gate-4-Kulturverein Bocholt',source:'targeted_outreach',desired_content_scope:'both',status:'accepted_pending_terms',revision:12,assigned_to:'M. Muster',next_review_at:'2026-08-04 10:00:00',website_url:'https://example.org/startpartner',description_text:'Lokaler Kulturverein mit Veranstaltungen und Aktivitäten.',contacts:[{contact_name:'Erika Beispiel',email:'erika@example.org',is_primary:true}],qualifications:[],readiness:{ready:true,assessed_count:14,total_count:14,blockers:[]},capacity:gate4.capacity,reservations:[],active_reservation:scenario==='active'?null:{id:77,status:'active',ends_at:'2026-08-20 10:00:00'},waitlist:null,decision:{result:'accepted_pending_terms',reason:'Fachlich geeignet.'},events:[],gate3:{complete:true,blockers:[],terms_acceptance:{id:301,terms_version:'pilot-terms-v1',accepted_at:'2026-07-27 20:00:00'},organizer:{id:401,organization_name:'Gate-4-Kulturverein Bocholt',email:'erika@example.org'},pilot,scopes:[{scope_key:'events',limit_value:8,is_unlimited:false,period_unit:'pilot_month'},{scope_key:'activities',limit_value:1,is_unlimited:false,period_unit:'concurrent'}],entitlement:{id:'23100000-0000-4000-8000-000000000002',status:scenario==='active'?'active':'pending_activation'},events:[]},gate4};
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

async function controlState(browser,scenario,viewport,name,markers){
  const {page,context}=await openControl(browser,scenario,viewport,name);
  const panel=page.locator('[data-gate4-panel]:visible');
  assert(await panel.count()===1,`${name}: Gate-4-Panel fehlt oder ist doppelt`);
  const text=await panel.innerText();
  for(const marker of markers)assert(containsVisibleText(text,marker),`${name}: Marker fehlt: ${marker}`);
  assert(!/\b(editorial_ready|pending_activation|activation_ready|onboarding)\b/.test(text),`${name}: technischer Rohstatus sichtbar`);
  assert(await panel.locator('[data-gate4-item="measurement_ready"] button').count()===0,`${name}: abgeleitete Messbereitschaft ist manuell überschreibbar`);
  assert(await panel.locator('[data-gate4-item="portal_access_tested"] button').count()===(scenario==='active'?0:2),`${name}: manueller Portalnachweis hat falsche Aktionen`);
  assert(await panel.locator('details[open]').count()===0,`${name}: Detailbereiche müssen initial eingeklappt sein`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  if(viewport.width<760){
    const box=await panel.boundingBox(); const nav=await page.locator('.cc-nav').boundingBox();
    assert(box&&nav&&box.x>=0&&box.x+box.width<=viewport.width,`${name}: Panel liegt außerhalb des mobilen Viewports`);
  }
  await context.close(); results.push({name,status:'OK'});
}

async function activationDialog(browser){
  const {page,context}=await openControl(browser,'ready',{width:390,height:844},'gate4-mobile-activation-dialog');
  await page.locator('[data-review-action="gate4:activate"]').click();
  await page.waitForSelector('#cc-dialog[open] #gate4-activation-date');
  const text=await page.locator('#cc-dialog').innerText();
  for(const marker of ['Was beim Start passiert','keine Zahlung ausgelöst','Startdatum','Geplantes Ende'])assert(containsVisibleText(text,marker),`activation dialog: Marker fehlt: ${marker}`);
  assert(await page.locator('#gate4-activation-date').inputValue()!=='','activation dialog: lokales Standarddatum fehlt');
  assert(await page.locator('#gate4-end-preview').innerText()!=='','activation dialog: Enddatumsvorschau fehlt');
  await context.close();results.push({name:'gate4-activation-dialog',status:'OK'});
}

async function manualDialog(browser){
  const {page,context}=await openControl(browser,'onboarding',{width:390,height:844},'gate4-mobile-manual-dialog');
  await page.locator('[data-gate4-panel] summary', {hasText:'Schritte der Piloteinrichtung'}).click();
  await page.locator('[data-review-action="gate4:item:portal_access_tested:complete"]').click();
  await page.waitForSelector('#cc-dialog[open] #gate4-evidence');
  const text=await page.locator('#cc-dialog').innerText();
  assert(containsVisibleText(text,'Veranstalterzugang getestet'),'manual dialog: fachlicher Titel fehlt');
  assert(containsVisibleText(text,'Hinterlege den Nachweis'),'manual dialog: Nachweisanforderung fehlt');
  assert(await isRequired(page.locator('#gate4-evidence')),'manual dialog: Nachweis ist nicht erforderlich');
  await context.close();results.push({name:'gate4-manual-dialog',status:'OK'});
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
  for(const marker of ['Kostenloser Startpartner-Pilot','Einrichtung läuft','9 von 14 Schritten erledigt'])assert(containsVisibleText(initial,marker),`${name}: Portalmarker fehlt: ${marker}`);
  assert(!/\b(onboarding|draft|pending_activation)\b/.test(initial),`${name}: technischer Rohstatus sichtbar`);
  await card.locator('summary', {hasText:'Neuen Inhalt einreichen'}).click();
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
  await card.locator('summary', {hasText:'Neuen Inhalt einreichen'}).click();
  const refreshed=page.locator('#organizer-pilot-content-form');
  assert(await refreshed.locator('[name="content_type"]').inputValue()==='event',`${name}: Formularreset stellt den Standardtyp nicht wieder her`);
  assert(await isRequired(refreshed.locator('[name="start_date"]')),`${name}: Datum ist nach Reset für Veranstaltung nicht erforderlich`);
  assert(await refreshed.locator('[data-pilot-event-date]').isVisible(),`${name}: Datum bleibt nach Reset verborgen`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  await page.screenshot({path:path.join(outDir,`${name}.png`),fullPage:true});
  await context.close();results.push({name,status:'OK'});
}

const browser=await chromium.launch({headless:true});
try{
  await controlState(browser,'onboarding',{width:360,height:780},'gate4-mobile-360x780-onboarding',['Piloteinrichtung','9 von 14 erledigt','Nächster offener Punkt','Der Veranstalterzugang ist noch nicht getestet.','Schritte der Piloteinrichtung','Inhalte im Pilot','Erfolgsmessung','Reichweitenbeitrag']);
  await controlState(browser,'ready',{width:390,height:844},'gate4-mobile-390x844-ready',['Bereit zum Start','14 von 14 erledigt','Pilot jetzt starten','Eingerichtet','Vorbereitet']);
  await controlState(browser,'active',{width:1440,height:900},'gate4-desktop-1440x900-active',['Pilotphase läuft','Sechsmonatige Pilotphase läuft','01.08.2026','01.02.2027','Veröffentlicht']);
  await activationDialog(browser);
  await manualDialog(browser);
  await organizerPortal(browser,{width:390,height:844},'gate4-organizer-mobile-390x844');
  await organizerPortal(browser,{width:1440,height:900},'gate4-organizer-desktop-1440x900');
}finally{
  await browser.close();
}
fs.writeFileSync(path.join(outDir,'gate4-summary.json'),JSON.stringify({status:'OK',results},null,2)+'\n');
console.log('=== Startpartner Gate-4 Browser Contract: OK ===');
