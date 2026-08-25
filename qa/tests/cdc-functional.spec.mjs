import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-functional');
const topicPath = process.env.SB_FUNCTIONAL_TOPIC_PATH || '/forums/topic/par-ou-commencer-une-epargne-d-urgence/';
const profilePath = process.env.SB_PROFILE_PATH || '/forums/users/sbvip/';
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

async function dismissLanguagePopup(page, waitMs = 2000) {
  const overlay = page.locator('#sb-lang-popup-overlay');
  await overlay.waitFor({ state: 'attached', timeout: waitMs }).catch(() => {});
  if (await overlay.count() && await overlay.isVisible().catch(() => false)) {
    const stayButton = page.locator('#sb-lang-stay');
    if (await stayButton.count() && await stayButton.isVisible().catch(() => false)) await stayButton.click();
    await expect(overlay).toBeHidden();
  }
}

test('CDC — publication réelle d’une réponse bbPress', async ({ page }, testInfo) => {
  expect(testUser, 'SB_TEST_USER requis pour la recette authentifiée.').toBeTruthy();
  expect(testPassword, 'SB_TEST_PASSWORD requis pour la recette authentifiée.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await login(page, testUser, testPassword);
  const text = `Réponse QA ${new Date().toISOString()}`;
  await page.goto(topicPath, { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  const form = page.locator('#new-post');
  await expect(form).toBeVisible();
  await page.locator('#bbp_reply_content').fill(text);
  // Chaque projet Playwright réutilise le compte QA ; respecter le throttle
  // bbPress de 10 s entre deux publications au lieu de neutraliser ce contrôle.
  await page.waitForTimeout(11_000);
  const submitResponsePromise = page.waitForResponse(response => (
    response.request().method() === 'POST' && response.url().includes('/forums/topic/')
  ));
  await page.locator('#bbp_reply_submit').click();
  // Capturer la redirection HTTP de bbPress, car WebKit mobile peut conserver
  // l’URL précédente malgré le 302 suivi. Le permalink exact prouve ensuite
  // la persistance visible du reply publié.
  const submitResponse = await submitResponsePromise;
  expect(submitResponse.status()).toBe(302);
  const location = submitResponse.headers().location || '';
  expect(location).toMatch(/#post-\d+$/);
  const replyId = location.match(/#post-(\d+)$/)?.[1];
  expect(replyId).toBeTruthy();
  await page.goto(`/?p=${replyId}`, { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  await expect(page.locator('body')).toContainText(text);
  await page.screenshot({ path: path.join(outDir, `comment-${testInfo.project.name}.png`), fullPage: true });
});

test('CDC — profil VIP visible et badge accessible', async ({ page }, testInfo) => {
  expect(vipUser, 'SB_VIP_USER requis pour la recette VIP.').toBeTruthy();
  expect(vipPassword, 'SB_VIP_PASSWORD requis pour la recette VIP.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await login(page, vipUser, vipPassword);
  await page.goto(profilePath, { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  await expect(page.locator('.sb-profile')).toBeVisible();
  await expect(page.locator('.sb-profile-grade')).toBeVisible();
  await expect(page.locator('.sb-profile-tabs')).toBeVisible();
  await page.screenshot({ path: path.join(outDir, `vip-profile-${testInfo.project.name}.png`), fullPage: true });
});

test('CDC — contrat clavier et lien d’évitement', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/', { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
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


test('CDC — actions authentifiées vote sauvegarde et overflow', async ({ page }, testInfo) => {
  test.setTimeout(140_000);
  expect(testUser, 'SB_TEST_USER requis pour la recette authentifiée.').toBeTruthy();
  expect(testPassword, 'SB_TEST_PASSWORD requis pour la recette authentifiée.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await login(page, testUser, testPassword);

  await page.goto('/', { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  const voteButton = page.locator('.sb-post-votes .sb-vote-btn.up:visible, .sb-comment-votes .sb-comment-vote-btn.up:visible').first();
  await expect(voteButton).toBeVisible();
  await expect(voteButton).toHaveAttribute('data-post-id', /\d+/);
  // Les projets Playwright partagent le compte QA ; attendre l’intervalle
  // métier avant le premier vote, comme déjà fait entre ajout et retrait.
  await page.waitForTimeout(6_000);
  const voteResponse = page.waitForResponse(response => response.url().includes('/wp-json/swiftboard/v1/vote') && response.request().method() === 'POST');
  await voteButton.click();
  const votePayload = await voteResponse;
  expect(votePayload.status()).toBe(200);
  await expect(voteButton).toHaveAttribute('aria-pressed', 'true');
  await expect(voteButton).toHaveClass(/is-active/);

  // La route applique l’intervalle métier du grade Rookie (5 secondes).
  // Une seconde de marge rend la preuve stable sans contourner la limitation.
  await page.waitForTimeout(6_000);
  const removeResponse = page.waitForResponse(response => response.url().includes('/wp-json/swiftboard/v1/vote') && response.request().method() === 'POST');
  await voteButton.click();
  expect((await removeResponse).status()).toBe(200);
  await expect(voteButton).toHaveAttribute('aria-pressed', 'false');
  await expect(voteButton).not.toHaveClass(/is-active/);

  const moreToggle = page.locator('.sb-more-toggle:visible').first();
  await expect(moreToggle).toBeVisible();
  await moreToggle.click();
  await expect(moreToggle).toHaveAttribute('aria-expanded', 'true');
  await expect(moreToggle.locator('..').locator('.sb-more-menu')).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(moreToggle).toHaveAttribute('aria-expanded', 'false');

  await page.goto(topicPath, { waitUntil: 'networkidle' });
  await dismissLanguagePopup(page);
  const saveButton = page.locator('.sb-action-save:visible').first();
  await expect(saveButton).toBeVisible();
  const saveResponse = page.waitForResponse(response => response.url().includes('/wp-json/swiftboard/v1/user-action') && response.request().method() === 'POST');
  await saveButton.click();
  expect((await saveResponse).status()).toBe(200);
  await expect(saveButton).toHaveClass(/active/);
  await expect(saveButton).toHaveAttribute('aria-pressed', 'true');
  await expect(saveButton).toHaveAttribute('aria-label', /Sauvegardé|Saved/i);
  const unsaveResponse = page.waitForResponse(response => response.url().includes('/wp-json/swiftboard/v1/user-action') && response.request().method() === 'POST');
  await saveButton.click();
  expect((await unsaveResponse).status()).toBe(200);
  await expect(saveButton).not.toHaveClass(/active/);
  await expect(saveButton).toHaveAttribute('aria-pressed', 'false');
  await expect(saveButton).toHaveAttribute('aria-label', /Sauvegarder|Save/i);

  await page.screenshot({ path: path.join(outDir, `authenticated-actions-${testInfo.project.name}.png`), fullPage: true });
});
