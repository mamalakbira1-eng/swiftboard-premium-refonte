import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.SWIFTBOARD_BASE_URL || 'https://8088-iusiaz3ltza0hnfunobhr-de99ba16.us5.manus.computer';

export default defineConfig({
  testDir: './tests',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: '../reports/playwright-html', open: 'never' }],
    ['json', { outputFile: '../reports/playwright-results.json' }],
  ],
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium-mobile', use: { ...devices['iPhone 13'], browserName: 'chromium' } },
    { name: 'chromium-tablet', use: { ...devices['iPad Mini'], browserName: 'chromium' } },
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'], browserName: 'chromium', viewport: { width: 1440, height: 900 } } },
    { name: 'chromium-large', use: { ...devices['Desktop Chrome'], browserName: 'chromium', viewport: { width: 1920, height: 1080 } } },
    { name: 'firefox-desktop', use: { ...devices['Desktop Firefox'], browserName: 'firefox', viewport: { width: 1440, height: 900 } } },
    { name: 'webkit-desktop', use: { ...devices['Desktop Safari'], browserName: 'webkit', viewport: { width: 1440, height: 900 } } },
  ],
});
