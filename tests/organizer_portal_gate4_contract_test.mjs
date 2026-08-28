import fs from 'node:fs';

const read = path => fs.readFileSync(new URL(`../${path}`, import.meta.url), 'utf8');
const html = read('fuer-veranstalter/dashboard/index.html');
const fixture = read('tests/fixtures/organizer_gate4_portal.html');
const js = read('js/organizer-pilot.js');
const organizerPortal = read('js/organizer-portal.js');
const pilotApi = read('api/organizer-portal/pilot.php');
const projection = read('api/startpartner/_gate4_projection.php');
const contentApi = read('api/startpartner/content.php');
const portalDomain = read('api/startpartner/_gate4_portal_domain.php');
const failures = [];
const assert = (ok, message) => { if (!ok) failures.push(message); };

assert(html.includes('organizer-dashboard-pilot-card'), 'Dashboard needs a dedicated pilot card inside the existing portal.');
assert(html.includes('/js/organizer-pilot.js?v=2026-08-27-startpartner-premium-finish-v1'), 'Dashboard must load the premium pilot UI from the established asset owner.');
assert(html.includes('/js/organizer-portal.js?v=2026-08-28-phase-a-single-status-repair-v1'), 'Dashboard must cache-bust the corrected organizer portal renderer.');
assert(fixture.includes('<main class="page page--organizers" data-organizer-page="dashboard">'), 'Synthetic partner evidence must use the real organizer dashboard page shell.');
assert(fixture.includes('class="content-card content-card--primary" id="organizer-dashboard-pilot-card"'), 'Synthetic partner evidence must use the real dashboard pilot-card primitives.');
assert(!fixture.includes('class="content-shell"') && !fixture.includes('class="content-section"') && !fixture.includes('class="organizer-dashboard-card"'), 'Synthetic partner evidence must not reintroduce a parallel simplified dashboard layout.');
assert(js.includes('/api/organizer-portal/pilot.php'), 'Portal must read the canonical pilot state.');
assert(js.includes('/api/startpartner/content.php'), 'Portal must prepare content through the Gate-4 submission integration.');
assert(js.includes('keine automatische kostenpflichtige Verlängerung'), 'Pilot UI must state the no-auto-renewal boundary in the canonical public wording.');
assert(js.includes('Nächster Schritt'), 'Partner portal must lead with one explicit next-step area.');
assert(js.includes("if (!first)"), 'Partner portal must preserve a dedicated first-content state.');
assert(js.includes("title: copy.title.replace('Nächste', 'Erste').replace('Weiteren', 'Ersten')") && js.includes('Reiche den ersten Inhalt ein.'), 'A partner without pilot content must get an explicit first-content action.');
assert(js.includes("if (first.status === 'draft')") && js.includes('Deine Einreichung ist angekommen und wird redaktionell geprüft.') && js.includes("action: 'wait'"), 'Pre-activation submission must remain a waiting state.');
assert(js.includes('Pilot ist pausiert') && js.includes('Pilot wird abgeschlossen'), 'Partner portal must translate pause and closing states.');
assert(js.includes("projected.code === 'event_limit_full'") && js.includes('Die vereinbarten Veranstaltungen für diesen Monat sind bereits ausgeschöpft.'), 'Partner portal must explain the fail-closed event limit without technical state language.');
assert(js.includes("projected.code === 'activity_limit_full'") && js.includes('Der vereinbarte Aktivitätsplatz ist belegt.'), 'Partner portal must explain the fail-closed activity limit without technical state language.');
assert(js.includes("projected.code === 'submit_content'"), 'Active partner CTA must come from the canonical next-action projection.');
assert(!js.includes('Weiteren Inhalt einreichen</summary>'), 'Non-action states must not retain a hidden secondary submission path.');
assert(js.includes('pendingClientReference') && js.includes('if (!pendingClientReference) pendingClientReference = newClientReference()'), 'Ambiguous partner retries must keep one stable client_reference.');
assert(js.includes("pendingClientReference = '';"), 'A confirmed successful submit must release the retry reference.');
assert(js.includes("error?.status === 409"), 'Payload-bound replay conflicts need a clear portal-facing conflict path.');
assert(!js.includes('von ${Number(gate4.onboarding'), 'Partner UI must not expose the internal Gate-4 checklist count.');
assert(!js.includes('Schritten erledigt'), 'Partner UI must not present internal readiness as a fourteen-step journey.');
assert(js.includes('Deine Inhalte'), 'Pilot content must be a primary partner work area.');
assert(js.includes('<summary>Pilotdetails</summary>') && js.includes('Vereinbarter Rahmen') && js.includes('Pilotstart') && js.includes('Geplantes Ende'), 'Terms-relevant pilot scope and lifetime must remain available as secondary Pilotdetails.');
assert(js.includes('form.reset(); sync();'), 'Form reset must immediately restore event-date visibility and validation.');
assert(js.includes('await load(`Einreichung'), 'Successful submissions must refresh the canonical portal state.');
assert(js.includes('contentStatus'), 'Portal must translate internal content states.');
assert(!js.includes('phaseBadge') && !js.includes('organizer-status-badge'), 'Partner portal must not repeat the phase with a redundant status badge.');
assert(!js.includes('Event-Limit erreicht') && !js.includes('Pilotverbrauch') && !js.includes('Pilotumfang und Laufzeit'), 'Partner portal must not regress to old technical or status-document wording.');
assert(!js.includes('create-billing-portal-session'), 'Pilot UI must not expose Stripe billing as the pilot contract.');

assert(organizerPortal.includes('? formatSubmissionStatusLabel(latestSubmission)'), 'Single-status hero must use submission-kind-aware status labels.');
assert(organizerPortal.includes('isSingleStatusView && isLatestSubmissionActivity'), 'Single-status CTA must branch on an Activity submission.');
assert(organizerPortal.includes('label: "Weitere Aktivität einreichen"'), 'Single-status Activity CTA must remain Activity-specific.');
assert(organizerPortal.includes('${isLatestSubmissionActivity ? "Einreichung" : "Termin"}: ${latestDateText}'), 'Single-status metadata must not label an Activity as a Termin.');
assert(organizerPortal.includes('? formatSubmissionStatusDescription(latestSubmission)'), 'Single-status status copy must derive from the canonical submission status description.');
assert(!organizerPortal.includes('? "Einreichung erhalten. Die Veröffentlichung erfolgt nach Prüfung."'), 'Single-status published content must not be forced back into a pending-review message.');
assert(organizerPortal.includes('["Bereich", latestSubmission ? formatSubmissionKindLabel(latestSubmission) : "–"]'), 'Single-status overview must show the actual submission kind instead of hard-coded Einzeltermin.');

assert(pilotApi.includes('be_startpartner_gate4_portal_projection'), 'Portal API must use the minimized projection.');
assert(!pilotApi.includes("'candidate'=>$candidate") && !pilotApi.includes("'candidate' => $candidate"), 'Portal API must not expose the internal candidate object.');
assert(!pilotApi.includes('portal_session_id'), 'Portal API must not expose the internal session identifier.');
assert(!pilotApi.includes('error_message'), 'Portal API must not leak internal exception messages.');
assert(projection.includes('function be_startpartner_gate4_portal_projection'), 'Canonical projection owner must contain the portal projection.');
assert(projection.includes("'limits' => $safeLimits") && projection.includes("'measurement' => $safeMeasurement") && projection.includes("'distribution' => $safeDistribution"), 'Portal lifecycle fields must use minimized projections.');
const portalProjection = projection.slice(projection.indexOf('function be_startpartner_gate4_portal_projection'));
for (const forbidden of ['capacity', 'measurement_preflights', 'distribution_commitments', 'events', 'audit_json', 'evidence_json', 'operator_reference', 'error_message', 'reporting_target_id']) {
  assert(!portalProjection.includes(`'${forbidden}' =>`), `Portal projection must not expose internal ${forbidden}.`);
}
assert(contentApi.includes('be_startpartner_gate4_portal_session'), 'Content endpoint must authenticate before processing the submission.');
assert(contentApi.includes('STARTPARTNER_CONTENT_REPLAY_CONFLICT') && contentApi.includes('409'), 'Changed replay payload must surface as HTTP conflict.');
assert(!contentApi.includes("'error_message'"), 'Content endpoint must not leak internal exception messages.');
assert(portalDomain.includes('be_startpartner_gate4_portal_payload_hash'), 'Portal replay must be bound to normalized content payload.');
assert(portalDomain.includes('be_startpartner_gate4_portal_assert_active_capacity'), 'Active submit must fail closed against current lifetime and limits.');

if (failures.length) {
  console.error('=== Organizer Portal Gate-4 Contract: FAILED ===');
  failures.forEach(failure => console.error(`- ${failure}`));
  process.exit(1);
}
console.log('=== Organizer Portal Gate-4 Contract: OK ===');