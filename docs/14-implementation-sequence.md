# Implementation Sequence and Gates

One phase per Codex task. Earlier dependencies must pass before later work is claimed complete. Code lives in the existing project, not a new scaffold. Each handover includes scope, changed files, migration/data impact, commands/results and remaining issues.

| Phase | Work | Dependency | Exit gate |
|---|---|---|---|
| P00 | Re-audit current tree, environment/DB, source delta, scope/PDF gaps | None | Baseline report, safe local env, live-schema unknowns recorded |
| P01 | Characterization tests; campaign membership repair; maintenance HTTP security; route collision audit | P00 | Relevant current-flow/security tests pass, no unrelated rewrite |
| P02 | Expand schema, money widening, migration tests, idempotent backfill command | P01 | Fresh+upgrade migrations and dry-run/repeatability tests pass |
| P03 | Shared inventory/pricing/lifecycle and durable idempotency, integrate current ingress | P02 | Campaign/manual/edit/legacy/race tests; no duplicate decrement |
| P04 | Category hierarchy, option/variant admin and product data/query contract | P03 | Variant CRUD/selection/stock adjustment and hierarchy tests pass |
| P05 | Homepage draft/revision/publish backend, authorization and safe media references | P02/P04 | Draft isolation, cloning, conflicts, restore, permission tests pass |
| P06 | Admin drag-and-drop UI, per-section Design tab, preview and color validation | P05 | Actual persistence, palette isolation, accessible movement pass |
| P07 | Separate storefront render, details/category/shop, widget layout/mobile nav | P04/P05/P06 | Campaign regression, active-data filtering, style/mobile tests pass |
| P08 | Persistent guest cart and checkout using P03 core | P03/P07 | Ownership, cart version, shipping, price-change, idempotency tests pass |
| P09 | Full integration/security/browser/concurrency/performance/release testing | P01-P08 | Mandatory test matrix green; no unresolved release blockers |
| P10 | Approved staging deployment, reconciliation, publication, rollback rehearsal | P09 | Staging evidence + owner approval; production action separate |

## P00 details

Read manifests, routes, core models, all order ingress/status writers, media configuration, actual DB metadata, and any source changes after the supplied hash. Read the newly supplied 32-page meeting AI report in `sources/client-meeting-provided.pdf` and `docs/19`-`docs/26`; do NOT assume audio independently verified. Compare meeting-described proyojonerbazar.com with actual ZIP codebase. Do not ask the user to resend code already present. Detect missing tools/dependencies honestly.

## P01 details

Build real tests for existing URLs, campaign default behavior, manual order editing, contacts/media and source filters. Fix B01 and B05 with narrow changes. Reserve new root slugs only after a collision report; unresolved advertising URL collisions block switching routes. Track B02/B03/B04/B09/B13 for later phases rather than fake a green inventory status.

## P02 details

Implement M01-M11 in order. Add backfill command with dry-run/apply and product-level reconciliation; no production run. All new-feature modes remain disabled. Do not create demo products/reviews or duplicate existing description/contact/source columns. Review currency widening with before/after assertions.

## P03 details

Inventory is a service used by existing campaign/manual flows and later storefront checkout. Include current order edits, status changes, callbacks and external-unmapped policy. Implement legacy-order gates and exact money semantics. Only then expose truthful available-stock features. Race tests run on the agreed DB engine.

## P04 details

Admin can create size/color definitions, explicit variants and audited inventory adjustments. Frontend product DTO returns permitted variants/price/availability and no sensitive purchase costs. Category ordering/reparenting has defined boundaries. No stock multiplication when enabling variable products.

## P05/P06 details

Implement typed sections, child selection pivots, immutable revisions and allowlisted style resolution before wiring drag interactions. Publish is real backend state, not localStorage. All required section types get Design controls. Do not use placeholder success alerts or pretend saving succeeded when no API call was made.

## P07/P08 details

Create separate storefront views/assets. Product cards choose options for variable products; the campaign template is not replaced. Floating cart/contact/nav use shared state and safe-area layout. Guest checkout retains block checks/assignment/history and uses validated global shipping. Do not invent Account/Wishlist/payment gateways.

## P09/P10 details

Run negative, concurrency, mobile and regression cases. Add screenshot artifacts after actual browser testing. Record what remains untested. Production rollout cannot be authorized by an agent's self-reported 'done'; owner approval, reconciled inventory, backup/restore and compatible rollback are required.

## Every phase's handover format

1. Problem/requirement summary in Bangla.
2. Source-grounded explanation and root cause.
3. Changed paths with why/what.
4. Implementation/migration steps and actual diff.
5. Exact commands run, exit/result and tests not run.
6. Requirements/tests closed, blockers left, next permitted phase.

No 'zero errors' or 'fully secure' declaration without scoped evidence. The phase record is the single progress authority.

## Meeting overlay on P00-P10

Additional acceptance contract: [SRS](21-client-srs-merged.md), [UI workflows](22-ui-workflows.md), [meeting test cases](25-meeting-tests.md), and [phase-specific Codex inserts](24-codex-meeting-integration-prompts.md). Apply these before claiming phase exit. At P04 support **weight and piece_count** in addition to size/color; P07 enforce **desktop 4 / mobile 2**, **25/75 card CTA**, **2-part borderless details**, **description after CTA**, **sticky Order Now**, mobile nav and green/per-section colors; P08 enforce desktop sticky summary and mobile button beneath form. Meeting PDF coverage gaps never count as functional test passes.
