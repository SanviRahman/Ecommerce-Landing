# Migration, Backfill and Cutover Plan

## Safety prerequisites

Use a development clone and a separate database. Confirm actual server DB driver/version and schema from an approved read-only connection; the ZIP's SQLite file is not the deployed commerce database. Back up DB + public media together, verify restore procedures, and prevent running checkout during the inventory cutover window. No automatic production seed or destructive reset.

## Migration order

| Sequence | New migration responsibility | Preconditions |
|---|---|---|
| M01 | Add category parent_id/sort_order; product has_variants/inventory_version | Existing tables present; no FK type mismatch |
| M02 | Create product_options and product_option_values | None beyond products/users |
| M03 | Create product_option_assignments, product_variants, product_variant_values | M01-M02; composite value/option consistency |
| M04 | Add order inventory_version and line variant snapshots/allocation quantity | M03, existing orders/order_items |
| M05 | Create inventory_movements and checkout_requests | M03-M04 |
| M06 | Widen monetary order/item columns to DECIMAL(14,2) preserving BDT values | Read actual types, check range and DB support, restore rehearsal |
| M07 | Create storefront_pages with nullable pointers but initially no pointer FKs | users exists |
| M08 | Create storefront_revisions, then add page revision-pointer FKs | M07; avoid circular creation error |
| M09 | Create homepage_sections and section selection/item tables | M08, existing category/product/review/campaign/media |
| M10 | Create homepage_audit_events | M08 |
| M11 | Create carts/cart_items | M03 |

Timestamp each migration in implementation order after existing migrations. Implement non-destructive `up` operations. `down` may be used only for a clean disposable test environment; production rollback is application/feature rollback, not dropping new order/ledger history. Monetary narrowing is forbidden while fractional values exist.

## Source-aware baseline tests

Run original migrations on a fresh disposable database first. Existing migration source includes schema repair/backfill logic; test the **whole chain**, not only new tables. Also test an upgraded, anonymized schema with representative rows. Do not paper over a failing old migration by changing its history in a deployed application; add a repair migration with an explained invariant.

## Backfill command (to be implemented)

Proposed command: `storefront:backfill-default-variants --dry-run`, with explicit `--apply` mode and chunking. These commands do not exist in the supplied ZIP; create and test them before use.

For each product under a product-row lock:

1. Skip inventory_version=1. For version=0, verify there is no unexpected preexisting variant mapping; report discrepancies instead of guessing.
2. Create one variant with signature `default`, unique deterministic SKU (start from existing product_code; collision-safe suffix), no option values and nullable price override.
3. Transfer the **single** legacy stock balance to that variant. Record one idempotent opening ledger movement with a key based on product ID and migration version.
4. Do not create one full-stock row per size/color; no variants can be inferred from free-text weight_size.
5. Mark product inventory_version=1 only after its variant, ledger and compatibility stock agree in the same transaction.
6. Preserve all product/order/campaign IDs, slugs, prices, sold_quantity, media and history.

Dry-run output reports counts, totals and conflicts without customer/secret data. Re-running applied backfill changes nothing. Save before/after aggregates and compare per-product stock, not only grand totals.

## Existing orders and stock truth

Source order paths have inconsistent deductions, so uploaded stock cannot automatically be assumed to equal physical warehouse availability. Obtain owner-approved stock reconciliation before public stock enforcement is enabled.

Historical orders stay inventory_version=0. Do not subtract all open orders from stock during backfill or automatically credit cancelled historical lines. Managed order editing/cancellation is blocked until that legacy order is explicitly reconciled, or uses an approved manual adjustment with reason and audit. Historical size/color choices must remain unknown unless evidenced.

## Variable-product conversion

For a product converting from default to explicit variants: temporarily disable purchase, lock product and variants, create desired combinations with zero initial stock, transfer an owner-specified allocation whose sum matches the old pool, record transfer events, deactivate the default stock pool, and enable only after reconciliation. Preserve the old default variant for historical order references. A return for an old default SKU may require manual mapping; do not credit all variants.

## Homepage initialization

Create `home` page in `campaign` mode. Do not populate fake brands/products/reviews. Create an editable draft with valid selected existing resources or clearly empty blocks. Existing campaign images/content may be referenced only through approved safe media ownership rules. No automatically published homepage.

## Route collision preflight

List campaigns with custom route in the new reserved set: `shop`, `product`, `products`, `category`, `categories`, `cart`, `checkout`, `account`, `wishlist`, `search`, plus required builder preview roots if any. Preserve collisions through an approved alias/redirect migration plan; do not silently overwrite or change advertising URLs. Only add usable features to public navigation; reserving a path does not mean implementing an account/wishlist.

## Release gate

Enable storefront only when: old migrations+new migrations pass on staging, variant counts/stock reconcile, all consuming paths use InventoryService or are explicitly disabled, money regression passes, routes do not collide, published revision is valid, customer flows and rollback have been tested. Meeting PDF is now included but original audio unverified; business decisions (variant pricing, stock units, shipping) remain release gates and must not be invented.
