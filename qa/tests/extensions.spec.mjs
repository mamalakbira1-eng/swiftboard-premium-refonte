import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const outDir = path.resolve('../reports/extensions');

test('CDC — rendu réel Gutenberg, shortcode et Elementor', async ({ page }, testInfo) => {
  await fs.mkdir(outDir, { recursive: true });
  const failures = [];
  page.on('console', msg => { if (msg.type() === 'error') failures.push(`console: ${msg.text()}`); });
  page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
  page.on('requestfailed', request => failures.push(`requestfailed: ${request.url()} ${request.failure()?.errorText || ''}`));
  for (const fixture of [
    { slug: 'qa-gutenberg-hot-topics', selector: '.sb-gutenberg-block.sb-block-hot-topics' },
    { slug: 'qa-shortcode-hot-topics', selector: '.sb-gutenberg-block.sb-block-hot-topics' },
    { slug: 'qa-elementor-hot-topics', selector: '.sb-elementor-widget.sb-widget-hot-topics' },
  ]) {
    const response = await page.goto(`/${fixture.slug}/`, { waitUntil: 'networkidle' });
    expect(response?.status(), fixture.slug).toBe(200);
    await expect(page.locator(fixture.selector)).toBeVisible();
    await expect(page.locator('body')).toContainText('Hot Topics');
    // BuddyPress imprime sa barre d’admin hors périmètre du thème, même sur
    // ce snapshot local ; elle est auditée séparément et exclue ici pour ne
    // pas masquer les violations du rendu SwiftBoard.
    const axe = await new AxeBuilder({ page }).exclude('#wpadminbar').analyze();
    expect(axe.violations, JSON.stringify(axe.violations)).toEqual([]);
    await page.screenshot({ path: path.join(outDir, `${fixture.slug}-${testInfo.project.name}.png`), fullPage: true });
  }
  expect(failures, failures.join('\n')).toEqual([]);
});
