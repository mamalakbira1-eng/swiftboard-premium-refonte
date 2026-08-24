import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.SWIFTBOARD_BASE_URL || 'https://8088-iusiaz3ltza0hnfunobhr-de99ba16.us5.manus.computer';

const viewports = {
  mobile: { width: 375, height: 812 },
  tablet: { width: 768, height: 1024 },
  desktop: { width: 1440, height: 900 },
  large: { width: 1920, height: 1080 },
};

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
    // Les PNG et traces restent les preuves normatives; la vidéo est optionnelle pour éviter les crashes WebKit large en matrice 12 projets.
    video: process.env.SB_QA_RECORD_VIDEO === '1' ? 'retain-on-failure' : 'off',
  },
  projects: [
    { name: 'chromium-mobile', use: { ...devices['iPhone 13'], browserName: 'chromium', viewport: viewports.mobile } },
    { name: 'chromium-tablet', use: { ...devices['iPad Mini'], browserName: 'chromium', viewport: viewports.tablet } },
    { name: 'chromium-desktop', use: { ...devices['Desktop Chrome'], browserName: 'chromium', viewport: viewports.desktop } },
    { name: 'chromium-large', use: { ...devices['Desktop Chrome'], browserName: 'chromium', viewport: viewports.large } },
    { name: 'firefox-mobile', use: { ...devices['iPhone 13'], browserName: 'firefox', viewport: viewports.mobile } },
    { name: 'firefox-tablet', use: { ...devices['iPad Mini'], browserName: 'firefox', viewport: viewports.tablet } },
    { name: 'firefox-desktop', use: { ...devices['Desktop Firefox'], browserName: 'firefox', viewport: viewports.desktop } },
    { name: 'firefox-large', use: { ...devices['Desktop Firefox'], browserName: 'firefox', viewport: viewports.large } },
    { name: 'webkit-mobile', use: { ...devices['iPhone 13'], browserName: 'webkit', viewport: viewports.mobile } },
    { name: 'webkit-tablet', use: { ...devices['iPad Mini'], browserName: 'webkit', viewport: viewports.tablet } },
    { name: 'webkit-desktop', use: { ...devices['Desktop Safari'], browserName: 'webkit', viewport: viewports.desktop } },
    { name: 'webkit-large', use: { ...devices['Desktop Safari'], browserName: 'webkit', viewport: viewports.large } },
  ],
});

export { viewports };

// Les quatre tailles sont intentionnellement des viewports CSS exacts du CDC;
// les deviceScaleFactor hérités des profils iPhone/iPad servent seulement aux PNG.
