import { test, expect } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/cdc-sse');
const username = process.env.SB_TEST_USER;
const password = process.env.SB_TEST_PASSWORD;

test('CDC — SSE : 20 notifications et p95 de livraison', async ({ page }, testInfo) => {
  expect(username, 'SB_TEST_USER requis pour la preuve SSE.').toBeTruthy();
  expect(password, 'SB_TEST_PASSWORD requis pour la preuve SSE.').toBeTruthy();
  await fs.mkdir(outDir, { recursive: true });
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php/);
  await page.goto('/', { waitUntil: 'networkidle' });
  await expect(page.locator('body')).toBeVisible();
  const result = await page.evaluate(() => new Promise((resolve, reject) => {
    const started = performance.now();
    const samples = [];
    const configEl = document.getElementById('swiftboard-sse-config');
    const config = configEl ? configEl.dataset : {};
    const streamUrl = new URL(config.url || '/wp-json/swiftboard/v1/notifications/stream', window.location.origin);
    streamUrl.searchParams.set('last_seen_id', '0');
    if (config.nonce) streamUrl.searchParams.set('_wpnonce', config.nonce);
    const source = new EventSource(streamUrl.toString(), { withCredentials: true });
    const finish = (error) => {
      source.close();
      if (error) reject(error);
      else resolve({ samples, received: samples.length, p95: samples.slice().sort((a, b) => a - b)[Math.ceil(samples.length * 0.95) - 1] });
    };
    source.addEventListener('notification', () => {
      samples.push(performance.now() - started);
      if (samples.length >= 20) finish();
    });
    source.onerror = () => finish(new Error('SSE connection error before 20 notifications'));
    setTimeout(() => finish(new Error(`SSE timeout after ${samples.length} notifications`)), 35_000);
  }));

  expect(result.received).toBeGreaterThanOrEqual(20);
  expect(result.p95).toBeLessThan(5_000);
  await fs.writeFile(path.join(outDir, `sse-${testInfo.project.name}.json`), JSON.stringify({ ...result, generatedAt: new Date().toISOString() }, null, 2));
});

test('CDC — SSE : reconnexion et fallback polling après erreurs', async ({ page }, testInfo) => {
  expect(username, 'SB_TEST_USER requis pour la preuve SSE.').toBeTruthy();
  expect(password, 'SB_TEST_PASSWORD requis pour la preuve SSE.').toBeTruthy();
  let fallbackRequests = 0;
  await page.addInitScript(() => {
    const nativeTimeout = window.setTimeout.bind(window);
    const nativeInterval = window.setInterval.bind(window);
    window.setTimeout = (fn, delay, ...args) => nativeTimeout(fn, Math.min(Number(delay) || 0, 50), ...args);
    window.setInterval = (fn, delay, ...args) => nativeInterval(fn, Math.min(Number(delay) || 0, 50), ...args);
  });
  await page.route('**/wp-json/swiftboard/v1/notifications/stream**', route => route.abort());
  page.on('request', request => {
    if (request.url().includes('/wp-json/swiftboard/v1/notifications/unread-count')) fallbackRequests++;
  });
  await page.goto('/wp-login.php', { waitUntil: 'networkidle' });
  await page.locator('#user_login').fill(username);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page).not.toHaveURL(/wp-login\.php/);
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect.poll(() => fallbackRequests, { timeout: 20_000, intervals: [100, 250, 500] }).toBeGreaterThan(0);
  await fs.mkdir(outDir, { recursive: true });
  await fs.writeFile(
    path.join(outDir, `sse-fallback-${testInfo.project.name}.json`),
    JSON.stringify({ fallbackRequests, streamAborted: true, generatedAt: new Date().toISOString() }, null, 2)
  );
});
