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
