/** Pure P09 HTTP CSRF utilities: no DB, process spawn, or network side effects. */
export const P09_CSRF_HOST = '127.0.0.1';
export const P09_CSRF_PORT = 8089;

export function mergeSetCookies(jar, headers) {
  if (!(jar instanceof Map)) throw new TypeError('Cookie jar must be a Map.');
  for (const header of headers) {
    const pair = String(header).split(';', 1)[0];
    const equal = pair.indexOf('=');
    if (equal <= 0) continue;
    const name = pair.slice(0, equal).trim();
    const value = pair.slice(equal + 1).trim();
    if (!/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/.test(name)) continue;
    if (value === '') jar.delete(name);
    else jar.set(name, value);
  }
  return jar;
}

export function cookieHeader(jar) {
  return [...jar].map(([name, value]) => `${name}=${value}`).join('; ');
}

export function csrfHeaderValue(jar) {
  const value = jar.get('XSRF-TOKEN');
  if (!value) throw new Error('Laravel did not set an XSRF-TOKEN cookie.');
  try { return decodeURIComponent(value); }
  catch { throw new Error('Malformed XSRF-TOKEN cookie value.'); }
}

export function assertCsrfStatuses({ get, noToken, withToken }) {
  if (get !== 200) throw new Error(`Cart GET expected 200; got ${get}.`);
  if (noToken !== 419) throw new Error(`Missing-CSRF POST expected 419; got ${noToken}.`);
  if (![302, 422].includes(withToken)) {
    throw new Error(`Valid-CSRF/invalid-product POST expected 302 or 422; got ${withToken}.`);
  }
  return true;
}
