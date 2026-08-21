import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args=process.argv.slice(2);
const value=name=>{const index=args.indexOf(name);return index>=0?args[index+1]:'';};
const baseUrl=value('--base-url');
const outDir=value('--out-dir');
if(!baseUrl||!outDir){console.error('Usage: node tests/control_center_mobile_exception_review_browser_test.mjs --base-url URL --out-dir DIR');process.exit(2);}
fs.mkdirSync(outDir,{recursive:true});
const results=[];
function assert(condition,message){if(!condition)throw new Error(message);}
async function openScenario(browser,scenario,viewport,name,beforeGoto=null){
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
  if(beforeGoto)await beforeGoto(page);
  await page.goto(`${baseUrl}/tests/fixtures/control_center_mobile_exception_review.html?scenario=${scenario}`,{waitUntil:'networkidle'});
  await page.waitForSelector('html[data-fixture-ready="true"]');
  await page.screenshot({path:path.join(outDir,`${name}.png`),fullPage:true});
  return {page,context};
}
async function mobileDuplicate(browser,viewport,name){
  const {page,context}=await openScenario(browser,'duplicate',viewport,name);
  assert(await page.locator('.cc-pill:visible').count()===1,`${name}: genau ein sichtbarer Status erwartet`);
  assert(await page.locator('.cc-duplicate-comparison:visible').count()===1,`${name}: Vergleich fehlt`);
  const comparison=await page.locator('.cc-duplicate-comparison').innerText();
  for(const marker of ['Kandidat','Bestehendes Event','Sommerabend im TextilWerk','Sommerabend im Textilwerk','15.08.2026','TextilWerk','Match 96 %','Bestehendes Event öffnen'])assert(comparison.includes(marker),`${name}: Vergleichsmarker fehlt: ${marker}`);
  assert(!comparison.includes('20:00 Uhr'),`${name}: nicht gelieferte Bestandsuhrzeit wurde dargestellt`);
  const decision=page.locator('.cc-mobile-decision--choices:visible');
  assert(await decision.count()===1,`${name}: eine unmittelbare Entscheidungsebene erwartet`);
  const decisionBox=await decision.boundingBox(); const navBox=await page.locator('.cc-nav').boundingBox();
  assert(decisionBox&&navBox&&decisionBox.y+decisionBox.height<=navBox.y,`${name}: Entscheidung wird von unterer Navigation verdeckt oder liegt außerhalb des ersten Viewports`);
  assert(await page.locator('.cc-review-task-evidence--mobile[open]').count()===0,`${name}: Nachweis muss initial eingeklappt sein`);
  assert(await page.locator('.cc-reviewed-summary--mobile[open]').count()===0,`${name}: Gesamtfassung muss mobil initial eingeklappt sein`);
  assert(await page.locator('.cc-mobile-case-options[open]').count()===0,`${name}: Nebenaktionen müssen initial eingeklappt sein`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  const summary=decision.locator('summary');
  assert(await summary.getAttribute('aria-expanded')==='false',`${name}: geschlossene Entscheidungsebene meldet aria-expanded nicht korrekt`);
  await summary.focus(); await summary.press('Enter');
  assert(await decision.getAttribute('open')!==null,`${name}: Entscheidungsebene ist nicht per Tastatur bedienbar`);
  await page.waitForFunction(()=>document.querySelector('.cc-mobile-decision--choices > summary')?.getAttribute('aria-expanded')==='true');
  assert(await summary.getAttribute('aria-expanded')==='true',`${name}: geöffnete Entscheidungsebene meldet aria-expanded nicht korrekt`);
  assert(await decision.locator('[data-review-task-resolution]:visible').count()===4,`${name}: vollständige Dublettenaktionen fehlen`);
  assert(await decision.locator('.cc-button--danger:visible').count()===1,`${name}: destruktive Aktion ist nicht getrennt gekennzeichnet`);
  await context.close(); results.push({name,status:'OK'});
}
function latestCandidate(overrides={}){return {id:'19900000-0000-0000-0000-000000009999',organization_name:'GATE2_SYNTHETIC_199_Bocholt Kulturverein',source:'targeted_outreach',desired_content_scope:'both',status:'qualifying',revision:4,assigned_to:'M. Muster',next_review_at:null,website_url:'https://example.org/startpartner',description_text:'Lokaler Kulturverein mit regelmäßigem Event- und Aktivitätsangebot.',contacts:[],qualifications:[],readiness:{ready:false,assessed_count:0,total_count:14,blockers:[{dimension:'local_relevance',message:'Bewertung fehlt.'}]},capacity:{active_reservations:2,hard_stop_at:8,soft_stop:false,hard_stop:false},reservations:[],active_reservation:null,waitlist:null,decision:null,gate3:{complete:false,blockers:[]},events:[],...overrides};}
async function aiReviewContract(browser,scenario,viewport,name,hardStop=false){
  const {page,context}=await openScenario(browser,scenario,viewport,name);
  const review=page.locator('.cc-startpartner-ai-review:visible');
  assert(await review.count()===1,`${name}: KI-Prüfebene fehlt`);
  const text=await page.locator('.cc-startpartner-review').innerText();
  const normalizedText=text.toLocaleLowerCase('de-DE');
  for(const marker of ['Prüfung offen','Prüfprompt kopieren','Rückfrage nötig','Nicht geeignet','Kapazität'])assert(normalizedText.includes(marker.toLocaleLowerCase('de-DE')),`${name}: Marker fehlt: ${marker}`);
  assert(!text.includes('Alle 6 Kriterien')&&!text.includes('Eignungscheck speichern')&&!text.includes('Qualifizierung bearbeiten'),`${name}: alter manueller Eignungscheck ist noch sichtbar`);
  const positive=hardStop?'Auf Warteliste':'Startpartner aufnehmen';
  assert((await review.locator('button[data-review-action]').allTextContents()).includes(positive),`${name}: positive Entscheidung fehlt: ${positive}`);
  if(hardStop)assert(!(await review.innerText()).includes('Startpartner aufnehmen'),`${name}: Aufnahme darf am Hard Stop nicht angeboten werden`);
  assert(await page.locator('.cc-startpartner-primary:visible').count()===0,`${name}: alte Server-Hauptaktion darf im KI-Prüfzustand nicht sichtbar sein`);
  assert(await page.locator('.cc-mobile-case-options[open]').count()===0,`${name}: Nebenoptionen müssen initial eingeklappt sein`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);assert(!overflow,`${name}: horizontaler Überlauf`);
  await context.close();results.push({name,status:'OK'});
}
async function aiDecisionDialogs(browser){
  const candidate=latestCandidate();
  const {page,context}=await openScenario(browser,'startpartner-blocked',{width:390,height:844},'startpartner-ai-decision-dialogs',async current=>{
    await current.route('**/api/control-center/case.php*',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{id:'fixture-startpartner',startpartner_candidate:candidate}})}));
  });
  await page.locator('[data-review-action="review_approve"]').click();
  await page.waitForSelector('#cc-dialog[open] #sp-review-confirm');
  let text=await page.locator('#cc-dialog').innerText();
  assert(text.includes('Startpartner aufnehmen')&&text.includes('20 Tage')&&text.includes('noch kein Pilot'), 'approve dialog: Sicherheitskontext fehlt');
  assert(await page.locator('#cc-dialog textarea').count()===1,'approve dialog: nur optionale Notiz erwartet');
  await page.locator('#cc-dialog-close').click();
  await page.locator('[data-review-action="review_needs_information"]').click();
  await page.waitForSelector('#cc-dialog[open] #sp-review-confirm');
  text=await page.locator('#cc-dialog').innerText();assert(text.includes('Welche Information fehlt'), 'needs-information dialog: Pflichtfrage fehlt');
  await page.locator('#cc-dialog-close').click();
  await page.locator('[data-review-action="review_reject"]').click();
  await page.waitForSelector('#cc-dialog[open] #sp-review-confirm');
  assert((await page.locator('#cc-dialog').innerText()).includes('Begründung'),'reject dialog: Begründung fehlt');
  assert(await page.locator('#sp-review-confirm.cc-button--danger').count()===1,'reject dialog: destruktive Aktion ist nicht getrennt');
  await context.close();results.push({name:'startpartner-ai-decision-dialogs',status:'OK'});
}
async function aiDecisionMutation(browser,conflict){
  const name=conflict?'startpartner-ai-stale-conflict':'startpartner-ai-successful-readback';const candidate=latestCandidate();
  const {page,context}=await openScenario(browser,'startpartner-blocked',{width:390,height:844},name,async current=>{
    await current.route('**/api/control-center/case.php*',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{id:'fixture-startpartner',startpartner_candidate:candidate}})}));
    await current.route('**/api/startpartner/review-decision.php',route=>route.fulfill(conflict?{status:409,contentType:'application/json',body:JSON.stringify({status:'error',code:'STARTPARTNER_CONFLICT',message:'Zwischenzeitlich geändert.',current:{revision:5}})}:{status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{candidate:{...candidate,status:'accepted_pending_terms',revision:5},operation_id:'gate2:299:review:fixture',idempotent_replay:false}})}));
  });
  await page.locator('[data-review-action="review_approve"]').click();await page.locator('#sp-review-confirm').click();
  await page.waitForFunction(()=>window.__fixtureReloads===1);
  if(conflict){assert((await page.locator('#cc-dialog-message').innerText()).includes('Zwischenzeitlich geändert'),'AI stale conflict: klare Konfliktmeldung fehlt');assert(await page.locator('#cc-dialog[open]').count()===1,'AI stale conflict: Dialog muss offen bleiben');}
  else{assert(await page.locator('#cc-dialog[open]').count()===0,'AI successful readback: Dialog wurde nicht geschlossen');assert((await page.locator('#cc-status').innerText()).includes('Entscheidung gespeichert'),'AI successful readback: Rückmeldung fehlt');}
  await context.close();results.push({name,status:'OK'});
}
async function laterStartpartnerState(browser,scenario,viewport,name,markers,primaryLabel){
  const {page,context}=await openScenario(browser,scenario,viewport,name);const priority=page.locator('.cc-startpartner-priority:visible');assert(await priority.count()===1,`${name}: priorisierte Startpartner-Ebene fehlt`);const text=await page.locator('.cc-startpartner-review').innerText();for(const marker of markers)assert(text.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE')),`${name}: Marker fehlt: ${marker}`);assert((await priority.locator('.cc-startpartner-primary').innerText())===primaryLabel,`${name}: falsche Hauptaktion`);assert(await page.locator('.cc-startpartner-evidence[open]').count()===0,`${name}: Nachweise müssen initial eingeklappt sein`);const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);assert(!overflow,`${name}: horizontaler Überlauf`);await context.close();results.push({name,status:'OK'});
}
async function gate3DialogContract(browser){
  const reservedCandidate=latestCandidate({status:'accepted_pending_terms',revision:4,contacts:[{contact_name:'Synthetischer Gate-3-Kontakt',email:'gate3@example.org',is_primary:true}],readiness:{ready:true,assessed_count:14,total_count:14,blockers:[]},capacity:{active_reservations:4,hard_stop_at:8,soft_stop:false,hard_stop:false},active_reservation:{id:231,ends_at:'2026-08-20 12:00:00',status:'active'},decision:{result:'accepted_pending_terms',reason:'Synthetisch reserviert.'},gate3:{complete:false,blockers:[{message:'Pilotbedingungen müssen ausdrücklich bestätigt werden.'}]}});
  const {page,context}=await openScenario(browser,'startpartner-reserved',{width:390,height:844},'startpartner-gate3-dialog',async current=>{await current.route('**/api/control-center/case.php*',route=>route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({status:'ok',data:{id:'fixture-startpartner',startpartner_candidate:reservedCandidate}})}));});
  await page.locator('.cc-startpartner-primary').click();await page.waitForSelector('#cc-dialog[open] #sp-terms-version');
  for(const marker of ['Version der Pilotbedingungen','Referenz der bestätigten Fassung','SHA-256','Bestätigende Person','Bestätigungskanal','Pilotgruppe','Keine automatische kostenpflichtige Verlängerung'])assert((await page.locator('#cc-dialog').innerText()).includes(marker),`gate3 dialog: Feld fehlt: ${marker}`);
  assert(await page.locator('#sp-no-auto-renewal').isChecked()===false,'gate3 dialog: automatische Verlängerung darf nicht vorselektiert sein');await context.close();results.push({name:'startpartner-gate3-dialog',status:'OK'});
}
async function shellContracts(browser){
  const noJsContext=await browser.newContext({viewport:{width:360,height:780},javaScriptEnabled:false});const noJs=await noJsContext.newPage();await noJs.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'domcontentloaded'});assert(await noJs.locator('#cc-auth:visible').count()===1,'JavaScript aus: private Zugangshülle fehlt');await noJs.screenshot({path:path.join(outDir,'mobile-360x780-javascript-disabled.png'),fullPage:true});await noJsContext.close();results.push({name:'javascript-disabled-shell',status:'OK'});
  const moduleContext=await browser.newContext({viewport:{width:360,height:780}});const modulePage=await moduleContext.newPage();await modulePage.route('**/js/control-center/app.js*',route=>route.abort());await modulePage.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'domcontentloaded'});await modulePage.waitForFunction(()=>document.querySelector('#cc-status')?.textContent.includes('Steuerzentrale konnte nicht gestartet werden'));assert(await modulePage.locator('#cc-auth:visible').count()===1,'Modulfehler: Zugangshülle darf nicht verschwinden');await moduleContext.close();results.push({name:'module-failure-shell',status:'OK'});
  const apiContext=await browser.newContext({viewport:{width:360,height:780}});const apiPage=await apiContext.newPage();await apiPage.route('**/api/control-center/auth.php',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({message:'Synthetischer Ladefehler'})}));await apiPage.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'networkidle'});await apiPage.locator('#cc-password').fill('synthetic-only');await apiPage.locator('#cc-auth-form button[type="submit"]').click();await apiPage.waitForFunction(()=>document.querySelector('#cc-status')?.textContent.includes('Synthetischer Ladefehler'));assert(await apiPage.locator('#cc-auth:visible').count()===1,'API-Fehler: Zugangshülle darf nicht verschwinden');await apiContext.close();results.push({name:'api-error-shell',status:'OK'});
}
const browser=await chromium.launch({headless:true});
try{
  await mobileDuplicate(browser,{width:360,height:780},'mobile-360x780-duplicate');await mobileDuplicate(browser,{width:390,height:844},'mobile-390x844-duplicate');
  const readyRun=await openScenario(browser,'ready',{width:360,height:780},'mobile-360x780-ready');assert(await readyRun.page.locator('.cc-mobile-priority [data-review-action="approve"]:visible').count()===1,'ready: Event übernehmen fehlt');await readyRun.context.close();results.push({name:'mobile-ready',status:'OK'});
  const waitingRun=await openScenario(browser,'waiting',{width:360,height:780},'mobile-360x780-waiting');assert(await waitingRun.page.locator('.cc-mobile-decision--waiting:visible').count()===1,'waiting: passiver Wartezustand fehlt');await waitingRun.context.close();results.push({name:'mobile-waiting',status:'OK'});
  const failedRun=await openScenario(browser,'failed',{width:360,height:780},'mobile-360x780-failed-verification');assert(await failedRun.page.locator('.cc-mobile-decision [data-review-task-resolution="retry_verification"]:visible').count()===1,'failed_verification: Wiederholungsaktion fehlt');await failedRun.context.close();results.push({name:'failed-verification',status:'OK'});
  const desktopRun=await openScenario(browser,'duplicate',{width:1440,height:900},'desktop-1440x900-duplicate');assert(await desktopRun.page.locator('.cc-gate:visible').count()===1,'desktop: bestehende Gate-Karte fehlt');await desktopRun.context.close();results.push({name:'desktop-contract',status:'OK'});
  await aiReviewContract(browser,'startpartner-blocked',{width:360,height:780},'startpartner-mobile-360x780-ai-review',false);
  await aiReviewContract(browser,'startpartner-ready',{width:390,height:844},'startpartner-mobile-390x844-ai-ready',false);
  await aiReviewContract(browser,'startpartner-hard',{width:360,height:780},'startpartner-mobile-360x780-ai-hard-stop',true);
  await aiDecisionDialogs(browser);await aiDecisionMutation(browser,false);await aiDecisionMutation(browser,true);
  await laterStartpartnerState(browser,'startpartner-reserved',{width:1440,height:900},'startpartner-desktop-1440x900-reserved',['Platz reserviert','Aktive Reservierung','Pilotbedingungen müssen ausdrücklich bestätigt'],'Bedingungen bestätigen und Pilot anlegen');
  await laterStartpartnerState(browser,'startpartner-gate3-complete',{width:390,height:844},'startpartner-mobile-390x844-gate3-complete',['Piloteinrichtung','Pilotstart ausstehend','Noch nicht aktiv'],'Pilotstatus prüfen');
  await gate3DialogContract(browser);await shellContracts(browser);
}finally{await browser.close();}
fs.writeFileSync(path.join(outDir,'summary.json'),JSON.stringify({status:'OK',results},null,2)+'\n');
console.log('=== Control Center Mobile Exception Review and Startpartner Browser Contract: OK ===');