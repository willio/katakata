let playwright;
try {
  playwright = require('@playwright/test');
} catch (error) {
  if (error.code !== 'MODULE_NOT_FOUND') throw error;
  playwright = require('playwright/test');
}

const { expect } = playwright;

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

async function assertReadableMeasure(page, selector, maximumCh = 75) {
  const target = page.locator(selector).first();
  await expect(target).toBeVisible();
  const measure = await target.evaluate((element, limit) => {
    const styles = getComputedStyle(element);
    const probe = document.createElement('div');
    probe.style.position = 'fixed';
    probe.style.visibility = 'hidden';
    probe.style.width = `${limit}ch`;
    probe.style.fontFamily = styles.fontFamily;
    probe.style.fontSize = styles.fontSize;
    document.body.append(probe);
    const maximum = probe.getBoundingClientRect().width;
    probe.remove();
    return {
      actual: element.getBoundingClientRect().width,
      maximum,
      maximumCh: limit,
    };
  }, maximumCh);
  expect(measure.actual, JSON.stringify(measure)).toBeLessThanOrEqual(measure.maximum + 1);
}

module.exports = {
  assertNoHorizontalOverflow,
  assertReadableMeasure,
  assertStylesLoaded,
};
