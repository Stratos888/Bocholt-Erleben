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

function includes(text, marker) {
  return String(text).toLocaleLowerCase('de-DE').includes(String(marker).toLocaleLowerCase('de-DE'));
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
  assert(!includes(text, 'Startpartner-Pilot'), `${label}: alte sichtbare Produktbezeichnung Startpartner-Pilot`);
  assert(!includes(text, 'Startpartnerplatz'), `${label}: alte sichtbare Produktbezeichnung Startpartnerplatz`);
  return text;
}

async function fillStartpartnerForm(page, suffix) {
  await page.locator('#startpartner-scope').selectOption('both');
  await page.locator('#startpartner-organization').fill(`Browser Test Organisation ${suffix}`);
  await page.locator('#startpartner-contact').fill('Erika Beispiel');
  await page.locator('#startpartner-email').fill(`erika.${suffix.toLowerCase()}@example.test`);
  await page.locator('#startpartner-website').fill('https://example.test/angebot');
  await page.locator('#startpartner-note').fill('Lokales Angebot mit regelmäßigen Veranstaltungen und Aktivitäten für den Browser-Contract.');
  await page.locator('#startpartner-privacy-confirmed').check();
}

async function runProfile(browser, profileName, viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  let formspreeRequests = 0;
  let intakeMode = 'success';
  const intakeRequests = [];

  await page.route('https://formspree.io/**', async (route) => {
    formspreeRequests += 1;
    await route.abort();
  });

  await page.route('**/api/startpartner/intake.php', async (route) => {
    const request = route.request();
    let payload = null;
    try {
      payload = request.postDataJSON();
    } catch (_) {
      payload = null;
    }
    intakeRequests.push({ method: request.method(), headers: request.headers(), payload, mode: intakeMode });

    if (intakeMode === 'error') {
      await route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ status: 'error', message: 'synthetic intake unavailable' }),
      });
      return;
    }

    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', data: { stored: true, confirmation_mail_sent: true } }),
    });
  });

  // Bestehender Publish-Funnel bleibt die visuelle Formularreferenz.
  await open(page, '/events-veroeffentlichen/anbindung/');
  const referenceSignature = await formFunnelSignature(page);
  await assertNoOverflow(page, `${profileName}: Anfrage-Referenz`);

  // Event-Funnel: genau drei primäre Wege, Startpartner vor dem sekundären Automatikpfad.
  await open(page, '/events-veroeffentlichen/');
  const eventText = await assertCanonicalNaming(page, `${profileName}: Event-Funnel`);
  for (const marker of [
    'Wähle den passenden Veröffentlichungsweg',
    'Einzelne Veranstaltung einreichen',
    'Mitgliedschaft für regelmäßige Termine',
    'Startpartner',
    '6 Monate kostenlos',
    'Termine schon gepflegt? Automatische Übernahme prüfen',
    'Wie funktioniert Startpartner? Kurz erklärt',
  ]) {
    assert(includes(eventText, marker), `${profileName}: Event-Marker fehlt: ${marker}`);
  }
  const eventModels = await page.locator('.publish-model-list > li .publish-model-copy strong').allTextContents();
  assert(JSON.stringify(eventModels.map((item) => item.trim())) === JSON.stringify([
    'Einzelne Veranstaltung einreichen',
    'Mitgliedschaft für regelmäßige Termine',
    'Startpartner',
  ]), `${profileName}: Event-Funnel muss genau die drei primären Wege in Zielreihenfolge zeigen`);
  assert(await page.locator('.publish-model-list a[href="/events-veroeffentlichen/anbindung/"]').count() === 0, `${profileName}: automatische Übernahme darf kein primäres Modell sein`);
  assert(await page.locator('a[href="/events-veroeffentlichen/anbindung/"]').count() === 1, `${profileName}: sekundärer Automatik-Pfad fehlt`);
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Event-Startpartner-Scope fehlt`);
  assert(await page.getByRole('link', { name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Event-Startpartner-CTA fehlt`);
  assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner-weg"]').count() === 1, `${profileName}: Event-Startpartner-Erkläranker fehlt`);
  await assertNoOverflow(page, `${profileName}: Event-Funnel`);

  // Aktivitäts- und Membership-Funnel bleiben fachlich intakt.
  await open(page, '/aktivitaeten/sichtbar-werden/');
  const activityText = await assertCanonicalNaming(page, `${profileName}: Aktivitäts-Funnel`);
  for (const marker of ['Wähle den passenden Tarif', 'Startpartner', '6 Monate kostenlos testen', 'So geht es weiter']) {
    assert(includes(activityText, marker), `${profileName}: Aktivitäts-Marker fehlt: ${marker}`);
  }
  assert(await page.locator('a[href="/startpartner/?scope=activities"]').count() === 1, `${profileName}: Activity-Startpartner-Scope fehlt`);
  await assertNoOverflow(page, `${profileName}: Aktivitäts-Funnel`);

  await open(page, '/fuer-veranstalter/');
  const membershipText = await assertCanonicalNaming(page, `${profileName}: Membership-Funnel`);
  assert(includes(membershipText, 'Mitgliedschaft für regelmäßige Veranstaltungen'), `${profileName}: Membership-H1 fehlt`);
  assert(await page.locator('a[href="/startpartner/?scope=events"]').count() === 1, `${profileName}: Membership-Startpartner-Scope fehlt`);
  await assertNoOverflow(page, `${profileName}: Membership-Funnel`);

  // Startpartner: kompakter First-Party-Funnel, gleiche Primitives, keine redundanten Erklärblöcke.
  await open(page, '/startpartner/?scope=events');
  await page.locator('#startpartner-request-form').waitFor({ state: 'visible' });
  const startpartnerText = await assertCanonicalNaming(page, `${profileName}: Startpartner`);
  for (const marker of [
    'Als Startpartner 6 Monate kostenlos testen',
    'Veranstaltungen, Aktivitäten oder beides testen',
    'keine Zahlungsart erforderlich',
    'keine automatische kostenpflichtige Verlängerung',
    'Wir prüfen, ob Startpartner zu deinem Angebot passt',
  ]) {
    assert(includes(startpartnerText, marker), `${profileName}: Startpartner-Marker fehlt: ${marker}`);
  }
  assert(await page.locator('.content-kicker').count() === 0, `${profileName}: Kicker darf nicht vorhanden sein`);
  assert(!includes(startpartnerText, 'Was kann der Pilot umfassen?'), `${profileName}: redundanter Scope-Block sichtbar`);
  assert(!includes(startpartnerText, 'So läuft der Start ab'), `${profileName}: redundanter Ablaufblock sichtbar`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profileName}: Event-Scope nicht vorausgewählt`);
  assert(await page.locator('a[href="/veroeffentlichung-erklaert/#startpartner-weg"]').count() === 1, `${profileName}: kanonischer Erklärlink fehlt`);

  const regularSection = page.locator('section[aria-labelledby="startpartner-regular-paths-title"]');
  assert(await regularSection.locator('.publish-model-list > li').count() === 2, `${profileName}: genau zwei reguläre Alternativen erwartet`);
  assert(await regularSection.locator('a[href="/events-veroeffentlichen/"]').count() === 1, `${profileName}: Event-Rückweg fehlt`);
  assert(await regularSection.locator('a[href="/aktivitaeten/sichtbar-werden/"]').count() === 1, `${profileName}: Activity-Rückweg fehlt`);

  const startpartnerSignature = await formFunnelSignature(page);
  assertSame(startpartnerSignature, referenceSignature, `${profileName}: Startpartner/Publish-Form-Konsistenz`);
  await assertNoOverflow(page, `${profileName}: Startpartner`);
  await page.screenshot({ path: path.join(outDir, `startpartner-${profileName}.png`), fullPage: true });

  // Erfolgs- und Fehlerpfad bleiben vollständig synthetisch; kein DB-/Mail-/Formspree-Write.
  intakeMode = 'success';
  await fillStartpartnerForm(page, profileName.replace(/[^a-z0-9]/gi, ''));
  await Promise.all([
    page.waitForURL('**/startpartner/erfolg/?mail=sent', { timeout: 8000 }),
    page.locator('#startpartner-request-submit').click(),
  ]);
  const successText = await page.locator('body').innerText();
  assert(includes(successText, 'Anfrage erhalten'), `${profileName}: eindeutiger Erfolgszustand fehlt`);
  assert(includes(successText, 'Die Anfrage ist noch keine Aufnahmezusage.'), `${profileName}: No-Approval-Hinweis fehlt`);
  await assertNoOverflow(page, `${profileName}: Startpartner-Erfolg`);

  const successRequest = intakeRequests.at(-1);
  assert(successRequest?.method === 'POST', `${profileName}: Intake muss POST verwenden`);
  assert(String(successRequest?.headers?.['content-type'] || '').includes('application/json'), `${profileName}: Intake muss JSON senden`);
  assert(String(successRequest?.headers?.['idempotency-key'] || '').length >= 16, `${profileName}: Idempotency-Key fehlt`);
  assert(successRequest?.payload?.source === 'self_service', `${profileName}: Public-Source muss self_service sein`);
  assert(successRequest?.payload?.desired_content_scope === 'both', `${profileName}: Scope-Payload falsch`);

  await open(page, '/startpartner/?scope=activities');
  assert(await page.locator('#startpartner-scope').inputValue() === 'activities', `${profileName}: Activity-Scope nicht vorausgewählt`);
  intakeMode = 'error';
  await fillStartpartnerForm(page, `Error${profileName.replace(/[^a-z0-9]/gi, '')}`);
  const organizationBefore = await page.locator('#startpartner-organization').inputValue();
  await page.locator('#startpartner-request-submit').click();
  const errorNode = page.locator('#startpartner-request-result');
  await errorNode.waitFor({ state: 'visible', timeout: 8000 });
  assert(includes(await errorNode.innerText(), 'nicht sicher gespeichert'), `${profileName}: klarer Fehlertext fehlt`);
  assert(await page.locator('#startpartner-organization').inputValue() === organizationBefore, `${profileName}: Fehler darf Formulardaten nicht leeren`);

  // Erklärlink landet am sichtbaren Weg; FAQ bleibt erreichbar.
  await open(page, '/veroeffentlichung-erklaert/#startpartner-weg');
  const explainerText = await assertCanonicalNaming(page, `${profileName}: Erklärseite`);
  for (const marker of ['Sonderweg: Startpartner', 'sechs Monate kostenlos', 'Keine Zahlungsart erforderlich', 'keine automatische kostenpflichtige Verlängerung']) {
    assert(includes(explainerText, marker), `${profileName}: Erklärseiten-Marker fehlt: ${marker}`);
  }
  const details = page.locator('details#startpartner');
  assert(await details.count() === 1, `${profileName}: Startpartner-FAQ fehlt`);
  if (!(await details.evaluate((node) => node.open))) await details.locator('summary').click();
  assert(await details.evaluate((node) => node.open), `${profileName}: Startpartner-FAQ lässt sich nicht öffnen`);
  await assertNoOverflow(page, `${profileName}: Erklärseite`);

  assert(formspreeRequests === 0, `${profileName}: Browser-Test hat unerwartet Formspree aufgerufen`);
  assert(intakeRequests.length === 2, `${profileName}: exakt zwei intercepted Intake-Requests erwartet`);

  await context.close();
  return { profile: profileName, viewport, status: 'OK' };
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

// #364 verlangt die vollständige etablierte Responsive-Matrix als Teil des exakten PR-Gates.
// Das Premium-Skript nutzt dieselben --base-url/--out-dir-Argumente und arbeitet ausschließlich
// mit synthetischen/intercepted Browserzuständen; es führt keine Produktionsmutation aus.
await import('./startpartner_premium_finish_browser_test.mjs');
