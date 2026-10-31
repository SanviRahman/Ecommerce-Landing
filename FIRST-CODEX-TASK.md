# First Codex Task - P00

Paste the following task into Codex opened at the existing Laravel repository root. `AGENTS.md` must be there too.

```text
Read AGENTS.md, README-START-HERE.md, project-overview.md, docs/00-source-audit.md,
docs/01-requirements.md, docs/14-implementation-sequence.md and docs/16-decisions-and-open-items.md.
Perform P00 only: inspect the current source, preserve existing edits, verify source differences,
map campaign/homepage/order/media flows and establish a safe isolated testing environment.
Do not print or reuse uploaded .env credentials, modify business logic, run production migrations,
seed data or rotate APP_KEY. Meeting PDF is now in `sources/client-meeting-provided.pdf` and analyzed in `docs/19` through `docs/26`; it is an earlier AI-produced report, not independently verified meeting audio.
Compare findings with SOURCE-EVIDENCE.md and update docs/17-progress.md with actual checks and blockers.
Explain in Bangla using the project's eight-part response structure.
```

Continue with P01 only after P00's actual prerequisites are met. All subsequent tasks are in [docs/15-codex-prompts.md](docs/15-codex-prompts.md).

## Supplementary P00 requirement

Read the merged meeting documents `docs/19-meeting-source-and-coverage.md`, `docs/20-meeting-traceability.md`, `docs/21-client-srs-merged.md`, and `docs/23-decisions-conflicts.md`; use the P00 insert in `docs/24-codex-meeting-integration-prompts.md`. Verify the PDF's referenced proyojonerbazar.com is actually the deployed revision of the supplied campaign ZIP before assuming existing shop routes/views. No migrations in P00.
