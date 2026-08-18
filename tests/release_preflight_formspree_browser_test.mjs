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
  console.error('Usage: node tests/release_preflight_formspree_browser_test.mjs --base-url URL --out-dir DIR');
  process.exit(2);
}

fs.mkdirSync(outDir, { recursive: true });

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function escapeRegex(value) {
  return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function assertMultipartField(raw, name, expectedValue, label) {
  const pattern = new RegExp(
    `name="${escapeRegex(name)}"\\r?\\n\\r?\\n${escapeRegex(expectedValue)}(?:\\r?\\n|$)`,
  );
  assert(pattern.test(raw), `${label}: Feld ${name}=${expectedValue} fehlt im Formspree-Payload`);
}

function assertMultipartContains(raw, name, expectedFragment, label) {
  assert(raw.includes(`name="${name}"`), `${label}: Feld ${name} fehlt im Formspree-Payload`);
  assert(raw.includes(expectedFragment), `${label}: erwarteter Wertteil für ${name} fehlt: ${expectedFragment}`);
}

async function open(page, routePath) {
  const response = await page.goto(`${baseUrl}${routePath}`, {
    waitUntil: 'networkidle',
    timeout: 18000,
  });
  assert(response && response.status() === 200, `${routePath}: erwarteter HTTP 200 fehlt`);
}

async function testStartpartner(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const requests = [];
  let responseMode = 'success';

  await page.route('https://formspree.io/**', async (route) => {
    const request = route.request();
    assert(request.method() === 'POST', `${profileName}: unerwartete Formspree-Methode ${request.method()}`);
    requests.push({
      mode: responseMode,
      url: request.url(),
      raw: request.postData() || '',
    });

    const status = responseMode === 'success' ? 200 : 500;
    await route.fulfill({
      status,
      headers: {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
      },
      body: JSON.stringify(status === 200 ? { ok: true } : { error: 'synthetic-preflight' }),
    });
  });

  await open(page, '/startpartner/?scope=events');
  const form = page.locator('#startpartner-request-form');
  await form.waitFor({ state: 'visible' });

  // Pflichtfeldvalidierung muss vor dem Netzwerk stoppen.
  await page.locator('#startpartner-request-submit').click();
  await page.locator('[data-startpartner-validation-status]').waitFor({ state: 'visible' });
  assert(
    (await page.locator('[data-startpartner-validation-status]').innerText()).includes('Bitte fülle die markierten Pflichtfelder aus.'),
    `${profileName}: Startpartner-Pflichtfeldhinweis fehlt`,
  );
  assert(requests.length === 0, `${profileName}: ungültige Startpartner-Anfrage hat Formspree aufgerufen`);

  // Erfolgsweg: echter Browsercode, aber Formspree wird vor dem Internet abgefangen.
  await page.locator('#startpartner-organization').fill('Release Preflight 283');
  await page.locator('#startpartner-email').fill('release-preflight-283@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetischer Release-Preflight ohne externe Zustellung.');
  await page.locator('#startpartner-privacy-confirmed').check();
  await page.locator('#startpartner-request-submit').click();

  await page.locator('#startpartner-request-result').waitFor({ state: 'visible' });
  assert(
    (await page.locator('#startpartner-request-result-text').innerText()).includes('Deine Anfrage zum Startpartner-Pilot ist angekommen.'),
    `${profileName}: Startpartner-Erfolgsmeldung fehlt`,
  );
  assert(requests.length === 1, `${profileName}: Startpartner-Erfolgsweg erzeugte ${requests.length} statt 1 Request`);

  const successPayload = requests[0].raw;
  assert(requests[0].url === 'https://formspree.io/f/mrerpwjy', `${profileName}: falscher Startpartner-Formspree-Endpunkt`);
  assertMultipartField(successPayload, 'pilot_scope', 'events', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'organization', 'Release Preflight 283', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'email', 'release-preflight-283@example.invalid', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'message', 'Synthetischer Release-Preflight ohne externe Zustellung.', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'privacy_confirmed', 'on', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'lead_type', 'startpartner_6_months_limited', `${profileName}: Startpartner Erfolg`);
  assertMultipartField(successPayload, 'source_label', 'bocholt-erleben-startpartner', `${profileName}: Startpartner Erfolg`);
  assertMultipartContains(successPayload, 'page_url', '/startpartner/?scope=events', `${profileName}: Startpartner Erfolg`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profileName}: Scope wurde nach Erfolg nicht aus URL wiederhergestellt`);
  assert(await page.locator('#startpartner-organization').inputValue() === '', `${profileName}: Formular wurde nach Erfolg nicht zurückgesetzt`);

  // Fehlerweg: Felder bleiben nutzbar und es wird eine verständliche Meldung gezeigt.
  responseMode = 'error';
  await page.locator('#startpartner-organization').fill('Release Preflight Fehler');
  await page.locator('#startpartner-email').fill('release-preflight-error@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetischer Fehlerpfad ohne externe Zustellung.');
  await page.locator('#startpartner-privacy-confirmed').check();
  await page.locator('#startpartner-request-submit').click();

  await page.locator('#startpartner-request-result').waitFor({ state: 'visible' });
  assert(
    (await page.locator('#startpartner-request-result-text').innerText()).includes('Die Anfrage konnte gerade nicht gesendet werden.'),
    `${profileName}: Startpartner-Fehlermeldung fehlt`,
  );
  assert(requests.length === 2, `${profileName}: Startpartner-Fehlerweg erzeugte ${requests.length} statt insgesamt 2 Requests`);
  assert(await page.locator('#startpartner-request-submit').isEnabled(), `${profileName}: Startpartner-Submit bleibt nach Fehler deaktiviert`);
  assert(await page.locator('#startpartner-organization').inputValue() === 'Release Preflight Fehler', `${profileName}: Startpartner-Fehlerweg hat Eingaben unerwartet gelöscht`);

  await page.screenshot({ path: path.join(outDir, `formspree-startpartner-${profileName}.png`), fullPage: true });
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

async function testFeedback(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const requests = [];
  let responseMode = 'success';

  await page.route('https://formspree.io/**', async (route) => {
    const request = route.request();
    assert(request.method() === 'POST', `${profileName}: unerwartete Feedback-Formspree-Methode ${request.method()}`);
    requests.push({ mode: responseMode, url: request.url(), raw: request.postData() || '' });

    const status = responseMode === 'success' ? 200 : responseMode === 'rate-limit' ? 429 : 500;
    await route.fulfill({
      status,
      headers: {
        'content-type': 'application/json',
        'access-control-allow-origin': '*',
      },
      body: JSON.stringify(status === 200 ? { ok: true } : { errors: [] }),
    });
  });

  // Validierung ohne Netzaufruf.
  let form = await openMissingFeedback(page);
  await form.locator('button[type="submit"]').click();
  assert(requests.length === 0, `${profileName}: leeres Feedback hat Formspree aufgerufen`);
  assert(await form.locator('[data-feedback-error="message"]').innerText() !== '', `${profileName}: Feedback-Pflichtfeldfehler fehlt`);

  // Erfolg inklusive Payload.
  await form.locator('[name="message"]').fill('Release Preflight 283: fehlendes Event, synthetisch getestet.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-success-card').waitFor({ state: 'visible', timeout: 8000 });
  assert((await page.locator('.feedback-success-card').innerText()).includes('Feedback gesendet'), `${profileName}: Feedback-Erfolgsmeldung fehlt`);
  assert(requests.length === 1, `${profileName}: Feedback-Erfolgsweg erzeugte ${requests.length} statt 1 Request`);

  const successPayload = requests[0].raw;
  assert(requests[0].url === 'https://formspree.io/f/mrerpwjy', `${profileName}: falscher Feedback-Formspree-Endpunkt`);
  assertMultipartField(successPayload, 'feedback_type', 'missing', `${profileName}: Feedback Erfolg`);
  assertMultipartField(successPayload, 'feedback_type_label', 'Etwas fehlt', `${profileName}: Feedback Erfolg`);
  assertMultipartField(successPayload, 'message', 'Release Preflight 283: fehlendes Event, synthetisch getestet.', `${profileName}: Feedback Erfolg`);
  assertMultipartContains(successPayload, 'page_url', '/events-veroeffentlichen/', `${profileName}: Feedback Erfolg`);
  assertMultipartField(successPayload, 'route', '/events-veroeffentlichen/', `${profileName}: Feedback Erfolg`);

  // 429 muss den speziellen Rate-Limit-Hinweis zeigen.
  responseMode = 'rate-limit';
  form = await openMissingFeedback(page);
  await form.locator('[name="message"]').fill('Release Preflight 283: synthetischer Rate-Limit-Test.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-form__status.is-error').waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await page.locator('.feedback-form__status.is-error').innerText()).includes('Das Feedback-Limit ist aktuell erreicht.'),
    `${profileName}: Feedback-429-Hinweis fehlt`,
  );
  assert(await form.locator('button[type="submit"]').isEnabled(), `${profileName}: Feedback-Submit bleibt nach 429 deaktiviert`);

  // Generischer Serverfehler.
  responseMode = 'error';
  form = await openMissingFeedback(page);
  await form.locator('[name="message"]').fill('Release Preflight 283: synthetischer Serverfehler-Test.');
  await form.locator('button[type="submit"]').click();
  await page.locator('.feedback-form__status.is-error').waitFor({ state: 'visible', timeout: 8000 });
  assert(
    (await page.locator('.feedback-form__status.is-error').innerText()).includes('Absenden hat nicht geklappt. Bitte später erneut versuchen.'),
    `${profileName}: generische Feedback-Fehlermeldung fehlt`,
  );
  assert(await form.locator('button[type="submit"]').isEnabled(), `${profileName}: Feedback-Submit bleibt nach 500 deaktiviert`);
  assert(requests.length === 3, `${profileName}: Feedback-Test erwartete insgesamt 3 Formspree-Requests, bekam ${requests.length}`);

  await page.screenshot({ path: path.join(outDir, `formspree-feedback-${profileName}.png`), fullPage: true });
  await context.close();
}

const browser = await chromium.launch({ headless: true });
try {
  const profiles = [
    ['mobile-390x844', { width: 390, height: 844 }],
    ['desktop-1440x1000', { width: 1440, height: 1000 }],
  ];

  for (const [profileName, viewport] of profiles) {
    await testStartpartner(browser, profileName, viewport);
    await testFeedback(browser, profileName, viewport);
    console.log(`Release-preflight Formspree browser contract: ${profileName} OK`);
  }

  console.log('=== Release-preflight Formspree Browser Contract: OK ===');
} finally {
  await browser.close();
}
