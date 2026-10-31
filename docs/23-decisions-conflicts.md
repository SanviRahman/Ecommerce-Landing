# Client Decisions, Conflicts, Approval Gates and Action Tracker

**Source distinction:** `M` = prior AI meeting report in PDF (not audio verified); `U` = direct user request; `C` = code evidence; `P` = proposed engineering choice. Do not claim client signatures or agreed deadlines.

## PDF-reported accepted UI direction

| ID | Meeting time (PDF report) | Decision as reported | State |
|---|---|---|---|
| MD01 | ~01:34-02:18 | Keep current desktop header arrangement and existing Shop by Category appearance/size | M-reported agreement |
| MD02 | ~01:34-02:08 | Expand All Categories into nested subcategories | M-reported requirement |
| MD03 | ~02:19-03:05 | Homepage category-wise sections; reorder on admin; desktop 4 cards/row | M-reported agreement |
| MD04 | ~03:05-03:10 | Mobile 2 product cards/row | M-reported agreement |
| MD05 | ~03:46-03:57 | Retain shop/category filters and increase product card legibility | M-reported requirement |
| MD06 | ~05:42-06:28 | Two-part details; 25% Cart and 75% Buy/Order buttons on cards | M-reported requirement |
| MD07 | ~06:28-07:28 | Size/color/weight or piece variant selection with price change | M-reported requirement; pricing formula unresolved |
| MD08 | ~07:31-08:18 | Desktop sticky checkout summary, mobile CTA beneath form | M-reported requirement |
| MD09 | ~08:18-08:35; 09:40-10:53 | Sticky mobile header/nav and scrolling **Order Now** | M-reported requirement |
| MD10 | ~11:03-11:38 | Green-like theme and dynamic admin background/button picker | M-reported approved idea; exact hex unknown |
| MD11 | ~11:43-12:14 | Floating WhatsApp/Call and separate floating mini-cart | M-reported requirement |
| MD12 | ~12:35-13:44 | Large borderless product image, description below CTAs | M-reported agreement |

## Direct user additions, not inferred from the recording

| ID | Source | Request | Treatment |
|---|---|---|---|
| UD01 | U | Drag-and-drop every relevant homepage section/item | Required structured builder; taxonomy reparent is separate action |
| UD02 | U + S | Every section's background, button and related color settings editable | Required per-section controls, global inheritance |
| UD03 | U | Show available pieces on cart and product detail | Required truthful variant-aware inventory |
| UD04 | U | Contact `+` has WhatsApp, Messenger and **Email** | Required configured channels; PDF's Phone/Call optional |
| UD05 | U | Preserve campaigns and add shop-like features | Required compatibility and regression |

## Open decisions and conflicts - sorted by release risk

| Gate | Evidence/ambiguity | Safe implementation assumption | Must be approved before |
|---|---|---|---|
| G01 | Uploaded ZIP campaign homepage vs PDF describing proyojonerbazar.com shop | Code against ZIP; preserve campaign; stage separate storefront | Any production deployment/URL cutover |
| G02 | 1kg=1499, 2kg=1800 examples vs follow-up `1499 added` statement | Separate **explicit variant price**, never guessed formula | Product pricing release/seed |
| G03 | `2 pieces` variant vs quantity=2; packaging/stock units unknown | Explicit `piece_count` option values with SKU, price; stock unit labelled sellable pack if pack | Variant inventory backfill/live stock badge |
| G04 | Client says green like Desh Bazar; exact HEX not supplied | Green placeholder palette; admin-adjustable | Branding sign-off |
| G05 | User says WhatsApp/Messenger/Email; PDF mentions WhatsApp/Call | Required U channels; Call optional configurable | Floating contacts final QA |
| G06 | Sidebar shop layout/filters kept; desktop 4 cards vs large cards | Homepage 4, mobile 2; shop sidebar responsive card density | Shop layout acceptance |
| G07 | PDF says category click and also hover; exact trigger unspecified | Click-first, hover progressive, keyboard support | Nested menu UI sign-off |
| G08 | Product card ~25% area may be too narrow for `Add to Cart` on mobile | Accessible icon + aria-label on narrow width | UI review |
| G09 | Variable product card Buy Now requires selected variant; meeting does not decide default | Redirect to details/selection, no implicit SKU | Checkout launch |
| G10 | Source code campaign-specific pricing precedence and shipping; meeting omits | Preserve existing policy until explicitly reconciled | Shared storefront/campaign pricing |
| G11 | Existing product stock not yet reconciled; historical orders unknown | No bulk restock/redecrement, dry-run backfill + approved opening stock | Inventory enforcement/live purchase |
| G12 | Customer account shown on reference, not requested | Do not implement unsupported Account/Wishlist links | Navigation design |
| G13 | Client says button at end on checkout, then mobile just below form | Desktop final summary bottom; mobile immediately after form; one submit | Checkout UI QA |
| G14 | Green global palette requested; user requests section-level design overrides | Inherit global default unless section override; test precedence | Homepage publish |
| G15 | Category homepage rows/product cap unspecified | Admin-configured bounded count; no invented numerical client requirement | Homepage content approval |
| G16 | Call recording original not supplied and PDF truncated after CF-06 | Attribute to AI-produced document, not verified original speaker testimony | Client requirement sign-off |
| G17 | Need customer cart persistence duration/user ownership | Server-owned guest cart, secure cookie; retention/privacy policy pending | Cart deployment |
| G18 | Order cancellation/returns/inventory allocations in existing code unclear | Operate ledger transitions only after proven prior allocation | Cancellation/returns go-live |

## Approval workflow

Each gate has one of: `NOT_REVIEWED`, `PROPOSED`, `APPROVED`, `REJECTED`, `DEFERRED` and an evidence URL/page/date and decision owner, if known. In this document all business gates **require review**; none has an independently obtained new client signature. P00 identifies the actual repo, DB engine, inventory owner and production access path.

## Action tracker (no invented person or deadline)

| Action | Responsible role | Priority (engineering) | Deadline | Status |
|---|---|---|---|---|
| Verify ZIP vs live deployment identity and paths | Project owner + developer | Release blocker | Not Confirmed | OPEN |
| Confirm variant/pack stock and unit pricing model | Business owner + inventory owner | Release blocker | Not Confirmed | OPEN |
| Confirm global green HEX/logo/contact channel config | Client or project owner | UI approval | Not Confirmed | OPEN |
| Build scoped phased source modifications | Developer/Codex under human review | Implementation | Not Confirmed | NOT STARTED |
| Run unit/feature/security/mobile/concurrency tests | Developer + QA | Release blocker | Not Confirmed | NOT RUN |
| Approve staging rollout, rollback and production flip | Authorized owner | Release blocker | Not Confirmed | NOT APPROVED |

## Change control

If client later provides original meeting media, real deployed repo or approved decisions, update this register with evidence, then amend `docs/20`, `docs/21`, `database-schema.md`, `docs/08` and relevant tests/prompts **together**. Do not silently overwrite existing code or retroactively mark ambiguous statements as approvals.
