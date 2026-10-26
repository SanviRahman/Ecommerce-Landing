# P19 Source Audit & Implemented Patch — Bangla

## Source scope & evidence

- Latest local target: `website(20261026-033819).zip`, full Laravel 13 source with existing P01–P18/P02 gated functionality.
- Behavioral/design reference: `shoppilot.zip` (proyojonerbazar.com), twelve screenshots of HT Bazar + ShopPilot, and the long user-provided **AI-transcribed/AI-reported** 14:07 meeting text. **Original meeting audio/video was not supplied in this turn; no unverified spoken claims are considered independently proven.**
- Repo contract checked: `AGENTS.md`, `rules.md`, `bugfix.md`, `constraints.md`, `docs/14-implementation-sequence.md`, `docs/17-progress.md`, `docs/19`–`25` meeting specification/test files and P14 safety doc. Reference site is not asserted to be the same deployed source.
- Changes are only in an isolated extraction under the authoring container; no changes applied to the user's Windows MySQL or local code.

## Current source observations (verified)

- 11 P02 migrations already previously reported RUN (Batch 2), typed Homepage Builder with draft/publish and versioned audit is present. Existing columns already support category-driven section sources and ordering; only account migration is new.
- Legacy `/` is bound to existing campaign landing; storefront is separately namespaced at `/storefront` and remains intentionally restricted in the original local database, as dictated by P14/P07/P08/P03 guards.
- Existing category `parent_id` supports one-level active child taxonomy; inventory/variants/COD have authoritative server services and separate test gates. They were reused rather than replaced by sample ShopPilot logic.
- Reference account + checkout layout cannot be copied as an isolated Blade component because the reference project has independent auth/order/payment/database assumptions.

## What the patch contains

| Requirement | New / modified implementation | Current validation |
|---|---|---|
| 3+ Hero images / auto slider | Existing CMS Hero media Items, keyboard and controls; accessibility/reduced motion | P19 source PASS; browser pending |
| Category-wise product rows, Wholesale/Kitchen etc | Typed `category_products` per active category + descendants, dynamic products/sort/limit, admin drag | P19 source PASS; DB rendering pending |
| Multi-level category menu | Existing P02 parent/child, native detail/flyout + mobile offcanvas | P19 source PASS; browser pending |
| Product cards 4 desktop / 2 mobile | Responsive CSS grids, 25/75 Cart/Order action; variant products select options first | P19 source PASS; mobile pending |
| Real floating Mini Cart count/total | Read-only `summary` endpoint backed by owner hashed guest cart, CSRF/version on writes | P19/P08 source PASS; session/DB pending |
| Product detail | Large two-column gallery, information/actions before description, sticky Order Now with product thumbnail | P19/P10 source PASS; browser pending |
| Secure 4-step checkout | Shipping -> COD Payment -> Review -> existing order-success page, server authority for totals | P19/P08/P09 source PASS; COD MySQL pending |
| Login/registration/profile | Separate `storefront_accounts` migration + `storefront` guard, secure login, scoped order dashboard | P19 source PASS; auth/migration runtime pending |
| Footer/FAQ/Contact | Quick Links / Customer Care / Contact Us, real SiteSetting contact links and global FAQs | P19 source PASS; DB content pending |
| Responsive animations | No-dependency native Hero/review slide, scroll reveal, hover, FAQ details, reduced motion | JS syntax/source PASS; browser pending |

## Exact tool results in this authoring environment

| Verification | Result |
|---|---|
| PHP syntax lint: app/bootstrap/config/migrations/routes/tests/tools | **320/320 PASS** (PHP 8.4) |
| JavaScript syntax lint: frontend + QA scripts | **38/38 PASS** (Node 22) |
| P19 storefront/security contracts | **29/29 PASS** |
| P11 full offline jobs | **17/17 PASS** |
| P13 Admin Homepage contracts | **29/29 PASS** |
| P07 catalog source checks | **34/34 PASS** |
| P17 Review/Builder checks | **16/16 PASS** |
| P15 report/CSV checks | **20/20 PASS** |
| Blade view-reference resolution | **158/158 PASS** |
| SHA-256 source-manifest review | **543 files verified**, excludes secrets |
| Actual Laravel bootstrap/PHPUnit | **BLOCKED**: target excludes `vendor/` |
| Real MariaDB checkout/login / guest sessions | **BLOCKED**: authoring PHP has no `pdo_mysql`, no user's MySQL DB |
| Actual Chrome desktop/mobile simulation | **NOT RUN**: no authenticated local app/database access |
| Stock race / last-item order / delivery integration | **NOT RUN** for this patch; required separately |
| Public cutover/production approval | **NOT GRANTED** |

## Safety and known limitations

- **Do NOT run `migrate:fresh --seed` on the user's local `ecommerce_landing_db`.** It destroys P14 published Revision #1 and other local data. Need reviewed local database backup and separate forward migration approval for the one customer-account schema migration.
- `P07_CATALOG_TEST_ENABLED`, `P08_CART_TEST_ENABLED`, `P08_CHECKOUT_TEST_ENABLED` and P03 core flags stay off by default and enforce isolated MySQL. This is a necessary security barrier; the new storefront pages may return 404 on the original database until release/test decisions are taken. Published Homepage Builder preview is separately available to the approved Admin.
- Customer account registration is deliberately separate from admin auth; existing historical orders cannot be magically linked by phone/email without verified ownership. Only future directly-associated checkout orders appear in My Account.
- Online payments, wishlist and coupons shown in third-party screenshot are **not activated** without approved data/contracts. The Payment step currently represents COD only.
- Existing `image & text`, Hero, Category, Review sections are preserved; new category shelf sections do **not** automatically populate all categories. Admin must add each section explicitly and choose its approved category.
- FAQ/contact animation adapts patterns from the user-shared FloorExperts text. Unrelated Services/About content and backend form submission were not invented.
- Existing public media files, private `.env`, Composer `vendor`, Laravel `storage`, history, customer records and auth keys were neither touched nor included in the deliverable.

## Recommended Windows local acceptance (after owner-reviewed apply/backup)

1. Source: `node tools/qa/check-p19-storefront-contract.mjs`, `node tools/qa/p11-offline-gate.mjs`, `node tools/qa/check-p15-blade-views.mjs`, `php artisan migrate:status`.
2. Read-only staged Admin Preview: add three approved Hero Items; add Wholesale and Kitchen category shelf sections; ensure 4 desktop/2 mobile, correct product count, missing image placeholders, reviewed links and active-only filtering.
3. With **isolated `p01_campaign_test` DB only** and approved flags: test guest add/change/remove, real cart total across pages, mandatory variant selection, stock-outs, single/multi-item checkout and one order placed. Test 409 stale tab and CSRF errors, no duplicate purchase under double submit; verify no campaign order/stock regression.
4. After reviewed *forward-only* account migration, test customer create/login/logout/deactivation, guest checkout, orders from authenticated customer and order scoping across two distinct accounts; admin session must remain independent.
5. Responsive desktop/tablet 320/375/768/1280+, reduced motion, keyboard Escape/Tab, Hero dots, mobile nav/cart Order Now overlay, footer and FAQ disclosure, picture loading, font/contrast, and real browser console logs.
6. Staged publish does **not** replace public `/` until separate release signoff and rollback plan.
