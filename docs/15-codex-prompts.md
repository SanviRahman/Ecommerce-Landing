# Codex Task Prompts - Run in Order

Copy **one** phase prompt at a time into Codex running in the existing Laravel repository root. AGENTS.md contains the permanent rules; do not paste all project documents into every task or ask for every phase in one turn. Resume from the actual progress record, not an assumed phase.

## P00 - establish the real baseline

```text
Read AGENTS.md, README-START-HERE.md, project-overview.md, docs/00-source-audit.md,
docs/01-requirements.md, docs/14-implementation-sequence.md and docs/16-decisions-and-open-items.md.
Perform P00 only on this existing Laravel repository. Read the actual code and compare it with
SOURCE-EVIDENCE.md. Check git status and preserve all existing edits. Map campaign/homepage,
order ingress/edit/status flows, media paths, dependencies and testing setup. Verify tools and
safe local database configuration without printing .env or contacting production services.
The meeting PDF is now available as sources/client-meeting-provided.pdf; it contains an earlier AI transcript/report, original audio/video unverified, and ends mid feedback after CF-06.
Read docs/19-meeting-source-and-coverage.md, docs/20-meeting-traceability.md, docs/21-client-srs-merged.md and docs/24-codex-meeting-integration-prompts.md; never invent missing meeting parts.
Do not change business logic, migrate/seed a real database, install unreviewed dependencies,
rotate keys, or implement the homepage yet. Produce a source-grounded baseline and identify
what is needed for isolated testing. Update docs/17-progress.md with verified results and blockers.
Explain findings and file impact in Bangla using the eight-part project response format.
```

## P01 - characterize and repair narrow security gaps

```text
Follow AGENTS.md. Implement only P01 after reading the P00 record.
Read docs/03-bugfix-register.md and docs/12-testing-and-acceptance.md.
Create real characterization tests for current home/campaign URLs and relevant existing order
behavior. Reproduce and repair B01 campaign-product membership and B05 GET maintenance
mutations with minimal changes; preserve auth, order assignment, shipping and old URL behavior.
Create a read-only custom-slug collision report before introducing new reserved storefront paths.
Do not claim inventory/idempotency issues fixed before their later schema/service phases.
Run owned tests in the isolated environment. Report changed paths, actual commands/results,
remaining blockers and update docs/17-progress.md. Do not run production actions.
```

## P02 - schema and idempotent backfill

```text
Follow AGENTS.md. Implement P02 only, using database-schema.md, ERD.md,
docs/04-migrations-and-backfill.md and docs/11-file-impact-map.md.
Compare the actual deployed-compatible schema with source before writing new migrations.
Implement expand/widen migrations in M01-M11 order, preserving BDT values, IDs and historical data.
Create/test storefront:backfill-default-variants with dry-run and explicit apply modes;
one default variant per product, stable opening ledger identity and no repeated stock multiplication.
Create a read-only storefront:readiness command. Existing order history stays inventory_version=0.
Do not apply migrations/backfill to production or publish a homepage. Test fresh and upgrade
paths on disposable databases, including repeated backfill and monetary value preservation.
Update ERD/schema if necessary through an explicit recorded decision, not silent divergence.
```

## P03 - inventory and order correctness

```text
Follow AGENTS.md. Implement only P03 using docs/05-products-and-inventory.md,
docs/09-cart-and-checkout.md and the bug register. Build shared InventoryService, VariantResolver,
MoneyCalculator, PricingService, OrderLifecycleService and durable checkout idempotency.
Integrate current campaign/manual-create/manual-edit/status flows; remove duplicate independent
stock decrements. Validate aggregate quantities, membership and variant ownership.
Use deterministic product/variant locks, ledger uniqueness and one transaction; trigger external
side effects only after commit. Retain legacy-order and external-unmapped restrictions.
Test stock-1 concurrency with independent connections on the deployment DB engine, whole-order
rollback, cancellation retries, line deltas, monetary persistence and idempotency conflicts.
No frontend redesign or production cutover in this phase. Report actual results and blockers.
```

## P04 - product options, variants and category administration

```text
Follow AGENTS.md. Implement only P04. Read docs/05-products-and-inventory.md and the schema.
Extend existing Product/Category controllers/forms with a two-level category hierarchy,
size/color/weight/piece-count definitions, values, unique SKU combinations and explicit stock adjustments.
Keep existing product descriptions/media fields. Simple products use a default variant;
variable products require real selections. Converting a default pool must preserve total stock.
Reject category cycles, invalid option ownership, duplicate signatures and stale stock edits.
Add bounded catalog query/DTO support without exposing purchase costs/private fields.
Test all owned catalog/variant cases and update docs/17-progress.md with changed paths/results.
```

## P05 - draft and publication backend

```text
Follow AGENTS.md. Implement only P05 using docs/06-homepage-builder.md,
docs/08-routes-and-contracts.md and database-schema.md.
Build typed section registry, draft cloning, section/item/pivot editing, expected_version conflicts,
private preview authorization, atomic publication and restore-to-draft. Published rows are immutable.
Validate every nested ID against its page/current draft and allowlisted component/settings schema.
Add policies and immutable-media reference protections. No arbitrary HTML/CSS/Blade view selection.
Test draft/publication isolation, stale tabs, cross-page IDs, broken media and restore behavior.
Do not replace the existing campaign landing view or enable storefront mode.
```

## P06 - real admin drag-and-drop and color controls

```text
Follow AGENTS.md. Implement only P06. Read docs/06-homepage-builder.md and
 docs/07-section-theme-and-widgets.md. Keep the AdminLTE shell and use reviewed, pinned SortableJS.
Create section palette, page preview, settings inspector, desktop/mobile preview and Save Draft/Publish UI.
Support section, product, category-tile, slide/review and widget/nav ordering with tap/keyboard alternatives.
Each section must expose background, heading/text/accent, primary/secondary button/hover/border
and card color controls, with hex input and inherit/reset behavior. Only allowed typed values reach APIs.
Wire actual saves, version conflicts, loading/errors and failed-save rollback; no mock success alerts.
Use the shared style resolver for preview/live. Test persistence, palette isolation and accessibility.
```

## P07 - separate public storefront

```text
Follow AGENTS.md. Implement only P07 from docs/02-architecture.md,
docs/07-section-theme-and-widgets.md and docs/11-file-impact-map.md.
Create separate storefront layout/home/product/category/shop views and isolated assets.
Meet PDF F-003/F-004/F-005/F-007: 4/2 product grid, 25/75 card actions, two-part borderless details, descriptions after buttons, sticky Order Now.
Root stays campaign mode until release approval; campaign pages must retain their template and URLs.
Render only published typed sections, live active catalog content and variant-aware available quantities.
Use real configured contacts in a floating + menu (WhatsApp, Messenger, Email), floating cart UI,
and supported mobile nav links. Hide Account/Wishlist unless separately implemented.
Do not copy the reference's device preview frame, copyrighted branding or private admin code.
Test styles/responsiveness/empty states, active-data filtering, media and campaign regression.
```

## P08 - guest cart and storefront checkout

```text
Follow AGENTS.md. Implement only P08 using docs/09-cart-and-checkout.md and route contracts.
Implement server-issued guest-cookie ownership, persistent carts, variant lines, versioned mutation,
server summaries, current-stock warnings and explicit changed-price acknowledgement.
Storefront checkout uses global shipping rows and existing COD/payment/status/success conventions.
Use the P03 inventory/idempotency core; preserve block checks, customer identity and assignment.
Campaign quick order remains separate and never clears or imports this cart.
Test ownership attacks, duplicate submissions, stale tabs, changed stock/price/shipping, and atomic failure.
No payment gateway or customer-account feature is implied. Update progress with actual evidence.
```

## P09 - full regression and release verification

```text
Follow AGENTS.md. Perform only P09. Execute docs/12-testing-and-acceptance.md in isolated environments.
Add the missing browser/integration test tooling deliberately; record pinned versions and scripts.
Run campaign/manual/courier-fixture/report regressions, builder publication/style/permission tests,
cart/checkout abuse tests, real DB concurrency tests and responsive accessibility checks.
Verify money widening/backfill, media retention, route conflicts, no secret leakage, no N+1 growth,
and supported runtime builds/view/route caches. Capture real screenshots after browser execution.
Fix failures in scoped commits with regression tests; never remove assertions or disable auth/CSRF to pass.
List every skipped/blocked case and unresolved P0/P1 issue. No production deployment or zero-error claim.
```

## P10 - controlled staging and release handover

```text
Follow AGENTS.md and docs/13-deployment-and-rollback.md. Perform P10 staging preparation/rehearsal only.
Verify backups, anonymized staging data, CLI/web media paths, feature flags and stock reconciliation.
Rehearse tested migrations/backfill/publish/restore and a schema-compatible rollback.
Do not switch production mode, run production migration/seed, contact real couriers or deploy without
explicit owner approval. Record the meeting-PDF status and acceptance of open business defaults.
Deliver a release checklist, actual test evidence, compatible rollback instructions, changed-file summary,
and truthful remaining limitations in Bangla. Update docs/17-progress.md.
```

## Focused bugfix follow-up prompt

```text
Read AGENTS.md and the current bug/test record. Reproduce the reported issue using the exact error,
request fixture and relevant source; explain meaning/root cause before changing code.
Identify the smallest affected file set, add a failing regression test, implement the fix,
run relevant tests and report actual results. Do not rewrite unrelated features, reset the DB,
change dependency versions without cause, or claim a fix that was not tested.
```

## Meeting-aware gate (mandatory)

For **every** P00-P10 prompt above, read and append the matching insert from [docs/24-codex-meeting-integration-prompts.md](24-codex-meeting-integration-prompts.md). P07/P08/P09 specifically require the 4/2 grids, 25/75 card actions, two-part details, weight/piece variants, scroll sticky **Order Now**, mobile checkout action after form, global/per-section color controls and floating widgets. Record status for F-001..F-011 separately. No verified audio is supplied.
