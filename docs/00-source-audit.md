# Source Audit and Verification Boundary

Audit date: 2026-10-24. This is a static review of supplied source, not a penetration test or running-site certification.

## Archive identity

| Input | SHA-256 | Entries |
|---|---|---:|
| website(1).zip | `349b43d5ad81f83480712c6fd618a4d95c39ba368dbdec6eba291d9cbe190004` | 773 |
| public_html leatherket(1).zip | `0f7f4a664be0f97dfe812db9dbac09b3c6af8a939b04a6e102bb02b12969556f` | 562 |

The two website ZIP uploads have identical SHA-256 checksums. The target is rooted directly at `artisan`/`composer.json`, not in an extra `website/` directory. No PDF or DOCX was found in either inspected archive. Attachment metadata also contained no meeting PDF.

## Verified observations

| Evidence | Observation | Implication |
|---|---|---|
| E01 | Manifest/lock contain Laravel 13.7.0 and PHP ^8.3 | Preserve compatible dependencies; verify actual runtime separately |
| E02-E03 | Homepage and public campaign use the same Blade | Introduce a separate storefront view |
| E04 | Product has stock, weight_size and existing descriptions | Do not duplicate descriptions; weight_size is not variant inventory |
| E05 | Category schema lacks parent_id | Extend categories, do not build a duplicate taxonomy |
| E06 | Public campaign submission accepts global active-product fallback | Campaign isolation must be enforced |
| E07 | Public campaign submission lacks inventory decrement/lock | Availability display alone cannot prevent overselling |
| E08 | OrderManagementService checks then decrements without locking; reviewed ingress does not uniformly call it | Consolidate inventory rules, including manual/order-edit paths |
| E09 | GET command routes invoke mutations behind admin auth | Preserve auth but remove GET side effects |
| E10 | Custom campaign root matching and reserved list exist | Check slug conflicts before adding shop paths |
| E11 | Public storage uses custom direct paths and links=[] | No automatic storage:link/move |
| E12 | SiteSetting has phone, WhatsApp, Messenger and email | Reuse contact fields |
| E13 | Product/order amount columns largely use integers; discounts decimal and manual calculations use floats | Verify live types; widen order totals preserving BDT semantics; deterministic arithmetic |
| E14 | Existing created_via values and reports/integrations | Do not add a duplicate source_type column |
| E15 | Browser selectedProducts object is page-local | Add separate persistent storefront cart |
| E16 | Reference sections use category/max/numeric sort | It is not an existing drag-and-drop builder to copy |
| E17 | Reference campaign handler clears cart | Do not import that behavior |
| E18 | Raw Blade HTML slots exist | Audit sanitizer path; do not declare exploitation without proving untrusted input reaches it |

See [SOURCE-EVIDENCE.md](../SOURCE-EVIDENCE.md) for source excerpts and paths. Absence statements are based on archive inventory, reviewed model/migration/controller/route sets and targeted searches, not production introspection.

## Runtime checks performed

- Parser: `PHP 8.4.24 (cli) (built: Jul 31 2026 05:11:11) (NTS)`.
- `php -l` ran on 234 PHP files under app/bootstrap/config/routes/database; 234 passed, 0 failed.
- Blade templates are excluded from the PHP parser count; templates still need `view:cache` and actual rendering tests.
- Composer/package manifests and locks parsed as JSON.
- Bundled SQLite opened read-only for table names only; it contains migrations/users/sessions/cache/jobs support tables, not the commerce tables described by source migrations.
- Application entrypoints were not executed; supplied `.env` was not loaded, printed or copied into deliverables.

## Not performed

Composer install/audit, vendor platform checks, Artisan boot, real DB schema validation, migration execution, seed execution, PHPUnit, asset build, browser interaction, load tests, concurrent stock tests, courier calls, external order sync and deployment. These remain implementation/release gates.

`tests/` is absent although phpunit.xml references tests/Unit and tests/Feature. Do not report an empty suite as successful coverage. Dependencies are absent; this does not prove a production deployment lacks them.

## Review limitations and source hygiene

The archive includes `.env`; never share it in the documentation pack or source repository. Only filenames/presence were needed. Security-sensitive media/HTML paths require targeted tests before assigning exploitability. A passed PHP syntax check proves parseability on the stated parser, not correct Laravel runtime behavior.


## Later source addition (2026-10-24)

The original ZIP archives had no meeting PDF, but the user subsequently uploaded a **separate 32-page PDF**. It is preserved in `sources/client-meeting-provided.pdf`. Its contents are a prior AI transcript/report rather than the original audio/video and end after a partial CF-06 feedback row; see docs/19-26. This addition **does not change** the hashes/code inspection results recorded above.
