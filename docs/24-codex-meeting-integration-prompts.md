# Meeting-Specific Codex Task Inserts (P00-P10)

**Run one phase at a time.** These inserts supplement, not replace, `docs/15-codex-prompts.md`, `AGENTS.md` and the existing phase gates. Never paste all task blocks into a single Codex run. In each phase request: (1) explain requirement/error, (2) verified root cause, (3) exact files and why, (4) changes, (5) real commands/tests, (6) blocked items, (7) update `docs/17-progress.md`.

## P00: reconcile meeting evidence and actual source

```text
P00 ONLY. Read AGENTS.md, docs/19-meeting-source-and-coverage.md, docs/20-meeting-traceability.md,
docs/21-client-srs-merged.md, docs/23-decisions-conflicts.md and the original source map.
Re-inspect actual project routes/models/views and git status. Produce an explicit report of whether
this codebase has /shop, /category/{slug}, /product/{slug}, and whether the PDF's
proyojonerbazar.com deployment appears identical to this repository. DO NOT assume yes.
The PDF is a 32-page previous AI-produced transcript/report, NOT original meeting media.
Capture G01-G18 decision status without inventing approvals. No application code or DB writes.
```

## P01: regression + route safety

```text
P01 ONLY after P00. Add regression coverage for root mode, default campaign, custom URLs and
campaign purchase rules. Audit collisions with proposed singular /product and /category,
/shop, /cart, /checkout. Preserve present header and Shop by Category (meeting MD01).
Repair only pre-existing P01 issues with reproducible failing tests. Do not claim variant/stock
safety yet. Update the progress matrix with meeting feature IDs affected.
```

## P02: schema + explicit variant options

```text
P02 ONLY. Extend approved migrations using database-schema.md, ERD.md and meeting FR-06.
Support size, color, weight (e.g. 1 kg/2 kg) and piece-count/pack labels as explicit option
values and valid SKU variants, not decorative selectors. Each variant has its own price and stock.
Do NOT infer "2 kg = 2 * 1 kg". Do NOT duplicate simple-product stock among all combinations.
If product-level stock units vs multipacks are unresolved (G03), build migration and disable
live sale/stock claims pending reconciliation. Test fresh + upgrade + idempotent backfill.
```

## P03: shared authoritative inventory and order pricing

```text
P03 ONLY. Implement shared variant-aware inventory and server-side pricing for campaign/manual
orders and later storefront. Tests must cover 1kg/2kg price distinction, quantity multiplication,
aggregate stock, duplicate submission, concurrent last-item sell and cancellation retries.
Do not expose available-pieces badges until stock authority is verified. Record G02/G03/G10/G11
as blockers where necessary. Preserve all historical orders and data.
```

## P04: category, product admin, variants

```text
P04 ONLY. Add nested category editor with valid parent ID, cycle protection, menu ordering,
mobile accordion and keyboard-operable parent expansion contract. Extend product admin to
manage option values (color, size, weight, piece-count) and explicit variant price/availability.
Do not add unrelated customer accounts/wishlists. Add tests for invalid combinations and
orphaned category parent. Preserve current Shop by Category design proportions.
```

## P05: homepage versioning/data sources

```text
P05 ONLY. Support typed category/subcategory/manual-product sections, selected product cap,
manual order and deterministic automatic sort, source validation, publishable revisions.
Meeting requires admin category section serial choice; user additionally requires drag-sort.
Maintain existing campaign landing template. Sections inherit global green-like theme except
when an approved per-section explicit color is saved. Check G04/G14 before visual sign-off.
```

## P06: drag editor + palettes

```text
P06 ONLY. Build AdminLTE/Blade section editor with drag handles + keyboard Move Up/Down,
source selectors, visibility, color pickers (background, accent, heading/body, primary button
background/text/hover/border), CTA fields and preview. Show true saved status and 409 conflict
resolution; never display fake success. Preserve meeting Shop by Category dimensions.
Use section-local CSS variables and inherit/reset. Test changed global theme vs explicit overrides.
```

## P07: meeting-accurate storefront visuals

```text
P07 ONLY. Add separate storefront views. Meeting contract:
1) desktop homepage product grid 4 columns, mobile 2;
2) existing header and Shop by Category layout retained;
3) nested All Categories accessible desktop/mobile;
4) product card cart action ~25%, Buy/Order Now ~75%; narrow cart button may be accessible icon;
5) product details large borderless left media + right options/price/actions, all descriptions/badges BELOW CTA;
6) price/availability updates on real selected SKU;
7) scroll-triggered sticky "Order Now" ABOVE sticky mobile Home/Menu/Cart nav;
8) independent floating contact+ (WhatsApp/Messenger/Email) and floating cart icon.
Phone/Call optional only; Account/Wishlist hidden if not implemented. Test 320/390/1440 and
no overlay/long Bangla copy regressions. Never overwrite campaign page view.
```

## P08: cart, mini-cart and responsive checkout

```text
P08 ONLY. Build actual guest-owned persistent cart and side mini-cart drawer. Source of truth
is server cart+quote, not JS/localStorage or screenshot-style badge. Validate stock/variant at
add/update/checkout. Product "Buy Now" with required unselected variant opens selection
instead of guessing. Desktop checkout has sticky order summary; mobile has exactly one
semantic "অর্ডার করুন" submit immediately after name/phone/address/delivery fields,
followed by summary. Sticky Order Now in product detail must use current variant/quantity.
Preserve existing COD/courier/report behavior and test unauthorized cart tampering.
```

## P09: meeting acceptance/regression

```text
P09 ONLY. Run docs/12-testing-and-acceptance.md AND docs/25-meeting-tests.md.
Capture responsive screenshots proving 4/2 grids, 25/75 buttons, product detail description
order, sticky Order Now, checkout mobile submit location, mini-cart/contact independence and
per-section colors. Browser UI appearance alone is not proof of storage/order correctness.
Run DB-engine concurrent stock tests, regression campaigns and negative security cases.
Mark each M-ID and R-ID PASS/FAIL/BLOCKED with actual artifacts/commands/results.
```

## P10: controlled release

```text
P10 ONLY after P09 green. Present migration/backfill dry-run, stock reconciliation report,
original campaign regression results, approved branding/variants/shipping/contact decisions,
asset-build evidence, rollback plan and owner sign-off. Do not deploy autonomously.
Production mode flip/URL cutover requires approval separate from coding completion.
```

## Post-meeting source reconciliation prompt

```text
Read the 32-page original PDF in sources/client-meeting-provided.pdf and compare meeting F-001
through F-011 against docs/20, docs/21 and the currently implemented feature matrix.
The original audio/video is NOT included; never claim firsthand transcript verification.
Generate a delta report grouped into verified user requirement, PDF-reported meeting request,
project code fact and proposed default. If an ambiguity changes pricing/stock/site identity,
keep the release gate blocked and request a targeted approval instead of guessing.
Do not rewrite already-working modules unrelated to the accepted delta.
```
