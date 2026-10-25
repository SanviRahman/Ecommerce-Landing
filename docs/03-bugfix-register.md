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
