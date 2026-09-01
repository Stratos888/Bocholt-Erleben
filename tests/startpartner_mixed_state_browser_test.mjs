import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const args = process.argv.slice(2);
const value = (name) => {
  const index = args.indexOf(name);
  return index >= 0 ? args[index + 1] : '';
};

const baseUrl = String(value('--base-url') || '').replace(/\/+$/, '');
const parentOutDir = value('--out-dir');
if (!baseUrl || !parentOutDir) {
  console.error('Usage: node tests/startpartner_mixed_state_browser_test.mjs --base-url URL --out-dir DIR');
  process.exit(2);
}

const outDir = path.join(parentOutDir, 'startpartner-mixed-state');
fs.mkdirSync(outDir, { recursive: true });

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function includes(text, marker) {
  return String(text).toLocaleLowerCase('de-DE').includes(String(marker).toLocaleLowerCase('de-DE'));
}

function zeroMetrics() {
  return {
    website_clicks: 0,
    maps_clicks: 0,
    location_clicks: 0,
    organizer_cta_clicks: 0,
    share_clicks: 0,
    copy_link_clicks: 0,
    detail_views: 0,
    total_interactions: 0,
  };
}

function regularSubmission(id = 4301) {
  return {
    id,
    submission_kind: 'event',
    status: 'approved',
    payment_kind: 'subscription',
    requested_model_key: 'active',
    title: 'Regulärer Mitgliedschaftstermin',
    start_date: '2026-10-04',
    time_text: '18:00 Uhr',
    location_name: 'Bocholt',
    location_address: 'Markt 1, Bocholt',
    event_url: 'https://example.test/regular',
    ticket_url: '',
    description_text: 'Regulärer synthetischer Termin.',
    location_public_confirmed: 1,
    created_at: '2026-08-20 10:00:00',
    updated_at: '2026-08-20 12:00:00',
    impact_metrics: zeroMetrics(),
  };
}

function pilotSubmission() {
  return {
    id: 4241,
    submission_kind: 'event',
    status: 'approved',
    payment_kind: 'startpartner_pilot',
    requested_model_key: 'active',
    title: 'Startpartner Kulturtag',
    start_date: '2026-09-12',
    time_text: '19:00 Uhr',
    location_name: 'Kulturort Bocholt',
    location_address: 'Musterstraße 1, Bocholt',
    event_url: 'https://example.test/pilot',
    ticket_url: '',
    description_text: 'Synthetischer Pilotinhalt.',
    location_public_confirmed: 1,
    created_at: '2026-08-25 10:00:00',
    updated_at: '2026-08-25 12:00:00',
    impact_metrics: zeroMetrics(),
  };
}

function activeSubscription() {
  return {
    id: 8101,
    source_provider: 'stripe',
    stripe_subscription_id: 'sub_synthetic_active',
    stripe_customer_id: 'cus_synthetic',
    plan_key: 'active',
    plan_label: 'Aktiv',
    status: 'active',
    monthly_amount_cents: 1999,
    monthly_amount_label: '19,99 € / Monat',
    current_period_start: '2026-08-01 00:00:00',
    current_period_end: '2026-09-01 00:00:00',
    cancel_at_period_end: 0,
    pending_plan_key: null,
    pending_change_effective_at: null,
  };
}

function portalProjection({ mixed = false } = {}) {
  const subscription = activeSubscription();
  const submissions = mixed ? [pilotSubmission(), regularSubmission()] : [regularSubmission()];
  return {
    organizer: {
      id: 401,
      organization_name: 'Kulturverein Bocholt',
      contact_name: 'Erika Beispiel',
      email: 'erika@example.test',
      default_plan_key: 'active',
      stripe_customer_id: 'cus_synthetic',
    },
    portal_session: {
      id: 7001,
      expires_at_utc: '2026-09-30 00:00:00',
      last_seen_at_utc: '2026-08-31 08:00:00',
    },
    subscription,
    active_subscriptions: [subscription],
    quota: {
      entitlement_count: 1,
      has_unlimited: false,
      included_total: 8,
      consumed_total: 3,
      remaining_total: 5,
      current_period_start: '2026-08-01 00:00:00',
      current_period_end: '2026-09-01 00:00:00',
    },
    quota_by_plan: [{
      plan_key: 'active',
      plan_label: 'Aktiv',
      entitlement_count: 1,
      has_unlimited: false,
      included_total: 8,
      consumed_total: 3,
      remaining_total: 5,
      current_period_start: '2026-08-01 00:00:00',
      current_period_end: '2026-09-01 00:00:00',
    }],
    billing_summary: {
      currency: 'EUR',
      subscription_count: 1,
      monthly_total_cents: 1999,
      monthly_total_label: '19,99 € / Monat',
      items: [subscription],
    },
    impact_summary: {
      status: 'ok',
      reporting_target: { type: 'organizer', id: '401' },
      period: { start_date: '2026-08-04', end_date: '2026-08-31' },
      previous_period: { start_date: '2026-07-07', end_date: '2026-08-03' },
      metrics: zeroMetrics(),
      previous_metrics: zeroMetrics(),
      items: [],
    },
    recent_submissions: submissions,
    published_content: submissions,
  };
}

function pilotProjection({ eventFull = false } = {}) {
  return {
    organizer_id: 401,
    gate4: {
      phase: 'active',
      complete: true,
      active: true,
      effective_active: true,
      activation_ready: false,
      pilot: {
        id: '24100000-0000-4000-8000-000000000002',
        status: 'active',
        revision: 7,
        activation_date_local: '2026-08-01',
        planned_end_date: '2027-02-01',
      },
      scopes: [{
        scope_key: 'events',
        status: 'active',
        target_plan_key: 'active',
        limit_value: 8,
        is_unlimited: false,
        period_unit: 'pilot_month',
      }],
      content_links: [{
        id: '24100000-0000-4000-8000-000000000010',
        submission_id: 4241,
        content_type: 'event',
        status: 'approved',
        title: 'Startpartner Kulturtag',
        start_date: '2026-09-12',
      }],
      limits: {
        event: {
          available: true,
          used: eventFull ? 8 : 1,
          limit: 8,
          is_unlimited: false,
          full: eventFull,
          reset_date_local: '2026-09-01',
        },
        activity: { available: false, used: 0, limit: 0, is_unlimited: false, full: false },
      },
      next_action: eventFull
        ? { code: 'event_limit_full', label: 'Eventrahmen ausgeschöpft' }
        : { code: 'submit_content', label: 'Nächsten Termin einreichen', content_type: 'event' },
      blockers: [],
    },
  };
}

async function noOverflow(page, label) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  assert(!overflow, `${label}: horizontaler Überlauf`);
}

async function open(page, route) {
  const response = await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle', timeout: 18000 });
  assert(response && response.status() === 200, `${route}: HTTP 200 erwartet`);
}

async function dashboardContract(browser, viewport, label) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();

  await page.route('**/api/organizer-portal/me.php', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'ok', data: portalProjection({ mixed: true }) }),
  }));
  await page.route('**/api/organizer-portal/pilot.php', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'ok', data: pilotProjection() }),
  }));

  await open(page, '/fuer-veranstalter/dashboard/');
  const pilotCard = page.locator('#organizer-dashboard-pilot-card');
  await pilotCard.waitFor({ state: 'visible', timeout: 8000 });

  const pilotText = await pilotCard.innerText();
  assert(includes(pilotText, 'Startpartner Kulturtag'), `${label}: Pilotinhalt fehlt`);
  assert(!includes(pilotText, 'Regulärer Mitgliedschaftstermin'), `${label}: regulärer Inhalt darf nicht als Pilotinhalt erscheinen`);
  assert(await pilotCard.locator('.content-cta--primary:visible').count() === 1, `${label}: genau eine primäre Pilotaktion erwartet`);
  assert(await page.locator('#organizer-pilot-form-shell').isHidden(), `${label}: Dashboard ohne Handoff-Intent darf das Formular nicht automatisch öffnen`);

  const regularMembership = pilotCard.locator('[data-startpartner-regular-membership]');
  assert(await regularMembership.count() === 1, `${label}: aktive reguläre Mitgliedschaft wird im Pilotkontext verschluckt`);
  assert(await regularMembership.isVisible(), `${label}: reguläre Mitgliedschaft muss sekundär sichtbar bleiben`);
  const membershipText = await regularMembership.innerText();
  assert(includes(membershipText, 'Reguläre Mitgliedschaft'), `${label}: Mixed-State-Abgrenzung fehlt`);
  assert(includes(membershipText, 'Aktiv'), `${label}: regulärer Tarifname fehlt`);
  assert(includes(membershipText, '19,99 € / Monat'), `${label}: regulärer Tarifpreis fehlt`);
  assert(includes(membershipText, '3 von 8 genutzt'), `${label}: reguläre Nutzung fehlt`);

  assert(await page.locator('main.page--organizers > .content-hero--panel').isHidden(), `${label}: generischer Hero konkurriert mit Pilot`);
  assert(await page.locator('#organizer-dashboard-summary').isHidden(), `${label}: generische Summary darf nicht als zweiter aktueller Owner erscheinen`);
  assert(await page.locator('#organizer-dashboard-impact-card').isHidden(), `${label}: generische Null-KPI-Wirkung darf den Pilotkontext nicht als prominente leere KPI-Fläche ergänzen`);

  await pilotCard.locator('#organizer-pilot-open-submission-details').click();
  const submissions = page.locator('#organizer-dashboard-submissions-card');
  await submissions.waitFor({ state: 'visible', timeout: 5000 });
  const submissionText = await submissions.innerText();
  assert(includes(submissionText, 'Startpartner Kulturtag'), `${label}: Pilotinhalt ist im Änderungsweg nicht erreichbar`);
  assert(includes(submissionText, 'Regulärer Mitgliedschaftstermin'), `${label}: reguläre Einreichung ist im Änderungsweg verloren`);
  await noOverflow(page, `${label}: Dashboard`);

  await page.screenshot({ path: path.join(outDir, `mixed-dashboard-${label}.png`), fullPage: true });
  await context.close();
}

async function regularOnlyContract(browser) {
  const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();

  await page.route('**/api/organizer-portal/me.php', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'ok', data: portalProjection() }),
  }));
  await page.route('**/api/organizer-portal/pilot.php', (route) => route.fulfill({
    status: 404,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'error', message: 'No active Startpartner pilot found.' }),
  }));

  await open(page, '/fuer-veranstalter/dashboard/');
  await page.locator('#organizer-dashboard-title').waitFor({ state: 'visible', timeout: 8000 });
  assert(includes(await page.locator('#organizer-dashboard-title').innerText(), 'Kulturverein Bocholt'), 'regular-only: regulärer Dashboard-Hero wurde verändert');
  assert(await page.locator('#organizer-dashboard-pilot-card').isHidden(), 'regular-only: Pilotkarte darf ohne aktiven Pilot nicht erscheinen');
  assert(await page.locator('#organizer-dashboard-summary').isVisible(), 'regular-only: Tarif-/Account-Summary muss unverändert sichtbar bleiben');
  await noOverflow(page, 'regular-only: Dashboard');
  await context.close();
}

async function singleEventRouteContract(browser, { eventFull, label }) {
  const context = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await context.newPage();
  let regularSubmissionRequests = 0;
  let pilotContentRequests = 0;

  await page.route('**/api/organizer-portal/me.php', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'ok', data: portalProjection({ mixed: true }) }),
  }));
  await page.route('**/api/organizer-portal/pilot.php', (route) => route.fulfill({
    status: 200,
    contentType: 'application/json',
    body: JSON.stringify({ status: 'ok', data: pilotProjection({ eventFull }) }),
  }));
  await page.route('**/api/submissions/init.php', (route) => {
    regularSubmissionRequests += 1;
    return route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', data: { submission_id: 9999, payment_reference_key: 'synthetic' } }),
    });
  });
  await page.route('**/api/startpartner/content.php', (route) => {
    pilotContentRequests += 1;
    return route.fulfill({
      status: 201,
      contentType: 'application/json',
      body: JSON.stringify({ status: 'ok', data: { submission_id: 9998 } }),
    });
  });

  await open(page, '/events-veroeffentlichen/einreichen/');
  const guard = page.locator('[data-startpartner-route-guard]');
  await guard.waitFor({ state: 'visible', timeout: 8000 });
  const guardText = await guard.innerText();
  const heroText = await page.locator('.page--publish > .content-hero').innerText();

  if (!eventFull) {
    assert(includes(heroText, 'Veranstaltung über Startpartner einreichen'), `${label}: Seitenkontext bleibt fälschlich auf dem regulären Einzelterminweg`);
    assert(!includes(heroText, 'Zahlungslink'), `${label}: nutzbarer Pilot darf im Hero keine Zahlungsankündigung zeigen`);
    assert(includes(guardText, 'Startpartner') || includes(heroText, 'Startpartner'), `${label}: Startpartner-Hinweis fehlt`);
    assert(includes(guardText, 'kostenlos') || includes(heroText, 'kostenlos'), `${label}: kostenloser Pilotweg wird nicht erklärt`);
    assert(await page.locator('#publish-submit').isHidden(), `${label}: reguläres 9,90-EUR-Formular muss bei nutzbarem Pilot zurücktreten`);
    const pilotLink = guard.locator('a[href="/fuer-veranstalter/dashboard/?startpartner_submit=event"]');
    assert(await pilotLink.count() === 1, `${label}: direkter CTA zum Startpartner-Eventformular fehlt`);

    await pilotLink.click();
    const formShell = page.locator('#organizer-pilot-form-shell');
    await formShell.waitFor({ state: 'visible', timeout: 8000 });
    assert(await page.locator('#organizer-pilot-open-form').isHidden(), `${label}: redundanter zweiter Öffnen-Klick ist noch erforderlich`);
    assert(await page.locator('#organizer-pilot-content-form').isVisible(), `${label}: bestehendes Pilotformular wurde nicht direkt geöffnet`);
    assert(await page.locator('#organizer-pilot-content-form').getAttribute('data-preferred-type') === 'event', `${label}: Event-Handoff wählt nicht Veranstaltung vor`);
    assert(!includes(page.url(), 'startpartner_submit='), `${label}: UI-Handoff-Intent muss nach erfolgreichem Konsum aus der URL entfernt werden`);
    await noOverflow(page, `${label}: direkt geöffnetes Pilotformular`);
  } else {
    assert(includes(heroText, 'Einzeltermin zur Prüfung einreichen'), `${label}: regulärer Hero muss bei ausgeschöpftem Pilot erhalten bleiben`);
    assert(includes(guardText, 'ausgeschöpft'), `${label}: Pilotlimit-Abgrenzung fehlt`);
    assert(includes(guardText, 'regulär'), `${label}: regulärer Alternativweg wird nicht klar erklärt`);
    assert(await page.locator('#publish-submit').isVisible(), `${label}: regulärer Einzeltermin muss bei ausgeschöpftem Pilot verfügbar bleiben`);
    assert(await page.locator('#publish-standard-pay').isVisible(), `${label}: regulärer Submit-CTA fehlt`);
    await noOverflow(page, `${label}: Einzeltermin`);
  }

  assert(regularSubmissionRequests === 0, `${label}: Browser-Evidence darf keine reguläre Submission erzeugen`);
  assert(pilotContentRequests === 0, `${label}: Handoff darf keine Pilot-Submission erzeugen`);
  await page.screenshot({ path: path.join(outDir, `single-event-${label}.png`), fullPage: true });
  await context.close();
}

const browser = await chromium.launch({ headless: true });
try {
  await regularOnlyContract(browser);
  await dashboardContract(browser, { width: 390, height: 844 }, 'mobile-390x844');
  await dashboardContract(browser, { width: 1280, height: 900 }, 'desktop-1280x900');
  await singleEventRouteContract(browser, { eventFull: false, label: 'pilot-available' });
  await singleEventRouteContract(browser, { eventFull: true, label: 'pilot-full' });
} finally {
  await browser.close();
}

fs.writeFileSync(path.join(outDir, 'summary.json'), `${JSON.stringify({ status: 'OK', scenarios: 5 }, null, 2)}\n`, 'utf8');
console.log(JSON.stringify({ status: 'OK', contract: 'startpartner-mixed-state', scenarios: 5 }));