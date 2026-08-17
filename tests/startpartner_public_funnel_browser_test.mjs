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

async function styleSignature(page, selector, properties) {
  const locator = page.locator(selector).first();
  await locator.waitFor({ state: 'visible', timeout: 8000 });
  return locator.evaluate((element, props) => {
    const styles = getComputedStyle(element);
    return Object.fromEntries(props.map((property) => [property, styles[property]]));
  }, properties);
}

async function pageSignature(page) {
  return {
    hero: await styleSignature(page, '.content-hero--panel', [
      'borderRadius',
      'boxShadow',
      'paddingTop',
      'paddingRight',
      'paddingBottom',
      'paddingLeft',
    ]),
    primaryCard: await styleSignature(page, '.content-card--primary', [
      'borderRadius',
      'backgroundColor',
      'borderTopColor',
      'boxShadow',
    ]),
    primaryCta: await styleSignature(page, '.content-cta--primary', [
      'borderRadius',
      'minHeight',
      'backgroundColor',
      'borderTopColor',
      'boxShadow',
      'paddingLeft',
      'paddingRight',
    ]),
    formControl: await styleSignature(page, '.content-field__control', [
      'borderRadius',
      'backgroundColor',
      'borderTopColor',
      'fontSize',
    ]),
  };
}

function assertEqualSignature(actual, expected, label) {
  for (const [component, actualProps] of Object.entries(actual)) {
    const expectedProps = expected[component];
    for (const [property, actualValue] of Object.entries(actualProps)) {
      const expectedValue = expectedProps[property];
      assert(
        actualValue === expectedValue,
        `${label}: ${component}.${property} weicht vom gemeinsamen Funnel-Primitive ab: ${actualValue} != ${expectedValue}`,
      );
    }
  }
}

async function open(page, routePath) {
  const response = await page.goto(`${baseUrl}${routePath}`, { waitUntil: 'networkidle', timeout: 18000 });
  assert(response && response.status() === 200, `${routePath}: erwarteter HTTP 200 fehlt`);
}

async function runProfile(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  let formspreeRequests = 0;

  await page.route('https://formspree.io/**', async (route) => {
    formspreeRequests += 1;
    await route.abort();
  });

  await open(page, '/startpartner/');
  await page.locator('main.page--startpartner').waitFor({ state: 'visible' });
  await page.locator('#startpartner-request-form').waitFor({ state: 'visible' });
  await page.locator('#startpartner-request-submit').waitFor({ state: 'visible' });

  const bodyText = await page.locator('body').innerText();
  for (const marker of [
    'Startpartner-Pilot',
    'Kostenlose sechsmonatige Pilotphase',
    'nach sechs Monaten gemeinsam entscheiden',
    'Startpartnerplatz anfragen',
  ]) {
    assert(bodyText.toLocaleLowerCase('de-DE').includes(marker.toLocaleLowerCase('de-DE')), `${profileName}: sichtbarer Marker fehlt: ${marker}`);
  }

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  assert(!overflow, `${profileName}: Startpartner-Funnel hat horizontalen Überlauf`);

  const stylesheetHrefs = await page.locator('link[rel="stylesheet"]').evaluateAll((nodes) => nodes.map((node) => node.getAttribute('href')));
  assert(stylesheetHrefs.length === 1, `${profileName}: Startpartner-Funnel lädt mehr als einen CSS-Entry-Point`);
  assert(String(stylesheetHrefs[0] || '').startsWith('/css/style.css?'), `${profileName}: Startpartner-Funnel nutzt nicht den zentralen CSS-Entry-Point`);

  const startpartnerStyles = await pageSignature(page);
  await page.screenshot({ path: path.join(outDir, `startpartner-${profileName}.png`), fullPage: true });

  await open(page, '/fuer-veranstalter/');
  const organizerStyles = await pageSignature(page);
  assertEqualSignature(startpartnerStyles, organizerStyles, profileName);

  assert(formspreeRequests === 0, `${profileName}: Browser-Contract hat unerwartet Formspree aufgerufen`);

  await context.close();
  return {
    profile: profileName,
    viewport,
    status: 'OK',
    compared: ['hero', 'primaryCard', 'primaryCta', 'formControl'],
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
console.log('Startpartner public funnel browser contract: OK');
