# Routes, Validation and Payload Contracts

**Proposed routes.** Add fixed public routes before the custom campaign catch-all. Preserve existing route names and client bookmarks. All state changes use POST/PATCH/DELETE with web-session CSRF protection. Inspect Laravel 13's configured middleware; do not insert an obsolete middleware class blindly.

## Public storefront

| Method/path | Proposed name | Behavior |
|---|---|---|
| GET / | home (existing) | Mode-aware root resolver |
| GET /shop | storefront.shop | Paginated catalog; query/filters validated |
| GET /product/{product:slug} | storefront.products.show | Active product details, variants, media |
| GET /category/{category:slug} | storefront.categories.show | Visible category/subcategory products |
| GET /cart | storefront.cart.index | Guest-owned cart HTML |
| GET /cart/summary | storefront.cart.summary | JSON snapshot, no-store |
| POST /cart/items | storefront.cart.items.store | Add/merge selected variant |
| PATCH /cart/items/{item} | storefront.cart.items.update | Replace absolute quantity with version check |
| DELETE /cart/items/{item} | storefront.cart.items.destroy | Remove owned line |
| GET /checkout | storefront.checkout.show | Form, quote + idempotency key |
| POST /checkout | storefront.checkout.store | Authoritative order transaction |

GET searches use /shop?q=...; no need for a new public API framework. Existing `/success/{token}` is reused. Campaign order endpoints remain, but delegate to the shared order/inventory core. Add appropriate abuse limits to both campaign and storefront submission; avoid a blanket low IP-only limit that blocks many legitimate users behind one network.

## Admin builder

All below: existing auth + active admin role + HomepagePolicy. Prefix inside existing `/admin` group; names start `admin.homepage.`.

| Method/path (after /admin) | Purpose |
|---|---|
| GET /homepage | Builder shell |
| GET /homepage/state | Current page/draft/published metadata |
| POST /homepage/draft | Start/return single shared draft |
| POST /homepage/sections | Add typed section |
| PATCH /homepage/sections/{section} | Update allowed fields/styles/source |
| DELETE /homepage/sections/{section} | Remove section from draft |
| PATCH /homepage/sections/reorder | Reorder all draft section IDs |
| PATCH /homepage/sections/{section}/products | Replace curated product selection/order |
| PATCH /homepage/sections/{section}/categories | Replace curated category selection/order |
| POST /homepage/sections/{section}/items | Add slide/review/image/campaign item |
| PATCH /homepage/items/{item} | Update typed item |
| DELETE /homepage/items/{item} | Remove draft item |
| PATCH /homepage/sections/{section}/items/reorder | Reorder all items of one section |
| PATCH /homepage/settings | Global theme, widgets and nav settings |
| GET /homepage/preview | Authorized draft preview |
| POST /homepage/publish | Activate current draft atomically |
| POST /homepage/revisions/{revision}/restore | Clone retained revision into a new draft |
| PATCH /homepage/mode | Controlled campaign/storefront mode |
| GET /homepage/catalog/products | Paginated selection search |
| GET /homepage/catalog/categories | Valid category/subcategory choices |

Register static routes before wildcard paths; constrain `{section}`, `{item}`, `{revision}` to numeric IDs where applicable so `reorder` cannot be interpreted as a model identifier.

Taxonomy/variant routes extend existing admin resources: `/products/{product}/variants`, `/product-options`, option values, and `/categories/reorder`. Preserve existing CRUD URLs. Catalog stock adjustments require a separate authorized adjustment endpoint with reason, not a builder mutation.

## Example: reorder sections

```json
{
  "expected_version": 7,
  "revision_id": 12,
  "section_ids": [44, 41, 43]
}
```

Reject unknown fields, duplicate IDs, missing siblings, any foreign revision ID, inactive admin and non-draft revision. Success:

```json
{
  "ok": true,
  "data": {
    "revision_id": 12,
    "version": 8,
    "section_ids": [44, 41, 43]
  }
}
```

## Example: section change

```json
{
  "expected_version": 8,
  "title": "Wallet Collection",
  "is_enabled": true,
  "show_desktop": true,
  "show_mobile": true,
  "source_type": "subcategory",
  "category_id": 9,
  "include_descendants": false,
  "product_sort": "manual",
  "product_limit": 8,
  "layout": {"mode": "grid", "mobile_columns": 2, "desktop_columns": 4},
  "styles": {"background": "#FFFFFF", "button_background": "#201446", "button_text": "#FFFFFF"}
}
```

IDs are illustrative, not existing data. Verify category_id=9 is really a visible subcategory before accepting. A PATCH updates supplied allowed fields; null resets an inheritable override. Unknown nested keys are rejected, not silently stored as arbitrary JSON.

## Example: cart update

```json
{
  "expected_version": 3,
  "product_id": 18,
  "product_variant_id": 67,
  "quantity": 2
}
```

Owner derives from the protected guest cookie. Product/variant relationship is verified. Add merges quantities and checks the aggregate; PATCH sets quantity explicitly. A stale version is 409. Client must fetch/reconcile current cart rather than reapply a stale increment repeatedly.

## Example: storefront checkout

```json
{
  "idempotency_key": "7e054c24-ad7a-4c84-a93d-dae5ee16fe87",
  "cart_version": 4,
  "customer_name": "Test Customer",
  "phone": "01700000000",
  "address": "Test delivery address",
  "shipping_charge_id": 2,
  "customer_note": null
}
```

The server derives items/prices from the owned cart. Do not add trusted `price` or `total` inputs. Global shipping_charge_id must belong to active `campaign_id=null` rows. The example phone is a fixture; never send real SMS/courier calls in tests.

Campaign adapter accepts explicit product_variant_id plus quantity (and product_id for compatibility), requires attached campaign product, and resolves legacy product-only payload only when the product is simple with exactly one valid default variant. Variable product without a chosen variant returns validation error; no guessed size/color.

## Error contract

| Status/code | Meaning and client behavior |
|---|---|
| 401/redirect | Auth required; retain unsaved UI only locally, never auto-publish |
| 403 | Authorized identity lacks resource permission |
| 404 | Missing or not visible product/category/resource |
| 409 STALE_VERSION | Reload current builder/cart state; manual reapply |
| 409 IDEMPOTENCY_CONFLICT | Same key with different payload; no duplicate order |
| 409 PRICE_CHANGED | Return fresh quote/cart state, require acknowledgement and retry |
| 422 VALIDATION_FAILED | Field errors including invalid selection/insufficient stock |
| 419 or configured request-forgery failure | Refresh authenticated session/token; do not remove CSRF middleware |
| 429 | Honor Retry-After and show clear retry message |
| 500 | Generic user-safe message + request ID; internal detail only in protected logs |

JSON example:

```json
{
  "ok": false,
  "code": "STALE_VERSION",
  "message": "This draft changed in another tab.",
  "current_version": 9
}
```

HTML form failures use validation errors/old inputs and no partial order. A redirect from inside a transaction is not a rollback: validate before side effects or throw a typed exception to roll back. JS responses must never contain raw exception traces, private media paths or credentials.

## Canonical route and UI source caveat (meeting PDF)

The previous AI meeting report describes the demonstrated site's `/product/{slug}` and `/category/{slug}` and `/shop`; the uploaded ZIP may not implement these URLs. This merged specification uses **singular candidate routes** in the table above for closer UX correspondence. During P00/P01, resolve existing actual routes, links and root custom-campaign slug collisions; never create silent 301 redirects or overwrite known campaign paths without an approved mapping. All CTA requests resolve explicit valid variant: if mandatory options are unselected, return selection guidance rather than an arbitrary default. Details page scroll-sticky CTA uses the same server-owned quote state as primary Order Now.


## P31: Approved public-root browse cutover (implementation, 2026-10-27)

The previous routes above are a historic proposal, not the currently shipped path set.
For the latest user-authorized local cutover, the actual routes are:

| Method/path | Route name | Behavior |
|---|---|---|
| GET `/` | `home` | Publicly read the one pinned, published storefront homepage revision; no admin session |
| GET `/storefront/home` | `storefront.home` | Compatibility alias to the same published homepage |
| GET `/storefront/shop` | `storefront.shop` | Search/sort active products; no QA opt-in |
| GET `/storefront/category/{slug}` | `storefront.category` | Active category and child products |
| GET `/storefront/product/{slug}` | `storefront.product` | Product details, options and stock display |
| GET `/storefront/help`, `/storefront/contact` | `storefront.help`, `storefront.contact` | Global FAQs and configured contacts |
| GET `/campaign/{campaign:slug}` | `campaign.show` | Canonical URL for standard **and custom** campaigns |
| POST `/campaign/{campaign:slug}/order` | `campaign.order.store` | Original validated campaign order handler (unchanged semantics) |
| GET `/{customRoute}` | `campaign.custom.show` | Historical custom campaign bookmark redirects to canonical URL |
| POST `/{customRoute}/order` | `campaign.custom.order.store` | Legacy campaign POST compatibility retained |
| GET/POST `/track-order` | `order.track`, `order.track.search` | Standalone page, no dependency on former campaign root anchor |

P26 isolated QA remains optional for account/cart/checkout on loopback 8088 only.
Main database commerce writes are NOT enabled by this browse cutover; independent P02/P03/P08 gates remain.
Admin builder mutation/preview authorization and immutable publication rules are unchanged.
No new migrations or data writes are part of P31. Browser and database integration not verified on this uploaded copy (user opted for manual testing).

### P38 - Independent Storefront FAQ and archive visibility

| Method/Path | Route name | Scope |
|---|---|---|
| GET `/storefront/help` | `storefront.help` | Reads `storefront_faqs` where `is_active=1` and `deleted_at IS NULL`; no campaign FAQ mixing |
| GET `/admin/storefront-faqs` | `admin.storefront-faqs.index` | Authenticated active admin list |
| GET `/admin/storefront-faqs/create`, POST `/admin/storefront-faqs` | `.create`, `.store` | Admin CSRF + validation; creates FAQ |
| GET `/admin/storefront-faqs/{faq}/edit`, PUT `/admin/storefront-faqs/{faq}` | `.edit`, `.update` | Admin edit; no campaign FAQ writes |
| DELETE `/admin/storefront-faqs/{faq}` | `.destroy` | Admin CSRF; soft-deletes a storefront FAQ |
| DELETE `/admin/storefront/home/history/{revision}` | `admin.storefront.home.history.hide` | Only `archived` history entries, optimistic version lock, retained audit and content |

Published and archived revision editing is **clone-to-draft**, using existing `admin.storefront.home.restore` POST route. Published revision and current draft can never be deleted by history action. Site branding uses existing Site Settings update route, with `storefront_logo`, `storefront_white_logo`, `storefront_favicon` media collections separate from the legacy campaign collections. Contact float links use only validated Site Settings values.

### P40 Admin product option value CRUD

| Method/path | Name | Contract |
|---|---|---|
| `POST /admin/products/options/{option}/values` | `admin.products.options.values.store` | Existing CSRF/admin/DB-gated option-value creation |
| `PATCH /admin/products/options/{option}/values/{value}` | `admin.products.options.values.update` | CSRF/admin, nested ownership, validated unique code/label/HEX/sort/status; no code edits if referenced or active-value disabling if used |
| `DELETE /admin/products/options/{option}/values/{value}` | `admin.products.options.values.destroy` | CSRF/admin, nested ownership; forbidden if any historical/current variant refers to value |

Existing product, campaign, catalog and checkout URLs are unchanged. Site Settings uses original `PUT /admin/site-settings/{siteSetting}` with four new optional social fields and managed flag, after the forward-only migration.

### P48 local-only admin variant release routes

- `POST /admin/products/{product}/variants/activate` (`admin.products.variants.activate`): admin + CSRF + verified local main DB or restricted isolated QA, transactional SKU/checkout readiness; redirect on success, session validation errors on failed preflight.
- `POST /admin/products/{product}/variants/pause` (`admin.products.variants.pause`): admin + CSRF + same guards, sets variable product inactive without stock or order mutation.

The public cart/checkout URLs and request payloads are unchanged. Do not treat these admin routes as a production release toggle.

## P49 implementation — Homepage Shipping Charges (2026-10-28)

These are **implemented fixed admin routes** and do not alter campaign URLs:

| Method / path | Route name | Scope / behavior |
|---|---|---|
| GET `/admin/homepage-shipping-charges` | `admin.homepage-shipping-charges.index` | Admin-only global (`campaign_id IS NULL`) list/creator |
| POST `/admin/homepage-shipping-charges` | `admin.homepage-shipping-charges.store` | Validate and create a global shipping area; owner id forced NULL |
| PATCH `/admin/homepage-shipping-charges/{charge}` | `admin.homepage-shipping-charges.update` | Update only a global row; a campaign row ID yields 404 |
| DELETE `/admin/homepage-shipping-charges/{charge}` | `admin.homepage-shipping-charges.destroy` | Soft-delete only a global row; a campaign row ID yields 404 |

All mutations require Laravel CSRF, authenticated active Admin, required area/BDT/status validation; duplicate active/non-deleted global area names are rejected. Last active global area cannot be removed/deactivated. No campaign edit form, campaign charge or order snapshot is modified by this page.

### P52 Storefront Account / Cart navigation contract
- Existing `PATCH /storefront/account/profile` requires an authenticated active storefront customer and CSRF. Multipart `_method=PATCH` validates optional profile fields and JPG/PNG/WebP photo <= 2 MB. Email stays read-only. Only the current authenticated account is updated. Only five personal/address columns are introduced in a gated, additive local migration. Profile photos reuse the existing Spatie `media` table and `StorefrontAccount::profile_photo` single-file collection; no photo-path column or new media migration.
- Existing `GET /storefront/checkout` without a purchasable owned cart **redirects** to `storefront.cart` with a visible status flash instead of exposing a generic 409 exception screen. Checkout POST validation remains fail-closed.
- Floating WhatsApp UI is an in-page progressive enhancement on validated Site Settings links; no stored contact URLs or routes are altered.
