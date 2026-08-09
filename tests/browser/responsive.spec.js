let playwright;
try {
  playwright = require('@playwright/test');
} catch (error) {
  if (error.code !== 'MODULE_NOT_FOUND') throw error;
  playwright = require('playwright/test');
}

const { test, expect } = playwright;
const { signIn } = require('./helpers/auth');
const {
  assertNoHorizontalOverflow,
  assertReadableMeasure,
  assertStylesLoaded,
} = require('./helpers/visual-contract');

test.use({
  baseURL: process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1:8000',
});

const routes = [
  { name: 'home', path: '/', family: 'home' },
  { name: 'login', path: '/login', family: 'owner' },
  { name: 'article', path: '/2026/02/calm-software', family: 'public', measure: '.article-shell' },
  { name: 'archive', path: '/archive', family: 'public', measure: '.page-shell' },
  { name: 'author', path: '/authors/jane-doe', family: 'public', measure: '.page-shell' },
  { name: 'newsletter', path: '/newsletter', family: 'public', measure: '.publication-form' },
  { name: 'dashboard', path: '/dashboard', family: 'owner', auth: true },
  { name: 'posts', path: '/posts', family: 'owner', auth: true },
  { name: 'analytics', path: '/analytics', family: 'owner', auth: true },
  { name: 'mail', path: '/mail?area=inbox', family: 'owner', auth: true },
  { name: 'mailboxes', path: '/dashboard/settings/mailboxes', family: 'owner', auth: true },
  { name: 'mailbox-import', path: '/dashboard/settings/mailboxes/import', family: 'owner', auth: true },
  { name: 'mail-archive', path: '/mail/archive', family: 'owner', auth: true },
  { name: 'sent-mail', path: '/mail/sent', family: 'owner', auth: true },
  { name: 'campaign-workspace', path: '/mail?area=campaigns', family: 'owner', auth: true },
  { name: 'campaigns', path: '/mail/campaigns', family: 'owner', auth: true },
  { name: 'settings', path: '/dashboard/settings', family: 'owner', auth: true },
];

const fixtureStates = [
  {
    name: 'selected-message',
    listingPath: '/mail?area=inbox',
    link: '[data-mail-message-link]',
    ready: '[data-mail-reader] .mail-message',
    fixture: 'a cached reader message',
  },
  {
    name: 'correspondence-editor',
    listingPath: '/mail?area=inbox#mail-drafts',
    link: '#mail-drafts a[href^="/mail/drafts/"][href$="/edit"]',
    ready: '[data-mail-draft-editor]',
    fixture: 'a correspondence draft',
    font: { selector: '#mail-text', token: '--font-serif' },
  },
  {
    name: 'campaign-editor',
    listingPath: '/mail?area=campaigns#campaign-drafts',
    link: '#campaign-drafts a[href^="/mail/campaign-drafts/"]',
    ready: '[data-campaign-draft]',
    fixture: 'a campaign draft',
    font: { selector: '#campaign-body', token: '--font-serif' },
  },
];

async function assertFontRole(page, selector, token) {
  const families = await page.locator(selector).first().evaluate((element, property) => {
    const normalize = (value) => value.split(',').map((family) => family.trim().replace(/^['"]|['"]$/g, '').toLowerCase());
    return {
      actual: normalize(getComputedStyle(element).fontFamily),
      expected: normalize(getComputedStyle(document.documentElement).getPropertyValue(property)),
    };
  }, token);
  expect(families.actual, `${selector} should resolve to ${token}`).toEqual(families.expected);
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

async function openFixtureState(page, state) {
  await page.goto(state.listingPath);
  const link = page.locator(state.link).first();
  await expect(link, `Browser fixtures must provide ${state.fixture}`).toHaveCount(1);
  await link.click();
  await expect(page.locator(state.ready)).toBeVisible();
}

async function blockAutosaveWrites(page) {
  await page.route('**/autosave', (route) => route.abort('blockedbyclient'));
}

async function capture(page, testInfo, name) {
  await page.screenshot({
    path: testInfo.outputPath(`${name}.png`),
    fullPage: true,
  });
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

        if (route.measure) await assertReadableMeasure(page, route.measure);
        if (route.family === 'home') await assertFontRole(page, '.home-lead h1', '--font-serif');
        if (route.family === 'public') await assertFontRole(page, '.publication-title', '--font-serif');
        if (route.family === 'owner') await assertFontRole(page, 'h1', '--font-sans');
        if (route.name === 'mail' && viewport.width === 320) await assertActiveMailDestinationVisible(page);

        await capture(page, testInfo, route.name);
      });
    }

    for (const state of fixtureStates) {
      test(`${state.name} has styled responsive layout`, async ({ page }, testInfo) => {
        await signIn(page);
        if (state.font) await blockAutosaveWrites(page);
        await openFixtureState(page, state);
        await assertStylesLoaded(page);
        await assertNoHorizontalOverflow(page);
        if (state.font) await assertFontRole(page, state.font.selector, state.font.token);
        await capture(page, testInfo, state.name);
      });
    }

    test('editor settings-open state has styled responsive layout', async ({ page }, testInfo) => {
      await signIn(page);
      await blockAutosaveWrites(page);
      await page.goto('/editor/drafts/an-idea-in-progress');
      await page.locator('[data-settings-toggle]').click();
      await expect(page.locator('[data-editor-panel]')).toBeVisible();
      await assertStylesLoaded(page);
      await assertNoHorizontalOverflow(page);
      await assertFontRole(page, 'textarea[name="body"]', '--font-mono');
      await capture(page, testInfo, 'editor-settings-open');
    });
  });
}
