/**
 * P29 read-only P11 fingerprint diagnostic (Node 20+).
 * Mirrors the existing P11 allowlisted inventory, but never updates it.
 * Prints file paths and change categories only, not file contents or credentials.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';

const root = path.resolve(import.meta.dirname, '../..');
const manifestFile = path.join(root, 'reports', 'p11-source-manifest.json');
const roots = [
  'app', 'bootstrap', 'config', 'database/migrations', 'resources/views',
  'resources/css', 'resources/js', 'routes', 'tests', 'tools/qa', 'public/frontend',
];
const named = [
  'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
  'vite.config.js', 'phpunit.xml', 'phpunit.p01.xml', 'phpunit.p03.xml',
  'phpunit.p04.xml', 'phpunit.p05.xml', 'phpunit.p06.xml',
  'phpunit.p07.xml', 'phpunit.p08.xml', 'phpunit.p10.xml',
];

function sha256(buffer) { return createHash('sha256').update(buffer).digest('hex'); }
function checksum(files) {
  return sha256(files.map((file) => `${file.path}\0${file.bytes}\0${file.sha256}\n`).join(''));
}
function collect(rel, files) {
  const absolute = path.join(root, rel);
  if (!fs.existsSync(absolute)) return;
  const stat = fs.lstatSync(absolute);
  if (stat.isSymbolicLink()) throw new Error(`SYMLINK_REQUIRES_REVIEW: ${rel}`);
  if (stat.isDirectory()) {
    for (const entry of fs.readdirSync(absolute, { withFileTypes: true })) {
      if (entry.name === 'cache' && rel === 'bootstrap') continue;
      if (entry.name.startsWith('.')) continue;
      collect(path.posix.join(rel, entry.name), files);
    }
    return;
  }
  if (!stat.isFile() || !/\.(php|mjs|js|css|scss|json|svg|ps1)$/i.test(rel)) return;
  const bytes = fs.readFileSync(absolute);
  files.push({ path: rel.replaceAll('\\', '/'), bytes: bytes.length, sha256: sha256(bytes) });
}

function run() {
  if (!fs.existsSync(manifestFile)) {
    throw new Error('Saved manifest missing: reports/p11-source-manifest.json');
  }
  const saved = JSON.parse(fs.readFileSync(manifestFile, 'utf8'));
  if (saved.schema !== 1 || saved.scope !== 'allowlisted-code-only'
    || !Array.isArray(saved.files)) {
    throw new Error('Manifest schema/scope invalid; stop for manual review.');
  }
  const current = [];
  for (const dir of roots) collect(dir, current);
  for (const file of named) collect(file, current);
  current.sort((a, b) => a.path.localeCompare(b.path, 'en'));
  const savedFiles = saved.files;
  const bySavedPath = new Map(savedFiles.map((f) => [f.path, f]));
  const byCurrentPath = new Map(current.map((f) => [f.path, f]));
  const duplicates = savedFiles.length - bySavedPath.size;
  const changed = savedFiles.filter((f) => {
    const actual = byCurrentPath.get(f.path);
    return actual && (f.bytes !== actual.bytes || f.sha256 !== actual.sha256);
  }).map((f) => f.path);
  const missing = savedFiles.filter((f) => !byCurrentPath.has(f.path)).map((f) => f.path);
  const added = current.filter((f) => !bySavedPath.has(f.path)).map((f) => f.path);
  const savedDigestOK = saved.sha256 === checksum(savedFiles) && saved.count === savedFiles.length;
  const currentDigest = checksum(current);
  const orderOK = savedFiles.every((f, i) => f.path === current[i]?.path);
  console.log('P29 P11 READ-ONLY FINGERPRINT DIAGNOSIS');
  console.log(`Saved files: ${saved.count}; current files: ${current.length}`);
  console.log(`Saved manifest self-consistent: ${savedDigestOK ? 'YES' : 'NO'}`);
  console.log(`Inventory order matches: ${orderOK ? 'YES' : 'NO'}`);
  console.log(`CHANGED ${changed.length} | MISSING ${missing.length} | EXTRA ${added.length} | DUPLICATES ${duplicates}`);
  for (const [label, items] of [['CHANGED', changed], ['MISSING', missing], ['EXTRA', added]]) {
    for (const item of items.slice(0, 80)) console.log(`${label}: ${item}`);
    if (items.length > 80) console.log(`${label}: ...${items.length - 80} more (not shown)`);
  }
  const clean = savedDigestOK && duplicates === 0 && missing.length === 0 && added.length === 0
    && changed.length === 0 && orderOK && saved.sha256 === currentDigest;
  if (clean) {
    console.log('RESULT: MATCH. If P11 --verify still fails, review source-checker integrity.');
    return 0;
  }
  console.log('RESULT: MISMATCH. DO NOT run P11 --write, migrate, or disable security checks.');
  console.log('Review each listed file against its last approved version.');
  console.log('No source file, manifest, .env, or database was modified by this diagnostic.');
  return 2;
}
try {
  process.exitCode = run();
} catch (e) {
  console.error('BLOCKED DIAGNOSTIC: ' + e.message);
  process.exitCode = 2;
}
