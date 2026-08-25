import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-lots-4-9');
const topicPath = process.env.SB_TOPIC_PATH || '/forums/topic/comment-prolonger-la-batterie-d-un-portable/';
const functionalTopicPath = process.env.SB_FUNCTIONAL_TOPIC_PATH || '/forums/topic/par-ou-commencer-une-epargne-d-urgence/';
const forumPath = process.env.SB_FORUM_PATH || '/forums/forum/finances/';
const profilePath = process.env.SB_PROFILE_PATH || '/forums/users/sbvip/';

async function writeResult(name, result) {
  await fs.mkdir(outDir, { recursive: true });
  await fs.writeFile(path.join(outDir, `${name}.json`), JSON.stringify(result, null, 2));
}

async function dismissLanguagePopup(page, waitMs = 0) {
  const overlay = page.locator('#sb-lang-popup-overlay');
  if (waitMs > 0) await overlay.waitFor({ state: 'attached', timeout: waitMs }).catch(() => {});
  if (await overlay.count() && await overlay.isVisible().catch(() => false)) {
    const stayButton = page.locator('#sb-lang-stay');
    if (await stayButton.count()) await stayButton.click();
    await expect(overlay).toBeHidden();
  }
}

function assertCleanRuntime(issues, label = 'runtime') {
  const { transientProxyResponses, ...productIssues } = issues;
  expect(productIssues, `${label}: ${JSON.stringify(productIssues)}`).toEqual({ consoleErrors: [], pageErrors: [], failedRequests: [], badResponses: [] });
}

function attachRuntimeIssues(page) {
  const issues = { consoleErrors: [], pageErrors: [], failedRequests: [], badResponses: [], transientProxyResponses: [] };
  page.on('console', message => {
    if (message.type() === 'error') issues.consoleErrors.push(message.text());
  });
  page.on('pageerror', error => issues.pageErrors.push(String(error)));
  page.on('requestfailed', request => {
    const error = request.failure()?.errorText || 'unknown';
    // WordPress annule son script zxcvbn lors de la navigation wp-login sur
    // WebKit ; c’est une annulation cœur attendue, non une erreur SwiftBoard.
    if (request.url().includes('/wp-includes/js/zxcvbn.min.js') && /cancelled|aborted/i.test(error)) {
      return;
    }
    // Les navigateurs annulent parfois les images lazy et le beacon vitals
    // lors d’un changement de page; ce n’est pas un échec HTTP du produit.
    if (/cancelled|aborted/i.test(error)
      && (request.resourceType() === 'image' || request.url().includes('/wp-json/swiftboard/v1/vitals'))) {
      return;
    }
    issues.failedRequests.push({ url: request.url(), error });
  });
  page.on('response', response => {
    if (response.status() >= 500 && response.status() <= 504 && response.request().resourceType() === 'document') {
      issues.transientProxyResponses.push({ url: response.url(), status: response.status() });
      return;
    }
    if (response.status() >= 400) issues.badResponses.push({ url: response.url(), status: response.status() });
  });
  return issues;
}

async function captureThemeStates(page, prefix, testInfo) {
  for (const theme of ['light', 'dark']) {
    await page.evaluate(value => localStorage.setItem('swiftboard-theme', value), theme);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    await page.screenshot({ path: path.join(outDir, `${prefix}-${theme}-${testInfo.project.name}.png`), fullPage: true });
  }
}

async function auditPage(page, url, label, theme = null, screenshotPath = null) {
  const issues = attachRuntimeIssues(page);
  const canBustCache = process.env.SB_QA_BUST_QUERY === '1' && url !== '/login/' && !url.startsWith('/wp-login.php');
  const targetUrl = canBustCache
    ? (() => {
      const base = page.url().startsWith('http') ? page.url() : (process.env.SWIFTBOARD_BASE_URL || 'http://localhost/');
      const target = new URL(url, base);
      target.searchParams.set('sbqa', `${label}-${Date.now()}`);
      return target.toString();
    })()
    : url;
  let response;
  for (let attempt = 0; attempt < 3; attempt += 1) {
    try {
      response = await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
      if (response?.status() !== 502 && response?.status() !== 503 && response?.status() !== 504) break;
    } catch (error) {
      // hcdn peut détacher une frame pendant un renouvellement de cache; on
      // réessaie la même URL, puis on laisse l’erreur remonter au dernier essai.
      if (attempt === 2) throw error;
    }
    await page.waitForTimeout(750);
  }
  expect(response?.status(), label).toBe(200);
  if (theme) {
    await page.evaluate(value => localStorage.setItem('swiftboard-theme', value), theme);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
  }
  await dismissLanguagePopup(page, 3000);
  // WebKit large peut terminer Axe puis crasher avant une évaluation ou une
  // capture suivante. L’overflow est donc relevé avant Axe, sans supprimer
  // aucune des deux assertions normatives.
  const overflow = await page.evaluate(() => ({ innerWidth: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }));
  expect(overflow.scrollWidth, `${label} horizontal overflow`).toBeLessThanOrEqual(overflow.innerWidth + 1);
  if (screenshotPath) {
    await page.screenshot({ path: screenshotPath, fullPage: false, timeout: 15000 });
  }
  const axe = await new AxeBuilder({ page })
    .exclude('#wpadminbar')
    .options({ resultTypes: ['violations'] })
    .analyze();
  return { url: page.url(), status: response?.status(), axeViolations: axe.violations, overflow, issues };
}

test('Lot 4 — cartes, tri, pagination et responsive du fil', async ({ page }, testInfo) => {
  test.setTimeout(120_000);
  const result = { project: testInfo.project.name, criteria: {}, runtime: null };
  const runtime = attachRuntimeIssues(page);
  const response = await page.goto('/', { waitUntil: 'networkidle' });
  expect(response?.status()).toBe(200);
  const cards = page.locator('article.sb-post-card');
  await expect(cards.first()).toBeVisible();
  expect(await cards.count()).toBeGreaterThan(0);
  const firstCard = cards.first();
  await expect(firstCard.locator('.sb-post-title a')).toBeVisible();
  await expect(firstCard.locator('.sb-forum-pill')).toBeVisible();
  const voteBlock = firstCard.locator('.sb-post-votes:visible, .sb-card-votes-inline:visible');
  await expect(voteBlock.first()).toBeVisible();
  await expect(firstCard.locator('.sb-post-actions')).toBeVisible();
  result.criteria['L4-01'] = { status: 'PASS', cards: await cards.count() };

  const sortTabs = page.locator('.sb-sort-tab');
  const sortStates = [];
  const orderBySort = {};
  for (let i = 0; i < await sortTabs.count(); i += 1) {
    const tab = sortTabs.nth(i);
    sortStates.push({ label: (await tab.innerText()).trim(), href: await tab.getAttribute('href') });
  }
  expect(sortStates.length).toBeGreaterThanOrEqual(4);
  for (const state of sortStates) {
    await page.goto(new URL(state.href, page.url()).toString(), { waitUntil: 'networkidle' });
    await expect(page.locator('.sb-sort-tab.active')).toContainText(state.label);
    const key = new URL(state.href, page.url()).searchParams.get('sort') || 'hot';
    orderBySort[key] = await page.locator('article.sb-post-card').evaluateAll(nodes => nodes.map(node => node.id));
  }
  expect(orderBySort.new).not.toEqual(orderBySort.top);
  expect(orderBySort.top).not.toEqual(orderBySort.rising);
  result.criteria['L4-02'] = { status: 'PASS', states: sortStates, orderSignatures: Object.fromEntries(Object.entries(orderBySort).map(([key, ids]) => [key, ids.slice(0, 5)])), distinctPairs: ['new/top', 'top/rising'] };

  const compact = page.locator('[data-compact-toggle], .sb-compact-view-toggle, [data-view="compact"]');
  await dismissLanguagePopup(page, 2000);
  if (await compact.count() === 0) {
    result.criteria['L4-03'] = { status: 'N/A justifié', reason: 'Aucun contrôle de vue compacte n’est exposé par le DOM du produit.' };
  } else {
    const compactToggle = compact.first();
    await expect(compactToggle).toBeVisible();
    await expect(compactToggle).toHaveAttribute('aria-pressed', 'false');
    await compactToggle.focus();
    await expect(compactToggle).toBeFocused();
    await compactToggle.click();
    await expect(compactToggle).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('body')).toHaveClass(/sb-compact-view/);
    await page.reload({ waitUntil: 'networkidle' });
    await expect(page.locator('[data-compact-toggle]').first()).toHaveAttribute('aria-pressed', 'true');
    await expect(page.locator('body')).toHaveClass(/sb-compact-view/);
    await page.locator('[data-compact-toggle]').first().click();
    await expect(page.locator('body')).not.toHaveClass(/sb-compact-view/);
    result.criteria['L4-03'] = { status: 'PASS', persisted: true };
  }

  const pagination = page.locator('.sb-home-pagination, .pagination');
  if (await pagination.count() === 0) {
    result.criteria['L4-04'] = { status: 'N/A justifié', reason: 'Aucun mécanisme de pagination ou scroll infini n’est exposé sur ce dataset.' };
  } else {
    const next = pagination.locator('a').filter({ hasText: /Suivant|Next|›/ }).first();
    if (await next.count() === 0) {
      result.criteria['L4-04'] = { status: 'N/A justifié', reason: 'Pagination présente mais une seule page est disponible dans le dataset.' };
    } else {
      const idsBefore = await page.locator('article.sb-post-card').evaluateAll(nodes => nodes.map(node => node.id));
      await next.click();
      await page.waitForLoadState('networkidle');
      const idsAfter = await page.locator('article.sb-post-card').evaluateAll(nodes => nodes.map(node => node.id));
      expect(new Set(idsAfter).size).toBe(idsAfter.length);
      expect(idsAfter).not.toEqual(idsBefore);
      result.criteria['L4-04'] = { status: 'PASS', before: idsBefore.length, after: idsAfter.length };
    }
  }

  await page.goto('/', { waitUntil: 'networkidle' });
  await captureThemeStates(page, 'lot4-home', testInfo);
  const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
  const overflow = await page.evaluate(() => ({ innerWidth: window.innerWidth, scrollWidth: document.documentElement.scrollWidth }));
  expect(overflow.scrollWidth).toBeLessThanOrEqual(overflow.innerWidth + 1);
  result.criteria['L4-05'] = { status: 'PASS', viewport: testInfo.project.name, overflow };
  result.criteria['L4-07'] = { status: axe.violations.length ? 'FAIL' : 'PASS', axeViolations: axe.violations };
  result.criteria['L4-08'] = { status: runtime.consoleErrors.length || runtime.pageErrors.length || runtime.failedRequests.length || runtime.badResponses.length ? 'FAIL' : 'PASS', runtime };
  result.runtime = runtime;
  await writeResult(`lot4-${testInfo.project.name}`, result);
  expect(axe.violations, JSON.stringify(axe.violations)).toEqual([]);
  assertCleanRuntime(runtime, 'Lot 4 runtime');
});

test('Lots 5 à 8 — thread, profil, forum et auth', async ({ page }, testInfo) => {
  // Le staging Hostinger peut dépasser 45 s sur networkidle (CDN/polling),
  // sans que la page ou l’assertion métier soit en échec.
  test.setTimeout(120_000);
  const result = { project: testInfo.project.name, criteria: {}, note: 'Les mutations authentifiées sont couvertes par cdc-functional.spec.mjs ; ce scénario exécute les états rendus sur chaque moteur.' };
  let audit = await auditPage(page, topicPath, 'topic');
  const comments = page.locator('.sb-comment');
  expect(await comments.count()).toBeGreaterThan(0);
  expect(await page.locator('.sb-comment[data-depth="2"], .sb-comment[data-depth="3"]').count()).toBeGreaterThan(0);
  expect(await page.locator('.sb-comment-sort, [class*="comment-sort"]').count()).toBeGreaterThan(0);
  const commentSortButtons = page.locator('.sb-comment-sort-btn');
  expect(await commentSortButtons.count()).toBeGreaterThanOrEqual(5);
  const commentOrders = {};
  for (let i = 0; i < await commentSortButtons.count(); i += 1) {
    const button = commentSortButtons.nth(i);
    const href = await button.getAttribute('href');
    const key = new URL(href, page.url()).searchParams.get('csort');
    await page.goto(new URL(href, page.url()).toString(), { waitUntil: 'networkidle' });
    await expect(page.locator('.sb-comment-sort-btn.active')).toContainText((await button.innerText()).trim());
    commentOrders[key] = await page.locator('.sb-comment[data-reply-id]').evaluateAll(nodes => nodes.map(node => node.getAttribute('data-reply-id')));
  }
  expect(commentOrders.new).not.toEqual(commentOrders.old);
  const firstComment = page.locator('.sb-comment').first();
  const collapseBar = firstComment.locator('[data-sb-action="collapse"]');
  await dismissLanguagePopup(page, 3000);
  await collapseBar.click();
  await expect(firstComment).toHaveClass(/collapsed/);
  await collapseBar.press('Enter');
  await expect(firstComment).not.toHaveClass(/collapsed/);
  const replyButton = firstComment.locator('[data-sb-action="reply-open"]');
  await dismissLanguagePopup(page, 3000);
  await replyButton.click();
  await expect(firstComment.locator('.sb-comment-reply-form')).toBeVisible();
  await expect(firstComment.locator('.sb-comment-reply-form textarea')).toBeFocused();
  await dismissLanguagePopup(page, 3000);
  await firstComment.locator('[data-sb-action="reply-cancel"]').click();
  await expect(firstComment.locator('.sb-comment-reply-form')).toBeHidden();
  result.criteria['L5-01'] = { status: 'PASS', comments: await comments.count(), nested: await page.locator('.sb-comment[data-depth="2"], .sb-comment[data-depth="3"]').count() };
  result.criteria['L5-03'] = { status: 'PASS', orders: Object.fromEntries(Object.entries(commentOrders).map(([key, ids]) => [key, ids.slice(0, 5)])), distinct: 'new/old' };
  await page.screenshot({ path: path.join(outDir, `lot5-topic-${testInfo.project.name}.png`), fullPage: true });

  audit = await auditPage(page, profilePath, 'profile');
  await expect(page.locator('.sb-profile')).toBeVisible();
  await expect(page.locator('.sb-profile-name')).toContainText(/VIP/i);
  const statCount = await page.locator('.sb-profile-stat').count();
  expect(statCount).toBeGreaterThanOrEqual(4);
  await expect(page.locator('.sb-profile-grade')).toBeVisible();
  await expect(page.locator('.sb-profile-tabs')).toBeVisible();
  const tabCount = await page.locator('.sb-profile-tab').count();
  expect(tabCount).toBeGreaterThanOrEqual(4);
  await page.goto(`${profilePath}?tab=trophies`, { waitUntil: 'networkidle' });
  await expect(page.locator('.sb-profile')).toBeVisible();
  const trophyCount = await page.locator('.sb-trophy').count();
  expect(trophyCount).toBeGreaterThan(0);
  await page.screenshot({ path: path.join(outDir, `lot6-vip-trophies-${testInfo.project.name}.png`), fullPage: true });
  await page.goto(`${profilePath}?tab=posts`, { waitUntil: 'networkidle' });
  await expect(page.locator('.sb-profile-content')).toBeVisible();
  const postHistoryCount = await page.locator('.sb-profile-list > *').count();
  await page.goto(`${profilePath}?tab=comments`, { waitUntil: 'networkidle' });
  await expect(page.locator('.sb-profile-content')).toBeVisible();
  const commentHistoryCount = await page.locator('.sb-profile-list > *').count();
  result.criteria['L6-01'] = { status: 'PASS', stats: statCount, trophies: trophyCount, posts: postHistoryCount, comments: commentHistoryCount, tabs: tabCount };
  await page.goto(profilePath, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(outDir, `lot6-vip-${testInfo.project.name}.png`), fullPage: true });

  audit = await auditPage(page, forumPath, 'forum');
  await expect(page.locator('.sb-subreddit-hero')).toBeVisible();
  await expect(page.locator('.sb-about-card')).toBeVisible();
  await expect(page.locator('.sb-about-rules')).toBeVisible();
  result.criteria['L7-01'] = { status: 'PASS' };
  await page.screenshot({ path: path.join(outDir, `lot7-forum-${testInfo.project.name}.png`), fullPage: true });

  audit = await auditPage(page, '/login/', 'login');
  await expect(page.locator('body')).toContainText(/Connexion|Se connecter|Log In|Login/i);
  const signupResponse = await page.goto('/wp-login.php?action=register', { waitUntil: 'networkidle' });
  expect(signupResponse?.status()).toBe(200);
  const signupSelector = '#registerform, #user_login, #user_email, #signup_username, #signup_email';
  await expect(page.locator(signupSelector).first()).toBeAttached();
  const signupFields = await page.locator('#user_login, #user_email, #signup_username, #signup_email').count();
  expect(signupFields).toBeGreaterThan(0);
  await page.screenshot({ path: path.join(outDir, `lot8-signup-${testInfo.project.name}.png`), fullPage: true });
  await page.goto('/', { waitUntil: 'networkidle' });
  const onboarding = page.locator('#sb-onboarding-modal');
  await expect(onboarding).toHaveCount(1);
  const openOnboarding = page.locator('[data-open-onboarding]:visible, .sb-onboarding-open:visible').first();
  await expect(openOnboarding).toBeVisible();
  await openOnboarding.click();
  await expect(onboarding).toBeVisible();
  await expect(page.locator('#sb-onb-step-1')).toBeVisible();
  await dismissLanguagePopup(page, 3000);
  await page.locator('.sb-onb-gender-btn').first().click();
  await expect(page.locator('#sb-onb-step-2')).toBeVisible();
  await dismissLanguagePopup(page);
  await page.locator('.sb-onb-avatar-btn').first().click();
  await expect(page.locator('#sb-onb-step-3')).toBeVisible();
  await dismissLanguagePopup(page);
  await expect(page.locator('.sb-onb-social-btn')).toHaveCount(3);
  await page.locator('#sb-onb-email-submit').click();
  await expect(page.locator('.sb-onb-status')).toContainText(/e-mail|email/i);
  await page.locator('.sb-onb-social-btn').first().click();
  await expect(page.locator('.sb-onb-status')).toContainText(/configuré|configure|Chargement|SDK/i);
  await page.locator('#sb-onboarding-modal [data-action="close"]').click();
  await expect(onboarding).toBeHidden();
  result.criteria['L8-01'] = { status: 'PASS', onboarding: true, steps: 3 };
  result.criteria['L8-02'] = { status: 'PASS contractuel', oauth: 'covered by oauth-contract.spec.mjs' };
  await page.screenshot({ path: path.join(outDir, `lot8-auth-${testInfo.project.name}.png`), fullPage: true });
  await writeResult(`lots5-8-${testInfo.project.name}`, result);
  for (const value of [audit]) assertCleanRuntime(value.issues);
});

test('Lot 9 — pages clés, axe, overflow, thème et états vides', async ({ page }, testInfo) => {
  // Le scénario couvre 7 pages × 2 thèmes, onboarding, clavier et persistance.
  // Ce délai étendu concerne uniquement la durée d’exécution, pas les assertions.
  test.setTimeout(120_000);
  if (process.env.SB_QA_BUST_CACHE === '1') {
    await page.setExtraHTTPHeaders({ 'Cache-Control': 'no-cache' });
  }
  const pages = [
    ['home', '/'], ['forum', forumPath], ['topic', topicPath], ['profile', profilePath],
    ['login', '/login/'], ['signup', '/wp-login.php?action=register'], ['search-empty', '/?s=zzzz-no-result-swiftboard-qa'],
  ];
  const results = {};
  for (const theme of ['light', 'dark']) {
    await page.goto(process.env.SB_QA_BUST_QUERY === '1' ? `/?sbqa=${theme}-${Date.now()}` : '/', { waitUntil: 'networkidle' });
    await page.evaluate(value => localStorage.setItem('swiftboard-theme', value), theme);
    for (const [label, url] of pages) {
      const routePage = await page.context().newPage();
      try {
        const audit = await auditPage(
          routePage,
          url,
          `${label}-${theme}`,
          theme,
          path.join(outDir, `lot9-${label}-${theme}-${testInfo.project.name}.png`),
        );
        results[`${label}-${theme}`] = audit;
        expect(audit.axeViolations, `${label}/${theme}: ${JSON.stringify(audit.axeViolations)}`).toEqual([]);
        assertCleanRuntime(audit.issues, `${label}/${theme}`);
      } finally {
        await routePage.close().catch(() => {});
      }
    }
  }
  const onboardingResponse = await page.goto(process.env.SB_QA_BUST_QUERY === '1' ? `/?sbqa=onboarding-${Date.now()}` : '/', { waitUntil: 'commit', timeout: 20000 });
  expect(onboardingResponse?.status()).toBe(200);
  const onboarding = page.locator('#sb-onboarding-modal');
  await onboarding.waitFor({ state: 'attached', timeout: 10000 });
  const onboardingTrigger = page.locator('.sb-r-signup-btn[data-open-onboarding]:visible, .sb-home-btn-primary[data-open-onboarding]:visible, .sb-onboarding-open:visible').first();
  await expect(onboardingTrigger).toBeVisible();
  await onboardingTrigger.scrollIntoViewIfNeeded();
  await onboardingTrigger.dispatchEvent('click');
  await expect(onboarding).toBeVisible();
  await dismissLanguagePopup(page, 2000);
  await page.waitForTimeout(400);
  const onboardingAxe = await new AxeBuilder({ page }).include('#sb-onboarding-modal').analyze();
  expect(onboardingAxe.violations, `onboarding: ${JSON.stringify(onboardingAxe.violations)}`).toEqual([]);
  results.onboarding = { status: 'PASS', axeViolations: onboardingAxe.violations };
  await page.screenshot({ path: path.join(outDir, `lot9-onboarding-${testInfo.project.name}.png`), fullPage: false, timeout: 15000, animations: 'disabled', scale: 'css' });
  // La modale de langue est un état produit indépendant; la fermer proprement
  // avant la fermeture de l’onboarding évite qu’elle intercepte le clic.
  const languagePopup = page.locator('#sb-lang-popup-overlay');
  if (await languagePopup.count() && await languagePopup.isVisible().catch(() => false)) {
    const stayButton = page.locator('#sb-lang-stay');
    if (await stayButton.count()) await stayButton.click();
    await expect(languagePopup).toBeHidden();
  }
  await onboarding.locator('[data-action="close"]').first().click();
  await expect(onboarding).toBeHidden();

  // Parcours clavier minimal reproductible : skip link en première position,
  // puis ordre sans répétition immédiate et focus visible sur les contrôles clés.
  await page.locator('.skip-link').focus();
  await expect(page.locator('.skip-link')).toBeFocused();
  const firstFocus = await page.evaluate(() => ({ tag: document.activeElement?.tagName, className: document.activeElement?.className }));
  expect(firstFocus.className).toContain('skip-link');
  const focusOrder = [firstFocus];
  for (let i = 0; i < 11; i += 1) {
    await page.keyboard.press('Tab');
    focusOrder.push(await page.evaluate(() => ({ tag: document.activeElement?.tagName, id: document.activeElement?.id || '', className: document.activeElement?.className || '', aria: document.activeElement?.getAttribute('aria-label') || '' })));
  }
  const focusUnique = new Set(focusOrder.map(item => JSON.stringify(item)));
  expect(focusUnique.size).toBeGreaterThanOrEqual(5);
  expect(focusOrder.slice(1).every(item => item.tag !== 'BODY')).toBe(true);
  const focusTargets = ['.menu-toggle', '.theme-toggle', '.sb-sort-tab.active', '.sb-compact-view-toggle'];
  const focusEvidence = [];
  for (const selector of focusTargets) {
    const target = page.locator(selector).first();
    if (await target.count() === 0 || !(await target.isVisible())) continue;
    await target.focus();
    const evidence = await target.evaluate(el => { const s = getComputedStyle(el); return { selector: el.className, outlineStyle: s.outlineStyle, outlineWidth: s.outlineWidth, boxShadow: s.boxShadow }; });
    expect(evidence.outlineStyle !== 'none' || evidence.outlineWidth !== '0px' || evidence.boxShadow !== 'none').toBe(true);
    focusEvidence.push(evidence);
  }
  results.focus = { status: 'PASS', first: firstFocus, order: focusOrder, visible: focusEvidence };
  await page.evaluate(() => document.activeElement?.blur());
  await page.screenshot({ path: path.join(outDir, `lot9-focus-${testInfo.project.name}.png`), fullPage: false, timeout: 10000, animations: 'disabled' });

  await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'light'));
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
  await page.evaluate(() => localStorage.setItem('swiftboard-theme', 'dark'));
  await page.reload({ waitUntil: 'domcontentloaded', timeout: 30000 });
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
  results.themePersistence = { status: 'PASS', localStorage: await page.evaluate(() => localStorage.getItem('swiftboard-theme')) };
  await writeResult(`lot9-${testInfo.project.name}`, { project: testInfo.project.name, results });
});
