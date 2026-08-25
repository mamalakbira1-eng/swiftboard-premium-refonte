import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-lot9-authenticated');
const username = process.env.SB_VIP_USER || 'sbvip';
const password = process.env.SB_VIP_PASSWORD;

async function login(page) {
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php/);
}

async function dismissLanguagePopup(page) {
  const overlay = page.locator('#sb-lang-popup-overlay');
  try {
    await overlay.waitFor({ state: 'visible', timeout: 2_000 });
    const stay = page.locator('#sb-lang-stay');
    if (await stay.isVisible()) await stay.click();
  } catch (_) {
    // Popup conditionnelle à la langue du navigateur et au cookie de session.
  }
}

async function audit(page, url, label) {
  const issues = { consoleErrors: [], pageErrors: [], failedRequests: [], badResponses: [] };
  page.on('console', msg => { if (msg.type() === 'error') issues.consoleErrors.push(msg.text()); });
  page.on('pageerror', error => issues.pageErrors.push(String(error)));
  page.on('requestfailed', request => {
    // Une navigation ou la fermeture du contexte annule normalement la
    // connexion persistante SSE. Ce n’est pas une erreur réseau produit.
    if (request.url().includes('/wp-json/swiftboard/v1/notifications/stream')) return;
    issues.failedRequests.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' });
  });
  page.on('response', response => { if (response.status() >= 400) issues.badResponses.push({ url: response.url(), status: response.status() }); });
  const response = await page.goto(url, { waitUntil: 'networkidle' });
  expect(response?.status(), label).toBe(200);
  const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
  const overflow = await page.evaluate(() => ({ innerWidth: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }));
  expect(overflow.scrollWidth, `${label} overflow`).toBeLessThanOrEqual(overflow.innerWidth + 1);
  expect(axe.violations, `${label} axe`).toEqual([]);
  return { url: page.url(), status: response?.status(), axeViolations: axe.violations, axeExcluded: ['#wpadminbar (chrome WordPress tiers)'], overflow, issues };
}

test('Lot 9 — états membre, notifications, focus et thème sans flash', async ({ page }, testInfo) => {
  expect(password, 'SB_VIP_PASSWORD requis pour la passe authentifiée locale.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await login(page);

  const results = {};
  results.home = await audit(page, '/', 'home-auth');
  await dismissLanguagePopup(page);
  const menuToggle = page.locator('.sb-user-menu-toggle');
  await expect(menuToggle).toBeVisible();
  await menuToggle.focus();
  await expect(menuToggle).toBeFocused();
  await menuToggle.click();
  await expect(page.locator('.sb-user-menu')).toHaveClass(/is-open/);
  await expect(page.locator('.sb-user-menu-list [role="menuitem"]').first()).toBeFocused();
  await page.keyboard.press('Escape');
  await expect(page.locator('.sb-user-menu')).not.toHaveClass(/is-open/);
  await expect(menuToggle).toBeFocused();

  const bell = page.locator('#sb-notif-bell');
  await expect(bell).toBeVisible();
  const bellButton = bell.locator('.sb-notif-btn');
  await bellButton.focus();
  await expect(bellButton).toBeFocused();
  await bellButton.click();
  await expect(bell.locator('.sb-notif-dropdown')).toBeVisible();
  await expect(bellButton).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Escape');
  await expect(bellButton).toHaveAttribute('aria-expanded', 'false');
  await expect(bellButton).toBeFocused();
  await bellButton.click();
  const notificationList = bell.locator('.sb-notif-list');
  await expect(notificationList).toBeVisible();
  await expect(notificationList.locator('.sb-notif-empty, .sb-notif-item')).toHaveCount(await notificationList.locator('.sb-notif-empty, .sb-notif-item').count());
  const emptyState = await notificationList.locator('.sb-notif-empty').count();
  const notificationItems = await notificationList.locator('.sb-notif-item').count();
  expect(emptyState + notificationItems).toBeGreaterThan(0);
  results.notifications = { status: 'PASS', dropdown: true, emptyState, notificationItems, list: await notificationList.innerText() };
  await page.screenshot({ path: path.join(outDir, `notifications-${testInfo.project.name}.png`), fullPage: true });

  results.profileNotifications = await audit(page, '/forums/users/sbvip/?tab=notifications', 'profile-notifications');
  await page.screenshot({ path: path.join(outDir, `profile-notifications-${testInfo.project.name}.png`), fullPage: true });

  await page.addInitScript(() => localStorage.setItem('swiftboard-theme', 'dark'));
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  const immediateTheme = await page.locator('html').getAttribute('data-theme');
  expect(immediateTheme).toBe('dark');
  await page.waitForLoadState('networkidle');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  results.themeNoFlash = { status: 'PASS', domContentLoaded: immediateTheme, final: await page.locator('html').getAttribute('data-theme') };
  await page.screenshot({ path: path.join(outDir, `theme-dark-${testInfo.project.name}.png`), fullPage: true });

  for (const value of Object.values(results)) {
    if (value?.issues) expect(value.issues, JSON.stringify(value.issues)).toEqual({ consoleErrors: [], pageErrors: [], failedRequests: [], badResponses: [] });
  }
  await fs.writeFile(path.join(outDir, `${testInfo.project.name}.json`), JSON.stringify({ project: testInfo.project.name, results }, null, 2));
});


test('Lot 9 — état vide notifications sur compte frais', async ({ page }, testInfo) => {
  const emptyUser = process.env.SB_EMPTY_USER || 'sbempty';
  const emptyPassword = process.env.SB_EMPTY_PASSWORD;
  expect(emptyPassword, 'SB_EMPTY_PASSWORD requis pour la preuve d’état vide.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(emptyUser);
  await page.locator('#user_pass').fill(emptyPassword);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php/);

  await page.goto('/', { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  const bell = page.locator('#sb-notif-bell');
  const bellButton = bell.locator('.sb-notif-btn');
  await expect(bellButton).toBeVisible();
  const notificationsResponse = page.waitForResponse(response => response.url().includes('/wp-json/swiftboard/v1/notifications?') && response.request().method() === 'GET');
  await bellButton.click();
  await expect(bell.locator('.sb-notif-dropdown')).toBeVisible();
  expect((await notificationsResponse).status()).toBe(200);
  await expect(bell.locator('.sb-notif-empty')).toHaveText('Aucune notification');
  await expect(bell.locator('.sb-notif-item')).toHaveCount(0);
  await expect(bellButton).toHaveAttribute('aria-expanded', 'true');
  const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
  expect(axe.violations, JSON.stringify(axe.violations)).toEqual([]);
  await page.screenshot({ path: path.join(outDir, `notifications-empty-${testInfo.project.name}.png`), fullPage: true });
  await page.keyboard.press('Escape');
  await expect(bellButton).toHaveAttribute('aria-expanded', 'false');
  await expect(bellButton).toBeFocused();
  await fs.writeFile(path.join(outDir, `${testInfo.project.name}-empty.json`), JSON.stringify({
    project: testInfo.project.name,
    status: 'PASS',
    state: 'empty',
    axeViolations: axe.violations,
    excluded: ['#wpadminbar (chrome WordPress tiers)']
  }, null, 2));
});
