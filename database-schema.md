# Database Schema - Proposed Target v1

**P02 implementation note (2026-10-24):** M01-M11 source migrations now exist, guarded to a disposable local test DB. They have NOT been executed or MySQL-verified here. The data model remains a proposed target until an isolated migration test passes.

**This is a design, not an applied migration.** Existing tables/columns below were inspected in source migrations; verify live schema before deployment. New fields/tables must be created only by new migrations. Do not edit historical migrations or import Leatherket's database.

## Conventions and financial units

- Existing IDs remain unsigned BIGINT; new business tables use the same type unless a UUID is explicitly specified.
- Standard `timestamps` means `created_at` and `updated_at`; existing soft deletes remain.
- New money overrides on variants follow existing product price semantics: **integer whole BDT**, not poisha. Never reinterpret existing 999 as 9.99.
- Order monetary columns are widened to DECIMAL(14,2) to preserve existing integer BDT and safely store already-supported fractional manual discounts. Decimal columns represent BDT; calculations use integer minor units through MoneyCalculator, never binary floats. Conversion of a product price is `whole_bdt * 100`; storage conversion is a formatted decimal string divided by 100.
- Do not silently change external courier/order-sync money contracts. Add boundary tests and reject unsupported fractional values rather than truncate.
- JSON stores typed layout settings/snapshots, not arbitrary code, SQL, model names or serialized PHP.
- Application limits below are proposed safe defaults, not claimed meeting decisions.

## Existing tables: targeted changes

### categories (existing)

Keep `id,name,slug,is_front_view,status,timestamps,deleted_at`.

| New column | Type/default | Constraint/purpose |
|---|---|---|
| parent_id | nullable BIGINT | Self-FK, RESTRICT physical delete |
| sort_order | unsigned INT, 0 | Taxonomy order among siblings |

Index `(parent_id,status,sort_order,id)`. Slug remains globally unique. V1 allows root + one subcategory level. Lock involved rows and reject cycles/self-parenting; descendants of inactive/deleted parents are not publicly visible. Do not rely only on FK checks for hierarchy validity. Reparenting does not move product rows implicitly.

### products (existing)

Keep existing category_id, brand_id, prices, stock, descriptions, flags, metadata and media relations.

| New column | Type/default | Purpose |
|---|---|---|
| has_variants | BOOLEAN false | Whether shopper must choose explicit options |
| inventory_version | unsigned SMALLINT 0 | 0=legacy/unreconciled; 1=variant-backed |

After controlled cutover, `products.stock` is the derived sum of non-deleted variant stock, including disabled variants (physical sellable pool projection); public availability sums **active purchasable variants only**. This distinction prevents hidden stock from being sold while retaining inventory totals. Writes to `products.stock` outside InventoryService/approved backfill are prohibited. Simple-product editor adjusts the default variant through InventoryService.

### orders (existing)

Reuse `campaign_id` (already nullable), `created_via`, customer identity, existing status/payment constants, assignment and integration fields. Do NOT add another `source_type` column.

| New/change | Type/default | Purpose |
|---|---|---|
| inventory_version | unsigned SMALLINT 0 | 0=legacy/external-unmanaged; 1=locally inventory-managed |
| sub_total | widen to DECIMAL(14,2), 0.00 | Preserve BDT numeric values |
| shipping_charge | widen to DECIMAL(14,2), 0.00 | Preserve BDT numeric values |
| cod_charge | widen to DECIMAL(14,2), 0.00 | Preserve BDT numeric values |
| total_amount | widen to DECIMAL(14,2), 0.00 | Preserve BDT numeric values |

Index `(inventory_version,order_status)`. Storefront uses existing `created_via=frontend`, `campaign_id=null`; campaign orders keep their campaign_id. Reports must not assume frontend always means a campaign. Never rewrite historical created_via or timestamps.

### order_items (existing)

| New/change | Type/default | Purpose |
|---|---|---|
| product_variant_id | nullable BIGINT | FK product_variants, RESTRICT physical delete |
| variant_sku | nullable VARCHAR(100) | Immutable purchase-time SKU snapshot |
| variant_options | nullable JSON | Purchase-time option labels/IDs |
| inventory_allocated_quantity | unsigned INT 0 | Net quantity allocated against this line in managed inventory |
| unit_price | widen to DECIMAL(14,2), 0.00 | BDT, same numeric meaning as old integers |
| total_price | widen to DECIMAL(14,2), 0.00 | BDT |
| discount_amount | widen to DECIMAL(14,2), 0.00 | Already decimal in source; consistent precision |

Index `(product_variant_id,order_id)`. Existing nullable product_id remains. Old rows keep null variant snapshots unless safely mapped; never invent which size/color a past customer chose. Item edits use quantity/variant deltas, not full decrement again. Inventory ledger must be processed before a managed line can be soft-deleted.

## New catalog/inventory tables

### product_options

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| code | VARCHAR(50) | unique, e.g. size/color |
| name | VARCHAR(100) | display label |
| input_type | VARCHAR(20), select | allowlist select/swatch |
| is_active | BOOLEAN true | deactivate without deleting history |
| sort_order | unsigned INT 0 | editor/display order |
| timestamps | timestamps | |

Initial option definitions are idempotent `size`, `color`, `weight`, and `piece_count` (piece/pack quantity) when needed by configured products; no production product seed data. Store display labels such as `1 kg`, `2 kg`, `1 piece`, `2 pieces`, not assumed pricing formulas. Product-specific options may be enabled independently.

### product_option_values

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| product_option_id | BIGINT | FK options RESTRICT |
| code | VARCHAR(80) | stable value identifier |
| label | VARCHAR(100) | e.g. XL, Black |
| swatch_hex | nullable CHAR(7) | only validated #RRGGBB, color option only |
| is_active | BOOLEAN true | |
| sort_order | unsigned INT 0 | |
| timestamps | timestamps | |

Unique `(product_option_id,code)` and unique `(id,product_option_id)` for composite consistency. Used values cannot be hard-deleted. Labels can change prospectively; order snapshots do not change.

### product_option_assignments

`product_id BIGINT FK products RESTRICT`, `product_option_id BIGINT FK product_options RESTRICT`, `sort_order unsigned INT default 0`, timestamps. Composite PK `(product_id,product_option_id)`. Specifies which options belong to a product. V1 permits applicable `size`, `color`, `weight`, and `piece_count` options; **only explicitly created combinations** are purchasable. No automatic Cartesian expansion or price multiplication. A proposed cap of 200 variants per product applies.

### product_variants

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| product_id | BIGINT | FK products RESTRICT |
| sku | VARCHAR(100) | globally unique, including retired SKUs |
| option_signature | VARCHAR(191) | non-null canonical sorted option:value ID pairs, or `default` |
| price_override | nullable unsigned INT | whole BDT; null inherits product.new_price; zero is rejected for purchasable v1 variants |
| old_price_override | nullable unsigned INT | whole BDT; validate against effective price |
| stock | unsigned INT 0 | authoritative available-to-sell quantity |
| is_default | BOOLEAN false | simple-product fallback identity |
| is_active | BOOLEAN true | |
| sort_order | unsigned INT 0 | |
| timestamps | timestamps | |
| deleted_at | nullable timestamp | soft delete only; stock must be zero first |

Unique `(product_id,option_signature)`; index `(product_id,is_active,sort_order,id)`. A uniqueness constraint with nullable size/color is **not** sufficient; the non-null signature avoids duplicate nullable combinations. Server generates the signature from verified IDs, never trusts a submitted signature. Exactly one default variant is enforced under product-row lock for a simple product. A variable product may have a preferred display variant but checkout must explicitly resolve a valid selection.

If creating explicit variants from a simple product, transfer stock through InventoryService; total before/after must reconcile. Do not duplicate the default's full stock into every new combination. Do not sell both a generic default pool and its transferred explicit combinations.

### product_variant_values

`product_variant_id BIGINT FK product_variants RESTRICT`, `product_option_id BIGINT FK product_options RESTRICT`, `product_option_value_id BIGINT`, timestamps.

PK `(product_variant_id,product_option_id)`. Composite FK `(product_option_value_id,product_option_id)` references option_values `(id,product_option_id)`. Application additionally validates the option is assigned to the variant's product. Database constraint migration must pass on the chosen production engine and test engine.

### inventory_movements

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK, immutable row |
| product_variant_id | BIGINT | FK variants RESTRICT |
| order_item_id | nullable BIGINT | FK order_items RESTRICT |
| event_uuid | UUID/CHAR(36) | persisted business-operation identity |
| operation_key | VARCHAR(191) | unique retry identity derived server-side |
| kind | VARCHAR(30) | opening/allocate/release/return/adjust/transfer |
| quantity_delta | signed INT | negative sells/allocates, positive restores |
| stock_before | unsigned INT | locked prior value |
| stock_after | unsigned INT | new value, never negative |
| actor_id | nullable BIGINT | FK users SET NULL; guest/system=null |
| reason | VARCHAR(255) | mandatory for adjustments/returns |
| metadata | nullable JSON | minimal operational context, no secrets |
| created_at | timestamp | no update/delete API |

Index `(product_variant_id,id)` and `(order_item_id,id)`. Invariant: stock_after=stock_before+quantity_delta. Stock change, ledger row and line allocated quantity are in one DB transaction. No cascade that erases history when products/orders are deleted. Never blindly restock legacy orders without allocation evidence.

## New homepage-builder tables

### storefront_pages

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| slug | VARCHAR(80) | unique; initial page `home` |
| mode | VARCHAR(20), campaign | allowlist campaign/storefront |
| draft_revision_id | nullable BIGINT | FK storefront_revisions RESTRICT, add after both tables exist |
| published_revision_id | nullable BIGINT | FK storefront_revisions RESTRICT |
| lock_version | unsigned BIGINT 1 | optimistic concurrency for all mutations |
| timestamps | timestamps | |

The revision pointers must reference this page, enforced in the locked service and tested against cross-page IDs. No public page deletion endpoint. Mode can be set to storefront only with a valid published revision and completed release gates.

### storefront_revisions

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| storefront_page_id | BIGINT | FK pages RESTRICT |
| revision_number | unsigned INT | unique with page_id |
| status | VARCHAR(20), draft | draft/published/archived |
| schema_version | unsigned SMALLINT 1 | payload compatibility |
| base_revision_id | nullable BIGINT | self-FK RESTRICT |
| settings | JSON | typed global theme, widgets, nav and SEO configuration |
| created_by | nullable BIGINT | FK users SET NULL |
| updated_by | nullable BIGINT | FK users SET NULL |
| published_by | nullable BIGINT | FK users SET NULL |
| published_at | nullable timestamp | |
| timestamps | timestamps | |

Unique `(storefront_page_id,revision_number)`. Only the revision pointed to by draft_revision_id and status=draft is mutable. Publication does not snapshot product stock/prices or copy customer information.

### homepage_sections

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| storefront_revision_id | BIGINT | FK revisions RESTRICT |
| section_key | UUID/CHAR(36) | logical identity preserved when cloning |
| type | VARCHAR(40) | server registry allowlist |
| title | nullable VARCHAR(160) | |
| subtitle | nullable VARCHAR(200) | |
| description | nullable TEXT | escaped plain text by default |
| is_enabled | BOOLEAN true | |
| show_desktop | BOOLEAN true | |
| show_mobile | BOOLEAN true | |
| sort_order | unsigned INT | contiguous zero-based position |
| source_type | VARCHAR(30), none | none/manual/category/subcategory |
| category_id | nullable BIGINT | FK categories RESTRICT |
| include_descendants | BOOLEAN false | root-category source only |
| product_sort | VARCHAR(30), manual | manual/newest/price_asc/price_desc |
| product_limit | unsigned INT 8 | validated 1..50 for product lists |
| layout | JSON | allowed responsive presentation settings |
| styles | JSON | nullable, typed color overrides only |
| settings | JSON | typed per-section content settings |
| timestamps | timestamps | |

Unique `(storefront_revision_id,section_key)`; index `(storefront_revision_id,sort_order,id)`. Do not make sort_order globally unique. Transactional reorder normalizes siblings and rejects duplicated/missing/foreign IDs. Both visibility flags false is allowed in draft but warns on publish.

For category/subcategory sources with manual ordering, the stored product selection is the explicit curated subset; require each selected product to satisfy the source filter. For automatic sorting, resolve eligible current products and ignore stored manual selections. Never silently append arbitrary products to a manually curated section.

### homepage_section_products

`homepage_section_id BIGINT FK sections RESTRICT`, `product_id BIGINT FK products RESTRICT`, `sort_order unsigned INT`, timestamps. Composite PK `(homepage_section_id,product_id)`, index `(homepage_section_id,sort_order,product_id)`. A product can occur in different sections; its section-specific order does not mutate product.category_id or campaign_product.sort_order.

### homepage_section_categories

`homepage_section_id BIGINT FK sections RESTRICT`, `category_id BIGINT FK categories RESTRICT`, `sort_order unsigned INT`, timestamps. Composite PK `(homepage_section_id,category_id)`. Represents curated category tiles, not taxonomy ownership. Inactive/deleted categories are hidden and reported as stale references in the editor.

### homepage_section_items

| Column | Type/default | Key/rule |
|---|---|---|
| id | BIGINT auto | PK |
| homepage_section_id | BIGINT | FK sections RESTRICT |
| item_key | UUID/CHAR(36) | logical identity |
| type | VARCHAR(30) | slide/image_text/review/campaign; allowed by section registry |
| title | nullable VARCHAR(160) | |
| description | nullable TEXT | plain text |
| link_url | nullable VARCHAR(2048) | validated relative or permitted HTTPS URL |
| link_label | nullable VARCHAR(80) | |
| media_id | nullable BIGINT | FK existing media RESTRICT |
| review_id | nullable BIGINT | FK existing reviews RESTRICT |
| campaign_id | nullable BIGINT | FK existing campaigns RESTRICT |
| is_enabled | BOOLEAN true | |
| sort_order | unsigned INT | sibling order |
| settings | JSON | typed item data, alt text and optional second CTA |
| timestamps | timestamps | |

Unique `(homepage_section_id,item_key)`; index `(homepage_section_id,sort_order,id)`. Enforce the valid reference combination per type: a review references review_id; a campaign card campaign_id; slides use approved media. Reject unrelated IDs and mass-assigned foreign keys. Media must be safe public content authorized for use, not arbitrary private media.

### homepage_audit_events

`id BIGINT PK`, `storefront_page_id BIGINT FK RESTRICT`, `storefront_revision_id nullable BIGINT FK RESTRICT`, `actor_id nullable BIGINT FK users SET NULL`, `event VARCHAR(30)`, `from_version unsigned BIGINT`, `to_version unsigned BIGINT`, `summary JSON`, `created_at timestamp`.

Events: draft_created, draft_saved, reordered, published, restore_draft_created, mode_changed. Append-only; summaries include changed section keys, not PII, session tokens or full credentials. This is an operational audit, not evidence that every request should be logged verbatim.

## New cart/checkout tables

### carts

`id UUID PK`, `owner_hash CHAR(64) unique`, `currency CHAR(3) default BDT`, `lock_version unsigned BIGINT default 1`, `expires_at timestamp`, timestamps. The owner is a server-issued 256-bit random guest cookie; store only its SHA-256 hash. Do not accept owner_hash/cart ownership from request JSON. No customer-auth assumption. Proposed retention is 30 days, configurable and subject to business policy.

### cart_items

`id BIGINT PK`, `cart_id UUID FK carts CASCADE`, `product_id BIGINT FK products RESTRICT`, `product_variant_id BIGINT FK variants RESTRICT`, `quantity unsigned INT`, `quoted_unit_price DECIMAL(14,2)`, timestamps.

Unique `(cart_id,product_variant_id)`. Server enforces variant belongs to product and bounded quantities. quoted_unit_price is a server-created last acknowledgement used to detect changed prices, not checkout authority. No campaign_id: mixed campaign/storefront cart is deliberately out of v1 scope.

### checkout_requests

`id UUID PK`, `owner_hash CHAR(64)`, `idempotency_key UUID`, `channel VARCHAR(20)` (storefront/campaign), `campaign_id nullable BIGINT FK campaigns RESTRICT`, `request_hash CHAR(64)`, `status VARCHAR(20)` (processing/completed), `order_id nullable BIGINT FK orders RESTRICT`, timestamps.

Unique `(owner_hash,idempotency_key)` and unique nullable `order_id`; index `(status,created_at)`. Key replay with different canonical payload returns 409. A completed identical replay returns the same success reference, without another stock change. Create/complete processing in the order transaction; do not leave a half-committed request after validation/stock failure. Existing campaign form token is not a substitute for this durable uniqueness guarantee.

## Key cross-table invariants

1. Category-parent, page-revision and section-item ownership are checked on every mutation.
2. Catalog IDs/options are not copied from an unrelated project; all FKs use current records.
3. All purchased variant options are valid for that product; snapshots preserve historical labels.
4. Available stock is never negative; all sales paths use the same lock and ledger rules.
5. A published revision never points at mutable draft rows; clone children and pivots on draft creation.
6. Soft-deleted/inactive referenced catalog content never appears publicly merely because a page references it.
7. Product/media deletion is blocked if it would destroy retained publication/order history; replacing media creates a new record.
8. Existing integer BDT values survive monetary widening unchanged in value; no silent currency-unit migration.
9. Old order inventory_version=0 cannot be auto-restocked until explicitly reconciled.
10. Repeated migration/backfill runs do not create extra variants or multiply stock.

See [migration sequence](docs/04-migrations-and-backfill.md), [inventory](docs/05-products-and-inventory.md), and [builder](docs/06-homepage-builder.md) for transactional rules.

## Meeting F-006: quantity, weight and package semantics

The PDF-reported meeting provides e.g. 1 kg=1499 BDT and 2 kg=1800 BDT, and separately references 1/2 piece choices. Model each as a **selected variant option value** with its own price; cart `quantity` counts purchased units of that variant and is a distinct field. A `2-piece pack` variant may represent one pack sold; whether its stock is counted in packs or underlying pieces is **G03 pending**. Do not publish available-"pieces" labels until approved inventory units are reconciled. Any migration to normalized unit conversion, consumption of child pieces, or variant-specific campaign overrides requires its own approved schema decision and tests. See docs/21, docs/23 and docs/25.
