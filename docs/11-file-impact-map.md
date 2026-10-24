# File Impact Map

Paths marked **existing** were found in the target archive. Paths marked **new** are proposed implementation files. Before editing, Codex must read the current working copy and explain why each file changes. Do not overwrite unrelated methods in large controllers.

## Existing files

| Exact path | Why/check | Intended change |
|---|---|---|
| composer.json / composer.lock | Version/platform and scripts | Preserve lock; only approved minimal dependency changes |
| package.json / package-lock.json | Build/dependency contract | Add pinned SortableJS and browser-test dependency if approved |
| vite.config.js | Current entries include app.scss/app.js only | Add isolated builder/storefront JS+CSS entries; do not blend AdminLTE/Bootstrap globals |
| routes/user.php | Public fixed routes | Add shop/details/category/cart/checkout before catch-all |
| routes/web.php | Admin grouping and custom campaigns | Preserve route order and custom slug behavior |
| routes/admin.php | Existing auth/role groups | Add scoped builder/variant/option actions |
| routes/command.php | Authenticated GET side effects | Remove production HTTP migrate/seed, convert approved mutations to POST |
| config/campaign-routes.php | Reserved root paths | Add reserved storefront names after collision report |
| config/adminlte.php | Backend navigation | Add Homepage Builder and variant/option links with access controls |
| phpunit.xml | References nonexistent tests | Restore real test suite; add integration suite configuration safely |
| app/Http/Controllers/Frontend/HomeController.php | Current default campaign renderer | Mode resolver preserving existing branch |
| app/Http/Controllers/Admin/CampaignPageController.php | Public campaign shares landing Blade | Keep campaign view/data, add variant-aware contract without storefront replacement |
| app/Http/Controllers/Admin/CampaignOrderController.php | Membership/stock/idempotency gaps | Delegate secure campaign checkout to shared core |
| app/Http/Controllers/Admin/ProductController.php | Direct stock updates and catalog CRUD | FormRequests, options/variants, audited stock edits |
| app/Http/Controllers/Admin/CategoryController.php | Flat category CRUD | Parent checks, sibling sort and visibility |
| app/Http/Controllers/Admin/OrderController.php | Manual create/edit/status/bulk operations | Managed inventory deltas and exact monetary arithmetic |
| app/Services/OrderManagementService.php | Independent check/decrement | Adapter to shared inventory, no second decrement |
| app/Services/DynamicPricingService.php | Existing pricing rules | Preserve behavior, route arithmetic through exact money conversion |
| app/Services/ExternalOrderImportService.php | Imported nullable product items | Preserve external-managed policy; no guessed local variant mapping |
| app/Services/ExternalOrderPayloadBuilder.php | Outbound compatibility | Verify new order lines and existing money contract |
| app/Services/OrderAssignmentService.php | Assignment and transactions | Preserve behavior; test once-only assignment |
| app/Services/SteadfastStatusService.php | Courier status changes | Use managed lifecycle where needed; no blind auto-restock |
| app/Services/PathaoStatusService.php | Courier status changes | Same idempotent lifecycle rules |
| app/Models/Product.php | Existing relations/stock getters | Variant relations/effective price/availability; compatibility projection |
| app/Models/Category.php | No parent relation | Parent/children, visibility and cycle-safe scopes |
| app/Models/Order.php | Existing statuses/observers/source fields | inventory_version and compatibility; retain created_via values |
| app/Models/OrderItem.php | Existing line snapshots | Variant snapshots, allocation state and stable rendering |
| app/Models/SiteSetting.php | Already has contact links | Reuse validated fields; avoid duplicate contact columns |
| app/Models/MediaLibraryAsset.php | Existing reusable library assets | Reuse safe public media ownership |
| app/Http/Controllers/Admin/MediaManagementController.php | Replacement/trash/delete APIs | Respect references from retained homepage revisions |
| config/filesystems.php | Custom direct public storage | Verify paths; change only if required, no blind relocation |
| config/media-library.php | Custom paths + version_urls=false | Preserve paths; ensure revision-safe new media strategy |
| app/Http/Middleware/LogRequestLifecycle.php | Logs full paths and exception info | Redact sensitive token segments; do not log PII payloads |
| resources/views/frontend/pages/home.blade.php | 4282-line campaign template | Characterize first; limited safe variant/stock integration; not a shop rewrite |
| resources/views/admin/products/partials/form.blade.php | Existing product editing | Product type/variants/stock adjustment UI |
| resources/views/admin/campaigns/partials/form.blade.php | Campaign configuration | Only changes needed for explicit variant behavior/content; preserve existing fields |

Also inspect existing category, order invoice/report/export and media partials before edits. Discover the exact views called by their controllers; do not invent a filename and replace the wrong view.

## New PHP/model/service files

| Proposed path/group | Purpose |
|---|---|
| config/storefront.php | Caps, component registry, token/style keys, readiness flags |
| app/Models/ProductOption.php | Global size/color option definitions |
| app/Models/ProductOptionValue.php | Values and optional swatch |
| app/Models/ProductVariant.php | Purchasable SKU and stock |
| app/Models/InventoryMovement.php | Immutable ledger |
| app/Models/StorefrontPage.php | Mode, revision pointers and version |
| app/Models/StorefrontRevision.php | Draft/publication snapshot |
| app/Models/HomepageSection.php | Typed section configuration |
| app/Models/HomepageSectionItem.php | Slide/image/review/campaign item |
| app/Models/HomepageAuditEvent.php | Append-only edit/publish audit |
| app/Models/Cart.php and app/Models/CartItem.php | Guest cart persistence |
| app/Models/CheckoutRequest.php | Durable idempotency |
| app/Services/Storefront/InventoryService.php | One locked allocation/adjustment implementation |
| app/Services/Storefront/VariantResolver.php | Valid default/selected variant |
| app/Services/Storefront/MoneyCalculator.php | Exact BDT arithmetic/conversion |
| app/Services/Storefront/PricingService.php | Context-aware final prices |
| app/Services/Storefront/OrderLifecycleService.php | Managed edits/cancel/return events |
| app/Services/Storefront/CartService.php | Cart ownership/version/summary |
| app/Services/Storefront/CheckoutService.php | Transaction coordination |
| app/Services/Storefront/ProductCatalogService.php | Bounded public queries |
| app/Services/Homepage/HomepageDraftService.php | Clone/edit/reorder |
| app/Services/Homepage/HomepagePublishService.php | Validate/publish/restore |
| app/Services/Homepage/HomepageRenderer.php | Allowed components only |
| app/Services/Homepage/SectionStyleResolver.php | Safe inherited CSS tokens |
| app/Http/Controllers/Admin/HomepageBuilderController.php | Builder orchestration |
| app/Http/Controllers/Admin/ProductVariantController.php | Variant CRUD and validated selection |
| app/Http/Controllers/Admin/ProductOptionController.php | Size/color value administration |
| app/Http/Controllers/Admin/InventoryAdjustmentController.php | Audited adjustments |
| app/Http/Controllers/Frontend/StorefrontHomeController.php | Public published layout |
| app/Http/Controllers/Frontend/StorefrontProductController.php | Product detail page |
| app/Http/Controllers/Frontend/StorefrontCategoryController.php | Category landing |
| app/Http/Controllers/Frontend/StorefrontCatalogController.php | Shop/search |
| app/Http/Controllers/Frontend/CartController.php | Cart mutations and summary |
| app/Http/Controllers/Frontend/StorefrontCheckoutController.php | Storefront order form/submission |
| app/Policies/HomepagePolicy.php | Draft/edit/publish access |
| app/Policies/ProductVariantPolicy.php | Variant/stock administration |
| app/Http/Requests/Admin/SaveHomepageSectionRequest.php | Section fields/styles/source rules |
| app/Http/Requests/Admin/ReorderHomepageRequest.php | Complete scoped unique ID sets + version |
| app/Http/Requests/Admin/PublishHomepageRequest.php | Draft/version checks |
| app/Http/Requests/Admin/SaveProductVariantRequest.php | Options/price/stock rules |
| app/Http/Requests/Storefront/MutateCartRequest.php | Ownership-independent structural validation |
| app/Http/Requests/Storefront/CheckoutRequest.php | Customer/shipping/idempotency validation |
| app/Console/Commands/BackfillDefaultVariants.php | Idempotent dry-run/apply backfill |
| app/Console/Commands/AuditStorefrontReadiness.php | Slug, stock, reference and publication readiness |

Naming detail: request `App\Http\Requests\Storefront\CheckoutRequest` and model `App\Models\CheckoutRequest` must use explicit aliases where imported together, or name the request `SubmitCheckoutRequest` and update all contracts consistently.

## New frontend/admin assets

- `resources/views/admin/homepage-builder/index.blade.php` and `partials/section-settings.blade.php`, `partials/design-settings.blade.php`, `partials/item-list.blade.php`.
- `resources/views/frontend/storefront/layout.blade.php`, `home.blade.php`, `product.blade.php`, `category.blade.php`, `shop.blade.php`, `cart.blade.php`, `checkout.blade.php`.
- `resources/views/frontend/storefront/sections/hero-slider.blade.php`, `category-grid.blade.php`, `product-list.blade.php`, `image-text.blade.php`, `reviews.blade.php`, `campaign-cards.blade.php`, `faq.blade.php`.
- `resources/views/frontend/storefront/partials/product-card.blade.php`, `variant-selector.blade.php`, `floating-cart.blade.php`, `contact-menu.blade.php`, `mobile-nav.blade.php`.
- `resources/js/admin/homepage-builder.js`, `resources/js/storefront/cart.js`, `resources/js/storefront/variants.js`, `resources/js/storefront/widgets.js`.
- `resources/css/homepage-builder.css` and `resources/css/storefront.css` (explicit new Vite entries).

## New tests and migrations

Migration names are timestamped according to docs/04-migrations-and-backfill.md. Test files are enumerated in docs/12-testing-and-acceptance.md. Do not add empty tests to imply coverage. Every new/changed path must be included in the phase handover with its reason and actual verification.

## Meeting-specific changes (source PDF F-001 to F-011)

Exact file existence/name must be rechecked in the actual working ZIP before editing. Scope: `routes/user.php` and `config/campaign-routes.php` for candidate singular catalog routes; `app/Models/Category.php`/existing CategoryController and views for nested All Categories; product options and variants model/admin form for size/color/**weight/piece_count**; separate new storefront Blade product cards/details/checkout for 4/2 grids, 25/75 buttons, image/description placement, sticky Order Now, mobile/nav/cart drawer; existing admin homepage builder controller/views for per-section background/button palettes and drag reorder; order and inventory services for authoritative variant availability. Consult docs/20, docs/22, docs/24 for detailed requirements and phased file gates. Do not replace the existing campaign landing Blade.
