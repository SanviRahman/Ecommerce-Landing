/** Offline view reference inventory: no Laravel boot, database or network. */
import fs from 'node:fs';
import path from 'node:path';
const root = path.resolve(import.meta.dirname, '../..');
const sources = [];
function collect(dir, extension) {
  const full = path.join(root, dir);
  for (const name of fs.readdirSync(full, { withFileTypes: true })) {
    const relative = path.posix.join(dir, name.name);
    if (name.isDirectory()) collect(relative, extension);
    else if (name.isFile() && name.name.endsWith(extension)) sources.push(relative);
  }
}
collect('app', '.php');
collect('resources/views', '.blade.php');
const references = [];
const missing = [];
const pattern = /(?:\bview|@include|@extends|@component)\s*\(\s*['"]([a-zA-Z0-9._:-]+)['"]/g;
for (const file of sources) {
  for (const match of fs.readFileSync(path.join(root, file), 'utf8').matchAll(pattern)) {
    const name = match[1];
    if (name.includes('::')) continue; // Laravel package namespace views.
    const target = path.join(root, 'resources/views', ...name.split('.')) + '.blade.php';
    references.push(name);
    if (!fs.existsSync(target)) missing.push(`${file}: ${name}`);
  }
}
if (missing.length) {
  for (const problem of missing) console.error('FAIL unresolved Blade view: ' + problem);
  process.exit(1);
}
if (references.length < 100) {
  console.error('FAIL view reference check unexpectedly small');
  process.exit(1);
}
for (const type of ['create', 'edit']) {
  const blade = fs.readFileSync(path.join(root, `resources/views/admin/products/${type}.blade.php`), 'utf8');
  if (!blade.includes("@include('admin.products.partials.form')")
      || !blade.includes("@extends('adminlte::page')")
      || !blade.includes("route('admin.products.index')")
      || !blade.includes("asset('frontend/js/product-page-standalone.js')")
      || /<script\s*>/i.test(blade)) {
    console.error(`FAIL standalone product ${type} page does not reuse the protected form.`);
    process.exit(1);
  }
}
console.log(`PASS ${references.length}/${references.length} internal view references resolve to Blade files.`);
if (!fs.existsSync(path.join(root, 'public/frontend/js/product-page-standalone.js'))) {
  console.error('FAIL missing direct-page JS asset');
  process.exit(1);
}
console.log('PASS Product create/edit direct URL views reuse CSRF-protected shared form and external JS.');
console.log('RESULT: P15 source/view linking PASS (no Laravel render/browser evidence).');
