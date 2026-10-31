# P13 — Admin Homepage Builder 404: Safe Fix & Browser QA (বাংলা)

## Evidence এবং Root Cause

- Screenshot #1: `http://127.0.0.1:8000/admin/storefront/home` → 404; Screenshot #2: sidebar shows `STOREFRONT (P05 TEST)` with a clickable Homepage Builder link.
- Homepage Builder backend **শুধু** local MySQL `p01_campaign_test` + restricted `p01_tester` connection-এ authorised। সাধারণ `ecommerce_landing_db`-এ 404 উদ্দেশ্যমূলক security safeguard। এটি route missing-এর লক্ষণ নয়।
- `website(10).zip` archive **incomplete**: `routes/admin.php`, `api.php`, `command.php`, `console.php`, `storefront.php`, `user.php`, `web.php` অনুপস্থিত। Running localhost site-এ routing আছে; আলাদা ZIP export omission।

## কী পরিবর্তন হয়েছে

- `app/Providers/AppServiceProvider.php`: AdminLTE-এর জন্য `isolated-homepage-builder` Gate: active admin + `APP_ENV=local|testing`, exact restricted MySQL test-config। **এটি শুধু menu-display guard**; controller/service-এর আসল MySQL identity/authorization checks অপরিবর্তিত।
- `config/adminlte.php`: `STOREFRONT (ISOLATED QA)` নামে menu, main DB-তে link/header **লুকানো** থাকবে।
- `tools/qa/check-p13-homepage-gate.mjs` এবং `tests/Unit/P13HomepageMenuGateTest.php`: regression tests। P11 offline gate এখন P13 check-সহ 14টি job।
- `tools/qa/p13-isolated-admin-browser.ps1`: P12 **test copy only**-তে QA server `127.0.0.1:8089` এবং test DB / P12-generated synthetic APP_KEY। `--no-reload` ব্যবহার করা হয়েছে যাতে Laravel dev-server inherited scoped DB overrides না হারায়। Session cookie আলাদা।
- `tools/qa/p13-browser-readiness.php`: read-only restricted DB identity/schema + synthetic active admin check। `@example.test` admin না থাকলে **BLOCKED**; কারও live admin copy করা, admin seeding বা production DB পরিবর্তন করা নিষিদ্ধ।
- `P13-ROUTES-RECOVERY/`: আগের `website(9).zip`-এর historical route snapshot; `P13-RESTORE-MISSING-ROUTES.ps1` **existing route overwrite করে না** এবং শুধু `website-p12-test`-এ missing routes restore করে। Local/production `website`-এ restore script চালাবে না।
- Source manifest `reports/p11-source-manifest.json` updated after reviewed code + historical route reconstruction. Latest Windows route files আগের snapshot থেকে ভিন্ন হলে fingerprint FAIL হতে পারে; যাচাই না করে `--write` চালাবে না।

## Install — আগে শুধু ISOLATED P12 TEST COPY-তে

1. `D:\Ecommerce-Landing\website-p12-test` folder-এর source backup নাও।
2. `P13_Homepage_Builder_404_OnlyUpdated.zip` **ওই test-copy root-এ** extract করো, folder tree preserve করো। `vendor`, synthetic `.env` বাদ দিয়ে কোনো file overwrite করার প্রয়োজন নেই।
3. যদি test-copy-তে `routes/` file অনুপস্থিত থাকে, **শুধু তখন**:

```powershell
cd D:\Ecommerce-Landing\website-p12-test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\P13-RESTORE-MISSING-ROUTES.ps1
```

4. Source QA ও unit test (test-copy only):

```powershell
cd D:\Ecommerce-Landing\website-p12-test
node tools/qa/p11-offline-gate.mjs
composer validate --strict
composer audit --locked
php artisan test tests/Unit/P13HomepageMenuGateTest.php
```

Expected offline: `14/14` QA jobs and `24/24` P13 static contracts if local routes match. PHP unit test result must be actually executed on your Windows PHP runtime. **No DB migration**।

5. Isolated Browser UI (new terminal):

```powershell
cd D:\Ecommerce-Landing\website-p12-test
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\tools\qa\p13-isolated-admin-browser.ps1
```

If safe preflight PASS and an **existing synthetic test-admin** exists, open:

`http://127.0.0.1:8089/admin/storefront/home`

- Sign in with **existing, test-DB-only synthetic admin** credentials (do not paste to chat).
- UI manual QA: load, create draft, add safe category section, adjust theme, move/reorder, unpublished draft isolation, preview, publish on **isolated test DB only**; verify CSRF and mobile layout.
- If preflight says `BLOCKED_NO_ISOLATED_ADMIN`, **stop**, share only the blocker message. A synthetic test-only admin needs a separately approved guarded setup; do not use original DB users or passwords.
- P13 runner-এ P07 browse **শুধু isolated test DB-তে ON** যাতে preview link কাজ করে; P08 cart ও checkout OFF। Browser QA-র মূল scope builder; COD payment unchanged. COD client requirement unchanged.

## Applying the menu fix to normal `website`

After P13 Gate tests PASS on test-copy, back up the original two files and copy **only** `app/Providers/AppServiceProvider.php` and `config/adminlte.php` from your verified test-copy into the normal website. The misleading Homepage Builder link will disappear on original DB; **direct URL still returns intentional 404**. When real rollout approval and data migration/backfill are separately reviewed, a production activation implementation can follow. This patch does NOT activate it.

**Never** migrate, wipe, seed, change `APP_KEY`, modify main `.env`, enable production flags or run unsafe overwrite/recovery on `D:\Ecommerce-Landing\website`.

## Verification boundaries

- Source checks are NOT equivalent to a successfully running Windows UI, real MySQL login, or full staging approval. Any new runtime error requires Windows output before we can confirm a fix.
- Latest uploaded ZIP contained `.env`/runtime files; they are intentionally **not included in patch**. Do not publish confidential files.

## Authoring QA evidence (not real Windows browser evidence)

- Fresh `website(10).zip` + patch + **missing-file-only** historical `routes/` recovery: Node P13 **24/24 PASS**, P11 source **42/42 PASS**, P11 offline **14/14 PASS**, P11 source manifest **517 files verified**.
- PHP lint **507 files / 0 syntax failures** (all project PHP outside bundled vendor folders), JavaScript syntax **62 files / 0 failures**.
- Actual PHP Laravel PHPUnit, restricted MySQL page rendering/login and PowerShell script not executable here without Windows environment and Composer vendor; **PENDING**.
