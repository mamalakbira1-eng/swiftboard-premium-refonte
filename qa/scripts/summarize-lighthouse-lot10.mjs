import fs from 'node:fs/promises';
import path from 'node:path';

const root = path.resolve(new URL('.', import.meta.url).pathname, '..', '..');
const dir = path.join(root, 'reports', 'lighthouse-lot10');
const files = (await fs.readdir(dir)).filter(name => name.endsWith('.json') && name !== 'summary.json').sort();
const summary = [];
for (const file of files) {
  const report = JSON.parse(await fs.readFile(path.join(dir, file), 'utf8'));
  const audits = report.audits || {};
  summary.push({
    page: file.replace(/\.json$/, ''),
    url: report.finalDisplayedUrl || report.finalUrl,
    fetchTime: report.fetchTime,
    scores: Object.fromEntries(Object.entries(report.categories || {}).map(([key, value]) => [key, value.score == null ? null : Math.round(value.score * 100)])),
    metrics: {
      fcpMs: audits['first-contentful-paint']?.numericValue ?? null,
      lcpMs: audits['largest-contentful-paint']?.numericValue ?? null,
      tbtMs: audits['total-blocking-time']?.numericValue ?? null,
      cls: audits['cumulative-layout-shift']?.numericValue ?? null,
      siMs: audits['speed-index']?.numericValue ?? null,
    },
    failedAudits: Object.values(audits).filter(audit => audit.score !== 1 && audit.score !== null && audit.score !== undefined).map(audit => ({ id: audit.id, score: audit.score, title: audit.title })).slice(0, 20),
  });
}
await fs.writeFile(path.join(dir, 'summary.json'), JSON.stringify({ generatedAt: new Date().toISOString(), pages: summary }, null, 2));
console.log(JSON.stringify(summary, null, 2));
