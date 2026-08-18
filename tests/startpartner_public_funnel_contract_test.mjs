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

const startpartner = read('startpartner/index.html');
const funnelJs = read('js/startpartner-funnel.js');
const eventPublish = read('events-veroeffentlichen/index.html');
const activityPublish = read('aktivitaeten/sichtbar-werden/index.html');
const membership = read('fuer-veranstalter/index.html');
const login = read('fuer-veranstalter/login/index.html');
const explainer = read('veroeffentlichung-erklaert/index.html');
const styleEntry = read('css/style.css');
const pagesCss = read('css/pages.css');

// #274-Leitplanke: Der freigegebene Live-Funnel bleibt die Referenz.
// Nur Startpartner-spezifische Inhalte/Links dürfen additiv präzisiert werden.
assert(!fs.existsSync('events-veroeffentlichen/mitgliedschaft/index.html'), 'Live-Parität: zusätzliche Membership-Unterroute darf nicht existieren');

// Event-Funnel: bestehende Live-Struktur und Reihenfolge bleiben erhalten.
assert(eventPublish.includes('Wähle den passenden Veröffentlichungsweg'), 'Event-Funnel: Live-Wegwahl fehlt');
assert(eventPublish.includes('href="/fuer-veranstalter/"'), 'Event-Funnel: Membership muss weiter auf /fuer-veranstalter/ führen');
assert(!eventPublish.includes('/events-veroeffentlichen/mitgliedschaft/'), 'Event-Funnel: neue Membership-Unterroute ist nicht erlaubt');
assert(eventPublish.includes('Noch nicht der richtige Weg?'), 'Event-Funnel: bestehender Sekundärbereich fehlt');
assert(eventPublish.includes('Nur etwas vorschlagen'), 'Event-Funnel: bestehender Tippweg fehlt');
assert(eventPublish.includes('data-feedback-open="missing"'), 'Event-Funnel: bestehender Tipp-Trigger fehlt');
assert(!eventPublish.includes('Du möchtest nur einen fehlenden Termin melden?'), 'Event-Funnel: aus #272 stammende zusätzliche Tipp-Karte darf nicht bleiben');
assert(!eventPublish.includes('Etwas anderes sichtbar machen? Zur Auswahl für Veranstalter & Anbieter'), 'Event-Funnel: aus #272 stammende Anbieter-Navigation darf nicht bleiben');
assert(eventPublish.includes('Begrenzte Startpartnerplätze'), 'Event-Funnel: Startpartner-Einstieg fehlt');
assert(eventPublish.includes('href="/startpartner/?scope=events"'), 'Event-Funnel: Startpartner darf Events kontextuell vorauswählen');

// Aktivitäts-Funnel: Live-Reihenfolge Eignung -> Tarife -> bestehende Alternativen -> Ablauf.
for (const marker of [
  'Für welche Angebote ist die Aktivitätspräsenz gedacht?',
  'Wähle den passenden Tarif',
  'Noch nicht der richtige Weg?',
  'So geht es weiter',
]) {
  assert(activityPublish.includes(marker), `Aktivitäts-Funnel: Live-Marker fehlt: ${marker}`);
}
assert(activityPublish.indexOf('Für welche Angebote ist die Aktivitätspräsenz gedacht?') < activityPublish.indexOf('Wähle den passenden Tarif'), 'Aktivitäts-Funnel: Live-Reihenfolge Eignung/Tarife verändert');
assert(activityPublish.indexOf('Wähle den passenden Tarif') < activityPublish.indexOf('Noch nicht der richtige Weg?'), 'Aktivitäts-Funnel: Live-Reihenfolge Tarife/Alternativen verändert');
assert(activityPublish.indexOf('Noch nicht der richtige Weg?') < activityPublish.indexOf('So geht es weiter'), 'Aktivitäts-Funnel: Live-Reihenfolge Alternativen/Ablauf verändert');
assert(activityPublish.includes('Nur etwas vorschlagen'), 'Aktivitäts-Funnel: bestehender Tippweg fehlt');
assert(!activityPublish.includes('Du möchtest nur ein fehlendes Angebot melden?'), 'Aktivitäts-Funnel: aus #272 stammende zusätzliche Tipp-Karte darf nicht bleiben');
assert(!activityPublish.includes('Etwas anderes sichtbar machen? Zur Auswahl für Veranstalter & Anbieter'), 'Aktivitäts-Funnel: aus #272 stammende Anbieter-Navigation darf nicht bleiben');
assert(activityPublish.includes('href="/startpartner/?scope=activities"'), 'Aktivitäts-Funnel: Startpartner darf Aktivitäten kontextuell vorauswählen');

// /fuer-veranstalter/ bleibt die freigegebene Membership-Seite, kein neuer Provider-Hub.
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
assert(!membership.includes('Was möchtest du sichtbar machen?'), 'Membership: darf nicht zum Provider-Hub aus #272 umgebaut sein');
assert(!membership.includes('Veranstaltungen / Events'), 'Membership: neutraler Provider-Hub aus #272 darf nicht bleiben');

// Login bleibt wie im freigegebenen Live-Funnel.
assert(login.includes('Status oder Veranstalterbereich öffnen'), 'Login: Live-Titel fehlt');
assert(login.includes('deine Veranstaltung eingereicht oder deine Mitgliedschaft gestartet'), 'Login: Live-Kontext wurde verändert');
assert(login.includes('href="/events-veroeffentlichen/"'), 'Login: Live-Rückweg zur Event-Wegwahl fehlt');
assert(!login.includes('Zurück zur Auswahl für Veranstalter & Anbieter'), 'Login: Provider-Hub-Rückweg aus #272 darf nicht bleiben');

// Erklärseite behält die Live-Struktur und ergänzt Startpartner nur als bestehenden Weg/FAQ.
assert(explainer.includes('id="welcher-weg-passt"'), 'Erklärseite: Live-Einstieg "Welcher Weg passt?" fehlt');
assert(explainer.includes('<h2 id="publish-explainer-paths-title">Welcher Weg passt?</h2>'), 'Erklärseite: Live-Wegwahl fehlt');
assert(explainer.includes('href="/fuer-veranstalter/"'), 'Erklärseite: Membership-Link muss auf /fuer-veranstalter/ bleiben');
assert(!explainer.includes('/events-veroeffentlichen/mitgliedschaft/'), 'Erklärseite: neue Membership-Unterroute darf nicht bleiben');
assert(!explainer.includes('id="inhaltstyp"'), 'Erklärseite: IA-Neustrukturierung aus #272 darf nicht bleiben');
assert(explainer.includes('id="startpartner-weg"'), 'Erklärseite: Startpartner-Weg fehlt');
assert(explainer.includes('id="startpartner"'), 'Erklärseite: Startpartner-FAQ fehlt');
assert(explainer.includes('keine Zahlungsart'), 'Erklärseite: fehlende Zahlungsart muss erklärt werden');
assert(explainer.includes('nicht automatisch in einen kostenpflichtigen Tarif umgewandelt'), 'Erklärseite: automatische Bezahlumwandlung muss ausgeschlossen sein');

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
for (const selector of ['.page--organizers', '.content-hero--panel', '.content-card', '.content-cta', '.content-field']) {
  assert(pagesCss.includes(selector), `Shared CSS: Primitive fehlt: ${selector}`);
}

console.log('Startpartner additive live-parity contract: OK');
