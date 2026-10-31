# Meeting Feature-to-Implementation Traceability Matrix

This document reconciles the **11 F-IDs actually visible in the supplied meeting PDF** with the user's direct requests, the earlier R-ID specification and Codex phases. Meeting times are **reported by the PDF**, not independently verified audio timestamps. `P` in a row denotes proposed engineering design, not meeting approval.

| Meeting ID | PDF page | Agreed request as reported | Related R-IDs | Phase(s) | Primary UI / workflow acceptance |
|---|---:|---|---|---|---|
| F-001 | 23-24 | Multi-level categories under All Categories (Electronics→Fans/Lights; Fashion→Shirts/Pants) | R03, R18 | P04, P07 | Parent expands submenu; leaf opens corresponding filtered listing; admin can manage parent |
| F-002 | 24-25 | Category-based homepage sections; admin orders category-section blocks | R04, R05, R19 | P05-P07 | Admin swaps Gadget before Kitchen; published homepage follows order; no change to category parenthood |
| F-003 | 25 | Product grid exactly four columns desktop, two mobile; enlarge catalog cards | R20 | P07, P09 | 1440px→4; 390px/320px→2; no button/title overflow; shop filters preserved |
| F-004 | 25-26 | Product card buttons: one-quarter cart action, three-quarter Buy/Order Now | R21 | P07-P08 | Order Now primary ~75%, cart secondary ~25%; both actionable; no fake no-op button |
| F-005 | 26-27 | Product details two-part; big borderless image; CTA before description/badges | R22 | P07 | Desktop image-left/info-right; mobile image→title/price/variants/actions→description |
| F-006 | 27 | Color, size and weight/piece options update actual price | R02, R06, R23 | P02-P04, P07-P08 | 1 kg and 2 kg are distinct selected variants with own admin price and stock; cart/checkout quote server-authoritatively |
| F-007 | 27-28 | On scrolling away from main CTA, bottom sticky **Order Now** | R24 | P07-P08 | Show after primary CTA exits viewport; selected variant/qty; not hidden behind mobile nav |
| F-008 | 28 | Desktop sticky order summary, mobile submit below customer form | R25 | P08 | Total/shipping visible; one authoritative submit; order summary follows mobile CTA |
| F-009 | 28-29 | Sticky mobile header and Home/Menu/Cart navigation | R12, R26 | P07 | Navigation remains accessible; no floating overlaps |
| F-010 | 29 | Floating WhatsApp/Call and **separate** cart icon opening slide-out mini-cart | R07, R12, R27 | P07-P08 | Cart count/live items/subtotal; contact button independent; direct user additionally requires Messenger/Email |
| F-011 | 30 | Default green and admin-selectable background/button colors | R08, R09, R28 | P05-P07 | Global defaults are green; independent section background/text/button override, safe contrast and inheritance |

## Additional direct-user-only features (do not attribute to meeting recording)

| Source | Requirement | R-IDs | Phase |
|---|---|---|---|
| Direct user + Leatherket screenshot | Product count and per-variant available pieces on details/cart; product count in sections; optional badges | R06 | P03/P04/P07/P08 |
| Direct user | Drag-and-drop **homepage section/order/item** administration rather than only numeric serial fields | R05, R10, R15, R19 | P05/P06 |
| Direct user + agency-admin screenshots | Section-specific background, heading, accent, button, text, hover and visibility controls | R08, R09, R15, R28 | P05/P06 |
| Direct user | Floating `+` reveals **WhatsApp, Messenger, Email** | R07, R27 | P07 |
| Direct user / inspected source | Existing campaigns, order history, courier/report must not break | R01/R14/R16 | All gates |

## Explicit preserve/don't-redesign evidence

- Transcript p.7-8, ~01:34: client says keep current desktop header/navigation layout, not move logo centrally merely because HT Bazar does so.
- Transcript p.8, ~02:09: client says **keep the existing Shop by Category section size/layout**. Make it data-driven without unsolicited visual redesign.
- Transcript p.9-10, ~03:46: preserve current `/shop` filtering behavior; only enlarge cards appropriately.
- Transcript p.18-19, ~12:45-13:44: HT Bazar is a **reference**, but descriptions and shipping text should be **below** actions like Leatherket; do not blindly clone HT Bazar's detail layout.

## Contract decision points

1. **PDF vs ZIP identity:** PDF describes proyojonerbazar.com; ZIP is a campaign-based Laravel codebase with shared landing view. Implement against the inspected ZIP only; do not claim live proyojonerbazar.com's existing Shop UI is present in the ZIP.
2. **Routes:** PDF observes `/product/{slug}` and `/category/{slug}` on example site; code-base proposed contracts formerly used plural `/products/` and `/categories/`. This merged pack recommends **singular canonical paths pending route-collision audit**, no unreviewed redirects. See docs/08.
3. **Green hex:** client names Desh Bazar-like green, no numeric hex; do not claim `#008000` is the approved brand value just because PDF uses it as an example.
4. **Variant multiplication:** 2 kg option price is a total for one selected sellable variant when explicitly configured. Quantity = count of that variant's units, not additional kilograms. Do not infer a pricing formula beyond data.
5. **Checkout mobile position:** PDF says mobile Order button just below the contact/address form; desktop button at end of order summary. Use responsive positioning of the **same submit action**.
6. **Phone contact:** PDF shows call option; user's written request specifically says WhatsApp/Messenger/Email. Treat call as optional configurable (not required mandatory).
7. **Desktop grid:** exactly 4 columns on practical desktop homepage grid. Filter sidebar shop page may need its own tested responsive breakpoint (don't crush cards); verify actual acceptance.
8. **Account/wishlist:** not approved; don't invent frontend auth or link to admin login.
9. **Drag-and-drop means structured blocks and curated item order, not arbitrary text/DOM positioning.**

## Acceptance logic for Codex

Each meeting F-ID is accepted only when its requirement has: implementation file references, validated server behavior if interactive, a passing dedicated test, screenshot at desktop and mobile when visual, and a populated evidence/status field in `docs/17-progress.md`. F-IDs refer to document-reported requests, not test pass results.
