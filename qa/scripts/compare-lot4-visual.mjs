import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const baselineDir = path.join(root, 'reports/cdc-lots-4-9/baseline-parent');
const finalDir = path.join(root, 'reports/cdc-lots-4-9');
const diffDir = path.join(finalDir, 'visual-diff');
await fs.mkdir(diffDir, { recursive: true });
const projects = ['chromium-mobile', 'chromium-tablet', 'chromium-desktop', 'chromium-large', 'firefox-desktop', 'webkit-desktop'];
const pixelmatchThreshold = 0.1;
const maxDiffPercent = 1;
const results = [];
for (const project of projects) {
  for (const theme of ['light', 'dark']) {
    const beforePath = path.join(baselineDir, `homepage-${theme}-${project}.png`);
    const afterPath = path.join(finalDir, `lot4-home-${theme}-${project}.png`);
    const [beforeBuffer, afterBuffer] = await Promise.all([fs.readFile(beforePath), fs.readFile(afterPath)]);
    const before = PNG.sync.read(beforeBuffer);
    const after = PNG.sync.read(afterBuffer);
    if (before.width !== after.width || before.height !== after.height) {
      results.push({ project, theme, status: 'FAIL', reason: 'dimensions differ', before: { width: before.width, height: before.height }, after: { width: after.width, height: after.height } });
      continue;
    }
    const diff = new PNG({ width: before.width, height: before.height });
    const mismatchedPixels = pixelmatch(before.data, after.data, diff.data, before.width, before.height, { threshold: pixelmatchThreshold, includeAA: false });
    const totalPixels = before.width * before.height;
    const diffPercent = Number((mismatchedPixels / totalPixels * 100).toFixed(4));
    const result = { project, theme, status: diffPercent <= maxDiffPercent ? 'PASS' : 'FAIL', pixelmatchThreshold, maxDiffPercent, includeAA: false, width: before.width, height: before.height, mismatchedPixels, totalPixels, diffPercent };
    await fs.writeFile(path.join(diffDir, `lot4-${theme}-${project}-diff.png`), PNG.sync.write(diff));
    results.push(result);
  }
}
const summary = { generatedAt: new Date().toISOString(), baselineCommit: '4c92c94', comparison: 'working tree Lot 4 versus parent baseline', pixelmatchThreshold, maxDiffPercent, results };
await fs.writeFile(path.join(diffDir, 'summary.json'), JSON.stringify(summary, null, 2));
console.log(JSON.stringify(summary, null, 2));
