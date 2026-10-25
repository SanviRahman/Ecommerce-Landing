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
