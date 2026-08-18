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
  const start = haystack.indexOf(marker);
  if (start < 0) return '';
  const end = haystack.indexOf('</section>', start);
  return end < 0 ? haystack.slice(start) : haystack.slice(start, end + '</section>'.length);
}

const startpartner = read('startpartner/index.html');
const funnelJs = read('js/startpartner-funnel.js');
const eventPublish = read('events-veroeffentlichen/index.html');
const activityPublish = read('aktivitaeten/sichtbar-werden/index.html');
const membership = read('fuer-veranstalter/index.html');
const login = read('fuer-veranstalter/login/index.html');
const explainer = read('veroeffentlichung-erklaert/index.html');
const styleEntry = read('css/style.css');
const pagesCss = read('css/pages.css');

// #279-Leitplanke: Der freigegebene Live-Funnel bleibt Referenz.
// Startpartner bleibt ein eigener begrenzter Sonderweg, nutzt aber kompakte gemeinsame Funnel-Primitives und die bestehende FAQ für Details.
assert(!fs.existsSync('events-veroeffentlichen/mitgliedschaft/index.html'), 'Live-Parität: zusätzliche Membership-Unterroute darf nicht existieren');

// Event-Funnel: reguläre Live-Wege bleiben, danach kompakter Startpartner-Pilot, danach Tippkanal.
assert(eventPublish.includes('Wähle den passenden Veröffentlichungsweg'), 'Event-Funnel: Live-Wegwahl fehlt');
assert(eventPublish.includes('href="/fuer-veranstalter/"'), 'Event-Funnel: Membership muss weiter auf /fuer-veranstalter/ führen');
assert(!eventPublish.includes('/events-veroeffentlichen/mitgliedschaft/'), 'Event-Funnel: neue Membership-Unterroute ist nicht erlaubt');
for (const marker of ['Einzelne Veranstaltung einreichen', 'Mitgliedschaft für regelmäßige Termine', 'Automatische Übernahme prüfen']) {
  assert(eventPublish.includes(marker), `Event-Funnel: regulärer Live-Weg fehlt: ${marker}`);
}
assert(eventPublish.includes('<h2 id="publish-startpartner-title">Startpartner-Pilot</h2>'), 'Event-Funnel: kompakte Pilot-Überschrift fehlt');
assert(eventPublish.includes('6 Monate kostenlos testen'), 'Event-Funnel: kostenlose Pilotdauer fehlt');
assert(eventPublish.includes('href="/startpartner/?scope=events"'), 'Event-Funnel: Startpartner muss Events kontextuell vorauswählen');
assert(eventPublish.includes('Startpartner-Pilot anfragen'), 'Event-Funnel: Pilot-CTA fehlt');
assert(eventPublish.includes('Noch nicht der richtige Weg?'), 'Event-Funnel: bestehender Tippbereich fehlt');
assert(eventPublish.includes('Nur etwas vorschlagen'), 'Event-Funnel: bestehender Tippweg fehlt');
assert(eventPublish.includes('data-feedback-open="missing"'), 'Event-Funnel: bestehender Tipp-Trigger fehlt');
assert(eventPublish.indexOf('Wähle den passenden Veröffentlichungsweg') < eventPublish.indexOf('<h2 id="publish-startpartner-title">Startpartner-Pilot</h2>'), 'Event-Funnel: Pilot muss nach regulären Wegen stehen');
assert(eventPublish.indexOf('<h2 id="publish-startpartner-title">Startpartner-Pilot</h2>') < eventPublish.indexOf('Noch nicht der richtige Weg?'), 'Event-Funnel: Pilot muss vor dem Tippkanal stehen');
const eventPilotSection = sectionFrom(eventPublish, 'aria-labelledby="publish-startpartner-title"');
assert(eventPilotSection.includes('publish-membership-card publish-models-card'), 'Event-Funnel: Pilot muss gemeinsame Card-/Model-Primitives nutzen');
assert(eventPilotSection.includes('class="publish-model-list"'), 'Event-Funnel: Pilot muss die gemeinsame Model-Liste nutzen');
assert(eventPilotSection.includes('class="publish-model-copy"'), 'Event-Funnel: Pilot muss die gemeinsame Model-Copy nutzen');
assert(!eventPilotSection.includes('content-card--primary'), 'Event-Funnel: Pilot darf keine abweichende Primary-Card-Sonderformatierung nutzen');
assert(eventPilotSection.includes('href="/veroeffentlichung-erklaert/#startpartner"'), 'Event-Funnel: Pilot-FAQ-Link fehlt');
assert(eventPilotSection.includes('Was ist der Startpartner-Pilot? Kurz erklärt'), 'Event-Funnel: verständlicher Pilot-FAQ-Link fehlt');
const eventTipSection = sectionFrom(eventPublish, 'aria-labelledby="publish-secondary-paths-title"');
assert(eventTipSection && !eventTipSection.includes('Startpartner'), 'Event-Funnel: Startpartner darf nicht mehr mit dem Tippkanal gekoppelt sein');
assert(!eventPublish.includes('Du möchtest nur einen fehlenden Termin melden?'), 'Event-Funnel: zusätzliche Tipp-Karte darf nicht eingeführt werden');
assert(!eventPublish.includes('Etwas anderes sichtbar machen? Zur Auswahl für Veranstalter & Anbieter'), 'Event-Funnel: Provider-Hub-Navigation darf nicht eingeführt werden');

// Aktivitäts-Funnel: Live-Reihenfolge bleibt; Pilot wird kompakt zwischen Tarifen und Tippkanal ergänzt.
for (const marker of [
  'Für welche Angebote ist die Aktivitätspräsenz gedacht?',
  'Wähle den passenden Tarif',
  'Noch nicht der richtige Weg?',
  'So geht es weiter',
]) {
  assert(activityPublish.includes(marker), `Aktivitäts-Funnel: Live-Marker fehlt: ${marker}`);
}
assert(activityPublish.includes('<h2 id="activity-presence-startpartner-title">Startpartner-Pilot</h2>'), 'Aktivitäts-Funnel: kompakte Pilot-Überschrift fehlt');
assert(activityPublish.includes('6 Monate kostenlos testen'), 'Aktivitäts-Funnel: kostenlose Pilotdauer fehlt');
assert(activityPublish.includes('href="/startpartner/?scope=activities"'), 'Aktivitäts-Funnel: Startpartner muss Aktivitäten kontextuell vorauswählen');
assert(activityPublish.includes('Startpartner-Pilot anfragen'), 'Aktivitäts-Funnel: Pilot-CTA fehlt');
assert(activityPublish.includes('Nur etwas vorschlagen'), 'Aktivitäts-Funnel: bestehender Tippweg fehlt');
assert(activityPublish.includes('data-feedback-open="missing"'), 'Aktivitäts-Funnel: bestehender Tipp-Trigger fehlt');
assert(activityPublish.indexOf('Für welche Angebote ist die Aktivitätspräsenz gedacht?') < activityPublish.indexOf('Wähle den passenden Tarif'), 'Aktivitäts-Funnel: Live-Reihenfolge Eignung/Tarife verändert');
assert(activityPublish.indexOf('Wähle den passenden Tarif') < activityPublish.indexOf('<h2 id="activity-presence-startpartner-title">Startpartner-Pilot</h2>'), 'Aktivitäts-Funnel: Pilot muss nach Tarifen stehen');
assert(activityPublish.indexOf('<h2 id="activity-presence-startpartner-title">Startpartner-Pilot</h2>') < activityPublish.indexOf('Noch nicht der richtige Weg?'), 'Aktivitäts-Funnel: Pilot muss vor dem Tippkanal stehen');
assert(activityPublish.indexOf('Noch nicht der richtige Weg?') < activityPublish.indexOf('So geht es weiter'), 'Aktivitäts-Funnel: Live-Reihenfolge Tipp/Ablauf verändert');
const activityPilotSection = sectionFrom(activityPublish, 'aria-labelledby="activity-presence-startpartner-title"');
assert(activityPilotSection.includes('publish-membership-card publish-models-card'), 'Aktivitäts-Funnel: Pilot muss gemeinsame Card-/Model-Primitives nutzen');
assert(activityPilotSection.includes('class="publish-model-list"'), 'Aktivitäts-Funnel: Pilot muss die gemeinsame Model-Liste nutzen');
assert(activityPilotSection.includes('class="publish-model-copy"'), 'Aktivitäts-Funnel: Pilot muss die gemeinsame Model-Copy nutzen');
assert(!activityPilotSection.includes('content-card--primary'), 'Aktivitäts-Funnel: Pilot darf keine abweichende Primary-Card-Sonderformatierung nutzen');
assert(activityPilotSection.includes('href="/veroeffentlichung-erklaert/#startpartner"'), 'Aktivitäts-Funnel: Pilot-FAQ-Link fehlt');
assert(activityPilotSection.includes('Was ist der Startpartner-Pilot? Kurz erklärt'), 'Aktivitäts-Funnel: verständlicher Pilot-FAQ-Link fehlt');
const activityTipSection = sectionFrom(activityPublish, 'aria-labelledby="activity-presence-secondary-paths-title"');
assert(activityTipSection && !activityTipSection.includes('Startpartner'), 'Aktivitäts-Funnel: Startpartner darf nicht mehr mit dem Tippkanal gekoppelt sein');
assert(!activityPublish.includes('Du möchtest nur ein fehlendes Angebot melden?'), 'Aktivitäts-Funnel: zusätzliche Tipp-Karte darf nicht eingeführt werden');
assert(!activityPublish.includes('Etwas anderes sichtbar machen? Zur Auswahl für Veranstalter & Anbieter'), 'Aktivitäts-Funnel: Provider-Hub-Navigation darf nicht eingeführt werden');

// /fuer-veranstalter/ bleibt die freigegebene Membership-Seite; nur Pilot-Scope wird präzisiert.
assert(membership.includes('<h1>Mitgliedschaft für regelmäßige Veranstaltungen</h1>'), 'Membership: Live-Haupttitel fehlt');
assert(membership.includes('id="organizer-membership-form"'), 'Membership: bestehendes Formular fehlt');
assert(membership.includes('id="organizer-membership-submit"'), 'Membership: bestehender Submit fehlt');
assert(membership.includes('/js/organizer-membership.js?'), 'Membership: bestehende Membership-Runtime fehlt');
assert(membership.includes('Starter · 9,99 € / Monat'), 'Membership: Starter-Preis verändert');
assert(membership.includes('Aktiv · 19,99 € / Monat'), 'Membership: Aktiv-Preis verändert');
assert(membership.includes('Dauerhaft · 29,99 € / Monat'), 'Membership: Dauerhaft-Preis verändert');
assert(membership.includes('Andere Ausgangslage?'), 'Membership: bestehender Alternativbereich fehlt');
assert(membership.includes('Automatische Übernahme prüfen'), 'Membership: bestehender Automatikweg fehlt');
assert(membership.includes('Begrenzter Startpartnerplatz'), 'Membership: bestehender Startpartner-Einstieg fehlt');
assert(membership.includes('href="/startpartner/?scope=events"'), 'Membership: Startpartner muss Event-Scope mitgeben');
assert(!membership.includes('Was möchtest du sichtbar machen?'), 'Membership: darf nicht zum Provider-Hub umgebaut sein');
assert(!membership.includes('Veranstaltungen / Events'), 'Membership: neutraler Provider-Hub darf nicht bleiben');

// Login bleibt wie im freigegebenen Live-Funnel.
assert(login.includes('Status oder Veranstalterbereich öffnen'), 'Login: Live-Titel fehlt');
assert(login.includes('deine Veranstaltung eingereicht oder deine Mitgliedschaft gestartet'), 'Login: Live-Kontext wurde verändert');
assert(login.includes('href="/events-veroeffentlichen/"'), 'Login: Live-Rückweg zur Event-Wegwahl fehlt');
assert(!login.includes('Zurück zur Auswahl für Veranstalter & Anbieter'), 'Login: Provider-Hub-Rückweg darf nicht bleiben');

// Erklärseite: reguläre Wege bleiben zusammen; Startpartner steht als eigener Sonderweg danach und besitzt den FAQ-Owner.
assert(explainer.includes('id="welcher-weg-passt"'), 'Erklärseite: regulärer Wege-Einstieg fehlt');
assert(explainer.includes('<h2 id="publish-explainer-paths-title">Welcher reguläre Weg passt?</h2>'), 'Erklärseite: reguläre Wegwahl fehlt');
assert(explainer.includes('href="/fuer-veranstalter/"'), 'Erklärseite: Membership-Link muss auf /fuer-veranstalter/ bleiben');
assert(!explainer.includes('/events-veroeffentlichen/mitgliedschaft/'), 'Erklärseite: neue Membership-Unterroute darf nicht bleiben');
assert(!explainer.includes('id="inhaltstyp"'), 'Erklärseite: IA-Neustrukturierung zum Provider-Hub darf nicht bleiben');
assert(explainer.includes('id="startpartner-weg"'), 'Erklärseite: separater Startpartner-Sonderweg fehlt');
assert(explainer.includes('Sonderweg: Startpartner-Pilot'), 'Erklärseite: Sonderweg-Kennzeichnung fehlt');
assert(explainer.indexOf('Welcher reguläre Weg passt?') < explainer.indexOf('Sonderweg: Startpartner-Pilot'), 'Erklärseite: Startpartner muss nach regulären Wegen stehen');
assert(explainer.includes('id="startpartner"'), 'Erklärseite: Startpartner-FAQ fehlt');
assert(explainer.includes('keine Zahlungsart'), 'Erklärseite: fehlende Zahlungsart muss erklärt werden');
assert(explainer.includes('keine automatische kostenpflichtige Umwandlung'), 'Erklärseite: automatische Bezahlumwandlung muss ausgeschlossen sein');

// Startpartner selbst darf als einziger Pilot-Funnel Events, Aktivitäten oder beides erfassen.
for (const marker of [
  'Startpartner-Pilot',
  '6 Monate kostenlos gemeinsam testen',
  'keine Zahlungsart',
  'nicht automatisch in einen kostenpflichtigen Tarif umgewandelt',
]) {
  assert(startpartner.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE')), `Startpartner: Marker fehlt: ${marker}`);
}
assert(startpartner.includes('id="startpartner-scope"'), 'Startpartner: Scope-Auswahl fehlt');
for (const value of ['events', 'activities', 'both', 'unsure']) {
  assert(startpartner.includes(`value="${value}"`), `Startpartner: Scope-Option fehlt: ${value}`);
}
assert(startpartner.includes('href="/events-veroeffentlichen/"'), 'Startpartner: Rückweg zu regulären Events fehlt');
assert(startpartner.includes('Regulär Veranstaltungen veröffentlichen'), 'Startpartner: Event-Rückweg ist nicht verständlich beschriftet');
assert(startpartner.includes('href="/aktivitaeten/sichtbar-werden/"'), 'Startpartner: Rückweg zu regulären Aktivitäten fehlt');
assert(startpartner.includes('Regulär Aktivität sichtbar machen'), 'Startpartner: Aktivitäts-Rückweg ist nicht verständlich beschriftet');
assert(!startpartner.includes('Zur Auswahl für Veranstalter & Anbieter'), 'Startpartner: falscher Provider-Hub-Rückweg darf nicht bleiben');
assert(funnelJs.includes('new URLSearchParams(window.location.search)'), 'Startpartner JS: Query-Scope-Auswertung fehlt');
assert(funnelJs.includes('applyScopeFromUrl()'), 'Startpartner JS: Scope-Vorauswahl fehlt');
assert(funnelJs.includes('allowedScopes'), 'Startpartner JS: Scope-Whitelist fehlt');

// Bestehender Formspree-Weg und gemeinsames Designsystem bleiben unverändert.
assert(count(startpartner, 'rel="stylesheet"') === 1, 'Startpartner: genau ein Stylesheet-Link erwartet');
assert(startpartner.includes('href="/css/style.css?v=2026-06-22-css-governance-v1"'), 'Startpartner: zentraler CSS-Entry-Point fehlt');
assert(!/<style(?:\s|>)/i.test(startpartner), 'Startpartner: route-spezifischer Style-Block ist nicht erlaubt');
assert(!/\sstyle\s*=/i.test(startpartner), 'Startpartner: Inline-Styles sind nicht erlaubt');
assert(startpartner.includes('action="https://formspree.io/f/mrerpwjy"'), 'Startpartner: bestehender Formspree-Writer wurde verändert');
assert(startpartner.includes('method="POST"'), 'Startpartner: POST-Vertrag fehlt');
assert(startpartner.includes('name="lead_type" value="startpartner_6_months_limited"'), 'Startpartner: stabiler lead_type fehlt');
assert(startpartner.includes('name="pilot_scope"'), 'Startpartner: Scope wird nicht mit der Anfrage übertragen');
assert(!startpartner.includes('/api/startpartner/'), 'Startpartner: öffentlicher Funnel darf nicht auf internen API-Pfad umgestellt werden');
assert(funnelJs.includes('fetch(form.action'), 'Startpartner JS: bestehender Formspree-Submit-Vertrag fehlt');
assert(!funnelJs.includes('/api/startpartner/'), 'Startpartner JS: interner Startpartner-API-Pfad ist nicht erlaubt');

for (const importPath of ['./base.css', './pages.css', './components.css']) {
  assert(styleEntry.includes(`@import url("${importPath}`), `CSS-Governance: Import fehlt: ${importPath}`);
}
assert(!styleEntry.toLowerCase().includes('startpartner.css'), 'CSS-Governance: eigener Startpartner-CSS-Owner ist nicht erlaubt');
for (const selector of ['.page--organizers', '.content-hero--panel', '.content-card', '.content-cta', '.content-field', '.publish-model-list', '.publish-model-copy']) {
  assert(pagesCss.includes(selector), `Shared CSS: Primitive fehlt: ${selector}`);
}

console.log('Startpartner compact FAQ live-parity contract: OK');