# Sources and Technical References

Prepared 2026-10-24. Distinguish uploaded-source observations from external framework guidance and proposed architecture.

## Uploaded evidence

- Target `website(1).zip` and earlier `website.zip`: equal hashes; current source paths/line snippets in [SOURCE-EVIDENCE.md](../SOURCE-EVIDENCE.md).
- Legacy reference `public_html leatherket(1).zip`: selected Section/Product/Website controller behavior reviewed; do not copy wholesale.
- Four earlier screenshots: Leatherket section table and HT Bazar storefront/mobile elements.
- Two current screenshots: Sunnah Digital Agency Hero/Spotlight admin color controls. These are visual input only; no private admin backend was accessed.
- Client meeting PDF: now preserved as [`sources/client-meeting-provided.pdf`](../sources/client-meeting-provided.pdf). It is an earlier AI-generated transcript/report from a conversation, **not independently verified meeting audio**; the 32-page document stops after CF-06. See [meeting source notes](19-meeting-source-and-coverage.md).

## Public/reference site

[R1] [HT Bazar public homepage](https://htbazar.com/) - inspected public page and supplied screenshots for category products, reviews, cart and navigation patterns. This is not evidence about the site's private database, framework or implementation quality. No attempt was made to access private admin routes.

## Primary technical references consulted

[R2] [OpenAI: Custom instructions with AGENTS.md](https://developers.openai.com/codex/guides/agents-md/) - official entrypoint redirects to ChatGPT Learn's AGENTS.md guidance. Standard project instruction filename is AGENTS.md; keep the root file concise and refer to detailed phase docs.

[R3] [Laravel 13 validation](https://laravel.com/docs/13.x/validation) - FormRequest structure, validated input and validation error responses. This supports the request boundary, not a claim that input is safe without project-specific rules.

[R4] [Laravel 13 query builder](https://laravel.com/docs/13.x/queries) - pessimistic locking and transactions inform inventory locking; correct engine-level behavior still needs concurrency tests.

[R5] [Laravel 13 authorization](https://laravel.com/docs/13.x/authorization) - gates/policies for resource actions. Existing custom roles still need an explicit policy bridge.

[R6] [Laravel 13 CSRF protection](https://laravel.com/docs/13.x/csrf) - preserve configured request-forgery defenses for session-authenticated changes; do not exempt builder/cart APIs.

[R7] [SortableJS official repository](https://github.com/SortableJS/Sortable) - sortable lists, drag handles and touch interactions. It does not itself implement the project's persistence, authorization or accessible non-drag controls.

[R8] [W3C: Dragging movement alternatives](https://www.w3.org/WAI/WCAG22/Techniques/general/G219) - provide click/tap alternatives such as Move Up/Down, not drag-only interaction.

[R9] [W3C: Contrast minimum](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) - normal text minimum 4.5:1, large text 3:1; image backgrounds need contextual checks.

## Evidence limits

Only successful page retrievals informed external factual claims. Two attempted Spatie documentation fetches did not return readable pages in the review tool; media recommendations here rely on inspected project code and conservative design, not a claim of those pages being read. Consult the installed package's official docs during implementation if changing package-specific behavior.

No external source proves this application is bug-free, secure or production-ready. The proposed schema/limits/phases are engineering decisions documented in this pack, not facts taken from a meeting PDF.
