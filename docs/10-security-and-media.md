# Security, Validation and Media Rules

## Authorization

Existing auth uses `users.role` through RoleMiddleware and is_active. Start builder/variant/stock administration as active-admin-only, adding policies/gates for resource scope. Do not assume that hiding a menu or installing a permission package enforces access. Employees retain existing order permissions but do not acquire homepage publish permission by default.

Authorize before processing uploads or mutating records. A section/item/revision ID must belong to the selected page and current draft. Cross-page IDs receive denial. Guest cart operations scope through the protected ownership cookie. Existing customer records do not authorize customer-facing account access.

## Input and query rules

Use FormRequests with strict allowed nested fields and bounded arrays. Persist validated data, not request->all(). Protect fillable/guarded properties from owner/revision/stock foreign-key injection. Eloquent/bound queries only; map sorting/filter operators from an allowlist instead of concatenating raw order-by strings.

Require CSRF for all session-authenticated mutations. Do not exempt new builder/cart endpoints. Existing courier webhook exceptions remain isolated and must still verify their provider credentials/signatures. Disable production HTTP migration/seeding; safe maintenance endpoints must be POST+CSRF+role protection, not GET links.

## Theme and content injection

Colors accept only `^#[0-9A-Fa-f]{6}$` or explicit null inheritance. URLs use allowed HTTPS destinations or validated local routes; reject scheme-relative URLs, script/data URLs for links, encoded path tricks and invalid contact destinations. Do not allow the client to select an arbitrary Blade view/component class or filesystem path.

Plain descriptions/headings use escaped Blade `{{ }}`. Rich text, if needed, uses an audited server-side allowlist sanitizer; do not substitute `strip_tags` for robust sanitization of allowed HTML attributes. Review existing raw campaign HTML slots before expanding them. Product data inserted into JavaScript uses safe JSON serialization, not hand-built unescaped strings.

## Uploads

Reuse existing Spatie media and custom path generators. Validate actual image type/MIME, extension consistency, byte size and decodeability; accept JPEG/PNG/WebP. Proposed builder limit 6 MB, product existing limit 4 MB unless a documented change is approved. Reject SVG by default, executable/polyglot files, unexpected archives and oversized decoded dimensions. Use safe generated filenames; user strings never determine executable storage paths.

Public media files must not execute PHP/HTML server-side. Serve with appropriate content type and no-sniff where deployment permits. Do not fetch arbitrary remote image URLs from the server; remote ingestion is a separate SSRF-sensitive feature. Use fake/local fixtures in tests.

## Revision-safe media lifetime

Builder content items reference approved public media IDs. A replacement creates a new media row/file and updates only the draft item; it does not mutate bytes used by a published revision. Deleting/replacing in MediaManagementController must respect published/retained revision references. Soft-trash referenced media is blocked or retained visibly; hard deletion requires a reference-safe cleanup workflow.

Cloning a revision shares immutable media references. Garbage collection deletes only unreferenced, retention-expired media after a dry-run. Do not cascade delete a shared file merely because one section is deleted. Protect originals and conversions; keep old URLs alive for retained published versions.

The project writes directly to a configurable public storage root. CLI and web must use consistent PUBLIC_DISK_ROOT/PUBLIC_DISK_URL on the actual host. No broad storage relocation or `storage:link` without reviewing this setup. Cache/version behavior must not break existing product/campaign URLs.

## Privacy, logging and abuse

Exclude `.env`, courier/API secrets, raw guest tokens, request credentials, contact/address dumps and database files from commits/artifacts. A real leaked credential should be rotated deliberately by its owner; do not blindly regenerate APP_KEY and break encryption.

Use structured request IDs/status/error categories. Review the existing lifecycle logger's `path`: URLs such as success/{token} may expose capability-like success tokens in logs. Redact sensitive path segments in production logs and access-log policies. Restrict log access/retention. Do not log complete checkout payloads.

Use bounded rate limits and idempotency for checkout; retain existing customer blocks/validation. Do not expose inventory admin adjustments or draft previews publicly. Contact links are navigation, not a requirement to embed third-party tracking scripts. Review existing pixel code permissions separately.

## Security acceptance

Guest admin mutations denied; employee publish denied; foreign resource IDs rejected; missing/invalid CSRF denied; bad color/URL/HTML/file payloads inert/rejected; cart IDOR denied; forged variant/price/quantity rejected; identical checkout retry deduplicated; raw errors/secrets not returned. See docs/12-testing-and-acceptance.md for case IDs.

## P15 local-only report export confidentiality (2026-10-25)

CSV/PDF reports may contain confidential order/customer information; new generated files are written to the existing `local` disk (`storage/app/private/reports/`) and downloaded only through the authenticated, active-admin `ReportController::download()` route. Public media stays on the existing configured public disk; DO NOT relocate public product/campaign files. Exported text cells, including metadata and filters, are serialized through `SafeCsvCell` before CSV quoting to prevent spreadsheet formula interpretation.

Historical `file_disk=public` report records are deliberately preserved for download compatibility. A separate approved maintenance workflow is needed to inventory/migrate existing `public/storage/reports/` exports and record the new private path; do not delete or rewrite originals without backups. `public/.htaccess` forbids direct `/storage/reports/` reads on Apache, **but Nginx, CDN, PHP `artisan serve`, Windows/IIS or a custom document root require independent server-level deny rules or verified legacy migration**. Never call this server-agnostic remediation without actual HTTP verification on the deployment server.
