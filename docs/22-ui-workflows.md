# User Interface / Interaction Acceptance Contract (Meeting-merged)

**Authority:** PDF-reported F-001..F-011 + direct user specifications; engineering-only defaults marked `(P)`. Illustrations in screenshots are reference behavior and do not imply hidden backend behavior.

## 1. Navigation and nested categories (F-001)

```text
Desktop: [Existing Logo] [Search] [Supported Wishlist?/Cart/etc]
         [Home] [Shop] [All Categories ▾] ...
                            Electronics  › [Fan, Light]
                            Fashion      › [T-Shirt, Shirt, Pant]
Mobile:  [Sticky Header]
         [Menu] → accordion categories → leaf category product listing
```

Keep original header arrangement if already present; client accepted existing header (PDF ~01:34). Accessible button controls expansion. A parent with children expands without immediately routing away. Preserve an explicit way to view the parent category (P: "View all Electronics"). Click works on desktop/mobile; hover is optional progressive enhancement, never the only activation mechanism. `Escape` closes, Tab accesses child links, click outside closes. Active child page has breadcrumb.

## 2. Homepage section builder workflow (U, M F-002)

```text
[Website > Homepage Builder]   [Save Draft] [Preview] [Publish]
┌─────────────┬──────────────────────────────┬───────────────────────┐
│ Add blocks  │  Drag sections ↑↓            │ Selected Section      │
│ + Hero      │ [Hero]                       │ Content / Data source │
│ + Category  │ [Shop by Category]            │ Layout                │
│ + Products  │ [Electronics products]        │ Colors & Button       │
│ + Reviews   │ [Kitchen products]            │ Visibility & Publish  │
└─────────────┴──────────────────────────────┴───────────────────────┘
```

- Drag-and-drop and accessible Move Up/Down reorders sections, not taxonomy itself.
- Each product section may choose `category` / `subcategory` / `manual`, limit, `manual/newest/price_asc/price_desc`, title and visibility.
- `Shop by Category` preserves current visual size/layout from meeting, although items are data-driven and may be reordered.
- Manual selected products may be reordered *within that section*. Dragging a product does not alter product's global category.
- Draft Save → Preview → Publish; publish requires authorized action. Hidden/incomplete section cannot leak. CSS preview uses the same validated styling resolver as public view.
- Color controls: global default plus each section's background, button fill, button text, hover, title, text and accent. Image overlay only with approved safe settings. Desh Bazar-like green is a visual direction; hex pending.
- Hero controls shown in agency screenshot (eyebrow, headline, CTAs, autoplay) are patterns to adapt, not agency text to copy.

## 3. Product listing card (M F-003/F-004)

```text
┌───────────────────────┐  4 cards / desktop row, 2 cards / mobile row
│    Product image      │
│  Name (readable)      │
│  Price • Available n  │
│ [🛒 25%][Buy Now 75%]│
└───────────────────────┘
```

- Usable primary action width matters more than text label length. Cart's 25% area may use an accessible icon on narrow mobile cards, with full `aria-label="Add to Cart"` and clear feedback.
- 2-column mobile includes 320px width; do not let sticky floating elements cover action buttons.
- Out of stock: disabled or non-purchase state with visible text. If variants must be chosen, both actions take shopper to options, not a guessed variant. No front-end stock-based enforcement without server validation.
- `Buy Now` on cards and `Order Now` on product sticky bar; final product primary text (Buy Now vs Order Now) may be standardized after copy review. Function remains direct valid checkout.
- Shop and category filters remain accessible; larger cards may lead to fewer columns inside narrow sidebar-constrained catalogs (client did not choose a fixed shop sidebar count).

## 4. Product details reading/interaction order (M F-005/F-006/F-007)

```text
Desktop
┌─────────────────────────┬─────────────────────────────────────┐
│ Large borderless media  │ Name                                │
│ Gallery thumbnails      │ Price (updates with variant)         │
│                         │ Color / Size / Weight / Piece       │
│                         │ Available for selected SKU          │
│                         │ Qty [− 1 +]                          │
│                         │ Add to Cart   |  Order/Buy Now       │
└─────────────────────────┴─────────────────────────────────────┘
                      DESCRIPTION / badges / shipping copy / FAQ

Mobile stacking: Gallery → Name → Price → Variant → Available → Qty/CTA
                 → Description → supplementary information
```

- Do not place product description/Free Shipping badge between current price/selection and CTA. Client explicitly requested these underneath (PDF 09:14 and 12:45).
- Variant value change triggers fresh quote or trusted server-provided variant price data, updates selected availability; validation prevents non-existent combination. The example 1 kg vs 2 kg is two admin-configured SKU prices, not automatic total formula.
- Sticky CTA appears when primary CTA leaves viewport; label is **Order Now**, uses the same current variant/qty, and sits above mobile bottom nav with safe-area padding. Prevent two fixed bars from hiding the bottom content.
- Sticky component only on detail page. Contact + and mini-cart remain independent.

## 5. Cart and checkout (M F-008/F-010)

```text
Desktop Checkout:
[Name] [Phone] [Address] [Delivery area]   [Sticky Order Summary]
                                            [Items + qty + variants]
                                            [Subtotal / Shipping / Total]
                                            [অর্ডার করুন]
Mobile Checkout:
[Name] → [Phone] → [Address] → [Delivery area]
[অর্ডার করুন]  ← immediately below form fields, not after long summary
[Order Summary] → [Shipping] → [Totals]
```

- **One semantic form submission**, even if responsive design uses a visually repositioned action; no duplicates on desktop/mobile DOM. Server checks actual cart, selected options, active inventory, final shipping, quote changes and idempotency.
- Desktop sticky summary must respect sticky header offset, stay within scroll container and not obscure footer. Mobile CTA must not be accidentally hidden under mobile nav or keyboard.
- Side mini-cart displays product name, selected options, stock status, price, quantity controls, remove, subtotal and Checkout. Use GET-safe own-cart read; updates use auth/CSRF rules and correct ownership.

## 6. Floating controls / z-index plan (U + M F-009/F-010)

Priority from screen bottom to top on mobile: `safe area` → `sticky bottom nav` → `sticky product Order Now` (only when active) → unobstructed content. Floating contact+/cart must be offset above the active bars and never cover the Checkout submit or drawer Close button. A cart drawer temporarily closes or collapses contact options.

- Floating **contact +**: WhatsApp, Messenger, Email required when configured (user), Phone/Call optional if client approves (PDF). Click opens, second click closes; `Escape` and outside-click close; focus returns to trigger. Unconfigured services hidden.
- Floating **cart**: a separate icon and server-derived item count. Opens mini-cart drawer. Cart values refresh after edits and can never be fake static badges.
- Bottom mobile nav: Home, Menu, Cart; Search optional if functional. `Account` hidden until customer authentication exists. Accessible names, active states and minimum practical touch targets.
- Sticky **header**: keep search/logo semantics from existing reference layout; `position: sticky`/fixed offsets coordinated with drawers.
- Visual tests at 320, 360, 390, 768, 1024 and 1440 CSS px. Reduced-motion setting respected.

## 7. Section color inheritance precedence (U, M F-011)

`section explicit override` → `published global theme setting` → `safe compiled default green/neutral palette`.

Colors are validated `#RRGGBB` (or null for inherit). No admin-injected CSS, CSS selector strings, javascript URLs or arbitrary theme file upload. When the global color changes, only inheriting sections update; explicitly customized sections preserve their own background/button values. High contrast preview and fail-safe text color fallback are required.

## 8. Visual test artifacts to capture

- Desktop header/nested flyout, homepage 4 columns, category sorting before/after publish.
- 320px and 390px homepage 2-column card with 25:75 actions, long Bangla title.
- Product desktop two-part layout, large borderless image; mobile option/price change and description after actions.
- Sticky product **Order Now** before/after main CTA leaves viewport, with bottom nav and contact controls.
- Checkout desktop sticky summary and mobile order button directly after form fields.
- Color change one section vs another (no leakage); global theme changed while an override remains.
- Mini-cart drawer, active contact menu, cart count/empty state.
