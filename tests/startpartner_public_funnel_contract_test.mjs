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
const organizer = read('fuer-veranstalter/index.html');
const explainer = read('veroeffentlichung-erklaert/index.html');
const styleEntry = read('css/style.css');
const pagesCss = read('css/pages.css');

const forbiddenWording = [
  'danach den passenden Tarif wählen',
  'vor dem regulären Tarif',
  'danach passend wechseln',
];

for (const [label, content] of [
  ['Startpartner', startpartner],
  ['Veranstaltung veröffentlichen', eventPublish],
  ['Aktivität sichtbar machen', activityPublish],
  ['Mitgliedschaft', organizer],
  ['Veröffentlichung erklärt', explainer],
]) {
  for (const wording of forbiddenWording) {
    assert(!content.includes(wording), `${label}: veraltete Startpartner-Formulierung gefunden: ${wording}`);
  }
}

assert(startpartner.includes('Startpartner-Pilot'), 'Startpartner: kanonischer Produktbegriff fehlt');
assert(startpartner.includes('Kostenlose sechsmonatige Pilotphase'), 'Startpartner: kostenlose sechsmonatige Pilotphase fehlt');
assert(startpartner.includes('nach sechs Monaten gemeinsam entscheiden'), 'Startpartner: gemeinsame Entscheidung nach sechs Monaten fehlt');
assert(startpartner.includes('keine Zahlungsart'), 'Startpartner: Hinweis auf fehlende Zahlungsart fehlt');
assert(startpartner.includes('nicht automatisch in einen kostenpflichtigen Tarif umgewandelt'), 'Startpartner: Ausschluss automatischer Bezahlumwandlung fehlt');
assert(startpartner.includes('Keine gekaufte Platzierung und keine bessere öffentliche Optik.'), 'Startpartner: Gleichbehandlungs-Hinweis fehlt');

for (const requiredClass of [
  'page--organizers',
  'page--startpartner',
  'content-hero content-hero--panel',
  'content-card content-card--primary',
  'content-cta content-cta--primary',
  'content-cta content-cta--secondary',
  'content-form',
  'content-field__control',
]) {
  assert(startpartner.includes(requiredClass), `Startpartner: Shared Primitive fehlt: ${requiredClass}`);
}

assert(count(startpartner, 'rel="stylesheet"') === 1, 'Startpartner: es muss genau einen Stylesheet-Link geben');
assert(startpartner.includes('href="/css/style.css?v=2026-06-22-css-governance-v1"'), 'Startpartner: zentraler CSS-Entry-Point fehlt');
assert(!/<style(?:\s|>)/i.test(startpartner), 'Startpartner: route-spezifischer Style-Block ist nicht erlaubt');
assert(!/\sstyle\s*=/i.test(startpartner), 'Startpartner: Inline-Styles sind nicht erlaubt');

assert(startpartner.includes('action="https://formspree.io/f/mrerpwjy"'), 'Startpartner: bestehender Formspree-Writer wurde verändert');
assert(startpartner.includes('method="POST"'), 'Startpartner: POST-Vertrag fehlt');
assert(startpartner.includes('name="lead_type" value="startpartner_6_months_limited"'), 'Startpartner: stabiler lead_type fehlt');
for (const id of [
  'startpartner-organization',
  'startpartner-email',
  'startpartner-note',
  'startpartner-privacy-confirmed',
  'startpartner-request-submit',
]) {
  assert(startpartner.includes(`id="${id}"`), `Startpartner: Formularfeld fehlt: ${id}`);
}
assert(!startpartner.includes('/api/startpartner/'), 'Startpartner: öffentlicher Funnel darf nicht auf internen Startpartner-API-Pfad umgestellt werden');
assert(funnelJs.includes('fetch(form.action'), 'Startpartner JS: bestehender Formspree-Submit-Vertrag fehlt');
assert(!funnelJs.includes('/api/startpartner/'), 'Startpartner JS: interner Startpartner-API-Pfad ist nicht erlaubt');
assert(funnelJs.includes('Bitte fülle die markierten Pflichtfelder aus.'), 'Startpartner JS: gemeinsamer Validierungszustand fehlt');
assert(funnelJs.includes('Deine Anfrage zum Startpartner-Pilot ist angekommen.'), 'Startpartner JS: kanonischer Erfolgszustand fehlt');

for (const [label, content] of [
  ['Veranstaltung veröffentlichen', eventPublish],
  ['Aktivität sichtbar machen', activityPublish],
  ['Mitgliedschaft', organizer],
]) {
  assert(content.includes('kostenlose sechsmonatige Pilotphase'), `${label}: Pilotphase ist nicht konsistent benannt`);
  assert(content.includes('danach gemeinsam entscheiden, ob ein regulärer Tarif passt'), `${label}: gemeinsame Tarifentscheidung fehlt`);
  assert(content.includes('href="/startpartner/"'), `${label}: Startpartner-Link fehlt`);
}

assert(explainer.includes('<strong>Startpartner-Pilot</strong>'), 'Erklärseite: Startpartner-Pilot fehlt in der Wegwahl');
assert(explainer.includes('kostenlose sechsmonatige Pilotphase'), 'Erklärseite: kostenlose Pilotphase fehlt');
assert(explainer.includes('keine Zahlungsart erforderlich'), 'Erklärseite Schema: fehlende Zahlungsart wird nicht erklärt');
assert(explainer.includes('keine automatische kostenpflichtige Umwandlung'), 'Erklärseite Schema: automatische Bezahlumwandlung wird nicht ausgeschlossen');
assert(explainer.includes('Du hinterlegst keine Zahlungsart und wirst nicht automatisch in einen kostenpflichtigen Tarif umgewandelt.'), 'Erklärseite sichtbar: Produktgrenze fehlt');
assert(explainer.includes('Nach sechs Monaten werten wir die Wirkung gemeinsam aus und entscheiden ausdrücklich'), 'Erklärseite sichtbar: ausdrückliche Abschlussentscheidung fehlt');

for (const importPath of ['./base.css', './pages.css', './components.css']) {
  assert(styleEntry.includes(`@import url("${importPath}`), `CSS-Governance: Import fehlt: ${importPath}`);
}
assert(!styleEntry.toLowerCase().includes('startpartner.css'), 'CSS-Governance: eigener Startpartner-CSS-Owner ist nicht erlaubt');
for (const selector of ['.page--organizers', '.content-hero--panel', '.content-card', '.content-cta', '.content-field']) {
  assert(pagesCss.includes(selector), `Shared CSS: Primitive fehlt: ${selector}`);
}
assert(pagesCss.includes('var(--cmp-btn-primary-bg)'), 'Shared CSS: Primary-CTA nutzt nicht den Komponenten-Token');
assert(pagesCss.includes('var(--cmp-card-radius)'), 'Shared CSS: Kartenradius nutzt nicht den Komponenten-Token');

console.log('Startpartner public funnel contract: OK');
