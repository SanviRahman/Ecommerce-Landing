/**
 * P20: safe P19 fingerprint reconciliation for the known P16 -> P17 -> P19 patch chain.
 * Default mode is read-only. --fix-reviewed writes only after every source file
 * matches the reviewed P19 baseline, allowing only exact P16 config/checker variants.
 * This script never reads .env, vendor, storage, logs, uploads or the database.
 */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const scriptDir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(scriptDir, '../..');
const baselinePath = path.join(scriptDir, 'p19-reviewed-manifest.json');
const configTemplate = path.join(scriptDir, 'templates/adminlte.php');
const checkerTemplate = path.join(scriptDir, 'templates/check-p16-reviews-sidebar.php');
const manifestPath = path.join(root, 'reports/p11-source-manifest.json');
const verifierPath = path.join(root, 'tools/qa/p11-source-manifest.mjs');
const configPath = 'config/adminlte.php';
const p16CheckerPath = 'tools/qa/check-p16-reviews-sidebar.php';
const mode = process.argv[2] ?? '--diagnose';

const P16_CONFIG_SHA = '65d37813263bc301f7d70cee39cdf0a1dc08e71bae0f4f0861c0fb8bac583747';
const P19_CONFIG_SHA = '80cecb50ac09c29a24c865d2a39739d06fade112047f91e4b075a73b1eb4b631';
const P16_CHECKER_OLD_SHA = '93aae367b625f9c58aa790785f1ebd9e9d9b76a793d2392c388d7ad98160613c';
const P16_CHECKER_FIXED_SHA = 'dc898c660f3df02aa4f14c96840814f03d918cd792ef146e0eba03c94c2a5377';
const roots = [
  'app', 'bootstrap', 'config', 'database/migrations', 'resources/views',
  'resources/css', 'resources/js', 'routes', 'tests', 'tools/qa', 'public/frontend',
];
const named = [
  'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
  'vite.config.js', 'phpunit.xml', 'phpunit.p01.xml', 'phpunit.p03.xml',
  'phpunit.p04.xml', 'phpunit.p05.xml', 'phpunit.p06.xml', 'phpunit.p07.xml',
  'phpunit.p08.xml', 'phpunit.p10.xml',
];
const digest = (value) => createHash('sha256').update(value).digest('hex');
const short = (value) => value ? value.slice(0, 14) : '(missing)';

function inventory() {
  const files = [];
  function collect(relative) {
    const target = path.join(root, relative);
    if (!fs.existsSync(target)) return;
    const stat = fs.lstatSync(target);
    if (stat.isSymbolicLink()) throw Error(`Unexpected source symlink: ${relative}`);
    if (stat.isDirectory()) {
      for (const entry of fs.readdirSync(target, { withFileTypes: true })) {
        if (entry.name === 'cache' && relative === 'bootstrap') continue;
        if (entry.name.startsWith('.')) continue;
        collect(path.posix.join(relative, entry.name));
      }
      return;
    }
    if (!stat.isFile() || !/\.(php|mjs|js|css|scss|json|svg|ps1)$/i.test(relative)) return;
    const buffer = fs.readFileSync(target);
    files.push({ path: relative.replaceAll('\\', '/'), bytes: buffer.length, sha256: digest(buffer) });
  }
  for (const dir of roots) collect(dir);
  for (const name of named) collect(name);
  files.sort((a, b) => a.path.localeCompare(b.path, 'en'));
  if (files.length < 150) throw Error(`Source inventory unexpectedly small: ${files.length}`);
  return files;
}
function makeManifest(files) {
  return {
    schema: 1,
    scope: 'allowlisted-code-only',
    count: files.length,
    sha256: digest(files.map((f) => `${f.path}\u0000${f.bytes}\u0000${f.sha256}\n`).join('')),
    files,
  };
}
function sameFile(a, b) {
  return Boolean(a && b && a.bytes === b.bytes && a.sha256 === b.sha256);
}
function atomicWrite(target, content) {
  fs.mkdirSync(path.dirname(target), { recursive: true });
  const tmp = `${target}.p20-${process.pid}.tmp`;
  try {
    fs.writeFileSync(tmp, content, { flag: 'wx' });
    fs.renameSync(tmp, target);
  } finally {
    if (fs.existsSync(tmp)) fs.unlinkSync(tmp);
  }
}
function backup(target, label) {
  const dir = path.join(root, 'reports/p20-source-backup');
  fs.mkdirSync(dir, { recursive: true });
  const dest = path.join(dir, `${label}.before-${process.pid}.bak`);
  if (fs.existsSync(target)) fs.copyFileSync(target, dest, fs.constants.COPYFILE_EXCL);
  return dest;
}

if (!['--diagnose', '--fix-reviewed'].includes(mode)) {
  console.error('Usage: node tools/p20/fix-p11-p19-reviewed.mjs [--diagnose | --fix-reviewed]');
  process.exit(2);
}

try {
  const baseline = JSON.parse(fs.readFileSync(baselinePath, 'utf8'));
  if (baseline.schema !== 1 || baseline.scope !== 'allowlisted-code-only' || baseline.count !== 543) {
    throw Error('Reviewed P19 baseline is unexpected. Stop without modifying files.');
  }
  if (digest(fs.readFileSync(configTemplate)) !== P19_CONFIG_SHA) {
    throw Error('Reviewed P19 adminlte template hash mismatch. Stop.');
  }
  if (digest(fs.readFileSync(checkerTemplate)) !== P16_CHECKER_FIXED_SHA) {
    throw Error('Reviewed P16 checker template hash mismatch. Stop.');
  }

  const actual = inventory();
  const expectedByPath = new Map(baseline.files.map((file) => [file.path, file]));
  const actualByPath = new Map(actual.map((file) => [file.path, file]));
  const allowedChanges = [];
  const blocked = [];
  let configVersion = 'P19';
  let checkerVersion = 'absent';

  for (const [filePath, file] of actualByPath) {
    if (filePath === configPath && !sameFile(file, expectedByPath.get(filePath))) {
      if (file.sha256 === P16_CONFIG_SHA) {
        configVersion = 'P16 exact reviewed variant';
        allowedChanges.push(`Reviewed P16 config variant: ${filePath} (${short(file.sha256)})`);
      } else {
        blocked.push(`MODIFIED ${filePath}: ${short(file.sha256)}; expected P19 ${short(expectedByPath.get(filePath)?.sha256)}`);
      }
      continue;
    }
    if (filePath === p16CheckerPath) {
      if (file.sha256 === P16_CHECKER_OLD_SHA) {
        checkerVersion = 'P16 old checker';
        allowedChanges.push(`Reviewed old P16 checker will be updated: ${filePath}`);
      } else if (file.sha256 === P16_CHECKER_FIXED_SHA) {
        checkerVersion = 'P18-compatible checker';
        allowedChanges.push(`Reviewed P18-compatible P16 checker: ${filePath}`);
      } else {
        blocked.push(`UNKNOWN ${filePath}: ${short(file.sha256)}; only reviewed P16/P18 hashes are allowed`);
      }
      continue;
    }
    if (!expectedByPath.has(filePath)) {
      blocked.push(`ADDED ${filePath} (${short(file.sha256)})`);
    } else if (!sameFile(file, expectedByPath.get(filePath))) {
      blocked.push(`MODIFIED ${filePath}: ${short(file.sha256)}; expected ${short(expectedByPath.get(filePath)?.sha256)}`);
    }
  }
  for (const filePath of expectedByPath.keys()) {
    if (!actualByPath.has(filePath)) blocked.push(`MISSING ${filePath}`);
  }

  const currentManifest = makeManifest(actual);
  let storedMatches = false;
  if (fs.existsSync(manifestPath)) {
    try {
      const stored = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
      storedMatches = stored.schema === 1 && stored.scope === 'allowlisted-code-only'
        && stored.count === currentManifest.count && stored.sha256 === currentManifest.sha256
        && JSON.stringify(stored.files) === JSON.stringify(currentManifest.files);
    } catch { storedMatches = false; }
  }

  console.log(`P20 P19 fingerprint audit: ${actual.length} allowlisted source files.`);
  console.log(`Stored manifest: ${storedMatches ? 'MATCHES current source' : 'DOES NOT MATCH current source'}.`);
  console.log(`Reviewed config: ${configVersion}; P16 checker: ${checkerVersion}.`);
  for (const item of allowedChanges) console.log(`INFO  ${item}`);
  for (const item of blocked.slice(0, 100)) console.error(`BLOCKED ${item}`);
  if (blocked.length > 100) console.error(`BLOCKED ... and ${blocked.length - 100} more differences`);
  if (blocked.length) {
    console.error(`STOP: ${blocked.length} unreviewed source difference(s). No files modified. Keep your current files and send this diagnostic output for review.`);
    process.exitCode = 2;
  } else if (mode === '--diagnose') {
    console.log('RESULT: Source matches reviewed P19 baseline plus only approved P16 checker/config variants. No files modified.');
    console.log('NEXT: run node tools/p20/fix-p11-p19-reviewed.mjs --fix-reviewed');
  } else {
    let changed = false;
    if (configVersion === 'P16 exact reviewed variant') {
      const previous = backup(path.join(root, configPath), 'adminlte.php');
      atomicWrite(path.join(root, configPath), fs.readFileSync(configTemplate));
      console.log(`PASS Updated exact reviewed P16 config to P19 config. Backup: ${path.relative(root, previous)}`);
      changed = true;
    }
    if (checkerVersion === 'P16 old checker') {
      const previous = backup(path.join(root, p16CheckerPath), 'check-p16-reviews-sidebar.php');
      atomicWrite(path.join(root, p16CheckerPath), fs.readFileSync(checkerTemplate));
      console.log(`PASS Updated exact reviewed P16 checker to P18-compatible version. Backup: ${path.relative(root, previous)}`);
      changed = true;
    }
    const current = makeManifest(inventory());
    let finalStoredMatches = false;
    if (fs.existsSync(manifestPath)) {
      try {
        const stored = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
        finalStoredMatches = stored.schema === 1 && stored.scope === 'allowlisted-code-only'
          && stored.count === current.count && stored.sha256 === current.sha256
          && JSON.stringify(stored.files) === JSON.stringify(current.files);
      } catch { finalStoredMatches = false; }
    }
    if (!finalStoredMatches) {
      const previous = backup(manifestPath, 'p11-source-manifest.json');
      atomicWrite(manifestPath, JSON.stringify(current, null, 2) + '\n');
      console.log(`PASS Rebuilt P11 manifest from reviewed source (${current.count} files). Backup: ${path.relative(root, previous)}`);
      changed = true;
    }
    const verification = spawnSync(process.execPath, [verifierPath, '--verify'], {
      cwd: root, encoding: 'utf8', timeout: 30000,
    });
    if (verification.status !== 0) {
      console.error(verification.stderr || verification.stdout || 'P11 source verifier failed.');
      throw Error('P11 --verify failed; backups retained. Do not deploy.');
    }
    console.log((verification.stdout || '').trim());
    console.log(changed ? 'RESULT: Reviewed P19 fingerprint fix complete.' : 'RESULT: No repair needed; fingerprint already matches.');
  }
} catch (error) {
  console.error(`ERROR: ${error.message}`);
  process.exitCode = 1;
}
