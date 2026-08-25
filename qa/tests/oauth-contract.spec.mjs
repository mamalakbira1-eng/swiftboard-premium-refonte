import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/oauth');

test('CDC — contrat OAuth non configuré et state anti-rejeu', async ({ page, context }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  const base = '/wp-json/swiftboard/v1/auth';

  const github = await page.request.get(`${base}/github-login`, { maxRedirects: 0 });
  expect(github.status()).toBe(500);
  const githubJson = await github.json();
  expect(githubJson.code).toBe('not_configured');

  const callback = await page.request.get(`${base}/callback?code=fake-code&state=fake-state`, { maxRedirects: 0 });
  expect(callback.status()).toBe(403);
  expect((await callback.json()).code).toBe('invalid_state');

  const challenge = await page.request.get(`${base}/google-challenge`);
  expect(challenge.status()).toBe(200);
  const { state } = await challenge.json();
  expect(state).toMatch(/^[a-f0-9]{64}$/);
  expect((await context.cookies()).some(cookie => cookie.name === 'sb_google_state')).toBeTruthy();

  const verify = await page.request.post(`${base}/google-verify`, {
    form: { id_token: 'fake-id-token', state },
  });
  expect(verify.status()).toBe(500);
  expect((await verify.json()).code).toBe('not_configured');

  const replay = await page.request.post(`${base}/google-verify`, {
    form: { id_token: 'fake-id-token', state },
  });
  expect(replay.status()).toBe(403);
  expect((await replay.json()).code).toBe('invalid_oauth_state');

  await page.screenshot({ path: path.join(outDir, `oauth-contract-${testInfo.project.name}.png`), fullPage: true });
});
