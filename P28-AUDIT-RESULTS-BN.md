# P28 — Isolated Account Migration Safety QA

Issue evidence (user's latest PowerShell): `p02-mysql-preflight.php` verified p01_campaign_test / p01_tester, denied access to ecommerce_landing_db, then P26 schema preflight stopped because `storefront_accounts` is missing.

Verified code cause: `database/migrations/2026_10_26_100001_create_storefront_accounts.php` creates the table and adds nullable `orders.storefront_account_id`; user previously migrated it in `ecommerce_landing_db`, which is a different database. Existing `app/Support/P02MigrationSafety.php` authorizes a correctly identified restricted p01_campaign_test connection for a forward migration without modifying production checks.

P28 solution: standalone, explicit, test-only one-migration helper in `tools/p28/` (outside P11 source-fingerprint include roots). Its read-only check rejects missing P02 prerequisites, inconsistent account table/order-column/migration-history artifacts, and unreviewed migration file hash. Apply mode additionally requires actual isolated-DB identity/grants preflight, exact text confirmation, approved restricted credentials, no Laravel config cache, and explicit child-process database overrides. No implicit migration, seed, reset, rollback, original `.env` write, main DB grants, or external API call.

Local isolated file-copy checks (no actual MySQL):
- `php -l tools/p28/isolate-account-migration.php`: PASS
- `php tools/p28/isolate-account-migration.php --self-test`: 4/4 PASS
- `node tools/qa/p11-source-manifest.mjs --verify`: PASS, 557 files
- `node tools/qa/check-p26-local-activation.mjs`: 21/21 PASS
- `node tools/qa/p11-offline-gate.mjs`: 21/21 PASS
- `php tools/p28/isolate-account-migration.php --check`: BLOCKED (environment lacks `pdo_mysql`) — expected external runtime limitation

**Not verified:** Windows PowerShell 5.1 execution, local MariaDB ALTER/CREATE permissions, actual migration, or sandbox commerce readiness. User must back up restricted test database and run commands locally; any partial migration remains manual recovery only.
