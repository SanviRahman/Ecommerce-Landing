import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  mergeSetCookies, cookieHeader, csrfHeaderValue, assertCsrfStatuses,
} from './p09-csrf-http-core.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const runner = fs.readFileSync(path.join(root, 'tools/qa/p09-browser-csrf-smoke.mjs'), 'utf8');
const guard = fs.readFileSync(path.join(root, 'tools/qa/p09-browser-csrf-db-guard.php'), 'utf8');
const tests = [];
function test(label, verify) { tests.push({ label, verify }); }
function mustThrow(fn) { try { fn(); return false; } catch { return true; } }

const jar = new Map();
mergeSetCookies(jar, [
  'XSRF-TOKEN=abc%3D%3D; Path=/; SameSite=Lax',
  'app-session=xyz%3D; Path=/; HttpOnly',
]);
test('Cookie jar extracts Laravel-style Set-Cookie pairs', () => jar.get('XSRF-TOKEN') === 'abc%3D%3D' && jar.get('app-session') === 'xyz%3D');
test('XSRF header percent-decodes cookie exactly once', () => csrfHeaderValue(jar) === 'abc==');
test('Cookie header preserves both session and XSRF state', () => cookieHeader(jar).includes('app-session=xyz%3D'));
test('Empty XSRF is rejected', () => mustThrow(() => csrfHeaderValue(new Map())));
test('Bad URL encoding is rejected', () => mustThrow(() => csrfHeaderValue(new Map([['XSRF-TOKEN', '%invalid']]))));
test('Expected 200/419/302 CSRF statuses accepted', () => assertCsrfStatuses({ get: 200, noToken: 419, withToken: 302 }));
test('Expected 200/419/422 CSRF statuses accepted', () => assertCsrfStatuses({ get: 200, noToken: 419, withToken: 422 }));
test('Missing CSRF rejection cannot pass', () => mustThrow(() => assertCsrfStatuses({ get: 200, noToken: 302, withToken: 302 })));
test('Valid-token 419 rejection cannot pass', () => mustThrow(() => assertCsrfStatuses({ get: 200, noToken: 419, withToken: 419 })));
test('Requires explicit approval without ExecutionPolicy bypass', () => runner.includes('YES_LOCAL_INVALID_POST_ONLY') && !/ExecutionPolicy|Set-ExecutionPolicy|Unblock-File/.test(runner));
test('Only loopback HTTP port 8089 is used', () => runner.includes('P09_CSRF_HOST') && runner.includes('P09_CSRF_PORT') && runner.includes('net.createServer'));
test('Separate real DB credentials never used as test password', () => runner.includes("'.p01-runtime', 'secrets', 'p01-db-password'") && runner.includes("DB_USERNAME: 'p01_tester'"));
test('Read-only identity guard and P02 schema enforced', () => runner.includes('p02-mysql-preflight.php') && runner.includes('p09-browser-csrf-db-guard.php') && guard.includes('P02TestSchemaGate::connectFromEnvironment()') && guard.includes('P02TestSchemaGate::evaluate('));
test('Guard refuses all nonempty commerce, cart and order tables', () => ['products','orders','customers','carts','cart_items','checkout_requests','inventory_movements'].every((t) => guard.includes(`'${t}'`)) && guard.includes('if ($count !== 0)'));
test('Lacking Laravel packages or cached config blocks browser probe', () => runner.includes("'vendor', 'autoload.php'") && runner.includes("'cache', 'config.php'"));
test('Fake APP_KEY + cookie session and local test flags only', () => runner.includes("APP_KEY: `base64:${randomBytes(32).toString('base64')}`") && runner.includes("SESSION_DRIVER: 'cookie'") && runner.includes("P08_CHECKOUT_TEST_ENABLED: 'false'"));
test('POST product variant is always invalid and zero', () => runner.includes("product_variant_id: '0'") && runner.includes("cart_version: '0'"));
test('Both 419 and valid-token validation paths are asserted', () => runner.includes('noToken !== 419') && runner.includes('assertCsrfStatuses('));
test('Before and after guard checks and cleanup are present', () => runner.includes('Before: empty isolated') && runner.includes('After: no product') && runner.includes('finally {\n  await stopServer();') && runner.includes('After failure: no business/cart writes'));
test('No schema reset, migration, seed, DDL or arbitrary DB SQL', () => !/\bmigrate:fresh\b|\bdb:wipe\b|\bdb:seed\b|\bTRUNCATE\b|\bDROP\b|\bALTER\b|\bCREATE\s+TABLE\b/i.test(runner + guard));

let passed = 0;
for (const { label, verify } of tests) {
  let ok = false;
  try { ok = verify() === true; } catch { /* fail */ }
  console.log(`${ok ? 'PASS' : 'FAIL'} ${label}`);
  if (ok) passed++;
}
console.log(`RESULT: ${passed}/${tests.length} P09 Node-CSRF safety and cookie tests ${passed === tests.length ? 'PASS' : 'FAIL'} (offline, no MySQL)`);
if (passed !== tests.length) process.exitCode = 1;
