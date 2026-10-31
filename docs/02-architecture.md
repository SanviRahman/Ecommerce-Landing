# Architecture and Request Flows

## Baseline to preserve

`routes/user.php` registers `/`, `/campaign/{campaign:slug}`, campaign order submit, success and tracking. `routes/web.php` appends one-segment custom campaigns after fixed routes. Admin auth is under `/admin` with custom role middleware; backend authorization currently uses `users.role` and active status. Do not assume that installing Spatie Permission means all existing authorization uses its role relations.

## Public routing

```text
GET / -> HomeController mode resolver
  mode=campaign -> existing campaign-home behavior -> frontend.pages.home
  mode=storefront -> StorefrontHomeController -> PublishedHomepageRenderer
                     -> frontend.storefront.home
GET /campaign/{slug} or /{customRoute} -> existing CampaignPageController
GET /products/{slug} -> StorefrontProductController
GET /categories/{slug} -> StorefrontCategoryController
GET /shop -> StorefrontCatalogController
GET /cart -> CartController
POST /checkout -> StorefrontCheckoutController -> CheckoutService
```

No published revision and mode=campaign preserves today's behavior. A malformed/missing published revision in storefront mode must log a structured error and return a safe fallback, not expose a draft. The operational feature flag can return `/` to campaign mode without rolling back orders/inventory.

## Services (proposed)

| Service | Responsibility |
|---|---|
| ProductCatalogService | Active/visible product & category queries; eager media/variants |
| VariantResolver | Valid combination, default/simple-product compatibility |
| MoneyCalculator | Exact decimal/minor-unit arithmetic with explicit BDT conversion |
| PricingService | Storefront/variant/campaign price, authorized admin discount logic |
| InventoryService | Lock, check, adjust and ledger variant stock once |
| OrderLifecycleService | New managed-order transitions, edits, cancellations and returns |
| CartService | Guest ownership, versioning, normalized quantities and repricing |
| CheckoutService | Idempotency, shipping scope, final validation, order/item snapshots |
| HomepageDraftService | Revision cloning and version-checked draft mutations |
| HomepagePublishService | Validation, immutable snapshot activation and audit |
| HomepageRenderer | Resolve only allowed component types and current public data |
| SectionStyleResolver | Typed style inheritance; scoped CSS tokens |

Controllers authorize/validate/delegate. Use FormRequests for mutation contracts, policies/gates for resource access, Eloquent relationships and bound queries. Existing services become adapters or are reused; do not keep two competing inventory/price implementations.

## Publication model

A page has at most one draft pointer and one published pointer. Draft revision rows own all section/item/selection rows. Save operations update draft rows only. Publish switches the published pointer under the page-row lock, archives the previous revision and clears the draft pointer. Published/archived content rows are immutable.

Starting the next draft clones published rows with new DB IDs, preserving logical section/item keys. Restoring an old revision clones it into a new draft; it never edits history. Global theme/widget settings are inside the revision. Catalog prices/stock and global site contact records remain live references; restoring a layout does not rewind commerce data or contact records.

## Inventory boundary

Use one default variant for each existing simple product. `product_variants.stock` is authoritative. `products.stock` is a derived compatibility projection, updated under the same product/variant locks by InventoryService; no controller writes it directly after cutover. Maintain a deterministic lock order: idempotency/cart or existing-order root, then product IDs ascending, then variant IDs ascending. Apply the same ordering in every writer.

Do not send courier/marketing/sync HTTP calls inside stock-lock transactions. Schedule approved side effects after commit or through a durable outbox/job with retry identity. Existing observers must be inspected so a newly created storefront order does not dispatch twice.

## Scoped cart versus campaign checkout

V1 cart contains storefront products only. Campaign landing pages keep quick ordering and campaign-specific prices/shipping. A campaign order must not clear or adopt the guest storefront cart. Both flow adapters call the same inventory core. Adding campaign deals to a shared mixed cart is a separate change because price/shipping context must be explicitly modeled.

## Data and media

Reuse Product descriptions/media collections, Category media, Review/Faq content, MediaLibraryAsset uploads and SiteSetting contact fields. Published layout media references must survive edit/replace/delete actions elsewhere. Builder replacement creates a new media record; old referenced media is retained until no published/retained revision uses it.

## Caching and performance

Cache immutable layout by page/revision/schema version, not entire personalized HTML. Never cache guest cart, CSRF, tracking results or checkout pages in shared caches. Catalog/stock cache invalidation is separate from layout; checkout always reads authoritative rows under locks. Use eager loading and bounded product_limit; no per-product DB query in Blade. Preview is private/no-store/noindex and uses the same render pipeline as public layout.

## Compatibility constraints

Keep existing `created_via` values and frontend reporting compatibility; distinguish storefront from campaign by frontend origin plus nullable campaign_id and checkout records. Preserve product numeric IDs and slugs. Keep old order snapshots unchanged. No new customer auth tables/flows solely for a decorative Account icon.
