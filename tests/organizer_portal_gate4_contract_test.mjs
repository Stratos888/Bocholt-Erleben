import fs from 'node:fs';

const read = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const html = read('fuer-veranstalter/dashboard/index.html');
const js = read('js/organizer-pilot.js');
const pilotApi = read('api/organizer-portal/pilot.php');
const projection = read('api/startpartner/_gate4_projection.php');
const contentApi = read('api/startpartner/content.php');
const failures = [];
const assert = (ok, message) => { if (!ok) failures.push(message); };

assert(html.includes('organizer-dashboard-pilot-card'), 'Dashboard needs a dedicated pilot card inside the existing portal.');
assert(html.includes('/js/organizer-pilot.js?v=2026-08-25-startpartner-journey-v2'), 'Dashboard must load the hardened pilot UI with a fresh cache key.');
assert(js.includes('/api/organizer-portal/pilot.php'), 'Portal must read the canonical pilot state.');
assert(js.includes('/api/startpartner/content.php'), 'Portal must prepare content through the Gate-4 submission integration.');
assert(js.includes('nicht automatisch kostenpflichtig verlängert'), 'Pilot UI must state the no-auto-renewal boundary.');
assert(js.includes('Nächster Schritt'), 'Partner portal must lead with one explicit next-step area.');
assert(js.includes('Als Nächstes: ersten Inhalt einreichen'), 'A partner without pilot content must get an explicit first-content action.');
assert(js.includes('Von dir ist aktuell nichts mehr nötig'), 'Partner portal must communicate a waiting state without exposing internal gates.');
assert(!js.includes('von ${Number(gate4.onboarding'), 'Partner UI must not expose the internal Gate-4 checklist count.');
assert(!js.includes('Schritten erledigt'), 'Partner UI must not present internal readiness as a fourteen-step journey.');
assert(js.includes('Deine Inhalte'), 'Pilot content must be a primary partner work area.');
assert(js.includes('Pilotumfang und Laufzeit'), 'Terms-relevant pilot details must remain available as secondary information.');
assert(js.includes('form.reset();\n        sync();'), 'Form reset must immediately restore event-date visibility and validation.');
assert(js.includes("await load(`Einreichung"), 'Successful submissions must refresh the canonical portal state.');
assert(js.includes('phaseBadge') && js.includes('contentStatus'), 'Portal must translate internal phase and content states.');
assert(!js.includes('create-billing-portal-session'), 'Pilot UI must not expose Stripe billing as the pilot contract.');

assert(pilotApi.includes('be_startpartner_gate4_portal_projection'), 'Portal API must use the minimized projection.');
assert(!pilotApi.includes("'candidate'=>$candidate") && !pilotApi.includes("'candidate' => $candidate"), 'Portal API must not expose the internal candidate object.');
assert(!pilotApi.includes('portal_session_id'), 'Portal API must not expose the internal session identifier.');
assert(!pilotApi.includes('error_message'), 'Portal API must not leak internal exception messages.');
assert(projection.includes('function be_startpartner_gate4_portal_projection'), 'Canonical projection owner must contain the portal projection.');
assert(projection.includes("'scopes' => $scopes") && projection.includes("'content_links' => $contentLinks"), 'Portal projection must expose only the required pilot UX data.');
const portalProjection = projection.slice(projection.indexOf('function be_startpartner_gate4_portal_projection'));
for (const forbidden of ['capacity', 'measurement_preflights', 'distribution_commitments', 'events', 'audit_json', 'evidence_json']) {
  assert(!portalProjection.includes(`'${forbidden}' =>`), `Portal projection must not expose internal ${forbidden}.`);
}
assert(contentApi.includes('be_startpartner_gate4_portal_session'), 'Content endpoint must authenticate before processing the submission.');
assert(contentApi.includes('Dein Veranstalterzugang ist nicht mehr gültig'), 'Expired sessions need a clear portal-facing 401 message.');
assert(!contentApi.includes("'error_message'"), 'Content endpoint must not leak internal exception messages.');

if (failures.length) {
  console.error('=== Organizer Portal Gate-4 Contract: FAILED ===');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log('=== Organizer Portal Gate-4 Contract: OK ===');
