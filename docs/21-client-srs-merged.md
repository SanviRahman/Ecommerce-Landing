# Merged Software Requirements Specification (SRS) - Campaign + Storefront

**Version:** 2.0; prepared 2026-10-24 from user-authored requests (U), inspected Laravel source (C), screenshots (S) and the **previous AI meeting report** in the uploaded 32-page PDF (M). **No original meeting audio/video was provided.** Client acceptance of this consolidated technical interpretation remains pending. See [source coverage](19-meeting-source-and-coverage.md) and [traceability](20-meeting-traceability.md).

## 1. Project overview and baseline

Extend `website(1).zip`, the user's existing PHP ^8.3 / Laravel 13.7.0 (locked dependency) campaign-based system. The present root homepage resolves an active campaign; current campaign URLs and order/courier/report functionality must remain supported. Build a standalone commerce storefront and a structured admin-controlled homepage without replacing the campaign landing template.

The PDF describes a separate demonstrated site, **proyojonerbazar.com**, and references **htbazar.com** as the desired design and **leatherket.com** for variations/description placement. It is NOT verified that the demonstrated site's code is the uploaded ZIP. Re-audit the actual repository and treat the PDF's depicted features as target behavior, not claims of features already implemented in the ZIP.

## 2. Business objectives

Enable a nondeveloper administrator to select, design, reorder and publish mobile-friendly merchandising sections; enable customers to discover products through nested categories, choose real color/size/weight/piece options, see accurate prices/stock and place valid orders. Existing campaign business, data and URLs must survive.

## 3. Scope and source-backed requirements

| Group | Requirements | Evidence |
|---|---|---|
| Homepage merchandising | Category/subcategory/manual product sections, reorder, limits, hide/show, draft/preview/publish | U + M F-002 + S |
| Category browsing | All Categories nested menu, direct leaf navigation, preserve existing Shop by Category appearance | M F-001, p7-8 |
| Responsive catalog | Desktop 4 cards/row, mobile 2, enlarged catalog cards, existing filters preserved | M F-003, p8-10 |
| Product card CTA | 25% secondary Add to Cart, 75% primary Buy/Order Now; variant-safe action | M F-004, p12-13 |
| Product details | 2-part desktop layout, large borderless image, price/variant/quantity/actions above description/shipping copy | M F-005, p11-19 |
| Catalog options/prices | color, size, **weight**, piece/pack, data-driven variant price; available units | U + M F-006, p13-14 |
| Mobile order bar | Scroll-triggered **Order Now**, chosen variant+qty, checkout destination | M F-007, p15-16 |
| Checkout | Desktop sticky summary, mobile button immediately after customer form; server-price validation | M F-008, p14 |
| Mobile navigation | Sticky header and bottom Home/Menu/Cart (optional search); accessible drawers | M F-009, p14; screenshots |
| Floating contacts/cart | `+` channels WhatsApp/Messenger/Email (U), Call optional (M); **separate** floating mini-cart drawer | U + M F-010, p17-18 |
| Colors | Green-like global default, admin global and **per-section** background/button/title/accent/text/hover style control | U + M F-011, p17-18 + S |
| Operational correctness | Shared inventory, campaign membership, safe migrations, idempotency, auth, media, regression | C/P required to safely satisfy U |

The full 11-item mapping is in `docs/20-meeting-traceability.md`. Do not call proposed implementation details meeting-approved merely because they appear in this SRS.

## 4. Roles and permissions

- Guest shopper: browse/search/categories, select options, add/update/remove own cart, read checkout quote and place valid COD order.
- Existing active administrator: catalog/variant CRUD, inventory adjustment authorization, site settings, homepage draft/reorder/preview/publish, campaign management.
- Employee: existing scopes remain unchanged; homepage publish and stock adjustments **denied by default** until explicit access policy approval.
- No new customer login, wishlists, payment gateway, wholesale engine, agency service menu or developer-access shell in scope.

## 5. Detailed functional specifications

### FR-01 Nested categories (M F-001)

Inputs: category name, unique slug, optional valid parent ID, status, sort order. Rules: V1 root plus one subcategory depth; no self-parenting/cycles; child hidden if inactive parent; parent may expand on click, with keyboard-operable controls. Example Electronics > Fan/Light and Fashion > T-Shirt/Shirt/Pant. Clicking a leaf routes to category-filtered product page; if parent has no children, parent category page opens. Empty results provide a user-readable state. **Desktop hover vs click:** click-first accessible default (P); client sign-off pending.

### FR-02 Dynamic homepage product sections (M F-002; U drag editor)

Admin can add typed category/product sections, choose category or subcategory source, optionally curate specific products, set display limit and sort, drag section above/below another and drag curated products. Section order stored as draft positions and becomes public on Publish only (P safe lifecycle). Empty active-category section hides rather than showing broken cards; fallback selection policy must be explicit. Shop by Category layout and current header are preserved unless approved changes. Category reordering does not implicitly rewrite taxonomy parent IDs.

### FR-03 Responsive product grids (M F-003)

Homepage desktop standard viewport: **4** cards per row; mobile: **2**. Existing Shop filters retained. On shop/category pages use a layout that keeps enlarged cards legible; the meeting leaves sidebar/3-column alternative open, so test representative widths (320, 360, 390, 768, 1024, 1440). Do not force four cramped columns in a sidebar-constrained container. Use consistent image aspect ratios, truncation/expandable name strategy and proper clickable action spacing.

### FR-04 Product card actions (M F-004)

Visually secondary Add to Cart occupies approximately **25%** and primary **Buy Now / Order Now 75%** of action row. On narrow cards, use a labelled accessible cart icon with tooltip/aria-label if full text will overflow; do not hide functional meaning. Add-to-cart updates the authoritative guest cart and mini-cart. Buy/Order Now begins checkout for explicitly selected valid variant (or redirects to variant selection if required); it must **not silently guess a variant**. Out-of-stock state disables purchase and communicates reason. Primary visible naming must be consistent with client-approved copy; sticky CTA label is always **Order Now**.

### FR-05 Product details (M F-005)

Desktop: left large borderless image/gallery, right title, price, option pickers, stock, quantity and actions. All descriptive text, SKU/category/free-shipping copy, FAQ/extra badges appear **below action controls**. Mobile stacks image -> title -> selected price -> options -> quantity/actions -> description. Do not blindly copy HT Bazar's description-first hierarchy. Maintain media alt text, aspect ratio and clear focus order. Prevent layout shift on image load.

### FR-06 Product options and pricing (M F-006; U availability)

The admin may attach color, size, weight (e.g. `1 kg`, `2 kg`) and piece/pack options (e.g. `1 piece`, `2 pieces`) to a product. Each valid combination resolves one purchasable variant with unique SKU, explicit inherited/overridden whole-BDT price, active status and available inventory. Selecting a variant immediately updates UI price and availability; changing cart **quantity** multiplies the single selected variant's price by its number of sellable units. **Do not automatically compute 2 kg price as double 1 kg price:** meeting reports illustrative 1kg=1499 and 2kg=1800 but also inconsistent wording. Treat price as explicit per-variant data; any tier/weight formula needs approval. Distinguish `2-piece pack` counted as one pack sold from selling two underlying units; choose after stock-unit mapping approval. Server never trusts browser price.

### FR-07 Scroll-sticky Order Now (M F-007)

On product detail pages, after the primary action section exits viewport during downward scroll, display a compact **Order Now** CTA. It uses the chosen variant/quantity and opens checkout; invalid options show inline validation instead. Do not show duplicate overlay while primary CTA visible. On mobile, position above fixed nav/safe area; hide or reposition around keyboard and drawer. Use IntersectionObserver with tested fallback; do not place obstructive invisible overlay.

### FR-08 Checkout layout and validation (M F-008)

Desktop: customer form plus right sticky order summary, including item/variant quantities, unit price, subtotal, shipping and total, followed by order submission. Mobile: form fields (name, phone, address, delivery area) then a visible **অর্ডার করুন** action directly below form, with order summary following. **One semantic submit operation** even if layout duplicates affordances. Pricing, shipping, active availability and idempotency checked transactionally on server. Empty cart cannot place order. Existing COD, courier, anti-block, attribution and report semantics preserved.

### FR-09 Sticky mobile navigation (M F-009)

Top header remains readable while scrolling. Bottom fixed nav has Home, Menu/Categories and Cart; optional Search only if functional. Cart count matches actual cart quantities. Supports keyboard/focus and 320px width without overlap with sticky Order Now, contacts and cart drawer. Account/Wishlist must not appear as nonfunctional links.

### FR-10 Floating contacts and separate mini-cart (M F-010; U)

The independent floating `+` expands **WhatsApp, Messenger and Email**, each using validated configured link; phone/call is a separate optional channel shown in the PDF, not automatically substituted for Email. A distinct floating cart icon opens a right slide-out drawer containing server-owned cart items, selected variant, stock, quantity controls, subtotal, delete and checkout. On adding a product, mini-cart and navigation counters reconcile immediately. No screen-position arbitrary free dragging is required for widgets: bounded admin alignment/spacing prevents overlap (P).

### FR-11 Global and section-specific theme controls (M F-011; U)

Default global design is **green-like**, reflecting the client's Desh Bazar reference; final hex not provided. Global Admin color pickers change selected global theme tokens. Every homepage section has independent background/heading/body/accent/button background, text, hover, border controls, plus inherit/reset and visibility. Use only validated hex tokens or null. Good contrast is checked and manually previewable over images. Existing hero/Spotlight screenshots guide admin control patterns but are not permission to add digital-agency-specific content to shop.

### FR-12 Inventory and order consistency (U/C/P)

Shared authoritative variant inventory across campaign, storefront, manual order/edit, cancellation and return actions; transaction-safe stock allocation and unique movement identity. No historical unverified stock deduction, no duplicated variants on migration. Available pieces shown only from reconciled sellable inventory. Existing campaign specific pricing/return policy awaits business sign-off. Do not mark stock correct solely from a UI badge.

### FR-13 Admin builder controls (U/S/P)

Structured component selector + sortable canvas + selected component settings, with Save Draft, Desktop/Mobile Preview and Publish. Allow structured blocks, not arbitrary injected CSS/Blade/HTML. Dragged order persists in DB, fails gracefully on conflict; keyboard/up/down alternatives. Published snapshot immutable; product price/stock remain live authoritative data. New custom section types only through code-owned allowlist.

## 6. UI acceptance contract

Consult [22-ui-workflows.md](22-ui-workflows.md) for breakpoints, element order, actionable controls and no-overlap contracts. Primary homepage/category design rules **must** match F-001-F-011 even though earlier spec used softer defaults. Preserve existing Shop by Category display size and familiar navigation unless acceptance review changes it.

## 7. Data and schema impact

Use `database-schema.md` and `ERD.md` as **proposed** design. Add `parent_id` to existing categories, normalized option/value/variant records (size, color, weight, piece_count as option codes with display labels), inventory movements, safe cart+homepage publication tables and order-item variant snapshots. Verify real schema and historical data before writing migrations. Do not create duplicate product description/media/contact/created_via columns. No production schema was altered by this documentation delivery.

## 8. API/route impacts

Use `docs/08-routes-and-contracts.md`. Canonical **candidate** paths based on PDF-observed examples: `/shop`, `/category/{slug}`, `/product/{slug}`, `/cart`, `/checkout`; route collision audit against the existing custom campaign root route is mandatory. Admin homepage endpoints require active admin authorization, CSRF, validation, version checking and real persisted results. **PDF URLs are descriptions of a demonstrated reference site's behavior, not proof the ZIP routes already exist.**

## 9. Nonfunctional requirements

- Security: Eloquent binding and validated input; no price/stock trust in JS; scoped authorization; output encoding/sanitized rich text; media validation; no GET mutations; CSRF on admin/cart/order mutations; secrets excluded.
- Accessibility: focusable submenu/drag alternatives, `aria` for expanders and mini-cart, contrast, minimum practical touch targets, reduced-motion autoplay behavior.
- Mobile: test 320/360/390/768; sticky UI overlap, keyboard safe-area, long Bangla product names.
- Performance: pagination/search for selectors, eager loading/media thumbnails, bounded published section queries, no duplicate JS listeners on drawer re-renders.
- Observability: audit publish, inventory movements, order idempotency and safe request errors; do not log PII/secrets.

## 10. Preserved capabilities and constraints

Campaign slug/custom route functionality, default campaign behavior until storefront enabled, media storage conventions, old orders and their inventory history, courier/refund/reporting, and available anti-fraud validation must remain intact. Do not run destructive migration, `migrate:fresh`, `db:wipe` or `key:generate` on the existing deployment. Current PHP/Laravel dependency lock is not permission for framework upgrade.

## 11. Risks, bugs and needed fixes

See `docs/03-bugfix-register.md`. Highest risk: campaign product membership bypass and absent campaign stock enforcement. A static review is not evidence of exploitability or deployment state. Fix with a targeted failing test first, then integrate shared price/stock service and test duplicate/race behavior. Category/custom URL collisions, unsafe write actions, stored raw HTML, image replacement cache and ledger history need separate verification. No fix has been applied by this merged documentation pack.

## 12. Implementation, dependencies and testing

Run P00-P10 sequentially under `docs/14-implementation-sequence.md` and Codex prompts. Additional UI acceptance and negative tests are in `docs/25-meeting-tests.md`, supplementing the original `docs/12-testing-and-acceptance.md`. Require actual browser screenshots and independent concurrent stock tests before storefront activation.

## 13. Out of scope and pending approval

Customer registration/login and wishlists, automatic wholesale pricing, online payment gateways, agency-services content, unrestricted pixel-level page builder, new notification platforms, importing other websites' code or data. Pending: brand exact HEX, variant weight/pack inventory accounting, mobile small-card 25% cart label/icon, CTA exact copy beyond required sticky `Order Now`, opening submenu interaction, dynamic logo asset, product limit/rows per category, phone call optional, employee publish roles, shipping and returns.

## 14. Release acceptance / definition of done

Each of meeting F-001..F-011 and direct-user R-IDs has a passing test record with changed files/commands and responsive evidence. Campaign URLs/orders/regressions pass, inventory baseline is reconciled and race-safe, dashboard color changes persist with no bleed, draft stays private, admin permissions enforced and backup/rollback tested. Report skipped tests honestly. **'No errors' cannot be guaranteed by static specification alone.**

## 15. Action tracker and approvals

See `docs/23-decisions-conflicts.md`. Priority classification follows user's business goal and the PDF's report as **guidance**, not delivery deadline. Do not invent named owners, dates or client signatures.
