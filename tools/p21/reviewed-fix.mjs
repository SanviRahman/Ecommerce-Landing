/** P21 exact snapshot repair. Default read-only. No Laravel/DB/credential access. */
import fs from 'node:fs';
import path from 'node:path';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';
import { spawnSync } from 'node:child_process';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const mode = process.argv[2] || '--diagnose';
const sha = (buf) => createHash('sha256').update(buf).digest('hex');
const hash = (filename) => fs.existsSync(filename) ? sha(fs.readFileSync(filename)) : null;
const baseline = path.join(root, 'tools/p20/p19-reviewed-manifest.json');
const motion = path.join(root, 'public/frontend/css/storefront-motion.css');
const savedMotion = path.join(root, 'reports/p21-preserved-source/storefront-motion.a026f6611fb7.css');
const checker = path.join(root, 'tools/qa/check-p16-reviews-sidebar.php');
const template = path.join(root, 'tools/p20/templates/check-p16-reviews-sidebar.php');
const p20 = path.join(root, 'tools/p20/fix-p11-p19-reviewed.mjs');
const known = {
  baseline: '7565f48f47893aceb18f071df805258cddd5ff19fc2aadbec9e4fb5b8a1865f3',
  motion: 'a026f6611fb7418c7495b41dbcb57e36d69aa7e8e77281da856ba99478b581d5',
  checker: 'dc898c660f3df02aa4f14c96840814f03d918cd792ef146e0eba03c94c2a5377',
  oldChecker: '93aae367b625f9c58aa790785f1ebd9e9d9b76a793d2392c388d7ad98160613c',
  oldAdmin: '65d37813263bc301f7d70cee39cdf0a1dc08e71bae0f4f0861c0fb8bac583747',
};
const dirs = ['app','bootstrap','config','database/migrations','resources/views',
  'resources/css','resources/js','routes','tests','tools/qa','public/frontend'];
const names = ['composer.json','composer.lock','package.json','package-lock.json',
  'vite.config.js','phpunit.xml','phpunit.p01.xml','phpunit.p03.xml',
  'phpunit.p04.xml','phpunit.p05.xml','phpunit.p06.xml','phpunit.p07.xml',
  'phpunit.p08.xml','phpunit.p10.xml'];

function collect() {
  const result = new Map();
  function visit(rel) {
    const file = path.join(root, rel);
    if (!fs.existsSync(file)) return;
    const st = fs.lstatSync(file);
    if (st.isSymbolicLink()) throw Error('Unexpected symlink: ' + rel);
    if (st.isDirectory()) {
      for (const name of fs.readdirSync(file)) {
        if (name.startsWith('.') || rel === 'bootstrap' && name === 'cache') continue;
        visit(path.posix.join(rel, name));
      }
    } else if (st.isFile() && /\.(php|mjs|js|css|scss|json|svg|ps1)$/i.test(rel)) {
      const contents = fs.readFileSync(file);
      result.set(rel.replaceAll('\\', '/'), {bytes: contents.length, sha256: sha(contents)});
    }
  }
  for (const rel of [...dirs, ...names]) visit(rel);
  return result;
}
function inspect() {
  if (hash(baseline) !== known.baseline) throw Error('P20 reference manifest missing or different. Stop.');
  if (hash(template) !== known.checker || !fs.existsSync(p20)) throw Error('P20 reviewed tool files incomplete. Stop.');
  const ref = JSON.parse(fs.readFileSync(baseline, 'utf8'));
  if (ref.schema !== 1 || ref.scope !== 'allowlisted-code-only' || ref.count !== 543) throw Error('Invalid P19 reference manifest.');
  const expected = new Map(ref.files.map(x => [x.path, x]));
  const files = collect();
  const unknown = [];
  for (const [name, file] of files) {
    const item = expected.get(name);
    if (item && item.bytes === file.bytes && item.sha256 === file.sha256) continue;
    if (name === 'public/frontend/css/storefront-motion.css' && file.sha256 === known.motion) continue;
    if (name === 'tools/qa/check-p16-reviews-sidebar.php' &&
       [known.oldChecker,known.checker].includes(file.sha256)) continue;
    if (name === 'config/adminlte.php' && file.sha256 === known.oldAdmin) continue;
    unknown.push(`${item ? 'MODIFIED' : 'UNREVIEWED'} ${name} sha256=${file.sha256.slice(0,16)}`);
  }
  for (const name of expected.keys()) if (!files.has(name)) unknown.push('MISSING ' + name);
  if (fs.existsSync(savedMotion) && hash(savedMotion) !== known.motion) unknown.push('Unexpected existing preservation backup');
  if (fs.existsSync(motion) && hash(motion) !== known.motion) unknown.push('Unexpected motion CSS hash');
  if (fs.existsSync(checker) && ![known.checker,known.oldChecker].includes(hash(checker))) unknown.push('Unknown P16 checker');
  const savedManifestPath = path.join(root, 'reports/p11-source-manifest.json');
  if (!fs.existsSync(savedManifestPath)) unknown.push('P11 manifest missing');
  else {
    try {
      const saved = JSON.parse(fs.readFileSync(savedManifestPath, 'utf8'));
      if (saved.schema !== 1 || saved.scope !== 'allowlisted-code-only' || ![543,544].includes(saved.count)) {
        unknown.push('P11 manifest schema/count unexpected');
      } else if (saved.count === 543 && hash(savedManifestPath) !== known.baseline) {
        unknown.push('Unknown 543-file fingerprint (not reviewed P19 snapshot)');
      } else if (saved.count === 544 &&
                 (!saved.files.some(f => f.path === 'tools/qa/check-p16-reviews-sidebar.php' && f.sha256 === known.checker)
                   || hash(checker) !== known.checker)) {
        unknown.push('Unrecognized 544-file fingerprint state');
      }
    } catch { unknown.push('Invalid P11 manifest JSON'); }
  }
  return { unknown, count:files.size, extra:fs.existsSync(motion), checker:fs.existsSync(checker) };
}
function run(cmd, args) {
  const res = spawnSync(cmd, args, {cwd:root, encoding:'utf8', timeout:60000, shell:false});
  if (res.stdout) process.stdout.write(res.stdout);
  if (res.stderr) process.stderr.write(res.stderr);
  if (res.error || res.status !== 0) throw Error(`${cmd} failed (exit ${res.status || res.error?.message})`);
}
if (!['--diagnose','--fix-reviewed'].includes(mode)) {
  console.error('Usage: node tools/p21/reviewed-fix.mjs --diagnose | --fix-reviewed');
  process.exit(2);
}
try {
  const result = inspect();
  console.log(`P21 exact-source audit: ${result.count} source files; leftover CSS=${result.extra}; P16 checker present=${result.checker}.`);
  for (const msg of result.unknown) console.error('BLOCKED ' + msg);
  if (result.unknown.length) {
    console.error('STOP: Source differs from reviewed P19 baseline. No files changed.');
    process.exitCode = 2;
  } else if (mode === '--diagnose') {
    console.log('RESULT: REVIEWED_VARIANT_ONLY; read-only. To apply run --fix-reviewed.');
  } else {
    if (result.extra) {
      fs.mkdirSync(path.dirname(savedMotion), {recursive:true});
      if (!fs.existsSync(savedMotion)) fs.copyFileSync(motion, savedMotion, fs.constants.COPYFILE_EXCL);
      if (hash(savedMotion) !== known.motion || hash(motion) !== known.motion) throw Error('Motion changed during preservation');
      fs.unlinkSync(motion);
      console.log('PASS Preserved unused CSS in reports/p21-preserved-source; original public copy removed.');
    }
    if (!result.checker) {
      fs.copyFileSync(template, checker, fs.constants.COPYFILE_EXCL);
      console.log('PASS Restored verified P16 Review sidebar QA script.');
    }
    const secondReview = inspect();
    if (secondReview.unknown.length) throw Error('Unexpected files after review; stop.');
    run(process.execPath, [p20, '--fix-reviewed']);
    run(process.execPath, [path.join(root, 'tools/qa/p11-source-manifest.mjs'), '--verify']);
    console.log('RESULT: P21 SOURCE REPAIR PASS (run PHP/P17/P19/P11 offline tests separately).');
  }
} catch (e) {
  console.error('BLOCKED ' + e.message);
  process.exitCode = 2;
}
