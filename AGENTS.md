# Repository Instructions for Codex

## Mission and authority

Upgrade this existing Laravel campaign/order application with a separate storefront and a structured homepage builder. Read `README-START-HERE.md`, `project-overview.md`, `docs/00-source-audit.md`, `docs/01-requirements.md`, and the current phase before changing code.

The user communicates in Bangla. Explain work/results in clear Bangla; keep code, identifiers and technical contracts professional. Do not start with a large code dump: state the problem, evidence, file impact and solution first. Final task summaries use: Problem Summary, Explanation, Root Cause, Files, Steps, Code/Diff, Commands, Tests.

## Ground truth

- Uploaded target: `website(1).zip`, identical to earlier `website.zip` at audit time. Re-read working files; source may have changed after the snapshot.
- Locked Laravel: 13.7.0; manifest PHP ^8.3. Preserve dependencies unless the phase explicitly requires a small, reviewed addition.
- Blade/AdminLTE are the existing UI stack. Do not rewrite as React, Next.js, a new Laravel project, or an unrestricted page builder.
- Meeting PDF now provided as `sources/client-meeting-provided.pdf`: it reproduces a **prior AI transcript/report** of a 14:07 meeting, original audio/video unavailable and report truncated after CF-06 on p32. Read `docs/19-meeting-source-and-coverage.md`, `docs/20-meeting-traceability.md` and `docs/21-client-srs-merged.md`. Attribute timestamps to the PDF report; never claim audio verified, or invent missing meeting parts.
- Source excerpts in `SOURCE-EVIDENCE.md` describe the baseline, not necessarily the latest working tree.

## Required workflow

1. Read relevant files and classify observations as verified, inferred risk, or proposed change.
2. Check `git status`; preserve user edits. Do not use destructive checkout/reset or overwrite unrelated changes.
3. Take one phase from `docs/14-implementation-sequence.md`; state allowed files and tests.
4. Add a failing regression test for each confirmed bug, then implement the smallest coherent fix.
5. Use actual existing paths; proposed paths in the pack are new files, not claims of current existence.
6. Update contracts/schema/ERD together if the design changes; record an ADR in `docs/16-decisions-and-open-items.md`.
7. Run allowed tests in an isolated environment. Record exact commands, exit status and limitations. Missing dependencies are blockers, not test passes.
8. Update `docs/17-progress.md`; never mark later phases complete just because interfaces or placeholder files exist.

## Non-negotiable constraints

- Preserve campaign URLs, current landing rendering, order tracking, COD semantics, courier integration, external sync, employee assignment and reports.
- Keep new storefront mode disabled until backfill and integration gates pass. Do not replace `frontend/pages/home.blade.php` with a shop.
- No production migrations, seeding, destructive cleanup, external API calls or production deployment without explicit operational approval.
- Never run `migrate:fresh`, `db:wipe`, broad `db:seed`, `composer setup`, or `key:generate` against an existing deployment. Never rotate an existing APP_KEY as part of installation.
- Do not copy, display, log or commit `.env`, credentials, customer data, courier tokens or exported production databases. Use `.env.example` and fake test fixtures.
- Preserve custom public media paths; inspect `config/filesystems.php`. Do not blindly run `storage:link` or move/delete referenced media.
- Do not copy legacy Leatherket controllers/package assumptions. Its namespace/schema differ; it is a behavioral reference.
- Reuse `orders.created_via`; do not add a redundant source column. Reuse existing descriptions, Spatie media, contact fields and category table.
- All checkout inputs are untrusted. Compute price/discount/shipping on the server. Validate variant ownership, active state, campaign membership and quantity.
- Every inventory-consuming path must share transaction-safe inventory handling. Never maintain independent unsynchronized stock counters.
- Never re-deduct historical orders or automatically restock legacy orders with unknown inventory history.
- Require admin authorization, CSRF and scoped validation on builder mutations. No GET mutations. No arbitrary HTML, Blade, JS or CSS execution from builder data.
- Theme values are allowlisted #RRGGBB tokens or null inheritance; raw CSS strings/selectors are prohibited.
- Dragging must have up/down or move-to alternatives, touch support, focus handling, and failed-save rollback.
- Published revisions are immutable. Draft saves cannot affect public rendering. Use revision/version conflicts, not last-write-wins.
- Review third-party dependency/install scripts before executing them. `composer install` respects the lock; do not replace it with `composer update`.
- Test double-submission and concurrent last-item purchases against the deployment DB engine, not SQLite alone.
- **Meeting-specific UI gates:** 4 desktop/2 mobile card columns on homepage, 25/75 Cart-vs-Buy card actions, two-part borderless product detail, descriptions after actions, scroll-sticky `Order Now`, sticky mobile header/nav, desktop sticky summary and mobile form→submit→summary, default green-like theme (exact HEX pending). Preserve current Shop by Category design.
- Support color/size/weight/piece-count option variants. Do not infer 2 kg price as twice 1 kg or that 2-piece pack equals cart quantity=2. Require approved unit/stock mapping before inventory release.
- Separate contact + (WhatsApp/Messenger/Email required when configured; Call optional) from mini-cart drawer. New controls require real server state, no fake badges.
- Consult `docs/25-meeting-tests.md` and record M F-001..F-011 alongside R-IDs. PDF's cited site and inspected ZIP are not proven to be the same deployed codebase.

## Testing/reporting rules

Baseline syntax success is not functional success. A feature is complete only after its acceptance tests pass. Test hidden/inactive/deleted products, variants, category cycles, all section colors, publish isolation, stale tabs, pricing changes, stock deltas, cart ownership, mobile overlap and security rejection paths.

Use `docs/12-testing-and-acceptance.md` for concrete cases and `docs/13-deployment-and-rollback.md` for environment-safe commands. Leave an honest BLOCKED status when a test cannot run. Do not claim all errors are solved or silently disable assertions/CSRF to make tests green.

## Specification navigation

- Data authority: `database-schema.md`, `ERD.md`.
- Route/API authority: `docs/08-routes-and-contracts.md`.
- Inventory authority: `docs/05-products-and-inventory.md`.
- Builder authority: `docs/06-homepage-builder.md`.
- Colors/widgets authority: `docs/07-section-theme-and-widgets.md`.
- Sequence: `docs/14-implementation-sequence.md` and `docs/15-codex-prompts.md`.
- Source evidence: `SOURCE-EVIDENCE.md`; external references: `docs/18-references.md`.

## Updated meeting documentation navigation

Start with `MEETING-MERGE-SUMMARY.md`, `docs/19-meeting-source-and-coverage.md`, `docs/20-meeting-traceability.md`, `docs/21-client-srs-merged.md`, `docs/22-ui-workflows.md`, `docs/23-decisions-conflicts.md`, `docs/24-codex-meeting-integration-prompts.md`, `docs/25-meeting-tests.md` and `docs/26-meeting-transcript-and-feedback-index.md`. Each P00-P10 step must use the specific meeting inserts for that phase.

- The user-provided meeting PDF inside `sources/` may be confidential. Do not auto-commit or upload it to a public repository; keep a private local copy or obtain explicit client authorization. Do not expose its external links, names or private discussion in public logs or test fixtures.
