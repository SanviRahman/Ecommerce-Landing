#!/usr/bin/env node
/**
 * P09 actual HTTP CSRF smoke without a PowerShell .ps1 or policy bypass.
 * Local PHP built-in web server + restricted p01_tester only.
 * The two POSTs ALWAYS use product_variant_id=0 and cannot add any item.
 * No Laravel migrations, seeds, schema changes, or browser JS are needed.
 */
import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import { randomBytes } from 'node:crypto';
import { spawn, spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import {
  P09_CSRF_HOST, P09_CSRF_PORT,
  mergeSetCookies, cookieHeader, csrfHeaderValue, assertCsrfStatuses,
} from './p09-csrf-http-core.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const host = P09_CSRF_HOST;
const port = P09_CSRF_PORT;
const php = process.platform === 'win32' ? 'php.exe' : 'php';
const secretPath = path.resolve(root, '..', '.p01-runtime', 'secrets', 'p01-db-password');
const origin = `http://${host}:${port}`;
let server = null;
let serverExit = false;
let postflightEnvironment = null;
let postflightConfirmed = false;
const pause = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function blocked(message) {
  const error = new Error(message);
  error.p09Code = 2;
  throw error;
}

function requireSafeSource() {
  if (process.env.P09_BROWSER_CSRF_APPROVED !== 'YES_LOCAL_INVALID_POST_ONLY') {
    blocked('Explicit P09_BROWSER_CSRF_APPROVED=YES_LOCAL_INVALID_POST_ONLY required.');
  }
  if (!fs.existsSync(path.join(root, 'vendor', 'autoload.php')) ||
      !fs.existsSync(path.join(root, 'bootstrap', 'app.php'))) {
    blocked('Laravel vendor/bootstrap missing. Run from the installed website root.');
  }
  if (fs.existsSync(path.join(root, 'bootstrap', 'cache', 'config.php')) ||
      fs.readdirSync(path.join(root, 'bootstrap', 'cache')).some((f) => /^routes-.*\.php$/.test(f))) {
    blocked('Cached Laravel config/routes present; cannot prove isolated environment. Do not clear production caches automatically.');
  }
  if (!fs.existsSync(secretPath)) {
    blocked('Restricted p01_tester password file missing. Never substitute original DB credentials.');
  }
}

function runPhpReadOnly(label, filename, env) {
  const result = spawnSync(php, [path.join(root, 'tools', 'qa', filename)], {
    cwd: root, env, encoding: 'utf8', timeout: 15000, windowsHide: true,
  });
  if (result.error || result.status !== 0) {
    blocked(`${label} rejected. ${String(result.stderr || result.stdout || result.error?.message || '')
      .replace(/\s+/g, ' ').slice(0, 280)}`);
  }
  if (!String(result.stdout).includes('PASS ')) blocked(`${label} did not confirm PASS.`);
  process.stdout.write(`PASS ${label}\n`);
}

function isolatedEnvironment(secret) {
  return {
    ...process.env,
    P02_TEST_DB_PASSWORD: secret,
    P09_HTTP_CSRF_GUARD: 'YES_READ_ONLY',
    DB_CONNECTION: 'mysql', DB_HOST: host, DB_PORT: '3306',
    DB_DATABASE: 'p01_campaign_test', DB_USERNAME: 'p01_tester',
    DB_PASSWORD: secret, DB_URL: '', DB_SOCKET: '',
    APP_ENV: 'local', APP_DEBUG: 'false', APP_URL: origin,
    APP_KEY: `base64:${randomBytes(32).toString('base64')}`,
    SESSION_DRIVER: 'cookie', SESSION_DOMAIN: '', SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'array',
    MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync',
    LOG_CHANNEL: 'stderr', LOG_LEVEL: 'error',
    P07_CATALOG_TEST_ENABLED: 'true', P08_CART_TEST_ENABLED: 'true',
    P08_CHECKOUT_TEST_ENABLED: 'false',
  };
}

async function assertPortUnused() {
  await new Promise((resolve, reject) => {
    const socket = net.createServer();
    socket.once('error', () => reject(new Error(`Local port ${port} is occupied; refusing to connect to an unrelated service.`)));
    socket.listen(port, host, () => socket.close(resolve));
  });
}

async function request(pathname, options, cookies) {
  const headers = new Headers(options?.headers || {});
  if (cookies.size > 0) headers.set('Cookie', cookieHeader(cookies));
  const response = await fetch(origin + pathname, {
    ...options, headers, redirect: 'manual', signal: AbortSignal.timeout(5500),
  });
  mergeSetCookies(cookies, response.headers.getSetCookie());
  await response.arrayBuffer();
  return response.status;
}

async function waitUntilUp(cookies) {
  let lastStatus = 'server not accepting requests';
  for (let attempt = 0; attempt < 32; attempt++) {
    if (serverExit) break;
    try {
      const status = await request('/storefront/cart', { method: 'GET' }, cookies);
      if (status === 200) return 200;
      lastStatus = `HTTP ${status}`;
      // Gate/auth failures are not transient; do not retry or post to them.
      if ([403, 404, 500, 503].includes(status)) break;
    } catch {
      // Only wait for the isolated PHP child to start listening.
    }
    await pause(180);
  }
  throw new Error(`Isolated GET /storefront/cart failed: ${lastStatus}. Check test-only flags, Laravel config and PHP extensions.`);
}

async function stopServer() {
  if (!server || serverExit) return;
  server.kill();
  await Promise.race([
    new Promise((resolve) => server.once('close', resolve)),
    pause(2500),
  ]);
  if (!serverExit) server.kill('SIGKILL');
}

async function main() {
  requireSafeSource();
  let secret = fs.readFileSync(secretPath, 'utf8').replace(/(?:\r?\n)+$/, '');
  if (secret.length === 0) blocked('Restricted test password file is empty.');
  const env = isolatedEnvironment(secret);
  secret = ''; // Never log or pass it through CLI command arguments.

  runPhpReadOnly('Restricted p01_tester MySQL identity / original DB denial', 'p02-mysql-preflight.php', env);
  runPhpReadOnly('Before: empty isolated P02 cart/commerce schema', 'p09-browser-csrf-db-guard.php', env);
  postflightEnvironment = env;
  await assertPortUnused();

  // Launch php directly rather than "artisan serve" to avoid an orphan child
  // after the local test and to remain portable across Windows and Linux.
  server = spawn(php, ['-S', `${host}:${port}`, '-t', 'public', 'public/index.php'], {
    cwd: root, env, stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true,
  });
  server.on('error', () => { serverExit = true; });
  server.on('close', () => { serverExit = true; });
  // Drain stderr; never echo request logs, tokens or restricted credentials.
  server.stderr.on('data', () => {});

  const cookies = new Map();
  const get = await waitUntilUp(cookies);
  console.log('PASS Local cart GET 200 from isolated server');
  csrfHeaderValue(cookies);

  const form = new URLSearchParams({
    product_variant_id: '0', quantity: '1', cart_version: '0',
  });
  const noToken = await request('/storefront/cart/items', {
    method: 'POST', body: form,
  }, cookies);
  if (noToken !== 419) throw new Error(`Missing-CSRF POST expected 419, got ${noToken}.`);
  console.log('PASS Missing-CSRF POST rejected with HTTP 419');

  const withToken = await request('/storefront/cart/items', {
    method: 'POST', body: new URLSearchParams(form),
    headers: { 'X-XSRF-TOKEN': csrfHeaderValue(cookies) },
  }, cookies);
  assertCsrfStatuses({ get, noToken, withToken });
  console.log('PASS Valid-CSRF POST with invalid variant reached validation (302/422)');
  await stopServer();
  runPhpReadOnly('After: no product, cart, order or inventory writes', 'p09-browser-csrf-db-guard.php', env);
  postflightConfirmed = true;
  console.log('RESULT: P09 3/3 REAL HTTP CSRF checks PASS (isolated test DB, no business rows)');
}

try { await main(); }
catch (error) {
  const code = error?.p09Code || 1;
  console.error(`${code === 2 ? 'BLOCKED' : 'FAIL'} P09 CSRF: ${error?.message || 'Unexpected error'}`);
  process.exitCode = code;
}
finally {
  await stopServer();
  // Even on HTTP failure, verify the invalid probe has not left any business rows.
  if (postflightEnvironment && !postflightConfirmed) {
    try {
      runPhpReadOnly('After failure: no business/cart writes', 'p09-browser-csrf-db-guard.php', postflightEnvironment);
    } catch (error) {
      console.error(`BLOCKED P09 postflight: ${error.message}`);
      process.exitCode = 1;
    }
  }
}
