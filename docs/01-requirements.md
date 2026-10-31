# Requirements and Traceability

Sources: U = user's direct written requirement; S = supplied screenshot; C = inspected uploaded code; M = meeting feature **reported by previous AI in supplied 32-page PDF (not audio verified)**; P = proposed engineering default. Read `docs/19` and `docs/20`.

| ID | Requirement | Source | Required acceptance |
|---|---|---|---|
| R01 | Preserve campaigns and existing operations | U/C | Old standard/custom URLs, order tracking, courier/report flows pass regression |
| R02 | Size/color product selection | U/Leatherket S | A real variant_id with valid option combination is selected |
| R03 | Category + subcategory hierarchy | U | Valid two-level tree; no self/descendant loops |
| R04 | Per-section category/subcategory/manual products | U | Source filters and product limits are honored |
| R05 | Section and item drag sorting | U | Order persists after refresh; keyboard/tap alternative works |
| R06 | Available pieces on cards/details/cart | U | Variant-aware quantity, no misleading count, checkout revalidates |
| R07 | Floating + with WhatsApp, Messenger, Email | U | Validated configured channels open; empty channels hidden |
| R08 | Per-section background and button colors | U/S | Changing one section never recolors another |
| R09 | Heading, text, accent and hover colors | S/P | Validated tokens, preview and inherit/reset behavior |
| R10 | Draft, preview, publish and restore | P | No unpublished leak; publish is atomic; version conflicts return 409 |
| R11 | Product detail and guest cart | U/integration necessity | Variant selection, persistence, own-cart protection, checkout |
| R12 | Floating cart + mobile bottom nav | HT Bazar S/P | Accurate count/amount and no overlap; unavailable Account/Wishlist hidden |
| R13 | Hero slides, review/media sections | S/P/C | Admin-managed content with safe media and stable order |
| R14 | Server inventory and idempotent order creation | C/P | Last-item race and duplicate-submit tests pass |
| R15 | Per-section content/visibility/layout editing | U/S | Enabled state and desktop/mobile visibility render correctly |
| R16 | Safe migration and legacy-data preservation | C/P | No deleted orders/media, correct stock backfill/reconciliation |
| R17 | Implementation documents for Codex | U | Ordered prompts, schema, ERD, source map and test gates delivered |
| R18 | Accessible nested All Categories submenu; preserve existing header | M F-001, MD01 | Parent expands, leaf filters, keyboard/click works |
| R19 | Category-wise section order + product cap; preserve Shop by Category appearance | M F-002 + U | Drag/save/publish ordering, no taxonomy reparent |
| R20 | Homepage 4 columns desktop, 2 mobile; larger shop/category cards | M F-003 | Visual/browser tests at 320/390/1440; filters unchanged |
| R21 | Product card 25% Cart + 75% Buy/Order Now | M F-004 | Actual actions and accessible compact icon when needed |
| R22 | Two-part borderless-image product details; description beneath actions | M F-005 | Correct DOM/mobile reading order and screenshot proof |
| R23 | Weight and piece/pack as explicit variant option types and price | M F-006 | Per-SKU price; no guessed arithmetic; stock units reconciled |
| R24 | Scroll-triggered sticky **Order Now** on detail page | M F-007 | Selected SKU/qty, no mobile-nav overlap |
| R25 | Sticky desktop checkout summary; mobile action after form | M F-008 | Single semantically valid submit and correct totals |
| R26 | Sticky mobile header and Home/Menu/Cart nav | M F-009 | Available actions, count, safe-area/no overlap |
| R27 | Independent floating mini-cart and contacts | M F-010 + U | Cart real drawer; contact+ WhatsApp/Messenger/Email, Call optional |
| R28 | Green-like default + admin global/per-section color pickers | M F-011 + U/S | Valid HEX, inheritance, no style leakage, contrast |

## Screenshot interpretation

**Leatherket section screen:** Category Name, Max Products, Sorting, Status; sidebar Products/Option/Size/Color/Section. Implement those business capabilities, not the reference's PHP controller code or numeric-sort limitations.

**HT Bazar screens:** product collection sections, discount labels, product cards, reviews, floating cart, compact mobile nav and expandable contacts. The external phone-preview frame and operating-system tray are not site requirements. A discount label is shown only when the former price is valid and greater than current price.

**New admin Hero screenshot:** eyebrow, headline, description, primary/secondary CTA label/URL, background/accent/heading color controls and autoplay. Map to typed hero settings, section styles and item data.

**New admin Spotlight screenshot:** heading/subheading/description, background/accent, autoplay and visibility. Map the generic controls; do not add an agency logo/video Spotlight module as a required commerce feature without client approval. Optional video remains a defined extension, not an invented meeting item.

## Drag-and-drop boundary

Drag is for ordering supported sections, slides, selected product lists, selected category tiles, review cards, contact-channel order and mobile-nav item order. Persistent positioning is responsive and constrained. Floating widgets use left/right and safe-gap controls, not arbitrary X/Y coordinates. Category reparenting is an explicit hierarchy edit, not an accidental consequence of merchandising reorder.

All controls have labels and loading/error/saved states. Invalid operations must be rejected server-side even if the UI disables them.

## Definition of done

Each R-ID must map to implementation files and tests in docs/17-progress.md. A static mock, hardcoded badge, fake cart or unsaved drag animation is not implementation. Storefront publication is blocked until R01-R28 acceptance passes and pending client choices affecting inventory/shipping are resolved or explicitly accepted as documented defaults.

## Meeting conflict resolution

M F-001..F-011 are detailed in [meeting traceability](20-meeting-traceability.md) and [merged SRS](21-client-srs-merged.md). Where an earlier generic recommendation conflicts with an M-specific visual target (e.g., unspecified columns or button ratio), use the concrete meeting targets. Where the M document conflicts internally (e.g. variant price formula), do not invent an agreement: see [decision gates](23-decisions-conflicts.md). F-IDs in this report are PDF-derived; complete audio/transcript verification is not claimed.
