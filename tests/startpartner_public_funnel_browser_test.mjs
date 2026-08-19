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

async function formFunnelSignature(page) {
  return {
    hero: await styleSignature(page, '.content-hero--panel', ['borderRadius', 'boxShadow', 'paddingTop', 'paddingLeft']),
    primaryCta: await styleSignature(page, '.content-cta--primary', ['borderRadius', 'minHeight', 'fontSize', 'fontWeight']),
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

async function assertCanonicalNaming(page, label) {
  const text = await page.locator('body').innerText();
  assert(!text.includes('Startpartner-Pilot'), `${label}: alte sichtbare Produktbezeichnung Startpartner-Pilot`);
  assert(!text.includes('Startpartnerplatz'), `${label}: alte sichtbare Produktbezeichnung Startpartnerplatz`);
  return text;
}

async function runProfile(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  let formspreeRequests = 0;

  await page.route('https://formspree.io/**', async (route) => {
    formspreeRequests += 1;
    await route.abort();
  });

  // Referenz: bestehender Publish-Funnel mit Anfrageformular.
  await open(page, '/events-veroeffentlichen/anbindung/');
  const referenceSignature = await formFunnelSignature(page);
  await assertNoOverflow(page, `${profileName}: Anfrage-Referenz`);

  // Event-Funnel: reguläre Wege bleiben, Startpartner ist konsistent benannt.
  await open(page, '/events-veroeffentlichen/');
  const eventText = await assertCanonicalNaming(page, `${profileName}: Event-Funnel`);
  for (const marker of [
    'Wähle den passenden Veröffentlichungsweg',
    'Einzelne Veranstaltung einreichen',
    'Mitgliedschaft für regelmäßige Termine',
    'Automatische Übernahme prüfen',
    'Startpartner',
    '6 Monate kostenlos testen',
    'Wie funktioniert Startpartner? Kurz erklärt',
  ]) {
    assert(eventText.includes(marker), `${profileName}: Event-Marker fehlt: ${marker}`);
  }
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Event-Startpartner-Scope fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Event-Startpartner-CTA fehlt`);
  assert(eventText.indexOf('Wähle den passenden Veröffentlichungsweg') < eventText.lastIndexOf('Startpartner'), `${profileName}: Startpartner steht nicht nach regulären Event-Wegen`);
  await assertNoOverflow(page, `${profileName}: Event-Funnel`);

  // Aktivitäts-Funnel: Tarifstruktur bleibt, Startpartner ist konsistent benannt.
  await open(page, '/aktivitaeten/sichtbar-werden/');
  const activityText = await assertCanonicalNaming(page, `${profileName}: Aktivitäts-Funnel`);
  for (const marker of [
    'Für welche Angebote ist die Aktivitätspräsenz gedacht?',
    'Wähle den passenden Tarif',
    'Startpartner',
    '6 Monate kostenlos testen',
    'Wie funktioniert Startpartner? Kurz erklärt',
    'So geht es weiter',
  ]) {
    assert(activityText.includes(marker), `${profileName}: Aktivitäts-Marker fehlt: ${marker}`);
  }
  assert(await page.locator('a[href="/startpartner/?scope=activities"]').count() === 1, `${profileName}: Activity-Startpartner-Scope fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Activity-Startpartner-CTA fehlt`);
  assert(activityText.indexOf('Wähle den passenden Tarif') < activityText.indexOf('Startpartner'), `${profileName}: Startpartner steht nicht nach Tarifen`);
  assert(activityText.indexOf('Startpartner') < activityText.indexOf('So geht es weiter'), `${profileName}: Startpartner steht nicht vor Ablauf`);
  await assertNoOverflow(page, `${profileName}: Aktivitäts-Funnel`);

  // Membership-Funnel: bestehender Weg bleibt, sekundärer Startpartner-Einstieg ist sprachlich konsistent.
  await open(page, '/fuer-veranstalter/');
  const membershipText = await assertCanonicalNaming(page, `${profileName}: Membership-Funnel`);
  assert(membershipText.includes('Mitgliedschaft für regelmäßige Veranstaltungen'), `${profileName}: Membership-H1 fehlt`);
  assert(membershipText.includes('Andere Ausgangslage?'), `${profileName}: Membership-Alternativbereich fehlt`);
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Membership-Startpartner-Scope fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Membership-Startpartner-CTA fehlt`);
  await assertNoOverflow(page, `${profileName}: Membership-Funnel`);

  // Startpartner: Hero -> Anfrage -> reguläre Alternativen, ohne Kicker und redundante Erklärblöcke.
  await open(page, '/startpartner/?scope=events');
  await page.locator('#startpartner-request-form').waitFor({ state: 'visible' });
  const startpartnerText = await assertCanonicalNaming(page, `${profileName}: Startpartner`);
  assert(await page.getByRole('heading', { level: 1, name: '6 Monate kostenlos testen' }).count() === 1, `${profileName}: kompakte Startpartner-H1 fehlt`);
  assert(await page.locator('.content-kicker').count() === 0, `${profileName}: Kicker darf nicht vorhanden sein`);
  assert(!startpartnerText.includes('Was kann der Pilot umfassen?'), `${profileName}: redundanter Scope-Block sichtbar`);
  assert(!startpartnerText.includes('So läuft der Start ab'), `${profileName}: redundanter Ablaufblock sichtbar`);
  assert(await page.getByRole('heading', { level: 2, name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Formulartitel fehlt`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profileName}: Event-Scope nicht vorausgewählt`);
  await page.locator('#startpartner-scope').selectOption('both');
  assert(await page.locator('#startpartner-scope').inputValue() === 'both', `${profileName}: Scope ist nicht änderbar`);
  assert(await page.getByRole('link', { name: 'Wie funktioniert Startpartner? Kurz erklärt' }).count() === 1, `${profileName}: zentraler Erklärlink fehlt`);

  const regularSection = page.locator('section[aria-labelledby="startpartner-regular-paths-title"]');
  assert(await regularSection.count() === 1, `${profileName}: Bereich für reguläre Alternativen fehlt`);
  assert(await regularSection.locator('.publish-model-list > li').count() === 2, `${profileName}: genau zwei reguläre Alternativen erwartet`);
  assert(await regularSection.locator('a[href="/events-veroeffentlichen/"]').count() === 1, `${profileName}: Event-Rückweg fehlt`);
  assert(await regularSection.locator('a[href="/aktivitaeten/sichtbar-werden/"]').count() === 1, `${profileName}: Activity-Rückweg fehlt`);
  assert(await regularSection.getByRole('link', { name: 'Zu den Veranstaltungswegen' }).count() === 1, `${profileName}: Event-Alternative nicht als CTA gerendert`);
  assert(await regularSection.getByRole('link', { name: 'Zu den Aktivitäts-Tarifen' }).count() === 1, `${profileName}: Activity-Alternative nicht als CTA gerendert`);

  const startpartnerSignature = await formFunnelSignature(page);
  assertSame(startpartnerSignature, referenceSignature, `${profileName}: Startpartner/Publish-Form-Konsistenz`);
  await assertNoOverflow(page, `${profileName}: Startpartner`);
  await page.screenshot({ path: path.join(outDir, `startpartner-${profileName}.png`), fullPage: true });

  await open(page, '/startpartner/?scope=activities');
  assert(await page.locator('#startpartner-scope').inputValue() === 'activities', `${profileName}: Activity-Scope nicht vorausgewählt`);

  // Detailwissen liegt nur auf der Erklärseite.
  await open(page, '/veroeffentlichung-erklaert/#startpartner');
  const explainerText = await assertCanonicalNaming(page, `${profileName}: Erklärseite`);
  assert(explainerText.includes('Sonderweg: Startpartner'), `${profileName}: Startpartner-Sonderweg fehlt`);
  assert(explainerText.includes('sechs Monate kostenlos'), `${profileName}: Sechs-Monats-Erklärung fehlt`);
  assert(explainerText.includes('keine Zahlungsart'), `${profileName}: Zahlungsart-Ausschluss fehlt`);
  assert(explainerText.includes('nicht automatisch in einen kostenpflichtigen Tarif umgewandelt'), `${profileName}: Auto-Umwandlungs-Ausschluss fehlt`);
  const details = page.locator('details#startpartner');
  assert(await details.count() === 1, `${profileName}: Startpartner-FAQ fehlt`);
  await page.waitForFunction(() => document.querySelector('details#startpartner')?.open === true, null, { timeout: 4000 });
  await assertNoOverflow(page, `${profileName}: Erklärseite`);

  assert(formspreeRequests === 0, `${profileName}: Browser-Test hat unerwartet Formspree aufgerufen`);

  await context.close();
  return {
    profile: profileName,
    viewport,
    status: 'OK',
    checkedRoutes: [
      '/events-veroeffentlichen/anbindung/',
      '/events-veroeffentlichen/',
      '/aktivitaeten/sichtbar-werden/',
      '/fuer-veranstalter/',
      '/startpartner/?scope=events',
      '/startpartner/?scope=activities',
      '/veroeffentlichung-erklaert/#startpartner',
    ],
  };
}

const browser = await chromium.launch({ headless: true });
const results = [];
try {
  results.push(await runProfile(browser, 'mobile-390x844', { width: 390, height: 844 }));
  results.push(await runProfile(browser, 'desktop-1280x900', { width: 1280, height: 900 }));
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outDir, 'summary.json'), `${JSON.stringify(results, null, 2)}\n`, 'utf8');
console.log(JSON.stringify({ status: 'OK', profiles: results.map((item) => item.profile) }));
