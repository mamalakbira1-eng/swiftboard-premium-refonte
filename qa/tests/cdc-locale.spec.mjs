import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-locale');
const expectedLocale = process.env.SB_EXPECT_LOCALE || (process.env.SB_EXPECT_RTL === '1' ? 'ar' : '');
const expectedDir = process.env.SB_EXPECT_DIR || (expectedLocale === 'ar' ? 'rtl' : 'ltr');

test('CDC — smoke locale et direction document', async ({ page }, testInfo) => {
  test.skip(!expectedLocale, 'Définir SB_EXPECT_LOCALE et SB_EXPECT_DIR sur un snapshot de locale dédié.');
  test.skip(testInfo.project.name !== 'chromium-desktop', 'Smoke locale exécuté une fois sur Chromium desktop.');
  await fs.mkdir(outDir, { recursive: true });
  for (const url of ['/', '/forums/forum/finances/', '/forums/topic/par-ou-commencer-une-epargne-d-urgence/']) {
    await page.goto(url, { waitUntil: 'networkidle' });
    const actualDir = await page.locator('html').getAttribute('dir');
    expect(actualDir || 'ltr').toBe(expectedDir);
    await expect(page.locator('html')).toHaveAttribute('lang', new RegExp(`^${expectedLocale}`));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    expect(overflow, `${url} déborde horizontalement en ${expectedLocale}/${expectedDir}`).toBe(false);
    await expect(page.locator('svg[aria-hidden="true"]:visible').first()).toBeVisible();
    await page.screenshot({ path: path.join(outDir, `${url === '/' ? 'home' : url.split('/').filter(Boolean).join('-')}-${testInfo.project.name}.png`), fullPage: true });
  }
});
