import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-functional');
const topicPath = '/forums/topic/par-ou-commencer-une-epargne-d-urgence/';
const testUser = process.env.SB_TEST_USER;
const testPassword = process.env.SB_TEST_PASSWORD;
const vipUser = process.env.SB_VIP_USER;
const vipPassword = process.env.SB_VIP_PASSWORD;

async function login(page, username, password) {
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php/);
}

function onlyChromiumDesktop(testInfo) {
  test.skip(testInfo.project.name !== 'chromium-desktop', 'Mutation métier exécutée une seule fois sur Chromium desktop.');
}

test('CDC — publication réelle d’une réponse bbPress', async ({ page }, testInfo) => {
  onlyChromiumDesktop(testInfo);
  test.skip(!testUser || !testPassword, 'Définir SB_TEST_USER et SB_TEST_PASSWORD pour la recette authentifiée.');
  await fs.mkdir(outDir, { recursive: true });
  await login(page, testUser, testPassword);
  const text = `Réponse QA ${new Date().toISOString()}`;
  await page.goto(topicPath, { waitUntil: 'networkidle' });
  const form = page.locator('#new-post');
  await expect(form).toBeVisible();
  await page.locator('#bbp_reply_content').fill(text);
  await page.locator('#bbp_reply_submit').click();
  await expect(page.locator('body')).toContainText(text);
  await page.screenshot({ path: path.join(outDir, `comment-${testInfo.project.name}.png`), fullPage: true });
});

test('CDC — profil VIP visible et badge accessible', async ({ page }, testInfo) => {
  onlyChromiumDesktop(testInfo);
  test.skip(!vipUser || !vipPassword, 'Définir SB_VIP_USER et SB_VIP_PASSWORD pour la recette VIP.');
  await fs.mkdir(outDir, { recursive: true });
  await login(page, vipUser, vipPassword);
  await page.goto('/forums/users/sbvip/', { waitUntil: 'networkidle' });
  await expect(page.locator('.sb-profile')).toBeVisible();
  await expect(page.locator('.sb-profile-grade')).toBeVisible();
  await expect(page.locator('.sb-profile-tabs')).toBeVisible();
  await page.screenshot({ path: path.join(outDir, `vip-profile-${testInfo.project.name}.png`), fullPage: true });
});

test('CDC — contrat clavier et lien d’évitement', async ({ page }, testInfo) => {
  onlyChromiumDesktop(testInfo);
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/', { waitUntil: 'networkidle' });
  const skip = page.locator('.skip-link');
  await expect(skip).toHaveAttribute('href', '#main-content');
  await expect(page.locator('#main-content')).toHaveCount(1);
  await skip.focus();
  await expect(skip).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('#main-content')).toBeVisible();
  await page.locator('.theme-toggle').focus();
  await expect(page.locator('.theme-toggle')).toBeFocused();
  await page.keyboard.press('Enter');
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  await page.screenshot({ path: path.join(outDir, `keyboard-${testInfo.project.name}.png`), fullPage: true });
});
