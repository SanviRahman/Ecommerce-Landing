# Meeting-Specific Acceptance / QA Cases

**Supplement to `docs/12-testing-and-acceptance.md`.** Tests below derive from PDF-reported meeting features (M) and direct user additions (U). All statuses `NOT RUN` on documentation delivery. Implement PHPUnit Feature/Unit and responsive browser tests in the actual repo; the merged docs do not certify functioning production software.

| Test ID | Requirement | Scenario / action | Expected result |
|---|---|---|---|
| MT01 | F-001 | Open All Categories, activate Electronics | Child Fan/Light displayed; parent does not prematurely navigate |
| MT02 | F-001 | Select child Fan | Only permitted visible products in Fan category shown; correct breadcrumb |
| MT03 | F-001 | Keyboard Tab/Enter/Escape submenu | Operable without hover/mouse; closes and focus retained |
| MT04 | F-001 | Admin sets invalid parent/cycle | Server rejects; no corrupted category tree |
| MT05 | F-002 | Admin place Gadget ahead of Kitchen; Save Draft | Draft order stored; public order unchanged |
| MT06 | F-002 | Publish authorized valid draft | Gadget above Kitchen on public; old revision retained |
| MT07 | U drag | Drag product within one manual section | Only local section selection order changes, not global category/product order |
| MT08 | U drag | Duplicate/missing/foreign IDs in reorder payload | 422; transaction rolls back; public unchanged |
| MT09 | F-003 | Load homepage at 1440 CSS px | Four cards per standard product row |
| MT10 | F-003 | Load homepage at 390 and 320 CSS px | Two cards per row; text/buttons stay within viewport |
| MT11 | F-003 | Use Shop category/price filters | Existing filtering still works; larger card layout not cropped |
| MT12 | F-004 | Compare product card secondary/primary CTAs | Cart ~25%, Buy/Order Now ~75% on ordinary desktop/mobile layout |
| MT13 | F-004 | Tap narrow cart icon with assistive tech | Accessible "Add to Cart" name; actual cart updates; no no-op |
| MT14 | F-004/F-006 | Buy Now on variable product with no chosen option | Opens valid option selection; no implicit arbitrary variant |
| MT15 | F-005 | Desktop product details | Borderless large media left, price/variants/actions right; no third purchase sidebar |
| MT16 | F-005 | Inspect description and shipping badge placement | Both below Buy/Order Now buttons on desktop and mobile |
| MT17 | F-006 | Admin SKU A 1 kg 1499 BDT, SKU B 2 kg 1800 BDT | Selecting option updates UI price 1499 → 1800 without multiplying kg |
| MT18 | F-006 | SKU B 2 kg price 1800, cart quantity 3 | Merchandise subtotal is 5400 BDT (other charges excluded) |
| MT19 | U stock | SKU A stock 2, SKU B stock 0 | Selected availability 2/0; out-of-stock B cannot purchase |
| MT20 | F-006/U | Submit tampered `price`, variant ID not in product | Server rejects mismatch/ignores client price; no order priced from browser |
| MT21 | F-007 | Main product CTA visible, then scroll past it | Sticky Order Now absent initially, then appears once below primary CTA |
| MT22 | F-007 | Change variant/qty, tap sticky Order Now | Correct selected SKU & qty reaches checkout; invalid state is blocked |
| MT23 | F-007/F-009 | Show sticky CTA and mobile bottom nav together | Both tappable; no overlay on safe-area/footer or input fields |
| MT24 | F-008 | Scroll desktop checkout | Right order summary remains sticky and displays real totals |
| MT25 | F-008 | Fill name/phone/address on mobile | "অর্ডার করুন" directly after form, summary below, no duplicate submission |
| MT26 | F-008 | Submit empty cart or missing required phone/address | Validation, no order row or inventory decrement |
| MT27 | F-009 | Scroll mobile page with Home/Menu/Cart bar | Header and bottom nav persist and remain accessible |
| MT28 | F-010 | Tap floating cart and update qty | Independent right drawer opens, count/subtotal reflect server authoritative state |
| MT29 | F-010/U | Tap floating + with all 3 links configured | WhatsApp, Messenger, Email appear; links validated and operate safely |
| MT30 | F-010/U | Close contact menu, open mini-cart | No overlapping menu, trap or obstructed drawer close control |
| MT31 | F-011 | No custom palette configured | Readable green-like compiled default without invalid/blank CSS |
| MT32 | F-011/U | Change background/button color on section A only | Section A changes; section B unchanged; persists after publish/refresh |
| MT33 | F-011/U | Change global theme while section B has override | Inheriting sections change; B retains override; reset restores inheritance |
| MT34 | F-011 | Input malicious CSS string / bad hex | Server rejects; no injected style/script |
| MT35 | U/C | Open a legacy campaign landing and submit its allowed SKU | Campaign appearance/order, attribution/courier/report behavior preserved |
| MT36 | C | Modify campaign hidden product to unrelated active product ID | Rejected; no unauthorized campaign item |
| MT37 | C | Two independent checkouts attempt last SKU | At most one succeeds; no negative stock, ledger consistent |
| MT38 | C | Repeat checkout/cancel retry | Same idempotency key doesn't create duplicate order or stock movement |
| MT39 | U/C | Non-admin tries draft publish or stock adjustment | 403; existing data unchanged |
| MT40 | U/C | Reorder in two tabs against stale version | Second stale update 409; no silent overwrite |
| MT41 | M/U | Check 320px while keyboard is open | Floating widgets/nav cannot conceal active checkout form/submit |
| MT42 | U | Reopen published homepage after draft-only color/content edits | Public reflects published version only, no draft content leak |
| MT43 | U/C | Product with `2-piece pack` SKU and quantity=1 | Units/stock label matches approved pack accounting rule; test BLOCKED until G03 approved |
| MT44 | C | Product 10 stock split to 3 variants on backfill | Reconciled total remains 10; no 30-quantity stock multiplication |
| MT45 | M source QA | Run acceptance against actual ZIP route/controller, not assumed proyojonerbazar.com deployment | Any ZIP-to-live mismatch logged, release blocked if unresolved |

## Test evidence log template

```text
Test ID:
Source commit/ref:
Environment/database engine/browser/viewport:
Preconditions/fixtures:
Command or reproducible UI steps:
Observed response/status:
Expected result:
PASS / FAIL / BLOCKED / NOT RUN:
Artifact path (screenshot/test report):
Relevant changed files:
Known limitations:
```

## Test release gates

- P07 cannot claim UI done without passing MT01-16, MT21-23, MT27 and responsive screenshots.
- P08 cannot claim checkout done without MT17-20, MT24-26, MT28, MT35-38 and independent database stock tests.
- P06 cannot claim admin done without MT05-08, MT31-34, MT39-40, MT42.
- MT43 is intentionally approval-blocked until pack-vs-piece inventory accounting clarified.
- All test results remain `NOT RUN` in this document; record actual results only after implementation.
