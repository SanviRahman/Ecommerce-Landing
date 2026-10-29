# Decisions, Assumptions and Open Items

## Engineering decisions in this pack

These are proposed implementation defaults, not meeting minutes. A later approved meeting document may change them through an explicit addendum.

| ID | Decision | Rationale/status |
|---|---|---|
| D01 | Separate storefront; existing campaign template retained | Avoid regression from shared home Blade; confirmed preservation objective |
| D02 | Structured typed builder, not unrestricted page editor | Predictable responsive UI and safe input |
| D03 | Blade/AdminLTE + small JS/SortableJS | Fits actual stack; no SPA rewrite required |
| D04 | Uniform default/explicit variant model | One stock authority and simple-product compatibility |
| D05 | Storefront free delivery initially follows existing any-free-product rule | **Business sign-off required before release**; do not assume meeting approval |
| D06 | Fixed campaign product price applies to all that product's variants | **Business sign-off required** if different variant surcharges expected |
| D07 | Widen order money to decimal BDT; internal exact minor-unit arithmetic | Fix existing discount precision mismatch without changing product price units |
| D08 | Guest storefront cart only, separate campaign quick ordering | Avoid undefined mixed campaign pricing/shipping |
| D09 | Draft/publish snapshots with optimistic versioning | Prevent accidental live edits and stale-tab overwrites |
| D10 | Contact order/layout revisioned; existing global contact values remain live | Reuse fields; UI labels site-wide changes clearly |
| D11 | Admin-only edit/publish initially | No invented employee publishing permissions |
| D12 | Two-level category hierarchy initially | Meets stated category/sub-category need; deeper trees require explicit scope |
| D13 | Reference floating cart/mobile nav included as proposed UI; unsupported Account/Wishlist hidden | No fake links or implied authentication scope |
| D14 | Existing order history unmanaged until reconciled | Source stock handling is inconsistent; no safe automatic historical deductions |
| D15 | External imports do not consume local inventory without approved SKU ownership mapping | Avoid double counting or guessed product matching |
| D16 | Feature flag remains campaign until full gates pass | Safe release/rollback boundary |
| D17 | Existing `.env` excluded from package/commits | Security; actual exposed secrets reviewed separately |

## Missing/not verified

| Item | What is known | Required action |
|---|---|---|
| Client meeting PDF | 32 pages supplied and integrated (docs/19-26), original audio/video not supplied, feedback table truncated at CF-06 | Retain documentary attribution; confirm approvals for disputed pricing/identity rules |
| Production runtime | Manifest/locks only | Record actual PHP, Node/build environment, DB engine/version |
| Actual commerce schema/data | Source migrations; bundled SQLite only base tables | Read approved live/staging metadata; no guessed rows |
| Accurate warehouse stock | Current source has inconsistent deduction paths | Owner-approved inventory baseline before enabling sales enforcement |
| Cancellation/returns policy | No approved stock restoration policy supplied | Review proposed lifecycle matrix, especially shipped returns |
| Global shipping setup | Model supports campaign_id=null, actual rows unknown | Configure approved areas/prices, confirm D05 |
| Variant-specific campaign pricing | Not stated | Confirm D06 or design campaign-variant pivot in a scoped change |
| Client visual palette/assets | Screenshots show controls, not final brand colors | Supply brand assets/colors or accept documented defaults |
| Customer login/wishlist | Visible on reference, not requested as implemented features | Leave disabled unless separately approved |
| Employee edit/publish rights | Not specified | Default deny, grant only after explicit policy decision |

Work may proceed against the **merged PDF-reported + direct-user** scope, but do not claim firsthand meeting recording compliance. Do not release inventory-affecting changes without pricing/stock/identity approvals. Do not repeatedly request code already supplied.

## Future meeting addendum template

Record document filename/hash/page, exact requirement summary in your own words, relevant R/D IDs, differences from current scope, schema/API impact, tests, and approval. Never infer a meeting decision from the reference website alone.

## ADR change template

- Decision ID / date:
- Current rule and why it no longer fits:
- User/client evidence:
- Chosen rule and rejected alternatives:
- Data/backfill impact:
- Existing campaign/order compatibility:
- Files/contracts/tests to update:
- Approval and rollout gate:

## Client meeting decision addendum

The additional reported decisions MD01-MD12 and pending gates G01-G18 live in [docs/23-decisions-conflicts.md](23-decisions-conflicts.md). These constrain earlier engineering defaults. Especially: preserve existing header/category design, four/two product grids, 25/75 CTA, two-part borderless product details, description below buttons, explicit weight/pack prices, sticky Order Now, responsive checkout, and global/per-section green-based theme. Client-specific exact brand HEX, pack stock units, live-site identity, and optional Call channel remain unapproved.

## P02 source preparation decision note (24 October 2026)

- **P02-S1 (safety gate):** New migrations reject all connections except the named disposable local MySQL test database `p01_campaign_test` using restricted user `p01_tester`. Production migration is intentionally not yet enabled; requires later approved safety review and release-gate code change. This is a temporary testing gate, not a production provisioning strategy.
- **P02-S2 (legacy SKU):** Prefer a valid <=100-char ASCII existing `product_code` when not occupied by any variant SKU; otherwise use unique stable `LEGACY-<id>`. Collision of both identities becomes an explicit conflict rather than silently overwriting an existing SKU. This choice still requires integration/acceptance testing.
- **P02-S3 (ledger opening):** Backfill's opening movement is a record of the existing `products.stock` snapshot only. It does not attest to physical inventory or permit P03 allocation cutover by itself. Existing order writes are still inventory-unaware.
- **P02-S4 (money):** Monetary widening is forward-only; integer BDT values remain BDT integers represented with 2 decimal places. Reversal via implicit DECIMAL->INT narrowing is prohibited.
- **Status:** These are source-stage engineering safeguards. Actual MariaDB acceptance, integration tests and business sign-off are required before activation/release.


## 2026-10-24 — P03 staged core source decision (P03-S1)

- Create reusable exact BDT, allocation/release and durable idempotency core before touching legacy order writers.
- New P03 code is OFF by default and doubly gated behind `P02MigrationSafety::assertIsolatedTestDatabase()` and an existing outer database transaction.
- Do not enable on the original database or wire a single legacy writer in isolation. Full cutover requires verified P02 MariaDB migrations/backfill, accurate stock reconciliation, pack-unit decisions, all ingress coverage and real concurrency tests.
- Only simple default variants are permitted in this initial core; options/pack conversions remain P04 work. Existing manually discounted/negotiated order totals require a separately defined trusted pricing adapter.
- Cancelled unshipped orders can be credited via an explicit operation; shipped or courier-dispatched orders cannot be auto-restocked. Legacy orders are never credited.
- Source tests are not runtime tests. No migration, order creation, stock update or production cutover occurred.

## 2026-10-25 — P04-S1 staged variant administration (not release approved)

- **Scope:** On the existing AdminLTE stack add 4 fixed dimensions (`color`, `size`, `weight`, `piece_count`), option values, manually selected SKU combinations, explicit nullable BDT price overrides and per-SKU stock ledger movements. No new public route or storefront cart behavior is enabled.
- **Data authority:** One `product_variants.stock` balance per sellable SKU, `inventory_movements` for opening and adjustments, `products.stock` as a compatibility sum of nondeleted variants. New combinations have *zero* opening stock. Duplicate signatures/SKUs (including archived SKUs) are rejected.
- **Conversion policy:** Existing default SKU must have zero stock and no linked managed order items before converting to optioned product. The default row/history remains; variable product becomes inactive until public variant checkout is released. Pack weights and piece-count quantities remain independent sellable SKU balances; do not assume conversion.
- **Permissions/rollout:** Admin-only route/form CSRF; P04 mutations require `P02MigrationSafety::assertIsolatedTestDatabase()` with restricted test MySQL user. Existing campaign/storefront views, `ecommerce_landing_db`, `.env`, P03 flags and courier flows are unchanged. Controlled rollout requires separate release approval.
- **Evidence:** Source checks and pure PHP signature tests passed locally in an offline workspace; new P04 17-case Laravel/MySQL suite is provided but not yet executed on user's XAMPP. No production deployment or migration.

## P05-ADR-01 (2026-10-25) — Stage versioned homepage privately

Decision: introduce `HomepageBuilder` and the reusable `storefront/pages/home.blade.php` using existing P02 tables. Keep all mutations behind verified `p01_campaign_test` identity and authenticated active-admin routes. Retain original campaign `/` route and frontend Blade. Drafts are isolated; published revision content is never mutated; restoring clones to a new draft. Every edit requires expected `lock_version`; HTTP 409 on stale input. Five component types are hardcoded, colors use `#RRGGBB`, CTA URLs HTTPS or safe root-relative, media limited to public approved collections and checked for file existence. Publish enforces active referenced resources and contrast. Future P06 drag handles will invoke the existing exact-permutation reorder endpoint. P07 public storefront integration requires independent approval and regression tests.

Open: browser-level preview/CSRF QA, image file reference tests, real MariaDB 20-case suite, P06 JS accessible drag, P07 final public mode switch/checkout path; no production rollout authorization.

### ADR P06-01 — Native pointer/keyboard drag UI, existing versioned reorder endpoint (2026-10-25)

- Decision: use dependency-free Pointer Events on a compact, admin-only section list and the existing `PATCH /admin/storefront/home/sections/reorder` backend, adding JSON acknowledgement only when `expectsJson()` is true. Plain HTML `Up`/`Down` forms continue working.
- Rationale: preserve the P05 DB draft-only permutation/version/audit transaction, avoid adding unreviewed NPM dependencies, retain touch/keyboard movement and avoid breaking campaign pages.
- Rejection: no client-only saved order, no writing to public revisions, no global style CSS injection, no last-write-wins updates.
- Client: failed 409/419/network saves restore the locally displayed list, show an aria-live error, and do not update the editor version or re-render form inputs; success updates optimistic form versions and moves existing DOM card nodes.
- Design tokens: 18 existing allowlisted `HomeSectionSchema::STYLE_KEYS` map to constant section-scoped preview CSS variables. Blank section override inherits the saved global token. No migration or raw CSS injection.
- Stage gate: P05 MySQL suite user output not yet supplied; P06 12-case real MySQL HTTP suite and full Laravel browser validation still PENDING. No production DB mode approval. Per-item/manual-product reorder remains open for subsequent P06 iteration.

### 2026-10-25 — P07 route namespace and staged browse security

- **Decision:** use four `/storefront/...` paths (at least two segments) rather than immediately reserving `/shop` or `/product` root slugs; preserves current root-level custom campaign URLs without an unverified collision migration.
- **Decision:** enable P07 catalog only in named restricted local MySQL test DB and only with `P07_CATALOG_TEST_ENABLED=true` (default false); P02 migration guard is independent. No production homepage mode switch.
- **Decision:** published-mode home uses pinned published revision; private preview retains inert action spans. All P07 product links are browse-only until P08 cart/checkout is real.
- **Open:** before canonical shop URLs or production cutover, audit existing campaign slugs, backfill and reconcile inventory on an approved staging DB, verify image storage and browser tests, run committed last-stock parallel checkout test, and approve final product weight/pack mapping. Do not use `migrate:fresh` on original data.

### 2026-10-25 - P08 scoped checkout decisions (staged)

- Retain `/storefront/*` paths and preserve legacy root/campaign endpoints; no public fixed `/cart` single-segment slug until campaign collision audit.
- Reuse existing P02 `carts`, `cart_items`, `checkout_requests` and P03 `inventory_movements` tables. No new schema or manual DB changes. Persistent guest ownership uses an HttpOnly, same-site cookie, raw token kept out of SQL; hash is stored.
- Extend the single P03 `InventoryAllocator` for optioned SKUs rather than create a second stock authority. Idempotency claim and completion, stock movement and cart clearing share a transaction. Repricing requires explicit cart update; invalid shipping cannot silently become free.
- Default all P08 flags OFF and keep the independent local P02 database test guard. P08 PHPUnit activates P03 core inside the restricted test process. Full browser checkout and production mode remain UNAPPROVED until isolated MySQL and parallel-purchase tests pass.
- Open decision: final storefront shipping/free-delivery policy, actual weight vs piece pack units, active admin variable-order quantity-edit behavior, and true browser cookie/CSRF smoke evidence before release.

### ADR P09-01 — Serialize storefront idempotency behind owned cart lock (2026-10-25)

- All storefront checkout calls lock the persisted owned `carts` row BEFORE reading/claiming `(owner_hash, idempotency_key)` to avoid first-use unique-key insertion races. Compute canonical request hash from that locked cart identity. Replays remain allowed after cart version increment; new purchases still demand exact cart version and full server price/stock validation.
- Added optional two-worker committed fake-final-item test in `p01_campaign_test` only. It uses IDs scoped to generated fixtures, suppresses external-order-sync observers and employee assignment, has an explicit distinct approval environment variable, and targets safe cleanup. Interrupted fixtures remain an inspection-only recovery case, not authorization for resetting any database.
- No shipping policy, stock unit mapping, migration, checkout UI route, or production flag changes. Browser HTTPS/mobile/courier acceptance and production cutover remain separately blocked.

### ADR-P09-CSRF — Platform-independent localhost CSRF verification (2026-10-25)

- Decision: prefer a built-in Node.js `fetch` + PHP local web-server CSRF runner over requesting an Execution Policy bypass for unsigned PowerShell.
- Tradeoff: extra Node test files; no new runtime packages or production route changes. Laravel cookie session, fake APP_KEY and restricted `p01_tester` process environment remain separate from the app `.env`.
- Controls: explicit local-test permission flag, port 8089 exclusively on loopback, standalone MySQL account+schema/empty-commerce preflight and postflight, invalid product ID=0, PHP child termination, fail-closed cached-config/port checks.
- Open: user's real CSRF result (3/3) and committed independent last-item race remain unverified here; P10/P11 are not complete.

### 2026-10-25 — P10 staged integration decision

- No automatic staging/production deployment because owner approval, real catalog reconciliation, production-ready schema upgrade and rollback evidence are still missing. The repository defines P10 as **staging deployment/rehearsal**, not simply UI work.
- Public contact controls read only from active existing `site_settings`, use reviewed URL formats, and render nothing if absent. Mini-cart and contact remain separate controls.
- Published homepage receives the same safe contact partial as catalog; private draft preview remains isolated/inert.
- Mobile Order Now is a form-associated P08 submit control, not duplicate checkout logic. Existing authorization, CSRF, cart version, money and stock guards remain authoritative.
- Browser/real MySQL P10 test results remain open, despite P09 CSRF and concurrent last-item tests reported PASS by the user.


### ADR P11-01 — Correct HTTP validation reproduction and read-only release review (2026-10-25)

- **Decision:** P10 UI validation must be proven via the existing POST `/storefront/cart/items` + 302 redirect + `ShareErrorsFromSession`, not by putting an artificial `ViewErrorBag` into a GET while cart is disabled. Keep the cart feature restricted to the isolated test account.
- **Decision:** Label the additional user-requested P11 stage as a *release-readiness tooling handoff*. The repository `docs/14-implementation-sequence.md` still names P10 as the real approved staging deployment; code agents do not silently reclassify staging as completed.
- **Decision:** P11 read-only data audit must run ONLY using existing restricted P02 test credentials, with explicit approval, no Laravel .env boot, no SQL writes, and public counts only. A candidate is NOT permission for production.
- **Open:** updated P10 7-case MariaDB result, populated staging data reconciliation, media evidence, browser/mobile captures, production asset/cache rehearsal, schema-compatible rollback, and signed owner release approval. Do not enable storefront flags against the original DB.

### ADR P14-LOCAL-01 — Explicit local Homepage Builder and forward-only migration permission (2026-10-25)

- **Context:** The user's seeded `ecommerce_landing_db` is a local developer database and had 73 legacy migrations recorded with eleven P02 migrations pending. P13 deliberately hid the Homepage Builder except on `p01_campaign_test`; each P02 migration `up()` also enforced that restricted identity. Simply revealing a menu caused an unavoidable 404 and `php artisan migrate` would fail the guard.
- **Decision:** Support a separately approved **local debug** (`APP_ENV=local`, `APP_DEBUG=true`) MySQL database named exactly `ecommerce_landing_db` over `127.0.0.1:3306` for Homepage Builder CRUD. `P14_LOCAL_HOMEPAGE_BUILDER_ENABLED=true` opts in for Admin menu/CRUD; the service independently verifies the actual MySQL `DATABASE()` and `CURRENT_USER()` before any builder queries/writes. Require active Admin/role middleware and CSRF; existing P13 isolated test access remains. No production/staging/remote/default enablement.
- **Migration consent:** A distinct CLI-only `P14_LOCAL_P02_MIGRATION_APPROVED=YES_LOCAL_P02_SCHEMA_ONLY` opens **only** the eleven P02 migration `up()` guards on the named local DB. Migration `down()` remains restricted to the original isolated test DB. **No rollback/fresh/wipe authorization**. All other services using `P02MigrationSafety::assertIsolatedTestDatabase()` (inventory, cart, variant manager, checkout, P01 tests, etc.) remain restricted and unaffected. P02 migrations alter legacy orders/money columns; take a local snapshot and pass the read-only P14 local migration-history/schema preflight before applying even with seed data.
- **Public semantics:** Saving/publishing builder revisions does not replace the legacy campaign homepage `/` or enable the P07/P08 checkout mode. Preview stays authenticated; COD-only semantics unchanged. The local flag is *not* a staging or production release flag.
- **Verification:** Pure 20-case permission matrix, source checks validating 11 `up()` changes with strict `down()` guard, and full offline P11 source contract gate; real local MySQL migrations/admin CRUD/browser tests require the operator's results. Do not claim production readiness.

### ADR-P15-01 — Private report exports, no customer-data migration (2026-10-25)

- **Verified source:** report CSV/PDF writers used `Storage::disk('public')`; `public/.htaccess` lacked an explicit deny for legacy report files. CSV data fields passed directly to `fputcsv()`, which quotes delimiters but does not prevent spreadsheet formulas. ProductController direct create/edit URL views were missing although AJAX modal views existed.
- **Minimal patch:** new exports use the existing private `local` disk with write-result checks; all spreadsheet data goes through `SafeCsvCell`; Apache public-report direct reads are denied; missing product create/edit standalone views reuse the exact authenticated/CSRF-protected partial, with a small external JS progressive enhancement.
- **Compatibility:** old report file records are not deleted/migrated or automatically rewritten. Existing download route, media/public path, Homepage Builder restrictions and migrations remain unchanged.
- **Open approval/gates:** legacy public export migration and platform-specific web server controls (Nginx/PHP dev server/IIS), authenticated Laravel/DB functional tests, real CSV download in Excel, product direct-page AJAX/POST tests, browser screenshots and owner review. No production deployment authorization.

### ADR-P19-UI-01 — Staged storefront visual update, category shelves and isolated customer auth (2026-10-26)

- **Authority:** user's ShopPilot ZIP and captured screenshots are design/functional references; AI-derived meeting transcript and previous docs are secondary source descriptions, not directly verified video/audio. Do not clone unknown reference services/controllers/database.
- **Decision:** extend existing P02 typed Homepage Builder with `category_products` backed by the already-migrated section `category_id`/`include_descendants`/`product_sort`/`product_limit` columns. Existing immutable revisions, per-section allowlisted themes, save/publish isolation, Admin CSRF and exact P14 database gate remain intact. Category with inactive/missing parent is rejected; empty shelves are omitted in gated published storefront and noted privately during preview.
- **Hero:** three approved image items in the existing Hero/Banner widget form an accessible carousel; a broader item cap remains in force for flexibility. No direct media upload path or arbitrary HTML/CSS accepted.
- **Commerce:** mini-cart reads cookie-owned guest cart via read-only JSON endpoint, and writes reuse the existing optimistic-version/CSRF/stock guards. Server remains authority for price, delivery and idempotency. Buy Now for mandatory variant combinations points to the exact product detail selector rather than inventing defaults. Desktop/mobile CTA/UI changes do not change COD and courier rules.
- **Auth:** separate `storefront_accounts` guard/table and nullable `orders.storefront_account_id` avoids treating admin users as shoppers or matching historical orders by phone/email. Customer logout preserves independent admin login; login attempts throttled; password securely cast. Additional migration uses *forward-only explicit P14 local permission* or isolated test DB; rollback demands approved data-preservation plan. Do not run `migrate:fresh` on existing `ecommerce_landing_db`.
- **Visual/animation:** green-like design defaults, native nested menu/off-canvas, hero/gallery/testimonial sliders, scroll reveals and FAQ accordion; keyboard access, no-JS, and reduced-motion alternatives. Contact links drawn only from verified SiteSetting fields, FAQ from global active rows. Do not invent e-commerce Services or payment gateway features from a third-party animation list.
- **Security gate:** production/public `/` campaign home and `/storefront` feature-off behavior are unchanged. P07/P08/P03 isolated approval still required; a visually complete route without runtime clearance remains unreleased. Authoring QA is source-only without MySQL/Composer/browser.
- **Pending owner approvals:** customer registration policy/privacy notice, true public cutover plan, exact theme assets/logo, category shelf ordering defaults, supported shipping and online payment methods, MySQL account migration snapshot, checkout concurrency tests and mobile browser acceptance.

### ADR P24-01 — Admin shortcut only, never a public storefront cutover (2026-10-26)

- **Context:** A real screenshot showed `GET /admin/storefront` returning 404 even though `/admin/storefront/home` existed. Older replies incorrectly suggested `/storefront` as a working main storefront route; this latest source only defines `/storefront/home` and its isolated-test identity gate still denies original `ecommerce_landing_db`.
- **Decision:** Add only the admin-scoped active-admin shortcut `/admin/storefront`. Delegate to the existing private builder Preview or editor with a second authorization and schema identity check. Make private preview section navigation honest and responsive; show real, validated contact links but no unsafe checkout/account promises.
- **Boundaries:** Do not register a new one-segment `/storefront` public alias (it may shadow existing custom campaign slugs), enable `p07_catalog_test_enabled` against the main database, or alter checkout/inventory guards. The existing public `/` route is unchanged. Public browse, real product details, cart, customer login and order submission still require formal release review and actual end-to-end tests.

### ADR P25 — Explicit local admin read-only storefront browse, not public release (2026-10-26)

**Decision:** Keep all P03/P07/P08 independent MySQL/cart/checkout guards unchanged for general public visitors. A separate default-OFF developer-only consent may allow active authenticated web admins to VIEW the already published storefront UI on the exact P14-approved `ecommerce_landing_db` after verifying the effective MySQL account and schema for each request. Six safe GET catalog route names only, not customer/account/cart/checkout. A visible banner labels this read-only mode. Published revision remains the source of truth; no draft or production `/` cutover.

**Rationale:** User's local `ecommerce_landing_db` has already migrated and staged Revision #2, but the deliberately isolated P07 public browsing flag causes a 404. Enabling `P07_CATALOG_TEST_ENABLED` alone on that database cannot fix it and weakening the isolated gate would expose untested checkout/inventory code. A local authenticated browsing exception is a narrow UI review path; **full PUBLIC storefront** still requires P09/P10 MySQL, browser, inventory/concurrency and explicit release approval. The separate flag is opt-in; setting it on production has no effect.

**Open:** Verify local admin browser GET/home/shop/category/product, mobile menu/hero/footer and anonymous denial, then real isolated cart/checkout acceptance before any actual public access.

### ADR P26 — Main DB read-only Admin showroom; commerce testing remains on isolated MySQL (2026-10-26)

**Decision:** The user needs a real URL and functional local testing. Preserve the documented P02/P03 inventory boundary rather than treating original local `ecommerce_landing_db` as a disposable test DB. Provide an explicit, CSRF-protected per-session Admin preview enabled only after verified P14 local builder database identity. No guest checkout or customer auth on original DB. Allow an independent per-request P26 testing mode strictly for loopback `127.0.0.1:8088` and the verified `p01_campaign_test`/`p01_tester` account; no persistent P03 flag change, no external order sync in QA. Full live releases still require staged data and MySQL acceptance tests.

**Pending:** Admin-in-browser authentication/switch acceptance; restricted P02 sandbox creation/permissions and P26 isolated migration; synthetic fixtures, P11 readiness, P08 HTTP/DB, P09 concurrency/replay, mobile QA and final release approval.


## P31 user-authorized public browse decision (2026-10-27)

- **D31 (approved request, scoped implementation):** Make `/` render the published Storefront Homepage and make `/campaign/{slug}` the canonical campaign URL. Preserve `/storefront/home` as a compatibility alias and legacy one-segment custom campaign URLs via redirect.
- Public browsing is read-only and no longer requires P25/P26 Admin preview permission or the isolated QA tool. `HomepageBuilder::publishedRevision()` does not permit draft access.
- Admin CRUD and publication remain permission-protected; these controls are unrelated to visitor browsing.
- No user approval to bypass transactional inventory, checkout and restricted DB gates was given. The user explicitly declined automated testing, so main-DB storefront cart, customer registration and checkout remain **BLOCKED / NOT RELEASED**.
- Before any real commerce release: obtain signed inventory reconciliation, real MySQL concurrency/CSRF/checkout QA, payment/COD verification, backup/rollback and release approval.
- Historical P19/P24/P25/P26 source-only QA contracts that assert `/` must remain a campaign describe an obsolete route policy and must be updated for a future automated regression run; they have not been silently modified or reported as passing.

### ADR P34 — Dark-green responsive storefront UI without commerce bypass (2026-10-27)

- **Evidence:** User-uploaded `website(20261026-211451).zip` and mobile/desktop screenshots show P33 blue overrides, native horizontal product-shelf scrollbar, Wishlist repeated in desktop link row, overlapping mobile floating cart and weak pictogram/buttons. Existing P32 Cart/Account/Wishlist routes and server API are present.
- **Decision:** Add scoped `p34-storefront` palette/style layer loaded last in existing public homepage/catalog layouts; do not affect legacy campaign/admin UI. Keep existing section-typed color tokens for Hero CTA configuration. Use reviewed inline SVG icon partial with fixed shapes (no external icon scripts/fonts), real POST cart/wishlist actions and server-owned counts. Rename only an **eligible** checkout submit to Buy Now; products with unavailable/uncertain SKU still link to product details/option picker instead of forging an Add action.
- **Responsive:** On mobile, top header exposes available Account, Wishlist and Cart actions; fixed dark-green bottom dock has Home, Menu, Wishlist, Cart, Search and Account when enabled, with adjacent wishlist/cart and real counts. Hide redundant mobile floating mini-cart to prevent occlusion; desktop floating cart remains centered. Hide only horizontal carousel scrollbars while retaining touch/keyboard/arrow interaction. Auto-slide visible product shelves right-to-left with reduced-motion, hover, focus, touch and tab-visibility pauses. Published Builder category shelves join auto-slide behavior; private preview still uses its static grid.
- **No release/security change:** Do not change commerce-mode middleware, route mapping, auth, CSRF, inventory, pricing, order transactions, publication rules, shipping, campaigns, migrations or `.env`. Full checkout still depends on the existing readiness checks; presentation alone cannot approve a release. Do not invent test rows or non-functional buttons.
- **Verification:** P34 read-only source contracts and P33 existing contracts plus lint are run against extracted ZIP; no live local Windows/MySQL acceptance. Browser screenshot attempt was blocked by browser environment policy, not recorded as visual PASS. Owner manual 320px/360px/390px/desktop Cart/Wishlist/product checks remain pending.

### ADR P36 - Banner-first hero, draft-only editor actions and zero-stock initial Color/Size (2026-10-27)

- **Evidence:** `website(20261027-031542).zip` plus screenshots show oversized desktop/mobile hero copy gaps, inset gallery thumbnails, a published-only homepage revision with `Draft: None`, create-product Color/Size requiring a second screen, and mobile mini-cart missing/right-center requirement.
- **UI decision:** Keep the source-bound right-to-left carousel and typed hero slides, show a full-width image-first 3:1 banner (desktop) and uncropped edge-to-edge banner (mobile). Keep overlay text only where explicitly configured. Use scoped `storefront-p36.css/js`, no global Admin or campaign layout mutation. Desktop floating cart unchanged, mobile cart right-center using real count/total, original bottom nav & sticky checkout gates preserved.
- **Admin decision:** Published revisions remain immutable. `Edit Published` creates a new draft using the original version lock; PATCH updates only owned draft items, POST discards only an editable draft by archiving it and retaining audit/published references. Admin role and CSRF mandatory; reviewed media/link allowlists retained.
- **Catalog decision:** On create only, active option values are selectable. New optioned product is automatically prepared/converted through existing `VariantCatalogManager`, default SKU zero stock, new Color/Size SKU zero stock, product remains **inactive**. No synthetic active stock and no bypass of the existing variable-checkout release hold. More combinations/prices remain editable under Variant Manager.
- **Pending gates:** Real MySQL transaction test (including rollback on invalid value), concurrent draft-version conflict, live banner media, 320px/360px/390px mobile, cart/checkout/inventory QA, manual sign-off. No migration, seed or `.env` modifications.


## ADR P37 - Storefront customer contact and review publishing (2026-10-27)

- Live public homepage shows all **active global** (`campaign_id IS NULL`) reviews with their existing Spatie `review_customer_image`. Disabled Review sections remain hidden; editor previews keep pinned revision items. Campaign-specific reviews are never promoted to the storefront.
- Existing `site_settings` fields are the single source of public phone, email, address, working hours and business description; Admin Site Settings now edits these columns. No duplicate contact-settings table. Existing campaign-specific site-setting controls are not removed.
- New forward-only `storefront_contact_messages` table stores encrypted customer name/email/phone/message/admin notes. Public CSRF + throttled submission and admin-only message CRUD; no external email sending or production migration claimed.
- Map URLs originate only from the configured address and fixed Google Maps hosts. No address, credentials, or example business data are seeded.
- Keep campaign landing, checkout, inventory, order and previously published homepage history unchanged.

### ADR P38 - Separate FAQ, media branding, contact dock and audit-safe history actions (2026-10-27)

**Evidence:** User screenshots and `website(20261027-052826).zip` show Contact+ as a text menu separate from the floating cart, campaign logo/favicons shared with storefront, public FAQ reading `Faq` without a separate admin workflow, and Revision History without a delete action. Existing `storefront_revisions` has `restrictOnDelete` references from page, audit and section tables.

**Decisions:** Keep the functional mini-cart. Move validated contact links into a right-side accessible compact `Contact+` dock alongside it. Introduce independent `storefront_faqs` table/Model/Admin role-gated CRUD, and update only `/storefront/help` to use it; do not change legacy Campaign FAQs. Use existing SiteSetting + Spatie media model, but separate storefront logo, white logo and favicon collections from campaign images. Existing Campaign brand accessors and media paths remain unchanged.

**Archive:** Do NOT physically delete immutable revision rows or audit references. New nullable `deleted_at` hides an **archived** revision from UI and preview; published/draft are forbidden. Admin action locks the homepage row and checks `expected_version`, writes a homepage audit event, increments version, and retains child records/media. History edit works by cloning the selected visible revision to a draft using existing authenticated POST. A future explicit recovery UI could clear the marker after reviewed ownership checks. No public draft mutations.

**Release:** Two forward-only migration files, guarded by P02 migration identity. No migration/seed or DB writes executed in the delivered workspace. Native Laravel/MySQL/browser acceptance remains manual on the user's Windows installation. Local backup required before migration, and no production release implied.

### ADR P39 - Reference visual merge, carousel direction, customer-only wishlist (2026-10-27)

**Evidence:** Uploaded `website(20261027-082637).zip`, green NextGenAIBD homepage reference and screenshots showing rotating Contact label, low-contrast active category text, manually controlled review cards and anonymous session wishlist. Current `StorefrontWishlistController` stored one shared anonymous `storefront_wishlist_ids` array.

**Decision:** Add `storefront-p39.css/js` loaded only in public storefront layouts, preserving AdminLTE and legacy Campaign. Reference styling uses the existing published hero and Spatie imagery rather than embedding a flattened screenshot. Review cards remain read-only, and the visible section respects Homepage Builder's enabled/device/theme rules. JS autoadvances through reviews in left-to-right reading order, with working dots, previous/next buttons, touch, keyboard, visibility, focus, hover and reduced-motion pauses; no duplicated review DOM or fake reviews.

**Customer ownership:** Wishlist POST and GET require an active authenticated `storefront` customer guard, not admin `web` auth. Guest hearts link to the existing customer login/registration path. Session state is now keyed per authenticated customer ID; pre-existing anonymous wishlist state is intentionally ignored and never imported. Cart, checkout, published pages and order/inventory services remain unchanged. No new schema migration. Wishlist remains **session-scoped**, not cross-device/database persisted.

**Limitations:** Local browsing/auth existing P32/P26 readiness restrictions remain enforced; this patch never bypasses them. Preview and published page data sources are unchanged. Live MySQL/customer login and actual website screenshots still require operator integration testing. Any future cross-device wishlist requires a separate reviewed migration and consent.

### ADR P40 — Reuse existing category/variant models; Site Settings owns footer social links (2026-10-27)

- Evidence: Uploaded `website(20261027-103622).zip` contains existing two-level `categories.parent_id`, ProductOptionValue, controlled VariantCatalogManager, `site_settings` contact fields, and separate legacy `social_media` links. Red-box user screenshots ask for their management with premium UI and preserved prior features.
- Decision: No new subcategory table. On existing parent category page, render active child cards. Extend existing option-value CRUD with transaction locks; codes used by variants remain immutable, referenced values never hard-delete, active uses must be retired before deactivation. Keep product variants zero-stock/inactive pending checkout acceptance.
- Footer: Add four nullable HTTPS social URL fields and `footer_social_managed` boolean to `site_settings`. Prior to first settings save, fall back to the original `social_media` records; after save, blank fields hide icons and this setting becomes public source of truth. WhatsApp and call continue to derive from prior phone fields, no duplicate data. Existing campaign-specific footer controls remain independent.
- UI: New back-to-top script/style is shared by AdminLTE, storefront, campaign and conventional app layout. Respects reduced motion and does not cover existing right-hand floating cart/contact dock.
- Migration: Forward-only, data-preserving; staged/operator-reviewed before execution. No automatic schema/production write. Manual MySQL/browser integration sign-off remains open.

### ADR P41 — Builder-only public sections and validated live section-order sync (2026-10-28)

- **Evidence:** Owner screenshots from `website(20261028-021532).zip` show a draft with eight typed sections but additional Clothing, Natural Oil, Organic Food and other shelves on `/`. Verified cause: `StorefrontCatalogController::home()` generated `autoShelves`/`autoCategories`, and `storefront.pages.home` appended out-of-band shelves, trust/stat/track areas and global reviews. A second review loop deferred configured reviews to the end of the homepage. The live published revision is intentionally separate from the current draft.
- **Owner decision:** ONLY enabled and populated sections explicitly saved in the published Homepage Builder revision may render in `/`, in the exact stored `sort_order`; the same typed section renderer is used by the authorized draft preview. Review cards use only active, section-selected `review_id` references in stored item order. Site header/footer and dedicated floating controls remain global chrome. Standalone tracking route remains operational; the unsolicited in-home tracking section is removed.
- **Live reorder UX decision:** Drag/keyboard/Up/Down controls of the admin UI explicitly send `publish_after_sort=true`. Reorder + existing publication validation + creation of a fresh editable draft occur within a single database transaction. The old publication is archived, never mutated. The editor reloads after success to show correct version/revision history. A failed publication preflight leaves the new order saved in the private draft only and tells the admin that the live page was not updated. All saved draft changes (not merely order) are published on a successful live reorder; the UI warns the admin to save other form edits before sorting.
- **Compatibility/security:** API requests without `publish_after_sort` keep pre-P41 draft-only behavior; existing P06 regression tests remain valid. Active-admin authorization, CSRF, optimistic version 409, exact UUID list validation, HTML Up/Down fallback, allowed theme tokens and publication media checks remain unchanged. No migrations, data resets, media deletion, changes to campaign/checkout/courier or new dependencies.
- **Presentation:** Restore manual carousel arrows within configured category shelves; shift viewport-fixed desktop cart and Contact dock from midpoint toward 57% without changing actions and use mobile safe-area offset for Contact. Add a missing static AdminLTE fallback PNG to match the existing P40 placeholder contract.
- **Verification limits:** P33–P41 updated offline source checks and PHP/JS lint run. Owner's actual MySQL, Laravel HTTP, private draft Preview and desktop/mobile browser acceptance are unverified because `vendor/` and live DB are unavailable here. Added isolated MySQL P41 HTTP tests; MUST run on the user's dedicated test database before production release.


### ADR P42 - Keep mobile banner media uncropped and show all configured copy (2026-10-28)

- **Evidence:** User-uploaded `website(20261028-053823).zip` and four screenshots. The standalone `resources/views/storefront/pages/home.blade.php` lacks back-to-top CSS/JS included by every other main layout. P36 mobile hero has `max-height:72%;overflow:auto` within image-ratio-controlled carousel; content is centered in a smaller scroll container, cutting off the top `Featured Collection` eyebrow and parts of the heading. The product detail route already displays the shared arrow correctly.
- **Decision:** Include the existing `back-to-top.css/js` in the standalone homepage (public and private preview) and the special local readiness HTML page; reuse its accessible animated scroll button unchanged in other layouts. Do not add duplicate controls.
- **Mobile hero decision:** Only text-bearing `p36-hero-with-copy` slides on <=760px stack the original uncropped banner above their dark-green copy area. Dynamic carousel height is image intrinsic height **plus** natural text height; no fixed-height crop or text hiding. The Featured Collection eyebrow becomes an explicit high-contrast pill. Desktop hero and image-only mobile slides continue their existing arrangement. Autoplay, preview, section settings, CTA destinations and cart/contact behavior are unchanged.
- **Evidence / release limits:** Offline existing P33-P41 source regression, P42 source checks, and Chromium synthetic visual/interaction checks at 320,390,760,1024,1366px. Actual Laravel Blade rendering/MySQL integration and on-device iOS/Android UI require owner testing; no production changes, migrations, DB operations or `.env` modifications.

### ADR P43 - Restore mobile hero image/text overlay while preserving full copy (2026-10-28)

- **Evidence:** Owner's new desktop/mobile screenshots after P42 show an unintended split: the image occupies the upper mobile block while the title/description/CTA occupy a separate green block. The owner explicitly requested desktop-like overlay, with all fields visible at mobile sizes. Uploaded `P42-CHANGED-FILES-PATCH(1).zip` was byte-compared with the P42 working source for provenance.
- **Decision:** Add `storefront-p43.css` after the historic P42 stylesheet. At <=760px, only text-bearing homepage hero slides position the approved banner behind a full-panel readable gradient and copy; image-only slides and desktop are untouched. Use `object-fit:cover` for this **specific** overlay so a mobile image may be cropped; this replaces P42's "always un-cropped" decision for text-bearing mobile slides. Do not remove any media or modify database content.
- **Height/overlap:** Revise `storefront-p36.js` to size the overlay by the maximum of image intrinsic ratio and actual visible text/padding/gap. Keep Featured Collection, CTA and carousel controls fully inside the panel. Reserve copy width away from the existing fixed mobile mini-cart. Do not modify the cart or other layout rules.
- **Compatibility/security:** Include the new CSS via existing shared `home.blade.php` in private preview and public homepage; cache-bust only the edited hero-height JS. Preserve builder revision/CSRF/auth, other routes/layouts, carousel navigation, reduced-motion behavior and old source/test fixtures. P42 static checks were updated only where its no-longer-current stacked layout expectation conflicted with the new owner directive.
- **Verification/limits:** 9 Chromium synthetic viewport sizes, two text slides + image-only, exceptionally long text at 320px; P33-P43 source contracts. Laravel/XAMPP/MySQL/live media, iOS/Android and staging acceptance not tested here. No migration, reset, key change or production action.

## P44-ADR-01 (2026-10-28) - Image Text hero overlay + mobile Contact clearance

- **Decision:** Preserve typed builder section and media IDs. Render all Image/Text section and item copy on top of the selected item's approved image with a high-contrast responsive gradient, no additional fields or data migration. For multiple items repeat the full image-backed card and show the section header only once in the first visible item. If there is no visible approved item, do not fabricate an image; private Preview explains the missing item.
- **Contact behavior:** On bottom-nav viewports up to 760px move the existing fixed contact dock farther up, open methods upward along its left side, support short viewports with a constrained scrolling list, preserve desktop positioning and P38 JS keyboard/outside-close behavior. Do not change configured contact method targets or mini-cart behavior.
- **Safety:** No changes to other sections, routes, models, controllers, DB, migrations, cart/checkout, campaign, site media, publication semantics or authentication. Local browser with owner's runtime and real media remains an explicit acceptance requirement.

### P46-ADR-01 (2026-10-28) — Cap homepage Product Collection and split Image/Text heading

- **Client evidence:** latest `website(20261028-065803).zip` and screenshots showing more than eight curated cards plus the section intro inside the Why Choose image.
- **Decision:** enforce an 8-**eligible**-product display cap at rendering, after filtering nonpublic items, preserving selected ID order, duplicate rejection and stored selection. Do not change product CRUD, pagination, published revisions, commerce or Admin form contract. `View All Products` uses existing `storefront.shop` route; preview has an inert matching CTA to preserve private isolation.
- **Design:** title/subtitle/description outside the image, approved item title/text/CTA overlay inside; only the homepage image_text section uses P46 overrides. Existing responsive media and theme system remain the authority.
- **Data/backfill:** none. **Campaign/order compatibility:** untouched. **Testing/rollout:** source contracts + synthetic Chromium; real Laravel/MySQL and device acceptance still required.
- **Additional baseline hygiene:** the user's ZIP omitted referenced AdminLTE `no-image.png`, so the already-used neutral placeholder was copied to that missing asset path. No upload was modified.

## P48 ADR — verified-local variable SKU activation (2026-10-28)

Decision: Do not bypass `P32LocalStorefrontAccess`, `P02MigrationSafety` or the allocator in order to turn buttons on. A new admin POST on the existing Variant Manager validates option signatures, category, audited ledger, stock mirror, positive price and global checkout readiness within a transaction before activating the product. An explicit Pause action preserves stock/orders. Single in-stock SKU is auto-selected in the public picker. Legacy campaign paths and production release gates remain unchanged. New option dimensions cannot be added to an active product without pausing first.

Open item: Execute real MySQL transaction, duplicate checkout, last-unit concurrency, shipping, session ownership and order cancellation tests before approving production. No production release is claimed by this patch.

## P49 ADR — Scoped Homepage Shipping Charge settings (2026-10-28)

**Decision:** Reuse the existing `shipping_charges` table and `ShippingCharge` model. New admin-only Sidebar > Settings > **Shipping Charge for Homepage** provides an inline, validated area/BDT/status form plus edit and soft-delete actions. Scope strictly to `campaign_id=NULL`, with checks at HTTP and query layers, not just the UI; campaign charges remain independently owned by CampaignController. Disable/soft-delete of the last active homepage area is blocked to protect Checkout readiness. No schema migration, rate seed, campaign copying, feature-flag bypass or other storefront/business logic update.

**Known constraint:** No runtime MySQL/HTTP tests are possible from the ZIP alone without vendor and an isolated verified test DB. The existing historical campaign global-shipping backfill migration should not be re-run to copy newly created Homepage rows into campaigns. Rate amounts and locations must be entered/approved by the site operator; none are assumed.

### P50-ADR-01 (2026-10-28) - Preserve checkout introduction and scale only storefront desktop

- Scope: the existing catalog/home Blade templates, `storefront-next.js` and a new `storefront-p50.css`. This is a UI/source fix, not an expansion of checkout permission or a payment-system change.
- Cause: initialization `show(1)` performed unconditional scroll; the 140px header offset did not match sticky header height. Suppress initial scroll and measure the header for user-triggered stage changes.
- UI: keep four real step-indicator states, use CSS connectors, display checkout instructions beside the steps on large desktop. Shared store views widen modestly only at >=1100px; color tokens, section publication and mobile layout are preserved.
- Security/operations: do not alter server-validated shipping, COD, order/stock transactions, CSRF, payment gateways, campaigns or admin settings. No DB change. Actual local Laravel/MySQL and production QA remain unverified.

## P51 ADR: Mobile-first sizing and branded storefront loader (2026-10-28)

- **Decision:** Add a scoped P51 CSS layer to the two existing storefront entrypoints (standalone homepage and shared catalog layout) rather than editing AdminLTE or legacy campaign pages. Preserve the documented 2-mobile/4-desktop grids and P43 hero-image overlay.
- **Brand authority:** reuse `StorefrontBranding::name()` and `logo()`, backed by Site Settings; do not copy media or invent a second branding table.
- **Loader policy:** reveal only when JavaScript has initialized, hide at DOM ready, cap wait to avoid inaccessible/stuck screens; tolerate JavaScript disabled, BFCache and reduced-motion preferences.
- **Scope/rollback:** no database/schema/route/auth/cart/order changes; three new loader/UI files and two changed Blade entrypoints. Restore P50 Blade versions and remove P51 assets to roll back.
- **Open gate:** Test actual logo loading, mobile navigation/checkout and touch behavior on the user's Windows/XAMPP DB and mobile browsers before production sign-off.

### P52 (2026-10-28): Customer account profile extension and safe navigation UX
- Scope: existing storefront account, empty-cart GET checkout, branded loader, floating WhatsApp contact, category dropdown presentation. No campaign/admin/order/payment/stock mutations.
- Decision: optional profile fields (`gender`, `date_of_birth`, `division`, `district`, `upazila`) are additive, with an explicit approved local-DB migration gate. Existing `name`, read-only login `email`, `phone`, and delivery `address` remain authoritative.
- Profile images: reuse the already-installed Spatie Media Library `media` table with a dedicated `StorefrontAccount::profile_photo` collection (`public` disk, `singleFile()`). Validate JPEG/PNG/WebP <=2MB and use UUID filenames; Spatie manages replacements/removals. No `profile_photo_path` column or separate manual upload folder.
- Empty checkout: redirect to Cart with a visible status message instead of returning HTTP 409 on a safe GET. A genuinely rejected purchase/cart POST still uses its existing server validation.
- Mobile WhatsApp: keep validated Site Settings link as progressive fallback; open an in-page dialog first with an explicit external WhatsApp action and copy-number alternative. The third-party destination cannot be guaranteed available.
- Loader: requested ~5.5s branded experience on normal navigation; reduced-motion skips prolonged animation/hold; 8-second watchdog + BFCache release avoids stuck overlays. This intentionally delays perceived page readiness and should be reviewed against mobile performance requirements.
- Remaining gate: protected local schema migration, real MySQL account/profile/photo upload and Cart/Checkout HTTP tests; production rollout requires explicit separate approval.

### ADR P52-R — Adopt existing Spatie media for customer avatars (2026-10-28)
- **Reason:** Customer already has Spatie Media Library installed; first P52 draft proposed redundant `profile_photo_path`/manual storage. Owner reports that first P52 was not installed or migrated.
- **Decision:** Replace original P52 draft in the shipped revised patch. `StorefrontAccount` implements `HasMedia`, registers a dedicated single-file `profile_photo` collection on existing public disk, accepts validated JPG/PNG/WebP <= 2 MB, and uses Spatie-managed replacement/removal. Existing User, Product and Campaign collections remain independent.
- **Schema:** Only five optional non-image storefront account fields require the pre-existing P02-guarded additive migration; `media` table is reused. No photo-path column or new media-table migration.
- **Release gate:** Validate disk/URL configuration and real authorized profile HTTP upload, replace and delete on approved local MySQL before deployment. Never remove unrelated media or bypass current DB approval.

## P53-S1 (2026-10-29) — Reuse shared courier lifecycle and scoped Meta events

- **Chosen:** existing signed Steadfast/Pathao webhook and tracking lookup work on shared `Order` with attached `CourierAccount`; public Homepage orders (`campaign_id = NULL`) stay in same lifecycle once booked to a courier using existing admin flow. Reuse `/track-order`, do not invent campaign-specific filter, duplicate webhook, automatic courier booking or new tracking schema.
- **Analytics:** use only active admin-managed numeric Meta Pixel IDs on public Storefront; existing first-party event bridge emits PageView, ViewContent, server-confirmed AddToCart, eligible InitiateCheckout and existing success-page Purchase. Do not copy arbitrary pixel script code; no PII event fields.
- **UX:** sticky mobile product Buy Now reuses original form and honors measured bottom navigation; public description CSS is scoped to paragraph content.
- **Gates:** real courier callback and Meta Events Manager receipt, MySQL HTTP and multi-device UI checks are required before production claims. Existing permissions/campaign-order flows and all migrations untouched.

## ADR P54 — Storefront tracking continuity, total preview and mobile contact (2026-10-29)

- **Source:** `website(20261029-031525).zip` and owner screenshots. Current storefront Card Add-to-Cart was asynchronous, while Product Details used a full-page POST; the latter's event appeared on the destination page and vanished from the previous page's inspector. The checkout displayed the hard-coded placeholder `Calculated on confirmation` even after selecting a delivery area.
- **Decision / analytics:** Product Details now uses the same progressive AJAX cart form as Product Cards. On verified AJAX Cart success, emit exactly one `AddToCart` event before unrelated summary requests. AJAX `Buy Now` does not emit before unloading; the server flashes authoritative item/price to Checkout, which reuses the existing P53 event bridge. No attempted/failed add fires an event. No customer PII in events; existing active Pixel ID authority unchanged.
- **Decision / prices:** Public checkout displays a non-authoritative BDT subtotal-plus-selected-shipping *estimate* in the Summary and Review. Whole paisa conversion avoids JavaScript floating-point fractional-currency drift. Order creation, shipping selection, price, tax and stock continue to be validated and recalculated server-side. Do not treat estimate as a binding price.
- **Decision / navigation:** remove only the top-level desktop `Checkout` menu item. Cart / Drawer / Checkout routes remain available. No new route, migration or schema change.
- **Decision / media:** enlarge branded loader image frame and use `object-fit:contain` (without cropping); preserve current source loader delay of `1000ms` (do not silently override the owner's custom setting).
- **Decision / WhatsApp:** WhatsApp cannot render inside iframes due to third-party framing restrictions. Use an explicit first-party dialog with native `whatsapp://` handoff and a standalone HTTPS link (`target=_blank`) plus copy-number fallback; do not embed WhatsApp or assume external network availability.
- **Open gate:** real MySQL HTTP Buy Now / cart / order integration, physical-device app/browser handoff and Meta Events Manager delivery must pass before production signoff. The old P52 offline duration check demands `5500ms` while the supplied unchanged source uses `1000ms`; record that pre-existing discrepancy rather than changing the owner's loader duration.
