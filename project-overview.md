# Project Overview

## 1. Goal

Extend the uploaded Laravel campaign application, rather than replace it, with a configurable e-commerce homepage inspired by Leatherket's merchandising administration and HT Bazar's storefront UI. Each content section must support independent styling and structured drag-and-drop management.

## 2. Input status

| Input | Status | Use |
|---|---|---|
| `website(1).zip` | Inspected, byte-identical to `website.zip` | Authoritative baseline source |
| `public_html leatherket(1).zip` | Inspected selected source | Behavioral reference only |
| Leatherket section screenshot | Visible | Category section, product cap, numeric sorting, size/color/option menu |
| HT Bazar screenshots and public homepage | Viewed | Floating cart, category product lists, reviews, mobile navigation/contact UI |
| Two Sunnah Digital Agency admin screenshots | Visible | Background/accent/heading color pickers, editable headings, CTA links, section visibility/autoplay controls |
| Client meeting PDF (32 pages) | ATTACHED, content integrated; original recording NOT supplied | Earlier AI report/transcript, F-001-F-011; PDF ends after CF-06 |

## 3. Confirmed scope

1. Size and color choices linked to real purchasable variants, not decorative dropdowns.
2. Parent categories and subcategories using the existing category table.
3. Homepage sections with selectable category/subcategory/manual-product sources, maximum products, and predictable ordering.
4. Drag-and-drop section order and item order; accessible move controls as an alternative.
5. Available pieces on product cards, product details and cart, with server-side purchase enforcement.
6. Floating `+` menu revealing WhatsApp, Messenger and Email.
7. Per-section background, heading/text/accent and button palette controls in the admin panel.
8. Ordered implementation documents, ERD, schema, agent rules, bugs, tests and deployment instructions for Codex.

## 4. Reference-derived but not independently confirmed features

Treat floating cart and mobile bottom navigation as proposed included storefront UI. Reviews, hero slides and product carousels reuse existing content where possible. No assumption that the screenshot's phone-device preview, Windows system tray, agency services or admin dashboard branding belongs in the delivered shop.

The Account/Wishlist icons on the reference are NOT authorization to create customer authentication or wishlists. Hide unsupported actions. Customer records in this source do not establish a customer-login system. Phone contact is optional; Email is required by the written request.

## 5. Baseline stack and architecture

The manifest requires PHP ^8.3 and Laravel ^13.0; lockfile pins Laravel 13.7.0, AdminLTE 3.16.0, Media Library 11.22.0 and Permission 7.4.1. Vite is locked at 8.0.10; Node engine requirement in the lockfile is ^20.19.0 or >=22.12.0. These are dependency facts, not verified production runtime versions.

The homepage currently resolves an active default/latest campaign; homepage and campaign routes both render `frontend.pages.home`. The large Blade file contains CSS, markup and JavaScript order selection. Product/category CRUD, descriptions, media, site contacts, order/courier/reporting systems already exist. There is no dedicated variant table, subcategory hierarchy or persistent storefront cart in the reviewed schema/routes.

## 6. Proposed shape

- Existing campaigns retain their landing template and links.
- Root `/` gains a mode switch: existing campaign behavior by default; storefront only after release approval.
- A separate storefront view renders published section revisions.
- Blade/AdminLTE stay in place; small ES modules and SortableJS implement editing interactions.
- Every product has at least one variant: a hidden default variant for simple products, explicit combinations for variable products.
- Inventory is authoritative at variant level; old `products.stock` becomes a derived compatibility projection.
- Existing order storage and integrations are extended, not replaced. New order lines snapshot variant selections.
- Cart is guest-owned and persistent, storefront-only in v1; campaign quick ordering is separate but uses the same inventory/pricing services.
- Page layout/theme publication is versioned. Product prices/stock remain live data, not frozen inside page revisions.

## 7. Out of scope unless a new approved requirement changes it

Unrestricted HTML/CSS page building; arbitrary absolute positioning; customer accounts; wishlist; payment gateways; coupon/loyalty/wholesale-price engines; multivendor support; copying private reference APIs; framework upgrades; rewriting courier/report modules; redesigning campaign pages; migrating all money to a new currency unit.

A category called Wholesale on the reference does not by itself specify quantity-based wholesale pricing.

## 8. Success definition

The administrator can reorder sections/items, select category sources, edit colors and contacts, preview changes and publish atomically. Shoppers can choose valid variants, see truthful availability, keep a cart across pages, and submit an idempotent COD order without overselling. Existing campaign and operational workflows pass regression tests. Only tested fixes may be called resolved.

## 9. Release blockers

No verified production database schema/engine or current inventory reconciliation is included in the ZIP. The bundled SQLite database has only base Laravel tables. `vendor/`, `node_modules/` and `tests/` are absent. The meeting PDF was supplied only after the first pack; its original video/audio is still absent and its Part D feedback table ends after CF-06. The live proyojonerbazar.com deployment identity is not verified against the inspected ZIP. These caveats are tracked; no claimed meeting recording verification or application test results are fabricated.

## 10. New client-meeting visual requirements (integrated)

The user's 32-page meeting-report PDF adds eleven `F-001..F-011` specs as **PDF-reported**, not independently audio-confirmed. Among them: preserve existing header and Shop by Category proportions; nested categories; admin category section ordering; **4 desktop/2 mobile product grid**; product cards **25% Add to Cart / 75% Buy/Order Now**; two-part borderless-image details with descriptions below actions; color/size/**weight/piece** options and dynamic per-variant price; scroll sticky **Order Now**; desktop sticky checkout summary/mobile action below form; mobile sticky top/bottom navigation; contact and independent mini-cart; green-like default and admin color pickers. U directly adds drag-and-drop all relevant section/item orders, WhatsApp/Messenger/Email +, variant availability and **per-section** button/background colors. Reference the [merged SRS](docs/21-client-srs-merged.md) and [decision gates](docs/23-decisions-conflicts.md).
