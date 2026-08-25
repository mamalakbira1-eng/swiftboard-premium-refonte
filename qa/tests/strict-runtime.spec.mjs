import { test, expect } from '@playwright/test';
import { AxeBuilder } from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/strict-runtime');
const pages = {
  home: '/',
  forum: '/forums/forum/finances/',
  topic: '/forums/topic/par-ou-commencer-une-epargne-d-urgence/',
  profile: '/forums/users/sbmember/',
  search: '/?s=teletravail',
};

function collectIssues(page) {
  const issues = { consoleErrors: [], pageErrors: [], failedRequests: [], badResponses: [] };
  page.on('console', (message) => {
    if (message.type() === 'error') issues.consoleErrors.push(message.text());
  });
  page.on('pageerror', (error) => issues.pageErrors.push(String(error)));
  page.on('requestfailed', (request) => {
    issues.failedRequests.push({ url: request.url(), error: request.failure()?.errorText ?? 'unknown' });
  });
  page.on('response', (response) => {
    if (response.status() >= 400) {
      issues.badResponses.push({ url: response.url(), status: response.status(), resourceType: response.request().resourceType() });
    }
  });
  return issues;
}

test('strict runtime — pages, console, réseau et accessibilité', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  const results = {};
  for (const [key, url] of Object.entries(pages)) {
    const issues = collectIssues(page);
    await page.goto(url, { waitUntil: 'networkidle' });
    await expect(page.locator('main, [role="main"]')).toHaveCount(1);
    const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
    results[key] = {
      url: page.url(),
      title: await page.title(),
      axeViolations: axe.violations,
      issues,
    };
    await page.screenshot({ path: path.join(outDir, `${key}-${testInfo.project.name}.png`), fullPage: true });
  }
  await fs.writeFile(path.join(outDir, `strict-${testInfo.project.name}.json`), JSON.stringify(results, null, 2));
  const failures = Object.fromEntries(Object.entries(results).filter(([, value]) =>
    value.axeViolations.length || value.issues.consoleErrors.length || value.issues.pageErrors.length ||
    value.issues.failedRequests.length || value.issues.badResponses.length,
  ));
  expect(failures, JSON.stringify(failures, null, 2)).toEqual({});
});
