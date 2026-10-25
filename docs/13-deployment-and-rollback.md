# Environment, Deployment and Rollback

These are commands for the implementing developer, not commands already executed on the user's server. Run only in the correct isolated environment. Never paste the whole document into a production terminal.

## 1. Read-only environment inventory

From the Laravel root after checking which directory/database this is:

```bash
php -v
node --version
npm --version
composer --version
git status --short
```

If vendor is already installed and the environment is safely configured:

```bash
php artisan --version
composer show laravel/framework
composer check-platform-reqs
php artisan route:list
php artisan migrate:status
```

Do not print `.env` or use broad config dumps that expose mail/courier/DB credentials. The source requires PHP ^8.3; verify every locked dependency's platform requirement. Node engine in the supplied Vite lock is ^20.19.0 or >=22.12.0. Composer itself is not bundled.

## 2. Development setup

Create a branch and an isolated local environment before installing anything. Preserve existing user work. Use `.env.example` with **local-only** credentials; never copy the uploaded production-like `.env` into CI. Disable/fake courier, external sync, mail and scheduled jobs. Set APP_URL/public storage paths to the local host. Do not run project `composer setup` because its script includes migrations, key generation and dependency installation as a bundle.

After reviewing the lockfiles and package scripts, normal locked installs are:

```bash
composer install --no-interaction
npm ci
```

Composer package discovery boots application code; perform it only after the environment is isolated. `--no-scripts` can be used during initial dependency inspection, followed by explicitly reviewed necessary scripts. Do not substitute `composer update` or unbounded package upgrades.

Generate an APP_KEY **only for a brand-new isolated development/test installation without a key**, never an existing deployment. Existing encrypted courier credentials depend on the original application key.

Once the implementation's migrations/test fixtures are ready on the disposable DB:

```bash
php artisan migrate
php artisan test
npm run build
php artisan view:cache
php artisan route:cache
```

Clear/rebuild caches only in the intended environment. The baseline ZIP has no tests directory; tests must be implemented before a meaningful `php artisan test` result exists. A browser-test command is added in Phase P09; do not pretend `npm run test:e2e` already exists.

## 3. Proposed readiness commands (not yet implemented)

Only after Phase P02/P03 creates/tests these commands:

```bash
php artisan storefront:backfill-default-variants --dry-run
php artisan storefront:readiness
```

Apply backfill only with backup, reconciled stock and a controlled write pause:

```bash
php artisan storefront:backfill-default-variants --apply
```

Document what readiness checked: custom-route collisions, product/variant completeness, ledger continuity, money types, global shipping setup, published layout references and permissions. A readiness command must not secretly seed/publish/alter inventory in read-only mode.

## 4. Staging checklist

Restore approved anonymized representative data and media into a separate staging environment; never send test orders to real couriers. Run fresh/upgrade migration tests, backfill twice, compare per-product stock, test money conversion, publish/restore, run the full security/browser/concurrency suite and rehearsal rollback. Confirm CLI/web use the same intended PUBLIC_DISK_ROOT/PUBLIC_DISK_URL. No automatic storage relocation or generic storage:link.

Pin dependency versions and build assets for the same tested commit. Ensure server routes rewrite to public/index.php, document root is the Laravel public directory, `.env` is not web-accessible, and uploaded media cannot execute PHP. Use least-privilege writable directories, not chmod 777.

## 5. Production sequence - explicit approval required

1. Verify latest backup and restoration access; capture code revision, DB schema and stock baseline. Pause all local order/stock writes, including admin tasks and relevant background jobs, during inventory reconciliation/cutover.
2. Deploy a schema-compatible application build with storefront disabled and shared inventory support. Reuse the existing APP_KEY and environment secrets.
3. Apply tested expand/widen migrations through CLI with deployment approval, never a GET admin URL.
4. Run approved backfill/reconciliation once, rerun dry-run to prove idempotency, and validate default variants/ledger/projection. Historical orders remain unmanaged until individually reconciled.
5. Build/deploy matching assets and refresh only relevant caches. Restart approved long-running workers so they use compatible inventory logic; prevent old workers from writing legacy stock.
6. Run smoke checks for existing campaigns, admin orders, tracking, new cart/details and draft preview. Validate global shipping and contacts.
7. Publish the reviewed homepage revision and switch root mode to storefront only after acceptance gates and business approvals are recorded. Resume writes using the unified inventory path.
8. Monitor validation/checkout failure rates, inventory mismatches, queue retries and error logs without recording customer/secret payloads.

## 6. Rollback strategy

**Layout rollback:** restore a retained revision into draft, preview and republish; commerce state stays unchanged.

**Storefront rollback:** switch root mode to campaign and/or disable new cart entrypoints using tested controls. Existing campaign checkout must still use the new inventory service. Do not clear carts/orders or reverse inventory because the homepage is switched off.

**Code rollback:** after managed variant orders exist, do NOT redeploy the original pre-inventory code that ignores variant allocations. Roll back only to a schema/inventory-compatible known-good build or roll forward a fix. Keep migrated tables/ledgers. Database restore after new orders would lose business transactions; it requires a separate incident/reconciliation plan, not a routine deployment rollback.

## 7. Never run against existing production as a shortcut

`migrate:fresh`, `db:wipe`, blanket `db:seed`, unconditional `key:generate`, unreviewed `composer setup`, arbitrary media cleanup, deleting lockfiles, disabling CSRF/auth, production DB snapshots committed to Git, or a global cache/session purge to hide a data bug.

## Meeting-SRS release blockers

Before enabling the new storefront mode, obtain approval for meeting/client conflicts G01 (actual ZIP vs described proyojonerbazar.com deployment), G02/G03 (weight/pack per-variant price + stock units), G04 (final global green HEX/brand) as needed for brand signoff, G10 (campaign-specific pricing/shipping), and G18 (cancel/returns stock transitions). Add before/after browser evidence for meeting tests MT01-MT45, explicit 4/2 grid, 25/75 CTA, sticky mobile bar and checkout. PDF is an AI-produced prior report and lacks independently verified recording; request actual owner approvals instead of treating PDF wording as a signed SRS.


## P11 supplementary read-only readiness (2026-10-25)

An optional user-requested P11 release handoff now includes `tools/qa/p11-readiness-review.php`, guarded to the separate `p01_campaign_test` / `p01_tester` database and requiring `P11_READ_ONLY_APPROVED=YES_ISOLATED_ONLY`. It only counts managed/legacy products, stock mirror mismatches, simple/variable SKU completeness, global shipping and pinned published homepage revisions. It **never** applies migrations, performs backfills, authorizes a deployment or reads secrets from `.env`. A blocked/empty schema must stay blocked until independently reviewed.

The offline code fingerprint lives in `reports/p11-source-manifest.json`; use `node tools/qa/p11-source-manifest.mjs --verify` and review source differences before updating it. This is code integrity evidence, not a DB backup. Use `docs/P11-release-signoff-template.md` for owner/operator sign-off. This supplements rather than replaces the P09/P10 deployment sequence. The temporary P02/P03/P07/P08 test-only gates are intentionally *not* bypassed to simulate production.
