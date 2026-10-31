# P28 - Isolated Customer Account Migration (Bangla)

## কেন BLOCKED?

P26 isolated sandbox preflight `p01_campaign_test` database-এ `storefront_accounts` table এবং `orders.storefront_account_id` migration আশা করে। মূল `ecommerce_landing_db`-তে আগে migration চললেও সেটি **test DB-তে স্বয়ংক্রিয়ভাবে চলে না**। এটি intentional safety gate, Laravel/P27 bug নয়।

## Install

ZIP-এর `tools/p28` directory পুরোটা `D:\Ecommerce-Landing\website\tools\p28`-এ extract করো। এটি `tools/p28`-এ থাকে, ফলে P11 fingerprint-এর allowlist source scope পরিবর্তন করে না। `.env`, P26/P27 code, DB passwords, migrations বা existing data এতে বদলায় না।

## Step 1: READ-ONLY check

```powershell
cd D:\Ecommerce-Landing\website
powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\p28\run-isolated-account-migration.ps1 -Mode Check
```

Expected: `P28 ISOLATED TEST DB STATUS: READY` অথবা `ALREADY_APPLIED`, এবং `READ-ONLY`. `BLOCKED` পেলে থামবে।

## Step 2: Explicitly approve the one targeted migration

**শুধু test database-এর backup/recovery snapshot প্রস্তুত থাকলে**, এই command:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\p28\run-isolated-account-migration.ps1 -Mode Apply
```

প্রম্পট এলে নিজের সিদ্ধান্তে ঠিক এই phrase টাইপ করো: `MIGRATE_P01_TEST_ONLY`। Runner restricted `p01_tester` account-এর actual database/grants যাচাই করে **শুধু** `2026_10_26_100001_create_storefront_accounts.php` migration চালায়। `migrate:fresh`, broad `artisan migrate`, `db:seed`, rollback, main `.env` edit কিছুই করে না।

Expected: `PASS P28 account migration recorded and verified in p01_campaign_test only.` এরপর P26 schema preflight `PASS`।

**সতর্কতা:** MySQL/MariaDB DDL partial failure হলে কোনো automatic rollback নেই। Migration `BLOCKED`/error হলে পুনরায় চালাবে না; stderr/log ও current test-schema metadata review করবে। Test account-এ DDL privileges না থাকলেও থামবে।

## Step 3: Start sandbox

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\tools\qa\p26-isolated-sandbox.ps1
```

এবার schema preflight PASS হলে P11 inventory/products/shipping/published homepage readiness চলবে। সেটিতে blocker থাকলে **synthetic test data**-এর প্রস্তুতি বাকি; migration fix alone full checkout enable করে না। সব readiness PASS হলে URL: http://127.0.0.1:8088/storefront/home

## Important safety

- Main `ecommerce_landing_db` database, customers, orders, APP_KEY, P14 Builder এবং campaign `/` অপরিবর্তিত।
- Script কেবল isolated test credentials (`P02_TEST_DB_PASSWORD` বা restricted password file) পড়ে, credential print/copy করে না।
- Windows PowerShell 5.1 compatible; `RandomNumberGenerator.Fill()` ব্যবহার করে না।
- P11 SHA-256 unchanged; script allowlisted directories-এর বাইরে থাকে।
- Actual MySQL migration execution এই authoring environment-এ যাচাই করা যায়নি।
