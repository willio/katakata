async function signIn(page) {
  await page.goto('/login');
  await page.locator('input[name="email"]').fill('ci-owner@example.test');
  await page.locator('input[name="password"]').fill('BrowserTestPassword123!');
  await Promise.all([
    page.waitForURL(/\/dashboard/),
    page.getByRole('button', { name: 'Sign in', exact: true }).click(),
  ]);
}

module.exports = { signIn };
