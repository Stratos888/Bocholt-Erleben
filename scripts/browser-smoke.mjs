#!/usr/bin/env node
/* === BEGIN FILE: scripts/browser-smoke.mjs | Zweck: read-only Browser-Smoke fuer zentrale Nutzerwege nach Deploy; Umfang: Playwright Chromium, Desktop/Mobile, Screenshots/Markdown-Artefakte bei Fehlern === */
import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const CONSENT_KEY = 'be_statistics_consent_v1';
const CONSENT_COOKIE = 'be_statistics_consent';

const PROFILES = {
  desktop: {
    viewport: { width: 1366, height: 900 },
    deviceScaleFactor: 1,
    isMobile: false,
    hasTouch: false,
  },
  mobile: {
    viewport: { width: 390, height: 844 },
    deviceScaleFactor: 3,
    isMobile: true,
    hasTouch: true,
    userAgent:
      'Mozilla/5.0 (Linux; Android 14; BocholtSmoke) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Mobile Safari/537.36',
  },
};

const ROUTE_CHECKS = [
  {
    name: 'Home',
    path: '/',
    selectors: ['body.page-route-today', '#today-title'],
    anyText: ['Heute rund um Bocholt', 'Was passt jetzt?'],
    dynamicAny: ['[data-today-card]', '.today-card'],
  },
  {
    name: 'Events',
    path: '/events/',
    selectors: ['#event-cards'],
    anyText: ['Events', 'Veranstaltungen'],
    dynamicAny: ['#event-cards .event-card', '#event-cards article'],
  },
  {
    name: 'Aktivitaeten',
    path: '/aktivitaeten/',
    selectors: ['#offer-cards'],
    anyText: ['Aktivitäten', 'Angebote'],
    dynamicAny: ['#offer-cards .event-card', '#offer-cards article'],
  },
  {
    name: 'Event einreichen',
    path: '/events-veroeffentlichen/einreichen/',
    selectors: ['#publish-standard-form', '#publish-standard-title', '#publish-standard-pay'],
    anyText: ['Veranstaltung', 'Einreichen', 'Prüfung'],
  },
  {
    name: 'Aktivitaet sichtbar werden',
    path: '/aktivitaeten/sichtbar-werden/',
    selectors: ['main.page--activity-presence'],
    anyText: ['Als Aktivität', 'sichtbar werden', 'Tarif'],
  },
  {
    name: 'Aktivitaet einreichen',
    path: '/aktivitaeten/sichtbar-werden/einreichen/',
    selectors: ['#activity-presence-form', '#activity-presence-title'],
    anyText: ['Aktivität', 'einreichen'],
  },
  {
    name: 'Zahlung starten',
    path: '/zahlung-starten/',
    selectors: ['#payment-start-title', '#payment-start-lead'],
    anyText: ['Zahlungslink ungültig', 'Zahlung wird vorbereitet', 'Zahlung'],
  },
  {
    name: 'Veranstalter Login',
    path: '/fuer-veranstalter/login/',
    selectors: ['#organizer-login-form', '#organizer-login-email', '#organizer-login-submit'],
    anyText: ['Zugangslink', 'E-Mail'],
  },
  {
    name: 'Veranstalter Dashboard Zugangszustand',
    path: '/fuer-veranstalter/dashboard/',
    selectors: ['#organizer-dashboard-title'],
    anyText: ['Veranstalterbereich', 'Zugangslink', 'Einreichungen'],
  },
];

function parseArgs(argv) {
  const args = {
    baseUrl: '',
    profile: 'all',
    outDir: 'artifacts/browser-smoke',
    expectedBuild: '',
    timeoutMs: 18000,
  };

  for (let i = 2; i < argv.length; i += 1) {
    const value = argv[i];
    const next = argv[i + 1];
    if (value === '--base-url') {
      args.baseUrl = next || '';
      i += 1;
    } else if (value === '--profile') {
      args.profile = next || 'all';
      i += 1;
    } else if (value === '--out-dir') {
      args.outDir = next || args.outDir;
      i += 1;
    } else if (value === '--expected-build') {
      args.expectedBuild = next || '';
      i += 1;
    } else if (value === '--timeout-ms') {
      args.timeoutMs = Number(next || args.timeoutMs);
      i += 1;
    } else if (value === '--help' || value === '-h') {
      printHelp();
      process.exit(0);
    }
  }

  args.baseUrl = String(args.baseUrl || '').trim().replace(/\/+$/, '');
  if (!/^https?:\/\//.test(args.baseUrl)) {
    throw new Error('--base-url muss mit https:// oder http:// beginnen.');
  }
  if (args.profile !== 'all' && !PROFILES[args.profile]) {
    throw new Error('--profile muss all, desktop oder mobile sein.');
  }
  return args;
}

function printHelp() {
  console.log(`Browser-Smoke Bocholt erleben\n\nUsage:\n  node scripts/browser-smoke.mjs --base-url https://staging.bocholt-erleben.de --profile all\n`);
}

function absoluteUrl(baseUrl, routePath) {
  return new URL(routePath, `${baseUrl}/`).toString();
}

function slug(value) {
  return String(value || '')
    .toLowerCase()
    .replace(/ä/g, 'ae')
    .replace(/ö/g, 'oe')
    .replace(/ü/g, 'ue')
    .replace(/ß/g, 'ss')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 80) || 'check';
}

async function ensureOutDir(outDir) {
  await fs.mkdir(outDir, { recursive: true });
}

async function writeJson(filePath, data) {
  await fs.writeFile(filePath, `${JSON.stringify(data, null, 2)}\n`, 'utf8');
}

async function writeSummary(outDir, results, startedAt, finishedAt, baseUrl) {
  const passed = results.filter((entry) => entry.status === 'ok').length;
  const failed = results.filter((entry) => entry.status !== 'ok');
  const lines = [
    '# Browser-Smoke',
    '',
    `Base URL: ${baseUrl}`,
    `Start: ${startedAt}`,
    `Ende: ${finishedAt}`,
    `Ergebnis: ${passed}/${results.length} OK`,
    '',
    '| Status | Profil | Check | Pfad | Hinweis |',
    '|---|---|---|---|---|',
    ...results.map((entry) => {
      const status = entry.status === 'ok' ? 'OK' : 'FEHLER';
      const hint = entry.error ? entry.error.replace(/\|/g, '/') : '';
      return `| ${status} | ${entry.profile} | ${entry.name} | ${entry.path || ''} | ${hint} |`;
    }),
  ];

  if (failed.length) {
    lines.push('', '## Fehler-Artefakte', '');
    failed.forEach((entry) => {
      if (entry.screenshot) lines.push(`- ${entry.name} (${entry.profile}): ${entry.screenshot}`);
    });
  }

  await fs.writeFile(path.join(outDir, 'summary.md'), `${lines.join('\n')}\n`, 'utf8');
}

async function createContext(browser, baseUrl, profileName, options = {}) {
  const url = new URL(baseUrl);
  const context = await browser.newContext({
    ...PROFILES[profileName],
    baseURL: baseUrl,
    ignoreHTTPSErrors: true,
  });

  await context.addCookies([
    {
      name: CONSENT_COOKIE,
      value: options.consentState || 'denied',
      domain: url.hostname,
      path: '/',
      sameSite: 'Lax',
      secure: url.protocol === 'https:',
      expires: Math.floor(Date.now() / 1000) + 180 * 24 * 60 * 60,
    },
  ]);

  await context.addInitScript(
    ({ key, state }) => {
      try {
        window.localStorage.setItem(key, state);
      } catch (_) {}
    },
    { key: CONSENT_KEY, state: options.consentState || 'denied' },
  );

  return context;
}

async function gotoReady(page, baseUrl, routePath, timeoutMs) {
  const url = absoluteUrl(baseUrl, routePath);
  const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: timeoutMs });
  if (!response) throw new Error(`Keine Browser-Response fuer ${routePath}`);
  if (response.status() >= 500) throw new Error(`${routePath} liefert HTTP ${response.status()}`);
  await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
  await page.waitForTimeout(350);
}

async function ensureNoFatal(page) {
  const bodyText = await page.locator('body').innerText({ timeout: 5000 }).catch(() => '');
  const fatalPatterns = [
    /Fatal error/i,
    /Parse error/i,
    /Warning:\s/i,
    /Notice:\s/i,
    /Stack trace/i,
    /Application error/i,
  ];
  const matched = fatalPatterns.find((pattern) => pattern.test(bodyText));
  if (matched) throw new Error(`Fataler Text sichtbar: ${matched}`);
}

async function expectVisible(page, selector, timeoutMs = 7000) {
  await page.locator(selector).first().waitFor({ state: 'visible', timeout: timeoutMs });
}

async function expectAnyText(page, texts) {
  const bodyText = await page.locator('body').innerText({ timeout: 5000 });
  const found = texts.some((text) => bodyText.toLowerCase().includes(String(text).toLowerCase()));
  if (!found) throw new Error(`Keiner der erwarteten Texte sichtbar: ${texts.join(' / ')}`);
}

async function expectAnySelectorCount(page, selectors, timeoutMs = 12000) {
  if (!selectors || !selectors.length) return;

  const selectorList = selectors.join(', ');
  await page.waitForFunction(
    (combinedSelector) => document.querySelectorAll(combinedSelector).length > 0,
    selectorList,
    { timeout: timeoutMs },
  );
}

async function checkRoute(page, baseUrl, route, timeoutMs) {
  await gotoReady(page, baseUrl, route.path, timeoutMs);
  await ensureNoFatal(page);
  for (const selector of route.selectors || []) {
    await expectVisible(page, selector);
  }
  if (route.anyText) await expectAnyText(page, route.anyText);
  if (route.dynamicAny) await expectAnySelectorCount(page, route.dynamicAny);
}

async function checkBottomNavigation(page, baseUrl, timeoutMs) {
  await gotoReady(page, baseUrl, '/', timeoutMs);
  await expectVisible(page, '#bottom-tabbar-root');
  await page.locator('#bottom-tabbar-root a[href="/events/"]').first().click({ timeout: 7000 });
  await page.waitForURL(/\/events\/?$/, { timeout: timeoutMs });
  await expectVisible(page, '#event-cards');
  await ensureNoFatal(page);

  await page.locator('#bottom-tabbar-root a[href="/aktivitaeten/"]').first().click({ timeout: 7000 });
  await page.waitForURL(/\/aktivitaeten\/?$/, { timeout: timeoutMs });
  await expectVisible(page, '#offer-cards');
  await ensureNoFatal(page);
}

async function checkConsentNavigationResync(browser, baseUrl, profileName, timeoutMs) {
  const context = await browser.newContext({
    ...PROFILES[profileName],
    baseURL: baseUrl,
    ignoreHTTPSErrors: true,
  });
  const page = await context.newPage();

  try {
    await page.goto(absoluteUrl(baseUrl, '/'), { waitUntil: 'domcontentloaded', timeout: timeoutMs });
    await page.evaluate(({ key, cookie }) => {
      try {
        localStorage.removeItem(key);
      } catch (_) {}
      document.cookie = `${cookie}=; Max-Age=0; path=/; SameSite=Lax`;
    }, { key: CONSENT_KEY, cookie: CONSENT_COOKIE });
    await page.reload({ waitUntil: 'domcontentloaded', timeout: timeoutMs });
    await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});

    await expectVisible(page, '[data-privacy-consent-banner]', 8000);
    await page.getByRole('button', { name: /Ohne Statistik/i }).click({ timeout: 7000 });
    await page.locator('[data-privacy-consent-banner]').waitFor({ state: 'detached', timeout: 7000 });

    const eventsLink = page.locator('#bottom-tabbar-root a[href="/events/"]').first();
    await eventsLink.waitFor({ state: 'visible', timeout: 7000 });
    await eventsLink.click();
    await page.waitForURL(/\/events\/?$/, { timeout: timeoutMs });
    await page.waitForTimeout(1000);

    const bannerCount = await page.locator('[data-privacy-consent-banner]').count();
    if (bannerCount !== 0) {
      throw new Error('Consent-Hinweis erscheint nach Bottom-Tab-Wechsel erneut.');
    }
  } finally {
    await context.close();
  }
}

async function checkBuild(baseUrl, expectedBuild) {
  if (!expectedBuild) return;
  const url = absoluteUrl(baseUrl, '/meta/build.txt');
  const response = await fetch(url, { cache: 'no-store' });
  if (!response.ok) throw new Error(`/meta/build.txt liefert HTTP ${response.status}`);
  const text = (await response.text()).trim();
  if (text !== expectedBuild) {
    throw new Error(`Build stimmt nicht: erwartet ${expectedBuild}, erhalten ${text}`);
  }
}

async function runProfile(browser, baseUrl, profileName, args, results) {
  const context = await createContext(browser, baseUrl, profileName, { consentState: 'denied' });
  const page = await context.newPage();

  const consoleErrors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => consoleErrors.push(error.message));

  try {
    for (const route of ROUTE_CHECKS) {
      await runSingleCheck(results, page, args.outDir, profileName, route.name, route.path, async () => {
        await checkRoute(page, baseUrl, route, args.timeoutMs);
      });
    }

    if (profileName === 'mobile') {
      await runSingleCheck(results, page, args.outDir, profileName, 'Bottom-Tabbar Navigation', '/', async () => {
        await checkBottomNavigation(page, baseUrl, args.timeoutMs);
      });

      await runSingleCheck(results, page, args.outDir, profileName, 'Consent bleibt nach Tabwechsel weg', '/', async () => {
        await checkConsentNavigationResync(browser, baseUrl, profileName, args.timeoutMs);
      });
    }

    const hardConsoleErrors = consoleErrors.filter((entry) => !/favicon|apple-mobile-web-app-capable|beforeinstallprompt|Images loaded lazily/i.test(entry));
    if (hardConsoleErrors.length > 0) {
      results.push({
        profile: profileName,
        name: 'Browser-Konsole',
        path: '',
        status: 'warn',
        error: hardConsoleErrors.slice(0, 3).join(' | '),
      });
    }
  } finally {
    await context.close();
  }
}

async function runSingleCheck(results, page, outDir, profileName, name, routePath, callback) {
  const started = Date.now();
  try {
    await callback();
    results.push({
      profile: profileName,
      name,
      path: routePath,
      status: 'ok',
      durationMs: Date.now() - started,
    });
    console.log(`✅ ${profileName}: ${name}`);
  } catch (error) {
    const screenshot = path.join(outDir, `${slug(profileName)}-${slug(name)}.png`);
    await page.screenshot({ path: screenshot, fullPage: true }).catch(() => {});
    results.push({
      profile: profileName,
      name,
      path: routePath,
      status: 'fail',
      durationMs: Date.now() - started,
      error: error?.message || String(error),
      screenshot,
    });
    console.error(`❌ ${profileName}: ${name}: ${error?.message || error}`);
  }
}

async function main() {
  const args = parseArgs(process.argv);
  const startedAt = new Date().toISOString();
  await ensureOutDir(args.outDir);

  const profiles = args.profile === 'all' ? Object.keys(PROFILES) : [args.profile];
  const results = [];

  if (args.expectedBuild) {
    try {
      await checkBuild(args.baseUrl, args.expectedBuild);
      results.push({ profile: 'http', name: 'Build-Datei', path: '/meta/build.txt', status: 'ok' });
      console.log('✅ http: Build-Datei');
    } catch (error) {
      results.push({
        profile: 'http',
        name: 'Build-Datei',
        path: '/meta/build.txt',
        status: 'fail',
        error: error?.message || String(error),
      });
    }
  }

  const browser = await chromium.launch({ headless: true });
  try {
    for (const profileName of profiles) {
      await runProfile(browser, args.baseUrl, profileName, args, results);
    }
  } finally {
    await browser.close();
  }

  const finishedAt = new Date().toISOString();
  await writeJson(path.join(args.outDir, 'results.json'), { baseUrl: args.baseUrl, startedAt, finishedAt, results });
  await writeSummary(args.outDir, results, startedAt, finishedAt, args.baseUrl);

  const failures = results.filter((entry) => entry.status === 'fail');
  const warnings = results.filter((entry) => entry.status === 'warn');
  const passed = results.filter((entry) => entry.status === 'ok').length;

  console.log(`=== Browser-Smoke Ergebnis: ${passed}/${results.length} OK, ${failures.length} Fehler, ${warnings.length} Warnungen ===`);

  if (failures.length > 0) {
    process.exitCode = 1;
  }
}

main().catch((error) => {
  console.error(`❌ Browser-Smoke konnte nicht ausgeführt werden: ${error?.message || error}`);
  process.exit(1);
});
/* === END FILE: scripts/browser-smoke.mjs === */
