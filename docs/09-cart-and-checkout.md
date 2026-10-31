# Guest Cart, Checkout and Campaign Compatibility

## Ownership and persistence

Create a cryptographically random 256-bit guest token server-side. Store it in a Laravel-protected HttpOnly, Secure-in-HTTPS, SameSite=Lax cookie; persist only its hash in carts/checkout_requests. Never send raw owner hashes in public API responses and never accept a client's requested cart owner.

Only the cookie owner's cart can be read/mutated. Cart item route binding must scope through that cart. An authenticated admin in the same browser is not implicitly the owner of every guest cart. Rotating a guest token must not expose another guest's cart.

Cart persists across pages/reloads. Proposed expiry 30 days; expired carts are cleared without any stock adjustment. No address/phone is saved in cart rows. Cleanup policies for order/idempotency records are separate; do not delete an idempotency record during its documented replay window.

## Variant and price behavior

Simple cards can add their hidden default variant. Variable cards lead to Choose Options or a proper selection dialog; never auto-add the first size. Merge identical variants into one line; different size/color variants remain distinct. Cart quantity controls obey latest server limits.

Server cart summary includes product/variant label, available stock, current unit price, quantity, line total, merchandise subtotal and lock_version. If a selected product is now inactive/deleted, keep a visible unavailable line so the shopper can remove it; do not silently order a replacement. Out-of-stock lines block checkout.

quoted_unit_price is a server-created acknowledgement snapshot. If price changes, the client shows the difference and acknowledges a fresh server quote. The final transaction recomputes anyway. Do not trust a browser boolean to bypass changed-price checks without checking the accepted quote/cart version.

## Shipping and COD

Existing ShippingCharge::resolvedForCampaign(null) returns global shipping rows; use it for storefront. Campaign checkout uses that exact campaign's rows and preserves its present no-runtime-fallback rule.

The current campaign code sets free shipping when **any** selected product is_free_delivery. Preserve existing campaign behavior unless explicitly approved otherwise. Proposed storefront default uses the same rule for compatibility, but this business decision is recorded as D05 and must be reviewed before release. No valid global shipping area means checkout is unavailable with a clear admin/setup message, not silently free shipping.

Use existing cash_on_delivery/cod_pending and processing state values. Do not invent card/mobile-payment integrations. Reuse success-token and order-tracking behavior while validating privacy/access separately.

## Idempotency

Generate one UUID for a checkout attempt and keep it stable over a network retry. Hash a canonical payload including channel, campaign context when relevant, normalized customer fields, line variants/quantities or owned cart version, and shipping choice. Do not hash client prices as authority.

Use unique `(owner_hash,idempotency_key)`. In transaction, create/lock the request, compare request_hash, and either create one order or replay its completed result. If concurrent insertion loses uniqueness, resolve/retry the existing row safely; do not produce an uncaught 500. Database failure rolls the entire attempt back. Reusing the key with changed contents is 409; a corrected new attempt can use a new key. The success redirect URL remains the same for a successful replay.

For campaign quick orders, add durable idempotency in addition to CSRF; transition the old consumed session token so failed validation does not strand the form. Preserve a grace-compatible form submission path while deployments roll over; verify old page behavior explicitly.

## One checkout transaction

Validate -> lock request/cart -> lock products/variants in deterministic order -> resolve stock and pricing -> resolve scoped shipping -> create customer/order/items -> allocate inventory+ledger -> complete request -> remove purchased cart lines -> commit -> schedule side effects -> return success.

No partial order when one item is invalid. No clearing the entire cart from a campaign page visit or campaign submission. Preserve other cart lines added after a stale checkout tab by requiring cart_version; do not clear a newer cart blindly.

## Failure cases that must be visible

- Cart empty: show shopping action, no order endpoint success.
- Selected combination inactive: show unavailable, do not swap size/color.
- Stock decreased: indicate currently available pieces and retain cart.
- Price changed: show updated quote; do not charge a higher hidden amount.
- Shipping removed: require a valid current shipping area.
- Network timeout after successful commit: retry returns the same order.
- Stale tab/cart version: 409 and refresh, no duplicate quantities.
- Invalid request/blocked customer: no stock decrement or customer-facing internal trace.

## Operational compatibility

Map existing block detection, customer identity normalization, assignment, status logs and success tokens into CheckoutService rather than delete them. Reports must correctly include storefront orders with campaign_id=null. External sync/courier endpoints keep their existing authenticated signatures and payload expectations. Customer login is a separate feature; no fake Account destination is acceptable.

## Meeting F-004/F-007/F-008/F-010 UI contract

Product cards: 25% cart / 75% primary Buy/Order action. Product sticky detail CTA: label is **Order Now** and uses chosen SKU/qty. Desktop checkout shows sticky summary on the right with totals and submit; mobile shows **one real `অর্ডার করুন` submit directly below customer information form**, ahead of long summary. Mini-cart is a side drawer opened by independent floating cart icon, not same as WhatsApp/Messenger/Email contact+. All UI values must be sourced from actual cart and server quote; do not create a client-only cart or fake count. See docs/22 and meeting MT24-MT30 tests.

## P08 implementation note (2026-10-25, staged)

See `P08-START-HERE.md` for actual code paths, opt-in flags, rollback-only test command, and currently unverified real-browser/concurrency gates. Cart IDs and owner hashes cannot be submitted from the public form. Storefront variation/order snapshots are allocated by `InventoryAllocator::allocateStorefrontOrder()` under the same P03 inventory ledger and transaction as the original managed orders.

## P49 Homepage shipping configuration (2026-10-28)

Homepage Shipping Charge admin CRUD manages existing `shipping_charges` rows with `campaign_id IS NULL`. It reuses `ShippingCharge::resolvedForCampaign(null)` already used by Storefront checkout, and the existing P32 global shipping readiness gate. Each delivery-area charge is computed server-side at checkout. Existing campaign-scoped charges (`campaign_id IS NOT NULL`) are unchanged. Historical checkout orders keep their stored delivery name and shipping amount. Configuring one active global area **satisfies only the shipping part** of readiness; other product/variant/homepage checks and local test access gates remain active. No new migration or automatic shipping rows/amounts are created.
