# P69 — Fresh-DB Option-wise Pricing + Shared Inventory (2026-11-04)

## User-authorized change
The owner explicitly opted to discard previous SKU combinations and start a disposable local database with `php artisan migrate:fresh --seed`. This is a new development-data contract, not permission to reset production, staging, or a shared DB. Existing client order histories must never be reset by unattended deployment.

## Source references and decision
Leatherket reference: independent product Color and Size choices, Option-wise (Piece Count) price; Stock page uses Product-level balance. Adapted to SFA with four explicit choice groups (Color, Size, Weight, Piece Count). The original SFA `product_variants`/`inventory_movements` infrastructure is retained only as **one hidden default inventory identity per product** for FK, ledger, deferred confirmation, and reservation compatibility. No per-combination SKU generation, no separate option stock, no price keyed by Color/Size/Weight.

## Persistence contracts
- `product_option_choices`: allowed independent product-option-value pivot.
- `product_piece_prices`: per-product Piece Count prices, server-authoritative.
- `cart_items.selected_options` and `selection_key`: different choices from one product can be distinct cart lines sharing a single stock balance.
- `order_items.variant_options`: immutable selected choices snapshot, price and inventory ID; existing order columns/FKs remain intact.
- Product/default-inventory physical stock + `inventory_movements`: single stock authority; `storefront_stock_state` (RESERVED / ALLOCATED / RELEASED) controls confirmation exactly once.

## Security invariants
1. Every submitted choice belongs to this active product and active choice group; Piece Count price is fetched from the DB, never trusted from client input.
2. A configured product has one shared default physical inventory identity; inactive/unknown/invalid choices fail validation.
3. Cart/checkout combine required quantity across distinct selections that share this identity; deferred reservation uses `lockForUpdate`, honours existing holds, and prevents oversell.
4. Campaign checkout requires campaign membership and an approved managed DB; no fallback to an unchecked variant price.
5. Admin Confirmed is the single audited physical allocation; deleting or editing Stock Purchases does not delete inventory ledger events.
6. Historical OrderSeeder rows are synthetic legacy fixtures, not newly placed managed orders. QA must create new orders before testing confirmation.
7. Preserved unrelated modules (courier, account, homepage builder, campaign builder) and did not auto-mutate production data.

## Modified/deleted UX
- Removed old Add New Variation, Build Multiple Variations, and per-SKU price forms from Admin. Retired manual/convert endpoints return HTTP 410; the internal default inventory pathway remains for stock accounting.
- Product Create option-wise checkbox routes directly to the new pricing setup. Product Edit offers that link.
- Current customer/storefront cards continue to show no inline variation controls; choices show only on product detail and Campaign order summary.
- Cart/order review shows selected choice labels, not internal SKU.
- Admin Stock Management displays one product-level balance and its audited movement history.

## Test notes and delivery limitations
PHP lint and Node syntax tests plus 54 independent UI DOM assertions and 31 source-contract assertions ran successfully in the artifact container. No app `vendor/`, configured local MySQL or headless browser is available here; migrations + order confirmation, cancellation/release, simultaneous checkout, and UI browser journey require a local acceptance pass before live use.

## Rollback
Restore code and DB backup; `migrate:fresh --seed` has no reversible DB rollback. Production schema changes require a separate review and explicit release.
