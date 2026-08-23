import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baselineDir = path.resolve('../baseline');
const reportsDir = path.resolve('../reports');

async function ensureDirs() {
  await fs.mkdir(baselineDir, { recursive: true });
  await fs.mkdir(reportsDir, { recursive: true });
}

async function auditPage(page, name, testInfo) {
  const consoleErrors = [];
  const pageErrors = [];
  const onConsole = (message) => {
    if (message.type() === 'error') consoleErrors.push(message.text());
  };
  const onPageError = (error) => pageErrors.push(String(error));
  page.on('console', onConsole);
  page.on('pageerror', onPageError);

  await page.goto('/', { waitUntil: 'networkidle' });
  await expect(page.locator('body')).toBeVisible();
  await page.screenshot({ path: path.join(baselineDir, `${name}-${testInfo.project.name}.png`), fullPage: true });

  const dimensions = await page.evaluate(() => ({
    viewport: window.innerWidth,
    documentWidth: document.documentElement.scrollWidth,
    hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    theme: document.documentElement.getAttribute('data-theme'),
  }));
  expect(dimensions.hasHorizontalOverflow, `${name} has horizontal overflow`).toBe(false);

  const axe = await new AxeBuilder({ page }).analyze();
  const severe = axe.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact));

  return { dimensions, axeViolations: axe.violations, severeCount: severe.length, consoleErrors, pageErrors };
}

test.describe('SwiftBoard runtime baseline', () => {
  test('homepage light and dark modes', async ({ page }, testInfo) => {
    await ensureDirs();
    const results = {};

    await page.goto('/', { waitUntil: 'networkidle' });
    await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'light'));
    results.light = await auditPage(page, 'homepage-light', testInfo);

    await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'dark'));
    await page.reload({ waitUntil: 'networkidle' });
    await page.screenshot({ path: path.join(baselineDir, `homepage-dark-${testInfo.project.name}.png`), fullPage: true });
    const darkState = await page.evaluate(() => ({
      theme: document.documentElement.getAttribute('data-theme'),
      hasHorizontalOverflow: document.documentElement.scrollWidth > window.innerWidth + 1,
    }));
    expect(darkState.theme).toBe('dark');
    expect(darkState.hasHorizontalOverflow).toBe(false);
    const axeDark = await new AxeBuilder({ page }).analyze();
    results.dark = { dimensions: darkState, axeViolations: axeDark.violations };
    results.dark.severeCount = axeDark.violations.filter((v) => ['critical', 'serious'].includes(v.impact)).length;

    const toggle = page.locator('.theme-toggle');
    if (await toggle.count()) {
      await toggle.click();
      await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    }

    await fs.writeFile(
      path.join(reportsDir, `baseline-${testInfo.project.name}.json`),
      JSON.stringify({ project: testInfo.project.name, results }, null, 2),
    );
  });

  test('login page renders', async ({ page }, testInfo) => {
    await ensureDirs();
    await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
    await expect(page.locator('body')).toBeVisible();
    await page.screenshot({ path: path.join(baselineDir, `login-${testInfo.project.name}.png`), fullPage: true });
    const axe = await new AxeBuilder({ page }).analyze();
    expect(axe.violations.filter((v) => ['critical', 'serious'].includes(v.impact))).toEqual([]);
  });
});
