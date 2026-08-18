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
  console.error('Usage: node tests/provider_funnel_release_acceptance_browser_test.mjs --base-url URL --out-dir DIR');
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

function includesMultipart(body, name, value) {
  return body.includes(`name="${name}"`) && body.includes(String(value));
}

async function runProfile(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const interceptedRequests = [];
  let mockMode = 'none';

  await page.route('https://formspree.io/**', async (route) => {
    const request = route.request();
    const body = (await request.postDataBuffer())?.toString('utf8') || '';
    interceptedRequests.push({ mode: mockMode, url: request.url(), method: request.method(), body });

    if (mockMode === 'startpartner-success' || mockMode === 'feedback-success') {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ ok: true }) });
      return;
    }
    if (mockMode === 'startpartner-error') {
      await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: 'synthetic_failure' }) });
      return;
    }
    if (mockMode === 'feedback-error') {
      await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ errors: [{ message: 'Synthetischer Feedback-Fehler' }] }) });
      return;
    }

    await route.abort('blockedbyclient');
    throw new Error(`${profileName}: unerwarteter externer Formspree-Request in Modus ${mockMode}`);
  });

  await open(page, '/events-veroeffentlichen/');
  await Promise.all([
    page.waitForURL(/\/startpartner\/\?scope=events$/, { timeout: 8000 }),
    page.getByRole('link', { name: 'Startpartner-Pilot anfragen' }).click(),
  ]);
  const eventScope = page.locator('#startpartner-scope');
  await eventScope.waitFor({ state: 'visible' });
  assert(await eventScope.inputValue() === 'events', `${profileName}: Event-CTA setzt Scope nicht auf events`);
  await eventScope.selectOption('both');
  assert(await eventScope.inputValue() === 'both', `${profileName}: Event-Scope ist nicht änderbar`);
  await assertNoOverflow(page, `${profileName}: Event -> Startpartner`);

  await open(page, '/aktivitaeten/sichtbar-werden/');
  await Promise.all([
    page.waitForURL(/\/startpartner\/\?scope=activities$/, { timeout: 8000 }),
    page.getByRole('link', { name: 'Startpartner-Pilot anfragen' }).click(),
  ]);
  const activityScope = page.locator('#startpartner-scope');
  await activityScope.waitFor({ state: 'visible' });
  assert(await activityScope.inputValue() === 'activities', `${profileName}: Activity-CTA setzt Scope nicht auf activities`);
  await assertNoOverflow(page, `${profileName}: Activity -> Startpartner`);

  await open(page, '/events-veroeffentlichen/');
  await Promise.all([
    page.waitForURL(/\/veroeffentlichung-erklaert\/#startpartner$/, { timeout: 8000 }),
    page.getByRole('link', { name: 'Was ist der Startpartner-Pilot? Kurz erklärt' }).click(),
  ]);
  const faqTarget = page.locator('#startpartner');
  await faqTarget.waitFor({ state: 'visible' });
  assert(await faqTarget.count() === 1, `${profileName}: FAQ-Zielanker #startpartner fehlt`);
  await assertNoOverflow(page, `${profileName}: Startpartner-FAQ`);

  await open(page, '/events-veroeffentlichen/');
  const missingTrigger = page.locator('footer[data-site-footer] [data-feedback-open="missing"]');
  await missingTrigger.waitFor({ state: 'visible', timeout: 8000 });
  await missingTrigger.click();
  const feedbackShell = page.locator('.feedback-modal-shell');
  await feedbackShell.waitFor({ state: 'visible' });
  const missingChip = feedbackShell.locator('[data-feedback-type="missing"]');
  assert(await missingChip.getAttribute('aria-checked') === 'true', `${profileName}: Missing-Typ wurde nicht vorausgewählt`);
  assert((await feedbackShell.locator('[data-feedback-prompt]').innerText()).includes('Was fehlt dir aktuell?'), `${profileName}: Missing-Prompt fehlt`);

  const feedbackMessage = feedbackShell.locator('[name="message"]');
  await feedbackMessage.fill('zu kurz');
  const beforeMissingValidation = interceptedRequests.length;
  await feedbackShell.locator('button[type="submit"]').click();
  assert(interceptedRequests.length === beforeMissingValidation, `${profileName}: ungültiges Feedback wurde gesendet`);
  assert((await feedbackShell.locator('[data-feedback-error="message"]').innerText()).includes('mindestens'), `${profileName}: Feedback-Mindestlängenfehler fehlt`);

  await feedbackMessage.fill('Ein synthetischer fehlender Termin für den Release-Acceptance-Test.');
  mockMode = 'feedback-success';
  const beforeFeedbackSuccess = interceptedRequests.length;
  await feedbackShell.locator('button[type="submit"]').click();
  await feedbackShell.locator('.feedback-success-card').waitFor({ state: 'visible', timeout: 8000 });
  assert(interceptedRequests.length === beforeFeedbackSuccess + 1, `${profileName}: Feedback-Success hat nicht genau einen Request erzeugt`);
  const feedbackSuccessRequest = interceptedRequests.at(-1);
  assert(feedbackSuccessRequest.method === 'POST', `${profileName}: Feedback-Success ist kein POST`);
  assert(includesMultipart(feedbackSuccessRequest.body, 'feedback_type_label', 'Etwas fehlt'), `${profileName}: Feedback-Payload enthält sichtbaren Typ nicht`);
  assert(includesMultipart(feedbackSuccessRequest.body, 'page_type', 'publish'), `${profileName}: Feedback-Payload enthält Seitentyp nicht`);
  assert(includesMultipart(feedbackSuccessRequest.body, 'message', 'synthetischer fehlender Termin'), `${profileName}: Feedback-Payload enthält Nachricht nicht`);
  assert(!feedbackSuccessRequest.body.includes('name="feedback_type"'), `${profileName}: interner Feedback-Typ wurde entgegen Payload-Minimierung versendet`);
  assert(!feedbackSuccessRequest.body.includes('name="route"'), `${profileName}: interne Route wurde entgegen Payload-Minimierung versendet`);
  await feedbackShell.locator('.feedback-modal__close[data-feedback-close]').click();
  await feedbackShell.waitFor({ state: 'hidden' });
  mockMode = 'none';

  const globalLauncher = page.locator('.feedback-launcher[data-feedback-open="global"]');
  await globalLauncher.waitFor({ state: 'visible', timeout: 8000 });
  await globalLauncher.click();
  await feedbackShell.waitFor({ state: 'visible' });
  assert(await feedbackShell.locator('[data-feedback-type][aria-checked="true"]').count() === 0, `${profileName}: globales Feedback hat unerwartete Vorauswahl`);

  const beforeGlobalValidation = interceptedRequests.length;
  await feedbackShell.locator('button[type="submit"]').click();
  assert(interceptedRequests.length === beforeGlobalValidation, `${profileName}: leeres globales Feedback wurde gesendet`);
  assert((await feedbackShell.locator('[data-feedback-error="type"]').innerText()).includes('Bitte zuerst auswählen'), `${profileName}: Feedback-Typ-Pflichtfehler fehlt`);

  await feedbackShell.locator('[data-feedback-type="idea"]').click();
  await feedbackShell.locator('[name="message"]').fill('Ein ausreichend langer synthetischer Verbesserungsvorschlag.');
  const optional = feedbackShell.locator('.feedback-optional');
  await optional.locator('summary').click();
  await feedbackShell.locator('[name="email"]').fill('ungueltig');
  await feedbackShell.locator('button[type="submit"]').click();
  assert((await feedbackShell.locator('[data-feedback-error="email"]').innerText()).includes('gültige E-Mail-Adresse'), `${profileName}: Feedback-E-Mail-Validierung fehlt`);
  assert(interceptedRequests.length === beforeGlobalValidation, `${profileName}: ungültige Feedback-E-Mail wurde gesendet`);

  await feedbackShell.locator('[name="email"]').fill('release-test@example.invalid');
  mockMode = 'feedback-error';
  const beforeFeedbackError = interceptedRequests.length;
  await feedbackShell.locator('button[type="submit"]').click();
  await feedbackShell.locator('.feedback-form__status.is-error').waitFor({ state: 'visible', timeout: 8000 });
  assert(interceptedRequests.length === beforeFeedbackError + 1, `${profileName}: Feedback-Fehlerpfad hat nicht genau einen Request erzeugt`);
  assert((await feedbackShell.locator('.feedback-form__status').innerText()).includes('Synthetischer Feedback-Fehler'), `${profileName}: Feedback-Serverfehler wird nicht verständlich angezeigt`);
  assert(!(await feedbackShell.locator('button[type="submit"]').isDisabled()), `${profileName}: Feedback-Submit bleibt nach Fehler gesperrt`);
  await feedbackShell.locator('.feedback-modal__close[data-feedback-close]').click();
  await feedbackShell.waitFor({ state: 'hidden' });
  mockMode = 'none';

  await open(page, '/startpartner/?scope=events');
  const form = page.locator('#startpartner-request-form');
  await form.waitFor({ state: 'visible' });
  const startSubmit = page.locator('#startpartner-request-submit');
  const beforeEmptyStartpartner = interceptedRequests.length;
  await startSubmit.click();
  assert(interceptedRequests.length === beforeEmptyStartpartner, `${profileName}: leere Startpartner-Anfrage wurde gesendet`);
  const validationStatus = form.locator('[data-startpartner-validation-status]');
  await validationStatus.waitFor({ state: 'visible' });
  assert((await validationStatus.innerText()).includes('markierten Pflichtfelder'), `${profileName}: Startpartner-Pflichtfeldhinweis fehlt`);
  assert(await page.locator('#startpartner-organization').getAttribute('aria-invalid') === 'true', `${profileName}: Organisation wird nicht als ungültig markiert`);

  await page.locator('#startpartner-organization').fill('Synthetic Release Partner');
  await page.locator('#startpartner-email').fill('ungueltig');
  await page.locator('#startpartner-note').fill('kurz');
  await page.locator('#startpartner-privacy-confirmed').check();
  await startSubmit.click();
  assert(interceptedRequests.length === beforeEmptyStartpartner, `${profileName}: ungültige Startpartner-Anfrage wurde gesendet`);
  assert(await page.locator('#startpartner-email').getAttribute('aria-invalid') === 'true', `${profileName}: ungültige Startpartner-E-Mail wird nicht markiert`);
  assert(await page.locator('#startpartner-note').getAttribute('aria-invalid') === 'true', `${profileName}: zu kurze Startpartner-Notiz wird nicht markiert`);

  await page.locator('#startpartner-email').fill('release-test@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetische Release-Anfrage mit ausreichend langer Beschreibung.');
  mockMode = 'startpartner-success';
  const beforeStartSuccess = interceptedRequests.length;
  await startSubmit.click();
  const resultCard = page.locator('#startpartner-request-result');
  await resultCard.waitFor({ state: 'visible', timeout: 8000 });
  assert(interceptedRequests.length === beforeStartSuccess + 1, `${profileName}: Startpartner-Success hat nicht genau einen Request erzeugt`);
  const startSuccessRequest = interceptedRequests.at(-1);
  assert(startSuccessRequest.method === 'POST', `${profileName}: Startpartner-Success ist kein POST`);
  assert(includesMultipart(startSuccessRequest.body, 'pilot_scope', 'events'), `${profileName}: Startpartner-Payload enthält Event-Scope nicht`);
  assert(includesMultipart(startSuccessRequest.body, 'organization', 'Synthetic Release Partner'), `${profileName}: Startpartner-Payload enthält Organisation nicht`);
  assert(includesMultipart(startSuccessRequest.body, 'email', 'release-test@example.invalid'), `${profileName}: Startpartner-Payload enthält E-Mail nicht`);
  assert(includesMultipart(startSuccessRequest.body, 'lead_type', 'startpartner_6_months_limited'), `${profileName}: Startpartner-Payload enthält Lead-Typ nicht`);
  assert((await page.locator('#startpartner-request-result-text').innerText()).includes('ist angekommen'), `${profileName}: Startpartner-Success-Text fehlt`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profileName}: Scope wird nach Success nicht aus URL wiederhergestellt`);
  assert(await page.locator('#startpartner-organization').inputValue() === '', `${profileName}: Organisation wird nach Success nicht zurückgesetzt`);
  assert(!(await startSubmit.isDisabled()), `${profileName}: Startpartner-Submit bleibt nach Success gesperrt`);
  mockMode = 'none';

  await open(page, '/startpartner/?scope=activities');
  await page.locator('#startpartner-organization').fill('Synthetic Error Partner');
  await page.locator('#startpartner-email').fill('release-error@example.invalid');
  await page.locator('#startpartner-note').fill('Synthetischer Fehlerpfad mit ausreichend langer Beschreibung.');
  await page.locator('#startpartner-privacy-confirmed').check();
  mockMode = 'startpartner-error';
  const beforeStartError = interceptedRequests.length;
  await page.locator('#startpartner-request-submit').click();
  await page.locator('#startpartner-request-result').waitFor({ state: 'visible', timeout: 8000 });
  assert(interceptedRequests.length === beforeStartError + 1, `${profileName}: Startpartner-Fehlerpfad hat nicht genau einen Request erzeugt`);
  assert((await page.locator('#startpartner-request-result-text').innerText()).includes('konnte gerade nicht gesendet werden'), `${profileName}: Startpartner-Fehlertext fehlt`);
  assert(await page.locator('#startpartner-organization').inputValue() === 'Synthetic Error Partner', `${profileName}: Startpartner-Werte gehen nach Fehler verloren`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'activities', `${profileName}: Activity-Scope geht im Fehlerpfad verloren`);
  assert(!(await page.locator('#startpartner-request-submit').isDisabled()), `${profileName}: Startpartner-Submit bleibt nach Fehler gesperrt`);
  mockMode = 'none';
  await assertNoOverflow(page, `${profileName}: Startpartner Release Acceptance`);

  await page.screenshot({ path: path.join(outDir, `provider-funnel-release-${profileName}.png`), fullPage: true });
  await context.close();

  return { profile: profileName, viewport, status: 'OK', interceptedFormspreeRequests: interceptedRequests.length, realExternalRequests: 0 };
}

const browser = await chromium.launch({ headless: true });
const results = [];
try {
  results.push(await runProfile(browser, 'mobile-390x844', { width: 390, height: 844 }));
  results.push(await runProfile(browser, 'desktop-1366x900', { width: 1366, height: 900 }));
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outDir, 'provider-funnel-release-acceptance-summary.json'), `${JSON.stringify({ status: 'OK', results }, null, 2)}\n`, 'utf8');
console.log('Provider funnel release acceptance browser test: OK');
