import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/multisite');

test('CDC — multisite principal et sous-site', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  const failures = [];
  page.on('console', msg => { if (msg.type() === 'error') failures.push(`console: ${msg.text()}`); });
  page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
  page.on('requestfailed', request => failures.push(`requestfailed: ${request.url()} ${request.failure()?.errorText || ''}`));
  for (const [label, pathName] of [['main', '/'], ['community', '/community/']]) {
    const response = await page.goto(pathName, { waitUntil: 'networkidle' });
    expect(response?.status(), label).toBe(200);
    await expect(page.locator('body')).toHaveClass(/wp-theme-swiftboard/);
    await expect(page.locator('main, [role="main"]')).toHaveCount(1);
    const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
    expect(axe.violations, JSON.stringify(axe.violations)).toEqual([]);
    await page.screenshot({ path: path.join(outDir, `${label}-${testInfo.project.name}.png`), fullPage: true });
  }
  expect(failures, failures.join('\n')).toEqual([]);
});
