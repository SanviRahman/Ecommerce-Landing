# P14 — Local Homepage Builder activation + reviewed P02 migration (বাংলা)

## সমস্যা ও সমাধান

- `Homepage Builder` sidebar-এ দেখা যেত না, কারণ P13 menu gate, HomepageBuilder service ও controller-এর `p01_campaign_test` guard ছিল।
- আসল local `ecommerce_landing_db`-এ ১১টি P02 migration `Pending`; প্রতিটি `up()`-এ একই P02 isolated guard ছিল।
- **P14 patch** আলাদা দুটি default-OFF opt-in দিয়েছে: **(A) Homepage Builder CRUD**, **(B) LOCAL P02 migrations**। পেছনের অন্য stock/checkout/test-DB guard বদলায়নি।

## কোন folder-এ ZIP বসাবে

`D:\Ecommerce-Landing\website\` — তোমার **original local** Laravel source। Patch-এর ভিতরের `app`, `config`, `database/migrations`, `resources`, `tools`, `tests`, `reports` নিজ নিজ subfolder-এ merge করবে। **পুরো project replace করবে না।** আগের code backup নাও।

**`website-p12-test` আলাদা synthetic test copy; সেখানে migration বা এই P14 local opt-in চালাবে না।** `routes`/`.env`/vendor/data এই patch-এ নেই।

## Step 1 — source QA (কোনো DB write নয়)

```powershell
cd D:\Ecommerce-Landing\website
node tools/qa/p11-offline-gate.mjs
php tools/qa/check-p14-local-access.php
php artisan test tests/Unit/P14LocalHomepageAccessTest.php
```

Expected: P14 default-deny permission matrix PASS; P11 gate PASS। PHPUnit pure test DB write করে না। **সব checks PASS না হলে migration নয়।**

## Step 2 — `.env` edit (LOCAL ONLY, কোথাও পাঠাবে না)

তোমার local `.env`-এ আগে থেকে `APP_KEY`/password যেমন আছে রেখে প্রয়োজনীয় value check করো:

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ecommerce_landing_db
P14_LOCAL_HOMEPAGE_BUILDER_ENABLED=true
P14_LOCAL_P02_MIGRATION_APPROVED=YES_LOCAL_P02_SCHEMA_ONLY
```

- **Existing DB_USERNAME, DB_PASSWORD, APP_KEY স্পর্শ/শেয়ার করবে না।**
- `DB_URL`/`DB_SOCKET`/read-write split থাকতে পারবে না; source guard fail-closed।
- `P14_LOCAL_P02_MIGRATION_APPROVED` কেবল migration চলাকালীন রাখতে হবে। production/staging-এ দুটোই OFF।
- `.env` Git-এ commit / ZIP-এ include করবে না।

## Step 3 — local migration review

```powershell
cd D:\Ecommerce-Landing\website
php artisan config:clear
php artisan migrate:status
php tools/qa/p14-local-migration-preflight.php
```

**`migrate:status`-এ শুধু P02-এর ১১টি Pending এবং preflight `READY_FOR_REVIEWED_LOCAL_MIGRATE` দেখালে** তোমার seed-based local database-এর একটি SQL snapshot রেখে এগোবে। Preflight শুধু local DB identity, legacy migration এবং P02 table-এর অবস্থা SELECT/Schema introspection দিয়ে যাচাই করে—কোনো schema/data write করে না। Migration #6 `orders`/`order_items` money columns integer থেকে decimal-এ widen করে, তাই local হলেও schema review দরকার।

## Step 4 — apply only after Step 3 validated

```powershell
cd D:\Ecommerce-Landing\website
php artisan migrate
php artisan migrate:status
```

কোনো error এলে **থামবে**, আবার migrate:rollback/fresh/reset/db:wipe চালাবে না; complete terminal output পাঠাবে। P02 migration `down()` এখনও isolated-only এবং decimal migration forward-only।

## Step 5 — remove migration approval; inspect Admin + Preview

সফল migration-এর পরে `.env` থেকে শুধু এই line **মুছে দাও**:

```
P14_LOCAL_P02_MIGRATION_APPROVED=YES_LOCAL_P02_SCHEMA_ONLY
```

বাকি `P14_LOCAL_HOMEPAGE_BUILDER_ENABLED=true` স্থানীয় Admin UI test-এর জন্য থাকবে। তারপর:

```powershell
cd D:\Ecommerce-Landing\website
php artisan config:clear
node tools/qa/p11-offline-gate.mjs
```

যদি `php artisan serve` আগে থেকেই চলছে, Ctrl+C করে **server restart** করো (একটি terminal থেকে `php artisan serve --host=127.0.0.1 --port=8000`)। তারপর Admin-এ login করে:

1. Sidebar: **HOMEPAGE MANAGEMENT → Homepage Builder**;
2. URL: `http://127.0.0.1:8000/admin/storefront/home`;
3. **Create Draft**, typed section add/edit, colors, Up/Down/drag, item add/remove, validation/409 conflict;
4. **Preview homepage** (Admin-protected preview, does NOT replace `/`);
5. Publish একটি revision করলে private builder `published_revision_id` বদলায়; **existing public root `/` campaign homepage অক্ষত থাকে**।

### জরুরি সীমা

- এই patch শুধু Homepage Builder-এর **local** CRUD এবং schema-upgrade guard প্রস্তুত করেছে। **Production deploy** বা public `/`-এ storefront cutover অনুমোদিত/করা হয়নি।
- P03 inventory, P07 catalog, P08 cart/checkout default OFF; COD only।
- এখানে local MariaDB বা browser চালিয়ে পরীক্ষার সুযোগ নেই; Step 3/4/5 ফলাফল অবশ্যই Windows terminal/browser-এ যাচাই করতে হবে।

**P13 isolated browser script এই local Admin testing-এর জন্য নয়**—সেটি `p01_campaign_test`-এর synthetic admin চায়। এখানে আগের login করা local Admin-ই যথেষ্ট, তবে active Admin role থাকতে হবে।
