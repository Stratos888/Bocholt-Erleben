import fs from 'node:fs';

function read(path) {
  return fs.readFileSync(path, 'utf8');
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function count(haystack, needle) {
  return haystack.split(needle).length - 1;
}

function sectionFrom(haystack, marker) {
  const markerIndex = haystack.indexOf(marker);
  if (markerIndex < 0) return '';
  const start = haystack.lastIndexOf('<section', markerIndex);
  if (start < 0) return '';
  const end = haystack.indexOf('</section>', markerIndex);
  return end < 0 ? haystack.slice(start) : haystack.slice(start, end + '</section>'.length);
}

const startpartner = read('startpartner/index.html');
const startpartnerSuccess = read('startpartner/erfolg/index.html');
const funnelJs = read('js/startpartner-funnel.js');
const intakePhp = read('api/startpartner/intake.php');
const eventPublish = read('events-veroeffentlichen/index.html');
const activityPublish = read('aktivitaeten/sichtbar-werden/index.html');
const membership = read('fuer-veranstalter/index.html');
const explainer = read('veroeffentlichung-erklaert/index.html');
const feedbackJs = read('js/feedback.js');
const siteFooterJs = read('js/site-footer.js');

// Kanonische öffentliche Benennung: das Produkt heißt überall Startpartner.
for (const [label, source] of [
  ['Startpartner', startpartner],
  ['Startpartner-Erfolg', startpartnerSuccess],
  ['Event-Funnel', eventPublish],
  ['Aktivitäts-Funnel', activityPublish],
  ['Mitgliedschaft', membership],
  ['Erklärseite', explainer],
]) {
  assert(!source.includes('Startpartner-Pilot'), `${label}: alte Produktbezeichnung Startpartner-Pilot darf nicht zurückkehren`);
  assert(!source.includes('Startpartnerplatz'), `${label}: alte Produktbezeichnung Startpartnerplatz darf nicht zurückkehren`);
}

// Globaler Feedback-Owner bleibt unverändert vorhanden.
assert(feedbackJs.includes('missing: {'), 'Feedback: Missing-Typ fehlt');
assert(feedbackJs.includes('ensureLauncher();'), 'Feedback: globaler Launcher fehlt');
assert(siteFooterJs.includes('data-feedback-open="missing"'), 'Footer: Missing-Trigger fehlt');
assert(siteFooterJs.includes('data-feedback-open="global"'), 'Footer: globaler Feedback-Trigger fehlt');

// Event-Funnel: drei primäre Veröffentlichungswege, Startpartner vor der sekundären automatischen Übernahme.
for (const marker of [
  'Wähle den passenden Veröffentlichungsweg',
  'Einzelne Veranstaltung einreichen',
  'Mitgliedschaft für regelmäßige Termine',
  '<strong>Startpartner</strong>',
  '6 Monate kostenlos',
  'Startpartner anfragen',
  'Termine schon gepflegt? Automatische Übernahme prüfen',
  'Wie funktioniert Startpartner? Kurz erklärt',
]) {
  assert(eventPublish.includes(marker), `Event-Funnel: Marker fehlt: ${marker}`);
}
assert(eventPublish.includes('href="/startpartner/?scope=events"'), 'Event-Funnel: Event-Scope fehlt');
assert(eventPublish.includes('href="/events-veroeffentlichen/anbindung/"'), 'Event-Funnel: sekundärer Automatik-Pfad fehlt');
assert(eventPublish.includes('href="/veroeffentlichung-erklaert/#startpartner-weg"'), 'Event-Funnel: Startpartner-Erklärlink fehlt');
assert(!eventPublish.includes('/events-veroeffentlichen/mitgliedschaft/'), 'Event-Funnel: zusätzliche Membership-Unterroute darf nicht existieren');
const eventPathsSection = sectionFrom(eventPublish, 'aria-labelledby="publish-paths-title"');
assert(eventPathsSection.includes('publish-membership-card publish-models-card'), 'Event-Funnel: gemeinsame Card-Primitives fehlen');
assert(eventPathsSection.includes('class="publish-model-list"'), 'Event-Funnel: Model-Liste fehlt');
assert(count(eventPathsSection, 'class="publish-model-copy"') === 3, 'Event-Funnel: genau drei primäre Veröffentlichungswege erwartet');
const singleIndex = eventPathsSection.indexOf('<strong>Einzelne Veranstaltung einreichen</strong>');
const membershipIndex = eventPathsSection.indexOf('<strong>Mitgliedschaft für regelmäßige Termine</strong>');
const startpartnerIndex = eventPathsSection.indexOf('<strong>Startpartner</strong>');
const automationIndex = eventPathsSection.indexOf('Termine schon gepflegt? Automatische Übernahme prüfen');
assert(singleIndex >= 0 && membershipIndex > singleIndex && startpartnerIndex > membershipIndex, 'Event-Funnel: Reihenfolge der drei primären Veröffentlichungswege ist falsch');
assert(automationIndex > startpartnerIndex, 'Event-Funnel: automatische Übernahme muss nach Startpartner als sekundärer Pfad stehen');

// Aktivitäts-Funnel: Eignung/Tarife/Ablauf bleiben, Startpartner liegt weiter zwischen Tarif und Ablauf.
for (const marker of [
  'Für welche Angebote ist die Aktivitätspräsenz gedacht?',
  'Wähle den passenden Tarif',
  '<h2 id="activity-presence-startpartner-title">Startpartner</h2>',
  '6 Monate kostenlos testen',
  'Startpartner anfragen',
  'Wie funktioniert Startpartner? Kurz erklärt',
  'So geht es weiter',
]) {
  assert(activityPublish.includes(marker), `Aktivitäts-Funnel: Marker fehlt: ${marker}`);
}
assert(activityPublish.includes('href="/startpartner/?scope=activities"'), 'Aktivitäts-Funnel: Activity-Scope fehlt');
assert(activityPublish.indexOf('Wähle den passenden Tarif') < activityPublish.indexOf('<h2 id="activity-presence-startpartner-title">Startpartner</h2>'), 'Aktivitäts-Funnel: Startpartner muss nach Tarifen stehen');
assert(activityPublish.indexOf('<h2 id="activity-presence-startpartner-title">Startpartner</h2>') < activityPublish.indexOf('So geht es weiter'), 'Aktivitäts-Funnel: Startpartner muss vor dem Ablauf stehen');

// Membership-Funnel bleibt fachlich unverändert; nur die öffentliche Startpartner-Sprache wird vereinheitlicht.
assert(membership.includes('<h1>Mitgliedschaft für regelmäßige Veranstaltungen</h1>'), 'Membership: H1 fehlt');
assert(membership.includes('id="organizer-membership-form"'), 'Membership: Formular fehlt');
assert(membership.includes('Starter · 9,99 € / Monat'), 'Membership: Starter-Preis verändert');
assert(membership.includes('Aktiv · 19,99 € / Monat'), 'Membership: Aktiv-Preis verändert');
assert(membership.includes('Dauerhaft · 29,99 € / Monat'), 'Membership: Dauerhaft-Preis verändert');
assert(membership.includes('<strong>Startpartner</strong>'), 'Membership: Startpartner-Einstieg fehlt');
assert(membership.includes('href="/startpartner/?scope=events"'), 'Membership: Event-Scope fehlt');
assert(membership.includes('>Startpartner anfragen</a>'), 'Membership: Startpartner-CTA inkonsistent');

// /startpartner/: kurzer First-Party-Funnel mit vollständigem Nutzenversprechen im Hero, ohne redundante Detailblöcke.
assert(startpartner.includes('<main class="page page--publish page--startpartner">'), 'Startpartner: Publish-Funnel-Familie fehlt');
assert(startpartner.includes('<h1>Als Startpartner 6 Monate kostenlos testen</h1>'), 'Startpartner: klares Startpartner-Versprechen in der H1 fehlt');
assert(startpartner.includes('Veranstaltungen, Aktivitäten oder beides testen'), 'Startpartner: Umfang fehlt im Hero');
assert(startpartner.includes('keine Zahlungsart erforderlich'), 'Startpartner: Ausschluss einer Zahlungsart fehlt im Hero');
assert(startpartner.includes('keine automatische kostenpflichtige Verlängerung'), 'Startpartner: Ausschluss automatischer Kosten fehlt im Hero');
assert(!startpartner.includes('ohne Zahlungsart'), 'Startpartner: alte Zahlungsart-Variante darf nicht zurückkehren');
assert(startpartner.includes('Wir prüfen, ob Startpartner zu deinem Angebot passt'), 'Startpartner: klare Prüfkommunikation fehlt');
assert(!startpartner.includes('class="content-kicker"'), 'Startpartner: Kicker darf nicht zurückkehren');
assert(!startpartner.includes('Was kann der Pilot umfassen?'), 'Startpartner: redundanter Scope-Erklärblock darf nicht zurückkehren');
assert(!startpartner.includes('So läuft der Start ab'), 'Startpartner: redundanter Ablaufblock darf nicht zurückkehren');
assert(startpartner.includes('<h2 id="startpartner-request-title">Startpartner anfragen</h2>'), 'Startpartner: Formulartitel fehlt');
assert(startpartner.includes('Was möchtest du testen? *'), 'Startpartner: kompakte Scope-Frage fehlt');
assert(startpartner.includes('id="startpartner-request-form"'), 'Startpartner: Anfrageformular fehlt');
assert(startpartner.includes('action="/api/startpartner/intake.php"'), 'Startpartner: First-Party-Endpoint fehlt');
assert(!startpartner.includes('formspree.io'), 'Startpartner: Formspree darf nach First-Party-Cutover nicht mehr vorkommen');
assert(startpartner.includes('id="startpartner-contact" name="contact_name"'), 'Startpartner: Ansprechperson fehlt');
assert(startpartner.includes('id="startpartner-website" name="website"'), 'Startpartner: getrennte Website/Quelle fehlt');
assert(startpartner.includes('id="startpartner-note" name="description"'), 'Startpartner: strukturierte Kurzbeschreibung fehlt');
assert(startpartner.includes('id="startpartner-website-confirm" name="website_confirm"'), 'Startpartner: Honeypot fehlt');
for (const value of ['events', 'activities', 'both', 'unsure']) {
  assert(startpartner.includes(`value="${value}"`), `Startpartner: Scope-Option fehlt: ${value}`);
}
assert(startpartner.includes('id="startpartner-request-submit" type="submit">Startpartner anfragen</button>'), 'Startpartner: Submit inkonsistent');
assert(startpartner.includes('href="/veroeffentlichung-erklaert/#startpartner-weg"'), 'Startpartner: Erklärlink fehlt');
assert(startpartner.includes('Wie funktioniert Startpartner? Kurz erklärt'), 'Startpartner: Erklärlink nicht verständlich');
assert(startpartner.includes('<h2 id="startpartner-regular-paths-title">Lieber regulär veröffentlichen?</h2>'), 'Startpartner: reguläre Alternativen fehlen');
const regularPathsSection = sectionFrom(startpartner, 'aria-labelledby="startpartner-regular-paths-title"');
assert(regularPathsSection.includes('class="publish-model-list"'), 'Startpartner: reguläre Alternativen müssen Premium-Model-Primitives nutzen');
assert(count(regularPathsSection, 'class="publish-model-copy"') === 2, 'Startpartner: genau zwei reguläre Alternativen erwartet');
assert(regularPathsSection.includes('href="/events-veroeffentlichen/"'), 'Startpartner: Event-Rückweg fehlt');
assert(regularPathsSection.includes('href="/aktivitaeten/sichtbar-werden/"'), 'Startpartner: Activity-Rückweg fehlt');
assert(regularPathsSection.includes('Zu den Veranstaltungswegen'), 'Startpartner: Event-CTA fehlt');
assert(regularPathsSection.includes('Zu den Aktivitäts-Tarifen'), 'Startpartner: Activity-CTA fehlt');
assert(count(startpartner, 'rel="stylesheet"') === 1, 'Startpartner: genau ein Stylesheet-Link erwartet');

// Eindeutiger Abschlusszustand.
assert(startpartnerSuccess.includes('<meta name="robots" content="noindex,nofollow">'), 'Startpartner-Erfolg: noindex fehlt');
assert(startpartnerSuccess.includes('<h1>Anfrage erhalten</h1>'), 'Startpartner-Erfolg: eindeutige H1 fehlt');
assert(startpartnerSuccess.includes('Wir prüfen jetzt, ob Startpartner zu deinem Angebot passt.'), 'Startpartner-Erfolg: Prüfzustand fehlt');
assert(startpartnerSuccess.includes('So geht es weiter'), 'Startpartner-Erfolg: nächste Schritte fehlen');
assert(startpartnerSuccess.includes('Die Anfrage ist noch keine Aufnahmezusage.'), 'Startpartner-Erfolg: No-Approval-Hinweis fehlt');
assert(!startpartnerSuccess.includes('class="content-kicker"'), 'Startpartner-Erfolg: Kicker darf nicht vorkommen');

// Die Erklärseite ist der Detail-Owner für Bedingungen und Ablauf.
assert(explainer.includes('id="startpartner-weg"'), 'Erklärseite: Startpartner-Sonderweg fehlt');
assert(explainer.includes('Sonderweg: Startpartner'), 'Erklärseite: kanonische Sonderweg-Überschrift fehlt');
assert(explainer.includes('id="startpartner"'), 'Erklärseite: Startpartner-FAQ-Anker fehlt');
assert(explainer.includes('Wie funktioniert Startpartner?'), 'Erklärseite: Startpartner-FAQ fehlt');
assert(explainer.includes('sechs Monate kostenlos'), 'Erklärseite: Sechs-Monats-Modell fehlt');
assert(explainer.includes('Keine Zahlungsart erforderlich'), 'Erklärseite: Zahlungsart-Ausschluss fehlt');
assert(explainer.includes('keine automatische kostenpflichtige Verlängerung'), 'Erklärseite: automatische kostenpflichtige Verlängerung muss ausgeschlossen bleiben');
assert(!explainer.includes('kostenpflichtige Umwandlung'), 'Erklärseite: alte Umwandlungs-Sprache darf nicht zurückkehren');
assert(!explainer.includes('kostenpflichtigen Tarif umgewandelt'), 'Erklärseite: alte Umwandlungs-Sprache darf nicht zurückkehren');
assert(explainer.includes('Nach sechs Monaten werten wir die Wirkung gemeinsam aus'), 'Erklärseite: gemeinsame Auswertung fehlt');

// Runtime: First-Party-Submit, Idempotenz, Fehlererhalt und Redirect in Erfolgszustand.
assert(funnelJs.includes('new URLSearchParams(window.location.search)'), 'Startpartner JS: Query-Scope-Auswertung fehlt');
assert(funnelJs.includes('allowedScopes'), 'Startpartner JS: Scope-Whitelist fehlt');
assert(funnelJs.includes('applyScopeFromUrl()'), 'Startpartner JS: Scope-Vorauswahl fehlt');
assert(funnelJs.includes('Startpartner anfragen'), 'Startpartner JS: Default-CTA inkonsistent');
assert(funnelJs.includes('fetch(form.action'), 'Startpartner JS: First-Party-Request fehlt');
assert(funnelJs.includes('"Content-Type": "application/json"'), 'Startpartner JS: strukturierter JSON-Request fehlt');
assert(funnelJs.includes('"Idempotency-Key": getIdempotencyKey()'), 'Startpartner JS: Idempotency-Key fehlt');
assert(funnelJs.includes('window.location.assign'), 'Startpartner JS: Erfolgsredirect fehlt');
assert(funnelJs.includes('/startpartner/erfolg/'), 'Startpartner JS: Erfolgsroute fehlt');
assert(funnelJs.includes('Deine Angaben bleiben erhalten.'), 'Startpartner JS: klarer Fehlerzustand fehlt');
assert(!funnelJs.includes('form.reset()'), 'Startpartner JS: Formular darf vor sichtbarem Abschluss nicht mehr geleert werden');
assert(!funnelJs.includes('formspree'), 'Startpartner JS: Formspree darf nach First-Party-Cutover nicht mehr vorkommen');
assert(!funnelJs.includes('Startpartner-Pilot'), 'Startpartner JS: alte Produktbezeichnung darf nicht zurückkehren');
assert(!funnelJs.includes('Startpartnerplatz'), 'Startpartner JS: alte Produktbezeichnung darf nicht zurückkehren');

// Backend-Grenze: Public-Self-Service und geschützter Outreach teilen die Domain, nicht die Authentifizierung.
assert(intakePhp.includes("if ($source === 'targeted_outreach')"), 'Startpartner Intake: geschützter Outreach-Zweig fehlt');
assert(intakePhp.includes('be_require_review_access();'), 'Startpartner Intake: Outreach-Schutz fehlt');
assert(intakePhp.includes("if ($source !== 'self_service')"), 'Startpartner Intake: Public-Self-Service muss fail-closed abgegrenzt sein');
assert(intakePhp.includes("'stored' => true"), 'Startpartner Intake: kompakte Public-Erfolgsantwort fehlt');

console.log('startpartner_public_funnel_contract_test: OK');
