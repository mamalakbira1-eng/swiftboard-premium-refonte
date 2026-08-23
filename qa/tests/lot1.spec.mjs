import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/lot1');

async function runAxe(page) {
  const result = await new AxeBuilder({ page }).analyze();
  const severe = result.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
  expect(severe, JSON.stringify(severe, null, 2)).toEqual([]);
  return result;
}

test('Lot 1 — tokens, CTA contrast and theme persistence', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/', { waitUntil: 'networkidle' });
  await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'light'));
  await page.reload({ waitUntil: 'networkidle' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  const lightAxe = await runAxe(page);
  await page.screenshot({ path: path.join(outDir, `homepage-light-${testInfo.project.name}.png`), fullPage: true });

  await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'dark'));
  await page.reload({ waitUntil: 'networkidle' });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  const darkAxe = await runAxe(page);
  await page.screenshot({ path: path.join(outDir, `homepage-dark-${testInfo.project.name}.png`), fullPage: true });

  const toggle = page.locator('.theme-toggle');
  await expect(toggle).toBeVisible();
  await toggle.click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');

  await fs.writeFile(
    path.join(outDir, `lot1-${testInfo.project.name}.json`),
    JSON.stringify({
      project: testInfo.project.name,
      lightViolations: lightAxe.violations,
      darkViolations: darkAxe.violations,
      themeAfterToggle: await page.locator('html').getAttribute('data-theme'),
    }, null, 2),
  );
});
