# Testing and Acceptance Matrix

**Status at delivery:** these are required tests, not passed application tests. Only the static parser audit in reports/php-syntax-check.json was run.

## Test environment

Create tests/TestCase.php, factories and real test classes; the supplied ZIP has no tests directory. Use a disposable testing database and fake media. Assert APP_ENV=testing and a non-production database identity before destructive fixtures. Use Http::fake/Mail::fake/Queue::fake where appropriate; do not issue courier, external sync, email or SMS requests.

Use SQLite for portable unit/HTTP tests where supported. Run inventory/concurrency and migration compatibility on the **actual deployment DB engine/version** in isolated CI/staging. Create a separate phpunit.concurrency.xml or explicit suite configuration; never point these tests to production. No claim of race safety from SQLite-only success.

## Baseline/campaign

| ID | Scenario | Expected |
|---|---|---|
| T01 | Root in campaign mode with default/latest active campaign | Current selection/render preserved |
| T02 | Standard and custom campaign URLs, inactive campaign | Valid routes work; invalid/inactive denied as current policy requires |
| T03 | Root custom route collides with new reserved path | Readiness audit flags conflict; no silent disappearance |
| T04 | Existing campaign order, shipping, blocks, assignment, success token | Existing behavior preserved with secure stock checks |
| T05 | Campaign unrelated active product/variant | Rejected atomically; no order or movement |
| T06 | Campaign order without variant for simple versus variable product | Simple default resolves; variable missing selection rejected |
| T07 | Visit/submit campaign while storefront cart has items | Storefront cart unchanged |
| T08 | Existing order tracking/report/courier payload fixtures | Null campaign storefront orders safe; history intact |

Proposed files: tests/Feature/Regression/CampaignFlowTest.php, ExistingOperationsTest.php, ReservedRoutesTest.php.

## Catalog/variants/inventory

| ID | Scenario | Expected |
|---|---|---|
| T09 | Simple default-variant backfill run twice | Same row/stock/ledger; no multiplication |
| T10 | Convert 10 pieces to Black/L=4, Black/XL=6 | Total remains 10; generic pool no longer sold |
| T11 | Duplicate option signature, foreign value/option, duplicate SKU | Validation/DB constraint rejects |
| T12 | Category self-parent, cycle, third level, hidden parent | Invalid hierarchy denied; descendants hidden publicly |
| T13 | Selected variant's stock versus card total | Correct different counts shown |
| T14 | Disabled/deleted/zero-stock combination | Cannot purchase; no auto-substitution |
| T15 | Quantity duplicate rows summed over cap/stock | Aggregate rejected |
| T16 | Stock 1; two independent simultaneous requests quantity 1 | Exactly one order/movement; second rejected; stock 0 |
| T17 | Multi-line order where last line lacks stock | Full rollback; no partial customer order/stock/ledger completion |
| T18 | Repeated cancel/replayed webhook | Release once only |
| T19 | Ship then courier-cancel | No warehouse stock credit until approved return received |
| T20 | Change line 2 to 5 then variant to another SKU | Correct delta; atomic release/acquire |
| T21 | Stale admin product stock form submitted after a sale | No lost sale overwrite; conflict or audited delta |
| T22 | Legacy order cancel without allocation proof | No inferred restock |
| T23 | Imported unmapped external order | No local stock deduction or guessed SKU |
| T24 | Failed transaction/deadlock retry | Stable event identity; no duplicate ledger/order |
| T25 | Product 1000 BDT and manual discount 0.50 | 999.50 persisted/displayed/exported, no float rounding/truncation |
| T26 | Product price inherited versus override versus fixed campaign price | Correct resolution, no double add |

Files: tests/Feature/Catalog/ProductVariantTest.php, CategoryHierarchyTest.php; tests/Feature/Inventory/InventoryLifecycleTest.php, LegacyReconciliationTest.php; tests/Integration/Concurrency/LastItemRaceTest.php; tests/Unit/MoneyCalculatorTest.php.

## Builder/revisions/style

| ID | Scenario | Expected |
|---|---|---|
| T27 | Add/reorder/save/reload sections | Stable order and style association |
| T28 | Reorder category/product selections | Scoped order only; actual taxonomy/campaign order unchanged |
| T29 | Category/manual mode versus automatic sort | Explicit curated subset versus correct bounded query |
| T30 | Duplicate/missing/foreign IDs or unknown component type | 422/403, no partial draft change |
| T31 | Two tabs save with same expected_version | One update succeeds, stale one 409; no overwrite |
| T32 | Change draft background/button colors | Preview updates; live page stays unchanged |
| T33 | Publish or restore and republish | Atomic new snapshot; prior revision immutable |
| T34 | Invalid draft/missing required image | Publish blocked with field errors; current live page unchanged |
| T35 | Change section A's palette | Section B, header, campaign and admin CSS unaffected |
| T36 | Inherit/reset color, global theme change | Correct fallback cascade; no invalid empty CSS |
| T37 | CSS/script values in colors/URLs/content | Rejected or rendered inert; no execution |
| T38 | Preview requested by guest or employee | Denied; no draft leaks/shared cache |
| T39 | Product becomes inactive after publication | Public section skips safely; editor flags stale reference |
| T40 | Replace/trash media referenced by old published revision | Original remains accessible or operation blocked safely |
| T41 | Keyboard/tap Move Up/Down and touch drag | Same persisted result, focus and announcements correct |
| T42 | Low contrast and image-overlay readability | Feedback/publish gate works; no color-only error messages |

Files: tests/Feature/Homepage/DraftLifecycleTest.php, PublicationIsolationTest.php, SectionSelectionTest.php, ThemeValidationTest.php, RevisionMediaTest.php; tests/Browser/HomepageBuilder.spec.js.

## Cart/checkout

| ID | Scenario | Expected |
|---|---|---|
| T43 | Cart reload/new page, distinct guest cookies | Persistence, no cross-owner access |
| T44 | Tamper another guest cart/item ID | Denied without leaking details |
| T45 | Same variant add twice/different variants | Aggregate versus separate lines, correct stock cap |
| T46 | Changed price/removed shipping/stock decrease after cart load | Fresh error/quote; no stale-priced order |
| T47 | Manipulated client prices/totals/variant product ID | Server authority and ownership validation |
| T48 | Identical retry after commit but response timeout | Same order/success token, one stock allocation |
| T49 | Same idempotency key with different payload | 409; no second order |
| T50 | Concurrent checkout and cart mutation | Version handling prevents clearing newer items |
| T51 | Empty/expired/unavailable cart | Clear UI, no false success/no stock restore |
| T52 | Global versus campaign shipping/free-delivery policy | Correct scope and confirmed D05 behavior |

Files: tests/Feature/Storefront/CartOwnershipTest.php, CartVersionTest.php, CheckoutTest.php, CheckoutIdempotencyTest.php, ShippingScopeTest.php; tests/Browser/StorefrontCheckout.spec.js.

## Security/UI/operations

| ID | Scenario | Expected |
|---|---|---|
| T53 | Guest/employee builder and stock mutations | Authorization enforced server-side |
| T54 | Missing/invalid forgery protection on mutations | Request rejected with actual configured middleware behavior |
| T55 | Maintenance GET routes | No state changes; production migrate/seed endpoints absent |
| T56 | Invalid image type/size/dimensions/path and unsafe HTML | Rejected/inert with useful field messages |
| T57 | Contact + open/close, Escape, links and missing contacts | Correct channels, safe links, focus return |
| T58 | 320/360/390/768/1024/1440px, zoom and mobile keyboard | No overflow/overlap; visible primary actions |
| T59 | Cart unit count/subtotal across widgets | All badges consistent; shipping distinction clear |
| T60 | Reduced-motion/autoplay and screen-reader controls | Pause/respect preference; accessible labels |
| T61 | Dependency install/build/view/route cache | No build/template/cache errors on supported runtime |
| T62 | Upgrade and fresh migrations; backup restore rehearsal | Data retained; schema and stock reconciled |
| T63 | Logging/secret scan | No raw tokens, .env or customer payload leaks |
| T64 | Products/media query count and image loading | No N+1 growth per card; bounded selections |

Files: tests/Feature/Security/StorefrontAuthorizationTest.php, UploadValidationTest.php, MaintenanceRoutesTest.php; tests/Browser/ResponsiveWidgets.spec.js; CI/staging check records.

## Concurrency harness requirements

Use separate connections/processes and a synchronization barrier so both requests contend for the same initial stock. Test campaign+storefront, storefront+manual and duplicate-idempotency races. Assert orders count, exact remaining stock, ledger cardinality, item allocated quantities and full transaction rollback. Repeat under the deployment engine. Do not count a sequential 'two requests in a loop' as a concurrency test.

## UI test evidence

Browser screenshots are captured after implementation at agreed viewport sizes. Compare campaign baseline images before/after and verify chosen section styles through computed CSS, not screenshots alone. Use real drag and Move Up/Down interactions; assert DB/API persistence after reload. Tests must fail when save endpoints are stubbed or disconnected.

## Release requirements

All owned mandatory tests pass; no unresolved P0/P1 release blockers; no empty test suites; documented approved defaults; staging migration/restore success; source/media/stock counts reconcile; no real third-party calls during testing. Test logs state actual versions/engines and skipped tests. Release approval is separate from a green local parser check.

## Meeting-focused additional tests

Run **MT01-MT45** from [docs/25-meeting-tests.md](25-meeting-tests.md) in addition to existing acceptance cases. This adds explicit 4 desktop/2 mobile layout checks, 25/75 card CTA actions, image borderlessness and description order, weight/pack pricing, sticky Order Now, mobile submit-after-form behavior, separate mini-cart vs contact+, and global/section color precedence. Mark actual test outcomes only after live execution in the implementation environment.
