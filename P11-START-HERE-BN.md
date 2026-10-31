# P10 validation regression repair + P11 release-readiness handoff

Source of truth: uploaded `website(8).zip`. This is an **only-updated-files** patch, not a new Laravel project.

## 1. P10 root cause and fix

- The failed 7-case P10 MySQL test injected a `ViewErrorBag` into its session while leaving the guest cart feature **disabled**. The resulting product page was a `Browsing preview`, not a usable Add-to-Cart form; the test never exercised the real validation redirect.
- The replacement regression enables **only the isolated test cart**, opens an actual product order form, sends an intentionally invalid SKU to the existing CSRF-protected Add-to-Cart route, asserts Laravel's `302` redirect and validation bag, then checks the product's visible alert and restored quantity.
- The product form now uses `old('quantity', 1)` instead of always resetting to quantity 1.
- Neither change authorizes production cart/checkout or writes an order. The test's synthetic DB changes are covered by `DatabaseTransactions`.

## 2. Install

1. Back up `D:\Ecommerce-Landing\website` code without publishing its `.env` or private data.
2. Unzip this patch directly over `D:\Ecommerce-Landing\website`, preserving directory names.
3. Do **not** run migrations, `migrate:fresh`, broad seeds, `key:generate`, or `composer setup`.
4. Do not enable feature flags for the original `ecommerce_landing_db`.

## 3. Offline regression checks

```powershell
cd D:\Ecommerce-Landing\website
node tools/qa/p11-offline-gate.mjs
node tools/qa/check-p11-release-contract.mjs
php tools/qa/check-p11-readiness-policy.php
node tools/qa/p11-source-manifest.mjs --verify
php tools/qa/check-p10-contact.php
node tools/qa/check-p10-integration.mjs
node tools/qa/check-p09-race-contract.mjs
```

The SHA-256 manifest checks a selected set of code/assets/dependency lockfiles against this *exact uploaded version + patch*. If additional local source edits were made after this archive, **do not silently overwrite or bypass them**; inspect the differences and regenerate the manifest *only after review*:

```powershell
node tools/qa/p11-source-manifest.mjs --write
node tools/qa/p11-source-manifest.mjs --verify
```

The manifest never reads `.env`, user uploads, client PDFs, logs, or production data.

## 4. P10 MySQL rerun (rollback-only)

```powershell
$secretFile = 'D:\Ecommerce-Landing\.p01-runtime\secrets\p01-db-password'
$env:P02_TEST_DB_PASSWORD = [IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
$env:P10_TEST_WRITE_APPROVED = 'YES_ROLLBACK_ONLY'
try {
    php tools/qa/p10-mysql-ui-smoke.php
    Write-Host "Exit Code: $LASTEXITCODE"
} finally {
    Remove-Item Env:P02_TEST_DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:P10_TEST_WRITE_APPROVED -ErrorAction SilentlyContinue
}
```

Expected: 7 tests PASS and exit 0. The actual result is **pending** on your Windows/XAMPP setup. If it fails, send the exact failure; do not reset the database.

## 5. P11 read-only isolated data-readiness

The following tool checks migration/schema history, managed SKU stock mirrors, active simple defaults, available variable option combinations, published homepage pointers, and configured global shipping; its queries are aggregate SELECT statements. It cannot contact the original database.

```powershell
$secretFile = 'D:\Ecommerce-Landing\.p01-runtime\secrets\p01-db-password'
$env:P02_TEST_DB_PASSWORD = [IO.File]::ReadAllText($secretFile).TrimEnd("`r", "`n")
$env:P11_READ_ONLY_APPROVED = 'YES_ISOLATED_ONLY'
try {
    php tools/qa/p11-readiness-review.php
    Write-Host "Exit Code: $LASTEXITCODE"
} finally {
    Remove-Item Env:P02_TEST_DB_PASSWORD -ErrorAction SilentlyContinue
    Remove-Item Env:P11_READ_ONLY_APPROVED -ErrorAction SilentlyContinue
}
```

Status interpretation:

- `STAGING_DATA_CANDIDATE_ONLY` / exit 0: the checked data criteria are green **on the isolated DB only**, not a production release.
- `BLOCKED_DATA_READINESS` / exit 3: legitimate missing/unreconciled data, e.g. the empty test schema or an unpublished homepage. **Do not fake fixtures or change flags just to turn this green.**
- `BLOCKED` / exit 2: approval, schema identity, SQL, or runtime gate failed. Investigate; do not rerun with a different DB account.

## 6. Remaining owner-operated production gates

The repository `docs/14-implementation-sequence.md` names staging deployment **P10**; P11 is this *additional release/readiness handoff*, NOT proof staging/production has happened. Complete the separate operator sign-off template `docs/P11-release-signoff-template.md` on an approved staging environment. Still outstanding until independently evidenced:

- Staging restore of sanitized business data/media, migration upgrade and rollback-compatible restore rehearsal.
- Full admin/campaign/order/tracking/courier regression, variable-SKU cancellation/return, payment/stock reconciliation, and browser screenshots at 320/390/768/1440 px.
- Lockfile-backed dependency audit, asset build/cache/route checks, writable paths, document root, HTTPS/session cookies, security/CSRF, and real third-party isolation.
- Explicit business owner approval for inventory unit mappings, weight/pack SKUs, brand colors, checkout release and production migration/cutover.
- A compatible tested rollback plan. **Never roll back to the old stock writer after variant-managed orders exist.**

Do not enable feature flags, move media, deploy, reconcile historic production data, or run any production migration without separate explicit approval.
