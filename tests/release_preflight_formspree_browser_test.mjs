import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args = process.argv.slice(2);
const arg = (name) => {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : '';
};
const baseUrl = String(arg('--base-url') || '').replace(/\/+$/, '');
const outDir = arg('--out-dir');
if (!baseUrl || !outDir) process.exit(2);
fs.mkdirSync(outDir, { recursive: true });

const FORMSPREE = 'https://formspree.io/f/mrerpwjy';
const assert = (condition, message) => {
  if (!condition) throw new Error(message);
};
const esc = (value) => String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const hasField = (raw, name, value) => new RegExp(
  `name="${esc(name)}"\\r?\\n\\r?\\n${esc(value)}(?:\\r?\\n|$)`,
).test(raw);
const hasName = (raw, name) => raw.includes(`name="${name}"`);
const assertField = (raw, name, value, label) => assert(
  hasField(raw, name, value),
  `${label}: Feld ${name}=${value} fehlt`,
);
const assertContains = (raw, name, fragment, label) => {
  assert(hasName(raw, name), `${label}: Feld ${name} fehlt`);
  assert(raw.includes(fragment), `${label}: Wertteil ${fragment} für ${name} fehlt`);
};
const assertAbsent = (raw, name, label) => assert(
  !hasName(raw, name),
  `${label}: bewusst gepruntes Feld ${name} wurde unerwartet gesendet`,
);

async function open(page, routePath) {
  const response = await page.goto(`${baseUrl}${routePath}`, {
    waitUntil: 'networkidle',
    timeout: 18000,
  });
  assert(response?.status() === 200, `${routePath}: HTTP 200 fehlt`);
}

function installFormspreeIntercept(page, requests, getMode) {
  return page.route('https://formspree.io/**', async (route) => {
    const request = route.request();
    assert(request.method() === 'POST', `unerwartete Formspree-Methode ${request.method()}`);
    const mode = getMode();
    requests.push({ mode, url: request.url(), raw: request.postData() || '' });
    const status = mode === 'success' ? 200 : mode === 'rate-limit' ? 429 : 500;
    await route.fulfill({
      status,
      headers: {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
      },
      body: JSON.stringify(status === 200 ? { ok: true } : { errors: [] }),
    });
  });
}

async function testStartpartner(browser, profile, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const requests = [];
  let mode = 'success';
  await installFormspreeIntercept(page, requests, () => mode);

  await open(page, '/startpartner/?scope=events');
  const submit = page.locator('#startpartner-request-submit');

  await submit.click();
  await page.locator('[data-startpartner-validation-status]').waitFor({ state: 'visible' });
  assert(
    (await page.locator('[data-startpartner-validation-status]').innerText())
      .includes('Bitte fülle die markierten Pflichtfelder aus.'),
    `${profile}: Startpartner-Pflichtfeldhinweis fehlt`,
  );
  assert(requests.length === 0, `${profile}: ungültige Startpartner-Anfrage löste Request aus`);

  await page.locator('#startpartner-organization').fill('Release Preflight 283');
  await page.locator('#startpartner-email').fill('release-preflight-283@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetischer Release-Preflight ohne externe Zustellung.');
  await page.locator('#startpartner-privacy-confirmed').check();
  await submit.click();
  await page.locator('#startpartner-request-result').waitFor({ state: 'visible' });
  assert(
    (await page.locator('#startpartner-request-result-text').innerText())
      .includes('Deine Anfrage zum Startpartner-Pilot ist angekommen.'),
    `${profile}: Startpartner-Erfolgsmeldung fehlt`,
  );
  assert(requests.length === 1, `${profile}: Startpartner-Erfolg erzeugte nicht genau einen Request`);
  const payload = requests[0].raw;
  assert(requests[0].url === FORMSPREE, `${profile}: falscher Startpartner-Endpunkt`);
  assertField(payload, 'pilot_scope', 'events', `${profile}: Startpartner`);
  assertField(payload, 'organization', 'Release Preflight 283', `${profile}: Startpartner`);
  assertField(payload, 'email', 'release-preflight-283@example.invalid', `${profile}: Startpartner`);
  assertField(payload, 'message', 'Synthetischer Release-Preflight ohne externe Zustellung.', `${profile}: Startpartner`);
  assertField(payload, 'privacy_confirmed', 'on', `${profile}: Startpartner`);
  assertField(payload, 'lead_type', 'startpartner_6_months_limited', `${profile}: Startpartner`);
  assertField(payload, 'source_label', 'bocholt-erleben-startpartner', `${profile}: Startpartner`);
  assertContains(payload, 'page_url', '/startpartner/?scope=events', `${profile}: Startpartner`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profile}: Scope nach Erfolg falsch`);
  assert(await page.locator('#startpartner-organization').inputValue() === '', `${profile}: Formular nach Erfolg nicht geleert`);

  mode = 'error';
  await page.locator('#startpartner-organization').fill('Release Preflight Fehler');
  await page.locator('#startpartner-email').fill('release-preflight-error@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetischer Fehlerpfad ohne externe Zustellung.');
  await page.locator('#startpartner-privacy-confirmed').check();
  await submit.click();
  await page.locator('#startpartner-request-result').waitFor({ state: 'visible' });
  assert(
    (await page.locator('#startpartner-request-result-text').innerText())
      .includes('Die Anfrage konnte gerade nicht gesendet werden.'),
    `${profile}: Startpartner-Fehlermeldung fehlt`,
  );
  assert(requests.length === 2, `${profile}: Startpartner-Fehlerpfad erzeugte falsche Requestzahl`);
  assert(await submit.isEnabled(), `${profile}: Startpartner-Submit nach Fehler deaktiviert`);
  assert(
    await page.locator('#startpartner-organization').inputValue() === 'Release Preflight Fehler',
    `${profile}: Startpartner-Eingaben wurden bei Fehler gelöscht`,
  );

  await page.screenshot({ path: path.join(outDir, `startpartner-${profile}.png`), fullPage: true });
  await context.close();
}

async function openMissingFeedback(page) {
  await open(page, '/events-veroeffentlichen/');
  const trigger = page.locator('footer[data-site-footer] [data-feedback-open="missing"]');
  await trigger.waitFor({ state: 'visible', timeout: 8000 });
  await trigger.click();
  const form = page.locator('.feedback-form');
  await form.waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await form.locator('[data-feedback-prompt]').innerText()).includes('Was fehlt dir aktuell?'),
    'Feedback: sichtbare Missing-Vorauswahl fehlt',
  );
  return form;
}

async function testFeedback(browser, profile, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const requests = [];
  let mode = 'success';
  await installFormspreeIntercept(page, requests, () => mode);

  let form = await openMissingFeedback(page);
  await form.locator('button[type="submit"]').click();
  assert(requests.length === 0, `${profile}: leeres Feedback löste Request aus`);
  assert(
    await form.locator('[data-feedback-error="message"]').innerText() !== '',
    `${profile}: Feedback-Pflichtfeldfehler fehlt`,
  );

  await form.locator('[name="message"]').fill('Release Preflight 283: fehlendes Event, synthetisch getestet.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-success-card').waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await page.locator('.feedback-success-card').innerText()).includes('Feedback gesendet'),
    `${profile}: Feedback-Erfolgsmeldung fehlt`,
  );
  assert(requests.length === 1, `${profile}: Feedback-Erfolg erzeugte nicht genau einen Request`);

  const payload = requests[0].raw;
  assert(requests[0].url === FORMSPREE, `${profile}: falscher Feedback-Endpunkt`);
  assertField(payload, 'feedback_type_label', 'Etwas fehlt', `${profile}: Feedback`);
  assertField(payload, 'page_type', 'publish', `${profile}: Feedback`);
  assertField(payload, 'message', 'Release Preflight 283: fehlendes Event, synthetisch getestet.', `${profile}: Feedback`);
  assertContains(payload, 'page_url', '/events-veroeffentlichen/', `${profile}: Feedback`);
  assertContains(payload, 'subject', 'Etwas fehlt', `${profile}: Feedback`);
  for (const pruned of ['source_label', 'feedback_type', 'route', 'viewport', 'submitted_at']) {
    assertAbsent(payload, pruned, `${profile}: Feedback`);
  }

  mode = 'rate-limit';
  form = await openMissingFeedback(page);
  await form.locator('[name="message"]').fill('Release Preflight 283: synthetischer Rate-Limit-Test.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-form__status.is-error').waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await page.locator('.feedback-form__status.is-error').innerText())
      .includes('Das Feedback-Limit ist aktuell erreicht.'),
    `${profile}: Feedback-429-Hinweis fehlt`,
  );
  assert(await form.locator('button[type="submit"]').isEnabled(), `${profile}: Feedback-Submit nach 429 deaktiviert`);

  mode = 'error';
  form = await openMissingFeedback(page);
  await form.locator('[name="message"]').fill('Release Preflight 283: synthetischer Serverfehler-Test.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-form__status.is-error').waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await page.locator('.feedback-form__status.is-error').innerText())
      .includes('Absenden hat nicht geklappt. Bitte später erneut versuchen.'),
    `${profile}: Feedback-500-Hinweis fehlt`,
  );
  assert(await form.locator('button[type="submit"]').isEnabled(), `${profile}: Feedback-Submit nach 500 deaktiviert`);
  assert(requests.length === 3, `${profile}: Feedback-Test erzeugte insgesamt nicht genau drei Requests`);

  await page.screenshot({ path: path.join(outDir, `feedback-${profile}.png`), fullPage: true });
  await context.close();
}

const browser = await chromium.launch({ headless: true });
try {
  for (const [profile, viewport] of [
    ['mobile-390x844', { width: 390, height: 844 }],
    ['desktop-1440x1000', { width: 1440, height: 1000 }],
  ]) {
    await testStartpartner(browser, profile, viewport);
    await testFeedback(browser, profile, viewport);
    console.log(`Release-preflight Formspree browser contract: ${profile} OK`);
  }
  console.log('=== Release-preflight Formspree Browser Contract: OK ===');
} finally {
  await browser.close();
}
