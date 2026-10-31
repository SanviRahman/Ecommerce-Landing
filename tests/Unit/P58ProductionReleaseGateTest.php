<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\P58ProductionStorefrontAccess;
use PHPUnit\Framework\TestCase;

final class P58ProductionReleaseGateTest extends TestCase
{
    private function approved(
        string $environment = 'production',
        bool $debug = false,
        bool $enabled = true,
        string $requestHost = 'store.example.test',
        string $expectedHost = 'store.example.test',
        string $appUrl = 'https://store.example.test'
    ): bool {
        return P58ProductionStorefrontAccess::matchesContext(
            $environment, $debug, $enabled, $requestHost, $expectedHost, $appUrl
        );
    }

    public function test_explicitly_approved_production_https_host_is_eligible(): void
    {
        self::assertTrue($this->approved());
    }

    public function test_release_flag_is_off_by_default(): void
    {
        self::assertFalse($this->approved(enabled: false));
    }

    public function test_local_development_cannot_impersonate_production(): void
    {
        self::assertFalse($this->approved(environment: 'local'));
        self::assertFalse($this->approved(environment: 'testing'));
    }

    public function test_debug_mode_blocks_live_release(): void
    {
        self::assertFalse($this->approved(debug: true));
    }

    public function test_unknown_host_is_denied(): void
    {
        self::assertFalse($this->approved(requestHost: 'attacker.example.test'));
        self::assertFalse($this->approved(requestHost: 'www.store.example.test'));
    }

    public function test_app_url_must_match_canonical_https_host(): void
    {
        self::assertFalse($this->approved(appUrl: 'http://store.example.test'));
        self::assertFalse($this->approved(appUrl: 'https://other.example.test'));
    }

    public function test_invalid_host_approval_is_rejected(): void
    {
        self::assertFalse($this->approved(expectedHost: ''));
        self::assertFalse($this->approved(expectedHost: 'evil host'));
    }

    public function test_host_case_is_normalized_without_changing_domain_identity(): void
    {
        self::assertTrue($this->approved(requestHost: 'STORE.EXAMPLE.TEST'));
    }
}
