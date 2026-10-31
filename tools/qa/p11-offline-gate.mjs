/**
 * Fail-closed P11 source-only regression aggregator.
 * No Laravel boot, remote calls, SQL writes, migrations or deployment.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const jobs = [
  ['P26 local activation/readiness source', 'node', ['tools/qa/check-p26-local-activation.mjs']],
  ['P26 isolated/local access context', 'php', ['tools/qa/check-p26-local-access-context.php']],
  ['P25 local read-only showroom contracts', 'node', ['tools/qa/check-p25-storefront-readonly.mjs']],
  ['P25 access context matrix', 'php', ['tools/qa/check-p25-local-browse-context.php']],
  ['P13 homepage UI gate', 'node', ['tools/qa/check-p13-homepage-gate.mjs']],
  ['P14 local Homepage source', 'node', ['tools/qa/check-p14-local-homepage.mjs']],
  ['P14 local permission matrix', 'php', ['tools/qa/check-p14-local-access.php']],
  ['P02 schema source contracts', 'node', ['tools/qa/check-p02-schema.mjs']],
  ['P11 source safety', 'node', ['tools/qa/check-p11-release-contract.mjs']],
  ['P11 policy', 'php', ['tools/qa/check-p11-readiness-policy.php']],
  ['P11 SHA-256 fingerprint', 'node', ['tools/qa/p11-source-manifest.mjs', '--verify']],
  ['P10 contact', 'php', ['tools/qa/check-p10-contact.php']],
  ['P10 integration', 'node', ['tools/qa/check-p10-integration.mjs']],
  ['P09 race contracts', 'node', ['tools/qa/check-p09-race-contract.mjs']],
  ['P09 CSRF contracts', 'node', ['tools/qa/check-p09-csrf-node.mjs']],
  ['P08 contracts', 'node', ['tools/qa/check-p08-contract.mjs']],
  ['P07 contracts', 'node', ['tools/qa/check-p07-catalog-contract.mjs']],
  ['P07 browser DOM', 'node', ['tools/qa/check-p07-browser-core.mjs']],
  ['P06 contracts', 'node', ['tools/qa/check-p06-sort-contract.mjs']],
  ['P05 contracts', 'node', ['tools/qa/check-p05-contract.mjs']],
  ['P04 contracts', 'node', ['tools/qa/check-p04-contract.mjs']],
];
let passed = 0;
for (const [name, bin, args] of jobs) {
  const result = spawnSync(bin, args, { cwd: root, encoding: 'utf8',
    shell: false, env: process.env, timeout: 60000 });
  if (result.error || result.status !== 0) {
    console.error('FAIL ' + name + ': ' + (result.error?.message || `exit ${result.status}`));
    const lines = (result.stderr || result.stdout || '').split(/\r?\n/).filter(Boolean);
    for (const line of lines.slice(-5)) console.error(line);
    process.exit(1);
  }
  const resultLine = (result.stdout || '').split(/\r?\n/)
    .findLast((line) => /^(RESULT:|Result:|PASS P11 source fingerprint)/.test(line));
  console.log(`PASS ${name}${resultLine ? ` | ${resultLine}` : ''}`);
  passed++;
}
if (!fs.existsSync(path.join(root, 'public', 'index.php'))) {
  console.error('FAIL Laravel public/index.php is missing');
  process.exit(1);
}
if (fs.existsSync(path.join(root, '.git'))) {
  const tracked = spawnSync('git', ['ls-files', '--error-unmatch', '--', '.env'],
    {cwd: root, encoding: 'utf8', timeout: 15000});
  if (tracked.error) {
    console.error('BLOCKED: git secret-tracking review failed'); process.exit(2);
  }
  if (tracked.status === 0) {
    console.error('BLOCKED: .env is tracked by Git; stop before release'); process.exit(2);
  }
  console.log('PASS Local .env is not tracked by this Git index');
} else {
  console.log('INFO No local Git metadata: secret-tracking check remains a staging manual gate');
}
console.log(`RESULT: ${passed}/${jobs.length} P11 offline regression jobs PASS.`);
console.log('STATUS: CODE_REVIEW_ONLY; P10 MySQL, browser/staging backup/restore and owner approval remain external gates.');
