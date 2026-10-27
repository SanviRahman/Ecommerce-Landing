# Entity Relationship Diagrams

**Baseline + proposed extension**, not a representation of an already-migrated database. Existing entities are labeled in diagram comments. Column authority is [database-schema.md](database-schema.md). Mermaid blocks can be previewed in a compatible Markdown viewer.

## 1. Catalog, variants, inventory and orders

```mermaid
erDiagram
    categories ||--o{ categories : parent_of
    categories ||--o{ products : contains
    products ||--o{ product_option_assignments : configures
    product_options ||--o{ product_option_assignments : assigned_to
    product_options ||--o{ product_option_values : offers
    products ||--|{ product_variants : sells_as
    product_variants ||--o{ product_variant_values : selects
    product_option_values ||--o{ product_variant_values : chosen_value
    product_options ||--o{ product_variant_values : identifies_option
    campaigns ||--o{ campaign_product : contains
    products ||--o{ campaign_product : listed_in
    campaigns o|--o{ orders : attributes
    orders ||--|{ order_items : contains
    products o|--o{ order_items : historical_product
    product_variants o|--o{ order_items : purchased_variant
    product_variants ||--o{ inventory_movements : changes
    order_items o|--o{ inventory_movements : explains

    categories {
        bigint id PK
        bigint parent_id FK
        string slug UK
        int sort_order
        boolean status
    }
    products {
        bigint id PK
        bigint category_id FK
        string product_code UK
        boolean has_variants
        int inventory_version
        int stock
    }
    product_variants {
        bigint id PK
        bigint product_id FK
        string sku UK
        string option_signature
        int price_override
        int stock
        boolean is_active
    }
    product_options {
        bigint id PK
        string code UK
        string input_type
    }
    product_option_values {
        bigint id PK
        bigint product_option_id FK
        string code
        string swatch_hex
    }
    product_variant_values {
        bigint product_variant_id PK,FK
        bigint product_option_id PK,FK
        bigint product_option_value_id FK
    }
    orders {
        bigint id PK
        bigint campaign_id FK
        string created_via
        int inventory_version
        decimal total_amount
    }
    order_items {
        bigint id PK
        bigint order_id FK
        bigint product_id FK
        bigint product_variant_id FK
        string variant_sku
        json variant_options
        int quantity
        int inventory_allocated_quantity
    }
    inventory_movements {
        bigint id PK
        bigint product_variant_id FK
        bigint order_item_id FK
        string operation_key UK
        int quantity_delta
        int stock_before
        int stock_after
    }
```

`categories`, `products`, `campaigns`, `campaign_product`, `orders` and `order_items` already exist. Variant/ledger tables are proposed. Optional order-line variant relationships preserve old history. All products have one or more variants only after successful backfill; that minimum cardinality is an application invariant, not an FK guarantee.

## 2. Versioned homepage builder

```mermaid
erDiagram
    storefront_pages ||--o{ storefront_revisions : owns
    storefront_pages o|--o| storefront_revisions : points_to_draft
    storefront_pages o|--o| storefront_revisions : points_to_published
    storefront_revisions ||--o{ homepage_sections : snapshots
    homepage_sections ||--o{ homepage_section_products : curates
    products ||--o{ homepage_section_products : selected
    homepage_sections ||--o{ homepage_section_categories : curates
    categories ||--o{ homepage_section_categories : selected
    categories o|--o{ homepage_sections : filter_source
    homepage_sections ||--o{ homepage_section_items : contains
    media o|--o{ homepage_section_items : illustrates
    reviews o|--o{ homepage_section_items : references
    campaigns o|--o{ homepage_section_items : promotes
    storefront_pages ||--o{ homepage_audit_events : records
    storefront_revisions o|--o{ homepage_audit_events : concerns
    users o|--o{ homepage_audit_events : acts

    storefront_pages {
        bigint id PK
        string slug UK
        string mode
        bigint draft_revision_id FK
        bigint published_revision_id FK
        bigint lock_version
    }
    storefront_revisions {
        bigint id PK
        bigint storefront_page_id FK
        int revision_number
        int schema_version
        string status
        json settings
    }
    homepage_sections {
        bigint id PK
        bigint storefront_revision_id FK
        string section_key
        string type
        int sort_order
        string source_type
        bigint category_id FK
        string product_sort
        int product_limit
        json styles
        json layout
        json settings
    }
    homepage_section_items {
        bigint id PK
        bigint homepage_section_id FK
        string item_key
        string type
        bigint media_id FK
        bigint review_id FK
        bigint campaign_id FK
        int sort_order
        json settings
    }
```

The two page pointers are optional; each must point to a revision belonging to the same page. Revisions own separate section/item rows. `media`, `reviews`, `campaigns`, `categories`, `products`, `users` remain existing entities. Media is reused via approved references rather than duplicated image-path columns.

## 3. Guest storefront cart and idempotent checkout

```mermaid
erDiagram
    carts ||--o{ cart_items : contains
    products ||--o{ cart_items : identifies
    product_variants ||--o{ cart_items : selected
    campaigns o|--o{ checkout_requests : context
    orders o|--o| checkout_requests : completed_by
    carts {
        string id PK
        string owner_hash UK
        string currency
        bigint lock_version
        datetime expires_at
    }
    cart_items {
        bigint id PK
        string cart_id FK
        bigint product_id FK
        bigint product_variant_id FK
        int quantity
        decimal quoted_unit_price
    }
    checkout_requests {
        string id PK
        string owner_hash
        string idempotency_key
        string channel
        bigint campaign_id FK
        string request_hash
        string status
        bigint order_id FK,UK
    }
```

Cart ownership and checkout ownership share a server-derived guest-token hash, not a public relationship ID supplied by the client. Checkout does not require an FK to a cart: campaign quick-order submissions have no storefront cart. One completed request maps to one order; many legacy orders have no checkout_request. No customer-account relationship is implied.

## Meeting-aligned catalog note

The `product_options` -> `product_option_values` -> `product_variant_values` graph supports **size, color, weight (`1 kg`/`2 kg`) and piece-count/pack values (`1 piece`/`2 pieces`)**, not only size/color. Each concrete combination remains a single `product_variants` row with its own price/stock; the shopping cart `quantity` is different from the option's labeled amount. Until business gate G03 is approved, the meaning of pack stock vs underlying pieces remains conditional. No new redundant table is needed solely for these option names.


### P37 Contact message isolation

`storefront_contact_messages (id PK, encrypted name/email/phone/message/admin_note, subject, status, read_at, created_at, updated_at, deleted_at)`. Standalone private customer correspondence, with no foreign key to `orders` or campaigns. Public contact data continues to be read from the existing one-row active `site_settings` resource.

### P38 isolated additions

- `storefront_faqs` is stand-alone. `faqs` -> `campaigns` relationship stays untouched.
- `site_settings` -> `media` (existing polymorphic Media Library) now uses additional `storefront_logo`, `storefront_white_logo` and `storefront_favicon` collection names; no new FK or column.
- `storefront_revisions` gets nullable `deleted_at` for hidden *archived* histories only; all `storefront_pages` pointers, `homepage_sections` children, and `homepage_audit_events` references are retained.
