# P61 production bugfix release - October 31, 2026

## Scope

This release was prepared against `website(20261031-045824).zip` and preserves the other application files. It is a SOURCE-LEVEL PATCH. There was no live MySQL connection or Laravel Composer vendor directory in the analysis environment. **Do not deploy directly to an active live checkout without staging validation, a full database snapshot, and rollback readiness.**

1. **Complete-order stock deduction**: On P58-approved production storefront checkout only, reserve the SKU's sellable quantity without lowering physical stock (`orders.storefront_stock_state = 1`, `inventory_version = 0`). `confirmed` (the Complete Orders card) or `complete_invoice` atomically deducts physical variant stock and writes the same P03 inventory movement ledger exactly once. Cancel/fake/stock-out/courier-cancelled before completion releases the hold without a physical stock change. Existing legacy/P26/P32/campaign/manual/external orders do not get replayed or reallocated by this migration. Pending hold also lowers *sellable availability*, preventing multiple storefront customers from buying the same units before admin confirmation. Already allocated pre-existing orders are NOT deducted twice.
2. **Homepage Builder in production**: Allow active-admin-only builder, validated HTTPS canonical host, configured actual MySQL schema; still independently authorize/admin-gate draft, preview and publishing. Preview without any draft/published revision redirects to the editor with an instruction instead of 404. The public homepage only shows published revision.
3. **Product variants**: Color/size/other options are accessible wrapped flex radio chips (with swatch where configured), with a hidden exact-variant SKU id derived from the option matrix. No arbitrary variant default for multiple stock choices.
4. **Delivery area**: Replace checkout dropdown with labelled radio cards. Existing authoritative server-side shipping/checkout validation unchanged; all estimated totals listen to radio changes.
5. **Campaign reviews**: Campaign query and view accept only that campaign's own linked reviews. Homepage/global testimonials no longer leak onto campaigns.
6. **Offcanvas menu**: Desktop and mobile hamburger open the existing focus-managed offcanvas drawer, with aria-expanded state and focus restoration.
7. **Homepage quick actions**: Product image/title link to details; Add to Cart submits AJAX and opens the cart drawer; Buy Now adds the selected SKU and redirects to existing secure checkout form. Multiple-variant cards require an explicit valid SKU chip choice. No unknown SKU is auto-picked.

## Changed source files

### New
- `app/Support/ProductionHomepageAccess.php`
- `app/Services/Inventory/StorefrontDeferredStock.php`
- `database/migrations/2026_10_31_060001_add_storefront_stock_state_to_orders.php`
- `public/frontend/css/storefront-p61.css`
- `tests/offline/test_p61_production_bugfix.py`

### Edited
- `app/Providers/AppServiceProvider.php`
- `app/Support/P58ProductionStorefrontAccess.php`
- `app/Services/Storefront/HomepageBuilder.php`
- `app/Http/Controllers/Admin/HomepageBuilderController.php`
- `app/Http/Controllers/Admin/CampaignPageController.php`
- `app/Http/Controllers/Admin/OrderController.php`
- `app/Services/Inventory/ManagedOrderLifecycle.php`
- `app/Services/Cart/GuestCartService.php`
- `app/Services/Checkout/StorefrontCheckout.php`
- `app/Services/Storefront/PublicCatalog.php`
- `resources/views/frontend/pages/home.blade.php`
- `resources/views/storefront/pages/home.blade.php`
- `resources/views/storefront/pages/product.blade.php`
- `resources/views/storefront/pages/checkout.blade.php`
- `resources/views/storefront/partials/home-product-card.blade.php`
- `resources/views/storefront/partials/product-card.blade.php`
- `resources/views/storefront/partials/navigation.blade.php`
- `resources/views/storefront/layouts/catalog.blade.php`
- `public/frontend/js/storefront-catalog.js`
- `public/frontend/js/storefront-next.js`
- `tests/offline/p48_variant_picker_smoke.cjs`
- `tests/offline/test_p54_contracts.py` (updated delivery area test expectation only)

## Secure deployment sequence (staging first)

1. Schedule a quiet maintenance window; take a verified database backup and copy the existing source plus persistent upload directory.
2. Upload the changed/new application files over the SAME deployed Laravel project; do not delete unaffected files. **This ZIP intentionally excludes `.env` and logs. Preserve the server's existing `.env` and do not replace `APP_KEY`.** Do not overwrite deployment-specific media or writable storage blindly.
3. Ensure the existing P02 product variant/order inventory/ledger migrations are already installed (check `php artisan migrate:status` and real schema). If they are not, DO NOT force all historical guarded migrations onto production. Seek a reviewed migration plan.
4. Check environment and canonical host: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-live-domain`, `DB_CONNECTION=mysql`. Admin Homepage Builder requires HTTPS and the DB schema to match the configured MySQL connection. Confirm the P58 storefront release settings are approved for that exact host/database if online checkout is desired:

   ```dotenv
   P58_PRODUCTION_STOREFRONT_ENABLED=true
   P58_PRODUCTION_STOREFRONT_HOST=your-live-domain
   P58_PRODUCTION_STOREFRONT_DATABASE=your_existing_database_name
   ```

   This is NOT permission to enable an untested production checkout: enable P58 only after staging checks and release approval. A separate homepage-builder flag is NOT needed in valid production mode.
5. Run **only the new migration** with the real production backup ready:

   ```sh
   php artisan migrate --path=database/migrations/2026_10_31_060001_add_storefront_stock_state_to_orders.php --force
   ```

6. Rebuild the application caches after deployment (do not regenerate the key):

   ```sh
   php artisan optimize:clear
   php artisan route:list --path=admin/storefront
   php artisan migrate:status
   ```

   Optionally run `php artisan config:cache` and `php artisan view:cache` after confirming the application loads and environment variables are correct.
7. Hard-refresh the browser/clear CDN CSS/JS cache. Version `p61` is already on the new CSS asset.

**Never use `php artisan migrate:fresh`, `db:wipe`, blanket migration rollback or `php artisan key:generate` on this populated production instance. `composer setup` invokes database commands and key regeneration; do not run it on an existing site.**

## Production smoke-test checklist

- Admin with active admin role sees Homepage Builder; employee cannot. Click Preview with no draft: friendly editor redirect. Create draft, preview, publish, view `/`. Verify published section ordering, images and CTA links; test `?revision=` only with own homepage revision.
- Campaign page shows ONLY its own reviews; blank review section stays hidden. Other campaign FAQ/product/order flow untouched.
- Product page: select size/color radio combinations, verify SKU/price/stock and that sold-out SKU disables purchase. On mobile, chips wrap and remain keyboard accessible.
- Checkout: switch delivery area radios and confirm totals; submit with missing fields; verify server recalculates shipping and rejects invalid area.
- Hamburger opens on mobile and desktop; backdrop click, Escape key, focus trap, and focus restore work; desktop navigation otherwise unchanged.
- Homepage card: click image/title opens details; Add to Cart opens mini cart; Buy Now opens checkout form; product with several variants requires choosing a SKU; no out-of-stock SKU is offered.
- **Inventory staging scenario**: With SKU physical stock 20, place an order for 3. Verify stock remains physically 20, pending reservation state=1, sellable availability 17. Place another order for 4: physical 20, availability 13. Change the first order to `confirmed`: physical stock 17, availability remains 13, state=3, exactly one allocation ledger row per item. Repeat `confirmed`: still physical 17, no duplicate ledger. Change second order to `cancelled`: physical 17, availability 17, state=2, no allocation ledger. Retry checkout with same checkout key: no duplicate order. Try order 18 while 17 available: reject without creating an order. Try direct `shipped` from state=1: reject. Confirmed -> cancelled before courier dispatch: restore once; no duplicate restock on repeat cancellation.
- Verify other existing order sources (campaign/manual/bulk/external) are NOT unexpectedly reallocated, and courier APIs/track-order still work.
- Verify blocked/deleted orders and bulk trash cannot delete an outstanding reservation or allocated order.
- Check MySQL error log and `storage/logs/laravel.log` on staging (never share secrets/PII).

## Test evidence and limitations

- PHP syntax: 290 files under application, migrations, routes and feature tests passed.
- Node syntax: 20 frontend JavaScript / offline `.cjs` files passed.
- Variant picker DOM smoke: 4/4 scenarios passed.
- P61 source contracts: 10/10 pass.
- Existing P59 isolated authorization: 5/5 pass.
- Other baseline offline contracts unchanged: P52 2 old failures, P54 1 old failure, P59 CSS test 1 old failure; these failures also reproduce identically in the original ZIP before the patch.
- PHPUnit/Artisan/app boot, real Laravel Blade rendering, real MySQL concurrent checkout, browser visual/accessibility and production deployment were NOT executed in this environment due to missing Composer dependencies and a usable database. Consequently this release is NOT claimed to be verified error-free in production.
