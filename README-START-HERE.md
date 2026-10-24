# Laravel Storefront Upgrade - Start Here

**Delivery:** a source-grounded implementation specification for Codex, not an implemented application patch.
**Prepared:** 24 October 2026. **Target:** `website(1).zip`. **Pack version:** 2.0 (meeting-merged).

**Bangla guide:** [START-HERE-BN.md](START-HERE-BN.md). **Meeting merge summary:** [MEETING-MERGE-SUMMARY.md](MEETING-MERGE-SUMMARY.md). **First pasteable task:** [FIRST-CODEX-TASK.md](FIRST-CODEX-TASK.md).

## What this pack does

Adds a structured drag-and-drop shopping homepage, per-section background/button/theme controls, category/subcategory sorting, size/color variants, reliable inventory, storefront cart, product details, and a floating WhatsApp/Messenger/Email menu. Existing campaign pages and operational order workflows must remain functional.

The **32-page meeting PDF is now supplied**, preserved in `sources/client-meeting-provided.pdf`, and reconciled in `MEETING-MERGE-SUMMARY.md`, `docs/19-meeting-source-and-coverage.md`, `docs/20-meeting-traceability.md` and `docs/21-client-srs-merged.md`. It contains an earlier AI-produced transcript/report, **not original meeting media**, and cuts off mid feedback table after CF-06. Do not invent missing meeting conclusions or claim verified audio. The client visual contract is more specific than the prior preliminary scope: 4 desktop/2 mobile cards, 25% cart/75% Buy Now, 2-part product layout, sticky Order Now, desktop/mobile checkout ordering and green-like theme.

## Use with your existing project

1. Back up source, database and uploaded media. Work on a separate branch and isolated development database.
2. Open the existing Laravel project root in VS Code: the directory containing `artisan`, `composer.json`, `app/` and `routes/`.
3. Copy this pack's **contents** into that root. This package contains documentation and audit reports, not replacement application files. Keep the existing application `README.md`; this guide has a different name.
4. If `AGENTS.md` already exists, merge its instructions deliberately; do not overwrite unrelated project rules. The supplied target ZIP does not contain one.
5. Keep `AGENTS.md` in the project root, not only inside a docs/download folder. `agnet.md` is a human-facing spelling alias, not the standard agent instruction filename.
6. Start Codex in the project root and use the first prompt in [the prompt sequence](docs/15-codex-prompts.md). Implement **one phase per task**, not the whole application in one unrestricted run.
7. After each phase require a diff, changed-file explanation, executed commands, actual results, remaining blockers and an updated [progress record](docs/17-progress.md).
8. Run the release gates in [testing](docs/12-testing-and-acceptance.md) and [deployment](docs/13-deployment-and-rollback.md) before enabling storefront mode on production.

## Read/implementation sequence

| Order | File | Purpose |
|---|---|---|
| 0 | [Meeting merge summary](MEETING-MERGE-SUMMARY.md) | New 32-page PDF integration, limits and approval gates |
| 0a | [Client meeting SRS](docs/21-client-srs-merged.md) | Meeting-report feature contract, separate from proposed implementation |
| 0b | [Meeting task inserts](docs/24-codex-meeting-integration-prompts.md) | Append phase-specific acceptance and test instructions |
| 1 | [AGENTS.md](AGENTS.md) | Repository-level agent rules and safety constraints |
| 2 | [project-overview.md](project-overview.md) | Goals, scope, preserved features, architecture choices |
| 3 | [00-source-audit.md](docs/00-source-audit.md) | Verified code observations and limits of this review |
| 4 | [01-requirements.md](docs/01-requirements.md) | Requirement IDs, screenshot mapping, acceptance criteria |
| 5 | [02-architecture.md](docs/02-architecture.md) | Storefront/campaign separation and service boundaries |
| 6 | [ERD.md](ERD.md) | Existing and proposed relationships in Mermaid |
| 7 | [database-schema.md](database-schema.md) | Column-level target schema, keys and invariants |
| 8 | [03-bugfix-register.md](docs/03-bugfix-register.md) | Prioritized, evidence-backed repair tasks |
| 9 | [04-migrations-and-backfill.md](docs/04-migrations-and-backfill.md) | Safe migration order and old-data reconciliation |
| 10 | [05-products-and-inventory.md](docs/05-products-and-inventory.md) | Variant identity, pricing, stock lifecycle |
| 11 | [06-homepage-builder.md](docs/06-homepage-builder.md) | Editing, sorting, revisions, preview, publish |
| 12 | [07-section-theme-and-widgets.md](docs/07-section-theme-and-widgets.md) | Per-section colors, mobile UI and floating controls |
| 13 | [08-routes-and-contracts.md](docs/08-routes-and-contracts.md) | Routes, validation, payload and error contracts |
| 14 | [09-cart-and-checkout.md](docs/09-cart-and-checkout.md) | Ownership, persistence, idempotency, campaign isolation |
| 15 | [10-security-and-media.md](docs/10-security-and-media.md) | Auth, CSRF, uploads, safe content and media lifetime |
| 16 | [11-file-impact-map.md](docs/11-file-impact-map.md) | Existing/new paths and reasons for changes |
| 17 | [12-testing-and-acceptance.md](docs/12-testing-and-acceptance.md) | Concrete automated/manual/concurrency test matrix |
| 18 | [13-deployment-and-rollback.md](docs/13-deployment-and-rollback.md) | Safe commands, staging, deploy and operational rollback |
| 19 | [14-implementation-sequence.md](docs/14-implementation-sequence.md) | Phases, dependencies and completion gates |
| 20 | [15-codex-prompts.md](docs/15-codex-prompts.md) | Copyable tasks, ordered by phase |
| 21 | [16-decisions-and-open-items.md](docs/16-decisions-and-open-items.md) | Explicit defaults and unresolved client questions |
| 22 | [17-progress.md](docs/17-progress.md) | Honest implementation/test status template |
| 23 | [18-references.md](docs/18-references.md) | Source provenance and official technical references |
| 24 | [SOURCE-EVIDENCE.md](SOURCE-EVIDENCE.md) | Source excerpts with stable paths and line numbers |

`databasechema.md` points to the canonical schema. `agnet.md` points to the canonical agent rules. Do not maintain divergent duplicate specifications.

## What was actually tested here

234 non-Blade PHP files under `app`, `bootstrap`, `config`, `routes`, and `database` passed `php -l` with PHP 8.4.24. No Composer dependencies were installed and the application was not booted. Migrations, PHPUnit, production-DB concurrency tests, npm build, browser tests and deployment were **not run**. See [the audit](docs/00-source-audit.md).

No promise of a zero-error application is implied. The pack supplies specific fixes and test gates; successful implementation and test evidence are required before marking a bug fixed.

## Additional navigation and visual context

[Rules](rules.md), [bugfix entry](bugfix.md), [constraints](constraints.md), and [supplied reference screenshots](references/README.md). The screenshots are analysis references, not storefront assets.
