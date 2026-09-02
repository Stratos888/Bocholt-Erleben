#!/usr/bin/env node
import fs from 'node:fs/promises';
import path from 'node:path';
import { chromium } from 'playwright';

const VIEWPORTS = [
  { name: 'desktop-1920', width: 1920, height: 1080 },
  { name: 'desktop-1440', width: 1440, height: 900 },
  { name: 'desktop-1280', width: 1280, height: 800 },
  { name: 'desktop-1044', width: 1044, height: 900 },
  { name: 'mobile-390', width: 390, height: 844 },
  { name: 'mobile-412', width: 412, height: 915 },
];

function parseArgs(argv) {
  const args = { baseUrl: '', outDir: 'artifacts/weekend-route' };
  for (let i = 2; i < argv.length; i += 1) {
    if (argv[i] === '--base-url') {
      args.baseUrl = String(argv[i + 1] || '').replace(/\/+$/, '');
      i += 1;
    } else if (argv[i] === '--out-dir') {
      args.outDir = String(argv[i + 1] || args.outDir);
      i += 1;
    }
  }
  if (!/^https?:\/\//.test(args.baseUrl)) throw new Error('--base-url fehlt oder ist ungültig');
  return args;
}

function absoluteUrl(baseUrl, routePath) {
  return new URL(routePath, `${baseUrl}/`).toString();
}

async function waitForWeekendReady(page) {
  await page.locator('#filter-time-value').waitFor({ state: 'visible', timeout: 12000 });
  await page.waitForFunction(() => {
    const value = document.getElementById('filter-time-value')?.textContent?.trim();
    const cards = document.querySelectorAll('#event-cards .event-card').length;
    return value === 'Dieses Wochenende' && cards >= 1;
  }, null, { timeout: 12000 });
}

async function assertNoHorizontalOverflow(page, label) {
  const dimensions = await page.evaluate(() => ({
    innerWidth: window.innerWidth,
    htmlScrollWidth: document.documentElement.scrollWidth,
    bodyScrollWidth: document.body?.scrollWidth || 0,
  }));
  const maxWidth = Math.max(dimensions.htmlScrollWidth, dimensions.bodyScrollWidth);
  if (maxWidth > dimensions.innerWidth + 2) {
    throw new Error(`${label}: horizontal overflow ${maxWidth}px > ${dimensions.innerWidth}px`);
  }
}

async function assertInitialWeekendState(page, viewport) {
  const timeValue = (await page.locator('#filter-time-value').textContent())?.trim();
  if (timeValue !== 'Dieses Wochenende') {
    throw new Error(`${viewport.name}: expected Weekend default, got ${timeValue}`);
  }

  const reset = page.locator('#filter-reset-pill');
  if (!(await reset.isHidden())) {
    throw new Error(`${viewport.name}: reset X must be hidden for the route default`);
  }

  const bridge = page.locator('.weekend-route-bridge__link');
  if (!(await bridge.isVisible())) throw new Error(`${viewport.name}: Alle Veranstaltungen bridge missing`);
  if ((await bridge.getAttribute('href')) !== '/events/') {
    throw new Error(`${viewport.name}: bridge must target /events/`);
  }

  const feedText = await page.locator('#event-cards').innerText();
  if (!feedText.includes('Weekend Fixture Freitag') || !feedText.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: Weekend fixture events missing from default feed`);
  }
  if (feedText.includes('Außerhalb Weekend Fixture')) {
    throw new Error(`${viewport.name}: non-weekend event leaked into Weekend default feed`);
  }
  if (/DIESE WOCHE/i.test(feedText)) {
    throw new Error(`${viewport.name}: Weekend default feed contains a Diese-Woche group`);
  }

  if (viewport.width >= 900) {
    const title = page.locator('.desktop-entry__title');
    if (!(await title.isVisible())) throw new Error(`${viewport.name}: desktop Weekend H1 not visible`);
    const box = await title.boundingBox();
    if (!box || box.height > 155) {
      throw new Error(`${viewport.name}: Weekend H1 is too tall (${box?.height ?? 'missing'}px)`);
    }
  }

  await assertNoHorizontalOverflow(page, viewport.name);
}

async function assertResetReturnsToRouteDefault(page, viewport) {
  const search = page.locator('#search-filter');
  await search.fill('Weekend Fixture Freitag');
  await page.waitForFunction(() => !document.getElementById('filter-reset-pill')?.hidden);

  const filteredText = await page.locator('#event-cards').innerText();
  if (!filteredText.includes('Weekend Fixture Freitag') || filteredText.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: search did not narrow the Weekend feed as expected`);
  }

  await page.locator('#filter-reset-pill').click();
  await page.waitForFunction(() => {
    const searchValue = document.getElementById('search-filter')?.value || '';
    const timeValue = document.getElementById('filter-time-value')?.textContent?.trim();
    const resetHidden = document.getElementById('filter-reset-pill')?.hidden === true;
    return searchValue === '' && timeValue === 'Dieses Wochenende' && resetHidden;
  });

  const resetFeed = await page.locator('#event-cards').innerText();
  if (!resetFeed.includes('Weekend Fixture Freitag') || !resetFeed.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: reset did not restore Weekend feed`);
  }
}

async function assertSectionRootNavigation(page, baseUrl, viewport) {
  const navSelector = viewport.width >= 900
    ? '#desktop-section-nav-root [data-tab-key="events"]'
    : '#bottom-tabbar-root [data-tab-key="events"]';

  const nav = page.locator(navSelector);
  await nav.waitFor({ state: 'visible', timeout: 6000 });
  if ((await nav.getAttribute('href')) !== '/events/') {
    throw new Error(`${viewport.name}: active Events nav does not target section root`);
  }

  await nav.click();
  await page.waitForURL((url) => url.pathname === '/events/', { timeout: 8000 });
  await page.locator('#filter-time-value').waitFor({ state: 'visible' });
  const normalDefault = (await page.locator('#filter-time-value').textContent())?.trim();
  if (normalDefault !== 'Alle') {
    throw new Error(`${viewport.name}: /events/ default changed from Alle to ${normalDefault}`);
  }

  await page.goto(absoluteUrl(baseUrl, '/events/wochenende/'), { waitUntil: 'domcontentloaded' });
  await waitForWeekendReady(page);
  await page.locator('.weekend-route-bridge__link').click();
  await page.waitForURL((url) => url.pathname === '/events/', { timeout: 8000 });
}

async function main() {
  const args = parseArgs(process.argv);
  await fs.mkdir(args.outDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });
  const results = [];

  try {
    for (const viewport of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        isMobile: viewport.width < 900,
        hasTouch: viewport.width < 900,
        baseURL: args.baseUrl,
      });

      await context.addInitScript(() => {
        try {
          window.localStorage.setItem('be_statistics_consent_v1', 'denied');
        } catch (_) {}
      });

      const page = await context.newPage();
      const pageErrors = [];
      page.on('pageerror', (error) => pageErrors.push(String(error?.message || error)));

      try {
        await page.goto(absoluteUrl(args.baseUrl, '/events/wochenende/'), { waitUntil: 'domcontentloaded' });
        await page.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await waitForWeekendReady(page);
        await assertInitialWeekendState(page, viewport);
        await assertResetReturnsToRouteDefault(page, viewport);

        await page.screenshot({
          path: path.join(args.outDir, `${viewport.name}.png`),
          fullPage: true,
        });

        await assertSectionRootNavigation(page, args.baseUrl, viewport);

        if (pageErrors.length) {
          throw new Error(`${viewport.name}: page errors: ${pageErrors.join(' | ')}`);
        }

        results.push({ viewport: viewport.name, status: 'ok' });
        console.log(`WEEKEND_ROUTE_BROWSER: ${viewport.name} OK`);
      } finally {
        await context.close();
      }
    }
  } finally {
    await browser.close();
  }

  await fs.writeFile(
    path.join(args.outDir, 'results.json'),
    `${JSON.stringify(results, null, 2)}\n`,
    'utf8',
  );

  console.log(`WEEKEND_ROUTE_BROWSER: ${results.length}/${VIEWPORTS.length} OK`);
}

main().catch((error) => {
  console.error(`WEEKEND_ROUTE_BROWSER: FAIL: ${error?.stack || error}`);
  process.exit(1);
});
