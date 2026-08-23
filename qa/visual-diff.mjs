import fs from 'node:fs/promises';
import path from 'node:path';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';

const pairs = [
  ['homepage-light-chromium-desktop.png', '../baseline/homepage-light-chromium-desktop.png', '../reports/lot1/homepage-light-chromium-desktop.png'],
  ['homepage-dark-chromium-desktop.png', '../baseline/homepage-dark-chromium-desktop.png', '../reports/lot1/homepage-dark-chromium-desktop.png'],
];

const diffDir = path.resolve('../reports/visual-diff');
await fs.mkdir(diffDir, { recursive: true });
const results = [];

for (const [name, beforePath, afterPath] of pairs) {
  const before = PNG.sync.read(await fs.readFile(path.resolve(beforePath)));
  const after = PNG.sync.read(await fs.readFile(path.resolve(afterPath)));
  if (before.width !== after.width || before.height !== after.height) {
    results.push({ name, comparable: false, before: [before.width, before.height], after: [after.width, after.height] });
    continue;
  }
  const diff = new PNG({ width: before.width, height: before.height });
  const changedPixels = pixelmatch(before.data, after.data, diff.data, before.width, before.height, {
    threshold: 0.1,
    includeAA: false,
  });
  await fs.writeFile(path.join(diffDir, name), PNG.sync.write(diff));
  results.push({ name, comparable: true, width: before.width, height: before.height, changedPixels, changedRatio: changedPixels / (before.width * before.height) });
}

await fs.writeFile(path.join(diffDir, 'summary.json'), JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
console.log(JSON.stringify({ generatedAt: new Date().toISOString(), results }, null, 2));
