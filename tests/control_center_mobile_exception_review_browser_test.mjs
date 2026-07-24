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
async function openScenario(browser,scenario,viewport,name){
  const context=await browser.newContext({viewport});
  const page=await context.newPage();
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
  assert(await page.locator('.cc-review-task-evidence--mobile[open]').count()===0,`${name}: Evidence muss initial eingeklappt sein`);
  assert(await page.locator('.cc-reviewed-summary--mobile[open]').count()===0,`${name}: Gesamtfassung muss mobil initial eingeklappt sein`);
  assert(await page.locator('.cc-mobile-case-options[open]').count()===0,`${name}: Nebenaktionen müssen initial eingeklappt sein`);
  const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>document.documentElement.clientWidth);
  assert(!overflow,`${name}: horizontaler Überlauf`);
  const summary=decision.locator('summary');
  await summary.focus(); await summary.press('Enter');
  assert(await decision.getAttribute('open')!==null,`${name}: Entscheidungsebene ist nicht per Tastatur bedienbar`);
  assert(await decision.locator('[data-review-task-resolution]:visible').count()===4,`${name}: vollständige Dublettenaktionen fehlen`);
  assert(await decision.locator('.cc-button--danger:visible').count()===1,`${name}: destruktive Aktion ist nicht getrennt gekennzeichnet`);
  await context.close(); results.push({name,status:'OK'});
}
async function shellContracts(browser){
  const noJsContext=await browser.newContext({viewport:{width:360,height:780},javaScriptEnabled:false});
  const noJs=await noJsContext.newPage();
  await noJs.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'domcontentloaded'});
  assert(await noJs.locator('#cc-auth:visible').count()===1,'JavaScript aus: private Zugangshülle fehlt');
  await noJs.screenshot({path:path.join(outDir,'mobile-360x780-javascript-disabled.png'),fullPage:true});
  await noJsContext.close(); results.push({name:'javascript-disabled-shell',status:'OK'});

  const moduleContext=await browser.newContext({viewport:{width:360,height:780}});
  const modulePage=await moduleContext.newPage();
  await modulePage.route('**/js/control-center/app.js*',route=>route.abort());
  await modulePage.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'domcontentloaded'});
  await modulePage.waitForFunction(()=>document.querySelector('#cc-status')?.textContent.includes('Steuerzentrale konnte nicht gestartet werden'));
  assert(await modulePage.locator('#cc-auth:visible').count()===1,'Modulfehler: Zugangshülle darf nicht verschwinden');
  await modulePage.screenshot({path:path.join(outDir,'mobile-360x780-module-failure.png'),fullPage:true});
  await moduleContext.close(); results.push({name:'module-failure-shell',status:'OK'});

  const apiContext=await browser.newContext({viewport:{width:360,height:780}});
  const apiPage=await apiContext.newPage();
  await apiPage.route('**/api/control-center/auth.php',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({message:'Synthetischer Ladefehler'})}));
  await apiPage.goto(`${baseUrl}/steuerzentrale/`,{waitUntil:'networkidle'});
  await apiPage.locator('#cc-password').fill('synthetic-only');
  await apiPage.locator('#cc-auth-form button[type="submit"]').click();
  await apiPage.waitForFunction(()=>document.querySelector('#cc-status')?.textContent.includes('Synthetischer Ladefehler'));
  assert(await apiPage.locator('#cc-auth:visible').count()===1,'API-Fehler: Zugangshülle darf nicht verschwinden');
  await apiPage.screenshot({path:path.join(outDir,'mobile-360x780-api-error.png'),fullPage:true});
  await apiContext.close(); results.push({name:'api-error-shell',status:'OK'});
}
const browser=await chromium.launch({headless:true});
try{
  await mobileDuplicate(browser,{width:360,height:780},'mobile-360x780-duplicate');
  await mobileDuplicate(browser,{width:390,height:844},'mobile-390x844-duplicate');
  const readyRun=await openScenario(browser,'ready',{width:360,height:780},'mobile-360x780-ready');
  assert(await readyRun.page.locator('.cc-mobile-priority [data-review-action="approve"]:visible').count()===1,'ready: Event übernehmen fehlt in der priorisierten Ebene');
  assert(await readyRun.page.locator('.cc-action-primary--event-candidate:visible').count()===0,'ready: doppelte mobile Hauptaktion sichtbar');
  await readyRun.context.close(); results.push({name:'mobile-ready',status:'OK'});
  const waitingRun=await openScenario(browser,'waiting',{width:360,height:780},'mobile-360x780-waiting');
  assert(await waitingRun.page.locator('.cc-mobile-decision--waiting:visible').count()===1,'waiting: passiver Wartezustand fehlt');
  assert(await waitingRun.page.locator('[data-review-action="approve"]:visible').count()===0,'waiting: irreführende Freigabe sichtbar');
  await waitingRun.context.close(); results.push({name:'mobile-waiting',status:'OK'});
  const failedRun=await openScenario(browser,'failed',{width:360,height:780},'mobile-360x780-failed-verification');
  assert((await failedRun.page.locator('.cc-review-task--failed_verification:visible').innerText()).includes('Prüfung fehlgeschlagen'),'failed_verification: Zustand fehlt');
  assert(await failedRun.page.locator('.cc-mobile-decision [data-review-task-resolution="retry_verification"]:visible').count()===1,'failed_verification: vorhandene Wiederholungsaktion fehlt');
  assert(await failedRun.page.locator('[data-review-action="approve"]:visible').count()===0,'failed_verification: irreführende Freigabe sichtbar');
  await failedRun.context.close(); results.push({name:'failed-verification',status:'OK'});
  const desktopRun=await openScenario(browser,'duplicate',{width:1440,height:900},'desktop-1440x900-duplicate');
  assert(await desktopRun.page.locator('.cc-mobile-priority:visible').count()===0,'desktop: mobile Prioritätsebene sichtbar');
  assert(await desktopRun.page.locator('.cc-gate:visible').count()===1,'desktop: bestehende Gate-Karte fehlt');
  assert(await desktopRun.page.locator('.cc-queue:visible').count()===1,'desktop: Queue fehlt');
  assert(await desktopRun.page.locator('.cc-review-task-actions--desktop [data-review-task-resolution]:visible').count()===4,'desktop: bestehende Aufgabenaktionen fehlen');
  assert(await desktopRun.page.locator('.cc-action-primary--event-candidate:visible').count()===1,'desktop: bestehende Fall-Hauptaktion fehlt');
  assert(await desktopRun.page.locator('.cc-actions--secondary-desktop:visible').count()===1,'desktop: bestehende Nebenaktionen fehlen');
  await desktopRun.context.close(); results.push({name:'desktop-contract',status:'OK'});
  await shellContracts(browser);
}finally{await browser.close();}
fs.writeFileSync(path.join(outDir,'summary.json'),JSON.stringify({status:'OK',results},null,2)+'\n');
console.log('=== Control Center Mobile Exception Review Browser Contract: OK ===');
