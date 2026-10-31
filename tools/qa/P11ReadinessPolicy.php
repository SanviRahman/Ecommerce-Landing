<?php

declare(strict_types=1);

/**
 * P11 aggregate-only readiness rules. No Laravel, database connection, or I/O.
 * A candidate is suitable for an OWNER-APPROVED staging rehearsal, never a
 * production activation or a claim of completed operational checks.
 */
final class P11ReadinessPolicy
{
    /** @var array<string, string> */
    private const MUST_BE_ZERO = [
        'active_legacy_products' => 'Active products still use legacy inventory; backfill and reconciliation require review.',
        'managed_stock_mismatches' => 'Managed product stock does not match its variant stock mirror.',
        'invalid_simple_defaults' => 'Managed simple products lack exactly one valid active default variant.',
        'invalid_variable_options' => 'Active variable products do not have complete purchasable option variants.',
        'broken_published_revisions' => 'A published homepage points to an invalid, foreign, or non-published revision.',
    ];

    /** @var array<string, string> */
    private const MUST_BE_POSITIVE = [
        'active_managed_products' => 'No active managed products are available for the storefront.',
        'global_shipping_areas' => 'No active global delivery area is configured.',
        'published_homepages' => 'No pinned, published storefront homepage revision exists.',
    ];

    /**
     * @param array<string, int> $metrics
     * @return array{status: string, blockers: list<string>}
     */
    public static function evaluate(array $metrics): array
    {
        $required = array_merge(array_keys(self::MUST_BE_ZERO), array_keys(self::MUST_BE_POSITIVE));
        foreach ($required as $key) {
            if (! array_key_exists($key, $metrics) || ! is_int($metrics[$key]) || $metrics[$key] < 0) {
                throw new InvalidArgumentException('P11 metrics are missing or invalid: ' . $key);
            }
        }
        $blockers = [];
        foreach (self::MUST_BE_ZERO as $key => $message) {
            if ($metrics[$key] !== 0) {
                $blockers[] = $key . ': ' . $message;
            }
        }
        foreach (self::MUST_BE_POSITIVE as $key => $message) {
            if ($metrics[$key] === 0) {
                $blockers[] = $key . ': ' . $message;
            }
        }
        return [
            'status' => $blockers === [] ? 'STAGING_DATA_CANDIDATE_ONLY' : 'BLOCKED_DATA_READINESS',
            'blockers' => $blockers,
        ];
    }
}
