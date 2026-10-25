# Bugfix Register

**All items start OPEN.** A proposed fix is not a fixed bug. Severity is relative to the new storefront release; source findings must be covered by reproductions/tests.

| ID / priority | Evidence and meaning | Required repair | Completion test |
|---|---|---|---|
| B01 / P0 | E06: campaign product fallback accepts unrelated active catalog product | Remove global fallback; require campaign membership + valid active variant, reject invalid request atomically | Submit active foreign product: 422, no order, no stock movement |
| B02 / P0 | E07: campaign order records items without stock enforcement | Use shared InventoryService for aggregate quantities under locks; no frontend-only max | Stock 1 with two real DB clients: one succeeds, one fails |
| B03 / P0 | E08: isolated OrderManagementService checks before unlocked decrement; other ingress bypasses | Centralize all local consumption/edit/status paths; retire duplicate decrement logic | Concurrent service/manual/campaign purchase cannot oversell |
| B04 / P1 | E07: session token consumed before remaining order validation; not durable idempotency | Keep CSRF; add scoped checkout_requests; preserve retry after failure, replay same order | Failed shipping/stock then corrected retry works; double-click creates one order |
| B05 / P1 | E09: protected GET command routes mutate caches/schema/data | Disable production migrate/seed HTTP routes; approved maintenance actions POST+CSRF+auth; update callers | GET causes no mutation; guest/employee denied; invalid CSRF denied |
| B06 / P1 | E10: custom root campaigns may use new fixed route slugs | Audit live slug conflicts; reserve paths and preserve approved aliases/redirects | Existing campaigns not silently shadowed by /cart or /shop |
| B07 / P1 | E02-E03: shared campaign/home Blade | Keep current landing and create storefront renderer; no global CSS leakage | Before/after campaign visual and submit regression |
| B08 / P1 | Source archive: tests folder absent | Add TestCase, factories and meaningful characterization tests before refactor | PHPUnit discovers real tests; failure is reproduced before repair |
| B09 / P1 | E13: decimal manual amounts vs integer order columns + float arithmetic | Verify actual types; widen money storage preserving BDT; use exact arithmetic | 1000.00 minus 0.50 remains 999.50 through persistence/report/export |
| B10 / P1 | E18: raw HTML output exists; sanitizer path unverified | Trace write/render chain; escape plain text; allowlist sanitizer only where rich HTML needed | Script/event-handler payload is inert; approved formatting preserved |
| B11 / P1 | New functionality absent: variants/subcategories/builder/cart | Implement explicit data contracts rather than copy reference code | R02-R11 tests pass |
| B12 / P2 | E11: version_urls=false and replace-in-place workflows may serve stale assets | Builder uses immutable media IDs/files and safe URLs; evaluate cache busting for old media | Replace draft image: published old image intact, new image after publish |
| B13 / P1 | Inventory edits/admin status changes have no unified allocation history | Managed lifecycle deltas, idempotent cancellation/return handling, legacy reconciliation | Repeat cancel does not double-restock; edit quantity applies difference |
| B14 / P1 | Operational secret hygiene: .env packaged | Exclude secrets from shared deliverables/commits; owner reviews exposure and rotates only actually exposed credentials | Secret scan passes on output/commit; APP_KEY not blindly replaced |
| B15 / P1 | New style/reorder payloads are attack surfaces (proposed risk) | Typed allowlists, policy, foreign-ID rejection, version conflicts | CSS injection/foreign section/missing IDs rejected; 409 stale edit |

## Reproduction rules

Use fake fixtures in an isolated DB; no probing production order endpoints. Capture request fixture, current result, expected result and test ID. Source evidence of missing checks is sufficient to prioritize a repair but not a claim that a production exploit was demonstrated.

## Specific traps to avoid

- Quantity duplicates must be aggregated by **variant** and validated again after aggregation. A per-line cap alone can be bypassed through repeated rows.
- Do not silently skip invalid selected products and create a partial order without customer approval. Reject the request with a field error.
- A DB transaction without row locks/conditional stock updates does not by itself prevent the check-then-decrement race.
- Existing OrderManagementService is not proof that every ingress calls it. Map campaign, manual create/update, bulk conversion and external imports explicitly.
- The reference's Cart::destroy is not a bug in the target ZIP; it is a behavior not to copy (E17).
- Package version pinning and syntax success do not imply a vulnerability scan or working dependency installation.

## Closure evidence template

For each item append: reproduction, changed paths, regression test names, actual command/exit status, screenshots if UI, and commit identifier when available. If a risk is disproved, record why; do not fabricate a patch to an absent bug.


## Meeting-reported gaps are not automatically confirmed bugs in this ZIP

The user-provided 32-page AI meeting report describes a different demonstrated site (proyojonerbazar.com) and requested nested submenu, responsive 4/2 grids, CTA ratio, 2-part product detail, sticky mobile UI and theme pickers. These are **M-reported client change requests**, not reproducible bugs proven on the uploaded campaign source. Track them as F-001..F-011 in docs/20, with acceptance cases in docs/25; only add B-IDs and a confirmed root cause after reproducing on the real target revision. Do not claim the referenced site's observed past 500 error came from this Laravel ZIP.

### P04 source hardening — 2026-10-25

- Verified risk: `ProductController::update` previously overwrote `products.stock` from generic edit form regardless of `inventory_version`; with P03 managed allocations that can desynchronize stock mirror/ledger. **Patch:** row lock + reject changed managed stock + read-only UI field; normal legacy product edits preserved. Runtime regression still pending.
- Verified risk: generic `forceDelete` would violate FK/history after P02 `product_variants` records exist. **Patch:** reject permanent deletion of products with variants and skip these in bulk force delete.
- Identified limitation: category hierarchy schema existed but old Category CRUD lacked parent/sort/cycle protection. **Patch:** optional parent/sort fields with test-only validation and cycle guard. Runtime category regression pending.
- A stage-guard prevents accidental sale of variable SKUs via old checkout: converting simple to variable sets `status=false`; ordinary admin/bulk reactivation is rejected until variant-aware order processing exists.

### B16 / P1 — P07 smoke test references a nonexistent `guest_carts` table (2026-10-25)

- **Verified on uploaded `website(6).zip`:** `database/migrations/2026_10_24_210011_p02_create_guest_carts.php` actually creates `carts` and `cart_items`. The P07 smoke prebootstrap and `P07PublicCatalogMySqlTest` instead queried `guest_carts`, causing MySQL 1146 before PHPUnit ran.
- **Reproduction before patch:** `node tools/qa/check-p07-catalog-contract.mjs` with the newly added schema consistency assertions produced **32/34, FAIL** (two mismatches); user's Windows runner previously reported `Base table ... p01_campaign_test.guest_carts doesn't exist`.
- **Scoped fix:** replace the phantom table in `tools/qa/p07-mysql-catalog-smoke.php` preflight with both real cart tables, assert both real tables remain empty in the existing PHPUnit test; add migration/schema consistency regression assertions and clear missing-file diagnostic to `check-p07-catalog-contract.mjs`.
- **Offline evidence after patch:** P07 source contract **34/34 PASS**, P07 DOM unit **10/10 PASS**; full PHP lint and other static checks passed in extracted source. **The 16-case real MariaDB/PHPUnit suite is NOT executable in this container (no MySQL/PHPUnit vendor)**. It remains **PENDING user rerun**; do not mark the runtime bug verified resolved until `Exit Code: 0`.
- **Local extraction:** uploaded archive contains `resources/views/storefront/pages/shop.blade.php`; earlier Windows `ENOENT` indicates local extraction/path mismatch, not a missing file in uploaded ZIP. Supply the Blade file and post-extraction existence checks in the hotfix package.
- **No migration, reset, DB write, production configuration or checkout behavior changed by this patch.**

### P08 preventative fixes / coverage (2026-10-25)

- P07 `guest_carts` mismatch is retained as corrected in this source baseline; P08 exclusively uses the real `carts`/`cart_items` schema.
- New feature risks explicitly covered by P08 code: foreign-cart line mutation, optimistic stale updates, client price/variant manipulation, missing shipping, duplicate checkout and double allocation. A **17-case real MySQL regression suite** has been authored, not executed here; do not mark these production issues fully closed before real runtime evidence.

### B17 / P1 — P08 idempotency claim raced ahead of cart lock (2026-10-25)

- **Verified from current P08 source:** `StorefrontCheckout::place()` read a cart ID without locking outside its transaction and called `CheckoutIdempotencyService::claim()` before `assertOwnedCart()`. Two overlapping requests on the same key/owner could both try to create a new unique claim, producing an avoidable SQL duplicate-key/deadlock rather than the expected single committed order and stable replay.
- **Scoped patch:** lock the owned `carts` row first, calculate request hash from that locked cart ID inside the transaction, then claim. Preserve stable retry after completed checkout and existing BDT/cart/inventory/campaign behavior.
- **Added regression:** 2 new P08 rollback-only cases for missing/replaced owned cart and P09 real two-worker last-stock service-level smoke, with explicit separate committed-fixture approval and targeted cleanup. P09 source checks are not a substitute for MySQL results.
- **Status:** source fix prepared; real local tests PENDING. Existing P08 17/17 result is **before** this patch; do not reuse it as 19/19 evidence.

### B18 — P09 real browser CSRF probe blocked by unsigned PowerShell script (2026-10-25)

- **Reproduced from user terminal:** `p09-browser-csrf-smoke.ps1` returned `PSSecurityException / UnauthorizedAccess` due to the `.ps1` not being digitally signed. P08 suite separately passed 19 tests/83 assertions; these are unrelated concerns.
- **Source-verified root cause:** the P09 probe is implemented only as an unsigned PowerShell script; its code cannot execute under the user's current execution policy. Do not disable CSRF or lower machine-wide execution policy to mask this.
- **Scoped fix:** add a Node.js real-HTTP CSRF runner (no PowerShell `.ps1`), pure cookie/status helpers, an isolated read-only P02 schema+empty-cart guard, and an offline unit/source checker. Keep existing P09 `.ps1` as optional; no production code, routes, migrations or database changes.
- **Evidence:** offline Node cookie/safety suite 20/20 PASS, PHP new guard syntax PASS, unsigned PowerShell not invoked; actual user's localhost 3/3 HTTP test and committed concurrency test PENDING.

### 2026-10-25 — P10 storefront UI source bugs (staged patch)

- **P10-UI01:** Catalog footer asserted checkout was unavailable regardless of enabled P08 flags. Fixed via explicit browse/cart/COD branches. Source regression P10 check included; MySQL HTTP suite pending.
- **P10-UI02:** Published homepage omitted gated cart navigation. Added links for desktop/mobile, private preview intentionally untouched.
- **P10-UI03:** Product add-to-cart validation failures were invisible. Display scoped `$errors` with an accessible alert and restore remembered variant value on a failed request; no server-side price/stock bypass.
- **P10-UI04:** Storefront lacked configured customer contact actions. Added safe `SiteSetting`-driven WhatsApp/Messenger/Email/Call only, with an explicit URL allowlist; no fake controls.
- **P10-UI05:** Mobile product checkout lacked persistent Order Now. Added a second visual submit control owning the **existing P08 form** (not a new order path); no checkout enabled by default.
- Evidence: `P10-INTEGRATION-AND-TESTING-BN.md`; source/unit tests PASS; runtime and full staging acceptance not yet verified.


### B19 / P10 integration — invalid synthetic error-bag test did not exercise Add-to-Cart (2026-10-25)

- **User reproduction:** `php tools/qa/p10-mysql-ui-smoke.php` ran 7 rollback-only Laravel tests; 1 failed (`test_product_error_is_visible_without_mutating_inventory`, 7 tests / 28 assertions) while source checks P10 23/23, P09 18/18, P10 contact 16/16 passed. Product response included `Browsing preview` rather than the synthetic cart error.
- **Confirmed source mismatch:** `setUp()` left `p08_cart_test_enabled=false`, and the test injected `ViewErrorBag` directly with `withSession()` before issuing a GET. It did not open the real cart form, perform invalid Add-to-Cart POST, or assert an actual validation redirect. The product Blade *already* contains `$errors->any()`/`role="alert"`; the failed test does not, by itself, prove the production Blade is broken.
- **Scoped test repair:** enable isolated P08 cart for this one test, render real product form, POST invalid `product_variant_id=0`, assert redirect + session errors, and verify the returned page shows the validation message, alert, and original quantity. `old('quantity', 1)` restores the entered quantity after a rejection.
- **Authoring proof:** corrected test PHP syntax + P10/P11 offline source QA passed; **real corrected 7-case MySQL run is still pending user's Windows result.** No inventory mutation/migration or production activation.

### P11 release review — no automatic production sign-off (2026-10-25)

- Source controls: restricted read-only P02 MySQL gate, migration/schema inventory, active managed stock mirror, default/variable SKU completeness, published pointer, global COD delivery, pure policy tests and SHA-256 fingerprint of allowlisted source files only.
- These checks are deliberately fail-closed. Empty isolated test data yields `BLOCKED_DATA_READINESS`, NOT an approval. Source test success never implies staging deployed or production safe; manual operational evidence in `docs/P11-release-signoff-template.md` remains unsigned.
- No production migrations, database writes, seeding, key rotation, media movement, checkout flags, external calls or deploy performed by this patch.


### P10-VF-02 — Product validation feedback missing after rejected Add-to-Cart POST (2026-10-25)

- **Evidence:** User's actual P10 MySQL output: 7 tests / 34 assertions / 1 failure; HTTP 302 and `assertSessionHasErrors(product_variant_id)` passed, `old('quantity')=2` restored on GET, but no visible alert.
- **Confirmed scope:** Rendered feedback on the next GET, not validation execution or stock/order mutation. Exact root cause of the missing shared view error bag is not yet independently reproduced with Laravel vendor in authoring environment.
- **Repair:** Product page reads both the shared Laravel errors and flashed request-session `ViewErrorBag` and has a product-slug-scoped generic failure notice when both are empty. Hidden origin field only influences presentation, never checkout authorization. The feature test now simulates a full browser Add-to-Cart POST and verifies visible feedback plus unchanged cart/order/product/variant stock.
- **Regression gates:** P10 real MySQL rerun pending user. P10/P11 static source contracts and P11 source-fingerprint regenerate/verify performed offline. Never claim production ready on a static-only result.


### B19 / P11 — Admin validation error messages use raw Blade output (2026-10-25)

- **Verified risk/reproduction:** four admin views rendered `{!! $error !!}`. A P11 source regression added before the fix reproduced a failure: `41/42 P11 static release safety contracts FAIL` (admin escaping check). This does not by itself prove exploitable XSS.
- **Root cause:** `{!! !!}` skips HTML escaping; validation text should be inert text, not trusted markup.
- **Scoped fix:** use `{{ $error }}` in `resources/views/admin/site-settings/index.blade.php`, `resources/views/admin/reports/create.blade.php`, `resources/views/admin/reports/edit.blade.php`, and `resources/views/admin/tracking-pixels/partials/form.blade.php`. Add per-template contract in P11 checker and refresh approved manifest.
- **Acceptance:** P11 offline gate 13/13 PASS with 42/42 release source contracts and fingerprint verification; runtime Blade/admin cross-browser regression remains a staging gate. No DB/schema, COD checkout, media or feature-flag edits.
