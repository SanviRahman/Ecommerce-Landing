# Products, Variants and Inventory

Related: R02/R03/R06/R14/R16; B01-B04/B09/B13. Schema: database-schema.md.

## Product administration

Keep existing product details, category, brand, pricing, description, thumbnail/gallery and flags. Add a Product Type control (simple/variable) and Variants tab.

- Simple product: exactly one purchasable default SKU; size/color selectors are absent. Its existing stock input becomes an audited adjustment to the default variant, not a direct overwrite of products.stock.
- Variable product: select size/color options and active values, generate combinations with zero stock, edit SKU/price override/available pieces/status, optionally set a preferred display combination. Never generate extra inventory.
- New combinations require a unique canonical signature. Do not reuse a deleted SKU/signature to hide conflicting history.
- Changing an option label does not rewrite past orders. Deleting a used option/value/variant is blocked; deactivate instead.
- Verify purchase_price/old_price/new_price compatibility; a variant's null override inherits base price. A submitted zero does not mean 'inherit'.

All variant operations lock the product first. Bounds: 200 variants/product, quantity <=99 per line, total distinct cart lines <=50 by proposed defaults in config/storefront.php. Admin inventory counts need a larger explicit validated bound, not the cart quantity limit.

## Price resolution

**Storefront:** active variant price_override if not null, else product.new_price.
**Campaign:** product must be attached to the campaign; a positive campaign_product.campaign_price is the final per-unit campaign price for every allowed variant in v1. If no override, use effective variant price. Never add the variant override on top of a fixed campaign price.

Per-variant campaign prices are NOT implicitly supported by this rule; they require an explicit schema/ADR if the client needs them. Old-price badges use a valid comparison price greater than the final price; do not show negative discounts or invent a percentage.

Prices are reloaded on checkout. Client-submitted price, discount, shipping total or availability cannot authorize an order. Manual discounts remain permission-controlled and use exact arithmetic. MoneyCalculator converts whole-BDT product prices to minor units internally, then writes order decimals as BDT strings.

## Public availability

| Surface | Display/behavior |
|---|---|
| Product card | Sum of active non-deleted variant available stock; label 'total available' for variable products |
| Details before selection | Total available plus prompt to choose options; no false claim that every combination has this count |
| Details after selection | Exact selected variant stock and price; disable invalid/zero-stock combinations |
| Cart | Selected variant label, quantity, current available count and any changed-price/out-of-stock warning |
| Checkout | Authoritative server check under DB locks, even when the UI displayed stock seconds earlier |

A cart is not a stock reservation. Adding to cart never deducts stock. Expiring/deleting a cart never returns stock. Stock can change while a shopper browses; show a useful correction message instead of trusting stale HTML.

## Inventory transaction algorithm

For an accepted local order:

1. Validate request structure, guest/admin authorization, active product, correct variant ownership, campaign membership, normalized phone and shipping scope.
2. Canonicalize duplicate entries by variant ID; revalidate aggregate quantity caps. Reject the entire invalid request, not a silently reduced subset.
3. Enter transaction. Claim/lock checkout idempotency record (or existing order for an edit). Lock cart when applicable. Lock all product IDs ascending, then all variant IDs ascending. Every inventory writer uses this same relative lock order.
4. Read current price/status and stock from locked rows. Validate quantity <= available. Use nonnegative checks and guarded updates, not unchecked decrement.
5. Calculate exact line/order amounts server-side. Create order using existing statuses/payment constants; create immutable line snapshots.
6. Allocate stock and append one unique movement per affected line. Set line.inventory_allocated_quantity and order.inventory_version=1. Refresh products.stock compatibility totals in the same transaction.
7. Complete checkout_request with order_id, consume only successfully ordered cart lines and commit. A failure rolls back stock, order, ledger, request completion and cart mutation together.
8. Trigger courier/sync/email jobs only after commit, with their own deduplication. Return the existing success-token route.

Deadlock retry uses Laravel's transaction retry support with a bounded attempt count; event/idempotency identities stay stable across retries. Never catch a DB failure and return success. Use the production engine for concurrency tests; SQLite alone is insufficient to prove this behavior.

## Status and editing matrix

| Action on a managed order | Inventory effect |
|---|---|
| New accepted processing/COD order | Allocate once |
| Pending/processing/confirmed/complete_invoice transitions | No second deduction |
| Shipped/delivered transition | No second deduction |
| Cancel before dispatch | Release currently allocated quantity once |
| Repeat cancellation/webhook retry | No additional release |
| Reactivate previously released order | Check/allocate again with a new persisted transition event |
| Cancel after shipment/courier_cancelled | Do not auto-credit warehouse; require a received-return decision |
| Received, inspected, restockable return | Credit approved quantity once, bounded by previously allocated unreturned quantity |
| Damaged/lost return | No automatic sellable-stock credit |
| Change quantity on existing line | Apply difference from previously allocated quantity |
| Change variant | Release old allocation and allocate new variant atomically under sorted locks |
| Remove line | Release permitted allocation, then soft-delete line; preserve ledger |
| Delete/trash order | No implicit inventory effect; authorized lifecycle action must precede destructive cleanup |
| Legacy order inventory_version=0 | No inferred release/deduction; reconcile explicitly |

Use existing status spellings (`cancelled` and `canceled` both exist) and courier state mappings. A status string alone does not prove the goods reached the warehouse. Transition keys must use a persisted event UUID, not `now()` generated separately on each retry.

## Other ingress paths

- CampaignOrderController: adapter to the shared core, retaining campaign URL, shipping and block checks.
- OrderController::store/update and bulk conversion: resolve variants and use allocation differences; no direct uncontrolled stock input.
- OrderManagementService: replace its old independent decrement with the shared InventoryService to prevent double deduction.
- ExternalOrderImportService currently creates some items without local product_id. Default: external/unmapped orders remain inventory_version=0 and do not consume local inventory. A separately approved SKU mapping policy is required before treating imports as local sales.
- Courier webhook/status services: use lifecycle transitions where relevant; repeated external notifications must be idempotent and must not automatically restock shipped returns.

## Reconciliation and maintenance

Provide admin inventory adjustment with reason, before/after values and actor. Do not let a stale product edit overwrite recently sold stock; validate version or lock/read current value and apply a delta. Provide a read-only reconciliation report comparing variant stock/projection and ledger continuity. Repair requires an explicit audited adjustment, never a silent mass overwrite.

Existing sold_quantity is a historical field with inconsistent prior writers. Preserve its baseline. Do not silently reinterpret it as delivered sales; keep any new analytics definition separate until approved.

## Meeting F-006 addendum: weight/piece options

Store color, size, weight and piece_count labels as product option values associated with exact variants. The example price 1kg=1499 and 2kg=1800 is configured per-variant price, **not** an arithmetic rule. A cart quantity of 3 for a single 2kg variant means buying three 2kg sellable SKUs at that SKU's price, not three pieces from an unrelated base SKU. Distinguish `piece_count` option from cart count and inventory physical-unit meaning. Inventory owner must approve whether multi-piece pack depletion requires a linked base-unit conversion (G03); until then keep distinct stock identities and do not guess conversion. See docs/25 tests MT17-20, MT43.
