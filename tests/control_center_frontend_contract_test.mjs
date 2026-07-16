import fs from 'node:fs';
const read = path => fs.readFileSync(path, 'utf8');
const html = read('steuerzentrale/index.html');
const loader = read('js/control-center.js');
const moduleNames = ['shared','app','backlog','review','review-render','review-actions','manage','manage-render','manage-actions','development'];
const moduleSources = Object.fromEntries(moduleNames.map(name => [name, read(`js/control-center/${name}.js`)]));
const modules = Object.values(moduleSources).join('\n');
const app = moduleSources.app;
const backlog = moduleSources.backlog;
const review = moduleSources.review;
const reviewActions = moduleSources['review-actions'];
const seo = read('js/control-center-seo-embed.js');
const style = read('css/style.css');
const css = read('css/control-center-editorial.css');
const presentation = read('api/control-center/_presentation.php');
const caseApi = read('api/control-center/case.php');
const actionApi = read('api/control-center/action.php');
const directInboxWriteback = read('api/control-center/_inbox_decision_writeback.php');
const backlogApi = read('api/growth-backlog/list.php');
const backlogLib = read('api/growth-backlog-lib.php');
const backlogCreate = read('api/growth-backlog/create.php');
const backlogUpdate = read('api/growth-backlog/update.php');
const errors = [];
for (const asset of ['control-center-environment.js','control-center.js','control-center-seo-embed.js']) if (!html.includes(asset)) errors.push(`required script missing: ${asset}`);
for (const asset of ['control-center-source-editors.js','control-center-final-bridge.js','control-center-stability.js','control-center-publication.js','control-center-development.js','control-center-integrations.js']) if (html.includes(asset)) errors.push(`overlay controller still loaded: ${asset}`);
for (const marker of ['control-center/app.js','import(path)','2026-07-16-inbox-writeback-v3']) if (!loader.includes(marker)) errors.push(`module loader missing: ${marker}`);
for (const marker of ['edit_and_approve','decision_class','operation_id','Ablehnungsgrund auswählen','source_fingerprint','content_fingerprint','current_description_hash','data-manage-details','Live öffnen','Öffentliche Wirkung wird geprüft · Versuch','be_cc_draft:','Automatisierte Verbesserung','Promise.allSettled']) if (!modules.includes(marker) && !caseApi.includes(marker)) errors.push(`frontend contract missing: ${marker}`);
for (const marker of ['review_contract','be_cc_event_candidate_review_contract','edit_and_approve']) if (!presentation.includes(marker) && !caseApi.includes(marker)) errors.push(`presentation contract missing: ${marker}`);
for (const marker of ['/api/growth-backlog/list.php','configureBacklog','renderBacklog']) if (!app.includes(marker)) errors.push(`backlog module integration missing: ${marker}`);
for (const marker of ['Offen · empfohlene Reihenfolge','Abgeschlossen','<details class="cc-labor-row cc-roadmap-row','<summary>','cc-labor-detail','cc-backlog-new','data-backlog-edit','data-backlog-status','Details &amp; Bearbeiten','/api/growth-backlog/create.php','/api/growth-backlog/update.php']) if (!backlog.includes(marker)) errors.push(`backlog frontend contract missing: ${marker}`);
for (const marker of ['gbl_read_snapshot','counts','completed']) if (!backlogApi.includes(marker) && !backlogLib.includes(marker)) errors.push(`backlog source contract missing: ${marker}`);
for (const marker of ['why_relevant','recommended_action','expected_benefit','type']) if (!backlogCreate.includes(marker) || !backlogUpdate.includes(marker)) errors.push(`structured backlog writeback missing: ${marker}`);
for (const marker of ["['edit', 'complete', 'reopen']",'expected_updated_at',"'status' => 'open'","'status' => 'completed'"]) if (!backlogUpdate.includes(marker)) errors.push(`two-status backlog update contract missing: ${marker}`);
for (const forbidden of ['data-backlog-action','submitBacklog(','handleBacklog(','backlogCases()','<article class="cc-roadmap-item','cc-roadmap-summary','Informations- und Reihenfolgeansicht','Punkte insgesamt']) if (backlog.includes(forbidden)) errors.push(`backlog contains obsolete work-queue or header-block marker: ${forbidden}`);
for (const marker of ['2026-07-16-inbox-writeback-v3','review-actions.js?v=2026-07-16-inbox-writeback-v3']) if (!app.includes(marker) && !review.includes(marker)) errors.push(`current inbox action module missing: ${marker}`);
for (const marker of ['timeoutMs:90000','Ablehnung wird in der führenden Inbox gespeichert und anschließend geprüft','in der Inbox bestätigt']) if (!reviewActions.includes(marker)) errors.push(`verified inbox action feedback missing: ${marker}`);
for (const marker of ["_inbox_decision_writeback.php","be_cc_writeback_inbox_decision_direct"]) if (!actionApi.includes(marker)) errors.push(`direct inbox action routing missing: ${marker}`);
for (const marker of ['google_sheets_direct','be_cc_update_sheet_row_by_header','be_cc_verify_inbox_writeback']) if (!directInboxWriteback.includes(marker)) errors.push(`direct verified inbox writeback missing: ${marker}`);
if (!style.includes('control-center-editorial.css')) errors.push('editorial CSS is not in governance entrypoint');
for (const marker of ['cc-fact-grid','cc-gate--blocked','cc-dialog--wide','cc-history','cc-backlog-toolbar','cc-roadmap-actions','cc-roadmap-affordance','.cc-dialog .cc-fact-grid','grid-template-columns:minmax(0,1fr)!important','overflow-x:hidden']) if (!css.includes(marker)) errors.push(`editorial CSS marker missing: ${marker}`);
if (modules.includes('new MutationObserver(')) errors.push('core modules must not patch their own UI through MutationObserver');
if (!modules.includes("appPath('/intern/seo-dashboard/?embed=1')")) errors.push('SEO embed path is not environment-aware');
if (!seo.includes('ResizeObserver') || !seo.includes('mapDocumentUrl')) errors.push('isolated SEO embed contract missing');
for (const [name, source] of Object.entries(moduleSources)) {
  if (name === 'shared') continue;
  for (const match of source.matchAll(/from\s+['"](\.\/[^'"]+\.js(?:\?[^'"]+)?)['"]/g)) {
    if (!match[1].includes('?v=')) errors.push(`unversioned module import in ${name}: ${match[1]}`);
  }
}
if (errors.length) { console.error('=== Control Center Frontend Contract: FAILED ==='); for (const error of errors) console.error('-', error); process.exit(1); }
console.log('=== Control Center Frontend Contract: OK ===');
