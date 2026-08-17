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
const eventMembership = read('events-veroeffentlichen/mitgliedschaft/index.html');
const activityPublish = read('aktivitaeten/sichtbar-werden/index.html');
const providerEntry = read('fuer-veranstalter/index.html');
const providerLogin = read('fuer-veranstalter/login/index.html');
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
  ['Anbieter-Einstieg', providerEntry],
  ['Veröffentlichung erklärt', explainer],
]) {
  for (const wording of forbiddenWording) {
    assert(!content.includes(wording), `${label}: veraltete Startpartner-Formulierung gefunden: ${wording}`);
  }
}

// Informationsarchitektur: zuerst Inhaltstyp, Pilot als übergreifender Weg.
assert(providerEntry.includes('Was möchtest du sichtbar machen?'), 'Anbieter-Einstieg: Inhaltstyp-Frage fehlt');
assert(providerEntry.includes('Veranstaltungen / Events'), 'Anbieter-Einstieg: Event-Inhaltstyp fehlt');
assert(providerEntry.includes('Aktivitäten / dauerhafte Angebote'), 'Anbieter-Einstieg: Aktivitäts-Inhaltstyp fehlt');
assert(providerEntry.includes('href="/events-veroeffentlichen/"'), 'Anbieter-Einstieg: Event-Weg fehlt');
assert(providerEntry.includes('href="/aktivitaeten/sichtbar-werden/"'), 'Anbieter-Einstieg: Aktivitäts-Weg fehlt');
assert(providerEntry.includes('Kostenloser Startpartner-Pilot'), 'Anbieter-Einstieg: übergreifender Pilot fehlt');
assert(providerEntry.includes('Veranstaltungen, Aktivitäten oder beidem') || providerEntry.includes('Veranstaltungen, Aktivitäten oder beides'), 'Anbieter-Einstieg: kombinierter Pilot-Scope fehlt');
assert(!providerEntry.includes('id="organizer-membership-form"'), 'Anbieter-Einstieg darf nicht mehr direkt Membership-Checkout sein');

// Membership ist jetzt eine fokussierte Event-Unterroute, Mechanics bleiben gleich.
assert(eventMembership.includes('canonical" href="https://bocholt-erleben.de/events-veroeffentlichen/mitgliedschaft/"'), 'Membership: neue kanonische Route fehlt');
assert(eventMembership.includes('id="organizer-membership-form"'), 'Membership: bestehendes Formular fehlt');
assert(eventMembership.includes('id="organizer-membership-submit"'), 'Membership: bestehender Submit fehlt');
assert(eventMembership.includes('/js/organizer-membership.js?'), 'Membership: bestehende Membership-Runtime fehlt');
assert(eventMembership.includes('Starter · 9,99 € / Monat'), 'Membership: Starter-Preis verändert');
assert(eventMembership.includes('Aktiv · 19,99 € / Monat'), 'Membership: Aktiv-Preis verändert');
assert(eventMembership.includes('Dauerhaft · 29,99 € / Monat'), 'Membership: Dauerhaft-Preis verändert');
assert(eventPublish.includes('href="/events-veroeffentlichen/mitgliedschaft/"'), 'Event-Funnel: Membership-Link zeigt nicht auf neue Route');
assert(!eventPublish.includes('href="/fuer-veranstalter/">\n              Mitgliedschaft wählen'), 'Event-Funnel: alter Membership-Link ist noch vorhanden');

// Event- und Aktivitätsweg: reguläre Produkte zuerst, Pilot separat, Tipp separat.
assert(eventPublish.includes('Kostenloser Startpartner-Pilot für Veranstaltungen'), 'Event-Funnel: eigener Pilotblock fehlt');
assert(eventPublish.includes('href="/startpartner/?scope=events"'), 'Event-Funnel: Pilot-Scope events fehlt');
assert(eventPublish.includes('Du möchtest nur einen fehlenden Termin melden?'), 'Event-Funnel: separater Tippweg fehlt');
assert(activityPublish.includes('Kostenloser Startpartner-Pilot für Aktivitäten'), 'Aktivitäts-Funnel: eigener Pilotblock fehlt');
assert(activityPublish.includes('href="/startpartner/?scope=activities"'), 'Aktivitäts-Funnel: Pilot-Scope activities fehlt');
assert(activityPublish.includes('Du hast einen konkreten Termin? Veranstaltung veröffentlichen'), 'Aktivitäts-Funnel: direkter Event-Fallback fehlt');
assert(activityPublish.indexOf('So geht es weiter') < activityPublish.indexOf('Kostenloser Startpartner-Pilot für Aktivitäten'), 'Aktivitäts-Funnel: Ablauf muss vor alternativen Pilotweg stehen');
assert(activityPublish.includes('Du möchtest nur ein fehlendes Angebot melden?'), 'Aktivitäts-Funnel: separater Tippweg fehlt');

// Startpartner ist eine gemeinsame Seite für Event, Activity, Kombination und Unsicherheit.
assert(startpartner.includes('Startpartner-Pilot'), 'Startpartner: kanonischer Produktbegriff fehlt');
assert(startpartner.includes('6 Monate kostenlos gemeinsam testen'), 'Startpartner: kostenlose sechsmonatige Pilotphase fehlt');
assert(startpartner.includes('nach sechs Monaten gemeinsam entscheiden'), 'Startpartner: gemeinsame Entscheidung nach sechs Monaten fehlt');
assert(startpartner.includes('keine Zahlungsart'), 'Startpartner: Hinweis auf fehlende Zahlungsart fehlt');
assert(startpartner.includes('nicht automatisch in einen kostenpflichtigen Tarif umgewandelt'), 'Startpartner: Ausschluss automatischer Bezahlumwandlung fehlt');
assert(startpartner.includes('Keine gekaufte Platzierung und keine bessere öffentliche Optik.'), 'Startpartner: Gleichbehandlungs-Hinweis fehlt');
assert(startpartner.includes('id="startpartner-scope"'), 'Startpartner: Scope-Auswahl fehlt');
for (const value of ['events', 'activities', 'both', 'unsure']) {
  assert(startpartner.includes(`value="${value}"`), `Startpartner: Scope-Option fehlt: ${value}`);
}
assert(startpartner.includes('Was möchtest du im Pilot testen?'), 'Startpartner: verständliche Scope-Frage fehlt');
assert(funnelJs.includes('new URLSearchParams(window.location.search)'), 'Startpartner JS: Query-Scope-Auswertung fehlt');
assert(funnelJs.includes('applyScopeFromUrl()'), 'Startpartner JS: Scope-Vorauswahl fehlt');
assert(funnelJs.includes('allowedScopes'), 'Startpartner JS: Scope-Whitelist fehlt');

// Bestehender Formspree-Weg bleibt unverändert.
assert(count(startpartner, 'rel="stylesheet"') === 1, 'Startpartner: es muss genau einen Stylesheet-Link geben');
assert(startpartner.includes('href="/css/style.css?v=2026-06-22-css-governance-v1"'), 'Startpartner: zentraler CSS-Entry-Point fehlt');
assert(!/<style(?:\s|>)/i.test(startpartner), 'Startpartner: route-spezifischer Style-Block ist nicht erlaubt');
assert(!/\sstyle\s*=/i.test(startpartner), 'Startpartner: Inline-Styles sind nicht erlaubt');
assert(startpartner.includes('action="https://formspree.io/f/mrerpwjy"'), 'Startpartner: bestehender Formspree-Writer wurde verändert');
assert(startpartner.includes('method="POST"'), 'Startpartner: POST-Vertrag fehlt');
assert(startpartner.includes('name="lead_type" value="startpartner_6_months_limited"'), 'Startpartner: stabiler lead_type fehlt');
assert(startpartner.includes('name="pilot_scope"'), 'Startpartner: Scope wird nicht mit der Anfrage übertragen');
for (const id of [
  'startpartner-scope',
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

// Zentrale Erklärseite spiegelt die Hierarchie und den kombinierten Pilot-Scope.
assert(explainer.includes('id="inhaltstyp"'), 'Erklärseite: Inhaltstyp-Ebene fehlt');
assert(explainer.includes('1. Was möchtest du sichtbar machen?'), 'Erklärseite: erste Entscheidung fehlt');
assert(explainer.includes('id="veranstaltungen"'), 'Erklärseite: Event-Wege fehlen');
assert(explainer.includes('id="aktivitaeten"'), 'Erklärseite: Aktivitäts-Wege fehlen');
assert(explainer.includes('Der Startpartner-Pilot ist kein dritter Inhaltstyp.'), 'Erklärseite: Pilot-Einordnung fehlt');
assert(explainer.includes('Veranstaltungen, Aktivitäten oder beides'), 'Erklärseite: kombinierter Pilot-Scope fehlt');
assert(explainer.includes('keine Zahlungsart erforderlich'), 'Erklärseite Schema: fehlende Zahlungsart wird nicht erklärt');
assert(explainer.includes('keine automatische kostenpflichtige Umwandlung'), 'Erklärseite Schema: automatische Bezahlumwandlung wird nicht ausgeschlossen');

// Rückkehrweg ist nicht Event-only formuliert.
assert(providerLogin.includes('etwas eingereicht, eine Mitgliedschaft gestartet oder deinen Anbieterzugang erhalten'), 'Login: neutraler Anbieter-Kontext fehlt');
assert(providerLogin.includes('href="/fuer-veranstalter/"'), 'Login: Rückweg zur neutralen Auswahl fehlt');

// Gemeinsames Designsystem, keine neue CSS-Domäne.
for (const importPath of ['./base.css', './pages.css', './components.css']) {
  assert(styleEntry.includes(`@import url("${importPath}`), `CSS-Governance: Import fehlt: ${importPath}`);
}
assert(!styleEntry.toLowerCase().includes('startpartner.css'), 'CSS-Governance: eigener Startpartner-CSS-Owner ist nicht erlaubt');
for (const selector of ['.page--organizers', '.content-hero--panel', '.content-card', '.content-cta', '.content-field']) {
  assert(pagesCss.includes(selector), `Shared CSS: Primitive fehlt: ${selector}`);
}
assert(pagesCss.includes('var(--cmp-btn-primary-bg)'), 'Shared CSS: Primary-CTA nutzt nicht den Komponenten-Token');
assert(pagesCss.includes('var(--cmp-card-radius)'), 'Shared CSS: Kartenradius nutzt nicht den Komponenten-Token');

console.log('Startpartner/provider public funnel contract: OK');
