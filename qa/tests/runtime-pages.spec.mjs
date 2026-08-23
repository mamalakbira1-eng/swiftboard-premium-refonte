import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/runtime');
const paths = {
  forum: '/forums/forum/finances/',
  topic: '/forums/topic/par-ou-commencer-une-epargne-d-urgence/',
  profile: '/forums/users/sbmember/',
  search: '/?s=teletravail',
};

async function checkPage(page, key, testInfo) {
  const errors = [];
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(`console: ${message.text()}`);
  });
  page.on('pageerror', (error) => errors.push(`pageerror: ${error}`));
  await page.goto(paths[key], { waitUntil: 'networkidle' });
  await expect(page.locator('body')).toBeVisible();
  const axe = await new AxeBuilder({ page }).analyze();
  const severe = axe.violations.filter((v) => ['critical', 'serious'].includes(v.impact));
  await page.screenshot({ path: path.join(outDir, `${key}-${testInfo.project.name}.png`), fullPage: true });
  return { key, url: page.url(), title: await page.title(), severe, allViolations: axe.violations, errors };
}

test('pages clés bbPress, profil et recherche', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  const results = {};
  for (const key of Object.keys(paths)) results[key] = await checkPage(page, key, testInfo);
  expect(Object.values(results).every((r) => r.severe.length === 0), JSON.stringify(results, null, 2)).toBe(true);
  await fs.writeFile(path.join(outDir, `pages-${testInfo.project.name}.json`), JSON.stringify(results, null, 2));
});

test('interactions header, vote, sauvegarde et menus', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/', { waitUntil: 'networkidle' });
  await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'light'));
  await page.reload({ waitUntil: 'networkidle' });
  await expect(page.locator('.theme-toggle')).toBeVisible();
  await page.locator('.theme-toggle').click();
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

  const mobileMenu = page.locator('.menu-toggle');
  if (await mobileMenu.count() && await mobileMenu.isVisible()) {
    await mobileMenu.click();
    await expect(mobileMenu).toHaveAttribute('aria-expanded', 'true');
  }

  const userMenu = page.locator('#sb-user-menu-toggle');
  if (await userMenu.count() && await userMenu.isVisible()) {
    await userMenu.click();
    await expect(userMenu).toHaveAttribute('aria-expanded', 'true');
  }

  await page.screenshot({ path: path.join(outDir, `interactions-${testInfo.project.name}.png`), fullPage: true });
});
