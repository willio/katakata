const { test, expect } = require('@playwright/test');

test.use({
  baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
});

const routes = [
  { name: 'home', path: '/' },
  { name: 'login', path: '/login' },
  { name: 'dashboard', path: '/dashboard', auth: true },
  { name: 'posts', path: '/posts', auth: true },
  { name: 'mail', path: '/mail', auth: true },
  { name: 'settings', path: '/dashboard/settings', auth: true },
];

async function assertStylesLoaded(page) {
  const styles = await page.evaluate(() => Array.from(document.styleSheets).map((sheet) => {
    let ruleCount = -1;
    try { ruleCount = sheet.cssRules.length; } catch {}
    return { href: sheet.href, ruleCount };
  }));
  expect(styles.length, JSON.stringify(styles)).toBeGreaterThan(0);
  for (const style of styles) expect(style.ruleCount, JSON.stringify(styles)).toBeGreaterThan(0);
}

async function assertNoHorizontalOverflow(page) {
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    document: document.documentElement.scrollWidth,
    body: document.body.scrollWidth,
  }));
  expect(dimensions.document, JSON.stringify(dimensions)).toBeLessThanOrEqual(dimensions.viewport + 1);
  expect(dimensions.body, JSON.stringify(dimensions)).toBeLessThanOrEqual(dimensions.viewport + 1);
}

async function assertActiveMailDestinationVisible(page) {
  const geometry = await page.evaluate(() => {
    const sidebar = document.querySelector('.mail-sidebar');
    const active = sidebar?.querySelector('a[aria-current="page"]');
    if (!(sidebar instanceof HTMLElement) || !(active instanceof HTMLElement)) return null;
    const sidebarRect = sidebar.getBoundingClientRect();
    const activeRect = active.getBoundingClientRect();
    return {
      sidebarLeft: sidebarRect.left,
      sidebarRight: sidebarRect.right,
      activeLeft: activeRect.left,
      activeRight: activeRect.right,
    };
  });
  expect(geometry).not.toBeNull();
  expect(geometry.activeLeft, JSON.stringify(geometry)).toBeGreaterThanOrEqual(geometry.sidebarLeft - 1);
  expect(geometry.activeRight, JSON.stringify(geometry)).toBeLessThanOrEqual(geometry.sidebarRight + 1);
}

async function signIn(page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('ci-owner@example.test');
  await page.locator('input[name="password"]').fill('BrowserTestPassword123!');
  await Promise.all([
    page.waitForURL(/\/dashboard/),
    page.getByRole('button', { name: 'Sign in', exact: true }).click(),
  ]);
}

for (const viewport of [
  { label: 'desktop', width: 1440, height: 1000 },
  { label: 'mobile-320', width: 320, height: 844 },
]) {
  test.describe(viewport.label, () => {
    test.use({ viewport: { width: viewport.width, height: viewport.height } });

    for (const route of routes) {
      test(`${route.name} has styled responsive layout`, async ({ page }, testInfo) => {
        if (route.auth) await signIn(page);
        await page.goto(route.path);
        await expect(page.locator('body')).toBeVisible();
        await assertStylesLoaded(page);
        await assertNoHorizontalOverflow(page);
        if (route.name === 'mail' && viewport.width === 320) {
          await assertActiveMailDestinationVisible(page);
        }
        await page.screenshot({
          path: testInfo.outputPath(`${route.name}.png`),
          fullPage: true,
        });
      });
    }
  });
}
