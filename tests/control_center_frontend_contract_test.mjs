import fs from 'node:fs';
const read = path => fs.readFileSync(path, 'utf8');
const html = read('steuerzentrale/index.html');
const loader = read('js/control-center.js');
const moduleNames = ['shared','app','review','review-render','review-actions','manage','manage-render','manage-actions','development'];
const moduleSources = Object.fromEntries(moduleNames.map(name => [name, read(`js/control-center/${name}.js`)]));
const modules = Object.values(moduleSources).join('\n');
const app = moduleSources.app;
const seo = read('js/control-center-seo-embed.js');
const style = read('css/style.css');
const css = read('css/control-center-editorial.css');
const presentation = read('api/control-center/_presentation.php');
const caseApi = read('api/control-center/case.php');
const backlogApi = read('api/growth-backlog/list.php');
const backlogLib = read('api/growth-backlog-lib.php');
const errors = [];
for (const asset of ['control-center-environment.js','control-center.js','control-center-seo-embed.js']) if (!html.includes(asset)) errors.push(`required script missing: ${asset}`);
for (const asset of ['control-center-source-editors.js','control-center-final-bridge.js','control-center-stability.js','control-center-publication.js','control-center-development.js','control-center-integrations.js']) if (html.includes(asset)) errors.push(`overlay controller still loaded: ${asset}`);
for (const marker of ['control-center/app.js','import(path)']) if (!loader.includes(marker)) errors.push(`module loader missing: ${marker}`);
for (const marker of ['edit_and_approve','decision_class','operation_id','Ablehnungsgrund auswählen','source_fingerprint','content_fingerprint','current_description_hash','data-manage-details','Live öffnen','Öffentliche Wirkung wird geprüft · Versuch','be_cc_draft:','Automatisierte Verbesserung','Promise.allSettled']) if (!modules.includes(marker) && !caseApi.includes(marker)) errors.push(`frontend contract missing: ${marker}`);
for (const marker of ['review_contract','be_cc_event_candidate_review_contract','edit_and_approve']) if (!presentation.includes(marker) && !caseApi.includes(marker)) errors.push(`presentation contract missing: ${marker}`);
for (const marker of ['/api/growth-backlog/list.php','Informations- und Reihenfolgeansicht','alle sichtbar','Offen · empfohlene Reihenfolge','Abgeschlossen','cc-roadmap-item']) if (!app.includes(marker)) errors.push(`roadmap frontend contract missing: ${marker}`);
for (const marker of ['gbl_read_snapshot','counts','completed']) if (!backlogApi.includes(marker) && !backlogLib.includes(marker)) errors.push(`roadmap source contract missing: ${marker}`);
for (const forbidden of ['data-backlog-action','cc-new-backlog','submitBacklog(','handleBacklog(','backlogCases()']) if (app.includes(forbidden)) errors.push(`roadmap must not behave like a work queue: ${forbidden}`);
if (!style.includes('control-center-editorial.css')) errors.push('editorial CSS is not in governance entrypoint');
for (const marker of ['cc-fact-grid','cc-gate--blocked','cc-dialog--wide','cc-history','cc-roadmap-summary','cc-roadmap-item--completed','.cc-dialog .cc-fact-grid','grid-template-columns:minmax(0,1fr)!important','overflow-x:hidden']) if (!css.includes(marker)) errors.push(`editorial CSS marker missing: ${marker}`);
if (modules.includes('new MutationObserver(')) errors.push('core modules must not patch their own UI through MutationObserver');
if (!modules.includes("appPath('/intern/seo-dashboard/?embed=1')")) errors.push('SEO embed path is not environment-aware');
if (!seo.includes('ResizeObserver') || !seo.includes('mapDocumentUrl')) errors.push('isolated SEO embed contract missing');
for (const [name, source] of Object.entries(moduleSources)) {
  if (name === 'shared') continue;
  for (const match of source.matchAll(/from\s+['"](\.\/[^'"]+\.js(?:\?[^'"]+)?)['"]/g)) {
    if (!match[1].includes('?v=2026-07-15-control-center-editorial-v1')) errors.push(`unversioned module import in ${name}: ${match[1]}`);
  }
}
if (errors.length) { console.error('=== Control Center Frontend Contract: FAILED ==='); for (const error of errors) console.error('-', error); process.exit(1); }
console.log('=== Control Center Frontend Contract: OK ===');
