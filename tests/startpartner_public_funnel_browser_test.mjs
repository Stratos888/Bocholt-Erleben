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
    intakeRequests.push({
      method: request.method(),
      headers: request.headers(),
      payload,
      mode: intakeMode,
    });

    if (intakeMode === 'error') {
      await route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({
          status: 'error',
          message: 'synthetic intake unavailable',
        }),
      });
      return;
    }

    await route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({
        status: 'ok',
        data: {
          stored: true,
          confirmation_mail_sent: true,
        },
      }),
    });
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
  assert(await page.getByRole('heading', { level: 1, name: 'Als Startpartner 6 Monate kostenlos testen' }).count() === 1, `${profileName}: klares Startpartner-Versprechen in der H1 fehlt`);
  for (const marker of ['Veranstaltungen, Aktivitäten oder beides testen', 'ohne Zahlungsart', 'keine automatische kostenpflichtige Verlängerung']) {
    assert(startpartnerText.includes(marker), `${profileName}: Premium-Hero-Marker fehlt: ${marker}`);
  }
  assert(startpartnerText.includes('Wir prüfen, ob Startpartner zu deinem Angebot passt'), `${profileName}: klare Prüfkommunikation fehlt`);
  assert(await page.locator('.content-kicker').count() === 0, `${profileName}: Kicker darf nicht vorhanden sein`);
  assert(!startpartnerText.includes('Was kann der Pilot umfassen?'), `${profileName}: redundanter Scope-Block sichtbar`);
  assert(!startpartnerText.includes('So läuft der Start ab'), `${profileName}: redundanter Ablaufblock sichtbar`);
  assert(await page.getByRole('heading', { level: 2, name: 'Startpartner anfragen' }).count() === 1, `${profileName}: Formulartitel fehlt`);
  assert(await page.locator('#startpartner-scope').inputValue() === 'events', `${profileName}: Event-Scope nicht vorausgewählt`);
  assert(await page.locator('#startpartner-contact').count() === 1, `${profileName}: Ansprechperson fehlt`);
  assert(await page.locator('#startpartner-website').count() === 1, `${profileName}: Website/Quelle fehlt`);
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

  // Synthetischer Erfolgs-Submit: Request wird im Browser intercepted, kein DB-/Mail-Write.
  intakeMode = 'success';
  await fillStartpartnerForm(page, profileName.replace(/[^a-z0-9]/gi, ''));
  await Promise.all([
    page.waitForURL('**/startpartner/erfolg/?mail=sent', { timeout: 8000 }),
    page.locator('#startpartner-request-submit').click(),
  ]);
  assert(await page.getByRole('heading', { level: 1, name: 'Anfrage erhalten' }).count() === 1, `${profileName}: eindeutige Erfolgs-H1 fehlt`);
  const successText = await page.locator('body').innerText();
  assert(successText.includes('Wir prüfen jetzt, ob Startpartner zu deinem Angebot passt.'), `${profileName}: Erfolgs-Prüfzustand fehlt`);
  assert(successText.includes('Eine Eingangsbestätigung ist per E-Mail unterwegs.'), `${profileName}: Mail-Bestätigung fehlt`);
  assert(successText.includes('Die Anfrage ist noch keine Aufnahmezusage.'), `${profileName}: No-Approval-Hinweis fehlt`);
  assert(await page.locator('.content-kicker').count() === 0, `${profileName}: Erfolgsseite darf keinen Kicker besitzen`);
  await assertNoOverflow(page, `${profileName}: Startpartner-Erfolg`);

  const successRequest = intakeRequests.at(-1);
  assert(successRequest?.method === 'POST', `${profileName}: Intake muss POST verwenden`);
  assert(String(successRequest?.headers?.['content-type'] || '').includes('application/json'), `${profileName}: Intake muss JSON senden`);
  assert(String(successRequest?.headers?.['idempotency-key'] || '').length >= 16, `${profileName}: Idempotency-Key fehlt`);
  assert(successRequest?.payload?.source === 'self_service', `${profileName}: Public-Source muss self_service sein`);
  assert(successRequest?.payload?.desired_content_scope === 'both', `${profileName}: Scope-Payload falsch`);
  assert(successRequest?.payload?.organization?.startsWith('Browser Test Organisation'), `${profileName}: Organisation fehlt im Payload`);
  assert(successRequest?.payload?.contact_name === 'Erika Beispiel', `${profileName}: Ansprechperson fehlt im Payload`);
  assert(successRequest?.payload?.email?.includes('@example.test'), `${profileName}: E-Mail fehlt im Payload`);
  assert(successRequest?.payload?.website === 'https://example.test/angebot', `${profileName}: Website fehlt im Payload`);
  assert(successRequest?.payload?.description?.includes('Lokales Angebot'), `${profileName}: Beschreibung fehlt im Payload`);
  assert(successRequest?.payload?.privacy_confirmed === true, `${profileName}: Datenschutzbestätigung fehlt im Payload`);

  // Synthetischer Fehler: Formular bleibt gefüllt und zeigt einen klaren Fehlerzustand.
  await open(page, '/startpartner/?scope=activities');
  assert(await page.locator('#startpartner-scope').inputValue() === 'activities', `${profileName}: Activity-Scope nicht vorausgewählt`);
  intakeMode = 'error';
  await fillStartpartnerForm(page, `Error${profileName.replace(/[^a-z0-9]/gi, '')}`);
  const organizationBefore = await page.locator('#startpartner-organization').inputValue();
  await page.locator('#startpartner-request-submit').click();
  const errorNode = page.locator('#startpartner-request-result');
  await errorNode.waitFor({ state: 'visible', timeout: 8000 });
  assert((await errorNode.innerText()).includes('nicht sicher gespeichert'), `${profileName}: klarer Fehlertext fehlt`);
  assert(await page.locator('#startpartner-organization').inputValue() === organizationBefore, `${profileName}: Fehler darf Formulardaten nicht leeren`);
  assert(page.url().includes('/startpartner/?scope=activities'), `${profileName}: Fehler darf nicht auf Erfolgsseite navigieren`);

  // Detailwissen liegt nur auf der Erklärseite.
  await open(page, '/veroeffentlichung-erklaert/#startpartner');
  const explainerText = await assertCanonicalNaming(page, `${profileName}: Erklärseite`);
  assert(explainerText.includes('Sonderweg: Startpartner'), `${profileName}: Startpartner-Sonderweg fehlt`);
  assert(explainerText.includes('sechs Monate kostenlos'), `${profileName}: Sechs-Monats-Erklärung fehlt`);
  assert(explainerText.includes('keine Zahlungsart'), `${profileName}: Zahlungsart-Ausschluss fehlt`);
  assert(explainerText.includes('keine automatische kostenpflichtige Verlängerung'), `${profileName}: Ausschluss automatischer Kosten fehlt`);
  assert(!explainerText.includes('kostenpflichtige Umwandlung'), `${profileName}: alte Umwandlungs-Sprache sichtbar`);
  assert(!explainerText.includes('kostenpflichtigen Tarif umgewandelt'), `${profileName}: alte Umwandlungs-Sprache sichtbar`);
  const details = page.locator('details#startpartner');
  assert(await details.count() === 1, `${profileName}: Startpartner-FAQ fehlt`);
  await page.waitForFunction(() => document.querySelector('details#startpartner')?.open === true, null, { timeout: 4000 });
  await assertNoOverflow(page, `${profileName}: Erklärseite`);

  assert(formspreeRequests === 0, `${profileName}: Browser-Test hat unerwartet Formspree aufgerufen`);
  assert(intakeRequests.length === 2, `${profileName}: exakt zwei intercepted Intake-Requests erwartet`);

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
      '/startpartner/erfolg/?mail=sent',
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