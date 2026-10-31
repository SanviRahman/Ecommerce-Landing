# Meeting-Paired Developer Backlog and File Impact (P00-P10)

All tasks are **TODO** until the developer/agent applies and tests source changes in a safe repository. File paths are **candidates based on inspected ZIP**; verify exact locations/routes before editing. `M` IDs are taken from a prior AI-produced meeting PDF, not first-hand audio verification. Use `docs/11-file-impact-map.md` for the full earlier baseline and `docs/23-decisions-conflicts.md` for approvals.

| Task ID | Source | Phase | Feature | Files to inspect/change | Concrete completion evidence |
|---|---|---|---|---|---|
| DEV-M00 | C/U/M | P00 | Source/route/live identity audit | `composer.json`, `composer.lock`, `routes/web.php`, `routes/user.php`, `routes/admin.php`, controllers, `config/campaign-routes.php` | Actual repo/DB version, route diff and G01 disposition recorded |
| DEV-M01 | C | P01 | Preserve campaign and fix membership | `app/Http/Controllers/Admin/CampaignOrderController.php`, request tests, custom route config | Rejected foreign product ID, existing campaign regression test |
| DEV-M02 | M F-001 | P02/P04/P07 | Nested category menu and admin CRUD | existing `app/Models/Category.php`, CategoryController, category migration, storefront header/category views | Parent selection, cycle validation, keyboard submenu, leaf filter |
| DEV-M03 | U/M F-002 | P05/P06 | Category section ordering and builder | new HomepagePage/Revision/Section model/service/controller, `routes/admin.php`, `resources/views/admin/homepage-builder/**` | Draft reorder, persisted after refresh; publish changes only live section order |
| DEV-M04 | M F-003 | P07 | Responsive grid and larger cards | new storefront layout/product card CSS and category/shop Blade | Desktop 4; mobile 2; no overflow; sidebar filters remain functional |
| DEV-M05 | M F-004 | P07/P08 | Card 25/75 actions | new reusable storefront product-card component; cart actions/routes | Both commands work with variant-aware routing, accessible narrow icon |
| DEV-M06 | M F-005 | P07 | 2-part product details | new storefront product details Blade and CSS, Product show controller | Borderless large left media; action before descriptions/badges; mobile order correct |
| DEV-M07 | M F-006/U | P02-P04/P07 | Size/color/weight/pack variant prices and stock | `app/Models/Product.php`, new variant/option models, product form/controller, migration, InventoryService | Per-variant SKU stock/price; `1kg=1499`, `2kg=1800`; no assumed formula |
| DEV-M08 | M F-007 | P07/P08 | Sticky Order Now on product scroll | detail Blade, JS IntersectionObserver, cart/checkout integration | Shows only after primary CTA out of viewport, selected options, no nav overlap |
| DEV-M09 | M F-008 | P08 | Responsive checkout | storefront checkout Blade/FormRequest, checkout controller/service | Desktop sticky order summary; mobile button immediately after form; one actual submit |
| DEV-M10 | M F-009 | P07 | Sticky header and mobile bottom nav | storefront master layout, responsive CSS, accessible menu drawer | Home/Menu/Cart works, no false Account link or CTA overlap |
| DEV-M11 | M F-010/U | P07/P08 | Contact + / floating cart drawer | contact settings, storefront contact partial/JS; CartController/summary | WhatsApp/Messenger/Email and optional Call; independent real cart drawer/count |
| DEV-M12 | M F-011/U/S | P05-P07 | Green global and local section/button colors | homepage section style resolver, setting columns/JSON, admin Design tab | Global inheritance; independent section colors; safe HEX and contrast |
| DEV-M13 | C/U | P03 | Safe inventory across campaigns & shop | `app/Services/OrderManagementService.php`, CampaignOrderController, Order/OrderItem, new InventoryService, ledger | Last-item/concurrent/cancel/retry tests; stock reconciled |
| DEV-M14 | U/C | P08 | Guest cart, accurate mini cart and direct checkout | new cart models/migrations, routes, storefront controllers/partials | Server-owned cart, price/stock validated, ownership & idempotency |
| DEV-M15 | M/U/C | P09 | Browser + security regression | new `tests/Feature/**`, `tests/Unit/**`, browser harness, `docs/17-progress.md` | MT01-MT45 outcomes, screenshots, race tests, exact commands |
| DEV-M16 | U/C | P10 | Deployment/rollback | release scripts/process docs, feature flag, backups/staging, logs | Approved staging test, inventory reconciliation, reversible mode flip |

## Developer dependency diagram

```mermaid
flowchart LR
 P00[Verify source] --> P01[Guard existing flows]
 P01 --> P02[Schema]
 P02 --> P03[Inventory core]
 P03 --> P04[Variants and categories]
 P04 --> P05[Builder models]
 P05 --> P06[Admin drag/colors]
 P06 --> P07[Storefront UI]
 P07 --> P08[Cart/checkout]
 P08 --> P09[Acceptance tests]
 P09 --> P10[Release approval]
```

The diagram is a simplified milestone sequence; actual dependency edges from `docs/14-implementation-sequence.md` are authoritative.

## Standard per-task change instruction to Codex

1. State problem/request and meeting F-ID or U reference (with PDF page and reported timestamp).
2. Describe existing **verified** Laravel flow from inspected source; separate expected behavior.
3. List exact existing and proposed new file paths, each with **why** and **what**.
4. Implement only scoped phase; reuse existing models/media/roles/routes where possible.
5. Before code: failing test for a confirmed bug. After: test application, auth, validation, concurrency or responsive behavior as relevant.
6. Record commands/results and modify docs/17 progress. Leave business decision gates BLOCKED, not invented.
7. Do not run destructive DB/seeding, deploy production or promise zero errors.
