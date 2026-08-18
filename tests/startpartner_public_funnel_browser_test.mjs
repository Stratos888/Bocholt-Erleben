import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args = process.argv.slice(2);
const value = (name) => {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : '';
};

const baseUrl = String(value('--base-url') || '').replace(/\/+$/, '');
const outDir = value('--out-dir');
if (!baseUrl || !outDir) {
  console.error('Usage: node tests/startpartner_public_funnel_browser_test.mjs --base-url URL --out-dir DIR');
  process.exit(2);
}

fs.mkdirSync(outDir, { recursive: true });

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function open(page, routePath) {
  const response = await page.goto(`${baseUrl}${routePath}`, { waitUntil: 'networkidle', timeout: 18000 });
  assert(response && response.status() === 200, `${routePath}: erwarteter HTTP 200 fehlt`);
}

async function assertNoOverflow(page, label) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  assert(!overflow, `${label}: horizontaler Überlauf`);
}

async function styleSignature(page, selector, properties) {
  const locator = page.locator(selector).first();
  await locator.waitFor({ state: 'visible', timeout: 8000 });
  return locator.evaluate((element, props) => {
    const styles = getComputedStyle(element);
    return Object.fromEntries(props.map((property) => [property, styles[property]]));
  }, properties);
}

async function assertSameElementStyle(page, referenceSelector, actualSelector, properties, label) {
  const expected = await styleSignature(page, referenceSelector, properties);
  const actual = await styleSignature(page, actualSelector, properties);
  for (const property of properties) {
    assert(actual[property] === expected[property], `${label}: ${property}: ${actual[property]} != ${expected[property]}`);
  }
}

async function sharedSignature(page) {
  return {
    hero: await styleSignature(page, '.content-hero--panel', ['borderRadius', 'boxShadow', 'paddingTop', 'paddingLeft']),
    primaryCard: await styleSignature(page, '.content-card--primary', ['borderRadius', 'backgroundColor', 'borderTopColor', 'boxShadow']),
    primaryCta: await styleSignature(page, '.content-cta--primary', ['borderRadius', 'minHeight', 'backgroundColor', 'borderTopColor', 'boxShadow']),
    input: await styleSignature(page, 'input.content-field__control', ['borderRadius', 'backgroundColor', 'borderTopColor', 'fontSize']),
    select: await styleSignature(page, 'select.content-field__control', ['borderRadius', 'backgroundColor', 'borderTopColor', 'fontSize']),
  };
}

function assertSame(actual, expected, label) {
  for (const [component, props] of Object.entries(actual)) {
    for (const [property, actualValue] of Object.entries(props)) {
      const expectedValue = expected[component][property];
      assert(actualValue === expectedValue, `${label}: ${component}.${property}: ${actualValue} != ${expectedValue}`);
    }
  }
}

async function runProfile(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  let formspreeRequests = 0;

  await page.route('https://formspree.io/**', async (route) => {
    formspreeRequests += 1;
    await route.abort();
  });

  // Event-Seite: reguläre Live-Wege, danach kompakter Startpartner-Pilot mit FAQ-Link, danach Tippkanal.
  await open(page, '/events-veroeffentlichen/');
  const eventText = await page.locator('body').innerText();
  for (const marker of [
    'Wähle den passenden Veröffentlichungsweg',
    'Einzelne Veranstaltung einreichen',
    'Mitgliedschaft für regelmäßige Termine',
    'Automatische Übernahme prüfen',
    'Startpartner-Pilot',
    '6 Monate kostenlos testen',
    'Was ist der Startpartner-Pilot? Kurz erklärt',
    'Noch nicht der richtige Weg?',
    'Nur etwas vorschlagen',
  ]) {
    assert(eventText.includes(marker), `${profileName}: Event-Marker fehlt: ${marker}`);
  }
  assert(eventText.indexOf('Wähle den passenden Veröffentlichungsweg') < eventText.indexOf('Startpartner-Pilot'), `${profileName}: Event-Pilot steht nicht nach den regulären Wegen`);
  assert(eventText.indexOf('Startpartner-Pilot') < eventText.indexOf('Noch nicht der richtige Weg?'), `${profileName}: Event-Pilot steht nicht vor dem Tippkanal`);
  assert(!eventText.includes('Begrenzte Startpartnerplätze'), `${profileName}: Event-Pilot ist noch mit Tippkanal gekoppelt`);
  assert(await page.locator('a[href="/fuer-veranstalter/"]').count() > 0, `${profileName}: Live-Membership-Link fehlt`);
  assert(await page.locator('a[href="/events-veroeffentlichen/mitgliedschaft/"]').count() === 0, `${profileName}: zusätzliche Membership-Unterroute ist noch verlinkt`);
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Event-Startpartner-Scope fehlt`);
  assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner"]').count() === 1, `${profileName}: Event-Startpartner-FAQ-Link fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner-Pilot anfragen' }).count() === 1, `${profileName}: Event-Pilot-CTA fehlt`);
  assert(await page.locator('[data-feedback-open="missing"]').count() > 0, `${profileName}: bestehender Event-Tippweg fehlt`);
  await assertSameElementStyle(
    page,
    'section[aria-labelledby="publish-paths-title"] .publish-model-copy strong',
    'section[aria-labelledby="publish-startpartner-title"] .publish-model-copy strong',
    ['fontSize', 'fontWeight', 'lineHeight', 'color'],
    `${profileName}: Event-Pilot-Copy`,
  );
  await assertSameElementStyle(
    page,
    'section[aria-labelledby="publish-paths-title"] .content-cta--primary',
    'section[aria-labelledby="publish-startpartner-title"] .content-cta--primary',
    ['borderRadius', 'minHeight', 'fontSize', 'fontWeight'],
    `${profileName}: Event-Pilot-CTA`,
  );
  await assertNoOverflow(page, `${profileName}: Event-Funnel`);

  // Aktivitäts-Seite: Eignung und Tarife bleiben, kompakter Pilot mit FAQ-Link vor Tippkanal/Ablauf.
  await open(page, '/aktivitaeten/sichtbar-werden/');
  const activityText = await page.locator('body').innerText();
  for (const marker of [
    'Für welche Angebote ist die Aktivitätspräsenz gedacht?',
    'Wähle den passenden Tarif',
    'Startpartner-Pilot',
    '6 Monate kostenlos testen',
    'Was ist der Startpartner-Pilot? Kurz erklärt',
    'Noch nicht der richtige Weg?',
    'Nur etwas vorschlagen',
    'So geht es weiter',
  ]) {
    assert(activityText.includes(marker), `${profileName}: Aktivitäts-Marker fehlt: ${marker}`);
  }
  assert(activityText.indexOf('Wähle den passenden Tarif') < activityText.indexOf('Startpartner-Pilot'), `${profileName}: Activity-Pilot steht nicht nach den Tarifen`);
  assert(activityText.indexOf('Startpartner-Pilot') < activityText.indexOf('Noch nicht der richtige Weg?'), `${profileName}: Activity-Pilot steht nicht vor dem Tippkanal`);
  assert(activityText.indexOf('Noch nicht der richtige Weg?') < activityText.indexOf('So geht es weiter'), `${profileName}: Activity-Tipp/Ablauf-Reihenfolge verändert`);
  assert(!activityText.includes('Begrenzte Startpartnerplätze'), `${profileName}: Activity-Pilot ist noch mit Tippkanal gekoppelt`);
  assert(await page.locator('a[href="/startpartner/?scope=activities"]').count() === 1, `${profileName}: Activity-Startpartner-Scope fehlt`);
  assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner"]').count() === 1, `${profileName}: Activity-Startpartner-FAQ-Link fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner-Pilot anfragen' }).count() === 1, `${profileName}: Activity-Pilot-CTA fehlt`);
  assert(await page.locator('[data-feedback-open="missing"]').count() > 0, `${profileName}: bestehender Aktivitäts-Tippweg fehlt`);
  await assertSameElementStyle(
    page,
    'section[aria-labelledby="activity-presence-plan-title"] .publish-model-copy strong',
    'section[aria-labelledby="activity-presence-startpartner-title"] .publish-model-copy strong',
    ['fontSize', 'fontWeight', 'lineHeight', 'color'],
    `${profileName}: Activity-Pilot-Copy`,
  );
  await assertSameElementStyle(
    page,
    'section[aria-labelledby="activity-presence-plan-title"] .content-cta--primary',
    'section[aria-labelledby="activity-presence-startpartner-title"] .content-cta--primary',
    ['borderRadius', 'minHeight', 'fontSize', 'fontWeight'],
    `${profileName}: Activity-Pilot-CTA`,
  );
  await assertNoOverflow(page, `${profileName}: Aktivitäts-Funnel`);

  // /fuer-veranstalter/ bleibt der bestehende Membership-Funnel; Startpartner übernimmt Event-Scope.
  await open(page, '/fuer-veranstalter/');
  await page.locator('#organizer-membership-form').waitFor({ state: 'visible' });
  assert(await page.getByRole('heading', { name: 'Mitgliedschaft für regelmäßige Veranstaltungen', level: 1 }).count() === 1, `${profileName}: Membership-H1 fehlt`);
  assert(await page.getByText('Andere Ausgangslage?').count() === 1, `${profileName}: bestehender Membership-Alternativbereich fehlt`);
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Membership-Startpartner-Scope fehlt`);
  assert(await page.getByText('Was möchtest du sichtbar machen?').count() === 0, `${profileName}: Provider-Hub ist noch vorhanden`);
  const membershipStyles = await sharedSignature(page);
  await assertNoOverflow(page, `${profileName}: Membership-Funnel`);

  // Gemeinsamer Startpartner-Funnel: Scope vorausgewählt/änderbar, korrekte reguläre Rückwege.
  await open(page, '/startpartner/?scope=events');
  await page.locator('#startpartner-request-form').waitFor({ state: 'visible' });
  const scope = page.locator('#startpartner-scope');
  assert(await scope.inputValue() === 'events', `${profileName}: Event-Scope wurde nicht vorausgewählt`);
  await scope.selectOption('both');
  assert(await scope.inputValue() === 'both', `${profileName}: Scope ist fälschlich gesperrt`);

  const pilotText = await page.locator('body').innerText();
  for (const marker of ['Startpartner-Pilot', '6 Monate kostenlos gemeinsam testen', 'Veranstaltungen', 'Aktivitäten', 'Beides', 'keine Zahlungsart', 'nicht automatisch in einen kostenpflichtigen Tarif umgewandelt']) {
    assert(pilotText.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE')), `${profileName}: Startpartner-Marker fehlt: ${marker}`);
  }
  assert(await page.getByRole('link', { name: 'Regulär Veranstaltungen veröffentlichen' }).count() === 1, `${profileName}: regulärer Event-Rückweg fehlt`);
  assert(await page.getByRole('link', { name: 'Regulär Aktivität sichtbar machen' }).count() === 1, `${profileName}: regulärer Activity-Rückweg fehlt`);
  assert(!pilotText.includes('Zur Auswahl für Veranstalter & Anbieter'), `${profileName}: falscher Provider-Hub-Rückweg bleibt sichtbar`);
  const pilotStyles = await sharedSignature(page);
  assertSame(pilotStyles, membershipStyles, profileName);
  await assertNoOverflow(page, `${profileName}: Startpartner-Funnel`);
  await page.screenshot({ path: path.join(outDir, `startpartner-${profileName}.png`), fullPage: true });

  await open(page, '/startpartner/?scope=activities');
  assert(await page.locator('#startpartner-scope').inputValue() === 'activities', `${profileName}: Activity-Scope wurde nicht vorausgewählt`);

  // Erklärseite trennt reguläre Wege und Pilot-Sonderweg und bleibt zentraler FAQ-Owner.
  await open(page, '/veroeffentlichung-erklaert/');
  const explainerText = await page.locator('body').innerText();
  assert(explainerText.includes('Welcher reguläre Weg passt?'), `${profileName}: reguläre Erklär-Wegwahl fehlt`);
  assert(explainerText.includes('Sonderweg: Startpartner-Pilot'), `${profileName}: separater Startpartner-Sonderweg auf Erklärseite fehlt`);
  assert(explainerText.indexOf('Welcher reguläre Weg passt?') < explainerText.indexOf('Sonderweg: Startpartner-Pilot'), `${profileName}: Erklärseite ordnet Pilot nicht nach regulären Wegen ein`);
  assert(await page.locator('#startpartner').count() === 1, `${profileName}: Startpartner-FAQ-Anker fehlt`);
  await assertNoOverflow(page, `${profileName}: Erklärseite`);

  // Login bleibt Live-kompatibel.
  await open(page, '/fuer-veranstalter/login/');
  const loginText = await page.locator('body').innerText();
  assert(loginText.includes('Status oder Veranstalterbereich öffnen'), `${profileName}: Live-Login-Titel fehlt`);
  assert(loginText.includes('deine Veranstaltung eingereicht oder deine Mitgliedschaft gestartet'), `${profileName}: Live-Login-Kontext fehlt`);
  assert(await page.locator('a[href="/events-veroeffentlichen/"]').count() === 1, `${profileName}: Live-Login-Rückweg fehlt`);
  await assertNoOverflow(page, `${profileName}: Login`);

  assert(formspreeRequests === 0, `${profileName}: Browser-Test hat unerwartet Formspree aufgerufen`);

  await context.close();
  return {
    profile: profileName,
    viewport,
    status: 'OK',
    checkedRoutes: [
      '/events-veroeffentlichen/',
      '/aktivitaeten/sichtbar-werden/',
      '/fuer-veranstalter/',
      '/startpartner/?scope=events',
      '/startpartner/?scope=activities',
      '/veroeffentlichung-erklaert/',
      '/fuer-veranstalter/login/',
    ],
  };
}

const browser = await chromium.launch({ headless: true });
const results = [];
try {
  results.push(await runProfile(browser, 'mobile-390x844', { width: 390, height: 844 }));
  results.push(await runProfile(browser, 'desktop-1366x900', { width: 1366, height: 900 }));
} finally {
  await browser.close();
}

fs.writeFileSync(
  path.join(outDir, 'startpartner-public-funnel-summary.json'),
  `${JSON.stringify({ status: 'OK', results }, null, 2)}\n`,
  'utf8',
);
console.log('Startpartner compact FAQ live-parity browser contract: OK');