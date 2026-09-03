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
  await page.locator('#event-cards .event-card').first().waitFor({ state: 'visible', timeout: 12000 });
  await page.waitForFunction(() => {
    const cards = document.querySelectorAll('#event-cards .event-card').length;
    const timeKey = window.FilterModule?.filters?.zeitraum;
    return cards >= 1 && timeKey === 'weekend';
  }, null, { timeout: 12000 });
}

async function waitForEventsRootReady(page) {
  await page.locator('#filter-time-value').waitFor({ state: 'visible', timeout: 12000 });
  await page.waitForFunction(() => {
    const value = document.getElementById('filter-time-value')?.textContent?.trim();
    const cards = document.querySelectorAll('#event-cards .event-card').length;
    return value === 'Alle' && cards >= 1;
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

async function assertWeekendSingleFilterGeometry(page, viewport, resetVisible = false) {
  const searchBox = await page.locator('.desktop-hero__search-slot').boundingBox();
  const categoryBox = await page.locator('#filter-category-pill').boundingBox();
  if (!searchBox || !categoryBox) {
    throw new Error(`${viewport.name}: Weekend search/category geometry unavailable`);
  }

  const tolerance = 4;
  if (Math.abs(categoryBox.x - searchBox.x) > tolerance) {
    throw new Error(`${viewport.name}: Weekend category must align left with search (${categoryBox.x}px vs ${searchBox.x}px)`);
  }
  if (categoryBox.y <= searchBox.y + tolerance) {
    throw new Error(`${viewport.name}: Weekend category must occupy the filter row below search`);
  }

  if (!resetVisible) {
    if (Math.abs(categoryBox.width - searchBox.width) > tolerance) {
      throw new Error(`${viewport.name}: Weekend category must use full single-filter width (${categoryBox.width}px vs search ${searchBox.width}px)`);
    }
    return;
  }

  const resetBox = await page.locator('#filter-reset-pill').boundingBox();
  if (!resetBox) {
    throw new Error(`${viewport.name}: Weekend reset geometry unavailable after active filter state`);
  }
  if (Math.abs(resetBox.y - categoryBox.y) > tolerance) {
    throw new Error(`${viewport.name}: Weekend reset must share the category filter row`);
  }
  const searchRight = searchBox.x + searchBox.width;
  const resetRight = resetBox.x + resetBox.width;
  if (Math.abs(resetRight - searchRight) > tolerance) {
    throw new Error(`${viewport.name}: Weekend category + reset row must end with search width (${resetRight}px vs ${searchRight}px)`);
  }
}

async function assertWeekendRouteInvariant(page, viewport) {
  const timePill = page.locator('#filter-time-pill');
  if (!(await timePill.isHidden())) {
    throw new Error(`${viewport.name}: Weekend route must not render a time pill`);
  }

  if (!(await page.locator('#sheet-time').isHidden())) {
    throw new Error(`${viewport.name}: Weekend route must not expose the time sheet`);
  }
  if (!(await page.locator('#popover-time').isHidden())) {
    throw new Error(`${viewport.name}: Weekend route must not expose the time popover`);
  }

  const routeState = await page.evaluate(() => ({
    defaultTime: document.body?.dataset?.eventTimeDefault || '',
    locked: document.body?.dataset?.eventTimeLocked || '',
    activeTime: window.FilterModule?.filters?.zeitraum || '',
  }));
  if (routeState.defaultTime !== 'weekend' || routeState.locked !== 'true' || routeState.activeTime !== 'weekend') {
    throw new Error(`${viewport.name}: Weekend route invariant mismatch ${JSON.stringify(routeState)}`);
  }

  const title = page.locator('.desktop-entry__title');
  const lead = page.locator('.desktop-entry__text');
  if (!(await title.isVisible())) throw new Error(`${viewport.name}: Weekend H1 not visible`);
  if (!(await lead.isVisible())) throw new Error(`${viewport.name}: Weekend lead not visible`);

  const redundantParentNavigation = await page.locator('.weekend-route-bridge, .weekend-route-bridge__link').count();
  if (redundantParentNavigation !== 0) {
    throw new Error(`${viewport.name}: Weekend route must rely on central Events navigation, not a redundant parent link`);
  }

  const titleBox = await title.boundingBox();
  if (!titleBox) throw new Error(`${viewport.name}: Weekend H1 geometry unavailable`);
  if (viewport.width < 900 && titleBox.height > 76) {
    throw new Error(`${viewport.name}: mobile Weekend H1 is too tall (${titleBox.height}px)`);
  }
  if (viewport.width >= 900 && titleBox.height > 155) {
    throw new Error(`${viewport.name}: desktop Weekend H1 is too tall (${titleBox.height}px)`);
  }

  const visibleSectionTitles = await page.locator('#event-cards .events-section-title:visible').count();
  if (visibleSectionTitles !== 0) {
    throw new Error(`${viewport.name}: Weekend feed must not repeat a visible relative-time heading`);
  }
}

async function assertInitialWeekendState(page, viewport) {
  const reset = page.locator('#filter-reset-pill');
  if (!(await reset.isHidden())) {
    throw new Error(`${viewport.name}: reset X must be hidden for the route default`);
  }

  const feedText = await page.locator('#event-cards').innerText();
  if (!feedText.includes('Weekend Fixture Freitag') || !feedText.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: Weekend fixture events missing from default feed`);
  }
  if (feedText.includes('Außerhalb Weekend Fixture')) {
    throw new Error(`${viewport.name}: non-weekend event leaked into Weekend feed`);
  }

  await assertWeekendRouteInvariant(page, viewport);
  await assertWeekendSingleFilterGeometry(page, viewport, false);
  await assertNoHorizontalOverflow(page, viewport.name);
}

async function assertResetReturnsToRouteDefault(page, viewport) {
  const search = page.locator('#search-filter');
  await search.fill('Weekend Fixture Freitag');
  await page.waitForFunction(() => !document.getElementById('filter-reset-pill')?.hidden);
  await assertWeekendSingleFilterGeometry(page, viewport, true);

  const filteredText = await page.locator('#event-cards').innerText();
  if (!filteredText.includes('Weekend Fixture Freitag') || filteredText.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: search did not narrow the Weekend feed as expected`);
  }

  await page.locator('#filter-reset-pill').click();
  await page.waitForFunction(() => {
    const searchValue = document.getElementById('search-filter')?.value || '';
    const timeKey = window.FilterModule?.filters?.zeitraum;
    const resetHidden = document.getElementById('filter-reset-pill')?.hidden === true;
    return searchValue === '' && timeKey === 'weekend' && resetHidden;
  });

  const resetFeed = await page.locator('#event-cards').innerText();
  if (!resetFeed.includes('Weekend Fixture Freitag') || !resetFeed.includes('Weekend Fixture Samstag')) {
    throw new Error(`${viewport.name}: reset did not restore Weekend feed`);
  }
}

async function assertCategoryRemainsSharedFilter(page, viewport) {
  await page.locator('#filter-category-pill').click();
  const optionSelector = viewport.width >= 900
    ? '#popover-category [data-category="Musik & Bühne"]'
    : '#sheet-category [data-category="Musik & Bühne"]';
  const option = page.locator(optionSelector);
  await option.waitFor({ state: 'visible', timeout: 6000 });
  await option.click();

  await page.waitForFunction(() => {
    const feed = document.getElementById('event-cards')?.innerText || '';
    return feed.includes('Weekend Fixture Freitag') && !feed.includes('Weekend Fixture Samstag');
  }, null, { timeout: 6000 });

  const timeKey = await page.evaluate(() => window.FilterModule?.filters?.zeitraum || '');
  if (timeKey !== 'weekend') {
    throw new Error(`${viewport.name}: category filter changed Weekend route invariant to ${timeKey}`);
  }

  await page.locator('#filter-reset-pill').click();
  await page.waitForFunction(() => {
    const feed = document.getElementById('event-cards')?.innerText || '';
    const time = window.FilterModule?.filters?.zeitraum;
    return time === 'weekend' && feed.includes('Weekend Fixture Freitag') && feed.includes('Weekend Fixture Samstag');
  }, null, { timeout: 6000 });
}

async function assertSectionRootNavigation(page, viewport) {
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
  await waitForEventsRootReady(page);
}

async function assertWeekendSelectionUsesCanonicalAnchor(page, baseUrl, viewport) {
  await page.goto(absoluteUrl(baseUrl, '/events/'), { waitUntil: 'domcontentloaded' });
  await waitForEventsRootReady(page);

  await page.locator('#filter-time-pill').click();
  const optionSelector = viewport.width >= 900
    ? '#popover-time a[data-time-route="weekend"][href="/events/wochenende/"]'
    : '#sheet-time a[data-time-route="weekend"][href="/events/wochenende/"]';
  const option = page.locator(optionSelector);
  await option.waitFor({ state: 'visible', timeout: 6000 });

  const tagName = await option.evaluate((node) => node.tagName);
  if (tagName !== 'A') {
    throw new Error(`${viewport.name}: Weekend preset must be a real anchor, got ${tagName}`);
  }

  await option.click();
  await page.waitForURL((url) => url.pathname === '/events/wochenende/' && url.search === '', { timeout: 8000 });
  await waitForWeekendReady(page);
  await assertWeekendRouteInvariant(page, viewport);
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
        await assertCategoryRemainsSharedFilter(page, viewport);

        await page.screenshot({
          path: path.join(args.outDir, `${viewport.name}.png`),
          fullPage: true,
        });

        await assertSectionRootNavigation(page, viewport);
        await assertWeekendSelectionUsesCanonicalAnchor(page, args.baseUrl, viewport);
        await assertNoHorizontalOverflow(page, `${viewport.name}-canonical-weekend`);

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
