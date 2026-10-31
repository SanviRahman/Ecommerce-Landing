/**
 * P11 allowlisted source fingerprint. Never reads .env, logs, storage, uploads,
 * confidential client PDFs, or vendor. Does not deploy or write by default.
 */
import { createHash } from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '../..');
const outputPath = path.join(root, 'reports', 'p11-source-manifest.json');
const roots = [
  'app', 'bootstrap', 'config', 'database/migrations', 'resources/views',
  'resources/css', 'resources/js', 'routes', 'tests', 'tools/qa',
  'public/frontend',
];
const files = [];
function collect(relative) {
  const target = path.join(root, relative);
  if (!fs.existsSync(target)) return;
  const stat = fs.lstatSync(target);
  if (stat.isSymbolicLink()) throw new Error('Unexpected source symlink: ' + relative);
  if (stat.isDirectory()) {
    for (const entry of fs.readdirSync(target, { withFileTypes: true })) {
      if (entry.name === 'cache' && relative === 'bootstrap') continue;
      if (entry.name.startsWith('.')) continue;
      collect(path.posix.join(relative, entry.name));
    }
    return;
  }
  if (!stat.isFile()) return;
  if (!/\.(php|mjs|js|css|scss|json|svg|ps1)$/i.test(relative)) return;
  const buffer = fs.readFileSync(target);
  files.push({ path: relative.replaceAll('\\', '/'), bytes: buffer.length,
    sha256: createHash('sha256').update(buffer).digest('hex') });
}
for (const dir of roots) collect(dir);
for (const named of [
  'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
  'vite.config.js', 'phpunit.xml', 'phpunit.p01.xml', 'phpunit.p03.xml',
  'phpunit.p04.xml', 'phpunit.p05.xml', 'phpunit.p06.xml',
  'phpunit.p07.xml', 'phpunit.p08.xml', 'phpunit.p10.xml',
]) collect(named);
files.sort((a, b) => a.path.localeCompare(b.path, 'en'));
if (files.length < 150) throw new Error('Source inventory unexpectedly small.');
const checksum = createHash('sha256').update(files.map((f) =>
  `${f.path}\u0000${f.bytes}\u0000${f.sha256}\n`).join('')).digest('hex');
const current = { schema: 1, scope: 'allowlisted-code-only', count: files.length,
  sha256: checksum, files };
const cmd = process.argv[2] ?? '--check';
if (cmd === '--write') {
  fs.mkdirSync(path.dirname(outputPath), { recursive: true });
  fs.writeFileSync(outputPath, JSON.stringify(current, null, 2) + '\n');
  console.log(`PASS Wrote safe P11 code fingerprint (${files.length} files, no secrets)`);
} else if (cmd === '--verify') {
  if (!fs.existsSync(outputPath)) throw new Error('Manifest missing; no deployment claim is possible.');
  const saved = JSON.parse(fs.readFileSync(outputPath, 'utf8'));
  if (saved.schema !== 1 || saved.scope !== 'allowlisted-code-only'
      || saved.count !== current.count || saved.sha256 !== current.sha256
      || JSON.stringify(saved.files) !== JSON.stringify(current.files)) {
    throw new Error('P11 source fingerprint mismatch. Re-review changed code.');
  }
  console.log(`PASS P11 source fingerprint verified (${files.length} files)`);
} else if (cmd === '--check') {
  console.log(`PASS P11 safe source inventory: ${files.length} files; sha256 ${checksum}`);
} else {
  throw new Error('Use --check (default), --write, or --verify.');
}
